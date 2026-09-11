<?php

/**
 * Regression tests for infolog_bo::search()'s "limit rows ordered by last modified to the last
 * N month" optimization (InfoLog site configuration "limit_modified_n_month"), which used to
 * show "9999 entries" followed by an endless run of empty placeholder rows.
 *
 * Behaviour under test:
 *
 *  1. The reported total is the real total of the limited list, not a fixed 9999 placeholder.
 *  2. A start past the end returns NO rows, instead of re-running the query at start 0 - that
 *     returned page 1's rows labelled as the requested range, which the client discards as
 *     already-displayed ids, leaving that range as placeholder rows forever.
 *  3. The window is chosen from the TOTAL of the limited query, not from how many rows one
 *     request returned, so every page of a list agrees on one window.
 *
 * Setup strategy: $bo->search() is driven directly with the $query infolog_ui::get_rows() would
 * build.  These run against a real, populated dev database whose contents are unknown, so every
 * assertion is relative to totals read at test time or to fixtures this test creates.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

class SearchLimitModifiedTest extends \EGroupware\Api\AppTest
{
	protected $bo;

	protected $info_ids = array();

	protected function setUp() : void
	{
		$this->bo = new \infolog_bo();
		$this->mockTracking($this->bo, 'infolog_tracking');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->info_ids) as $info_id)
		{
			$this->bo->delete($info_id);
			$this->bo->delete($info_id);	// second one purges it
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	/**
	 * One freshly written entry, so its info_datemodified is inside any window
	 */
	protected function makeInfolog()
	{
		$info = array(
			'info_type'    => 'task',
			'info_subject' => 'SearchLimitModifiedTest ' . $this->name(),
		);
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	/**
	 * The query infolog_ui::get_rows() builds for the index list: sorted by last changed
	 * descending, which is the only ordering the optimization is allowed to apply to.
	 */
	protected function indexQuery(array $extra = array())
	{
		return $extra + array(
			'order'      => 'info_datemodified',
			'sort'       => 'DESC',
			'filter'     => '',
			'col_filter' => array(),
			'search'     => '',
			'start'      => 0,
			'num_rows'   => 20,
		);
	}

	/**
	 * Total of the unlimited list, ie. what the optimization is an approximation of
	 */
	protected function unlimitedTotal()
	{
		$query = $this->indexQuery(array('num_rows' => 1));
		$this->bo->search($query);
		return (int)$query['total'];
	}

	/**
	 * Part 2: asking past the end must return nothing, not wrap around to the first page.
	 *
	 * Pass criteria: the out-of-range page is empty, and specifically does not hold the first
	 * page's ids - "wrapped to page 1" is the regression, and worth naming in the failure.
	 */
	public function testStartPastEndReturnsNoRowsInsteadOfWrappingToPageOne()
	{
		$this->makeInfolog();
		$total = $this->unlimitedTotal();

		$first_query = $this->indexQuery(array('start' => 0, 'num_rows' => 5));
		$first_page = $this->bo->search($first_query);
		$this->assertNotEmpty($first_page, 'test needs a non-empty InfoLog list to be meaningful');

		$past_query = $this->indexQuery(array('start' => $total + 100, 'num_rows' => 5));
		$past_end = $this->bo->search($past_query);

		$this->assertEmpty($past_end,
			'a start past the end must return no rows, got '.count((array)$past_end));
		$this->assertEmpty(array_intersect(array_keys((array)$past_end), array_keys($first_page)),
			'a start past the end must not wrap around and return the first page again');
		$this->assertSame($total, (int)$past_query['total'],
			'the reported total must stay correct for an out-of-range start');
	}

	/**
	 * Part 1: with the window in effect, the total is the real total of the limited list.
	 *
	 * Setup: three entries written just now guarantee the 1-month window holds more than the
	 * requested page of 2, so the window is applied and never widened.
	 *
	 * Pass criteria: the window is applied at exactly the configured 1 month, and the total is
	 * a plausible real count - at least our 3 fixtures, at most the unlimited total.
	 */
	public function testLimitedListReportsItsOwnTotalNotAPlaceholder()
	{
		$this->makeInfolog();
		$this->makeInfolog();
		$this->makeInfolog();
		$unlimited = $this->unlimitedTotal();

		$query = $this->indexQuery(array('num_rows' => 2, 'limit_modified_n_month' => 1));
		$this->bo->search($query);

		$this->assertNotSame(9999, (int)$query['total'],
			'the limited list must report its own total, not the removed 9999 placeholder');
		$this->assertSame(1, $this->bo->limit_modified_applied,
			'3 entries changed just now must fill a page of 2, so the configured window applies unwidened');
		$this->assertGreaterThanOrEqual(3, (int)$query['total'],
			'the total must at least count the 3 entries this test just wrote');
		$this->assertLessThanOrEqual($unlimited, (int)$query['total'],
			'a limited list can never hold more entries than the unlimited one');
	}

	/**
	 * Part 3: every page of one list has to end up with the same window.
	 *
	 * Setup: the same limited query for the first and the last page - the last one returns
	 * fewer rows than requested, which is what used to widen the window for that request alone.
	 *
	 * Pass criteria: both requests report the same window and the same total.
	 */
	public function testWindowDoesNotDependOnWhichPageIsRequested()
	{
		$this->makeInfolog();
		$this->makeInfolog();
		$this->makeInfolog();

		$first = $this->indexQuery(array('start' => 0, 'num_rows' => 2, 'limit_modified_n_month' => 1));
		$this->bo->search($first);
		$first_window = $this->bo->limit_modified_applied;

		$last = $this->indexQuery(array(
			'start' => max(0, (int)$first['total'] - 1), 'num_rows' => 2, 'limit_modified_n_month' => 1,
		));
		$this->bo->search($last);

		$this->assertSame($first_window, $this->bo->limit_modified_applied,
			'the last page returns less than a full page, which must not widen the window for it alone');
		$this->assertSame((int)$first['total'], (int)$last['total'],
			'every page of one list must report the same total');
	}

	/**
	 * The window is dropped entirely when even the widest one cannot fill the requested page.
	 *
	 * Setup: asking for more rows than the whole InfoLog holds makes every window too small, so
	 * search() runs out of retries and falls back to the unlimited query.
	 *
	 * Pass criteria: no window applied, the total is the unlimited total, and search() unset the
	 * caller's limit_modified_n_month to say so.
	 */
	public function testWindowIsDroppedWhenItCannotFillTheRequestedPage()
	{
		$this->makeInfolog();
		$unlimited = $this->unlimitedTotal();

		$query = $this->indexQuery(array('num_rows' => $unlimited + 10, 'limit_modified_n_month' => 1));
		$this->bo->search($query);

		$this->assertNull($this->bo->limit_modified_applied,
			'no window can fill a page bigger than the whole list, so none may be reported as applied');
		$this->assertSame($unlimited, (int)$query['total'],
			'dropping the window must report the real, unlimited total');
		$this->assertArrayNotHasKey('limit_modified_n_month', $query,
			'search() unsets the caller\'s limit_modified_n_month to signal it gave up on the window');
	}

	/**
	 * A search without the optimization configured must report no window - that is what
	 * infolog_ui::get_rows() passes to the client to hide the "older entries" notice.
	 */
	public function testUnlimitedSearchReportsNoWindow()
	{
		$this->makeInfolog();

		$query = $this->indexQuery();
		$this->bo->search($query);

		$this->assertNull($this->bo->limit_modified_applied,
			'an unlimited search must not claim the list is limited');
	}
}
