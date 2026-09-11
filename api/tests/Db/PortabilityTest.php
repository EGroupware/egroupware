<?php
/**
 * EGroupware Api: tests for Api\Db's cross-engine SQL-portability abstraction functions
 *
 * Part of the Api\Db test-coverage project (doc/ai/projects/db-test-coverage.md), Phase 2.
 *
 * group_concat(), regexp_replace(), strpos(), unix_timestamp(), from_unixtime(), date_format(),
 * to_double(), to_int() and to_varchar() all branch purely on $this->Type (and, for
 * group_concat()/regexp_replace(), $this->ServerInfo['version']) - none of them call connect()
 * internally except indirectly via quote() for a couple of sub-cases (group_concat()'s custom
 * $separator, regexp_replace()'s $regexp). Because of that we use ONE real, live-connected Db
 * instance (same bootstrap as api/tests/Storage/BaseTest.php) and simply override ->Type/
 * ->ServerInfo per test case before calling the method under test - the underlying ADOdb Link_ID
 * stays bound to the REAL driver throughout, so quote()'s escaping is always genuinely correct,
 * while the method under test branches on whatever ->Type we told it to pretend to be. This lets
 * every engine branch (mysql/postgres/mssql) be exercised with a single connection, without
 * needing a live connection of each engine type.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class PortabilityTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * Real ->Type, saved once and restored after each test so overriding it for one test case
	 * never leaks into another (self::$db is shared across all test methods in this class).
	 *
	 * @var string
	 */
	private static $real_type;

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

		// see api/tests/Storage/BaseTest.php for why this resolution is needed instead of a
		// direct $GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']] index
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
		self::$real_type = self::$db->Type;
	}

	protected function tearDown() : void
	{
		self::$db->Type = self::$real_type;
		unset(self::$db->ServerInfo);
	}

	// -------------------------------------------------------------------
	// group_concat()
	// -------------------------------------------------------------------

	public function testGroupConcatMysql()
	{
		self::$db->Type = 'mysqli';
		$this->assertSame('GROUP_CONCAT(col)', self::$db->group_concat('col'));
	}

	public function testGroupConcatMysqlWithOrderBy()
	{
		self::$db->Type = 'mysqli';
		$this->assertSame('GROUP_CONCAT(col ORDER BY col ASC)', self::$db->group_concat('col', 'col ASC'));
	}

	public function testGroupConcatMysqlWithCustomSeparator()
	{
		self::$db->Type = 'mysqli';
		// non-default separator goes through quote() - exercises the real connection's qstr()
		$this->assertSame("GROUP_CONCAT(col SEPARATOR '|')", self::$db->group_concat('col', '', '|'));
	}

	public function testGroupConcatPostgresBelow84ReturnsFalse()
	{
		self::$db->Type = 'pgsql';
		self::$db->ServerInfo = ['version' => '8.3'];
		$this->assertFalse(self::$db->group_concat('col'),
			'ARRAY_AGG() needs Postgres >= 8.4 (or a custom method installed for older versions)');
	}

	public function testGroupConcatPostgresAt84OrAbove()
	{
		self::$db->Type = 'pgsql';
		self::$db->ServerInfo = ['version' => '9.6'];
		$this->assertSame("ARRAY_TO_STRING(ARRAY_AGG(col), ',')", self::$db->group_concat('col'));
	}

	public function testGroupConcatPostgresWithOrderBy()
	{
		self::$db->Type = 'pgsql';
		self::$db->ServerInfo = ['version' => '9.6'];
		$this->assertSame("ARRAY_TO_STRING(ARRAY_AGG(col ORDER BY col ASC), ',')",
			self::$db->group_concat('col', 'col ASC'));
	}

	public function testGroupConcatUnsupportedTypeReturnsFalse()
	{
		self::$db->Type = 'mssql';
		$this->assertFalse(self::$db->group_concat('col'), 'mssql has no GROUP_CONCAT-equivalent branch');
	}

	// -------------------------------------------------------------------
	// regexp_replace()
	// -------------------------------------------------------------------

	public function testRegexpReplaceMysqlBelow8ReturnsExprUnchanged()
	{
		self::$db->Type = 'mysqli';
		self::$db->ServerInfo = ['version' => '5.7'];
		$this->assertSame('col', self::$db->regexp_replace('col', '[0-9]', "''"),
			'REGEXP_REPLACE() needs MySQL 8.0 / MariaDB 10.0, older versions get back the untouched $expr');
	}

	public function testRegexpReplaceMysqlAt8OrAbove()
	{
		self::$db->Type = 'mysqli';
		self::$db->ServerInfo = ['version' => '8.0'];
		$this->assertSame("REGEXP_REPLACE(col,'[0-9]','')", self::$db->regexp_replace('col', '[0-9]', "''"));
	}

	public function testRegexpReplacePostgres()
	{
		self::$db->Type = 'pgsql';
		$this->assertSame("REGEXP_REPLACE(col,'[0-9]','')", self::$db->regexp_replace('col', '[0-9]', "''"));
	}

	public function testRegexpReplaceUnsupportedTypeReturnsExprUnchanged()
	{
		self::$db->Type = 'mssql';
		$this->assertSame('col', self::$db->regexp_replace('col', '[0-9]', "''"));
	}

	// -------------------------------------------------------------------
	// strpos() - deliberately NOT testing an unknown ->Type: that branch calls die()
	// -------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider('strposProvider')]
	public function testStrpos($type, $expected)
	{
		self::$db->Type = $type;
		$this->assertSame($expected, self::$db->strpos('haystack', 'needle'));
	}

	public static function strposProvider() : array
	{
		return [
			'mysql'  => ['mysql',  'LOCATE(needle,haystack)'],
			'mysqli' => ['mysqli', 'LOCATE(needle,haystack)'],
			'pgsql'  => ['pgsql',  'STRPOS(haystack,needle)'],
			'mssql'  => ['mssql',  'CHARINDEX(needle,haystack)'],
		];
	}

	// -------------------------------------------------------------------
	// unix_timestamp() / from_unixtime()
	// -------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider('unixTimestampProvider')]
	public function testUnixTimestamp($type, $expected)
	{
		self::$db->Type = $type;
		$this->assertSame($expected, self::$db->unix_timestamp('ts_col'));
	}

	public static function unixTimestampProvider() : array
	{
		return [
			'mysql'  => ['mysql',  'UNIX_TIMESTAMP(ts_col)'],
			'mysqli' => ['mysqli', 'UNIX_TIMESTAMP(ts_col)'],
			'pgsql'  => ['pgsql',  'EXTRACT(EPOCH FROM CAST(ts_col AS TIMESTAMP))'],
			'mssql'  => ['mssql',  "DATEDIFF(second,'1970-01-01',(ts_col))"],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('fromUnixtimeProvider')]
	public function testFromUnixtime($type, $expected)
	{
		self::$db->Type = $type;
		$this->assertSame($expected, self::$db->from_unixtime('int_col'));
	}

	public static function fromUnixtimeProvider() : array
	{
		return [
			'mysql'  => ['mysql',  'FROM_UNIXTIME(int_col)'],
			'mysqli' => ['mysqli', 'FROM_UNIXTIME(int_col)'],
			'pgsql'  => ['pgsql',  "(TIMESTAMP WITH TIME ZONE 'epoch' + (int_col) * INTERVAL '1 sec')"],
			// mssql stores server-time, so it adds seconds onto the literal 1970-01-01 00:00:00 epoch
			'mssql'  => ['mssql',  "DATEADD(second,(int_col),'".date('Y-m-d H:i:s', 0)."')"],
		];
	}

	public function testFromUnixtimeUnsupportedTypeReturnsFalse()
	{
		self::$db->Type = 'sqlite';
		$this->assertFalse(self::$db->from_unixtime('int_col'));
	}

	// -------------------------------------------------------------------
	// date_format() - the most bug-prone of these: per-character format-string translation
	// -------------------------------------------------------------------

	public function testDateFormatMysqlPassesFormatThroughLiterally()
	{
		self::$db->Type = 'mysqli';
		$this->assertSame("DATE_FORMAT(ts_col,'%Y-%m-%d %H:%i:%s')",
			self::$db->date_format('ts_col', '%Y-%m-%d %H:%i:%s'));
	}

	/**
	 * Postgres translates EVERY %-format character it recognizes via a fixed str_replace() table
	 * (%Y->YYYY, %y->YY, %m->MM, %d->DD, %H->HH24, %h->HH, %i->MI, %s->SS, %V/%v->IW, %X/%x->YYYY)
	 * before wrapping in TO_CHAR(). Uses one format string exercising every mapped character at
	 * once, not just one, since a single-character test could hide an incomplete translation table.
	 */
	public function testDateFormatPostgresTranslatesEveryKnownFormatCharacter()
	{
		self::$db->Type = 'pgsql';
		$result = self::$db->date_format('ts_col', '%Y-%y-%m-%d %H:%h:%i:%s %V-%v-%X-%x');
		$this->assertSame("TO_CHAR(ts_col,'YYYY-YY-MM-DD HH24:HH:MI:SS IW-IW-YYYY-YYYY')", $result);
	}

	/**
	 * mssql builds the result by splicing '+DATEPART(...)+' string-concatenation fragments
	 * directly into the format string, then cleans up the empty "''+"/"+''" segments left behind
	 * when two placeholders are adjacent (no literal text between them) - tested separately below
	 * since it's a distinct code path from the single-placeholder substitution itself.
	 */
	public function testDateFormatMssqlSinglePlaceholder()
	{
		self::$db->Type = 'mssql';
		$this->assertSame("'+DATEPART(yyyy,(ts_col))+'", self::$db->date_format('ts_col', '%Y'));
	}

	public function testDateFormatMssqlWithLiteralSeparatorBetweenPlaceholders()
	{
		self::$db->Type = 'mssql';
		$this->assertSame("'+DATEPART(yyyy,(ts_col))+'-'+DATEPART(mm,(ts_col))+'",
			self::$db->date_format('ts_col', '%Y-%m'));
	}

	/**
	 * Two placeholders with NO literal text between them: the naive per-placeholder substitution
	 * would leave a redundant "+''+" (string-concat an empty string) between them - the method
	 * strips that via its trailing "''+"/"+''" cleanup passes.
	 */
	public function testDateFormatMssqlCollapsesEmptyConcatBetweenAdjacentPlaceholders()
	{
		self::$db->Type = 'mssql';
		$this->assertSame("'+DATEPART(yyyy,(ts_col))+DATEPART(mm,(ts_col))+'",
			self::$db->date_format('ts_col', '%Y%m'));
	}

	public function testDateFormatUnsupportedTypeReturnsFalse()
	{
		self::$db->Type = 'sqlite';
		$this->assertFalse(self::$db->date_format('ts_col', '%Y'));
	}

	// -------------------------------------------------------------------
	// to_double() / to_int() / to_varchar()
	// -------------------------------------------------------------------

	#[\PHPUnit\Framework\Attributes\DataProvider('toDoubleProvider')]
	public function testToDouble($type, $expected)
	{
		self::$db->Type = $type;
		$this->assertSame($expected, self::$db->to_double('col'));
	}

	public static function toDoubleProvider() : array
	{
		return [
			'pgsql'          => ['pgsql', 'col::double'],
			'mysql'          => ['mysql', 'CAST(col AS DECIMAL(24,3))'],
			'mysqli'         => ['mysqli', 'CAST(col AS DECIMAL(24,3))'],
			// no mssql-specific branch: falls through to the unchanged $expr
			'unsupported (mssql)' => ['mssql', 'col'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('toIntProvider')]
	public function testToInt($type, $expected)
	{
		self::$db->Type = $type;
		$this->assertSame($expected, self::$db->to_int('col'));
	}

	public static function toIntProvider() : array
	{
		return [
			'pgsql'                => ['pgsql', 'col::integer'],
			'mysql'                => ['mysql', 'CAST(col AS SIGNED)'],
			'mysqli'               => ['mysqli', 'CAST(col AS SIGNED)'],
			'unsupported (mssql)'  => ['mssql', 'col'],
		];
	}

	/**
	 * Unlike to_double()/to_int(), to_varchar() has NO mysql-specific branch at all - mysql (and
	 * every other non-pgsql type) just gets $expr back unchanged. Documented here as observed
	 * behavior, not asserted to be a bug - not enough context on every caller to judge that.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('toVarcharProvider')]
	public function testToVarchar($type, $expected)
	{
		self::$db->Type = $type;
		$this->assertSame($expected, self::$db->to_varchar('col'));
	}

	public static function toVarcharProvider() : array
	{
		return [
			'pgsql'               => ['pgsql', 'CAST(col AS varchar)'],
			'mysql (no cast!)'    => ['mysql', 'col'],
			'mysqli (no cast!)'   => ['mysqli', 'col'],
			'mssql (no cast!)'    => ['mssql', 'col'],
		];
	}
}
