<?php
/**
 * EGroupware Api: tests for Api\Db::column_data_implode() and Api\Db::expression()
 *
 * Part of the Api\Db test-coverage project (doc/ai/projects/db-test-coverage.md), Phase 3.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * column_data_implode() is the shared engine behind insert()/update()/delete()/select()'s
 * column=>value and WHERE-clause building; expression() is a separate, lower-level variadic
 * WHERE-fragment builder used internally by those AND directly by some app code.
 *
 * Bootstrap mirrors api/tests/Db/QuoteTest.php exactly: a plain TestCase with a raw Api\Db
 * connection (no login needed), domain resolved via Api\Session::search_instance() since
 * $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml) which is never a real domain key.
 */
class ExpressionTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * @var array 'fd' column-definitions array for egw_test, used to exercise $only===True and
	 * $column_definitions-dependent branches
	 */
	private static $table_def;

	/**
	 * t_id's of rows created by the running test, deleted in tearDown() - this is a SHARED dev
	 * database, so we only ever delete rows we created ourselves, never truncate.
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
		$data = array_merge(array(
			't_title' => 'ExpressionTest-'.uniqid(),
			't_desc' => 'description',
		), $overrides);
		self::$db->insert('egw_test', $data, null, __LINE__, __FILE__, 'test');
		$id = (int)self::$db->get_last_insert_id('egw_test', 't_id');
		$this->created_ids[] = $id;
		return $id;
	}

	// -------------------------------------------------------------------
	// column_data_implode() - $or_null handling (a null member in an array value)
	// -------------------------------------------------------------------

	/**
	 * Behavior: for a WHERE-clause use ($use_key===True), an array value containing a literal null
	 * member is auto-detected (no separate $or_null param - it's derived from is_null($v) while
	 * iterating the array) and produces "(col IN (v1,v2) OR col IS NULL)", so NULL rows also count
	 * as a match, not just rows with one of the listed values.
	 *
	 * Setup: three rows sharing a title prefix, with t_modifier = 1, 2, and NULL (unset) respectively.
	 *
	 * Pass criteria: a select() with a col_filter/where of ['t_modifier' => [1, 2, null]] matches all
	 * three rows, not just the two with an explicit value.
	 */
	public function testColumnDataImplodeOrNullMatchesNullRowsToo()
	{
		$prefix = 'ExpressionTest-OrNull-'.uniqid();
		$id1 = $this->insertRow(['t_title' => $prefix.'-one', 't_modifier' => 1]);
		$id2 = $this->insertRow(['t_title' => $prefix.'-two', 't_modifier' => 2]);
		$id_null = $this->insertRow(['t_title' => $prefix.'-null']); // t_modifier left unset -> NULL

		// use column_data_implode() directly to build the WHERE fragment, then execute it via a real
		// query, proving both the generated SQL shape AND that it actually matches correctly
		$where = self::$db->column_data_implode(' AND ', ['t_modifier' => [1, 2, null]], True, False, self::$table_def);
		$this->assertStringContainsString('IN', $where, 'must build an IN(...) clause');
		$this->assertStringContainsString('IS NULL', $where, 'must OR in an IS NULL check for the null member');

		$ids = [];
		foreach (self::$db->select('egw_test', 't_id',
			"t_title LIKE ".self::$db->quote($prefix.'%')." AND ($where)",
			__LINE__, __FILE__, false, '', 'test') as $row)
		{
			$ids[] = (int)$row['t_id'];
		}

		$this->assertContains($id1, $ids, 't_modifier=1 row must match');
		$this->assertContains($id2, $ids, 't_modifier=2 row must match');
		$this->assertContains($id_null, $ids, 'NULL row must ALSO match via the OR IS NULL');
	}

	/**
	 * Behavior: without $use_key===True (eg. for a plain AND'ed VALUES-style list, glue !== a WHERE
	 * context), a null member in the array is NOT specially treated as "OR IS NULL" - that behavior
	 * is specifically gated on $use_key===True per the source. This locks down that the OR-NULL
	 * expansion is a WHERE-clause-only feature, not a general array-quoting behavior.
	 */
	public function testColumnDataImplodeOrNullOnlyAppliesWhenUseKeyIsTrue()
	{
		$fragment = self::$db->column_data_implode(',', ['t_modifier' => [1, 2, null]], False, False, self::$table_def);

		$this->assertStringNotContainsString('IS NULL', $fragment,
			'the OR-IS-NULL expansion must only trigger for $use_key===True (WHERE-clause usage)');
	}

	// -------------------------------------------------------------------
	// column_data_implode() - $only's three modes
	// -------------------------------------------------------------------

	/**
	 * $only unset (default False): every key in $array is written, regardless of whether it exists
	 * in $column_definitions.
	 */
	public function testColumnDataImplodeOnlyUnsetWritesEveryKey()
	{
		$fragment = self::$db->column_data_implode(',', ['t_title' => 'a', 't_desc' => 'b'], True, False, self::$table_def);

		$this->assertStringContainsString('t_title', $fragment);
		$this->assertStringContainsString('t_desc', $fragment);
	}

	/**
	 * $only as an array: acts as an explicit allow-list - only keys present in $only are written,
	 * everything else in $array is silently skipped.
	 */
	public function testColumnDataImplodeOnlyArrayActsAsAllowlist()
	{
		$fragment = self::$db->column_data_implode(',', ['t_title' => 'a', 't_desc' => 'b'], True, ['t_title'], self::$table_def);

		$this->assertStringContainsString('t_title', $fragment);
		$this->assertStringNotContainsString('t_desc', $fragment,
			'a key not present in the $only allow-list array must be skipped');
	}

	/**
	 * $only===True: only keys that are ALSO present in $column_definitions are written - a value for
	 * a column not in the table's schema is silently skipped, rather than throwing (unlike the
	 * "nothing known about column" guard, which only fires when $column_definitions is set but $only
	 * is NOT True/array - see testColumnDataImplodeThrowsForUnrecognizedColumn()).
	 */
	public function testColumnDataImplodeOnlyTrueWritesOnlyColumnsInDefinitions()
	{
		$fragment = self::$db->column_data_implode(',',
			['t_title' => 'a', 'not_a_real_column' => 'x'], True, True, self::$table_def);

		$this->assertStringContainsString('t_title', $fragment);
		$this->assertStringNotContainsString('not_a_real_column', $fragment,
			'a key absent from $column_definitions must be silently skipped when $only===True');
	}

	// -------------------------------------------------------------------
	// column_data_implode() - single-item-array-unwraps-to-scalar
	// -------------------------------------------------------------------

	/**
	 * Behavior: an array value with exactly one element is unwrapped to a scalar before quoting -
	 * "dont use IN(), if there's only one value, it's slower for MySQL" (source comment) - so
	 * ['t_modifier' => [5]] must produce the same fragment as ['t_modifier' => 5], not an IN(5).
	 */
	public function testColumnDataImplodeSingleElementArrayUnwrapsToScalar()
	{
		$fromArray = self::$db->column_data_implode(' AND ', ['t_modifier' => [5]], True, False, self::$table_def);
		$fromScalar = self::$db->column_data_implode(' AND ', ['t_modifier' => 5], True, False, self::$table_def);

		$this->assertSame($fromScalar, $fromArray);
		$this->assertStringNotContainsString('IN', $fromArray, 'a single-element array must not produce an IN(...) clause');
	}

	// -------------------------------------------------------------------
	// column_data_implode() - integer-key raw-SQL-fragment passthrough
	// -------------------------------------------------------------------

	/**
	 * Behavior: an integer array key (no column name given) is passed through as a raw SQL fragment,
	 * completely unquoted - "array('visits=visits+1') gives just 'visits=visits+1'" per the
	 * docblock. This is the SAME underlying mechanism api/tests/Storage/SaveDeleteTest.php's
	 * testUpdateWithRawSqlFragment() already exercises indirectly via Storage\Base::update() (which
	 * builds a $data array with an integer key and calls Db::update(), which calls
	 * column_data_implode() with that same array) - this test exercises it directly against
	 * column_data_implode()/Db::update() without going through the Storage layer.
	 *
	 * Pass criteria: an update() using this mechanism to increment t_modifier actually increments
	 * the stored value in the DB.
	 */
	public function testColumnDataImplodeIntegerKeyPassesThroughRawSqlFragment()
	{
		$id = $this->insertRow(['t_modifier' => 10]);

		$fragment = self::$db->column_data_implode(',', [0 => 't_modifier=t_modifier+1'], True, False, self::$table_def);
		$this->assertSame('t_modifier=t_modifier+1', $fragment,
			'an integer-keyed array value must pass through completely unquoted/unmodified');

		self::$db->update('egw_test', [0 => 't_modifier=t_modifier+1'], ['t_id' => $id], __LINE__, __FILE__, 'test');

		$row = self::$db->select('egw_test', 't_modifier', ['t_id' => $id], __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertEquals(11, $row['t_modifier'], 'the raw SQL fragment must have actually incremented the stored value');
	}

	// -------------------------------------------------------------------
	// column_data_implode() - unrecognized-column guard
	// -------------------------------------------------------------------

	/**
	 * Behavior: when $column_definitions IS given (so the method can know what's valid) and a
	 * string key doesn't match any known column (nor a valid "table.column" shape referring to a
	 * known column), column_data_implode() throws InvalidSql rather than silently building SQL that
	 * references a typo'd/malicious column name - a real safety net.
	 */
	public function testColumnDataImplodeThrowsForUnrecognizedColumn()
	{
		$this->expectException(Api\Db\Exception\InvalidSql::class);

		self::$db->column_data_implode(',', ['this_column_does_not_exist' => 'x'], True, False, self::$table_def);
	}

	/**
	 * Behavior: a "table.column" shaped key is accepted (not thrown on) as long as the column part
	 * resolves against $column_definitions - a documented escape hatch for qualified column
	 * references, distinct from the unrecognized-column guard above.
	 */
	public function testColumnDataImplodeAcceptsQualifiedTableDotColumnKey()
	{
		$fragment = self::$db->column_data_implode(',', ['egw_test.t_title' => 'x'], True, False, self::$table_def);

		$this->assertStringContainsString('t_title', $fragment);
	}

	// -------------------------------------------------------------------
	// expression() - calling convention
	// -------------------------------------------------------------------

	/**
	 * Behavior: expression() interleaves raw string literals (appended as-is) with array conditions
	 * (AND'ed together via column_data_implode()) to build a WHERE-clause fragment. This is a
	 * separate, lower-level builder from Storage\Base::parse_search() - used internally by
	 * insert/update/delete/select, and documented as also callable directly by app code.
	 *
	 * Pass criteria: the resulting fragment, embedded in a real query, matches only the row(s) it
	 * should.
	 */
	public function testExpressionCombinesStringLiteralsAndArrayConditions()
	{
		$prefix = 'ExpressionTest-Expr-'.uniqid();
		$id_match = $this->insertRow(['t_title' => $prefix.'-match', 't_modifier' => 42]);
		$id_other = $this->insertRow(['t_title' => $prefix.'-other', 't_modifier' => 99]);

		$where = self::$db->expression(self::$table_def, 't_title LIKE '.self::$db->quote($prefix.'%').' AND ',
			['t_modifier' => 42]);
		$this->assertStringContainsString('t_modifier', $where);

		$ids = [];
		foreach (self::$db->select('egw_test', 't_id', $where, __LINE__, __FILE__, false, '', 'test') as $row)
		{
			$ids[] = (int)$row['t_id'];
		}

		$this->assertContains($id_match, $ids);
		$this->assertNotContains($id_other, $ids);
	}

	/**
	 * Behavior: passing boolean false (or null, which is coerced to false) as an argument makes
	 * expression() skip/ignore the NEXT 2 arguments entirely - per the docblock: "bool: If False or
	 * is_null($arg): the next 2 (!) arguments gets ignored". A boolean true does nothing (does not
	 * trigger the skip).
	 *
	 * Pass criteria: the fragment contains the un-skipped literal 'kept' but neither the skipped
	 * literal 'never-appears' nor the skipped array condition's column name.
	 */
	public function testExpressionFalseArgumentSkipsNextTwoArguments()
	{
		$where = self::$db->expression(self::$table_def,
			false, 'never-appears-in-output', ['t_modifier' => 12345],
			'kept');

		$this->assertStringContainsString('kept', $where);
		$this->assertStringNotContainsString('never-appears-in-output', $where,
			'the string literal immediately after false must be skipped');
		$this->assertStringNotContainsString('12345', $where,
			'the array condition immediately after the skipped string must ALSO be skipped (2 args skipped total)');
	}

	/**
	 * Behavior: null, like false, triggers the skip-next-2 gate (the docblock explicitly says "False
	 * or is_null($arg)").
	 */
	public function testExpressionNullArgumentAlsoSkipsNextTwoArguments()
	{
		$where = self::$db->expression(self::$table_def,
			null, 'never-appears-either', ['t_modifier' => 54321],
			'still-kept');

		$this->assertStringContainsString('still-kept', $where);
		$this->assertStringNotContainsString('never-appears-either', $where);
		$this->assertStringNotContainsString('54321', $where);
	}

	/**
	 * Behavior: boolean true does NOT trigger the skip - it's a no-op, subsequent arguments are
	 * processed normally.
	 */
	public function testExpressionTrueArgumentDoesNotSkip()
	{
		$where = self::$db->expression(self::$table_def,
			true, 'immediately-after-true', ['t_modifier' => 77]);

		$this->assertStringContainsString('immediately-after-true', $where,
			'a true argument must NOT skip the following argument');
		$this->assertStringContainsString('77', $where);
	}
}
