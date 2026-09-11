<?php
/**
 * EGroupware Api: tests for Api\Db::set_app()/get_table_definitions()'s cross-app cache isolation
 * and the introspection methods (affected_rows/get_last_insert_id/table_names/pkey_columns/metadata)
 *
 * Part of the Api\Db test-coverage project (doc/ai/projects/db-test-coverage.md), Phase 4.
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
 *
 * self::$db is deliberately assigned to $GLOBALS['egw']->db (matching real bootstrap), since
 * set_app()'s global-db-protection guard is an identity check against exactly that global.
 */
class CacheIsolationTest extends TestCase
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

	/**
	 * Insert a fixture row, tracking its id for cleanup in tearDown().
	 *
	 * @param array $overrides column => value overrides merged over sane defaults
	 * @return int the new row's t_id
	 */
	private function insertRow(array $overrides = []) : int
	{
		self::$db->insert('egw_test', array_merge(array(
			't_title' => 'CacheIsolationTest-'.bin2hex(random_bytes(4)),
		), $overrides), null, __LINE__, __FILE__, 'test');
		$id = self::$db->get_last_insert_id('egw_test', 't_id');
		$this->created_ids[] = $id;
		return $id;
	}

	// -------------------------------------------------------------------
	// set_app() - global-db-protection guard
	// -------------------------------------------------------------------

	/**
	 * set_app() throws WrongParameter if called on $GLOBALS['egw']->db ITSELF (an identity check,
	 * "===") for any app other than 'api' - prevents the shared global connection from being
	 * silently repurposed to look up a different app's table definitions.
	 */
	public function testSetAppThrowsWhenCalledOnGlobalDbForNonApiApp()
	{
		$this->assertSame(self::$db, $GLOBALS['egw']->db, 'Precondition: self::$db must be the actual global db object for this identity-check test to be meaningful');

		$this->expectException(Api\Exception\WrongParameter::class);
		self::$db->set_app('test');
	}

	/**
	 * The guard only fires for a non-'api' app - set_app('api') on the global db must NOT throw
	 * (it's the default app anyway, so this is a no-op that must stay safe).
	 */
	public function testSetAppAllowsApiOnGlobalDb()
	{
		self::$db->set_app('api');
		$this->assertTrue(true, 'set_app(\'api\') on the global db must not throw');

		// restore, since $this->app is a real instance property we just changed on the SHARED
		// self::$db - other tests in this file rely on get_table_definitions() honoring whichever
		// app was last set via set_app() when no explicit $app is passed
		self::$db->set_app('api');
	}

	/**
	 * The guard is specific to the global db object - a SEPARATE (non-global) Db instance may call
	 * set_app() for any app, including non-'api' ones, without throwing.
	 */
	public function testSetAppAllowsAnyAppOnNonGlobalDbInstance()
	{
		$otherDb = new Api\Db(self::$db_data);
		$otherDb->connect();

		$this->assertNotSame(self::$db, $otherDb, 'Precondition: must be a genuinely different instance from the global db');

		$otherDb->set_app('test');
		$this->assertTrue(true, 'set_app() on a non-global Db instance must not throw regardless of app name');
	}

	// -------------------------------------------------------------------
	// get_table_definitions() - cross-app cache isolation
	// -------------------------------------------------------------------

	/**
	 * get_table_definitions() caches per-app in a process-wide static (self::$all_app_data, keyed
	 * by $app). This locks down that app A's table definitions never leak into app B's lookup -
	 * 'test' (egw_test: t_id/t_title/t_uniq/...) vs the real 'api' app (egw_config: config_app/
	 * config_name/config_value, a safe, always-present, read-only-here table).
	 */
	public function testGetTableDefinitionsCrossAppCacheIsolation()
	{
		$testDef = self::$db->get_table_definitions('test', 'egw_test');
		$apiDef = self::$db->get_table_definitions('api', 'egw_config');

		$this->assertIsArray($testDef, 'test app egw_test definition must be found');
		$this->assertArrayHasKey('t_id', $testDef['fd']);
		$this->assertArrayHasKey('t_uniq', $testDef['fd']);
		$this->assertArrayNotHasKey('config_app', $testDef['fd'],
			'the test app definition must not somehow contain a column from the api app');

		$this->assertIsArray($apiDef, 'api app egw_config definition must be found');
		$this->assertArrayHasKey('config_app', $apiDef['fd']);
		$this->assertArrayHasKey('config_name', $apiDef['fd']);
		$this->assertArrayNotHasKey('t_id', $apiDef['fd'],
			'the api app definition must not somehow contain a column from the test app');

		// whole-app form (no $table) must also stay isolated: 'test' app's table LIST must not
		// contain 'egw_config', and vice versa
		$testAllTables = self::$db->get_table_definitions('test');
		$apiAllTables = self::$db->get_table_definitions('api');
		$this->assertArrayHasKey('egw_test', $testAllTables);
		$this->assertArrayNotHasKey('egw_config', $testAllTables);
		$this->assertArrayHasKey('egw_config', $apiAllTables);
		$this->assertArrayNotHasKey('egw_test', $apiAllTables);
	}

	/**
	 * REAL BUG found and FIXED (commit adding the "$app_data = $phpgw_baseline;" plain-copy fix -
	 * see api/src/Db.php): get_table_definitions()'s docblock claims "the already read
	 * table-definitions are shared between all db-instances via a static var" (self::$all_app_data),
	 * but the caching was actually COMPLETELY NON-FUNCTIONAL before the fix. The code did:
	 *   $app_data =& self::$all_app_data[$app];      // $app_data now ALIASES the static array slot
	 *   if (!isset($app_data)) {
	 *       include($tables_current);                 // defines $phpgw_baseline
	 *       $app_data =& $phpgw_baseline;              // REBOUND $app_data to a DIFFERENT zval!
	 *       ...
	 *   }
	 * The 2nd "=&" did not copy $phpgw_baseline's VALUE into the zval that self::$all_app_data[$app]
	 * points to - it rebound the LOCAL variable $app_data to alias $phpgw_baseline instead, silently
	 * breaking the alias to the static array, leaving self::$all_app_data[$app] NULL forever. Fixed
	 * by replacing that line with a plain value-copy (`$app_data = $phpgw_baseline;`), which writes
	 * through the still-intact alias into the shared static-array slot.
	 *
	 * Practical impact of the bug (now resolved): the intended "read once, share via static var"
	 * caching never happened for the common ($app given explicitly, not true) path -
	 * tables_current.inc.php got include()'d and its top-level array-literal code re-executed on
	 * EVERY call to get_table_definitions($app), for EVERY app, in EVERY request. This is called by
	 * every single Storage\Base/Api\Storage construction (see the "shared engine" note throughout
	 * doc/ai/projects/storage-test-coverage.md) - a real, likely-widespread performance issue that's
	 * now fixed.
	 */
	public function testGetTableDefinitionsCacheIsPopulatedAfterFirstCall()
	{
		$first = self::$db->get_table_definitions('test', 'egw_test');
		$second = self::$db->get_table_definitions('test', 'egw_test');

		$this->assertEquals($first, $second, 'Both calls must return the correct, matching table definition');

		// the static cache should now hold this data for app 'test' after the first call
		$prop = new ReflectionProperty(Api\Db::class, 'all_app_data');
		$prop->setAccessible(true);
		$cache = $prop->getValue();

		$this->assertArrayHasKey('test', $cache);
		$this->assertNotNull($cache['test'],
			'The static cache slot for app \'test\' must be populated after the first call, not left NULL');
		$this->assertArrayHasKey('egw_test', $cache['test'], 'Cached data must contain the egw_test table definition');
	}

	/**
	 * $app===true triggers the "search all already-loaded apps, then scan EGW_INCLUDE_ROOT for
	 * apps not yet loaded" fallback used when the caller doesn't know which app a table belongs to.
	 *
	 * Now that testGetTableDefinitionsCacheIsPopulatedAfterFirstCall()'s fix is in place, calling
	 * get_table_definitions('test', ...) first DOES populate self::$all_app_data['test'], so this
	 * should find it via the "already loaded apps" search rather than falling through to the
	 * directory scan - either way, the CONTRACT (correct data returned) is what's asserted here.
	 */
	public function testGetTableDefinitionsAppTrueFindsTableAcrossLoadedApps()
	{
		self::$db->get_table_definitions('test', 'egw_test');

		$found = self::$db->get_table_definitions(true, 'egw_test');

		$this->assertIsArray($found, 'app=true must find egw_test\'s definition among loaded/scanned apps');
		$this->assertArrayHasKey('t_id', $found['fd']);
	}

	// -------------------------------------------------------------------
	// Introspection
	// -------------------------------------------------------------------

	public function testAffectedRowsAfterUpdate()
	{
		$prefix = 'CacheIsolationTest-affected-'.bin2hex(random_bytes(4));
		$this->insertRow(['t_title' => $prefix]);
		$this->insertRow(['t_title' => $prefix]);
		$this->insertRow(['t_title' => $prefix]);

		self::$db->update('egw_test', ['t_desc' => 'updated'], ['t_title' => $prefix], __LINE__, __FILE__, 'test');

		$this->assertSame(3, self::$db->affected_rows(), 'affected_rows() must report exactly the 3 rows matched/changed by the update');
	}

	public function testGetLastInsertIdMatchesNewlyCreatedRow()
	{
		$title = 'CacheIsolationTest-lastid-'.bin2hex(random_bytes(4));
		self::$db->insert('egw_test', ['t_title' => $title], null, __LINE__, __FILE__, 'test');
		$id = self::$db->get_last_insert_id('egw_test', 't_id');
		$this->created_ids[] = $id;

		$this->assertGreaterThan(0, $id);

		$row = self::$db->query('SELECT t_title FROM egw_test WHERE t_id='.(int)$id, __LINE__, __FILE__)->fetch();
		$this->assertSame($title, $row['t_title'], 'get_last_insert_id() must return the id of the row just inserted BY THIS connection');
	}

	public function testTableNamesIncludesEgwTest()
	{
		$this->assertContains('egw_test', self::$db->table_names(true));
	}

	public function testPkeyColumnsSingleColumnPrimaryKey()
	{
		$this->assertSame(['t_id'], self::$db->pkey_columns('egw_test'));
	}

	/**
	 * egw_config (real 'api' app table, read-only here) has a genuine 2-column composite primary
	 * key (config_app, config_name) - see api/setup/tables_current.inc.php.
	 */
	public function testPkeyColumnsCompositeKey()
	{
		$pk = self::$db->pkey_columns('egw_config');

		$this->assertCount(2, $pk);
		$this->assertContains('config_app', $pk);
		$this->assertContains('config_name', $pk);
	}

	public function testMetadataReturnsColumnNamesForEgwTest()
	{
		$metadata = self::$db->metadata('egw_test');

		$names = array_column($metadata, 'name');
		$this->assertContains('t_id', $names);
		$this->assertContains('t_title', $names);
		$this->assertContains('t_uniq', $names);
	}
}
