<?php
/**
 * EGroupware Api: tests for Api\Db::insert()/update()/delete()'s edge cases
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
 * Covers insert()'s $where-driven REPLACE-vs-check-then-update decision tree (MySQL), multi-row
 * bulk insert, delete()'s $limit param, log_updates as an array-of-table-names, and update()'s
 * raw-SQL-fragment integer-key passthrough (the Db-level counterpart of the already-tested
 * Storage\Base::update() feature - api/tests/Storage/SaveDeleteTest.php::testUpdateWithRawSqlFragment).
 *
 * Bootstrap mirrors api/tests/Db/QuoteTest.php exactly: a plain TestCase with a raw Api\Db
 * connection (no login needed), domain resolved via Api\Session::search_instance() since
 * $GLOBALS['EGW_DOMAIN'] is literally "default" (doc/phpunit.xml) which is never a real domain key.
 */
class InsertUpdateDeleteTest extends TestCase
{
	/**
	 * @var Api\Db
	 */
	private static $db;

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
	}

	protected function tearDown() : void
	{
		foreach ($this->created_ids as $id)
		{
			self::$db->delete('egw_test', array('t_id' => $id), __LINE__, __FILE__, 'test');
		}
		$this->created_ids = [];
	}

	private function track(int $id) : int
	{
		$this->created_ids[] = $id;
		return $id;
	}

	private function fetchRow(int $id) : ?array
	{
		$row = self::$db->select('egw_test', '*', array('t_id' => $id), __LINE__, __FILE__, false, '', 'test')->fetch();
		return $row ?: null;
	}

	// -------------------------------------------------------------------
	// insert() - $where-driven REPLACE-vs-check-then-update decision tree (MySQL)
	// -------------------------------------------------------------------

	/**
	 * Behavior: insert($table, $data, $where) with $where matching a real unique-key column
	 * (t_uniq) uses MySQL's REPLACE INTO directly (see insert()'s $table_def['uc'] intersection
	 * check) - when no row with that t_uniq value exists yet, REPLACE behaves exactly like a plain
	 * INSERT.
	 *
	 * Pass criteria: a new row is created with $data's title and $where's t_uniq value.
	 */
	public function testInsertWithWhereMatchingUniqueKeyCreatesRowWhenNotExisting()
	{
		$uniq = 'InsertUpdateDeleteTest-repl-new-'.bin2hex(random_bytes(4));

		self::$db->insert('egw_test', array('t_title' => 'created via REPLACE', 't_modifier' => 1),
			array('t_uniq' => $uniq), __LINE__, __FILE__, 'test');

		$row = self::$db->select('egw_test', '*', array('t_uniq' => $uniq), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertNotEmpty($row, 'REPLACE with a not-yet-existing unique key must create the row');
		$this->track((int)$row['t_id']);
		$this->assertSame('created via REPLACE', $row['t_title']);
	}

	/**
	 * Behavior: insert($table, $data, $where) with $where matching an EXISTING row's unique key
	 * uses REPLACE INTO, which is DELETE+INSERT, not a partial UPDATE - columns not present in the
	 * new $data (here t_desc) get reset to their table default/NULL, they do NOT keep their old
	 * value. This is the common REPLACE-vs-UPSERT confusion point, worth locking down explicitly
	 * since a caller expecting update()-like partial-merge semantics would be surprised.
	 *
	 * Pass criteria: after the 2nd insert() call, t_desc (omitted from the 2nd $data) is no longer
	 * "original description" - it was reset, not preserved. t_title (given in the 2nd $data) has
	 * the new value. The row keeps the SAME t_id only if the table's autoinc happens to reuse it -
	 * REPLACE with a non-PK unique key still generates a NEW autoinc id, so we look the row up by
	 * t_uniq afterward rather than assuming the t_id is unchanged.
	 */
	public function testInsertWithWhereMatchingExistingUniqueKeyReplacesNotUpdates()
	{
		$uniq = 'InsertUpdateDeleteTest-repl-existing-'.bin2hex(random_bytes(4));

		$original_id = self::$db->insert('egw_test',
			array('t_title' => 'original title', 't_desc' => 'original description', 't_modifier' => 1),
			array('t_uniq' => $uniq), __LINE__, __FILE__, 'test') ? self::$db->get_last_insert_id('egw_test', 't_id') : null;
		$this->track((int)$original_id);
		$this->assertNotEmpty($this->fetchRow((int)$original_id), 'precondition: first insert must have created a row');

		self::$db->insert('egw_test', array('t_title' => 'replaced title', 't_modifier' => 2),
			array('t_uniq' => $uniq), __LINE__, __FILE__, 'test');

		$row = self::$db->select('egw_test', '*', array('t_uniq' => $uniq), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertNotEmpty($row, 'exactly one row must still exist for this t_uniq after REPLACE');
		$this->track((int)$row['t_id']);

		$this->assertSame('replaced title', $row['t_title'], 'new $data value must be applied');
		$this->assertTrue(empty($row['t_desc']),
			'REPLACE is DELETE+INSERT: t_desc (omitted from the 2nd insert()) must NOT keep its old '.
			"value - got '{$row['t_desc']}'");
	}

	/**
	 * Behavior: insert($table, $data, $where) with $where NOT matching any unique key/PK falls
	 * through to the generic "SELECT COUNT(*) then insert-or-update" branch. With no matching row,
	 * a normal insert happens (the checked $where value gets merged into $data).
	 */
	public function testInsertWithWhereNotMatchingUniqueKeyInsertsWhenNotExisting()
	{
		$title = 'InsertUpdateDeleteTest-nonuniq-'.bin2hex(random_bytes(4));

		self::$db->insert('egw_test', array('t_desc' => 'from non-unique where'),
			array('t_title' => $title), __LINE__, __FILE__, 'test');

		$row = self::$db->select('egw_test', '*', array('t_title' => $title), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertNotEmpty($row, 'must insert when no row matches the (non-unique) $where');
		$this->track((int)$row['t_id']);
		$this->assertSame('from non-unique where', $row['t_desc']);
	}

	/**
	 * Behavior: same generic branch, but a row DOES already match the non-unique $where - insert()
	 * must delegate to update() (a partial merge, unlike REPLACE) rather than creating a duplicate.
	 *
	 * Pass criteria: still exactly one row for that title, its t_desc is updated, and columns not
	 * touched by the 2nd call (t_modifier) keep their original value - proving it's a real UPDATE,
	 * not a REPLACE.
	 */
	public function testInsertWithWhereNotMatchingUniqueKeyUpdatesWhenExisting()
	{
		$title = 'InsertUpdateDeleteTest-nonuniq-upd-'.bin2hex(random_bytes(4));

		self::$db->insert('egw_test', array('t_desc' => 'first', 't_modifier' => 42),
			array('t_title' => $title), __LINE__, __FILE__, 'test');
		$first = self::$db->select('egw_test', '*', array('t_title' => $title), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->track((int)$first['t_id']);

		self::$db->insert('egw_test', array('t_desc' => 'second'), array('t_title' => $title), __LINE__, __FILE__, 'test');

		$rows = iterator_to_array(self::$db->select('egw_test', '*', array('t_title' => $title), __LINE__, __FILE__, false, '', 'test'));
		$this->assertCount(1, $rows, 'must still be exactly one row - the 2nd call must UPDATE, not duplicate');
		$this->assertSame((int)$first['t_id'], (int)$rows[0]['t_id'], 'same row, same t_id');
		$this->assertSame('second', $rows[0]['t_desc'], 'the new value must be applied');
		$this->assertEquals(42, $rows[0]['t_modifier'],
			'a column NOT given in the 2nd insert() ($data only had t_desc) must keep its original '.
			'value - proving this is a real UPDATE (partial merge), unlike REPLACE');
	}

	// -------------------------------------------------------------------
	// insert() - multi-row bulk insert
	// -------------------------------------------------------------------

	/**
	 * Behavior: insert($table, $data, ...) with $data as an array-of-arrays (numeric-indexed,
	 * each inner array a full row) builds one multi-row "INSERT INTO ... VALUES (...),(...),..."
	 * statement instead of looping.
	 */
	public function testInsertMultipleRowsInOneCall()
	{
		$prefix = 'InsertUpdateDeleteTest-bulk-'.bin2hex(random_bytes(4)).'-';

		self::$db->insert('egw_test', array(
			array('t_title' => $prefix.'1', 't_modifier' => 1),
			array('t_title' => $prefix.'2', 't_modifier' => 2),
			array('t_title' => $prefix.'3', 't_modifier' => 3),
		), false, __LINE__, __FILE__, 'test');

		$rows = iterator_to_array(self::$db->select('egw_test', '*', "t_title LIKE ".self::$db->quote($prefix.'%'),
			__LINE__, __FILE__, false, 'ORDER BY t_title', 'test'));
		$this->assertCount(3, $rows, 'all 3 rows from the bulk insert must have been created');
		foreach ($rows as $row)
		{
			$this->track((int)$row['t_id']);
		}
		$this->assertSame($prefix.'1', $rows[0]['t_title']);
		$this->assertEquals(2, $rows[1]['t_modifier']);
		$this->assertSame($prefix.'3', $rows[2]['t_title']);
	}

	// -------------------------------------------------------------------
	// delete() - $limit param
	// -------------------------------------------------------------------

	/**
	 * Behavior: delete($table, $where, ..., $limit) appends a MySQL-specific "LIMIT N" to the
	 * DELETE statement, capping how many matching rows actually get removed.
	 *
	 * Pass criteria: 3 rows match $where, delete(..., $limit=1) removes exactly 1, leaving 2.
	 */
	public function testDeleteWithLimitRemovesOnlyThatManyRows()
	{
		$prefix = 'InsertUpdateDeleteTest-limit-'.bin2hex(random_bytes(4)).'-';
		for ($i = 1; $i <= 3; $i++)
		{
			self::$db->insert('egw_test', array('t_title' => $prefix.$i, 't_modifier' => 1), false, __LINE__, __FILE__, 'test');
			$row = self::$db->select('egw_test', 't_id', array('t_title' => $prefix.$i), __LINE__, __FILE__, false, '', 'test')->fetch();
			$this->track((int)$row['t_id']);
		}

		self::$db->delete('egw_test', "t_title LIKE ".self::$db->quote($prefix.'%'), __LINE__, __FILE__, 'test', false, 1);

		$remaining = iterator_to_array(self::$db->select('egw_test', 't_id', "t_title LIKE ".self::$db->quote($prefix.'%'),
			__LINE__, __FILE__, false, '', 'test'));
		$this->assertCount(2, $remaining, 'LIMIT 1 must have removed exactly 1 of the 3 matching rows');
	}

	// -------------------------------------------------------------------
	// log_updates as an array of table names
	// -------------------------------------------------------------------

	/**
	 * Behavior: $db->log_updates can be an array of table names (opt-in per-table audit logging)
	 * instead of a bare bool - insert()/update()/delete() check `in_array($table, (array)$this->log_updates)`
	 * and, if matched, temporarily force $this->log_updates=true for that one query so query()'s
	 * own logging branch fires, then restore the array afterward.
	 *
	 * Uses a fresh, private Db instance (not $GLOBALS['egw']->db / self::$db) so mutating
	 * log_updates/log_updates_to here cannot affect any other test in this shared connection.
	 *
	 * Pass criteria: a write against the listed table produces a log entry; a write against a
	 * table NOT in the list produces none.
	 */
	public function testLogUpdatesAsArrayOnlyLogsListedTables()
	{
		$log_file = tempnam(sys_get_temp_dir(), 'db_log_updates_test_');
		$this->assertNotFalse($log_file);

		$db = clone self::$db;
		$db->log_updates = ['egw_test'];
		$db->log_updates_to = $log_file;

		$title = 'InsertUpdateDeleteTest-logupd-'.bin2hex(random_bytes(4));
		$db->insert('egw_test', array('t_title' => $title, 't_modifier' => 1), false, __LINE__, __FILE__, 'test');
		$row = self::$db->select('egw_test', 't_id', array('t_title' => $title), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->track((int)$row['t_id']);

		clearstatcache(true, $log_file);
		$logged = file_exists($log_file) ? file_get_contents($log_file) : '';
		@unlink($log_file);

		$this->assertNotEmpty($logged, 'a write against a table listed in log_updates must produce a log entry');
		$this->assertStringContainsString('egw_test', $logged);
	}

	// -------------------------------------------------------------------
	// update() - raw-SQL-fragment integer-key passthrough
	// -------------------------------------------------------------------

	/**
	 * Behavior: update($table, $data, ...) with an integer key in $data treats that entry's VALUE
	 * as a raw SQL fragment, passed through column_data_implode() unquoted (Db.php ~line 1794-1798:
	 * `elseif (is_int($key) && $use_key===True) { ... $values[] = $data; }`). This is the SAME
	 * mechanism Storage\Base::update() already exercises indirectly
	 * (api/tests/Storage/SaveDeleteTest.php::testUpdateWithRawSqlFragment) - this is the direct
	 * Db-level confirmation.
	 *
	 * Pass criteria: t_modifier increments server-side via "t_modifier=t_modifier+1", not via a
	 * PHP-computed literal value.
	 */
	public function testUpdateWithIntegerKeyRawSqlFragment()
	{
		self::$db->insert('egw_test', array('t_title' => 'InsertUpdateDeleteTest-rawfrag-'.bin2hex(random_bytes(4)), 't_modifier' => 10),
			false, __LINE__, __FILE__, 'test');
		$row = self::$db->query('SELECT * FROM egw_test WHERE t_modifier=10 ORDER BY t_id DESC', __LINE__, __FILE__)->fetch();
		$this->track((int)$row['t_id']);

		self::$db->update('egw_test', array(0 => 't_modifier=t_modifier+1'), array('t_id' => $row['t_id']), __LINE__, __FILE__, 'test');

		$updated = $this->fetchRow((int)$row['t_id']);
		$this->assertEquals(11, $updated['t_modifier']);
	}
}
