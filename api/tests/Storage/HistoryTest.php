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

	// --- get_rows() filtering (col_filter / search) ---

	/**
	 * Build the $query array get_rows() expects, with the filter bits under test merged in.
	 */
	protected function rowsQuery($record_id, array $extra = array()) : array
	{
		return array(
			'appname'   => self::APP,
			'record_id' => $record_id,
			'start'     => 0,
			'num_rows'  => 50,
		) + $extra;
	}

	/**
	 * col_filter[status] must restrict results to the named field(s).  This is the filter key
	 * eTemplate actually sends - get_rows() historically only read 'colfilter', which nothing set.
	 */
	public function testGetRowsColFilterStatus()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');
		$this->history->add('C', $record_id, 'created', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('status' => 'E'))), $rows);

		$this->assertNotEmpty($rows, 'filtering by a status that exists must return rows');
		$this->assertSame(array('E'), array_unique(array_column($rows, 'status')),
			'col_filter[status] must restrict the result to that status only');
	}

	/**
	 * A multi-valued status filter (what a multi-select sends) must return all of the named
	 * fields and nothing else.
	 */
	public function testGetRowsColFilterStatusMultiple()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');
		$this->history->add('C', $record_id, 'created', '');
		$this->history->add('D', $record_id, 'deleted', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('status' => array('E', 'D')))), $rows);

		$statuses = array_unique(array_column($rows, 'status'));
		sort($statuses);
		$this->assertSame(array('D', 'E'), $statuses);
	}

	/**
	 * 'colfilter' (no underscore) is NOT honoured.
	 *
	 * get_rows() read that key from 2010 until this conversion, but nothing in EGroupware ever
	 * wrote it - eTemplate speaks 'col_filter' everywhere - so the filtering behind it was
	 * unreachable for its whole life.  It was dropped rather than kept as an alias; this pins that
	 * decision so nobody re-adds a second spelling.
	 */
	public function testGetRowsIgnoresTheOldColfilterSpelling()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');
		$this->history->add('C', $record_id, 'created', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('colfilter' => array('status' => 'C'))), $rows);

		$statuses = array_unique(array_column($rows, 'status'));
		sort($statuses);
		$this->assertSame(array('C', 'E'), $statuses,
			"'colfilter' must be ignored - 'col_filter' is the only spelling");
	}

	/**
	 * col_filter[owner] must restrict to changes made by that account.
	 */
	public function testGetRowsColFilterOwner()
	{
		$record_id = $this->newRecordId();
		$me = (int)$GLOBALS['egw_info']['user']['account_id'];
		$other = $me + 1;
		$this->history->add('E', $record_id, 'mine', '');
		// A second instance writes as a different user, without touching the shared one
		(new History(self::APP, $other))->add('E', $record_id, 'theirs', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('owner' => $me))), $rows);

		$this->assertNotEmpty($rows);
		$this->assertSame(array((string)$me), array_unique(array_map('strval', array_column($rows, 'owner'))),
			'col_filter[owner] must restrict the result to that account');
		$this->assertSame(array('mine'), array_column($rows, 'new_value'));
	}

	/**
	 * An unknown col_filter key must be dropped, not turned into a WHERE clause - otherwise a
	 * client could name any column (or worse) and at best get a fatal.
	 */
	public function testGetRowsDropsUnknownColFilter()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');

		$rows = array();
		$total = History::get_rows($this->rowsQuery($record_id, array('col_filter' => array(
			'no_such_column' => 'whatever',
		))), $rows);

		$this->assertGreaterThanOrEqual(1, $total, 'an unknown filter key must be ignored, not applied');
		$this->assertNotEmpty($rows);
	}

	/**
	 * A numerically-keyed col_filter entry is a raw SQL fragment in eTemplate's convention, and
	 * must never be accepted from a filter - only fragments get_rows() builds itself are trusted.
	 */
	public function testGetRowsIgnoresRawSqlColFilterFragment()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');

		$rows = array();
		$total = History::get_rows($this->rowsQuery($record_id, array('col_filter' => array(
			'1=2',                     // numeric key => would be raw SQL
		))), $rows);

		$this->assertGreaterThanOrEqual(1, $total,
			'a raw SQL fragment passed as a filter must be ignored, not executed');
		$this->assertNotEmpty($rows);
	}

	/**
	 * A '#cf' column filter is only honoured for a custom field that currently exists for the
	 * app - self::APP has none, so it must be dropped rather than applied.
	 */
	public function testGetRowsIgnoresUndefinedCustomFieldColFilter()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');

		$rows = array();
		$total = History::get_rows($this->rowsQuery($record_id, array('col_filter' => array(
			'#not_a_real_cf' => 'x',
		))), $rows);

		$this->assertGreaterThanOrEqual(1, $total,
			'a filter naming a non-existent custom field must be ignored');
	}

	/**
	 * search must match either the new or the old value, case-insensitively where the DB supports
	 * it, and must not match a row that contains the term in neither.
	 */
	public function testGetRowsSearch()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'findable haystack', '');
		$this->history->add('C', $record_id, '', 'other needle here');
		$this->history->add('D', $record_id, 'nothing relevant', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('search' => 'findable')), $rows);
		$this->assertSame(array('findable haystack'), array_column($rows, 'new_value'),
			'search must match the new value');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('search' => 'needle')), $rows);
		$this->assertCount(1, $rows, 'search must match the old value too');
		$this->assertSame('other needle here', $rows[0]['old_value']);

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('search' => 'definitely-absent-term')), $rows);
		$this->assertEmpty($rows, 'search must not match rows containing the term in neither value');
	}

	/**
	 * A '%' or '_' the user typed is a literal to search for, not a wildcard - otherwise
	 * searching for "50%" would match every row.
	 */
	public function testGetRowsSearchEscapesWildcards()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'discount 50% off', '');
		$this->history->add('C', $record_id, 'no percentage here', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('search' => '50%')), $rows);
		$this->assertSame(array('discount 50% off'), array_column($rows, 'new_value'),
			"'%' in a search term must be escaped, not treated as a wildcard");

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('search' => 'a%h')), $rows);
		$this->assertEmpty($rows, "'%' must not act as a wildcard between two literals");
	}

	/**
	 * An empty / whitespace-only search must be a no-op rather than a LIKE '%%' that happens to
	 * match everything (same result, but it would also drop the attachments leg).
	 */
	public function testGetRowsBlankSearchIsNoop()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');

		foreach(array('', '   ', null) as $search)
		{
			$rows = array();
			$total = History::get_rows($this->rowsQuery($record_id, array('search' => $search)), $rows);
			$this->assertGreaterThanOrEqual(1, $total,
				'a blank search must not filter anything out');
		}
	}

	/**
	 * A from/to date range must include a row written now, and exclude one outside the range.
	 * The filter value is user-time and the column is server-time, so this also covers the
	 * conversion - on an instance where the two differ, comparing them raw would be off by the
	 * user's tz offset.
	 */
	public function testGetRowsColFilterDateRange()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'today', '');

		$today = new Api\DateTime('now');
		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('user_ts' => array(
			'from' => $today->format('Y-m-d'),
			'to'   => $today->format('Y-m-d'),
		)))), $rows);
		$this->assertNotEmpty($rows, "a range covering today must include a row written now");

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('user_ts' => array(
			'from' => '2000-01-01',
			'to'   => '2000-01-31',
		)))), $rows);
		$this->assertEmpty($rows, 'a range in the far past must exclude a row written now');
	}

	/**
	 * The date range as the *client actually sends it*: Et2DateRange hands back W3C strings with a
	 * midnight time component ("2026-09-16T00:00:00Z"), not bare Y-m-d.
	 *
	 * That distinction is the whole bug this pins. An end-of-day extension keyed on "does the
	 * string contain a time?" never fires for these, so `to` stays at midnight and the user's last
	 * day is excluded entirely - filtering 16th..18th returned nothing at all for rows written on
	 * the 18th.
	 *
	 * Pass criteria: a range whose `to` is the row's own day includes that row.
	 */
	public function testGetRowsDateRangeIncludesTheToDayForW3CValues()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'written now', '');

		$today = new Api\DateTime('now');
		$from = (clone $today)->modify('-2 days');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('user_ts' => array(
			'from' => $from->format('Y-m-d').'T00:00:00Z',
			'to'   => $today->format('Y-m-d').'T00:00:00Z',
		)))), $rows);

		$this->assertNotEmpty($rows,
			"a range ending on today, sent as the client sends it (midnight W3C), must still include ".
			"a row written today - otherwise the user's last chosen day is silently dropped");
	}

	/**
	 * Both edges of the range, pinned with rows at known times rather than "whatever time the test
	 * happens to run".
	 *
	 * A range of 16th..18th must include everything on the 18th - right up to 23:59:59 - and
	 * nothing from the 19th. The rows are written through the normal add() path and then given
	 * explicit timestamps; they live under this test's own fake record id and tearDown() removes
	 * them with the rest.
	 *
	 * Pass criteria: late-on-the-to-day is in, just-after-midnight-next-day is out, and the
	 * from day's very first second is in.
	 */
	public function testGetRowsDateRangeIsInclusiveOfBothDays()
	{
		$record_id = $this->newRecordId();
		$db = $GLOBALS['egw']->db;

		$day = static function($offset_days, $h, $i, $sec = 0)
		{
			$d = new Api\DateTime('today');
			$d->modify($offset_days.' days');
			$d->setTime($h, $i, $sec);
			return $d;
		};
		// from = 2 days ago, to = today
		$cases = array(
			'start of from day'  => array($day(-2, 0, 0, 0), true),
			'end of to day'      => array($day(0, 23, 59, 59), true),
			'just after to day'  => array($day(1, 0, 0, 1), false),
			'just before from'   => array($day(-3, 23, 59, 59), false),
		);
		$expected = array();
		foreach($cases as $label => list($when, $in_range))
		{
			$this->history->add('E', $record_id, $label, '');
			// re-stamp the row we just wrote
			$db->update(History::TABLE, array('history_timestamp' => $when->format('ts')),
				array('history_appname' => self::APP, 'history_record_id' => $record_id,
					  'history_new_value' => $label), __LINE__, __FILE__);
			if($in_range) $expected[] = $label;
		}
		sort($expected);

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('user_ts' => array(
			'from' => (new Api\DateTime('today'))->modify('-2 days')->format('Y-m-d').'T00:00:00Z',
			'to'   => (new Api\DateTime('today'))->format('Y-m-d').'T00:00:00Z',
		)))), $rows);

		$got = array_column($rows, 'new_value');
		sort($got);
		$this->assertSame($expected, $got,
			'the range must cover whole days: both chosen days entirely in, neighbours out');
	}

	/**
	 * A 'to' date the user picked is a day they want included, so the range must run to the end
	 * of it - a naive comparison against midnight would silently drop everything from that day.
	 */
	public function testGetRowsDateRangeToIsInclusive()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'today', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('col_filter' => array('user_ts' => array(
			'to' => (new Api\DateTime('now'))->format('Y-m-d'),
		)))), $rows);

		$this->assertNotEmpty($rows,
			"a 'to' date of today must include rows written earlier today, not just before midnight");
	}

	// --- attachment (VFS) leg gating ---

	/**
	 * wantsFiles() decides whether the attachments UNION leg can be part of the query at all.
	 * None of the history columns exist on a VFS row, so any filter naming one has to exclude
	 * them - except a status filter that explicitly lists '~file~'.
	 *
	 * Tested directly: whether attachments actually appear also depends on the filemanager app
	 * being installed and the entry having a VFS directory, neither of which this fixture has.
	 */
	public function testWantsFiles()
	{
		$wantsFiles = new \ReflectionMethod(History::class, 'wantsFiles');
		$wantsFiles->setAccessible(true);
		$call = fn(array $col_filter, $search = null) => $wantsFiles->invoke(null, $col_filter, $search);

		$this->assertTrue($call(array()), 'no filter at all => attachments included');
		$this->assertTrue($call(array('status' => '')), 'an empty filter value is not a filter');
		$this->assertTrue($call(array('status' => History::FILE_STATUS)),
			"a status filter of just '~file~' => only attachments");
		$this->assertTrue($call(array('status' => array('E', History::FILE_STATUS))),
			"a status filter listing '~file~' alongside others => attachments included");
		$this->assertFalse($call(array('status' => 'E')),
			"a status filter not listing '~file~' => attachments excluded");
		$this->assertFalse($call(array('status' => array('E', 'C'))),
			'same for a multi-valued status filter');
		$this->assertFalse($call(array('owner' => 1)),
			'an owner filter cannot be answered for a VFS row => excluded');
		$this->assertFalse($call(array('user_ts' => array('from' => '2020-01-01'))),
			'a date filter maps to a different column on a VFS row => excluded');
		$this->assertFalse($call(array('#some_cf' => 'x')),
			'a custom-field filter cannot be answered for a VFS row => excluded');
		$this->assertFalse($call(array(), 'needle'),
			'a free-text search cannot be answered for a VFS row => excluded');
		$this->assertTrue($call(array(), '   '),
			'a blank search is not a search');
		$this->assertTrue($call(array('no_such_column' => 'x')),
			'an unknown (dropped) filter key must not exclude attachments either');
	}

	// --- missing appname ---

	/**
	 * What happens when the history log has no `app` in its content.
	 *
	 * Apps are expected to set it (Api\Storage\Tracking documents
	 * `$content['history'] = ['id' => ..., 'app' => ...]`), and every in-tree caller does - but
	 * nothing enforces it, and HistoryLog::validate() now takes the value from the server's own
	 * content, so a caller that omits it yields null rather than whatever the client happened to
	 * send.
	 *
	 * Pass criteria: it must not fatal or warn, and it must not leak another app's history.
	 */
	public function testGetRowsWithoutAppname()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'visible to its own app', '');

		foreach([null, ''] as $appname)
		{
			$rows = array();
			$query = array(
				'appname'   => $appname,
				'record_id' => $record_id,
				'start'     => 0,
				'num_rows'  => 10,
			);
			$total = History::get_rows($query, $rows);

			$this->assertSame(0, (int)$total,
				'without an appname nothing may be returned - a history query is only ever meaningful '.
				'scoped to one app, and returning everything for this record id across apps would leak');
			$this->assertEmpty($rows);
		}
	}

	/**
	 * The row loop reads $cfs, which is only assigned inside the `if($filter['history_appname'])`
	 * branch - so a query that returned rows without an appname would hit an undefined variable.
	 *
	 * Pass criteria: no *undefined-variable* warning.  Deliberately narrow: get_rows() fires the
	 * `etemplate2_history_get_rows` hook, which runs other apps' code and raises a steady stream of
	 * unrelated deprecations (json_decode(null), dynamic properties, cache-dir permissions) that
	 * have nothing to do with this.
	 */
	public function testGetRowsWithoutAppnameRaisesNoWarning()
	{
		$record_id = $this->newRecordId();
		$this->history->add('#some_cf', $record_id, 'x', '');
		$this->history->add('E', $record_id, 'y', '');

		$seen = array();
		set_error_handler(static function($errno, $errstr) use (&$seen)
		{
			$seen[] = $errstr;
			return true;
		});
		try
		{
			$rows = array();
			$query = array(
				'appname'   => null,
				'record_id' => $record_id,
				'start'     => 0,
				'num_rows'  => 10,
			);
			History::get_rows($query, $rows);
		}
		finally
		{
			restore_error_handler();
		}

		$undefined = array_values(array_filter($seen, static function($message)
		{
			return stripos($message, 'undefined variable') !== false ||
				stripos($message, 'undefined array key') !== false && stripos($message, 'cfs') !== false;
		}));
		$this->assertSame(array(), $undefined,
			'a history query without an appname must not read an undefined variable ($cfs)');
	}

	// --- server-side raw SQL 'filter' ---

	/**
	 * An app may add a raw SQL fragment server-side via `$content[<widget id>]['filter']`.
	 * Calendar is the real user: viewing one occurrence of a recurring event, it restricts
	 * `participants*` rows to the ones whose value ends with that recurrence, while leaving every
	 * other field's history alone.
	 *
	 * Pass criteria: the fragment is applied, matching calendar's actual shape.
	 */
	public function testGetRowsAppliesServerSideFilterFragment()
	{
		$record_id = $this->newRecordId();
		$sep = Tracking::ONE2N_SEPERATOR;
		// two participant changes for different recurrences, plus an unrelated field
		$this->history->add('participants', $record_id, 'someone'.$sep.'1500000000', '');
		$this->history->add('participants', $record_id, 'someone'.$sep.'1600000000', '');
		$this->history->add('title', $record_id, 'new title', 'old title');

		$query = $this->rowsQuery($record_id, array('filter' => array(
			"(history_status NOT LIKE 'participants%' OR (history_status LIKE 'participants%' AND (".
			"history_new_value LIKE '%".$sep."1600000000' OR history_old_value LIKE '%".$sep."1600000000')))"
		)));
		$rows = array();
		History::get_rows($query, $rows);

		// get_rows() explodes a value containing ONE2N_SEPERATOR into its parts, so compare on a
		// flattened form rather than the stored string
		$values = array_map(static function($value)
		{
			return is_array($value) ? implode(Tracking::ONE2N_SEPERATOR, $value) : $value;
		}, array_column($rows, 'new_value'));
		sort($values);
		$this->assertSame(array('new title', 'someone'.$sep.'1600000000'), $values,
			'the other recurrence\'s participant row must be filtered out, and the non-participant row kept');
	}

	/**
	 * Pass criteria: a string fragment works as well as calendar's one-element array, and an empty
	 * or non-string entry is ignored rather than producing broken SQL.
	 */
	public function testGetRowsServerSideFilterAcceptsStringAndIgnoresJunk()
	{
		$record_id = $this->newRecordId();
		$this->history->add('E', $record_id, 'edited', '');
		$this->history->add('C', $record_id, 'created', '');

		$rows = array();
		History::get_rows($this->rowsQuery($record_id, array('filter' => "history_status = 'E'")), $rows);
		$this->assertSame(array('E'), array_unique(array_column($rows, 'status')),
			'a plain string fragment must be applied');

		foreach([array(), array(''), array('   '), array(null), array(array('nested')), ''] as $junk)
		{
			$rows = array();
			$total = History::get_rows($this->rowsQuery($record_id, array('filter' => $junk)), $rows);
			$this->assertGreaterThanOrEqual(2, $total,
				'an empty or non-string filter entry must be ignored, not turned into SQL: '.json_encode($junk));
		}
	}
}
