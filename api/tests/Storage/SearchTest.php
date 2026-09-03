<?php
/**
 * EGroupware Api: tests for Api\Storage\Base's search machinery
 *
 * Covers parse_search() (via search()), the search() $filter injection guard,
 * search2criteria(), get_default_search_columns() and query_list() - see
 * doc/ai/projects/storage-test-coverage.md for the full behavior map and gap list this fills.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * @var Api\Storage\Base
	 */
	private $storage;

	/**
	 * autoinc ids of rows created by the running test, deleted in tearDown() - this is a SHARED
	 * dev database, so we only ever delete rows we created ourselves, never truncate.
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

		// see BaseTest.php for why this indirection is necessary (EGW_DOMAIN is literally
		// "default", never a real key in $GLOBALS['egw_domain'])
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();

		// Api\Db::connect() only calls set_capabilities() (which fixes eg. the hardcoded, invalid
		// default CAST_AS_VARCHAR capability "CAST(%s AS varchar)" - MySQL/MariaDB requires "AS
		// char" - into the correct per-driver value) when it establishes a NEW physical
		// connection. Api\Db pools the actual link in a process-wide static (self::$ADOdb); once
		// ANY earlier Api\Db instance in this process has connected to the same host/db/user, a
		// later `new Api\Db(...); ->connect()` with identical connection params just reuses that
		// pooled link and skips set_capabilities() entirely, silently leaving THIS instance's
		// $capabilities array at the generic (partly wrong) defaults. That makes search2criteria()'s
		// CAST(...)/CONCAT() query below fail with a real MariaDB syntax error - but only when
		// this test runs after another test file has already connected once in the same process
		// (eg. the full api/tests/Storage/ suite), not when run standalone. Not an Api\Storage bug -
		// pre-existing in Api\Db::connect(), flagged upstream in doc/ai/projects/storage-test-coverage.md,
		// worked around here rather than "fixed" (Db.php connection pooling is out of this file's scope).
		self::$db->set_capabilities(self::$db->Type, self::$db->ServerInfo['version'] ?? '10.0');
	}

	protected function setUp() : void
	{
		$this->storage = new Api\Storage\Base('test', 'egw_test', self::$db);
	}

	protected function tearDown() : void
	{
		foreach ($this->created_ids as $id)
		{
			self::$db->delete('egw_test', array('t_id' => $id), __LINE__, __FILE__, 'test');
		}
		$this->created_ids = [];
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if (!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
		if (!in_array('t_uniq', array_keys(self::$db->get_table_definitions('test', 'egw_test')['fd'])))
		{
			$this->markTestSkipped('egw_test fixture missing t_uniq/t_active columns (needs test app >= 17.1.002)');
		}
	}

	/**
	 * Insert a fixture row, tracking its id for cleanup in tearDown().
	 *
	 * @param array $overrides column => value overrides merged over sane defaults
	 * @return int the new row's t_id
	 */
	private function insertRow(array $overrides = []) : int
	{
		$storage = new Api\Storage\Base('test', 'egw_test', self::$db);
		$storage->data = array_merge(array(
			't_title' => 'SearchTest-'.uniqid(),
			't_desc' => 'description',
			't_modifier' => 123,
		), $overrides);
		$storage->save();
		$this->created_ids[] = $id = $storage->data['t_id'];
		return $id;
	}

	/**
	 * Behavior: parse_search() translates '*'/'?' wildcards to SQL '%'/'_' and otherwise does an
	 * exact match. This is the SQL-building logic behind every list/search box in the product.
	 *
	 * Setup: two rows with distinguishable t_title values under a common unique prefix.
	 *
	 * Pass criteria: a '*'-wildcard pattern matches both rows sharing the prefix; a plain
	 * (non-wildcard) exact value matches only the one row with that exact title.
	 */
	public function testWildcardTranslation()
	{
		$prefix = 'SearchTest-Wild-'.uniqid().'-';
		$id1 = $this->insertRow(['t_title' => $prefix.'Apple']);
		$id2 = $this->insertRow(['t_title' => $prefix.'Banana']);

		$rows = $this->storage->search(['t_title' => $prefix.'*'], false);
		$this->assertCount(2, $rows, "wildcard '*' pattern should match both rows sharing the prefix");

		$rows = $this->storage->search(['t_title' => $prefix.'Apple'], false);
		$this->assertCount(1, $rows, 'exact (non-wildcard) value should match only the one row with that title');
		$this->assertEquals($id1, $rows[0]['t_id']);
		unset($id2);
	}

	/**
	 * Behavior: a criteria value starting with '!' negates the match, wrapped as
	 * "(col IS NULL OR col NOT LIKE ...)" so NULL rows also count as "not matching".
	 *
	 * Pass criteria: searching for '!Banana' under our prefix must return the Apple row and
	 * exclude the Banana row.
	 */
	public function testNegatedCriteria()
	{
		$prefix = 'SearchTest-Neg-'.uniqid().'-';
		$id1 = $this->insertRow(['t_title' => $prefix.'Apple']);
		$id2 = $this->insertRow(['t_title' => $prefix.'Banana']);

		$rows = $this->storage->search(['t_title' => $prefix.'*'], false);
		$this->assertCount(2, $rows, 'precondition: both rows visible under prefix');

		$rows = $this->storage->search(['t_title' => '!'.$prefix.'Banana*'], false);
		$ids = array_map('intval', array_column($rows, 't_id'));
		$this->assertContains($id1, $ids, "negated criteria must still include the non-matching (Apple) row");
		$this->assertNotContains($id2, $ids, "negated criteria must exclude the matching (Banana) row");
	}

	/**
	 * Behavior: for a nullable varchar column, an empty-string criteria value (with $empty=true)
	 * is translated to "(col IS NULL OR col = '')" rather than a literal empty-string equality -
	 * so it also matches NULL rows, not just empty-string rows.
	 *
	 * Setup: one row with t_uniq left unset (NULL), one row with a real t_uniq value.
	 *
	 * Pass criteria: search(['t_uniq' => ''], only_keys=false, ..., $empty=true) matches the NULL
	 * row and not the row with a real value.
	 */
	public function testEmptyStringCriteriaOnNullableVarcharMatchesNull()
	{
		$prefix = 'SearchTest-Empty-'.uniqid();
		$id_null = $this->insertRow(['t_title' => $prefix.'-null']);	// t_uniq left unset -> NULL
		$id_set = $this->insertRow(['t_title' => $prefix.'-set', 't_uniq' => $prefix.'-uniq']);

		// $empty=false (default): an empty-string criteria value is ignored entirely (matches all)
		$rows = $this->storage->search(['t_title' => $prefix.'*', 't_uniq' => ''], false);
		$this->assertCount(2, $rows, '$empty=false must ignore the empty-string t_uniq criterion, matching both rows');

		// $empty=true: empty-string criteria on a nullable varchar becomes "IS NULL OR = ''"
		$rows = $this->storage->search(['t_title' => $prefix.'*', 't_uniq' => ''], false, '', '', '', true);
		$ids = array_map('intval', array_column($rows, 't_id'));
		$this->assertContains($id_null, $ids, "empty()=true empty-string criteria must match the NULL row");
		$this->assertNotContains($id_set, $ids, "empty()=true empty-string criteria must exclude the row with a real value");
	}

	/**
	 * Behavior: a criteria key containing a literal dot (table.column) is treated as an explicit,
	 * already-qualified SQL column reference. An array value with more than one entry becomes an
	 * IN(...) list (each value quoted per the column's real DB type via get_column_attribute()).
	 *
	 * Pass criteria: searching ['egw_test.t_title' => [titleA, titleB]] returns exactly those two
	 * rows and no others.
	 */
	public function testDottedColumnArrayValueBecomesIn()
	{
		$prefix = 'SearchTest-In-'.uniqid().'-';
		$id1 = $this->insertRow(['t_title' => $prefix.'One']);
		$id2 = $this->insertRow(['t_title' => $prefix.'Two']);
		$id3 = $this->insertRow(['t_title' => $prefix.'Three']);

		$rows = $this->storage->search(['egw_test.t_title' => [$prefix.'One', $prefix.'Two']], false);
		$ids = array_map('intval', array_column($rows, 't_id'));
		sort($ids);
		$expected = [$id1, $id2];
		sort($expected);
		$this->assertEquals($expected, $ids, 'dotted-column array value must produce an IN() match, excluding the third row');
		unset($id3);
	}

	/**
	 * Behavior (security-relevant): search()'s $filter param only special-cases the magic value
	 * "!''" into a raw, unquoted SQL fragment ("$col != ''") when $col matches a strict
	 * safe-column-name regex (/^[a-z0-9_]+(\.[a-z0-9_]+)?$/iu). This is the guard added against
	 * the CVE-2024-40614/CVE-2026-22243 family - a filter ARRAY KEY is caller/attacker-influenced
	 * in some code paths (eg. dynamic column names built from user-controlled data), so it must
	 * never be concatenated as raw SQL just because its value happens to be the magic "!''".
	 *
	 * Pass criteria:
	 *  - A real, safe column name (t_title) with value "!''" DOES get the raw-fragment treatment
	 *    and correctly filters out empty-string values (documents the legitimate use case still
	 *    works).
	 *  - An unsafe/injection-shaped "column name" with value "!''" must NOT produce a raw
	 *    "<injected> != ''" fragment in the executed SQL - the guard must route it through the
	 *    normal (quoted-value) path instead, so the generated SQL contains the value as a quoted
	 *    string literal, not as an unescaped boolean expression.
	 */
	public function testFilterBangEmptyStringOnlyRawForSafeColumnNames()
	{
		$prefix = 'SearchTest-Filter-'.uniqid().'-';
		$id_nonempty = $this->insertRow(['t_title' => $prefix.'nonempty']);

		// legitimate use: real column name -> raw "!= ''" fragment, filters correctly
		$rows = $this->storage->search(['t_title' => $prefix.'*'], false, '', '', '', false, 'AND', false,
			['t_title' => "!''"]);
		$ids = array_map('intval', array_column($rows, 't_id'));
		$this->assertContains($id_nonempty, $ids, "safe column name with '!\'\'' filter must still match non-empty values");

		// injection attempt: unsafe "column name" must NOT be concatenated as raw SQL
		$malicious = "t_id) OR (1=1";
		try
		{
			$this->storage->search(['t_title' => $prefix.'*'], false, '', '', '', false, 'AND', false,
				[$malicious => "!''"]);
		}
		catch (\Throwable $e)
		{
			// a DB error (unknown/malformed column) is an acceptable outcome here - the point is
			// that no injected SQL got executed as a boolean-always-true tautology
		}
		$sql = self::$db->Query_ID->sql ?? '';
		$this->assertStringNotContainsString($malicious.' != ', $sql,
			"unsafe filter key must never be concatenated into a raw SQL '!= ' fragment");
		$this->assertStringNotContainsString('1=1', $sql,
			"unsafe filter key's SQL-looking payload must never survive into the executed query unquoted");
	}

	/**
	 * Behavior: search2criteria() is the free-text search-box token parser. A '#123' pattern is a
	 * shortcut that limits the search to an exact primary-key match, bypassing all other columns.
	 *
	 * Pass criteria: search2criteria('#42') returns a single-element array containing an exact
	 * "(egw_test.t_id=42)" fragment - no LIKE/wildcard logic involved.
	 */
	public function testSearch2CriteriaIdShortcut()
	{
		$criteria = $this->storage->search2criteria('#42');
		$this->assertIsArray($criteria);
		$this->assertCount(1, $criteria);
		$this->assertStringContainsString('egw_test.t_id=42', $criteria[0]);
	}

	/**
	 * Behavior: search2criteria() breaks a free-text pattern into OR'ed tokens by default (no
	 * +/-/AND/OR/NOT prefix), and a bare token is wrapped with wildcards + fed into the search
	 * concatenated across all default search columns.
	 *
	 * Setup: a row whose t_desc (not t_title) contains a distinctive token, to prove
	 * search2criteria()'s multi-column CONCAT() reaches beyond just the primary display column.
	 *
	 * Pass criteria: search() with the raw string pattern (triggering search2criteria() internally
	 * via the "criteria is a non-array string" branch of search()) finds the row via its t_desc.
	 */
	public function testSearch2CriteriaFreeTextMatchesAnyColumn()
	{
		$token = 'SearchTestToken'.uniqid();
		$id = $this->insertRow(['t_title' => 'unrelated title', 't_desc' => "contains $token in the middle"]);

		$rows = $this->storage->search($token, false);
		$this->assertIsArray($rows, 'free-text search must find the row via its t_desc column');
		$ids = array_map('intval', array_column($rows, 't_id'));
		$this->assertContains($id, $ids);
	}

	/**
	 * Behavior: get_default_search_columns() (used whenever $columns_to_search isn't set) skips
	 * NUMERIC-typed columns whose name contains one of a fixed list of substrings
	 * ('_id','modified','modifier','status','cat_id','owner') - meant to exclude foreign
	 * keys/internal bookkeeping columns from free-text search. Non-numeric-typed columns are
	 * NEVER filtered by name, regardless of what they're called.
	 *
	 * Pass criteria (documents actual, verified behavior against the egw_test fixture, not
	 * assumed): t_id (numeric, name has '_id') and t_modifier (numeric, name has 'modifier') are
	 * excluded. t_title/t_desc/t_uniq (varchar) and t_active (bool) are included. t_modified is a
	 * 'timestamp'-typed column (not in the numeric-types list this method checks against) so,
	 * despite its name containing 'modified', it is NOT excluded by this method - documenting this
	 * as-is rather than assuming the name-based skip list is type-agnostic.
	 */
	public function testGetDefaultSearchColumnsExcludesKeyColumnsByNameAndType()
	{
		$method = new ReflectionMethod(Api\Storage\Base::class, 'get_default_search_columns');
		$method->setAccessible(true);
		$cols = $method->invoke($this->storage);

		$this->assertNotContains('egw_test.t_id', $cols, 't_id (numeric, name contains "_id") must be excluded');
		$this->assertNotContains('egw_test.t_modifier', $cols, 't_modifier (numeric, name contains "modifier") must be excluded');
		$this->assertContains('egw_test.t_title', $cols);
		$this->assertContains('egw_test.t_desc', $cols);
		$this->assertContains('egw_test.t_uniq', $cols);
		$this->assertContains('egw_test.t_active', $cols, 'bool-typed columns are never filtered by name');
		$this->assertContains('egw_test.t_modified', $cols,
			"documents current behavior: 'timestamp' type isn't in this method's numeric-types list, ".
			"so the name-based skip ('modified') never actually applies to it");
	}

	/**
	 * Behavior: query_list() maintains a process-wide `static $cache` inside the method itself,
	 * keyed by serialize($value_col).'-'.$key_col.'-'.serialize($filter).'-'.$order - NOT an
	 * instance property. This means a second call with identical arguments returns the FIRST
	 * call's result even if the underlying data has since changed and even across different
	 * Storage\Base instances/objects in the same PHP process - a real cross-test (and
	 * cross-request-within-the-same-process, eg. a long-running CLI job) staleness risk.
	 *
	 * Setup: insert a row, call query_list() for it once (priming the cache), then physically
	 * delete the row directly via SQL (bypassing Storage\Base, which would not itself invalidate
	 * this cache anyway) and call query_list() again with IDENTICAL arguments.
	 *
	 * Pass criteria: the second call still returns the now-deleted row's title, proving the result
	 * came from the process-wide cache and not a fresh query - this is a documentation-style
	 * regression test for the caching behavior itself, not a claim that it's a bug.
	 */
	public function testQueryListCachesAcrossCallsEvenAfterUnderlyingDataChanges()
	{
		$title = 'SearchTest-QueryList-'.uniqid();
		$id = $this->insertRow(['t_title' => $title]);

		$first = $this->storage->query_list('t_title', 't_id', ['t_id' => $id]);
		$this->assertSame($title, $first[$id] ?? null, 'precondition: query_list() finds the freshly inserted row');

		// delete directly via SQL - Storage\Base::query_list()'s cache has no invalidation hook
		self::$db->delete('egw_test', array('t_id' => $id), __LINE__, __FILE__, 'test');
		$this->created_ids = array_diff($this->created_ids, [$id]);	// already deleted, don't double-delete in tearDown

		$second = $this->storage->query_list('t_title', 't_id', ['t_id' => $id]);
		$this->assertSame($title, $second[$id] ?? null,
			'query_list() must still return the stale (deleted) value - documents the process-wide, '.
			'never-invalidated static cache; a genuinely fresh query here would return an empty array');
	}
}
