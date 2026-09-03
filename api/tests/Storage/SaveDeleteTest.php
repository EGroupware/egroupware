<?php
/**
 * EGroupware Api: tests for Api\Storage\Base's write path (save/update/delete/not_unique)
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * Covers Api\Storage\Base::save()/update()/delete()/not_unique() against the egw_test fixture
 * table. See doc/ai/projects/storage-test-coverage.md for the full behavior map this is drawn
 * from. Base::search()/parse_search()/search2criteria()/query_list() are covered separately in
 * SearchTest.php - out of scope here.
 *
 * Bootstrap mirrors BaseTest.php's pattern exactly (plain TestCase, no session/login - domain
 * resolved via Api\Session::search_instance() since $GLOBALS['EGW_DOMAIN'] is the literal string
 * "default" which is never a real domain key).
 *
 * This is a shared dev database - every row this file inserts is tagged with a distinctive
 * 'SaveDeleteTest-' prefix in t_title/t_uniq and removed in tearDown(), so a leaked row is both
 * identifiable and harmless to other concurrent test runs.
 */
class SaveDeleteTest extends TestCase
{
	/** @var Api\Db */
	private static $db;

	/** @var Api\Storage\Base */
	private $storage;

	/** @var int[] ids inserted by the current test, removed in tearDown() */
	private $created_ids = [];

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

		// see BaseTest.php for why this resolution is necessary instead of a direct
		// $GLOBALS['egw_domain'][$GLOBALS['EGW_DOMAIN']] index
		$default_domain = null;
		$domain = Api\Session::search_instance(null, $GLOBALS['EGW_DOMAIN'], $default_domain,
			array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''), $GLOBALS['egw_domain']);

		$GLOBALS['egw'] = new stdClass();
		$GLOBALS['egw']->db = self::$db = new Api\Db($GLOBALS['egw_domain'][$domain]);
		self::$db->connect();
	}

	protected function setUp() : void
	{
		$this->storage = new Api\Storage\Base('test', 'egw_test', self::$db);
	}

	protected function assertPreConditions() : void
	{
		$tables = self::$db->table_names(true);
		if(!in_array('egw_test', $tables))
		{
			$this->markTestSkipped('No test app installed');
		}
	}

	protected function tearDown() : void
	{
		foreach($this->created_ids as $id)
		{
			self::$db->delete('egw_test', array('t_id' => $id), __LINE__, __FILE__, 'test');
		}
		$this->created_ids = [];
	}

	/**
	 * Insert a fixture row directly via the storage object and remember its id for cleanup.
	 *
	 * @param array $data merged over defaults
	 * @return array the full $this->storage->data after save()
	 */
	private function insertRow(array $data=[])
	{
		$this->storage->data = array_merge(array(
			't_title' => 'SaveDeleteTest-'.__FUNCTION__.'-'.uniqid(),
			't_desc' => 'fixture row',
			't_modifier' => 1,
		), $data);
		$errno = $this->storage->save();
		$this->assertSame(0, $errno, 'save() failed inserting fixture row: db errno='.$errno);
		$this->created_ids[] = $this->storage->data['t_id'];
		return $this->storage->data;
	}

	/**
	 * Behavior: the insert-with-autoinc branch truncates a varchar value that exceeds the
	 * column's precision, instead of letting the DB reject/truncate it uncontrolled.
	 * Pass criteria: t_title (varchar 80) gets stored truncated to exactly 80 chars.
	 */
	public function testSaveTruncatesOverlongVarchar()
	{
		$long_title = 'SaveDeleteTest-truncate-'.str_repeat('x', 100);
		$this->assertGreaterThan(80, strlen($long_title), 'Test precondition: title must exceed t_title\'s 80-char precision');

		$data = $this->insertRow(array('t_title' => $long_title));

		$row = self::$db->select('egw_test', 't_title', array('t_id' => $data['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame(80, strlen($row['t_title']), 'Stored t_title should be truncated to the column precision (80)');
		$this->assertSame(substr($long_title, 0, 80), $row['t_title']);
	}

	/**
	 * Behavior: save() with an existing (non-empty) primary key goes through the
	 * update-or-insert branch (not the fresh-insert branch), and a partial $this->data (only the
	 * pk + one changed column) updates just that column, leaving the rest of the row untouched.
	 * Pass criteria: only t_desc changes; t_title/t_modifier keep their original DB values.
	 */
	public function testSaveWithExistingIdUpdatesRow()
	{
		$original = $this->insertRow(array('t_desc' => 'original desc', 't_modifier' => 42));

		$this->storage->data = array('t_id' => $original['t_id'], 't_desc' => 'updated desc');
		$errno = $this->storage->save();
		$this->assertSame(0, $errno);

		$row = self::$db->select('egw_test', '*', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame('updated desc', $row['t_desc']);
		$this->assertEquals($original['t_title'], $row['t_title'], 't_title must be unchanged by a partial update');
		$this->assertEquals(42, $row['t_modifier'], 't_modifier must be unchanged by a partial update');
	}

	/**
	 * Behavior: save($keys=null, $extra_where) on an existing row performs an
	 * optimistic-locking-style conditional update - if $extra_where's condition does NOT match
	 * the current DB row (e.g. a caller's stale "version" column value), zero rows are affected
	 * and save() returns `true` WITHOUT writing anything or falling back to an insert.
	 * Pass criteria: return === true (not 0/errno), and the row's t_desc is unchanged in the DB.
	 */
	public function testSaveWithMismatchedExtraWhereReturnsTrueWithoutWriting()
	{
		$original = $this->insertRow(array('t_desc' => 'before', 't_modifier' => 100));

		$this->storage->data = array('t_id' => $original['t_id'], 't_desc' => 'should not be written');
		// t_modifier used here as a stand-in "version"/etag column - real value is 100, we claim 999
		$result = $this->storage->save(null, array('t_modifier' => 999));

		$this->assertTrue($result, 'save() must return true (not an errno) when $extra_where does not match any row');

		$row = self::$db->select('egw_test', 't_desc', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame('before', $row['t_desc'], 'Row must be unmodified when $extra_where did not match');
	}

	/**
	 * Behavior: same as above, but $extra_where's condition DOES match the current DB row - the
	 * conditional update proceeds normally.
	 * Pass criteria: save() returns 0 (success), and the row is actually updated.
	 */
	public function testSaveWithMatchingExtraWhereUpdates()
	{
		$original = $this->insertRow(array('t_desc' => 'before', 't_modifier' => 100));

		$this->storage->data = array('t_id' => $original['t_id'], 't_desc' => 'after');
		$result = $this->storage->save(null, array('t_modifier' => 100));

		$this->assertSame(0, $result, 'save() should report success (0) when $extra_where matches');

		$row = self::$db->select('egw_test', 't_desc', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame('after', $row['t_desc']);
	}

	/**
	 * Behavior (the "grude hack" documented inline in Base::save(), lines ~613-625): if
	 * $this->data[Base::USER_TIMEZONE_READ] (stamped by a prior read()) differs from the
	 * CURRENT Api\DateTime::$user_timezone, save() silently reloads the OLD timezone into both
	 * Api\DateTime's static state and $GLOBALS['egw_info']['user']['preferences']['common']['tz']
	 * before proceeding - so that timestamp columns get written using the timezone the data was
	 * originally read under, not whatever the user has since switched to.
	 *
	 * Setup: capture the current user timezone, then stamp $this->data with a deliberately
	 * DIFFERENT timezone name via the USER_TIMEZONE_READ key and save() a fresh row.
	 * Pass criteria: after save(), Api\DateTime::$user_timezone has been switched to the stamped
	 * (old/stale) timezone name - restored in this test's own cleanup so it doesn't leak into
	 * other tests sharing this PHPUnit process.
	 */
	public function testSaveReloadsStaleUserTimezoneFromReadStamp()
	{
		$original_tz = Api\DateTime::$user_timezone->getName();
		$stale_tz = $original_tz === 'Europe/Berlin' ? 'Pacific/Auckland' : 'Europe/Berlin';

		try
		{
			$this->storage->data = array(
				't_title' => 'SaveDeleteTest-tzhack-'.uniqid(),
				Api\Storage\Base::USER_TIMEZONE_READ => $stale_tz,
			);
			$errno = $this->storage->save();
			$this->assertSame(0, $errno);
			$this->created_ids[] = $this->storage->data['t_id'];

			$this->assertSame($stale_tz, Api\DateTime::$user_timezone->getName(),
				'save() must reload the stale timezone from the USER_TIMEZONE_READ stamp when it differs from the current one');
			$this->assertSame($stale_tz, $GLOBALS['egw_info']['user']['preferences']['common']['tz'] ?? null);
		}
		finally
		{
			Api\DateTime::setUserPrefs($original_tz);
		}
	}

	/**
	 * Behavior: update($fields, $merge=true) merges $fields into $this->data (after the DB
	 * write), so $this->data ends up with the union of its previous state and the new fields.
	 * Pass criteria: only t_desc changes in the DB; $this->storage->data retains t_title from
	 * before the update AND reflects the new t_desc.
	 */
	public function testUpdatePartialFieldMergesIntoData()
	{
		$original = $this->insertRow(array('t_title' => 'keep-me', 't_desc' => 'old desc'));

		$this->storage->data = $original;
		$errno = $this->storage->update(array('t_desc' => 'new desc'));
		$this->assertSame(0, $errno);

		$this->assertSame('new desc', $this->storage->data['t_desc']);
		$this->assertSame('keep-me', $this->storage->data['t_title'], 'merge=true must keep unrelated fields already in $this->data');

		$row = self::$db->select('egw_test', '*', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame('new desc', $row['t_desc']);
		$this->assertSame('keep-me', $row['t_title']);
	}

	/**
	 * Behavior: update($fields, false) does NOT call data_merge() at all - despite the method's
	 * own docblock claiming "$this->data will be just $fields" for merge=false, the actual code
	 * (`if ($merge) $this->data_merge($_fields);`) leaves $this->data completely untouched when
	 * $merge is false. This test documents the ACTUAL behavior (a real doc/code mismatch found
	 * while mapping this class - see doc/ai/projects/storage-test-coverage.md) rather than the
	 * documented one, so a future fix to either side has a regression test either way.
	 * Pass criteria: the DB row IS updated (the write itself doesn't depend on $merge), but
	 * $this->storage->data is unchanged from before the call.
	 */
	public function testUpdateWithMergeFalseDoesNotTouchInMemoryData()
	{
		$original = $this->insertRow(array('t_title' => 'pre-update', 't_desc' => 'old desc'));

		$this->storage->data = $original;
		$data_before_call = $this->storage->data;

		$errno = $this->storage->update(array('t_id' => $original['t_id'], 't_desc' => 'merge-false desc'), false);
		$this->assertSame(0, $errno);

		$this->assertSame($data_before_call, $this->storage->data,
			'merge=false must leave $this->data exactly as it was - update() never resets it to $fields despite the docblock');

		$row = self::$db->select('egw_test', 't_desc', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame('merge-false desc', $row['t_desc'], 'The DB write itself must still happen regardless of $merge');
	}

	/**
	 * Behavior: an integer-keyed entry in $fields is passed through as a raw SQL fragment
	 * (eg. "col=col+1"), not treated as column data.
	 * Pass criteria: t_modifier is incremented by 1 in the DB via the raw fragment, without
	 * passing a literal new value.
	 */
	public function testUpdateWithRawSqlFragment()
	{
		$original = $this->insertRow(array('t_modifier' => 10));

		$this->storage->data = array('t_id' => $original['t_id']);
		$errno = $this->storage->update(array(0 => 't_modifier=t_modifier+1'), false);
		$this->assertSame(0, $errno);

		$row = self::$db->select('egw_test', 't_modifier', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertEquals(11, $row['t_modifier']);
	}

	/**
	 * Behavior: update() with no actual column data to write (only the pk) returns 0 ("nothing
	 * to update") without issuing a write.
	 * Pass criteria: return === 0, and the row is byte-for-byte unchanged.
	 */
	public function testUpdateNoOpWhenNoFieldsGiven()
	{
		$original = $this->insertRow(array('t_desc' => 'untouched'));

		$this->storage->data = array('t_id' => $original['t_id']);
		$result = $this->storage->update(array('t_id' => $original['t_id']), false);
		$this->assertSame(0, $result);

		$row = self::$db->select('egw_test', '*', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertSame('untouched', $row['t_desc']);
	}

	/**
	 * Behavior: delete(int $id) with a scalar autoinc id deletes that single row.
	 */
	public function testDeleteByScalarAutoincId()
	{
		$original = $this->insertRow();

		$affected = $this->storage->delete($original['t_id']);
		$this->assertEquals(1, $affected);

		$row = self::$db->select('egw_test', 't_id', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertFalse($row, 'Row must be gone after delete()');

		// already deleted - don't try again in tearDown()
		$this->created_ids = array_diff($this->created_ids, array($original['t_id']));
	}

	/**
	 * Behavior: delete(array $keys) deletes by an explicit column=>value criteria array, not
	 * $this->data.
	 */
	public function testDeleteByExplicitKeysArray()
	{
		$marker = 'SaveDeleteTest-explicit-keys-'.uniqid();
		$original = $this->insertRow(array('t_uniq' => $marker));

		// $this->storage->data intentionally left pointing at something else, to prove delete()
		// uses the explicit $keys array, not internal state
		$this->storage->data = array('t_id' => -1);

		$affected = $this->storage->delete(array('t_uniq' => $marker));
		$this->assertEquals(1, $affected);

		$row = self::$db->select('egw_test', 't_id', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertFalse($row);

		$this->created_ids = array_diff($this->created_ids, array($original['t_id']));
	}

	/**
	 * Behavior: delete() with no arguments falls back to $this->data + db_key_cols (deletes
	 * "the currently loaded row").
	 */
	public function testDeleteFallsBackToInternalData()
	{
		$original = $this->insertRow();

		$this->storage->data = array('t_id' => $original['t_id']);
		$affected = $this->storage->delete();
		$this->assertEquals(1, $affected);

		$row = self::$db->select('egw_test', 't_id', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertFalse($row);

		$this->created_ids = array_diff($this->created_ids, array($original['t_id']));
	}

	/**
	 * Behavior: delete($keys, $only_return_query=true) builds and returns the query array
	 * without ever executing it against the DB.
	 */
	public function testDeleteOnlyReturnQueryDoesNotDelete()
	{
		$original = $this->insertRow();

		$query = $this->storage->delete($original['t_id'], true);

		$this->assertIsArray($query);
		$this->assertSame($original['t_id'], $query['t_id']);

		$row = self::$db->select('egw_test', 't_id', array('t_id' => $original['t_id']), __LINE__, __FILE__, false, '', 'test')->fetch();
		$this->assertIsArray($row, 'Row must still exist - $only_return_query=true must not delete anything');
	}

	/**
	 * Behavior: not_unique() finds an EXISTING different row sharing the same unique-column
	 * (t_uniq) value, returning a non-zero index (1-based position among db_uni_cols) - the
	 * classic "duplicate check before insert" use case: checking whether a brand-new (not yet
	 * inserted) row's proposed t_uniq value collides with something already in the table.
	 */
	public function testNotUniqueDetectsExistingDuplicate()
	{
		$marker = 'SaveDeleteTest-notunique-'.uniqid();
		$this->insertRow(array('t_uniq' => $marker));

		// simulate checking a NOT-yet-inserted row (no t_id of its own) with the same t_uniq
		$conflict = $this->storage->not_unique(array('t_id' => 0, 't_uniq' => $marker));
		$this->assertSame(1, $conflict, 'not_unique() must report a conflict (non-zero) for a colliding t_uniq value belonging to a different row');
	}

	/**
	 * Behavior: not_unique() returns 0 (unique) when checking a row's OWN data against itself -
	 * the only DB row sharing that t_uniq value has the same t_id, so it's not "a different
	 * entry".
	 */
	public function testNotUniqueReturnsZeroForOwnRow()
	{
		$marker = 'SaveDeleteTest-notunique-self-'.uniqid();
		$original = $this->insertRow(array('t_uniq' => $marker));

		$this->storage->data = $original;
		$this->assertSame(0, $this->storage->not_unique());
	}

	/**
	 * Behavior: not_unique() returns 0 (unique) for a t_uniq value nothing else in the table has.
	 */
	public function testNotUniqueReturnsZeroWhenNoConflict()
	{
		$marker = 'SaveDeleteTest-notunique-none-'.uniqid();
		$conflict = $this->storage->not_unique(array('t_id' => 0, 't_uniq' => $marker));
		$this->assertSame(0, $conflict);
	}
}
