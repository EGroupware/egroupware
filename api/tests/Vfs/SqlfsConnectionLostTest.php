<?php
/**
 * Tests for the separate PDO connection of Sqlfs, which the server closes eg. after wait_timeout
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

use EGroupware\Api;
use EGroupware\Api\Vfs;
use PHPUnit\Framework\Attributes\DataProvider;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * Sqlfs uses a separate PDO connection. Long running jobs (eg. uploading a big file to S3) keep it idle longer than the
 * wait_timeout of the DB server (300s on our Galera cluster), which then closes it: "MySQL server has gone away".
 *
 * url_stat() treated every PDOException as "too many nested subqueries", decremented max_subquery_depth and saved it
 * permanently (down to 2, default 7), instead of reconnecting, and tried again with the same dead connection.
 * id2path() had no reconnect at all.
 */
class SqlfsConnectionLostTest extends Api\LoggedInTest
{
	/**
	 * @var mixed saved max_subquery_depth to restore it: code with the bug saves a lowered one permanently
	 */
	private $saved_subquery_depth;

	protected function setUp() : void
	{
		parent::setUp();
		$this->saved_subquery_depth = Api\Config::read('phpgwapi')['max_subquery_depth'] ?? null;
	}

	protected function tearDown() : void
	{
		if ((Api\Config::read('phpgwapi')['max_subquery_depth'] ?? null) !== $this->saved_subquery_depth)
		{
			Api\Config::save_value('max_subquery_depth', $this->saved_subquery_depth, 'phpgwapi');
		}
		parent::tearDown();
	}

	/**
	 * @return array[] [PDOException-errorInfo or null, message, code, expected]
	 */
	public static function exceptions() : array
	{
		return [
			'gone away (errorInfo)' => [['HY000', 2006, 'MySQL server has gone away'], 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away', 'HY000', true],
			'lost connection (errorInfo)' => [['HY000', 2013, 'Lost connection to MySQL server during query'], 'SQLSTATE[HY000]: General error: 2013 Lost connection', 'HY000', true],
			'gone away (message only)' => [null, 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away', 0, true],
			'syntax error' => [['42000', 1064, 'You have an error in your SQL syntax'], 'SQLSTATE[42000]: Syntax error', '42000', false],
			'too many subqueries' => [['HY000', 1436, 'Thread stack overrun'], 'SQLSTATE[HY000]: General error: 1436 Thread stack overrun', 'HY000', false],
			'deadlock' => [['40001', 1213, 'Deadlock found'], 'SQLSTATE[40001]: Deadlock found', '40001', false],
		];
	}

	#[DataProvider('exceptions')]
	public function testConnectionLostIsRecognised(?array $error_info, string $message, $code, bool $expected) : void
	{
		$e = new \PDOException($message);
		if ($error_info) $e->errorInfo = $error_info;
		$this->assertSame($expected, Api\Db\Pdo::connectionLost($e));
	}

	public function testOtherExceptionsAreNotConnectionLost() : void
	{
		$this->assertFalse(Api\Db\Pdo::connectionLost(new \Exception('something else', 2006 + 1)));
		$this->assertTrue(Api\Db\Pdo::connectionLost(new \Exception('MySQL server has gone away')));
	}

	/**
	 * Let the DB server close the PDO connection of Sqlfs, like it does with idle connections after wait_timeout
	 */
	private function killSqlfsConnection() : void
	{
		$id = (int)Sqlfs\StreamWrapper::connection()->query('SELECT CONNECTION_ID()')->fetchColumn();
		$this->assertGreaterThan(0, $id);
		$GLOBALS['egw']->db->query('KILL '.$id);
	}

	/**
	 * connection() checks a connection not used for PING_AFTER seconds, and replaces it, if the server closed it
	 */
	public function testConnectionChecksAnIdleConnection() : void
	{
		$old = Sqlfs\StreamWrapper::connection();
		$this->killSqlfsConnection();
		$last_used = new \ReflectionProperty(Api\Db\Pdo::class, 'last_used');
		$last_used->setValue(null, microtime(true) - Api\Db\Pdo::PING_AFTER - 1);

		$pdo = Sqlfs\StreamWrapper::connection();
		$this->assertNotSame($old, $pdo, 'a new connection is used');
		$this->assertSame('1', (string)$pdo->query('SELECT 1')->fetchColumn(), 'and works');
	}

	public function testConnectionIsNotCheckedWhenRecentlyUsed() : void
	{
		$old = Sqlfs\StreamWrapper::connection();
		$this->assertSame($old, Sqlfs\StreamWrapper::connection(), 'no new connection and no extra query');
	}

	public function testVfsOperationChecksTheConnectionBeforeUsingIt() : void
	{
		$depth = $GLOBALS['egw_info']['server']['max_subquery_depth'] ?? null;
		Sqlfs\StreamWrapper::connection();
		$this->killSqlfsConnection();
		(new \ReflectionProperty(Api\Db\Pdo::class, 'last_used'))->setValue(null, microtime(true) - Api\Db\Pdo::PING_AFTER - 1);

		// constructor of the stream wrapper revives the connection before url_stat() uses it, no retry necessary
		$this->assertEmpty(Vfs::stat('/sqlfs-connection-lost-test-'.uniqid()));
		$this->assertSame($depth, $GLOBALS['egw_info']['server']['max_subquery_depth'] ?? null);
	}

	public function testStatReconnectsAndDoesNotChangeTheSubqueryDepth() : void
	{
		$depth = $GLOBALS['egw_info']['server']['max_subquery_depth'] ?? null;
		$this->killSqlfsConnection();

		// path is not in the stat cache, so the DB is queried
		$this->assertEmpty(Vfs::stat('/sqlfs-connection-lost-test-'.uniqid()), 'not existing file is found as not existing, without exception');

		$this->assertSame($depth, $GLOBALS['egw_info']['server']['max_subquery_depth'] ?? null, 'subquery depth must not be changed');
		$this->assertSame($depth, Api\Config::read('phpgwapi')['max_subquery_depth'] ?? null, 'and not saved to the config');
	}

	public function testId2PathReconnects() : void
	{
		Vfs::$is_root = true;
		try
		{
			$dir = '/sqlfs-connection-lost-test-'.uniqid();
			$this->assertTrue(Vfs::mkdir($dir));
			$ino = Vfs::stat($dir)['ino'];
			Vfs::clearstatcache();

			$this->killSqlfsConnection();

			$this->assertSame($dir, Sqlfs\StreamWrapper::id2path($ino));
		}
		finally
		{
			Vfs::clearstatcache();
			if (!empty($dir)) Vfs::rmdir($dir);
			Vfs::$is_root = false;
		}
	}

	/**
	 * Prepared statements cached in a static variable are bound to the connection they were prepared on: after a
	 * reconnect every write failed with "MySQL server has gone away" (polluting all later tests of the process, too)
	 */
	public function testWritingAfterReconnectDoesNotUseStatementsOfTheOldConnection() : void
	{
		Vfs::$is_root = true;
		try
		{
			$dir = '/sqlfs-connection-lost-test-'.uniqid();
			$this->assertTrue(Vfs::mkdir($dir));
			// prepares (and caches) the statements adjusting the size of the directories
			$this->assertSame(1, file_put_contents(Vfs::PREFIX.$dir.'/first', 'x'));
			$old = Sqlfs\StreamWrapper::connection();

			$this->killSqlfsConnection();
			// next VFS operation replaces the dead connection
			Vfs::clearstatcache();
			(new \ReflectionProperty(Api\Db\Pdo::class, 'last_used'))->setValue(null, microtime(true) - Api\Db\Pdo::PING_AFTER - 1);

			$this->assertSame(2, file_put_contents(Vfs::PREFIX.$dir.'/second', 'yy'), 'writing a file uses the new connection');
			$this->assertNotSame($old, Sqlfs\StreamWrapper::connection(), 'connection was replaced');
			Vfs::clearstatcache();
			$this->assertSame(3, Vfs::stat($dir)['size'], 'size of the directory was adjusted by both writes');
		}
		finally
		{
			Vfs::clearstatcache();
			if (!empty($dir)) Vfs::remove($dir);
			Vfs::$is_root = false;
		}
	}
}
