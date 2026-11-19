<?php
/**
 * EGroupware Api: limiting Link::query() to the entries linked to one other entry
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Link;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use PHPUnit\Framework\Attributes\DataProvider;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * $options['filter']['linked'] asks for only the entries linked to one other entry, eg. the
 * InfoLogs of a project. Link::query() does not search itself: it turns the filter into the ids
 * of the linked entries and hands those to the app's own search in $options['ids'].
 *
 * Every app is handed the ids. One whose search does not support them ignores them and answers
 * with everything it found, as it does without the filter. A filter naming no entry, or one the
 * user may not see, hands over an empty list of ids: nothing to search, not everything. A filter
 * naming several entries hands over the ids linked to any of them.
 *
 * Setup: three apps are registered here, so the test does not depend on which apps an install
 * has - one searched app honouring ids, one ignoring them, and the app of the entry linked to.
 * Real links between them are stored through Link\Storage and removed again in tearDown().
 * Both searched apps record the options they were called with, which is what is asserted.
 */
class QueryLinkedTest extends LoggedInTest
{
	// App names are stored with the links, in a column of 16 characters. Links are only
	// returned for apps the user has rights for, going by the name up to the first dash.
	const APP_WITH_IDS = 'api-linked-ids';
	const APP_WITHOUT_IDS = 'api-linked-not';
	const TARGET_APP = 'api-linked-to';

	/**
	 * Entries of both searched apps linked to the target
	 */
	const LINKED = array(11, 12, 13);

	/**
	 * Target id the user has no access to: its title is false
	 */
	const NO_ACCESS = 'no-access';

	/**
	 * Target entry the links hang off, unique per test so runs can not see each other's links
	 *
	 * @var string
	 */
	protected $target;

	/**
	 * Options of every call to a searched app's query callback
	 *
	 * @var array[]
	 */
	protected static $calls = array();

	protected function setUp() : void
	{
		parent::setUp();

		self::$calls = array();
		$this->target = 'phpunit'.bin2hex(random_bytes(6));

		$register = new \ReflectionProperty(Api\Link::class, 'app_register');
		$register->setAccessible(true);
		$apps = $register->getValue();
		$apps[self::APP_WITH_IDS] = array(
			'name'      => self::APP_WITH_IDS,
			'query'     => array(self::class, 'query'),
			'title'     => array(self::class, 'title'),
		);
		$apps[self::APP_WITHOUT_IDS] = array(
			'name'  => self::APP_WITHOUT_IDS,
			'query' => array(self::class, 'queryIgnoringIds'),
			'title' => array(self::class, 'title'),
		);
		$apps[self::TARGET_APP] = array(
			'name'  => self::TARGET_APP,
			'title' => array(self::class, 'title'),
		);
		$register->setValue(null, $apps);

		try
		{
			$no_access = self::NO_ACCESS;	// link() takes its ids by reference
			foreach(array(self::APP_WITH_IDS, self::APP_WITHOUT_IDS) as $app)
			{
				foreach(self::LINKED as $id)
				{
					Storage::link(self::TARGET_APP, $this->target, $app, $id);
				}
				// what must never be found: an entry linked to something the user may not see
				Storage::link(self::TARGET_APP, $no_access, $app, 99);
			}
		}
		catch(\Throwable $e)
		{
			// tearDown() is not run for a failed setUp(), and would leave the links behind
			$this->removeLinks();
			throw $e;
		}
	}

	protected function tearDown() : void
	{
		$this->removeLinks();

		parent::tearDown();
	}

	protected function removeLinks() : void
	{
		Storage::unlink(0, self::TARGET_APP, $this->target);
		Storage::unlink(0, self::TARGET_APP, self::NO_ACCESS);
	}

	/**
	 * A searched app: has entries 1-20 and honours $options['ids'], recording how it was called
	 *
	 * @param string $pattern
	 * @param array $options
	 * @return array
	 */
	public static function query($pattern, array &$options = array())
	{
		unset($pattern);
		self::$calls[] = $options;

		$all = array();
		foreach($options['ids'] ?? range(1, 20) as $id)
		{
			$all[$id] = 'Entry '.$id;
		}
		return $all;
	}

	/**
	 * A searched app not supporting ids: always answers with its entries 1-20
	 *
	 * @param string $pattern
	 * @param array $options
	 * @return array
	 */
	public static function queryIgnoringIds($pattern, array &$options = array())
	{
		unset($pattern);
		self::$calls[] = $options;

		$all = array();
		foreach(range(1, 20) as $id)
		{
			$all[$id] = 'Entry '.$id;
		}
		return $all;
	}

	/**
	 * @param int|string $id
	 * @return string|boolean false for the entry the user has no access to
	 */
	public static function title($id)
	{
		return $id === self::NO_ACCESS ? false : 'Entry '.$id;
	}

	/**
	 * Pass criteria: the app is handed exactly the linked ids, the linked filter itself is gone
	 * - an app passing its filter on as columns would fail on it - and the rest of the filter
	 * is untouched.
	 */
	public function testLinkedFilterBecomesIds()
	{
		$options = array('filter' => array('linked' => self::TARGET_APP.':'.$this->target, 'status' => 'open'));
		$result = Api\Link::query(self::APP_WITH_IDS, '', $options);

		$this->assertCount(1, self::$calls, 'The app has to be asked once');
		$ids = array_map('intval', self::$calls[0]['ids']);
		sort($ids);
		$this->assertSame(self::LINKED, $ids, 'The app has to be handed the linked ids, and only those');
		$this->assertSame(array('status' => 'open'), self::$calls[0]['filter']);

		$found = array_keys($result);
		sort($found);
		$this->assertSame(self::LINKED, $found);
	}

	/**
	 * Pass criteria: the entry linked to can be named as array too, as a nextmatch does
	 */
	public function testLinkedFilterAsArray()
	{
		$options = array('filter' => array('linked' => array('app' => self::TARGET_APP, 'id' => $this->target)));
		Api\Link::query(self::APP_WITH_IDS, '', $options);

		$this->assertCount(1, self::$calls);
		$this->assertCount(count(self::LINKED), self::$calls[0]['ids']);
	}

	/**
	 * Pass criteria: a list of entries hands the app the ids linked to any of them, each id
	 * once. Entries of the list that lead nowhere - not existing, no access, not an entry at
	 * all - add nothing, and do not empty the list either.
	 */
	public function testLinkedFilterWithSeveralEntries()
	{
		$other = $this->target.'b';
		$also_first = 13;	// link() takes its ids by reference
		$only_other = 14;
		Storage::link(self::TARGET_APP, $other, self::APP_WITH_IDS, $also_first);
		Storage::link(self::TARGET_APP, $other, self::APP_WITH_IDS, $only_other);
		try
		{
			$options = array('filter' => array('linked' => array(
				self::TARGET_APP.':'.$this->target,
				array('app' => self::TARGET_APP, 'id' => $other),
				self::TARGET_APP.':'.self::NO_ACCESS,
				self::TARGET_APP.':nothing-linked-here',
				'not-an-app:1',
				null,
			)));
			Api\Link::query(self::APP_WITH_IDS, '', $options);
		}
		finally
		{
			Storage::unlink(0, self::TARGET_APP, $other);
		}

		$this->assertCount(1, self::$calls, 'The app has to be asked once');
		$ids = array_map('intval', self::$calls[0]['ids']);
		sort($ids);
		$this->assertSame(array(11, 12, 13, 14), $ids);
	}

	/**
	 * Pass criteria: an app not supporting ids is still asked, with the linked filter taken out
	 * of its filter, and what it answers is passed on - all its entries, as without the filter.
	 */
	public function testAppIgnoringIdsStillAnswers()
	{
		$options = array('filter' => array('linked' => self::TARGET_APP.':'.$this->target));
		$result = Api\Link::query(self::APP_WITHOUT_IDS, '', $options);

		$this->assertCount(1, self::$calls, 'The app has to be asked once');
		$this->assertSame(array(), self::$calls[0]['filter']);
		$this->assertCount(20, $result);
	}

	public static function nothingLinkedProvider() : array
	{
		return array(
			'nothing linked to it'     => array(self::TARGET_APP.':nothing-linked-here'),
			'no access to the entry'   => array(self::TARGET_APP.':'.self::NO_ACCESS),
			'app is not registered'    => array('not-an-app:1'),
			'no id'                    => array(self::TARGET_APP.':'),
			'no app'                   => array(':1'),
			'empty'                    => array(''),
			'null'                     => array(null),
			'array without id'         => array(array('app' => self::TARGET_APP)),
			'array with a list of ids' => array(array('app' => self::TARGET_APP, 'id' => array(1, 2))),
			'empty list'               => array(array()),
			'list of nothing linked'   => array(array(self::TARGET_APP.':nothing-linked-here', self::TARGET_APP.':'.self::NO_ACCESS)),
		);
	}

	/**
	 * Pass criteria: a linked filter that leads to no entries hands the app an empty list of
	 * ids, so an app supporting them finds nothing. The ids must never be left out instead, as
	 * that would answer with every entry.
	 */
	#[DataProvider('nothingLinkedProvider')]
	public function testLinkedFilterWithoutEntriesFindsNothing($linked)
	{
		$options = array('filter' => array('linked' => $linked));

		$this->assertSame(array(), Api\Link::query(self::APP_WITH_IDS, '', $options));
		$this->assertSame(0, (int)$options['total']);
		$this->assertCount(1, self::$calls, 'The app has to be asked once');
		$this->assertSame(array(), self::$calls[0]['ids']);
	}

	/**
	 * Pass criteria: ids only come from a linked filter - ones sent by the caller are dropped
	 * before the app is asked.
	 */
	public function testIdsFromTheCallerAreDropped()
	{
		$options = array('ids' => array(1, 2));
		$result = Api\Link::query(self::APP_WITH_IDS, '', $options);

		$this->assertCount(1, self::$calls);
		$this->assertArrayNotHasKey('ids', self::$calls[0]);
		$this->assertCount(20, $result, 'Without a linked filter everything is searched');
	}
}
