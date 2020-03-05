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

		$this->recur_end = new Api\DateTime(mktime(0,0,0,date('m'), date('d') + static::RECUR_DAYS, date('Y')));
	}

	protected function tearDown() : void
	{
		$this->bo->delete($this->cal_id);
		// Delete again to remove from delete history
		$this->bo->delete($this->cal_id);
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
	 * @dataProvider eventProvider
	 */
	public function testTimezones($timezones, $times)
	{
		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, $times);

		// Save the event
		$this->cal_id = $this->bo->save($event);

		// Check
		$this->checkEvent($timezones, $this->cal_id, $times);
	}

	/**
	 * Test one combination of event / client / server timezone on a daily recurring
	 * all day event to make sure it has the correct number of days, and its timezone
	 * stays as set.
	 *
	 * @param Array $timezones Timezone settings for event, client & server
	 * @param Array $times Start & end hours
	 *
	 * @dataProvider eventProvider
	 */
	public function testTimezonesAllDay($timezones, $times)
	{
		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, $times, true);

		// Save the event
		$this->cal_id = $this->bo->save($event);

		// Check
		$this->checkEvent($timezones, $this->cal_id, $times);
	}

	/**
	 * Test that making an exception works correctly, and does not modify the
	 * original series
	 *
	 * @param \EGroupware\calendar\Array $timezones
	 * @param \EGroupware\calendar\Array $times
	 *
	 * @dataProvider eventProvider
	 */

	public function testException($timezones, $times)
	{
		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, $times);

		// Save the event
		$this->cal_id = $this->bo->save($event);

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
		$exception['start'] = $exception_start->format('ts');
		$exception['end'] += 3600;

		$exception_id = $this->bo->save($exception);

		// now we need to add the original start as recur-execption to the series
		$recur_event = $this->bo->read($event['reference']);
		$recur_event['recur_exception'][] = $content['edit_single'];
		unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
		unset($recur_event['alarm']);	// unsetting alarms too, as they cant be updated without start!
		$this->bo->update($recur_event,true);	// no conflict check here


		// Load the event
		// BO does caching, pass ID as array to avoid it
		$loaded = $this->bo->read(Array($exception_id));
		$loaded = $loaded[$exception_id];

		$message = $this->makeMessage($timezones, $loaded);
		// Check exception times
		$this->assertEquals(
			Api\DateTime::to($exception_start, Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['start'], Api\DateTime::DATABASE),
			'Start date'. $message
		);

		// Check original event
		$this->checkEvent($timezones, $this->cal_id, $times);

		// Clean up exception
		$this->bo->delete($exception_id);
	}

	/**
	 * Load the event and check that it matches expectations
	 *
	 * @param Array $timezones List of timezones (event, client, server)
	 * @param int $cal_id
	 * @param Array $times start and end times (just hours)
	 */
	protected function checkEvent($timezones, $cal_id, $times)
	{
		// Load the event
		// BO does caching, pass ID as array to avoid it
		$loaded = $this->bo->read(Array($cal_id));
		$loaded = $loaded[$cal_id];

		$message = $this->makeMessage($timezones, $loaded);

		$start_time = \mktime($loaded['whole_day'] ? 0 : $times['start'], 0, 0, date('m'), date('d')+1, date('Y'));

		// Check that the start date is the same (user time)
		$this->assertEquals(
			Api\DateTime::to($start_time, Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['start'], Api\DateTime::DATABASE),
			'Start date'. $message
		);

		// Check that the end date is the same (user time)
		$this->assertEquals(
			Api\DateTime::to(
					$loaded['whole_day'] ? \mktime(0, 0, 0, date('m'), date('d')+2, date('Y'))-1 :
					\mktime($times['end'], 0, 0, date('m'), date('d')+1, date('Y')
			), Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['end'], Api\DateTime::DATABASE),
			'End date'. $message
		);

		// Check event recurring timezone is unchanged
		$this->assertEquals($timezones['event'], $loaded['tzid'], 'Timezone' . $message);

		// Check recurring end date is unchanged (user time)
		$loaded_end = new Api\DateTime($loaded['recur_enddate']);
		$this->assertEquals($this->recur_end->format('Ymd'), $loaded_end->format('Ymd'), 'Recur end date' . $message);

		// Recurrences
		$so = new \calendar_so();
		$recurrences = $so->get_recurrences($cal_id);
		unset($recurrences[0]);
		$this->assertEquals(static::RECUR_DAYS, count($recurrences), 'Recurrence count' . $message);
		foreach($recurrences as $recur_start_time => $participant)
		{
			$this->assertEquals(
					Api\DateTime::to($start_time, 'H:i:s'),
					$loaded['whole_day'] ? '00:00:00' : Api\DateTime::to(Api\DateTime::server2user($recur_start_time), 'H:i:s'),
					'Recurrence start time' . $message
			);
		}
	}

	/**
	 * Provide an event for checking, along with a list of timezones
	 */
	public function eventProvider()
	{
		$tests = array();
		$tz_combos = $this->makeTZCombos();

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
	protected function makeTZCombos()
	{
		// Timezone list
		$tz_list = Array(
			'Pacific/Tahiti',	// -10
			'Europe/Berlin',	//  +2
			// The first 2 are usually sufficient
			//'America/Edmonton',	//  -8
			//'Pacific/Auckland',	// +12
			//'UTC'
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
		$event = array(
			'title' => ($whole_day ? 'Whole day ' : '')."Test for " . $this->tzString($timezones),
			'description'   => ($whole_day ? 'Whole day ' : '').'Test for test ' . $this->getName() . ' ' . $this->tzString($timezones),
			'start' => \mktime($whole_day ? 0 : $times['start'], 0, 0, date('m'), date('d')+1, date('Y')),
			'end'   => $whole_day ? \mktime(23, 59, 59, date('m'), date('d')+1, date('Y')) : \mktime($times['end'], 0, 0, date('m'), date('d')+1, date('Y')),
			'tzid'	=> $timezones['event'],
			'recur_type'	=> 1, // MCAL_RECUR_DAILY
			'recur_enddate'	=> $this->recur_end->format('ts'),
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
