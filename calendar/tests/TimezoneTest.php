<?php

/**
 * Test the recurring event timezone field with different combinations of
 * user timezone, server timezone, and event timezone (on both sides of UTC)
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package calendar
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');	// Application test base

use Egroupware\Api;

class TimezoneTest extends \EGroupware\Api\AppTest {

	protected static $server_tz;

	protected $bo;

	const RECUR_DAYS = 5;

	protected $recur_end;
	protected $cal_id;
	protected $event_ids = array();

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		static::$server_tz = date_default_timezone_get();
	}
	public static function tearDownAfterClass() : void
	{
		date_default_timezone_set(static::$server_tz);

		parent::tearDownAfterClass();
	}

	protected function setUp() : void
	{
		$this->bo = new \calendar_boupdate();

		//$this->mockTracking($this->bo, 'calendar_tracking');
	}

	protected function tearDown() : void
	{
		foreach(array_unique($this->event_ids) as $cal_id)
		{
			$this->bo->delete($cal_id, 0, true);
			// Delete again to remove from delete history
			$this->bo->delete($cal_id, 0, true);
		}
		$this->bo = null;

		// need to call preferences constructor and read_repository, to set user timezone again
		$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_id']);
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);	// no session prefs!

		// Re-load date/time preferences
		Api\DateTime::init();
	}

	/**
	 * Test one combination of event / client / server timezone on a daily recurring
	 * event to make sure it has the correct number of days, and its timezone
	 * stays as set.
	 *
	 * @param Array $timezones Timezone settings for event, client & server
	 * @param Array $times Start & end hours
	 *
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('eventProvider')]
	public function testTimezones($timezones, $times)
	{
		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, $times);

		// Save the event
		$this->cal_id = $this->bo->save($event);
		$this->event_ids[] = $this->cal_id;

		// Check
		$this->checkEvent($timezones, $this->cal_id, $times, $event);
	}

	/**
	 * Test one combination of event / client / server timezone on a daily recurring
	 * all day event to make sure it has the correct number of days, and its timezone
	 * stays as set.
	 *
	 * @param Array $timezones Timezone settings for event, client & server
	 * @param Array $times Start & end hours
	 *
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('eventProvider')]
	public function testTimezonesAllDay($timezones, $times)
	{
		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, $times, true);

		// Save the event
		$this->cal_id = $this->bo->save($event);
		$this->event_ids[] = $this->cal_id;

		// Check
		$this->checkEvent($timezones, $this->cal_id, $times, $event);
	}

	/**
	 * Test that making an exception works correctly, and does not modify the
	 * original series
	 *
	 * @param \EGroupware\calendar\Array $timezones
	 * @param \EGroupware\calendar\Array $times
	 *
	 */

	#[\PHPUnit\Framework\Attributes\DataProvider('eventProvider')]
	public function testException($timezones, $times)
	{
		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, $times);

		// Save the event
		$this->cal_id = $this->bo->save($event);
		$this->event_ids[] = $this->cal_id;

		// Make an exception for the second day
		$start = new Api\DateTime($event['start']);
		$start->modify('+1 day');
		$exception = $event;
		$preserve = array('actual_date', $start->format('ts'));

		$ui = new \calendar_uiforms();
		$ui->_create_exception($exception, $preserve);

		// Move exception 1 hour later
		$exception_start = new Api\DateTime($exception['start']);
		$exception_start->modify('+1 hour');
		$exception['start'] = $exception_start;
		$exception_end = new Api\DateTime($exception['end']);
		$exception_end->modify('+1 hour');
		$exception['end'] = $exception_end;

		$exception_id = $this->bo->save($exception);
		$this->event_ids[] = $exception_id;

		// now we need to add the original start as recur-execption to the series
		$recur_event = $this->bo->read($event['reference']);
		$recur_event['recur_exception'][] = $preserve[1];
		unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
		unset($recur_event['alarm']);	// unsetting alarms too, as they cant be updated without start!
		$this->bo->update($recur_event,true);	// no conflict check here


		// Load the event
		// BO does caching, pass ID as array to avoid it
		$loaded = $this->bo->read(Array($exception_id), null, false, 'server');
		$loaded = $loaded[$exception_id];

		$message = $this->makeMessage($timezones, $loaded);
		// Check exception times
		$expected_exception_start = clone $exception_start;
		$expected_exception_start->setServer();
		$this->assertEquals(
			Api\DateTime::to($expected_exception_start, Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['start'], Api\DateTime::DATABASE),
			'Start date'. $message
		);

		// Check original event
		$this->checkEvent($timezones, $this->cal_id, $times, $event);

	}

	/**
	 * Test generating recurrence label does not mutate event start.
	 */
	public function testRecure2stringDoesNotMutateStart()
	{
		$timezones = array(
			'client' => 'Europe/Berlin',
			'server' => 'UTC',
			'event'  => 'America/Edmonton',
		);
		$this->setTimezones($timezones);

		$event = array(
			'start'           => new Api\DateTime('2026-01-05 08:00:00', Api\DateTime::$user_timezone),
			'end'             => new Api\DateTime('2026-01-05 09:00:00', Api\DateTime::$user_timezone),
			'tzid'            => $timezones['event'],
			'recur_type'      => MCAL_RECUR_DAILY,
			'recur_interval'  => 1,
			'recur_enddate'   => new Api\DateTime('2026-01-08 00:00:00', Api\DateTime::$user_timezone),
			'recur_data'      => 0,
			'recur_exception' => array(),
			'recur_rdates'    => array(),
			'whole_day'       => false,
		);

		$before_start = clone $event['start'];
		$before_start_iso = $before_start->format(Api\DateTime::DATABASE);
		$before_start_tz = $before_start->getTimezone()->getName();

		$this->assertIsString($this->bo->recure2string($event));

		$this->assertEquals(
			$before_start_iso,
			$event['start']->format(Api\DateTime::DATABASE),
			'recure2string() changed start timestamp'
		);
		$this->assertSame(
			$before_start_tz,
			$event['start']->getTimezone()->getName(),
			'recure2string() changed start timezone'
		);
	}

	/**
	 * Load the event and check that it matches expectations
	 *
	 * @param Array $timezones List of timezones (event, client, server)
	 * @param int $cal_id
	 * @param Array $times start and end times (just hours)
	 */
	protected function checkEvent($timezones, $cal_id, $times, $event)
	{
		// Load the event
		// BO does caching, pass ID as array to avoid it
		$loaded = $this->bo->read(Array($cal_id), null, false, 'server');
		$loaded = $loaded[$cal_id];

		$message = $this->makeMessage($timezones, $loaded);
		if($loaded['whole_day'])
		{
			// Whole-day events are normalized to server day boundaries in save().
			$expected_start = new Api\DateTime($event['start'], Api\DateTime::$user_timezone);
			$expected_start = new Api\DateTime($expected_start->format('Y-m-d 00:00:00'), Api\DateTime::$server_timezone);

			$expected_end = new Api\DateTime($event['end'], Api\DateTime::$user_timezone);
			$expected_end->modify('+60 seconds');
			$expected_end = new Api\DateTime($expected_end->format('Y-m-d 00:00:00'), Api\DateTime::$server_timezone);
			$expected_end->modify('-1 second');
		}
		else
		{
			$expected_start = $event['start'] instanceof Api\DateTime ?
				clone $event['start'] : new Api\DateTime($event['start'], Api\DateTime::$server_timezone);
			$expected_end = $event['end'] instanceof Api\DateTime ?
				clone $event['end'] : new Api\DateTime($event['end'], Api\DateTime::$server_timezone);
			$expected_start->setServer();
			$expected_end->setServer();
		}

		// Check that the start date is the same (user time)
		$this->assertEquals(
			Api\DateTime::to($expected_start, Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['start'], Api\DateTime::DATABASE),
			'Start date'. $message
		);

		// Check that the end date is the same (user time)
		$this->assertEquals(
			Api\DateTime::to($expected_end, Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['end'], Api\DateTime::DATABASE),
			'End date'. $message
		);

		// Check event recurring timezone is unchanged
		$this->assertEquals($timezones['event'], $loaded['tzid'], 'Timezone' . $message);

		// Check recurring end date is unchanged (user time)
		$expected_end = $event['recur_enddate'] instanceof Api\DateTime ?
			clone $event['recur_enddate'] : new Api\DateTime($event['recur_enddate'], Api\DateTime::$server_timezone);
		$loaded_end = new Api\DateTime($loaded['recur_enddate']);
		$compare_tz = $loaded['whole_day'] ? new \DateTimeZone($timezones['event']) : Api\DateTime::$user_timezone;
		$expected_end->setTimezone($compare_tz);
		$loaded_end->setTimezone($compare_tz);
		$this->assertEquals($expected_end->format('Ymd'), $loaded_end->format('Ymd'), 'Recur end date' . $message);

		// Recurrences
		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]);
		$this->assertEquals(static::RECUR_DAYS, count($recurrences), 'Recurrence count' . $message);
		$expected_recur_time = null;
		foreach($recurrences as $recur_start_time => $participant)
		{
			$recur_time = $loaded['whole_day'] ? '00:00:00' : Api\DateTime::to(Api\DateTime::server2user($recur_start_time), 'H:i:s');
			if($expected_recur_time === null)
			{
				$expected_recur_time = $recur_time;
			}
			$this->assertEquals(
				$expected_recur_time,
				$recur_time,
					'Recurrence start time' . $message
			);
		}
	}

	/**
	 * Provide an event for checking, along with a list of timezones
	 */
	public static function eventProvider()
	{
		$tests = array();
		$tz_combos = static::makeTZCombos();

		// Start times to test (hour of the day), 1 chosen to cross days
		$times = array(1, 9);

		foreach($tz_combos as $timezones)
		{
			foreach($times as $start_time)
			{
				$tests[] = Array($timezones,
					Array(
						'start' => $start_time,
						'end' => $start_time + 1
					)
				);
			}
		}

		return $tests;
	}

	/**
	 * Make a map of all the different client / server / event combinations
	 * that we'll use.
	 */
	protected static function makeTZCombos()
	{
		// Timezone list
		$tz_list = Array(
			'Pacific/Tahiti',	// -10
			'Europe/Berlin',	//  +2
			// The first 2 are usually sufficient
			//'America/Edmonton',	//  -8
			//'Pacific/Auckland',	// +12
			'UTC',
			// Half-hour timezones to catch edge-cases
			//'Australia/Adelaide',  // +9:30
			//'Asia/Kolkata',        // +5:30
			'America/St_Johns'     // -3:30
		);
		$tz_combos = Array();

		// Pick some timezones to use - every combination from the list
		$client_index = $server_index = $event_index = 0;
		do {
			$tz_combos[] = array(
				'client' => $tz_list[$client_index],
				'server'	=> $tz_list[$server_index],
				'event'		=> $tz_list[$event_index]
			);
			$client_index++;
			if($client_index > count($tz_list)-1)
			{
				$server_index++;
				$client_index = 0;
			}
			if($server_index > count($tz_list)-1)
			{
				$event_index++;
				$server_index = 0;
			}
		} while ($event_index < count($tz_list));

		/* one specific test 
		$tz_combos = array(array(
			'client'	=> 'Europe/Berlin',
			'server'	=> 'Pacific/Tahiti',
			'event'		=> 'Pacific/Tahiti'
		));
		// */
		return $tz_combos;
	}

	/**
	 * Make the array of event information
	 *
	 * @param Array $timezones
	 * @return Array Event array, unsaved.
	 * @param boolean $whole_day
	 */
	protected function makeEvent($timezones, $times, $whole_day = false)
	{
		// Preserve legacy test semantics from mktime()+timestamp flow:
		// use server calendar date, but interpret wall time in user timezone.
		$server_start_day = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$server_start_day->setTime(0, 0, 0);
		$server_start_day->modify('+1 day');
		$start = new Api\DateTime(
			$server_start_day->format('Y-m-d') . ' ' . sprintf('%02d:00:00', $whole_day ? 0 : $times['start']),
			Api\DateTime::$user_timezone
		);

		$end = clone $start;
		if($whole_day)
		{
			$end->modify('+1 day');
			$end->modify('-1 second');
		}
		else
		{
			$end->setTime($times['end'], 0, 0);
		}
		$server_recur_end_day = new Api\DateTime('now', Api\DateTime::$server_timezone);
		$server_recur_end_day->setTime(0, 0, 0);
		$server_recur_end_day->modify('+' . static::RECUR_DAYS . ' days');
		$this->recur_end = new Api\DateTime(
			$server_recur_end_day->format('Y-m-d') . ' 00:00:00',
			Api\DateTime::$user_timezone
		);

		$event = array(
			'title' => ($whole_day ? 'Whole day ' : '')."Test for " . $this->tzString($timezones),
			'description'   => ($whole_day ? 'Whole day ' : '').'Test for test ' . $this->name() . ' ' . $this->tzString($timezones),
			'start'         => $start,
			'end'           => $end,
			'tzid'	=> $timezones['event'],
			'recur_type'	=> 1, // MCAL_RECUR_DAILY
			'recur_enddate' => clone $this->recur_end,
			'whole_day'		=> $whole_day,
			'participants'	=> array(
				$GLOBALS['egw_info']['user']['account_id'] => 'A'
			)
		);
		return $event;
	}

	protected function makeMessage($timezones, $event)
	{
		return ' ' . ($event['id'] ? '[#'.$event['id'] .'] ' : '') . Api\DateTime::to($event['recur_enddate'], Api\DateTime::DATABASE) . ' '.
				($event['whole_day'] ? '(whole day) ' : '') . $this->tzString($timezones);
	}

	/**
	 * Set the current client & server timezones as given
	 *
	 * @param Array $timezones
	 */
	protected function setTimezones($timezones)
	{
		// Set the client preference & server preference
		$GLOBALS['egw_info']['server']['server_timezone'] = $timezones['server'];
		$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $timezones['client'];

		// Keep PHP date/time functions aligned to server timezone for consistency.
		date_default_timezone_set($timezones['server']);

		// Load date/time preferences into egw_time
		Api\DateTime::init();
	}

	/**
	 * Make a nice string for the timezone combination we're using
	 *
	 * @param Array $timezones
	 */
	protected function tzString($timezones)
	{
		return "[Event: {$timezones['event']} Client: {$timezones['client']} Server: {$timezones['server']}]";
	}
}
