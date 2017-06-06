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

require_once realpath(__DIR__.'/../../api/src/test/AppTest.php');	// Application test base

use Egroupware\Api;

class TimezoneTest extends \EGroupware\Api\AppTest {

	protected $bo;

	// TODO: Do this at different times, at least 12 hours apart
	const START_TIME = 9;
	const END_TIME = 10;
	const RECUR_DAYS = 5;

	protected $recur_end;
	protected $cal_id;

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();
	}
	public static function tearDownAfterClass()
	{
		parent::tearDownAfterClass();
	}
	
	public function setUp()
	{
		$this->bo = new \calendar_boupdate();

		//$this->mockTracking($this->bo, 'calendar_tracking');

		$this->recur_end = new Api\DateTime(mktime(0,0,0,date('m'), date('d') + static::RECUR_DAYS, date('Y')));
		echo "End date: " . $this->recur_end->format('Y-m-d') . '(Event time)';
	}

	public function tearDown()
	{
		//$this->bo->delete($this->cal_id);
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
	 * @param type $timezones
	 * 
	 * @dataProvider eventProvider
	 */
	public function testTimezones($timezones)
	{

		echo $this->tzString($timezones)."\n";

		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones);


		// Save the event
		$this->cal_id = $this->bo->save($event);

		// Check
		$this->checkEvent($timezones, $this->cal_id);
	}

	/**
	 * Test one combination of event / client / server timezone on a daily recurring
	 * all day event to make sure it has the correct number of days, and its timezone
	 * stays as set.
	 *
	 * @param type $timezones
	 *
	 * @dataProvider eventProvider
	 */
	public function notestTimezonesAllDay($timezones)
	{
		echo $this->tzString($timezones)."\n";

		$this->setTimezones($timezones);

		$event = $this->makeEvent($timezones, true);


		// Save the event
		$this->cal_id = $this->bo->save($event);

		// Check
		$this->checkEvent($timezones, $this->cal_id);
	}

	protected function checkEvent($timezones, $cal_id)
	{
		// Load the event
		// BO does caching, need array to avoid it
		$loaded = $this->bo->read(Array($cal_id));
		$loaded = $loaded[$cal_id];

		$message = $this->makeMessage($timezones, $loaded);

		// Check that the start date is the same (user time)
		$this->assertEquals(
			Api\DateTime::to(\mktime($loaded['whole_day'] ? 0 : static::START_TIME, 0, 0, date('m'), date('d')+1, date('Y')), Api\DateTime::DATABASE),
			Api\DateTime::to($loaded['start'], Api\DateTime::DATABASE),
			'Start date'. $message
		);

		// Check that the end date is the same (user time)
		$this->assertEquals(
			Api\DateTime::to(
					$loaded['whole_day'] ? \mktime(0, 0, 0, date('m'), date('d')+2, date('Y'))-1 :
					\mktime(static::END_TIME, 0, 0, date('m'), date('d')+1, date('Y')
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
		$this->assertEquals(static::RECUR_DAYS, count($recurrences)-1, 'Recurrence count' . $message);
	}

	/**
	 * Provide an event for checking, along with a list of timezones
	 */
	public function eventProvider()
	{
		$tests = array();
		$tz_combos = $this->makeCombos();

		foreach($tz_combos as $timezones)
		{
			$tests[] = Array($timezones);
		}

		return $tests;
	}

	/**
	 * Make a map of all the different client / server / event combinations
	 * that we'll use.
	 */
	protected function makeCombos()
	{
		// Timezone list
		$tz_list = Array(
			'Pacific/Tahiti',	// -10
			'America/Edmonton',	//  -8
			'Europe/Berlin',	//  +2
			'Pacific/Auckland',	// +12
			'UTC'
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
	 * @param boolean $whole_day
	 * @return Array Event array, unsaved.
	 */
	protected function makeEvent($timezones, $whole_day = false)
	{
		$event = array(
			'title' => ($whole_day ? 'Whole day ' : '')."Test for " . $this->tzString($timezones),
			'des'   => ($whole_day ? 'Whole day ' : '').'Test for test ' . $this->getName() . ' ' . $this->tzString($timezones),
			'start' => \mktime(static::START_TIME, 0, 0, date('m'), date('d')+1, date('Y')),
			'end'   => \mktime(static::END_TIME, 0, 0, date('m'), date('d')+1, date('Y')),
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
		return ' ' . Api\DateTime::to($event['recur_enddate'], Api\DateTime::DATABASE) . ' '.
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
