<?php
/**
 * EGroupware Api: tests for Api\Db::quote()/name_quote() and query()'s readonly mode + error
 * classification
 *
 * Part of the Api\Db test-coverage project (doc/ai/projects/db-test-coverage.md), Phase 1.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * quote() is the last line of defense against SQL injection for every value Api\Db writes.
 * These tests prove correctness the strongest way possible: round-tripping an adversarial value
 * through a REAL `SELECT <quoted> AS x` query and asserting the result is byte-identical to the
 * original PHP value - not just "the quoted string looks safe".
 *
 * Bootstrap mirrors api/tests/Storage/BaseTest.php exactly: a plain TestCase with a raw Api\Db
 * connection (no login needed), domain resolved via Api\Session::search_instance() since
 * $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml) which is never a real domain key.
 */
class QuoteTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * Full connection-param array for this domain, so individual tests can open their OWN fresh
	 * Db instance when they need one that's isolated from self::$db (eg. the readonly test).
	 *
	 * @var array
	 */
	private static $db_data;

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
		// api/tests/Storage/BaseTest.php do, via Session::search_instance()'s fallback-to-first-
		// domain logic, instead of indexing $GLOBALS['egw_domain'] directly.
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

	// -------------------------------------------------------------------
	// quote() - injection round-trip matrix
	// -------------------------------------------------------------------

	/**
	 * The strongest possible correctness proof: embed quote()'s output directly into a real
	 * `SELECT <quoted> AS x` query, execute it, and assert the returned value is byte-identical to
	 * the original PHP string - not just "doesn't look dangerous". If quote() were ever tricked into
	 * emitting unescaped SQL, one of these adversarial values would either break the query (visible
	 * as a thrown exception) or come back altered/truncated (visible as an assertion failure).
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('adversarialValueProvider')]
	public function testQuoteRoundTripsAdversarialStringValues($value)
	{
		$quoted = self::$db->quote($value);
		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame($value, $row['x'],
			'quote() must round-trip the exact original value through a real query, with no corruption or injection');
	}

	public static function adversarialValueProvider() : array
	{
		return [
			'single quote' => ["O'Brien"],
			'doubled single quote' => ["it''s already doubled"],
			'backslash then quote' => ["a\\'b"],
			'sql line comment' => ["value -- DROP TABLE egw_test"],
			'sql block comment' => ["value /* comment */ end"],
			'stacked query attempt' => ["x'; DROP TABLE egw_test; --"],
			'union injection attempt' => ["' UNION SELECT t_uniq FROM egw_test -- "],
			'nul byte' => ["before\0after"],
			'like wildcards (must be literal here, quote() is not a LIKE-pattern builder)' => ["100%_test"],
			'backslash only' => ["back\\slash"],
			'newline' => ["line1\nline2"],
			'double-quote character' => ['contains "a double quote"'],
			'backtick (identifier-quote char, irrelevant here but worth covering)' => ["has`backtick"],
		];
	}

	/**
	 * The value passed as the array element IS the $glue string itself - documents that this is NOT
	 * a security issue (unlike eg. a naive IN(...) builder): quote() implodes the array into ONE
	 * plain string BEFORE quoting it as a single SQL value, so there is no per-element SQL boundary
	 * for a glue-colliding element to escape from.
	 */
	public function testQuoteArrayValueWhereElementContainsGlueStringIsStillSafe()
	{
		$quoted = self::$db->quote(['a,b', 'c'], false, true, null, ',');
		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame('a,b,c', $row['x'],
			'Array values are implode()d into one string before quoting - a glue-colliding element is not a boundary escape');
	}

	public function testQuoteArrayValueImplodesWithGivenGlue()
	{
		$quoted = self::$db->quote(['a', 'b', 'c'], false, true, null, '|');
		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame('a|b|c', $row['x']);
	}

	// -------------------------------------------------------------------
	// quote() - null / $not_null footgun
	// -------------------------------------------------------------------

	/**
	 * quote($value, $type, $not_null) has a real, easy-to-misuse footgun: a PHP null only becomes
	 * SQL NULL if the caller explicitly passes $not_null=false. With the default $not_null=true (the
	 * common case), a null value silently falls through to (string)null === '' and gets quoted as an
	 * EMPTY STRING, not SQL NULL - see testQuoteNullWithDefaultNotNullTrueBecomesEmptyStringNotSqlNull().
	 */
	public function testQuoteNullWithNotNullFalseReturnsLiteralSqlNull()
	{
		$quoted = self::$db->quote(null, false, false);
		$this->assertSame('NULL', $quoted, 'Must be the unquoted literal NULL keyword, not a quoted string');

		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();
		$this->assertNull($row['x']);
	}

	public function testQuoteNullWithDefaultNotNullTrueBecomesEmptyStringNotSqlNull()
	{
		$quoted = self::$db->quote(null); // $not_null defaults to true
		$this->assertNotSame('NULL', $quoted,
			'FOOTGUN documented: with the default $not_null=true, a PHP null does NOT become SQL NULL');

		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();
		$this->assertSame('', $row['x'], 'Falls through to (string)null === "", quoted as an empty string');
	}

	// -------------------------------------------------------------------
	// quote() - multi-byte / encoding edge cases
	// -------------------------------------------------------------------

	/**
	 * $length truncation uses mb_substr() (character-aware), not substr() (byte-aware) - a
	 * multi-byte string must truncate at a CHARACTER boundary, never splitting a character's bytes
	 * and corrupting the encoding.
	 */
	public function testQuoteTruncatesMultibyteStringAtCharacterBoundary()
	{
		$value = str_repeat('日', 10); // each character is 3 bytes in UTF-8

		$quoted = self::$db->quote($value, false, true, 5);
		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame(str_repeat('日', 5), $row['x'],
			'Must truncate to exactly 5 whole characters (15 bytes), not corrupt a character at a byte boundary');
	}

	/**
	 * MySQL/MariaDB (the DB type actually in use here) can't store 4-byte UTF-8 characters (astral
	 * plane, eg. real emoji) in the schema's charset - quote() replaces them with the UTF-8 encoding
	 * of the U+FFFD replacement character before the value ever reaches qstr()/the database.
	 */
	public function testQuoteReplacesAstralPlaneCharactersForMysql()
	{
		$this->assertStringStartsWith('mysql', self::$db->Type,
			'This test asserts MySQL/MariaDB-specific behavior; skip/adjust if run against a different Type');

		$value = "before\u{1F389}after"; // 🎉 U+1F389, a 4-byte UTF-8 character
		$quoted = self::$db->quote($value);
		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame("before\xEF\xBF\xBDafter", $row['x'],
			'Astral-plane character must be replaced with the UTF-8 encoding of U+FFFD');
	}

	// -------------------------------------------------------------------
	// quote() - non-string/object values
	// -------------------------------------------------------------------

	public function testQuoteObjectWithToStringDegradesToItsStringRepresentation()
	{
		$obj = new class {
			public function __toString() : string { return 'stringified'; }
		};

		$quoted = self::$db->quote($obj);
		$row = self::$db->query('SELECT '.$quoted.' AS x', __LINE__, __FILE__)->fetch();

		$this->assertSame('stringified', $row['x'],
			'A generic (non-DateTime) object reaching the final (string) cast must invoke __toString() cleanly');
	}

	// -------------------------------------------------------------------
	// name_quote()
	// -------------------------------------------------------------------

	/**
	 * name_quote() deliberately returns any value containing a space, or starting with "CASE ",
	 * COMPLETELY UNQUOTED/as-is - a documented escape hatch for "this is already a SQL expression,
	 * not a plain identifier". Real column/table names are trusted (from app code), but this is the
	 * exact mechanism that lets an expression bypass identifier-quoting entirely - worth locking down
	 * as an explicit contract, not an accident.
	 */
	public function testNameQuoteReturnsSpaceContainingExpressionUnquoted()
	{
		$expr = 'some expression';
		$this->assertSame($expr, self::$db->name_quote($expr));
	}

	public function testNameQuoteReturnsCaseExpressionUnquoted()
	{
		$expr = 'CASE WHEN x THEN 1 ELSE 0 END';
		$this->assertSame($expr, self::$db->name_quote($expr));
	}

	/**
	 * A normal, no-special-character identifier (including a dotted table.column form, quoted
	 * per-segment) is returned unquoted for MySQL - only names with special characters, or the
	 * reserved word "index", get identifier-quoted.
	 */
	public function testNameQuotePlainDottedIdentifierIsNotQuotedForMysql()
	{
		$this->assertStringStartsWith('mysql', self::$db->Type,
			'This test asserts MySQL-specific behavior; skip/adjust if run against a different Type');

		$this->assertSame('egw_test.t_id', self::$db->name_quote('egw_test.t_id'));
	}

	public function testNameQuoteQuotesReservedWordIndex()
	{
		$quoted = self::$db->name_quote('index');

		$this->assertNotSame('index', $quoted, '"index" is a reserved word and must be identifier-quoted');
		$this->assertStringContainsString('index', $quoted);
	}

	// -------------------------------------------------------------------
	// query() - readonly mode
	// -------------------------------------------------------------------

	/**
	 * $db->readonly = true blocks any non-SELECT/SET/SHOW query WITHOUT executing it at all - a real
	 * safety feature (eg. for a read-replica or maintenance-mode connection). Uses a FRESH Db
	 * instance so this doesn't affect self::$db (the shared connection other tests rely on) - setting
	 * ->readonly is a plain instance property, not shared via Db's static connection pool.
	 */
	public function testQueryReadonlyModeBlocksWritesWithoutExecuting()
	{
		$readonlyDb = new Api\Db(self::$db_data);
		$readonlyDb->connect();
		$readonlyDb->readonly = true;

		// sanity: a SELECT must still work normally in readonly mode
		$row = $readonlyDb->query('SELECT 1 AS x', __LINE__, __FILE__)->fetch();
		$this->assertEquals(1, $row['x']);

		$title = 'QuoteTest-readonly-'.bin2hex(random_bytes(4));
		$result = $readonlyDb->query(
			'INSERT INTO egw_test (t_title) VALUES ('.$readonlyDb->quote($title).')', __LINE__, __FILE__);

		$this->assertSame(0, $result, 'A write query in readonly mode must return 0 without executing');
		$this->assertSame('Database is readonly', $readonlyDb->Error);

		// confirm, via the REAL (non-readonly) connection, that nothing was actually inserted
		$found = self::$db->query('SELECT COUNT(*) AS c FROM egw_test WHERE t_title='.self::$db->quote($title),
			__LINE__, __FILE__)->fetch();
		$this->assertEquals(0, $found['c'], 'readonly mode must not actually execute the blocked write');
	}

	// -------------------------------------------------------------------
	// query() - error classification
	// -------------------------------------------------------------------

	/**
	 * query()'s error-classification code READS as if it distinguishes InvalidSql (for a MySQL
	 * error code in [1064 syntax, 1062 duplicate-key, 1054 unknown-column]) from a generic
	 * Db\Exception for everything else - but that distinction lives in the
	 * `catch(\mysqli_sql_exception $e)` block, which requires mysqli's modern exception-throwing
	 * mode. Empirically verified (this session) that mysqli is NOT in that mode in this actual
	 * runtime: Execute()/SelectLimit() return false silently instead, so EVERY query failure here -
	 * including a non-existent-table error (code 1146, NOT in the InvalidSql code list) - falls
	 * through the OTHER path (the unconditional `if (!$rs) throw new InvalidSql(...)` at the end of
	 * query()) and comes back as InvalidSql regardless of the underlying error code. This test locks
	 * down that ACTUAL behavior rather than the code-discrimination behavior the catch block's logic
	 * would suggest - the fine-grained code-based classification is effectively dead code under this
	 * environment's mysqli configuration. Also documents that $e->details (only set by the
	 * catch-block's re-throw) is NEVER populated via this path - the SQL text instead appears
	 * directly in $e->getMessage() (prefixed "Invalid SQL: ").
	 */
	public function testQuerySyntaxErrorThrowsInvalidSqlWithSqlInMessage()
	{
		$sql = 'SELECT FROM WHERE this is not valid sql';

		try
		{
			self::$db->query($sql, __LINE__, __FILE__);
			$this->fail('Expected an exception for malformed SQL');
		}
		catch (Api\Db\Exception\InvalidSql $e)
		{
			$this->assertStringContainsString($sql, $e->getMessage(),
				'The original SQL must be recoverable from the exception for logging, here via getMessage() not ->details');
		}
	}

	public function testQueryUnknownTableThrowsInvalidSqlDespiteErrorCode1146NotBeingInTheClassificationList()
	{
		$table = 'egw_nonexistent_table_'.bin2hex(random_bytes(4));

		try
		{
			self::$db->query("SELECT * FROM $table", __LINE__, __FILE__);
			$this->fail('Expected an exception for a non-existent table');
		}
		catch (Api\Db\Exception\InvalidSql $e)
		{
			// documents actual behavior - see class doc-comment above for why this is InvalidSql
			// and not the generic Db\Exception the catch-block's code-list would otherwise imply
			$this->assertStringContainsString($table, $e->getMessage());
		}
	}

	public function testQueryDuplicateKeyThrowsInvalidSql()
	{
		$uniq = 'QuoteTest-dup-'.bin2hex(random_bytes(4));
		self::$db->insert('egw_test', ['t_uniq' => $uniq], null, __LINE__, __FILE__, 'test');

		try
		{
			self::$db->query('INSERT INTO egw_test (t_uniq) VALUES ('.self::$db->quote($uniq).')', __LINE__, __FILE__);
			$this->fail('Expected an exception for a duplicate-key insert');
		}
		catch (Api\Db\Exception\InvalidSql $e)
		{
			$this->assertNotEmpty($e->getMessage());
		}
		finally
		{
			self::$db->delete('egw_test', ['t_uniq' => $uniq], __LINE__, __FILE__, 'test');
		}
	}
}
