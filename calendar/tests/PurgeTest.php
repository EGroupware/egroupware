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
}
