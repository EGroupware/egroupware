<?php
/**
 * EGroupware Api: tests for Api\Db\Pdo
 *
 * Api\Db\Pdo is a small standalone PDO-connection helper, extended directly by
 * Api\Vfs\Sqlfs\StreamWrapper (the filemanager SQL-filesystem backend) and used by z-push's
 * SqlStateMaschine. Added to the Api\Db test-coverage project (doc/ai/projects/db-test-coverage.md)
 * as a small final addition before moving on to a separate Vfs/stream-wrappers testing effort.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Db\Pdo;
use PHPUnit\Framework\TestCase;

/**
 * Bootstrap mirrors api/tests/Db/QuoteTest.php exactly: a plain TestCase with a raw Api\Db
 * connection (no login needed), domain resolved via Api\Session::search_instance() since
 * $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml) which is never a real domain key.
 * Pdo::_pdo() reads $GLOBALS['egw']->db for connection params, which this bootstrap sets up.
 */
class PdoTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

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

		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function tearDown() : void
	{
		// _pdo_boolean() reads the process-static self::$pdo_type - restore it after any test that
		// mutates it via reflection, so later tests (in this file or others sharing the process)
		// don't see a polluted value.
		$prop = new ReflectionProperty(Pdo::class, 'pdo_type');
		$prop->setAccessible(true);
		$prop->setValue(null, 'mysql');
	}

	// -------------------------------------------------------------------
	// _pdo_timestamp() - pure, no DB needed
	// -------------------------------------------------------------------

	public function testPdoTimestampConvertsNumericEpochToDateString()
	{
		$epoch = mktime(14, 30, 0, 6, 15, 2026);

		$this->assertSame(date('Y-m-d H:i:s', $epoch), Pdo::_pdo_timestamp($epoch));
	}

	public function testPdoTimestampPassesThroughNonNumericValueUnchanged()
	{
		$this->assertSame('2026-06-15 14:30:00', Pdo::_pdo_timestamp('2026-06-15 14:30:00'));
	}

	// -------------------------------------------------------------------
	// _pdo_boolean() - pure, but depends on the process-static $pdo_type
	// -------------------------------------------------------------------

	/**
	 * With $pdo_type === 'mysql' (this environment's real type, and also the class's static
	 * default), true/false become the strings '1'/'0'.
	 */
	public function testPdoBooleanForMysqlType()
	{
		$prop = new ReflectionProperty(Pdo::class, 'pdo_type');
		$prop->setAccessible(true);
		$prop->setValue(null, 'mysql');

		$this->assertSame('1', Pdo::_pdo_boolean(true));
		$this->assertSame('0', Pdo::_pdo_boolean(false));
	}

	/**
	 * For any other $pdo_type (eg. 'pgsql'), true/false become the literal strings 'true'/'false'.
	 */
	public function testPdoBooleanForNonMysqlType()
	{
		$prop = new ReflectionProperty(Pdo::class, 'pdo_type');
		$prop->setAccessible(true);
		$prop->setValue(null, 'pgsql');

		$this->assertSame('true', Pdo::_pdo_boolean(true));
		$this->assertSame('false', Pdo::_pdo_boolean(false));
	}

	// -------------------------------------------------------------------
	// connection() / reconnect() / _pdo() - real live connection
	// -------------------------------------------------------------------

	/**
	 * connection() lazily connects via _pdo(), which reads $GLOBALS['egw']->db (set up in
	 * setUpBeforeClass()) to build a DSN and open a real \PDO connection. This environment's real
	 * DB type is MySQL/MariaDB (confirmed throughout the whole Api\Db test-coverage project), so
	 * this exercises the real mysql branch: self::$case_sensitive_equal must become '= BINARY '
	 * and self::$pdo_type 'mysql', and the returned \PDO object must actually work.
	 */
	public function testConnectionReturnsWorkingPdoWithMysqlSettings()
	{
		$this->assertStringStartsWith('mysql', self::$db->Type,
			'This test asserts MySQL-specific behavior; skip/adjust if run against a different Type');

		$pdo = Pdo::connection();

		$this->assertInstanceOf(\PDO::class, $pdo);
		$this->assertSame('= BINARY ', Pdo::$case_sensitive_equal);
		$this->assertSame('mysql', Pdo::$pdo_type);

		// the connection must actually work
		$row = $pdo->query('SELECT 1 AS x')->fetch(\PDO::FETCH_ASSOC);
		$this->assertEquals(1, $row['x']);
	}

	/**
	 * _pdo() clears MySQL 5.7-10.0's ONLY_FULL_GROUP_BY sql_mode via a post-connect query - only
	 * assert this when the real server version is actually in that range, per this project's
	 * established precedent of not forcing environment-specific behavior that may not apply here
	 * (see doc/ai/projects/db-test-coverage.md's fix_group_by_columns()/postgres-branch notes).
	 */
	public function testConnectionClearsOnlyFullGroupBySqlModeWhenServerVersionInRange()
	{
		$version = (float)(self::$db->ServerInfo['version'] ?? 0);
		if (!($version >= 5.7 && $version < 10.0))
		{
			$this->markTestSkipped("Server version $version is not in the 5.7-10.0 range this behavior targets");
		}

		$pdo = Pdo::connection();
		$row = $pdo->query('SELECT @@SESSION.sql_mode AS mode')->fetch(\PDO::FETCH_ASSOC);

		$this->assertStringNotContainsStringIgnoringCase('ONLY_FULL_GROUP_BY', $row['mode']);
	}

	/**
	 * reconnect() must actually open a NEW \PDO connection, not just no-op and return the
	 * previously cached one - proven via object identity (===), which for two separate \PDO
	 * instances (even to the same DSN) is always false.
	 */
	public function testReconnectReturnsGenuinelyNewPdoInstance()
	{
		$first = Pdo::connection();
		Pdo::reconnect();
		$second = Pdo::connection();

		$this->assertNotSame($first, $second, 'reconnect() must open a genuinely new \PDO connection');
		$this->assertInstanceOf(\PDO::class, $second);

		// the new connection must still actually work
		$row = $second->query('SELECT 1 AS x')->fetch(\PDO::FETCH_ASSOC);
		$this->assertEquals(1, $row['x']);
	}
}
