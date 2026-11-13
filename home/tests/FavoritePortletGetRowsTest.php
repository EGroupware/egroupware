<?php
/**
 * EGroupware home: tests for the row callback every favourite portlet shares
 *
 * @link http://www.egroupware.org
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * A portlet of an app that does not exist, returning two fixed rows
 *
 * Named the way the real ones are, as the app a portlet belongs to is read from its class name.
 */
class phpunitfixture_favorite_portlet extends home_favorite_portlet
{
	/** @var array[] queries rows() was called with */
	public static $calls = [];

	protected static function rows(&$query, &$rows, &$readonlys)
	{
		self::$calls[] = $query;
		$rows = [['id' => 1], ['id' => 2]];
		// what an app's own list method does, and a portlet on Home must not keep
		$GLOBALS['egw_info']['flags']['app_header'] = 'set by the list';

		return 2;
	}
}

/**
 * CONTRACT
 * home_favorite_portlet::get_rows() is the nextmatch callback of every favourite portlet. Each
 * app's portlet only supplies rows(). get_rows() itself
 * - hands the query to rows() and returns its total and rows unchanged
 * - remembers the query in the session, under the app the portlet class is named after and the
 *   list's session_for name: that is where the app's action endpoint looks for the query to
 *   re-run for a "select all" (Nextmatch::listQueryName()), and a portlet that did not store it
 *   had its "select all" refused
 * - does not remember one of the nextmatch's own partial queries (csv_export set: a single-row
 *   refresh, a check of known rows), which is narrowed to a few ids
 * - clears the page header the app's list method set
 *
 * SETUP
 * A fixture portlet class, never constructed: get_rows() is static and reads nothing from an
 * instance. The session keys it writes are removed again.
 *
 * PASS CRITERIA
 * Exactly the stored value, return value and header asserted.
 */
class FavoritePortletGetRowsTest extends \EGroupware\Api\AppTest
{
	const NAME = 'home-phpunit_portlet';

	protected function setUp() : void
	{
		parent::setUp();
		phpunitfixture_favorite_portlet::$calls = [];
		Api\Cache::unsetSession('phpunitfixture', self::NAME);
	}

	protected function tearDown() : void
	{
		Api\Cache::unsetSession('phpunitfixture', self::NAME);
		unset($GLOBALS['egw_info']['flags']['app_header']);
		parent::tearDown();
	}

	private function query(array $extra = []) : array
	{
		return $extra + ['start' => 0, 'num_rows' => 25, 'search' => 'something', 'col_filter' => ['cat_id' => 7],
			'session_for' => self::NAME];
	}

	public function testReturnsWhatTheAppsRowMethodReturns()
	{
		$query = $this->query();
		$rows = $readonlys = null;

		$total = phpunitfixture_favorite_portlet::get_rows($query, $rows, $readonlys);

		$this->assertSame(2, $total);
		$this->assertSame([['id' => 1], ['id' => 2]], $rows);
		$this->assertCount(1, phpunitfixture_favorite_portlet::$calls, 'rows() is called exactly once');
		$this->assertSame('something', phpunitfixture_favorite_portlet::$calls[0]['search']);
	}

	public function testRemembersTheQueryUnderTheAppAndTheListsName()
	{
		$query = $this->query(['rows' => ['x'], 'actions' => ['open' => []]]);
		$rows = $readonlys = null;

		phpunitfixture_favorite_portlet::get_rows($query, $rows, $readonlys);

		$stored = Api\Cache::getSession('phpunitfixture', self::NAME);
		$this->assertIsArray($stored, 'the query is stored under the app the class is named after');
		$this->assertSame('something', $stored['search']);
		$this->assertSame(['cat_id' => 7], $stored['col_filter']);
		$this->assertArrayNotHasKey('rows', $stored, 'the rows are no part of the query');
		$this->assertArrayNotHasKey('actions', $stored, 'nor are the actions');
	}

	public function testDoesNotRememberAPartialQuery()
	{
		$list = $this->query();
		$rows = $readonlys = null;
		phpunitfixture_favorite_portlet::get_rows($list, $rows, $readonlys);
		$stored = Api\Cache::getSession('phpunitfixture', self::NAME);

		$refresh = $this->query(['col_filter' => ['id' => 1], 'csv_export' => 'refresh']);
		phpunitfixture_favorite_portlet::get_rows($refresh, $rows, $readonlys);

		$this->assertSame($stored, Api\Cache::getSession('phpunitfixture', self::NAME),
			'a single-row refresh must not replace what the list shows');
	}

	public function testDoesNotStoreForAListWithoutAName()
	{
		$query = $this->query();
		unset($query['session_for']);
		$rows = $readonlys = null;

		$this->assertSame(2, phpunitfixture_favorite_portlet::get_rows($query, $rows, $readonlys));
		$this->assertNull(Api\Cache::getSession('phpunitfixture', self::NAME));
	}

	public function testClearsThePageHeaderTheListSet()
	{
		$query = $this->query();
		$rows = $readonlys = null;

		phpunitfixture_favorite_portlet::get_rows($query, $rows, $readonlys);

		$this->assertArrayNotHasKey('app_header', $GLOBALS['egw_info']['flags']);
	}
}
