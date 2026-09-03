<?php
/**
 * Tests for Api\Storage\History
 *
 * Part of the Api\Storage test-coverage project - see doc/ai/projects/storage-test-coverage.md
 *
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Storage;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;

/**
 * Behavior under test: Api\Storage\History's add()/delete()/delete_field()/search()/get_rows()/
 * needs_diff() against the shared, always-installed egw_history_log table.
 *
 * Setup strategy: every test scopes itself to history_appname=self::APP ('test') plus a fresh,
 * randomly-generated fake record_id in a dedicated high range (900000000-999999999), so nothing
 * here can ever read/write real app history data on this shared dev DB. Rows created by each test
 * are tracked in $this->record_ids and removed in tearDown() via History::delete().
 *
 * Pass criteria: documented per test method below.
 */
class HistoryTest extends LoggedInTest
{
	const APP = 'test';

	/** @var History */
	protected $history;

	/** @var int[] record_ids created by the current test, cleaned up in tearDown() */
	protected $record_ids = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->history = new History(self::APP, $GLOBALS['egw_info']['user']['account_id']);
	}

	protected function tearDown() : void
	{
		foreach($this->record_ids as $record_id)
		{
			$this->history->delete($record_id);
		}
		$this->record_ids = [];
		parent::tearDown();
	}

	/**
	 * A large, clearly-fake id in a dedicated range, further scoped by history_appname=self::APP,
	 * so it can never collide with / affect real app history data.
	 */
	protected function newRecordId() : int
	{
		$id = random_int(900000000, 999999999);
		$this->record_ids[] = $id;
		return $id;
	}

	// --- add() / delete() / delete_field() ---

	/**
	 * add() must be a no-op (no row written) when $new_value == $old_value (loose comparison).
	 */
	public function testAddNoopOnEqualValues()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'same', 'same');
		$rows = $this->history->search($record_id);
		$this->assertEmpty($rows, 'add() must NOT write a row when new_value == old_value');
	}

	/**
	 * add() with genuinely different values must write exactly one row with the given
	 * status/new_value/old_value.
	 */
	public function testAddWritesOnDifferentValues()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'new', 'old');

		$rows = $this->history->search($record_id);
		$this->assertCount(1, $rows);
		$this->assertEquals('new', $rows[0]['new_value']);
		$this->assertEquals('old', $rows[0]['old_value']);
		$this->assertEquals('E', $rows[0]['status']);
	}

	/**
	 * add()'s equality check is `$new_value != $old_value` (loose). '0' and 0 are loosely equal in
	 * PHP, so this must also be treated as a no-op - confirms current behavior, not necessarily
	 * ideal, but real and worth locking down.
	 */
	public function testAddLooseCompareNumericStringVsInt()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 0, '0');
		$rows = $this->history->search($record_id);
		$this->assertEmpty($rows, "add() uses loose != comparison - '0' and 0 must be treated as equal (no-op)");
	}

	/**
	 * delete($record_id) with a single scalar id removes exactly that record's history.
	 */
	public function testDeleteScalarId()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'v', '');
		$this->assertNotEmpty($this->history->search($record_id), 'precondition: row must exist before delete');

		$deleted = $this->history->delete($record_id);
		$this->assertGreaterThanOrEqual(1, $deleted);
		$this->assertEmpty($this->history->search($record_id));
	}

	/**
	 * delete() also accepts an array of record_ids and removes all of them.
	 */
	public function testDeleteArrayOfIds()
	{
		$id1 = $this->newRecordId();
		$id2 = $this->newRecordId();
		$this->history->add('E', $id1, 'v1', '');
		$this->history->add('E', $id2, 'v2', '');

		$deleted = $this->history->delete([$id1, $id2]);
		$this->assertGreaterThanOrEqual(2, $deleted);
		$this->assertEmpty($this->history->search($id1));
		$this->assertEmpty($this->history->search($id2));
	}

	/**
	 * delete_field() scopes deletion by (record_id, status) - deleting one status must leave other
	 * statuses on the same record_id untouched.
	 */
	public function testDeleteFieldScopesByStatus()
	{
		$record_id = $this->newRecordId();
		$this->history->add('A', $record_id, 'va', '');
		$this->history->add('B', $record_id, 'vb', '');

		$this->history->delete_field($record_id, 'A');

		$rows = $this->history->search($record_id);
		$statuses = array_column($rows, 'status');
		$this->assertNotContains('A', $statuses, 'delete_field() must remove only the given status');
		$this->assertContains('B', $statuses, 'delete_field() must leave other statuses alone');
	}

	/**
	 * delete(null) (falsy, non-array, non-numeric $record_id) intentionally wipes ALL rows for
	 * $this->appname - confirmed against the real code: History::delete()'s $where only gains a
	 * history_record_id condition when is_array($record_id) || is_numeric($record_id), so null (or
	 * 0, or '') skips that condition entirely and the delete() is scoped by appname alone. This is
	 * a real, sharp-edged behavior worth locking down explicitly.
	 *
	 * We use a dedicated History instance scoped to a random, unique fake appname (NOT self::APP)
	 * so a bug in this test can never wipe real 'test'-app history data used by other tests in this
	 * suite or by a concurrent session on this shared dev DB.
	 */
	public function testDeleteNullWipesAllRowsForApp()
	{
		$appname = 'histtest' . random_int(100000, 999999);
		$scoped_history = new History($appname, $GLOBALS['egw_info']['user']['account_id']);

		$id1 = random_int(900000000, 999999999);
		$id2 = random_int(900000000, 999999999);
		$scoped_history->add('E', $id1, 'v1', '');
		$scoped_history->add('E', $id2, 'v2', '');
		$this->assertNotEmpty($scoped_history->search($id1), 'precondition');
		$this->assertNotEmpty($scoped_history->search($id2), 'precondition');

		$scoped_history->delete(null);

		$this->assertEmpty($scoped_history->search($id1), 'delete(null) must wipe ALL rows for the appname, incl. id1');
		$this->assertEmpty($scoped_history->search($id2), 'delete(null) must wipe ALL rows for the appname, incl. id2');
	}

	// --- search() ---

	/**
	 * search() explicitly refuses to run without a history_record_id filter, to avoid a full-table
	 * scan of a shared, unbounded log table - must return [] rather than executing the query.
	 */
	public function testSearchWithoutRecordIdFilterReturnsEmptyArray()
	{
		$rows = $this->history->search(['history_status' => 'E']);
		$this->assertSame([], $rows, 'search() without a history_record_id filter must refuse and return []');
	}

	/**
	 * search($int) is shorthand for search(['history_record_id' => $int]).
	 */
	public function testSearchIntFilterShortcut()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'v', '');

		$rows = $this->history->search($record_id);
		$this->assertCount(1, $rows);
		$this->assertEquals($record_id, $rows[0]['record_id']);
	}

	/**
	 * A filter key that does NOT already start with 'history_' must be auto-prefixed, so callers
	 * can pass eg. ['record_id' => ...] instead of ['history_record_id' => ...].
	 */
	public function testSearchAutoPrefixesNonHistoryColumns()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'v', '');

		$rows = $this->history->search(['record_id' => $record_id]);
		$this->assertCount(1, $rows, "search() must auto-prefix non-'history_'-prefixed filter keys");
	}

	/**
	 * An $order value that fails the strict `^[a-z0-9_]+$` validation (eg. an injection attempt)
	 * must not reach raw SQL - search() falls back to a safe, hardcoded 'ORDER BY history_id DESC'
	 * instead of throwing or executing the malicious fragment.
	 */
	public function testSearchInvalidOrderFallsBackSafely()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'v1', '');
		$this->history->add('E', $record_id, 'v2', 'v1');

		$rows = $this->history->search($record_id, 'history_id; DROP TABLE egw_history_log', 'DESC');
		$this->assertCount(2, $rows, 'query must still execute safely and return both rows');
		$this->assertGreaterThan($rows[1]['id'], $rows[0]['id'], 'fallback order must still be DESC by history_id');
	}

	/**
	 * Same guard, but for an invalid $sort value (must match /^(asc|desc)?$/i).
	 */
	public function testSearchInvalidSortFallsBackSafely()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'v', '');

		$rows = $this->history->search($record_id, 'history_id', 'DESC; DROP TABLE egw_history_log');
		$this->assertCount(1, $rows, 'query must still execute safely despite the invalid $sort value');
	}

	/**
	 * A DateTimeInterface value passed to add() gets JSON-encoded on write (History::encode()) and
	 * must come back out of search() as a real Api\DateTime object again (History::decode()), not
	 * as a raw JSON string.
	 */
	public function testSearchDateTimeRoundTrip()
	{
		$record_id = $this->newRecordId();
		$dt = new Api\DateTime('2024-06-15 10:30:00', new \DateTimeZone('UTC'));
		$this->history->add('E', $record_id, $dt, '');

		$rows = $this->history->search($record_id);
		$this->assertCount(1, $rows);
		$this->assertInstanceOf(Api\DateTime::class, $rows[0]['new_value'],
			'a DateTimeInterface value must round-trip back into an Api\DateTime object, not a JSON string');
		$this->assertEquals($dt->format('c'), $rows[0]['new_value']->format('c'));
	}

	// --- needs_diff() ---

	public function testNeedsDiffShortTextNoSpecialName()
	{
		$this->assertFalse(History::needs_diff('some_field', 'short text'));
	}

	public function testNeedsDiffNoteFieldAlwaysNeedsDiff()
	{
		$this->assertTrue(History::needs_diff('note', 'x'), "field name 'note' (Addressbook) always needs a diff");
	}

	public function testNeedsDiffDescriptionSubstringNeedsDiff()
	{
		$this->assertTrue(History::needs_diff('cal_description', 'x'),
			"any field name containing 'description' always needs a diff");
	}

	public function testNeedsDiffDeFieldNeedsDiff()
	{
		$this->assertTrue(History::needs_diff('De', 'x'), "field name 'De' (Tracker/InfoLog) always needs a diff");
	}

	public function testNeedsDiffLongValueNeedsDiff()
	{
		$this->assertTrue(History::needs_diff('anything', str_repeat('x', 201)), 'values over 200 chars need a diff');
	}

	public function testNeedsDiffMultilineValueNeedsDiff()
	{
		$this->assertTrue(History::needs_diff('anything', "line1\nline2"), 'multi-line values need a diff');
	}

	public function testNeedsDiffArrayNeverNeedsDiff()
	{
		$this->assertFalse(History::needs_diff('note', ['a', 'b']), 'array values are never diffed, even for note');
	}

	/**
	 * Documents an actual quirk in needs_diff()'s PGP-armored-value guard:
	 *
	 *   strpos($value, static::BEGIN_PGP) == 0 && strpos($value, static::END_PGP) !== FALSE
	 *
	 * The first half uses LOOSE comparison (`==`), and PHP's strpos() returns the bool `false`
	 * (not an int) when the needle isn't found at all. `false == 0` is TRUE in PHP, so that half is
	 * satisfied both when BEGIN_PGP is at position 0 AND when BEGIN_PGP is absent entirely - it is
	 * NOT equivalent to "value starts with BEGIN_PGP" (which would need strict `=== 0`).
	 *
	 * Practical effect: any long/multi-line 'note'/'*description*'/'De' value that happens to also
	 * contain the literal END_PGP marker string anywhere gets treated as "no diff needed", even if
	 * BEGIN_PGP never appears in it at all (i.e. it isn't a PGP-armored value). This looks like an
	 * off-by-comparison-operator bug (probably meant `=== 0`), not intentional behavior - flagging
	 * for confirmation rather than silently fixing it.
	 */
	public function testNeedsDiffPgpLooseComparisonQuirk()
	{
		$real_pgp = History::BEGIN_PGP . "\n...\n" . History::END_PGP;
		$this->assertFalse(History::needs_diff('note', $real_pgp),
			'a real PGP-armored value (BEGIN_PGP at position 0, END_PGP present) must skip the diff');

		// BEGIN_PGP entirely ABSENT, but END_PGP present in a long value: due to the loose
		// `strpos(...) == 0` comparison this STILL skips the diff, even though it is not a
		// PGP-armored value at all - see docblock above.
		$not_really_pgp = str_repeat('x', 250) . History::END_PGP;
		$this->assertStringNotContainsString(History::BEGIN_PGP, $not_really_pgp, 'sanity check on the fixture value');
		$this->assertFalse(History::needs_diff('note', $not_really_pgp),
			'documents current (surprising) behavior: END_PGP alone, without BEGIN_PGP, still suppresses the '.
			'diff because strpos()===false loose-compares equal to 0');
	}

	// --- get_rows() ---

	/**
	 * get_rows() (the Nextmatch-style static entry point) must return the row we just added, with
	 * total >= 1 and the row's new_value/old_value intact.
	 */
	public function testGetRowsBasic()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'new value', 'old value');

		$query = array(
			'appname' => self::APP,
			'record_id' => $record_id,
			'colfilter' => array(),
			'start' => 0,
			'num_rows' => 10,
			'order' => 'history_id',
			'sort' => 'DESC',
		);
		$rows = array();
		$total = History::get_rows($query, $rows);

		$this->assertGreaterThanOrEqual(1, $total);
		$this->assertNotEmpty($rows);

		$found = false;
		foreach($rows as $row)
		{
			if($row['record_id'] == $record_id)
			{
				$found = true;
				$this->assertEquals('new value', $row['new_value']);
				$this->assertEquals('old value', $row['old_value']);
			}
		}
		$this->assertTrue($found, 'get_rows() must include the row we just added');
	}

	/**
	 * get_rows() filters out '#'-prefixed history_status entries (private/no-longer-defined custom
	 * fields) for apps other than calendar/infolog/addressbook (which get a '##'-prefixed
	 * exception for vCard/iCal X-attributes) - here self::APP ('test') gets neither exception, and
	 * the '#some_cf' status is not a currently-defined custom field (Customfields::get('test') is
	 * empty), so it must be excluded from the results entirely.
	 */
	public function testGetRowsFiltersPrivateCustomFields()
	{
		$record_id = $this->newRecordId();
		$this->history->add('#some_cf', $record_id, 'secret', '');
		$this->history->add('E', $record_id, 'public value', '');

		$query = array(
			'appname' => self::APP,
			'record_id' => $record_id,
			'colfilter' => array(),
			'start' => 0,
			'num_rows' => 10,
			'order' => 'history_id',
			'sort' => 'DESC',
		);
		$rows = array();
		History::get_rows($query, $rows);

		$statuses = array_column($rows, 'status');
		$this->assertNotContains('#some_cf', $statuses,
			"get_rows() must exclude '#'-prefixed (private/undefined custom field) history entries for app '".self::APP."'");
		$this->assertContains('E', $statuses, 'the plain, non-CF entry must still be present');
	}
}
