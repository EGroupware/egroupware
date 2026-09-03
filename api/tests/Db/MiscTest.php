<?php
/**
 * EGroupware Api: tests for Api\Db::concat()/to_timestamp()/from_timestamp(), query()'s empty-string
 * short-circuit, the global (bare true) log_updates mode, and the echo-based "not yet implemented"
 * quirks in get_last_insert_id()/index_names()
 *
 * Part of the Api\Db test-coverage project (doc/ai/projects/db-test-coverage.md), Phase 5 (final).
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * Bootstrap mirrors api/tests/Db/QuoteTest.php exactly: a plain TestCase with a raw Api\Db
 * connection (no login needed), domain resolved via Api\Session::search_instance() since
 * $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml) which is never a real domain key.
 */
class MiscTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * @var array
	 */
	private static $db_data;

	/**
	 * t_id's created by this test, deleted in tearDown() - shared dev database, only ever delete
	 * rows we created ourselves.
	 *
	 * @var int[]
	 */
	private $created_ids = [];

	public static function setUpBeforeClass() : void
	{
		if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
		{
			ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
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

		// $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml), which is never a real
		// key in $GLOBALS['egw_domain'] - resolve it the same way LoggedInTest::load_egw() /
		// api/tests/Db/QuoteTest.php do, via Session::search_instance()'s fallback-to-first-domain
		// logic, instead of indexing $GLOBALS['egw_domain'] directly.
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		self::$db_data = $GLOBALS['egw_domain'][$domain];
		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db(self::$db_data);
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

	protected function tearDown() : void
	{
		foreach ($this->created_ids as $id)
		{
			self::$db->delete('egw_test', array('t_id' => $id), __LINE__, __FILE__, 'test');
		}
		$this->created_ids = [];
	}

	// -------------------------------------------------------------------
	// concat() - the one portability function that DOES need a live connection
	// -------------------------------------------------------------------

	/**
	 * Behavior: concat() delegates to the underlying ADOdb driver's own concat() (mysql: CONCAT(...),
	 * postgres: ... || ...), unlike the other 9 portability functions in PortabilityTest.php which
	 * are pure string-builders switching only on ->Type. Proven the strongest way: embed the result
	 * in a real query and check the returned value.
	 */
	public function testConcatProducesCorrectSqlAgainstRealConnection()
	{
		$concat = self::$db->concat(self::$db->quote('Hello '), self::$db->quote('World'));
		$row = self::$db->query('SELECT '.$concat.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame('Hello World', $row['x']);
	}

	public function testConcatWithThreeArguments()
	{
		$concat = self::$db->concat(self::$db->quote('a'), self::$db->quote('b'), self::$db->quote('c'));
		$row = self::$db->query('SELECT '.$concat.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame('abc', $row['x']);
	}

	// -------------------------------------------------------------------
	// to_timestamp() / from_timestamp() - round trip through a real connection
	// -------------------------------------------------------------------

	/**
	 * Behavior: to_timestamp($epoch) converts a unix timestamp to a DB-specific timestamp literal
	 * (via ADOdb's DBTimeStamp(), quotes stripped); from_timestamp($timestamp) converts a DB
	 * timestamp string back to a unix timestamp (via ADOdb's UnixTimeStamp()). Both need a live
	 * connection - proven via an actual DB round trip (embed to_timestamp()'s output in a real
	 * query, read the raw DB timestamp back, then convert it back with from_timestamp()).
	 *
	 * Uses a whole-second epoch since DB timestamp columns/DBTimeStamp() are second-precision -
	 * no sub-second information exists to lose or preserve.
	 */
	public function testTimestampRoundTripsThroughRealConnection()
	{
		$epoch = mktime(12, 34, 56, 6, 15, 2025);	// 2025-06-15 12:34:56, a fixed whole-second epoch

		$quoted = self::$db->to_timestamp($epoch);
		$row = self::$db->query("SELECT '$quoted' AS x", __LINE__, __FILE__)->fetch();

		$this->assertSame((string)$epoch, (string)self::$db->from_timestamp($row['x']),
			'to_timestamp() -> raw DB value -> from_timestamp() must round-trip to the original epoch');
	}

	// -------------------------------------------------------------------
	// query() - empty-string short-circuit
	// -------------------------------------------------------------------

	/**
	 * Behavior: query('') returns 0 immediately (Db.php: "if ($Query_String == '') return 0;"),
	 * with NO actual DB round-trip - the very first check in the method, before even connect().
	 *
	 * Pass criteria: return value is exactly 0 (not false, not null).
	 */
	public function testQueryWithEmptyStringReturnsZeroWithoutExecuting()
	{
		$result = self::$db->query('', __LINE__, __FILE__);

		$this->assertSame(0, $result);
	}

	// -------------------------------------------------------------------
	// log_updates === true (global bare-boolean mode, distinct from the per-table array form
	// already tested in InsertUpdateDeleteTest::testLogUpdatesAsArrayOnlyLogsListedTables())
	// -------------------------------------------------------------------

	/**
	 * Behavior: log_updates === true (bare boolean, not an array) logs EVERY non-SELECT/SET/SHOW
	 * write, with no table-name filtering at all - unlike the array-of-table-names form, which only
	 * logs writes matching a listed table. Uses a dedicated (non-global) Db instance so this doesn't
	 * affect other tests sharing self::$db/$GLOBALS['egw']->db.
	 */
	public function testLogUpdatesTrueLogsWritesToAnyTableWithNoFiltering()
	{
		$log_file = tempnam(sys_get_temp_dir(), 'db_log_updates_global_test_');
		$this->assertNotFalse($log_file);

		$db = clone self::$db;
		$db->log_updates = true;
		$db->log_updates_to = $log_file;

		$title = 'MiscTest-logupd-global-'.bin2hex(random_bytes(4));
		$db->insert('egw_test', array('t_title' => $title, 't_modifier' => 1), false, __LINE__, __FILE__, 'test');
		$row = self::$db->select('egw_test', 't_id', array('t_title' => $title), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->created_ids[] = (int)$row['t_id'];

		clearstatcache(true, $log_file);
		$logged = file_exists($log_file) ? file_get_contents($log_file) : '';
		@unlink($log_file);

		$this->assertNotEmpty($logged, 'a write against ANY table must produce a log entry when log_updates is bare true');
		$this->assertStringContainsString('egw_test', $logged);
		// distinguishing feature vs. the array form: a debug-backtrace-style entry (function name +
		// args), not just the raw SQL - confirms this is genuinely the log_updates===true branch
		$this->assertStringContainsString(__CLASS__, $logged,
			'the debug-backtrace-style log entry must reference this test class as a caller');
	}

	// -------------------------------------------------------------------
	// get_last_insert_id()/index_names() - echo-based "not yet implemented" quirks
	// -------------------------------------------------------------------

	/**
	 * index_names() is only actually implemented for postgres - for every other DB type (mysql/
	 * mysqli, the type this test environment actually uses), it unconditionally echoes an HTML
	 * "not yet implemented" message and returns an empty array, rather than throwing or logging.
	 * This is a real, minor code-smell quirk (a library method producing raw output as a side
	 * effect) - documented here, not fixed (out of scope, low value, no known caller relies on the
	 * postgres-only behavior existing for mysql).
	 */
	public function testIndexNamesEchoesNotImplementedAndReturnsEmptyArrayOnMysql()
	{
		$this->assertNotSame('pgsql', self::$db->Type,
			'Precondition: this test documents the non-postgres echo path specifically');

		ob_start();
		$result = self::$db->index_names();
		$output = ob_get_clean();

		$this->assertSame(array(), $result, 'index_names() returns an empty array for unimplemented DB types');
		$this->assertStringContainsString('not yet implemented', $output,
			'QUIRK: index_names() echoes an HTML message as a side effect instead of throwing/logging - documented, not fixed');
	}

	/**
	 * get_last_insert_id()'s "not yet implemented" echo path (Db.php: "if ($id === False) { echo
	 * ...; return -1; }") is checked via a STRICT ($id === False) comparison against
	 * PO_Insert_ID()'s result. For the mysqli driver actually in use here, that value ultimately
	 * comes from mysqli_insert_id(), which always returns an int (0 if no insert has happened yet
	 * on this connection, never PHP's boolean false) - so this specific echo path is, in practice,
	 * UNREACHABLE for mysqli. Documented here as a real finding (dead code for this DB type) rather
	 * than forcing a fake/mocked trigger, consistent with this project's "found, not forced"
	 * discipline.
	 *
	 * Also documents a real, easy-to-misuse sharp edge found while writing this test:
	 * get_last_insert_id() (like mysqli_insert_id() itself) reflects only the MOST RECENT query on
	 * the connection - any intervening query (even a harmless SELECT) resets it to 0. Must be called
	 * immediately after insert(), not after an intervening select()/read() to fetch the row.
	 */
	public function testGetLastInsertIdNeverReturnsStrictFalseOnMysqli()
	{
		$this->assertSame('mysql', self::$db->Type,
			'Precondition: this test documents the mysqli-specific unreachability finding');

		$title = 'MiscTest-lastid-'.bin2hex(random_bytes(4));
		self::$db->insert('egw_test', array('t_title' => $title, 't_modifier' => 1), false, __LINE__, __FILE__, 'test');
		// must be called immediately after insert() - any intervening query resets mysqli_insert_id()
		// to 0 (a real MySQL/mysqli behavior, not a Db.php bug - see method doc-comment)
		$id = self::$db->get_last_insert_id('egw_test', 't_id');
		$this->created_ids[] = $id;

		$this->assertNotSame(-1, $id, 'the -1/"not yet implemented" sentinel must not be reachable for mysqli');

		$row = self::$db->select('egw_test', 't_id', array('t_title' => $title), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame((int)$row['t_id'], $id);
	}
}
