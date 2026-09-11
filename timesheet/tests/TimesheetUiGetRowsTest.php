<?php
/**
 * EGroupware timesheet: regression test for get_rrows() column-visibility markers
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api\Acl;

/**
 * timesheet_ui::get_rrows() computes a handful of "hide/disable this column" flags
 * (no_ts_quantity, no_ts_unitprice, no_ts_total, no_ts_status, no_owner_col, no_cat_id)
 * that the row template's `disabled="@flag"` column attributes are resolved against
 * client-side. The client resolves `@flag` against the nextmatch widget's own top-level
 * content (content.nm.flag), NOT against content.nm.rows.flag - so these flags must land
 * on $query_in (which becomes content.nm), not on $rows (which becomes content.nm.rows
 * and is otherwise real per-record row data).
 *
 * Before the fix, all of these were written onto $rows instead, which silently made every
 * one of these column toggles inert (columns never actually hid/disabled, regardless of
 * selectcols/permissions/pm_integration).
 */
class TimesheetUiGetRowsTest extends \EGroupware\Api\AppTest
{
	/** @var int[] ts_ids created by the current test, cleaned up in tearDown */
	private $ts_ids = array();

	protected function tearDown(): void
	{
		foreach ($this->ts_ids as $ts_id)
		{
			$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
			$so->delete(array('ts_id' => $ts_id));
		}
		$this->ts_ids = array();
	}

	/**
	 * Insert a timesheet row directly, owned by the given account.
	 */
	private function createTimesheet(int $owner, int $duration = 60, ?int $start = null): int
	{
		$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_getrows_'.bin2hex(random_bytes(6)),
			'ts_start'    => $start ?? time(),
			'ts_duration' => $duration,
			'ts_quantity' => 1.0,
			'ts_owner'    => $owner,
			'ts_created'  => time(),
			'ts_modified' => time(),
			'ts_modifier' => $owner,
		);
		$so->save();

		return $this->ts_ids[] = (int)$so->data['ts_id'];
	}

	/**
	 * timesheet_bo::search() restricts col_filter['ts_owner'] to accounts present in
	 * $this->grants (built from Api\Acl::get_grants() in the constructor), silently
	 * dropping any owner id that isn't - see search()'s "reimplemented to limit result
	 * to users we have grants from". A fixture owned by the synthetic id createTimesheet()
	 * uses (chosen specifically so it can never collide with another test's or the real
	 * user's data) would therefore never come back from get_rrows() at all, without this.
	 *
	 * $grants is a plain public property populated once in the constructor, so poking it
	 * directly here is enough - no real Acl/DB round-trip needed for a test-only grant.
	 */
	private function grantOwnerAccess(timesheet_bo $bo, int $owner): void
	{
		$bo->grants[$owner] = Acl::READ;
	}

	/**
	 * [year, month] for a month described relative to "now" (eg. 'first day of last month').
	 *
	 * Used instead of hardcoded calendar months so partial-month tests keep working no
	 * matter what day they happen to run on - a literal month/year pair goes stale the
	 * moment "now" moves past it (see the two partial-detection tests below, which used
	 * to hardcode July/August 2026 against a "today is 2026-08-27" comment).
	 */
	private function relativeMonth(string $modify): array
	{
		$ts = strtotime($modify);
		return array((int)date('Y', $ts), (int)date('n', $ts));
	}

	/**
	 * Build a minimal, valid $query array for get_rrows(), scoped to one owner so it
	 * never sees another test's or user's real data.
	 */
	private function baseQuery(int $owner, array $overrides = array()): array
	{
		// selectcols is deliberately omitted here, not set to '' - that's how the client
		// represents "no column restriction" (Et2Nextmatch only sends selectcols once
		// there's a non-default set of visible columns). Setting it to '' would explode()
		// into [''], a truthy 1-element array, and incorrectly look like a restriction
		// that excludes every real column.
		return array_merge(array(
			'filter'     => '',
			'startdate'  => 0,
			'enddate'    => 0,
			'col_filter' => array('ts_owner' => $owner),
			'cat_id'     => '',
			'no_status'  => false,
			'search'     => '',
		), $overrides);
	}

	/**
	 * Pass criteria: after get_rrows(), none of the column-visibility markers are present
	 * on $rows - $rows must contain only real per-record data (or be empty), since it
	 * becomes content.nm.rows, which the client only ever treats as literal row records.
	 */
	public function testVisibilityMarkersAreNotWrittenOntoRows()
	{
		$owner = random_int(1000000000, 2000000000);
		$this->createTimesheet($owner);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner);
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		foreach (array('no_cat_id', 'no_owner_col', 'pm_integration',
			'no_ts_quantity', 'no_ts_unitprice', 'no_ts_total', 'no_ts_status') as $flag)
		{
			$this->assertArrayNotHasKey($flag, $rows,
				"'$flag' must not be mixed into \$rows - it becomes content.nm.rows and would be ".
				"misread as a real row record client-side");
		}
	}

	/**
	 * Pass criteria: get_rrows() writes the flags onto $query (by-reference $query_in),
	 * which is what becomes content.nm - the scope the client actually resolves
	 * `disabled="@flag"` column attributes against.
	 */
	public function testVisibilityMarkersAreWrittenOntoQueryIn()
	{
		$owner = random_int(1000000000, 2000000000);
		$this->createTimesheet($owner);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner);
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		foreach (array('no_cat_id', 'no_owner_col', 'no_ts_quantity', 'no_ts_unitprice',
			'no_ts_total', 'no_ts_status') as $flag)
		{
			$this->assertArrayHasKey($flag, $query,
				"'$flag' must be set on \$query_in so it reaches content.nm at the top level");
		}
	}

	/**
	 * Pass criteria: with no selectcols restriction at all, none of the quantity/
	 * unitprice/total columns are marked hidden.
	 */
	public function testQuantityColumnsVisibleWithNoSelectcolsRestriction()
	{
		$owner = random_int(1000000000, 2000000000);
		$this->createTimesheet($owner);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner);
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$this->assertFalse((bool)$query['no_ts_quantity'], 'quantity column must be visible by default');
		$this->assertFalse((bool)$query['no_ts_unitprice'], 'unitprice column must be visible by default');
		$this->assertFalse((bool)$query['no_ts_total'], 'total column must be visible by default');
	}

	/**
	 * Pass criteria: when selectcols is restricted and excludes the quantity column's
	 * key, get_rrows() must mark no_ts_quantity truthy - and must NOT flag the
	 * unitprice/total columns, which are still present in selectcols.
	 *
	 * This is the actual "does the flag toggle what it should" check: the fix moves
	 * *where* the flag is written, this test confirms the *value* computed for each
	 * flag still tracks the real selectcols input, independently per column.
	 */
	public function testQuantityColumnHiddenWhenExcludedFromSelectcols()
	{
		$owner = random_int(1000000000, 2000000000);
		$this->createTimesheet($owner);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner, array(
			'selectcols' => array('ts_start', 'ts_unitprice', 'ts_total_price'),
		));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$this->assertNotEmpty($query['no_ts_quantity'], 'quantity column must be hidden when excluded from selectcols');
		$this->assertEmpty($query['no_ts_unitprice'], 'unitprice column must stay visible - it is in selectcols');
		$this->assertEmpty($query['no_ts_total'], 'total column must stay visible - it is in selectcols');
	}

	/**
	 * Pass criteria: no_ts_status is truthy when 'ts_status' is excluded from a
	 * restricted selectcols, and falsy when it's included.
	 *
	 * The selectcols-driven part of this flag is deliberately gated off by the app's
	 * 'history' config (see get_rrows()'s `&& !$this->config_data['history']`) - force
	 * it off here so the test exercises the selectcols branch regardless of what this
	 * install's timesheet config happens to have configured.
	 */
	public function testStatusColumnTracksSelectcols()
	{
		$owner = random_int(1000000000, 2000000000);
		$this->createTimesheet($owner);
		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$ui->config_data['history'] = '';

		$excluded = $this->baseQuery($owner, array('selectcols' => array('ts_start')));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($excluded, $rows, $readonlys);
		$this->assertNotEmpty($excluded['no_ts_status'], 'status column must be hidden when excluded from selectcols');

		$included = $this->baseQuery($owner, array('selectcols' => array('ts_start', 'ts_status')));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($included, $rows, $readonlys);
		$this->assertEmpty($included['no_ts_status'], 'status column must stay visible when included in selectcols');
	}

	/**
	 * Regression test: get_rrows() computes 'ts_end_time' = ts_start + 60*ts_duration on
	 * every real row, so a custom row template can display the timesheet's end time
	 * without the DB actually storing one.
	 *
	 * Pass criteria: for a row with a known start+duration, ts_end_time matches exactly.
	 */
	public function testEndTimeComputedFromStartPlusDuration()
	{
		$owner = random_int(1000000000, 2000000000);
		// Stay on today's date (only pin the time-of-day) - the default query filter
		// ('' / no explicit startdate+enddate) only matches a "current" date range, and
		// a fixed past/future calendar date would fall outside it and never come back.
		$start = strtotime('today 09:00:00');
		$duration = 45;    // minutes
		$ts_id = $this->createTimesheet($owner, $duration);
		// createTimesheet() above always uses time() as start; overwrite just that one
		// column so the expected end time is deterministic instead of tied to "now".
		$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->update(array('ts_id' => $ts_id, 'ts_start' => $start));

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner);
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$row = null;
		foreach ($rows as $candidate)
		{
			if (is_array($candidate) && ($candidate['ts_id'] ?? null) == $ts_id)
			{
				$row = $candidate;
				break;
			}
		}
		$this->assertNotNull($row, 'fixture row must come back from get_rrows()');
		$this->assertSame($start + 60 * $duration, $row['ts_end_time'],
			'ts_end_time must equal ts_start + 60*ts_duration');
	}

	/**
	 * Regression test: a 'custom' filter with no startdate yet (eg. the first
	 * ajax_get_rows on a fresh page load, before the client has resent a persisted custom
	 * range) used to have $query_in['startdate'] forced to the literal integer 1 (one
	 * second after Unix epoch) instead of staying falsy like $end_date does right beside
	 * it. That truthy placeholder made sql_filter() enter its 'custom' branch and
	 * round-trip it through `new DateTime(1)` -> setTime(0,0,0) -> format('ts'), which
	 * comes back as the *also*-falsy integer 0 (not exactly 1 - a subtlety loose
	 * assertEmpty()/assertFalsy() checks would miss, since 0 and false are both "empty" in
	 * PHP) - but a real Unix timestamp of 0 is a valid, specific date (1970-01-01), not
	 * "no date", so it still rendered client-side as a bogus near-epoch date instead of
	 * genuinely empty.
	 *
	 * Pass criteria: startdate comes back false, not merely falsy - the strict assertSame()
	 * is required here, since assertEmpty() cannot tell 0 and false apart and would have
	 * let the pre-fix behaviour (0) pass right along with the fix (false).
	 */
	public function testCustomFilterWithNoStartdateStaysEmpty()
	{
		$owner = random_int(1000000000, 2000000000);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner, array('filter' => 'custom', 'startdate' => 0, 'enddate' => 0));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$this->assertSame(0, $query['startdate'],
			'get_rrows() must not rewrite the caller\'s startdate into a truthy placeholder timestamp (eg. the old `: 1` sentinel)');
	}

	/**
	 * Nextmatch::beforeSendToClient() calls get_rrows() with the same array it then
	 * serializes back to the client as content.nm - so get_rrows() must not mutate
	 * $query_in['startdate']/['enddate'] into the internal raw-Unix-timestamp form it
	 * needs for the SQL date filter. Left mutated, the client (which expects an ISO
	 * string here) would misread the raw seconds value as milliseconds and render a
	 * near-epoch garbage date (eg. a real "2026-08-02" range showing as "1970-01-21").
	 */
	public function testCustomFilterStartdateNotMutatedForClient()
	{
		$owner = random_int(1000000000, 2000000000);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner, array(
			'filter'    => 'custom',
			'startdate' => '2026-08-02T00:00:00Z',
			'enddate'   => '2026-08-22T00:00:00Z',
		));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$this->assertSame('2026-08-02T00:00:00Z', $query['startdate'],
			'startdate sent back to the client must stay the original ISO string, not the internal raw timestamp used for the SQL filter');
		$this->assertSame('2026-08-22T00:00:00Z', $query['enddate'],
			'enddate sent back to the client must stay the original ISO string');
	}

	/**
	 * A full-month range is under the 5-week cutoff that would otherwise also turn on
	 * day-sums, which is far too granular on top of the month total already shown - week
	 * sums are the useful middle ground, even though a calendar month essentially never
	 * lands on the user's configured week boundaries.
	 */
	public function testMonthRangeShowsWeekSumsNotDaySums()
	{
		$owner = random_int(1000000000, 2000000000);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		$query = $this->baseQuery($owner, array(
			'filter'    => 'custom',
			'startdate' => '2026-08-01T00:00:00Z',
			'enddate'   => '2026-08-31T00:00:00Z',
		));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$this->assertContains('month', $ui->show_sums);
		$this->assertContains('week', $ui->show_sums);
		$this->assertNotContains('day', $ui->show_sums);
	}

	/**
	 * A month/week/year-sum whose period isn't fully covered by the report range - either
	 * because it's still running (the current, not-yet-finished month) or because the range
	 * itself starts/ends mid-period - is misleading labelled just "Sum ...", as if it were a
	 * complete total. It must say "Partial ..." instead and carry a distinct row class, so a
	 * template/CSS can call it out.
	 */
	public function testPartialMonthSumIsMarkedPartial()
	{
		$owner = random_int(1000000000, 2000000000);

		$ui = new timesheet_ui();
		$this->grantOwnerAccess($ui, $owner);
		// "Last month" is always entirely in the past - a complete month. "Next month" is
		// always entirely in the future - necessarily partial. The *current* calendar month
		// is NOT safe to use here: a month-sum's period_end is midnight of its last calendar
		// day (see timesheet_ui::get_rrows()), so once "now" reaches that day, the current
		// month stops being flagged partial - which is exactly what broke this test when it
		// used to hardcode a fixed "current" month against a "today is ..." comment.
		list($completeYear, $completeMonth) = $this->relativeMonth('first day of last month');
		list($partialYear, $partialMonth) = $this->relativeMonth('first day of next month');
		$this->createTimesheet($owner, 60, mktime(9, 0, 0, $completeMonth, 15, $completeYear));
		$this->createTimesheet($owner, 60, mktime(9, 0, 0, $partialMonth, 15, $partialYear));

		// get_rrows()'s month-alignment check wants 'enddate' anchored on the *last* day of
		// the covered month (like the date-range widget sends it), not the 1st of the
		// following month - otherwise it isn't "month-aligned" and no month-sums are shown.
		$partialMonthLastDay = (int)date('t', mktime(0, 0, 0, $partialMonth, 1, $partialYear));

		$query = $this->baseQuery($owner, array(
			'filter'    => 'custom',
			'startdate' => sprintf('%04d-%02d-01T00:00:00Z', $completeYear, $completeMonth),
			'enddate'   => sprintf('%04d-%02d-%02dT00:00:00Z', $partialYear, $partialMonth, $partialMonthLastDay),
			// summary/sum rows are only generated when ordered by ts_start (see
			// timesheet_bo::search()) - baseQuery() otherwise leaves order/sort unset.
			'order'     => 'ts_start',
			'sort'      => 'ASC',
		));
		$rows = array();
		$readonlys = array();
		$ui->get_rrows($query, $rows, $readonlys);

		$completeSumId = sprintf('sum-month-%04d%02d', $completeYear, $completeMonth);
		$partialSumId = sprintf('sum-month-%04d%02d', $partialYear, $partialMonth);
		$completeSum = null;
		$partialSum = null;
		foreach($rows as $row)
		{
			if (!is_array($row)) continue;	// eg. the header-totals entry, not a real/sum row
			if (($row['ts_id'] ?? null) === $completeSumId) $completeSum = $row;
			if (($row['ts_id'] ?? null) === $partialSumId) $partialSum = $row;
		}

		$this->assertNotNull($completeSum, "expected a month-sum row for $completeSumId");
		$this->assertNotNull($partialSum, "expected a month-sum row for $partialSumId");

		$this->assertStringStartsWith('Sum', $completeSum['ts_title'], 'a fully elapsed month must not be marked partial');
		$this->assertStringNotContainsString('rowSumPartial', $completeSum['class']);

		$this->assertStringStartsWith('Partial', $partialSum['ts_title'], 'a not-yet-started month must be marked partial');
		$this->assertStringContainsString('rowSumPartial', $partialSum['class']);
	}

	/**
	 * Api\DateTime::sql_filter() computes $start_date/$end_date via DateTime::user2server(),
	 * which converts its DateTime objects to server timezone as a side effect before the
	 * final format('ts') that produces the returned value - so format('ts') alone (without
	 * first calling setServer()) gives a value that's off by the user/server timezone
	 * difference. That only shows up for a user whose timezone isn't the server's, which a
	 * UTC-timezone test user can't catch - hence pinning a non-UTC timezone here.
	 *
	 * Uses a named filter ('Last month'), not 'custom': a 'custom' filter's Z-suffixed ISO
	 * dates are parsed by PHP as literal UTC, sidestepping the user-timezone conversion
	 * entirely - a named filter's 'now'-relative computation is what actually exercises it,
	 * and is also the path the reported bug came from (the toolbar's date-range presets).
	 */
	public function testPartialDetectionRespectsNonUtcUserTimezone()
	{
		$owner = random_int(1000000000, 2000000000);
		$originalTz = \EGroupware\Api\DateTime::$user_timezone;
		try
		{
			\EGroupware\Api\DateTime::setUserPrefs('America/Edmonton');

			$ui = new timesheet_ui();
			$this->grantOwnerAccess($ui, $owner);
			// "Last month" (relative to now) is always entirely in the past, so it must not
			// come back partial, regardless of the user's timezone.
			list($lastYear, $lastMonth) = $this->relativeMonth('first day of last month');
			$this->createTimesheet($owner, 60, mktime(9, 0, 0, $lastMonth, 15, $lastYear));

			$query = $this->baseQuery($owner, array(
				'filter'    => 'Last month',
				'order'     => 'ts_start',
				'sort'      => 'ASC',
			));
			$rows = array();
			$readonlys = array();
			$ui->get_rrows($query, $rows, $readonlys);

			$lastMonthSumId = sprintf('sum-month-%04d%02d', $lastYear, $lastMonth);
			$lastMonthSum = null;
			foreach($rows as $row)
			{
				if (!is_array($row)) continue;
				if (($row['ts_id'] ?? null) === $lastMonthSumId) $lastMonthSum = $row;
			}

			$this->assertNotNull($lastMonthSum, "expected a month-sum row for $lastMonthSumId");
			$this->assertStringStartsWith('Sum', $lastMonthSum['ts_title'],
				'a fully elapsed month must not be marked partial just because the user timezone is not UTC');
			$this->assertStringNotContainsString('rowSumPartial', $lastMonthSum['class']);
		}
		finally
		{
			\EGroupware\Api\DateTime::$user_timezone = $originalTz;
		}
	}
}
