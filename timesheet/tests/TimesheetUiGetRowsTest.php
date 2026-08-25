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
	private function createTimesheet(int $owner, int $duration = 60): int
	{
		$so = new EGroupware\Api\Storage\Base('timesheet', 'egw_timesheet');
		$so->data = array(
			'ts_title'    => 'phpunit_getrows_'.bin2hex(random_bytes(6)),
			'ts_start'    => time(),
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
}
