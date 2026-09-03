<?php
/**
 * EGroupware Api: tests for Api\Db::union(), transaction/locking methods, and strip_array_keys()
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
 */
class UnionTransactionTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

	/**
	 * Full connection-param array for this domain, so tests can open their OWN fresh Db instance
	 * when they need one (eg. to prove a commit is visible via a genuinely separate connection).
	 *
	 * @var array
	 */
	private static $db_data;

	/**
	 * t_id's created by the running test, deleted in tearDown() - this is a SHARED dev database,
	 * so we only ever delete rows we created ourselves, never truncate.
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
	 */
	private function insertRow(array $overrides = []) : int
	{
		self::$db->insert('egw_test', array_merge(array(
			't_title' => 'UnionTransactionTest-'.bin2hex(random_bytes(4)),
			't_desc' => 'description',
			't_modifier' => 123,
		), $overrides), null, __LINE__, __FILE__, 'test');
		$id = self::$db->get_last_insert_id('egw_test', 't_id');
		$this->created_ids[] = $id;
		return $id;
	}

	// -------------------------------------------------------------------
	// union() - single-select branch
	// -------------------------------------------------------------------

	/**
	 * Behavior: with exactly ONE select in $selects, union() does NOT wrap the query in a UNION at
	 * all - instead it does string surgery: 'SELECT DISTINCT'.substr($union[0], 6), stripping the
	 * literal 6-character "SELECT" prefix off the built select() SQL and re-prefixing with
	 * "SELECT DISTINCT". Each $selects[] entry is fed to select() with $line=false/$file=false/
	 * $offset=false, which per select()'s own code ("call by union, to return the sql rather than
	 * run the query") makes it return the raw SQL STRING instead of executing - that's what makes
	 * the string-surgery approach work at all.
	 *
	 * Setup: two rows sharing a title prefix, one of them duplicated in a way DISTINCT can collapse
	 * (same t_modifier value on two different rows - DISTINCT operates on the selected column list,
	 * so selecting only t_modifier must collapse them to one row).
	 *
	 * Pass criteria: selecting only t_modifier (both rows share the same value) via a single-select
	 * union() returns exactly ONE row - proving the DISTINCT actually applied (a UNION ALL or a plain
	 * SELECT without DISTINCT would return two).
	 */
	public function testUnionSingleSelectAppliesDistinct()
	{
		$prefix = 'UnionTransactionTest-single-'.uniqid();
		$this->insertRow(['t_title' => $prefix.'-a', 't_modifier' => 999]);
		$this->insertRow(['t_title' => $prefix.'-b', 't_modifier' => 999]);

		$rs = self::$db->union([
			['table' => 'egw_test', 'cols' => 't_modifier', 'where' => ['t_title' => [$prefix.'-a', $prefix.'-b']], 'app' => 'test'],
		], __LINE__, __FILE__);

		$rows = iterator_to_array($rs);
		$this->assertCount(1, $rows, 'DISTINCT must collapse the two rows sharing t_modifier=999 into one');
		$this->assertEquals(999, $rows[0]['t_modifier']);
	}

	// -------------------------------------------------------------------
	// union() - multi-select branch
	// -------------------------------------------------------------------

	/**
	 * Behavior: with 2+ selects, union() parenthesizes each select()-built SQL fragment and joins
	 * them with "\nUNION\n": "(...)\nUNION\n(...)". Real UNION semantics apply: rows are combined
	 * and de-duplicated (not UNION ALL).
	 *
	 * Setup: two distinct rows, each matched by a DIFFERENT single-row $where filter passed as a
	 * separate $selects[] entry.
	 *
	 * Pass criteria: the combined result contains both rows' t_title values, and nothing else.
	 */
	public function testUnionMultiSelectCombinesRows()
	{
		$prefix = 'UnionTransactionTest-multi-'.uniqid();
		$idA = $this->insertRow(['t_title' => $prefix.'-a']);
		$idB = $this->insertRow(['t_title' => $prefix.'-b']);

		$rs = self::$db->union([
			['table' => 'egw_test', 'cols' => 't_id,t_title', 'where' => ['t_id' => $idA], 'app' => 'test'],
			['table' => 'egw_test', 'cols' => 't_id,t_title', 'where' => ['t_id' => $idB], 'app' => 'test'],
		], __LINE__, __FILE__);

		$titles = array_column(iterator_to_array($rs), 't_title');
		sort($titles);
		$this->assertSame([$prefix.'-a', $prefix.'-b'], $titles);
	}

	/**
	 * Behavior: identical rows returned by two different selects in a multi-select union must be
	 * de-duplicated by real UNION semantics (as opposed to UNION ALL, which would keep duplicates).
	 *
	 * Pass criteria: the SAME row matched by two overlapping $where filters (both matching the same
	 * single t_id) appears exactly once in the combined result.
	 */
	public function testUnionMultiSelectDeduplicatesIdenticalRows()
	{
		$prefix = 'UnionTransactionTest-dedup-'.uniqid();
		$id = $this->insertRow(['t_title' => $prefix]);

		$rs = self::$db->union([
			['table' => 'egw_test', 'cols' => 't_id,t_title', 'where' => ['t_id' => $id], 'app' => 'test'],
			['table' => 'egw_test', 'cols' => 't_id,t_title', 'where' => ['t_title' => $prefix], 'app' => 'test'],
		], __LINE__, __FILE__);

		$rows = iterator_to_array($rs);
		$this->assertCount(1, $rows, 'UNION (not UNION ALL) must de-duplicate the identical row matched by both selects');
	}

	// -------------------------------------------------------------------
	// union() - $order_by param
	// -------------------------------------------------------------------

	/**
	 * Behavior: $order_by gets appended as "\nORDER BY <value>" UNLESS the given string already
	 * contains "ORDER BY" (case-insensitive, via stristr), in which case it's appended AS-IS with no
	 * extra "ORDER BY" prefix - avoiding a doubled "ORDER BY ORDER BY ...".
	 *
	 * Pass criteria: passing a bare column name sorts ascending by that column; passing an explicit
	 * "ORDER BY <col> DESC" string is honored as-is (descending), proving the no-double-prefix logic
	 * works and doesn't corrupt the DESC direction.
	 */
	public function testUnionOrderByPlainColumnName()
	{
		$prefix = 'UnionTransactionTest-order-'.uniqid();
		$this->insertRow(['t_title' => $prefix.'-b', 't_modifier' => 2]);
		$this->insertRow(['t_title' => $prefix.'-a', 't_modifier' => 1]);

		$rs = self::$db->union([
			['table' => 'egw_test', 'cols' => 't_title', 'where' => ['t_title' => [$prefix.'-a', $prefix.'-b']], 'app' => 'test'],
		], __LINE__, __FILE__, 't_title');

		$titles = array_column(iterator_to_array($rs), 't_title');
		$this->assertSame([$prefix.'-a', $prefix.'-b'], $titles, 'plain column name must sort ascending');
	}

	public function testUnionOrderByExplicitOrderByStringNotDoubled()
	{
		$prefix = 'UnionTransactionTest-orderdesc-'.uniqid();
		$this->insertRow(['t_title' => $prefix.'-a']);
		$this->insertRow(['t_title' => $prefix.'-b']);

		$rs = self::$db->union([
			['table' => 'egw_test', 'cols' => 't_title', 'where' => ['t_title' => [$prefix.'-a', $prefix.'-b']], 'app' => 'test'],
		], __LINE__, __FILE__, 'ORDER BY t_title DESC');

		$titles = array_column(iterator_to_array($rs), 't_title');
		$this->assertSame([$prefix.'-b', $prefix.'-a'], $titles,
			'an already-prefixed "ORDER BY ... DESC" string must be honored as-is (descending), not doubled/corrupted');
	}

	// -------------------------------------------------------------------
	// transaction_begin()/transaction_commit()/transaction_abort()
	// -------------------------------------------------------------------

	/**
	 * Behavior: transaction_begin()+transaction_commit() persists a write made inside the
	 * transaction.
	 *
	 * Pass criteria: after commit, a completely fresh Api\Db connection (not just the same session)
	 * can read the row - proving it was genuinely committed to the database, not merely visible
	 * within the same connection's transaction snapshot.
	 */
	public function testTransactionCommitPersists()
	{
		$title = 'UnionTransactionTest-commit-'.uniqid();

		self::$db->transaction_begin();
		self::$db->insert('egw_test', ['t_title' => $title, 't_modifier' => 1], null, __LINE__, __FILE__, 'test');
		$id = self::$db->get_last_insert_id('egw_test', 't_id');
		$this->created_ids[] = $id;
		self::$db->transaction_commit();

		$fresh = new Api\Db(self::$db_data);
		$fresh->connect();
		$row = $fresh->select('egw_test', 't_title', ['t_id' => $id], __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertNotFalse($row, 'row must be visible via a completely separate connection after commit');
		$this->assertSame($title, $row['t_title']);
	}

	/**
	 * Behavior, per this project's own established ADOdb semantics (see the Customfields::getSerial()
	 * and Db\Schema::RefreshTable() bug fixes documented in this project): transaction_abort() alone
	 * (ADOdb FailTrans()) only FLAGS the transaction to fail - it does NOT itself issue a ROLLBACK.
	 * Only transaction_commit() (ADOdb CompleteTrans()) actually finalizes the transaction, seeing the
	 * fail-flag and issuing ROLLBACK instead of COMMIT. So the CORRECT abort idiom is
	 * transaction_abort() followed by transaction_commit() (not skipping the trailing commit call).
	 *
	 * Pass criteria: a row inserted between transaction_begin() and
	 * transaction_abort()+transaction_commit() does NOT exist afterward.
	 */
	public function testTransactionAbortThenCommitRollsBack()
	{
		$title = 'UnionTransactionTest-abort-'.uniqid();

		self::$db->transaction_begin();
		self::$db->insert('egw_test', ['t_title' => $title, 't_modifier' => 1], null, __LINE__, __FILE__, 'test');
		self::$db->transaction_abort();
		self::$db->transaction_commit();	// finalizes the abort - see doc-comment above

		$row = self::$db->select('egw_test', 't_title', ['t_title' => $title], __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertFalse($row, 'row inserted inside an aborted transaction must not exist after transaction_abort()+transaction_commit()');
	}

	// -------------------------------------------------------------------
	// row_lock()/commit_lock()/rollback_lock()
	// -------------------------------------------------------------------

	/**
	 * Behavior: row_lock() starts a transaction (if none active) and does a real
	 * "SELECT ... FOR UPDATE" against the given table/where; commit_lock() commits that transaction.
	 * Scope note: genuine cross-connection lock CONTENTION (a second connection blocking on the same
	 * locked row) is deliberately not exercised here - too complex/risky against a shared dev DB
	 * (see doc/ai/projects/db-test-coverage.md's Phase 4 scope note). This tests the simpler
	 * lock -> write -> commit -> verify flow instead.
	 *
	 * Pass criteria: a row updated while locked is visible with its new value after commit_lock().
	 */
	public function testRowLockThenCommitLockPersistsWrite()
	{
		$id = $this->insertRow(['t_modifier' => 1]);

		$this->assertTrue((bool)self::$db->row_lock('egw_test', "t_id=$id"), 'row_lock() must succeed');
		self::$db->update('egw_test', ['t_modifier' => 42], ['t_id' => $id], __LINE__, __FILE__, 'test');
		self::$db->commit_lock('egw_test');

		$row = self::$db->select('egw_test', 't_modifier', ['t_id' => $id], __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertEquals(42, $row['t_modifier']);
	}

	/**
	 * Behavior: rollback_lock() rolls back the transaction row_lock() started, undoing any write
	 * made under the lock.
	 *
	 * Pass criteria: a row updated while locked is back to its ORIGINAL value after rollback_lock().
	 */
	public function testRowLockThenRollbackLockUndoesWrite()
	{
		$id = $this->insertRow(['t_modifier' => 1]);

		self::$db->row_lock('egw_test', "t_id=$id");
		self::$db->update('egw_test', ['t_modifier' => 42], ['t_id' => $id], __LINE__, __FILE__, 'test');
		self::$db->rollback_lock('egw_test');

		$row = self::$db->select('egw_test', 't_modifier', ['t_id' => $id], __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertEquals(1, $row['t_modifier'], 'rollback_lock() must undo the write made under the lock');
	}

	// -------------------------------------------------------------------
	// strip_array_keys()
	// -------------------------------------------------------------------

	/**
	 * Behavior: strip_array_keys($arr, $strip) removes a given substring (typically a prefix) from
	 * every key of $arr, rebuilding the array with the stripped keys but the SAME values (via
	 * array_walk() + array_combine()).
	 *
	 * Pass criteria: a common column-name prefix is removed from every key; values are unchanged and
	 * stay associated with their (now-shorter) key.
	 */
	public function testStripArrayKeysRemovesPrefix()
	{
		$result = Api\Db::strip_array_keys(['t_title' => 'Foo', 't_desc' => 'Bar'], 't_');

		$this->assertSame(['title' => 'Foo', 'desc' => 'Bar'], $result);
	}

	/**
	 * Behavior: a $strip value that doesn't occur in a given key leaves that key unchanged
	 * (str_replace() with no match is a no-op).
	 */
	public function testStripArrayKeysNoMatchLeavesKeyUnchanged()
	{
		$result = Api\Db::strip_array_keys(['other_col' => 'X'], 't_');

		$this->assertSame(['other_col' => 'X'], $result);
	}

	/**
	 * Behavior: $strip can also be an array of substrings (str_replace() accepts an array needle),
	 * each stripped from every key.
	 */
	public function testStripArrayKeysAcceptsArrayOfSubstrings()
	{
		$result = Api\Db::strip_array_keys(['prefix_col_suffix' => 'X'], ['prefix_', '_suffix']);

		$this->assertSame(['col' => 'X'], $result);
	}

	/**
	 * Behavior: an empty input array is returned unchanged (array_walk() on an empty array still
	 * succeeds/returns true, so the array_combine() branch runs and correctly reproduces the empty
	 * array).
	 */
	public function testStripArrayKeysEmptyArray()
	{
		$this->assertSame([], Api\Db::strip_array_keys([], 't_'));
	}
}
