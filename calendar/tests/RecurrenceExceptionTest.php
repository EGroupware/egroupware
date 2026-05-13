<?php
/**
 * Unit tests for recurrence exceptions through calendar BO (no CalDAV)
 *
 * @package calendar
 * @subpackage tests
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

use EGroupware\Api;

class RecurrenceExceptionTest extends \EGroupware\Api\AppTest
{
	/**
	 * @var \calendar_boupdate
	 */
	protected $bo;
	protected $ui;

	/**
	 * @var int[]
	 */
	protected $event_ids = [];
	protected $account_ids = [];

	protected static $orig_date_tz;

	/**
	 * Preserve current default timezone for this test class.
	 */
	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
		self::$orig_date_tz = date_default_timezone_get();
	}

	/**
	 * Restore default timezone after all tests in this class.
	 */
	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(self::$orig_date_tz);
		parent::tearDownAfterClass();
	}

	/**
	 * Initialize BO/UI objects and force deterministic UTC timezone context.
	 */
	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \calendar_boupdate();
		$this->ui = new \calendar_uiforms();
		$this->setTimezones('UTC', 'UTC');
	}

	/**
	 * Remove created events and temporary users after each test.
	 */
	protected function tearDown() : void
	{
		foreach(array_unique($this->event_ids) as $id)
		{
			$this->bo->delete($id, 0, true);
			$this->bo->delete($id, 0, true);
		}
		foreach($this->account_ids as $account_id)
		{
			$GLOBALS['egw']->accounts->delete($account_id);
		}
		parent::tearDown();
	}

	/**
	 * Set client/server timezone context for recurrence assertions.
	 */
	protected function setTimezones(string $client, string $server) : void
	{
		$GLOBALS['egw_info']['server']['server_timezone'] = $server;
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $client;
		date_default_timezone_set($server);
		Api\DateTime::init();
	}

	/**
	 * Create a daily recurring event with current user as accepted participant.
	 */
	protected function createDailyRecurringEvent() : int
	{
		$start = new Api\DateTime('now');
		$start->modify('+1 day');
		$start->setTime(9, 0, 0);
		$end = clone $start;
		$end->modify('+1 hour');
		$recur_end = clone $start;
		$recur_end->modify('+7 days');
		$recur_end->setTime(0, 0, 0);

		$event = [
			'title' => 'Recurrence exception unit test '.uniqid(),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => $start,
			'end' => $end,
			'tzid' => 'UTC',
			'recur_type' => MCAL_RECUR_DAILY,
			'recur_enddate' => $recur_end,
			'participants' => [
				$GLOBALS['egw_info']['user']['account_id'] => 'A',
			],
		];

		$id = $this->bo->save($event);
		$this->assertGreaterThan(0, $id, 'Recurring event could not be created');
		$this->event_ids[] = (int)$id;

		return (int)$id;
	}

	/**
	 * Create a daily recurring event with explicit participants.
	 *
	 * @param array $participants participant map uid => status
	 * @return int created event id
	 */
	protected function createDailyRecurringEventWithParticipants(array $participants) : int
	{
		$start = new Api\DateTime('now');
		$start->modify('+1 day');
		$start->setTime(9, 0, 0);
		$end = clone $start;
		$end->modify('+1 hour');
		$recur_end = clone $start;
		$recur_end->modify('+7 days');
		$recur_end->setTime(0, 0, 0);

		$event = [
			'title' => 'Recurrence participant create test '.uniqid(),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'start' => $start,
			'end' => $end,
			'tzid' => 'UTC',
			'recur_type' => MCAL_RECUR_DAILY,
			'recur_enddate' => $recur_end,
			'participants' => $participants,
		];

		$id = $this->bo->save($event);
		$this->assertGreaterThan(0, $id, 'Recurring event with participants could not be created');
		$this->event_ids[] = (int)$id;

		return (int)$id;
	}

	/**
	 * Create an exception event for one recurrence, optionally moved by days/hours.
	 */
	protected function createExceptionForRecurrence(int $cal_id, int $recur_start_server, int $move_hours = 2, int $move_days = 0) : int
	{
		// Load the selected occurrence from the master series.
		$occurrence = $this->bo->read($cal_id, Api\DateTime::server2user($recur_start_server));
		$this->assertIsArray($occurrence, 'Unable to read selected recurrence');

		// Mark the original slot as an exception on the master event.
		$master = $this->bo->read($cal_id);
		$master['recur_exception'][] = clone $occurrence['start'];
		unset($master['start'], $master['end'], $master['alarm']);
		$this->assertNotFalse($this->bo->update($master, true), 'Unable to add recurrence exception to master');

		$duration = $occurrence['start']->diff($occurrence['end']);
		$expected_start = clone $occurrence['start'];
		if($move_days)
		{
			$expected_start->modify($move_days > 0 ? '+' . $move_days . ' day' : $move_days . ' day');
		}
		$expected_start->modify($move_hours > 0 ? '+' . $move_hours . ' hour' : $move_hours . ' hour');
		$expected_end = clone $expected_start;
		$expected_end->add($duration);

		// Store a detached single event representing the moved occurrence.
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
		$this->assertGreaterThan(0, $exception_id, 'Exception event could not be created');
		$this->event_ids[] = $exception_id;

		return $exception_id;
	}

	/**
	 * Create a temporary secondary account for participant propagation tests.
	 */
	protected function createSecondaryUser() : int
	{
		// Create a dedicated participant account for tests that require "another user".
		$account = [
			'account_lid'       => 'recur_test_' . uniqid(),
			'account_firstname' => 'Recur',
			'account_lastname'  => 'Participant',
		];
		$command = new \admin_cmd_edit_user(false, $account);
		$command->comment = 'Needed for unit test ' . $this->name();
		$command->run();
		$account_id = (int)$command->account;
		$this->assertGreaterThan(0, $account_id, 'Unable to create secondary user for test');
		$this->account_ids[] = $account_id;
		return $account_id;
	}

	/**
	 * Return sorted recurrence start timestamps for a series, excluding master row.
	 */
	protected function recurrenceStarts(int $cal_id) : array
	{
		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]); // master row
		$starts = array_map('intval', array_keys($recurrences));
		sort($starts);
		return $starts;
	}

	/**
	 * Assert that a given date/time exists in a recurrence-exception list.
	 */
	protected function assertDateInList(array $dates, Api\DateTime $expected, string $message='') : void
	{
		$expected_ts = Api\DateTime::to($expected, 'ts');
		foreach($dates as $date)
		{
			if((int)Api\DateTime::to($date, 'ts') === (int)$expected_ts)
			{
				$this->assertTrue(true);
				return;
			}
		}
		$this->fail($message ?: 'Expected date not found in recurrence exception list');
	}

	/**
	 * Deleting one occurrence from a series stores its start as a recurrence exception.
	 */
	public function testDeleteSingleInstanceAddsRecurrenceException()
	{
		$cal_id = $this->createDailyRecurringEvent();
		$before = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(3, count($before), 'Expected at least 3 generated recurrences');

		$recur_start_server = $before[1];
		$recur_start_user = new Api\DateTime(
			Api\DateTime::server2user($recur_start_server),
			Api\DateTime::$user_timezone
		);

		$this->assertTrue(
			$this->bo->delete($cal_id, $recur_start_user, true, true),
			'Deleting single recurrence failed'
		);

		$master = $this->bo->read($cal_id);
		$this->assertIsArray($master);
		$this->assertIsArray($master['recur_exception']);
		$this->assertDateInList(
			$master['recur_exception'],
			$recur_start_user,
			'Deleted instance start is not listed as recur_exception on master'
		);

		$after = $this->recurrenceStarts($cal_id);
		$this->assertCount(count($before) - 1, $after, 'Single recurrence delete should remove exactly one recurrence row');
		$this->assertNotContains($recur_start_server, $after, 'Deleted recurrence start is still present');
	}

	/**
	 * Rescheduling one occurrence creates a detached exception event and keeps other recurrences unchanged.
	 */
	public function testRescheduleSingleInstanceCreatesExceptionEvent()
	{
		$cal_id = $this->createDailyRecurringEvent();
		$before = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(3, count($before), 'Expected at least 3 generated recurrences');

		$recur_start_server = $before[1];
		$recur_start_user = Api\DateTime::server2user($recur_start_server);
		$occurrence = $this->bo->read($cal_id, $recur_start_user);
		$this->assertIsArray($occurrence, 'Unable to read selected recurrence');

		$master = $this->bo->read($cal_id);
		$master['recur_exception'][] = clone $occurrence['start'];
		unset($master['start'], $master['end'], $master['alarm']);
		$this->assertNotFalse($this->bo->update($master, true), 'Unable to add recurrence exception to master');

		$duration = $occurrence['start']->diff($occurrence['end']);
		$expected_start = clone $occurrence['start'];
		$expected_start->modify('+2 hours');
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

		$exception_id = $this->bo->save($exception, true);
		$this->assertGreaterThan(0, $exception_id, 'Exception event could not be created');
		$this->event_ids[] = (int)$exception_id;

		$loaded_exception = $this->bo->read((int)$exception_id);
		$this->assertIsArray($loaded_exception, 'Saved exception event could not be read');
		$this->assertEquals($cal_id, (int)$loaded_exception['reference'], 'Exception reference should point to master event');
		$this->assertEquals(
			(int)Api\DateTime::to($occurrence['start'], 'ts'),
			(int)Api\DateTime::to($loaded_exception['recurrence'], 'ts'),
			'Exception recurrence should match original occurrence start'
		);
		$this->assertEquals(
			(int)Api\DateTime::to($expected_start, 'ts'),
			(int)Api\DateTime::to($loaded_exception['start'], 'ts'),
			'Exception start is not rescheduled as expected'
		);
		$this->assertEquals(
			(int)Api\DateTime::to($expected_end, 'ts'),
			(int)Api\DateTime::to($loaded_exception['end'], 'ts'),
			'Exception end is not rescheduled as expected'
		);

		$after = $this->recurrenceStarts($cal_id);
		$this->assertCount(count($before) - 1, $after, 'Master recurrences should exclude the moved occurrence');
		$this->assertNotContains($recur_start_server, $after, 'Original recurrence still exists on master after reschedule');

		// Ensure other recurrence starts are unchanged (only the moved occurrence removed)
		$expected = $before;
		foreach ($expected as $k => $v) {
			if ($v === $recur_start_server) {
				unset($expected[$k]);
				break;
			}
		}
		$expected = array_values($expected);
		sort($expected);
		$sorted_after = $after;
		sort($sorted_after);
		$this->assertEquals($expected, $sorted_after, 'Other recurrence starts were unexpectedly changed');
	}

	/**
	 * Creating a recurring event with participants stores both recurrence and attendees.
	 */
	public function testCreateRecurringEventWithParticipants()
	{
		$owner = $GLOBALS['egw_info']['user']['account_id'];
		$participant = $this->createSecondaryUser();

		$cal_id = $this->createDailyRecurringEventWithParticipants([
			$owner => 'A',
			$participant => 'U',
		]);
		$event = $this->bo->read($cal_id, null, true, 'server');
		$this->assertIsArray($event, 'Created recurring event could not be read');
		$this->assertNotEquals(MCAL_RECUR_NONE, $event['recur_type'], 'Event should be recurring');
		$this->assertArrayHasKey($owner, $event['participants'], 'Owner participant is missing');
		$this->assertArrayHasKey($participant, $event['participants'], 'Additional participant is missing');
		$this->assertSame('A', $event['participants'][$owner][0], 'Owner status mismatch');
		$this->assertSame('U', $event['participants'][$participant][0], 'Additional participant status mismatch');

		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThan(0, count($starts), 'Expected generated recurrences for participant series');
	}

	/**
	 * Recurrence / exception participant-state coverage.
	 *
	 * These tests verify:
	 * - status changes can be scoped to individual recurrences
	 * - exception creation supports shifted date/time
	 * - status changes inside an exception do not leak back into series recurrences
	 * - adding participants to a series can be propagated to existing exceptions
	 * - without explicit propagation, existing exceptions remain unchanged
	 */
	/**
	 * Participant status changes can be scoped to specific recurrences in the same series.
	 */
	public function testChangeStatusOfParticipantsInDifferentRecurrences()
	{
		$participant = $GLOBALS['egw_info']['user']['account_id'];
		$cal_id = $this->createDailyRecurringEvent();

		// Add participant to the recurring master with default unknown status.
		$master = $this->bo->read($cal_id);
		$master['participants'][$participant] = 'U';
		unset($master['start'], $master['end'], $master['alarm']);
		$this->assertNotFalse($this->bo->update($master, true), 'Unable to add participant to recurring event');

		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(3, count($starts), 'Expected at least 3 recurrences');

		// Change only first and second recurrence and keep third untouched.
		$this->assertGreaterThan(
			0,
			$this->bo->set_status($cal_id, $participant, 'A', Api\DateTime::server2user($starts[0]), true, true, true),
			'Changing participant status on first recurrence failed'
		);
		$this->assertGreaterThan(
			0,
			$this->bo->set_status($cal_id, $participant, 'R', Api\DateTime::server2user($starts[1]), true, true, true),
			'Changing participant status on second recurrence failed'
		);

		$first = $this->bo->read($cal_id, Api\DateTime::server2user($starts[0]), true, 'server');
		$second = $this->bo->read($cal_id, Api\DateTime::server2user($starts[1]), true, 'server');
		$third = $this->bo->read($cal_id, Api\DateTime::server2user($starts[2]), true, 'server');

		$this->assertSame('A', $first['participants'][$participant][0], 'First recurrence status mismatch');
		$this->assertSame('R', $second['participants'][$participant][0], 'Second recurrence status mismatch');
		$this->assertSame('U', $third['participants'][$participant][0], 'Third recurrence should keep default status');
	}

	/**
	 * Creating an exception can move the occurrence to a different date and time.
	 */
	public function testCreateExceptionWithDifferentDateAndTime()
	{
		$cal_id = $this->createDailyRecurringEvent();
		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(3, count($starts), 'Expected at least 3 recurrences');

		// Move one occurrence to a different day and hour.
		$exception_id = $this->createExceptionForRecurrence($cal_id, $starts[1], 3, 1);
		$exception = $this->bo->read($exception_id);
		$this->assertIsArray($exception, 'Saved exception event could not be read');

		$recurrence_day = (new Api\DateTime($exception['recurrence']))->format('Y-m-d');
		$exception_day = (new Api\DateTime($exception['start']))->format('Y-m-d');
		$this->assertNotSame($recurrence_day, $exception_day, 'Exception date should be different from original recurrence date');
		$this->assertSame('12:00', (new Api\DateTime($exception['start']))->format('H:i'), 'Exception time should be shifted');
	}

	/**
	 * Changing participant status in an exception must not affect series recurrences.
	 */
	public function testChangeStatusOfParticipantInException()
	{
		$participant = $GLOBALS['egw_info']['user']['account_id'];
		$cal_id = $this->createDailyRecurringEvent();

		// Ensure participant has an explicit baseline status in the series.
		$master = $this->bo->read($cal_id);
		$master['participants'][$participant] = 'U';
		unset($master['start'], $master['end'], $master['alarm']);
		$this->assertNotFalse($this->bo->update($master, true), 'Unable to add participant to recurring event');

		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(2, count($starts), 'Expected at least 2 recurrences');
		$exception_id = $this->createExceptionForRecurrence($cal_id, $starts[1]);

		// Update status on the detached exception only.
		$this->assertGreaterThan(
			0,
			$this->bo->set_status($exception_id, $participant, 'A', 0, true, true, true),
			'Changing participant status on exception failed'
		);

		$exception = $this->bo->read($exception_id, null, true, 'server');
		$occurrence = $this->bo->read($cal_id, Api\DateTime::server2user($starts[2]), true, 'server');

		$this->assertSame('A', $exception['participants'][$participant][0], 'Exception participant status mismatch');
		$this->assertSame('U', $occurrence['participants'][$participant][0], 'Status change in exception must not alter series recurrences');
	}

	/**
	 * Adding participants to the series is propagated when apply_changes_to_exceptions is used.
	 */
	public function testAddParticipantsToSeriesCanBeAppliedToExistingExceptions()
	{
		$participant = $this->createSecondaryUser();
		$cal_id = $this->createDailyRecurringEvent();
		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(2, count($starts), 'Expected at least 2 recurrences');

		// Prepare an existing exception before participant is added to master.
		$exception_id = $this->createExceptionForRecurrence($cal_id, $starts[1]);
		$exception_before = $this->bo->read($exception_id, null, true, 'server');
		$this->assertArrayNotHasKey($participant, $exception_before['participants'], 'Participant should not exist in exception before series update');

		// Simulate "apply changes to exceptions" behavior from the UI layer.
		$master = $this->bo->read($cal_id, null, true, 'server');
		$master['participants'][$participant] = 'U';
		$method = new \ReflectionMethod($this->ui, 'apply_changes_to_exceptions');
		$method->setAccessible(true);
		$method->invoke($this->ui, $master, [], true);

		$exception_after = $this->bo->read($exception_id, null, true, 'server');
		$this->assertArrayHasKey($participant, $exception_after['participants'], 'Participant should be copied to exception when applying changes');
	}

	/**
	 * Adding participants to the series alone must not modify already existing exceptions.
	 */
	public function testAddParticipantsToSeriesDoesNotChangeExistingExceptionsByDefault()
	{
		$participant = $this->createSecondaryUser();
		$cal_id = $this->createDailyRecurringEvent();
		$starts = $this->recurrenceStarts($cal_id);
		$this->assertGreaterThanOrEqual(2, count($starts), 'Expected at least 2 recurrences');

		// Prepare exception first, then update master participants without propagation step.
		$exception_id = $this->createExceptionForRecurrence($cal_id, $starts[1]);
		$master = $this->bo->read($cal_id);
		$master['participants'][$participant] = 'U';
		unset($master['start'], $master['end'], $master['alarm']);
		$this->assertNotFalse($this->bo->update($master, true), 'Unable to update series participants');

		$exception = $this->bo->read($exception_id, null, true, 'server');
		$this->assertArrayNotHasKey(
			$participant,
			$exception['participants'],
			'Series participant update without apply-to-exceptions should not alter existing exceptions'
		);
	}
}
