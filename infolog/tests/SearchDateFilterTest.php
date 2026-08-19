<?php

/**
 * Phase 0 test harness for the InfoLog storage-migration project
 * (doc/ai/projects/infolog-storage-migration.md).
 *
 * Covers infolog_bo::search()'s date-shortcut filters
 * ('upcoming'/'today'/'overdue', dispatched to infolog_so::dateFilter(), and
 * 'bydate', handled entirely inside infolog_bo::search() itself via
 * $query['startdate']/$query['enddate'] - infolog_so::dateFilter() con-
 * tributes nothing for 'bydate', see the migration doc's note on this).
 *
 * Fixtures are placed several days away from the "today"/"tomorrow"
 * boundary dateFilter() computes from the live clock, so ordinary test-run
 * timing jitter can't flip a result across the boundary. Assertions check
 * containment of specific known fixture ids rather than the whole result
 * set, since this runs against a real, possibly non-empty dev database.
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Infolog;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use EGroupware\Api;

class SearchDateFilterTest extends \EGroupware\Api\AppTest
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
			$this->bo->delete($info_id);
		}
		$this->info_ids = array();
		$this->bo = null;
	}

	protected function makeInfolog(array $fields = array())
	{
		$info = array(
			'info_type'    => 'task',
			'info_subject' => 'SearchDateFilterTest ' . $this->name(),
		);
		foreach($fields as $field => $value)
		{
			$info[$field] = $value;
		}
		$this->info_ids[] = $info_id = $this->bo->write($info, true, true, true, true);
		return $info_id;
	}

	protected function daysFromNow($days)
	{
		return Api\DateTime::to((new Api\DateTime('now'))->modify($days.' days'), 'ts');
	}

	/**
	 * dateFilter()'s 'upcoming': " AND info_startdate >= $tomorrow".
	 */
	public function testUpcomingFilterMatchesFutureNotPastStartdate()
	{
		$upcoming_id = $this->makeInfolog(array('info_startdate' => $this->daysFromNow(3)));
		$past_id     = $this->makeInfolog(array('info_startdate' => $this->daysFromNow(-3)));

		$query = array('filter' => 'upcoming', 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($upcoming_id, $ret,
			'a future info_startdate must match the "upcoming" filter');
		$this->assertArrayNotHasKey($past_id, $ret,
			'a past info_startdate must NOT match the "upcoming" filter');
	}

	/**
	 * dateFilter()'s 'today': " AND info_startdate < $tomorrow" - i.e. anything
	 * up to and including "now", but not anything starting tomorrow or later.
	 */
	public function testTodayFilterMatchesTodayNotFutureStartdate()
	{
		$today_id  = $this->makeInfolog(array('info_startdate' => $this->daysFromNow(0)));
		$future_id = $this->makeInfolog(array('info_startdate' => $this->daysFromNow(3)));

		$query = array('filter' => 'today', 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($today_id, $ret,
			'a startdate of "now" must match the "today" filter (info_startdate < tomorrow)');
		$this->assertArrayNotHasKey($future_id, $ret,
			'a future info_startdate must NOT match the "today" filter');
	}

	/**
	 * dateFilter()'s 'overdue': " AND (info_enddate != 0 AND info_enddate < $tomorrow)".
	 */
	public function testOverdueFilterMatchesPastNotFutureEnddate()
	{
		$overdue_id = $this->makeInfolog(array('info_enddate' => $this->daysFromNow(-3)));
		$future_id  = $this->makeInfolog(array('info_enddate' => $this->daysFromNow(3)));

		$query = array('filter' => 'overdue', 'order' => 'info_datemodified', 'sort' => 'DESC');
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($overdue_id, $ret,
			'a past info_enddate must match the "overdue" filter');
		$this->assertArrayNotHasKey($future_id, $ret,
			'a future info_enddate must NOT match the "overdue" filter');
	}

	/**
	 * 'bydate' is handled entirely inside infolog_bo::search() itself, NOT
	 * infolog_so::dateFilter() (whose regex happens to match the substring
	 * "date" inside "bydate", but its 'date' case bails out to '' because
	 * $today is never set on that code path - see the migration doc's SO
	 * method inventory). infolog_bo::search() instead turns
	 * $query['startdate']/$query['enddate'] into an info_startdate range
	 * col_filter.
	 */
	public function testBydateFilterMatchesStartdateWithinRange()
	{
		$in_id  = $this->makeInfolog(array('info_startdate' =>
			Api\DateTime::to(new Api\DateTime('2026-03-15 10:00:00'), 'ts')));
		$out_id = $this->makeInfolog(array('info_startdate' =>
			Api\DateTime::to(new Api\DateTime('2026-04-15 10:00:00'), 'ts')));

		$query = array(
			'filter'   => 'bydate',
			'startdate'=> '2026-03-01',
			'enddate'  => '2026-03-31',
			'order'    => 'info_datemodified',
			'sort'     => 'DESC',
		);
		$ret = $this->bo->search($query);

		$this->assertArrayHasKey($in_id, $ret,
			'a startdate inside the bydate range must match');
		$this->assertArrayNotHasKey($out_id, $ret,
			'a startdate outside the bydate range must NOT match');
	}
}
