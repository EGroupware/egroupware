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

	public function testRequireSqlFragmentObjectsDefaultsToTrue()
	{
		$db = clone self::$db;
		$this->assertTrue($db->requireSqlFragmentObjects());
	}

	public function testRequireSqlFragmentObjectsSetterIsFluentAndReadable()
	{
		$db = clone self::$db;
		$returned = $db->requireSqlFragmentObjects(false);

		$this->assertSame($db, $returned, 'the setter must return $this for fluent use');
		$this->assertFalse($db->requireSqlFragmentObjects());
	}

	public function testRequireSqlFragmentObjectsDoesNotLeakBetweenClones()
	{
		$lenient = (clone self::$db)->requireSqlFragmentObjects(false);

		$this->assertTrue(self::$db->requireSqlFragmentObjects(),
			'opting out a clone must not affect the instance it was cloned from');
		$this->assertFalse($lenient->requireSqlFragmentObjects());
	}

	// -------------------------------------------------------------------
	// column_data_implode() enforcement - the actual GHSA-vx89-j2x7-5wp2 closing mechanism
	// -------------------------------------------------------------------

	/**
	 * Opted-out behavior must be entirely unchanged from the pre-default-flip convention: a plain
	 * raw string at an integer key still works - existing, not-yet-converted callers that
	 * explicitly opt out must not break.
	 */
	public function testRawStringAtIntegerKeyStillWorksWhenOptedOut()
	{
		$db = (clone self::$db)->requireSqlFragmentObjects(false);

		$fragment = $db->column_data_implode(',', [0 => 't_modifier=t_modifier+1'], true, false, self::$table_def);

		$this->assertSame('t_modifier=t_modifier+1', (string)$fragment);
	}

	/**
	 * By default (enforced), a plain (non-SqlFragment) value at an integer key must be rejected -
	 * this is the actual fix for GHSA-vx89-j2x7-5wp2's class of bug: an attacker-influenced array
	 * landing in $where can no longer smuggle an unquoted raw fragment in merely by using an
	 * integer key.
	 */
	public function testRawStringAtIntegerKeyThrowsByDefault()
	{
		$this->expectException(InvalidArgument::class);
		self::$db->column_data_implode(',', [0 => 't_modifier=t_modifier+1'], true, false, self::$table_def);
	}

	/**
	 * A deliberately-constructed SqlFragment at an integer key must still work by default - the
	 * whole point is to make "this is intentionally raw SQL" an explicit type, not to forbid raw
	 * fragments altogether.
	 */
	public function testSqlFragmentAtIntegerKeyStillWorksByDefault()
	{
		$fragment = self::$db->column_data_implode(',', [0 => SqlFragment::raw('t_modifier=t_modifier+1')], true, false, self::$table_def);

		$this->assertSame('t_modifier=t_modifier+1', (string)$fragment);
	}

	/**
	 * A normal column => value pair (string key) must be completely unaffected by the default - only
	 * the integer-key raw-fragment path is guarded.
	 */
	public function testNormalColumnValuePairUnaffectedByDefault()
	{
		$fragment = (string)self::$db->column_data_implode(' AND ', ['t_modifier' => 5], true, false, self::$table_def);

		$this->assertStringContainsString('t_modifier', $fragment);
		$this->assertStringContainsString('5', $fragment);
	}

	/**
	 * expression() - the higher-level builder - must route through the same enforcement, since it's
	 * one of the paths apps use to build $where fragments.
	 */
	public function testExpressionArrayConditionIsUnaffectedByDefault()
	{
		$where = (string)self::$db->expression(self::$table_def, ['t_modifier' => 5]);

		$this->assertStringContainsString('t_modifier', $where);
	}

	// -------------------------------------------------------------------
	// Db::select() - an empty $where must not append a dangling WHERE
	// -------------------------------------------------------------------

	/**
	 * column_data_implode() returns a SqlFragment even for an empty array, which is always truthy:
	 * select() has to check the fragment's SQL, or it appends "WHERE " with nothing after it
	 */
	public function testSelectWithEmptyWhereArrayHasNoWhereClause()
	{
		// line=false and file=false make select() return the SQL instead of running it
		$sql = self::$db->select('egw_test', '*', [], false, false);

		$this->assertStringNotContainsString('WHERE', $sql);
	}

	public function testSelectWithOnlyEmptyRawFragmentHasNoWhereClause()
	{
		$sql = self::$db->select('egw_test', '*', [SqlFragment::raw('')], false, false);

		$this->assertStringNotContainsString('WHERE', $sql);
	}

	public function testSelectWithWhereArrayStillAddsWhereClause()
	{
		$sql = self::$db->select('egw_test', '*', ['t_modifier' => 5], false, false);

		$this->assertMatchesRegularExpression('/ WHERE .*t_modifier/', $sql);
	}

	// -------------------------------------------------------------------
	// SqlFragment::compare() / ::range()
	// -------------------------------------------------------------------

	public function testCompareBuildsIntegerComparisonByDefault()
	{
		$this->assertSame('t_modifier >= 5', (string)SqlFragment::compare(self::$db, 't_modifier', '>=', 5));
	}

	public function testCompareLowercasesOperatorIsAccepted()
	{
		$this->assertSame('t_modifier <= 5', (string)SqlFragment::compare(self::$db, 't_modifier', '<=', 5));
	}

	public function testCompareWithFloatTypeKeepsPrecision()
	{
		$this->assertSame('t_modifier >= 1.5', (string)SqlFragment::compare(self::$db, 't_modifier', '>=', 1.5, 'float'));
	}

	public function testCompareWithDefaultIntTypeTruncatesAFloat()
	{
		$this->assertSame('t_modifier >= 1', (string)SqlFragment::compare(self::$db, 't_modifier', '>=', 1.9));
	}

	public function testCompareRejectsAnInvalidOperator()
	{
		$this->expectException(\InvalidArgumentException::class);
		SqlFragment::compare(self::$db, 't_modifier', 'OR 1=1-- ', 5);
	}

	public function testCompareRejectsAnInvalidType()
	{
		$this->expectException(\InvalidArgumentException::class);
		SqlFragment::compare(self::$db, 't_modifier', '>=', 5, 'bogus');
	}

	/**
	 * $type is the caller's own stated intent, not inferred from $value's runtime type - a numeric
	 * value with $type='string' is still quoted, eg. "pe_status != '5'" (admittedly an odd thing to
	 * ask for, but it proves $type decides, not is_string($value)).
	 */
	public function testCompareWithStringTypeQuotesEvenANumericValue()
	{
		$this->assertSame("pe_status != '5'", (string)SqlFragment::compare(self::$db, 'pe_status', '!=', 5, 'string'));
	}

	/**
	 * A string $value with $type='string' (eg. "pe_status != 'ignore'") is quoted via $db->quote(),
	 * never spliced in raw - an embedded quote in the value must come out escaped, not able to close
	 * the literal early.
	 */
	public function testCompareWithStringTypeIsQuotedNotSpliced()
	{
		$sql = (string)SqlFragment::compare(self::$db, 'pe_status', '!=', "o' OR '1'='1", 'string');

		$this->assertStringStartsWith("pe_status != '", $sql);
		$this->assertStringNotContainsString("' OR '1'='1", $sql,
			"the attacker-controlled quote must be escaped, not close the string literal early");
	}

	/**
	 * The default $type='int' is the caller's choice too - a string value is silently (int)-cast, same
	 * as every hand-written version of this comparison already did, not rejected or auto-quoted.
	 */
	public function testCompareWithDefaultIntTypeCastsAStringValue()
	{
		$this->assertSame('t_modifier >= 5', (string)SqlFragment::compare(self::$db, 't_modifier', '>=', '5 OR 1=1-- '));
	}

	/**
	 * $value is declared int|float|string - PHP's own type coercion rejects a non-scalar value (eg.
	 * an array) with a TypeError regardless of $type, since the declared type doesn't accept it.
	 */
	public function testCompareRejectsANonScalarValue()
	{
		$this->expectException(\TypeError::class);
		SqlFragment::compare(self::$db, 't_modifier', '>=', ['5 OR 1=1-- ']);
	}

	public function testRangeWithBothBoundsReturnsTwoFragments()
	{
		$fragments = SqlFragment::range(self::$db, 't_modifier', 2, 10);

		$this->assertCount(2, $fragments);
		$this->assertSame('t_modifier >= 2', (string)$fragments[0]);
		$this->assertSame('t_modifier <= 10', (string)$fragments[1]);
	}

	/**
	 * A falsy bound (null, '', 0) is omitted entirely, matching every hand-written version of this
	 * range-filter pattern this replaces (eg. resources/infolog export_csv's "if($value['from'])").
	 */
	public function testRangeWithAFalsyBoundOmitsThatSide()
	{
		$this->assertCount(1, SqlFragment::range(self::$db, 't_modifier', 2, null));
		$this->assertCount(1, SqlFragment::range(self::$db, 't_modifier', 2, ''));
		$this->assertCount(1, SqlFragment::range(self::$db, 't_modifier', 2, 0));
		$this->assertCount(0, SqlFragment::range(self::$db, 't_modifier', null, null));
	}
}
