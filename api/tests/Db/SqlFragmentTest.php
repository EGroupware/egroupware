<?php
/**
 * EGroupware Api: tests for Api\Db\SqlFragment and Api\Db::requireSqlFragmentObjects()
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Db\SqlFragment;
use EGroupware\Api\Db\Exception\InvalidArgument;
use PHPUnit\Framework\TestCase;

/**
 * Bootstrap mirrors api/tests/Db/ExpressionTest.php: a plain TestCase with a raw Api\Db connection.
 */
class SqlFragmentTest extends TestCase
{
	/** @var Api\Db */
	private static $db;

	/** @var array 'fd' column-definitions array for egw_test */
	private static $table_def;

	public static function setUpBeforeClass() : void
	{
		if (ini_get('session.save_handler') == 'files' && !is_writable(ini_get('session.save_path')) && is_dir('/tmp') && is_writable('/tmp'))
		{
			ini_set('session.save_path','/tmp');
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

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if (!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
		self::$table_def = self::$db->get_table_definitions('test', 'egw_test')['fd'];
	}

	// -------------------------------------------------------------------
	// SqlFragment value-object behavior
	// -------------------------------------------------------------------

	public function testToStringReturnsTheWrappedSql()
	{
		$fragment = SqlFragment::raw("t_modifier=t_modifier+1");
		$this->assertSame("t_modifier=t_modifier+1", (string)$fragment);
	}

	public function testArrayAccessReadsLikeTheUnderlyingString()
	{
		$fragment = SqlFragment::raw('abc');
		$this->assertSame('a', $fragment[0]);
		$this->assertTrue(isset($fragment[2]));
		$this->assertFalse(isset($fragment[3]));
	}

	public function testArrayAccessWriteThrows()
	{
		$fragment = SqlFragment::raw('abc');
		$this->expectException(\LogicException::class);
		$fragment[0] = 'x';
	}

	public function testArrayAccessUnsetThrows()
	{
		$fragment = SqlFragment::raw('abc');
		$this->expectException(\LogicException::class);
		unset($fragment[0]);
	}

	// -------------------------------------------------------------------
	// Db::requireSqlFragmentObjects() - getter/setter and per-instance scope
	// -------------------------------------------------------------------

	public function testRequireSqlFragmentObjectsDefaultsToFalse()
	{
		$db = clone self::$db;
		$this->assertFalse($db->requireSqlFragmentObjects());
	}

	public function testRequireSqlFragmentObjectsSetterIsFluentAndReadable()
	{
		$db = clone self::$db;
		$returned = $db->requireSqlFragmentObjects(true);

		$this->assertSame($db, $returned, 'the setter must return $this for fluent use');
		$this->assertTrue($db->requireSqlFragmentObjects());
	}

	public function testRequireSqlFragmentObjectsDoesNotLeakBetweenClones()
	{
		$strict = (clone self::$db)->requireSqlFragmentObjects(true);

		$this->assertFalse(self::$db->requireSqlFragmentObjects(),
			'opting in a clone must not affect the instance it was cloned from');
		$this->assertTrue($strict->requireSqlFragmentObjects());
	}

	// -------------------------------------------------------------------
	// column_data_implode() enforcement - the actual GHSA-vx89-j2x7-5wp2 closing mechanism
	// -------------------------------------------------------------------

	/**
	 * Default (opted-out) behavior must be entirely unchanged: a plain raw string at an integer
	 * key still works exactly as before - existing, not-yet-converted callers must not break.
	 */
	public function testRawStringAtIntegerKeyStillWorksWhenNotOptedIn()
	{
		$fragment = self::$db->column_data_implode(',', [0 => 't_modifier=t_modifier+1'], true, false, self::$table_def);

		$this->assertSame('t_modifier=t_modifier+1', (string)$fragment);
	}

	/**
	 * Once opted in, a plain (non-SqlFragment) value at an integer key must be rejected - this is
	 * the actual fix for GHSA-vx89-j2x7-5wp2's class of bug: an attacker-influenced array landing in
	 * $where can no longer smuggle an unquoted raw fragment in merely by using an integer key.
	 */
	public function testRawStringAtIntegerKeyThrowsWhenOptedIn()
	{
		$db = (clone self::$db)->requireSqlFragmentObjects(true);

		$this->expectException(InvalidArgument::class);
		$db->column_data_implode(',', [0 => 't_modifier=t_modifier+1'], true, false, self::$table_def);
	}

	/**
	 * A deliberately-constructed SqlFragment at an integer key must still work when opted in - the
	 * whole point is to make "this is intentionally raw SQL" an explicit type, not to forbid raw
	 * fragments altogether.
	 */
	public function testSqlFragmentAtIntegerKeyStillWorksWhenOptedIn()
	{
		$db = (clone self::$db)->requireSqlFragmentObjects(true);

		$fragment = $db->column_data_implode(',', [0 => SqlFragment::raw('t_modifier=t_modifier+1')], true, false, self::$table_def);

		$this->assertSame('t_modifier=t_modifier+1', (string)$fragment);
	}

	/**
	 * A normal column => value pair (string key) must be completely unaffected by opting in - only
	 * the integer-key raw-fragment path is guarded.
	 */
	public function testNormalColumnValuePairUnaffectedWhenOptedIn()
	{
		$db = (clone self::$db)->requireSqlFragmentObjects(true);

		$fragment = (string)$db->column_data_implode(' AND ', ['t_modifier' => 5], true, false, self::$table_def);

		$this->assertStringContainsString('t_modifier', $fragment);
		$this->assertStringContainsString('5', $fragment);
	}

	/**
	 * expression() - the higher-level builder - must route through the same enforcement, since it's
	 * one of the paths apps use to build $where fragments.
	 */
	public function testExpressionArrayConditionIsUnaffectedByOptIn()
	{
		$db = (clone self::$db)->requireSqlFragmentObjects(true);

		$where = (string)$db->expression(self::$table_def, ['t_modifier' => 5]);

		$this->assertStringContainsString('t_modifier', $where);
	}
}
