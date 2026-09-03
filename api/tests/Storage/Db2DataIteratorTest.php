<?php
/**
 * EGroupware Api: tests for Api\Storage\Db2DataIterator
 *
 * Part of the Api\Storage test-coverage project, Phase 5 (doc/ai/projects/storage-test-coverage.md).
 * Db2DataIterator was not found to be used anywhere in this checkout (tracked or gitignored apps)
 * as of this writing - this is characterization/regression coverage for existing behavior, kept
 * proportionate to that low-stakes status.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * Base subclass tagging data2db()/db2data() output so tests can prove WHICH one Db2DataIterator
 * actually calls, independent of whether the two transforms happen to produce identical output
 * for a given row.
 */
class Db2DataIteratorTestStorage extends Api\Storage\Base
{
	public function data2db($data = null)
	{
		$result = parent::data2db($data);
		if (is_array($result)) $result['_via'] = 'data2db';
		return $result;
	}

	public function db2data($data = null)
	{
		$result = parent::db2data($data);
		if (is_array($result)) $result['_via'] = 'db2data';
		return $result;
	}
}

/**
 * Tests for Api\Storage\Db2DataIterator
 *
 * Db2DataIterator wraps a \Traversable DB result set (or an \IteratorAggregate's inner iterator)
 * and delegates current()/key()/next()/valid()/rewind() to it, transforming each row via the
 * storage object's data2db()/db2data() method. Its constructor type-hints the real
 * Api\Storage\Base class (not an interface), so a real, DB-connected instance is required -
 * follows BaseTest.php's lightweight (non-LoggedInTest) bootstrap pattern.
 */
class Db2DataIteratorTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	public static function setUpBeforeClass() : void
	{
		if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
		{
			ini_set('session.save_path', '/tmp');
		}
		$_REQUEST['domain'] = $GLOBALS['EGW_DOMAIN'];

		$GLOBALS['egw_info'] = array(
			'flags' => array(
				'noheader' => True,
				'nonavbar' => True,
				'currentapp' => 'setup',
				'noapi' => true,
		));
		require(__DIR__.'/../../../header.inc.php');

		// see BaseTest.php for why this resolution is necessary rather than a direct
		// $GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']] index
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if (!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
	}

	/**
	 * Behaviour: full foreach iteration over a wrapped \ArrayIterator of raw row arrays must
	 * delegate current()/key()/next()/valid()/rewind() correctly and yield every row, transformed.
	 *
	 * Pass criteria: iterating collects exactly the input rows' t_id values, in order.
	 */
	public function testFullIterationYieldsAllRows()
	{
		$storage = new Db2DataIteratorTestStorage('test', 'egw_test', self::$db);
		$rows = [
			['t_id' => 1, 't_title' => 'One'],
			['t_id' => 2, 't_title' => 'Two'],
			['t_id' => 3, 't_title' => 'Three'],
		];
		$iterator = new Api\Storage\Db2DataIterator($storage, new ArrayIterator($rows));

		$ids = [];
		foreach ($iterator as $key => $row)
		{
			$ids[$key] = $row['t_id'];
		}
		$this->assertSame([0 => 1, 1 => 2, 2 => 3], $ids,
			'Full foreach iteration did not yield all wrapped rows in order');
	}

	/**
	 * Behaviour: the constructor reads $storage->total into a public property, for callers that
	 * need a result count without exhausting the iterator.
	 */
	public function testTotalIsExposedFromStorage()
	{
		$storage = new Db2DataIteratorTestStorage('test', 'egw_test', self::$db);
		$storage->total = 42;

		$iterator = new Api\Storage\Db2DataIterator($storage, new ArrayIterator([]));

		$this->assertSame(42, $iterator->total, 'total was not copied from $storage->total');
	}

	/**
	 * Behaviour: with no result set given (the constructor's $rs=null default), every Iterator
	 * method must degrade safely rather than crash - valid() false, current() null, key() 0,
	 * next()/rewind() no-ops.
	 */
	public function testNullResultSetIsSafe()
	{
		$storage = new Db2DataIteratorTestStorage('test', 'egw_test', self::$db);
		$iterator = new Api\Storage\Db2DataIterator($storage, null);

		$this->assertFalse($iterator->valid(), 'valid() must be false with no result set');
		$this->assertNull($iterator->current(), 'current() must be null with no result set');
		$this->assertSame(0, $iterator->key(), 'key() must be 0 with no result set');
		// must not throw
		$iterator->next();
		$iterator->rewind();
		$this->assertTrue(true, 'next()/rewind() must not throw with no result set');
	}

	/**
	 * Behaviour: an \IteratorAggregate passed as $rs has its getIterator() unwrapped by the
	 * constructor, rather than being stored (and failing is_a($this->rs,'iterator')) as-is.
	 */
	public function testIteratorAggregateIsUnwrapped()
	{
		$storage = new Db2DataIteratorTestStorage('test', 'egw_test', self::$db);
		$aggregate = new class implements IteratorAggregate {
			public function getIterator(): Iterator
			{
				return new ArrayIterator([['t_id' => 99]]);
			}
		};

		$iterator = new Api\Storage\Db2DataIterator($storage, $aggregate);

		$this->assertTrue($iterator->valid(), 'IteratorAggregate was not unwrapped into a usable iterator');
		$this->assertSame(99, $iterator->current()['t_id']);
	}

	/**
	 * Regression/documentation test: current() calls $this->storage->data2db($data) (user->server
	 * direction), NOT db2data() (server->user) despite the class name and docblock both saying it
	 * "applies db2data". This is being locked down as CURRENT actual behavior, not silently
	 * corrected - see doc/ai/projects/storage-test-coverage.md. It's unreachable in production
	 * anyway: Base::search()'s $search_return_iterator flag (the only place that constructs a
	 * Db2DataIterator) defaults to false and is never set true anywhere in this checkout.
	 *
	 * Pass criteria: current() returns the '_via' marker for 'data2db', proving that method (not
	 * db2data()) is what actually gets invoked.
	 */
	public function testCurrentCallsDataToDbNotDbToData()
	{
		$storage = new Db2DataIteratorTestStorage('test', 'egw_test', self::$db);
		$iterator = new Api\Storage\Db2DataIterator($storage, new ArrayIterator([['t_id' => 1]]));

		$iterator->rewind();
		$row = $iterator->current();

		$this->assertSame('data2db', $row['_via'],
			"Db2DataIterator::current() is documented/named as applying db2data(), but actually ".
			"calls data2db() - this test locks down the real behavior, it is not asserting the ".
			"documented/intended one");
	}
}
