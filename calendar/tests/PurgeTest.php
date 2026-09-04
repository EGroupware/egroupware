<?php
/**
 * Tests for calendar's old-event purge (calendar_so::purge / calendar_boupdate::purge)
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__ . '/../../api/tests/AppTest.php'); // Application test base

use EGroupware\Api;

class PurgeTest extends \EGroupware\Api\AppTest
{
	protected $bo;
	protected $so;
	protected $event_ids = [];

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
		$this->so = new \calendar_so();
	}

	protected function tearDown() : void
	{
		foreach($this->event_ids as $id)
		{
			// ensure cleanup, call twice as some tests do keep-deleted behaviour
			$this->bo->delete($id, 0, true);
			$this->bo->delete($id, 0, true);
		}
		// make sure no continuation job is left behind for the real async service to pick up
		(new Api\Asyncservice())->cancel_timer(\calendar_boupdate::PURGE_CONTINUE_ID);

		parent::tearDown();
	}

	/**
	 * Whether a cal_id row still exists in the main calendar table, queried directly.
	 *
	 * calendar_bo::read() is not reliable for this: it keeps a static single-event cache
	 * (self::$cached_event) that a prior read() of the same id (eg. inside create_exception())
	 * will seed, and calendar_so::purge() deletes rows with raw SQL that never invalidates
	 * it - so a post-purge read() can return the stale pre-purge copy instead of reflecting
	 * the DB. Query the row directly to sidestep that entirely.
	 */
	protected function event_exists($cal_id) : bool
	{
		return (bool)$this->so->db->select($this->so->cal_table, 'COUNT(*) AS c', ['cal_id' => $cal_id],
			__LINE__, __FILE__, false, '', 'calendar')->fetchColumn();
	}

	protected function create_event($days_offset)
	{
		$start = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$start->modify($days_offset.' days');
		$end = clone $start;
		$end->modify('+1 hour');

		$id = $this->bo->save([
			'title' => 'Purge test event '.uniqid(),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => $start,
			'end'   => $end,
		]);
		$this->assertGreaterThan(0, $id, 'saved event id should be > 0');
		$this->event_ids[] = $id;

		return $id;
	}

	/**
	 * Create a weekly recurring event.
	 *
	 * @param int $start_days_ago how many days before now the series starts
	 * @param int|null $end_days_ago how many days before now the last occurrence (recur_enddate) is,
	 * 	or null for an unlimited series with no end date
	 */
	protected function create_recurring_event($start_days_ago, $end_days_ago = null)
	{
		$start = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$start->modify('-'.$start_days_ago.' days');
		$start->setTime(9, 0, 0);
		$end = clone $start;
		$end->modify('+1 hour');

		$event = [
			'title' => 'Purge test recurring event '.uniqid(),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => $start,
			'end'   => $end,
			'tzid'  => 'UTC',
			'recur_type' => MCAL_RECUR_WEEKLY,
			'participants' => [$GLOBALS['egw_info']['user']['account_id'] => 'A'],
		];
		if(isset($end_days_ago))
		{
			$recur_end = new Api\DateTime('now', Api\DateTime::$server_timezone);
			$recur_end->modify('-'.$end_days_ago.' days');
			$event['recur_enddate'] = $recur_end;
		}
		$id = $this->bo->save($event);
		$this->assertGreaterThan(0, $id, 'saved recurring event id should be > 0');
		$this->event_ids[] = $id;

		return (int)$id;
	}

	/**
	 * Turn the first occurrence of a recurring series into a detached exception event,
	 * moved a couple hours later but on the same day (same as the calendar UI's
	 * "reschedule a single occurrence" flow, see calendar_uiforms::_create_exception()).
	 */
	protected function create_exception($cal_id, $move_hours = 2)
	{
		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]);	// master row
		$starts = array_map('intval', array_keys($recurrences));
		sort($starts);
		$recur_start_server = $starts[0];

		$occurrence = $this->bo->read($cal_id, Api\DateTime::server2user($recur_start_server));
		$master = $this->bo->read($cal_id);
		$master['recur_exception'][] = clone $occurrence['start'];
		unset($master['start'], $master['end'], $master['alarm']);
		$this->bo->update($master, true);

		$duration = $occurrence['start']->diff($occurrence['end']);
		$expected_start = clone $occurrence['start'];
		$expected_start->modify('+'.$move_hours.' hours');
		$expected_end = clone $expected_start;
		$expected_end->add($duration);

		$exception = $occurrence;
		unset($exception['id']);
		$exception['reference'] = $cal_id;
		$exception['recurrence'] = clone $occurrence['start'];
		$exception['start'] = clone $expected_start;
		$exception['end'] = clone $expected_end;
		$exception['recur_type'] = MCAL_RECUR_NONE;
		foreach(['recur_enddate', 'recur_interval', 'recur_exception', 'recur_data', 'recur_rdates'] as $name)
		{
			unset($exception[$name]);
		}
		$exception_id = (int)$this->bo->save($exception, true);
		$this->assertGreaterThan(0, $exception_id, 'exception event could not be created');
		$this->event_ids[] = $exception_id;

		return $exception_id;
	}

	/**
	 * Pass criteria:
	 * - the event older than the cutoff gets purged
	 * - the event on/after the cutoff is left alone
	 * - purge() reports it finished (false), having processed everything within the time-limit
	 */
	public function testPurgeRespectsCutoff()
	{
		$old_id = $this->create_event(-60);
		$future_id = $this->create_event(60);

		$cutoff = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$cutoff->modify('-30 days');

		$more = $this->so->purge($cutoff, 300);

		$this->assertFalse($more, 'purge() should report it finished within the time-limit');
		$this->assertNull($this->bo->read($old_id), 'event older than cutoff should have been purged');
		$this->assertNotFalse($this->bo->read($future_id), 'event newer than cutoff should be untouched');

		// it's already gone, do not try to delete it again in tearDown
		$this->event_ids = array_diff($this->event_ids, [$old_id]);
	}

	/**
	 * Pass criteria:
	 * - an already-exhausted time-limit stops the loop before touching any row
	 * - purge() reports there is more work left (true)
	 */
	public function testPurgeTimeLimitStopsImmediately()
	{
		$old_id = $this->create_event(-60);

		$cutoff = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$cutoff->modify('-30 days');

		// a negative time-limit is already exceeded before the first row is even checked
		$more = $this->so->purge($cutoff, -1);

		$this->assertTrue($more, 'purge() should report unfinished work when the time-limit is already up');
		$this->assertNotFalse($this->bo->read($old_id), 'event should not have been purged when the time-limit was already exhausted');
	}

	/**
	 * Pass criteria:
	 * - when calendar_so::purge() reports unfinished work, calendar_boupdate::purge()
	 *   schedules an async job to continue purging on the next async tick
	 * - once calendar_so::purge() reports it's done, that continuation job is removed again
	 */
	public function testBoupdatePurgeSchedulesContinuation()
	{
		$async = new Api\Asyncservice();

		$unfinished_so = $this->getMockBuilder(\calendar_so::class)
			->disableOriginalConstructor()
			->onlyMethods(['purge'])
			->getMock();
		$unfinished_so->method('purge')->willReturn(true);
		$this->bo->so = $unfinished_so;

		$this->bo->purge(1);

		$job = $async->read(\calendar_boupdate::PURGE_CONTINUE_ID);
		$this->assertNotFalse($job, 'a continuation job should be scheduled while more purging is left to do');
		$job = $job[\calendar_boupdate::PURGE_CONTINUE_ID];
		$this->assertGreaterThan(time(), $job['next'], 'continuation job should be scheduled for a future tick, not run inline');

		$finished_so = $this->getMockBuilder(\calendar_so::class)
			->disableOriginalConstructor()
			->onlyMethods(['purge'])
			->getMock();
		$finished_so->method('purge')->willReturn(false);
		$this->bo->so = $finished_so;

		$this->bo->purge(1);

		$this->assertFalse($async->read(\calendar_boupdate::PURGE_CONTINUE_ID), 'continuation job should be cancelled once purging is finished');
	}

	/**
	 * Reproduces a user report that purge still deletes too much.
	 *
	 * Goes through the real entrypoint an async job uses (Api\Asyncservice::run()
	 * calls ExecMethod($job['method'], $job['data']), which for the purge
	 * continuation job resolves to calendar_boupdate::purge($age)), instead of
	 * calling calendar_so::purge() directly with a hand-built cutoff like the
	 * other tests. This exercises the actual $age -> cutoff conversion too.
	 *
	 * Pass criteria:
	 * - an event dated today is untouched by a purge of everything older than 1 year
	 * - an event well over a year old is purged
	 */
	public function testPurgeViaAsyncJobRespectsCutoff()
	{
		$today_id = $this->create_event(0);
		$old_id = $this->create_event(-800);	// well over a year old

		// this is exactly how Api\Asyncservice::run() invokes a due continuation job
		ExecMethod('calendar.calendar_boupdate.purge', 1);

		$this->assertNull($this->bo->read($old_id), 'event older than cutoff should have been purged');
		$this->assertNotFalse($this->bo->read($today_id), "today's event should NOT have been purged");

		// it's already gone, do not try to delete it again in tearDown
		$this->event_ids = array_diff($this->event_ids, [$old_id]);
	}

	/**
	 * Reproduces a user report that after a purge, "only recurring events, including
	 * exceptions, are left over" - ie. that recurring series aren't purged even once
	 * every occurrence is before the cutoff.
	 *
	 * Pass criteria:
	 * - a recurring series that finished entirely before the cutoff gets purged
	 * - a recurring series whose last occurrence is after the cutoff is kept (it "spans"
	 *   the cutoff)
	 * - a recurring series with no end date is kept, regardless of how long ago it
	 *   started - by design, see calendar_so::purge()'s docblock: "Recurring events
	 *   that span the date will be ignored"
	 */
	public function testPurgeHandlesRecurringSeries()
	{
		$finished_id = $this->create_recurring_event(150, 120);
		$spanning_id = $this->create_recurring_event(150, 10);
		$infinite_id = $this->create_recurring_event(150, null);

		$cutoff = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$cutoff->modify('-100 days');

		$more = $this->so->purge($cutoff, 300);

		$this->assertFalse($more, 'purge() should report it finished within the time-limit');
		$this->assertNull($this->bo->read($finished_id), 'a recurring series entirely finished before the cutoff should have been purged');
		$this->assertNotFalse($this->bo->read($spanning_id), 'a recurring series whose last occurrence is after the cutoff should be kept');
		$this->assertNotFalse($this->bo->read($infinite_id), 'a recurring series with no end date should never be purged');

		// it's already gone, do not try to delete it again in tearDown
		$this->event_ids = array_diff($this->event_ids, [$finished_id]);
	}

	/**
	 * Pass criteria:
	 * - both the master series AND its exception occurrence get purged, once the whole
	 *   series (including the moved occurrence) is before the cutoff
	 */
	public function testPurgeRemovesFinishedSeriesAndItsException()
	{
		$master_id = $this->create_recurring_event(150, 120);
		$exception_id = $this->create_exception($master_id);

		$cutoff = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$cutoff->modify('-100 days');

		$more = $this->so->purge($cutoff, 300);

		$this->assertFalse($more, 'purge() should report it finished within the time-limit');
		$this->assertFalse($this->event_exists($master_id), 'finished recurring series should have been purged');
		$this->assertFalse($this->event_exists($exception_id), 'exception occurrence of a finished series should have been purged too');

		// they're already gone, do not try to delete them again in tearDown
		$this->event_ids = array_diff($this->event_ids, [$master_id, $exception_id]);
	}
}
