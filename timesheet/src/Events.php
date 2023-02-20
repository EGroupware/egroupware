<?php
/**
 * TimeSheet - Events object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package timesheet
 * @copyright (c) 2022 by Ralf Becker <rb@egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL v2+ - GNU General Public License Version 2 or higher
 */

namespace EGroupware\Timesheet;

use EGroupware\Api;

/**
 * Events object of the TimeSheet
 *
 * Uses eTemplate's Api\Storage as storage object (Table: egw_timesheet).
 */
class Events extends Api\Storage\Base
{
	const APP = 'timesheet';
	const TABLE = 'egw_timesheet_events';
	/**
	 * @var string[] name of timestamps to convert (not set automatic by setup_table/parent::__construct()!)
	 */
	public $timestamps = [
		'tse_timestamp', 'tse_time', 'tse_modified',
	];

	/**
	 * Bitfields for egw_timesheet_events.tse_type
	 */
	const OVERALL = 16;
	const START = 1;
	const STOP = 2;
	const PAUSE = 4;

	/**
	 * @var Events
	 */
	private static $instance;
	protected int $user;

	function __construct()
	{
		parent::__construct(self::APP,self::TABLE, null, '', true, 'object');

		if (!isset(self::$instance))
		{
			self::$instance = $this;
		}
		$this->user = $GLOBALS['egw_info']['user']['account_id'];
	}

	function __destruct()
	{
		if (self::$instance === $this)
		{
			self::$instance = null;
		}
	}

	protected static function getInstance()
	{
		if (!isset(self::$instance))
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Record/persist timer events from UI
	 *
	 * @param array $state
	 * @return int tse_id of created event
	 * @throws Api\Exception
	 * @throws Api\Exception\WrongParameter
	 */
	public function ajax_event(array $state)
	{
		list($timer, $action) = explode('-', $state['action']);
		if (!in_array($timer, ['overall', 'specific']) || !in_array($action, ['start', 'stop', 'pause']))
		{
			throw new Api\Exception\WrongParameter("Invalid action '$state[action]'!");
		}
		if (empty($state['ts']))
		{
			throw new Api\Exception\WrongParameter("Invalid timestamp ('ts') '$state[ts]'!");
		}
		$type = ($timer === 'overall' ? self::OVERALL : 0) |
			($action === 'start' ? self::START : ($action === 'stop' ? self::STOP : self::PAUSE));

		$app = $id = $ts_id = null;
		if ($timer === 'specific' && !empty($state['specific']['app_id']))
		{
			list($app, $id) = explode('::', $state['specific']['app_id'], 2);
			if ($app === self::APP)
			{
				$ts_id = $id;
			}
		}

		$this->init();
		$this->save([
			'tse_timestamp' => new Api\DateTime(),
			'tse_time' => new Api\DateTime($state['ts']),
			'account_id' => $this->user,
			'tse_modifier' => $this->user,
			'tse_type' => $type,
			'tse_app'  => $app,
			'tse_app_id' => $id,
			'ts_id'    => $ts_id,
		]);

		// create timesheet for stopped working time
		if ($timer === 'overall' && $action === 'stop')
		{
			try {
				$minutes = $this->storeWorkingTime();
				Api\Json\Response::get()->message(lang('Working time of %1 hours stored',
					sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60)), 'success');
			}
			catch(\Exception $e) {
				Api\Json\Response::get()->message(lang('Error storing working time').': '.$e->getMessage(), 'error');
			}
		}
		// return (new) tse_id
		Api\Json\Response::get()->data($this->data['tse_id']);
	}

	/**
	 * Set app::id on an already started timer
	 *
	 * @param int $tse_id id of the running timer-event
	 * @param string $app_id "app::id" string
	 * @return void
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public function ajax_updateAppId(int $tse_id, string $app_id)
	{
		if ($tse_id > 0 && !empty($app_id))
		{
			list($app, $id) = explode('::', $app_id);
			$this->db->update(self::TABLE, [
				'tse_app' => $app,
				'tse_app_id' => $id,
			], [
				'tse_id' => $tse_id,
				'account_id' => $this->user,
				'ts_id IS NULL',
			], __LINE__, __FILE__, self::APP);
		}
	}

	/**
	 * Set tse_time on an already started timer
	 *
	 * @param int $tse_id id of the running timer-event
	 * @param string $time
	 * @return void
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public function ajax_updateTime(int $tse_id, string $time)
	{
		if ($tse_id > 0 && !empty($time) && ($event = $this->read($tse_id)))
		{
			$time = new Api\DateTime($time);
			$this->db->update(self::TABLE, [
				'tse_time' => $time,
			], [
				'tse_id' => $tse_id,
				'account_id' => $this->user,
			], __LINE__, __FILE__, self::APP);

			// if a stop event is overwritten, we need to adjust the timesheet duration and quantity
			if (!empty($event['ts_id']) && ($diff = round(($time->getTimestamp() - $event['tse_time']->getTimestamp()) / 60)))
			{
				$bo = new \timesheet_bo();
				if ($bo->read($event['ts_id']))
				{
					$bo->data['ts_duration'] += $diff;
					$bo->data['ts_quantity'] += $diff / 60;
					// set title, in case date(s) changed
					$events = self::get(['ts_id' => $event['ts_id']]);
					$bo->data['ts_title'] = self::workingTimeTitle($events);
					$bo->save();
				}
			}
		}
	}

	/**
	 * Generate working time title
	 *
	 * @param array $events
	 * @param Api\DateTime|null &$start on return start-time
	 * @return string
	 */
	protected static function workingTimeTitle(array $events, Api\DateTime &$start=null)
	{
		$start = array_shift($events)['tse_time'];
		$timespan = Api\DateTime::to($start, true);
		$last = array_pop($events);
		if ($timespan !== ($end = Api\DateTime::to($last['tse_time'], true)))
		{
			$timespan .= ' - '.$end;
		}
		return lang('Working time from %1', $timespan);
	}

	/**
	 * Store pending overall timer events as a working time timesheet
	 *
	 * @return int minutes
	 */
	public function storeWorkingTime()
	{
		if (!($events = self::getPending(true, $time)) || !$time)
		{
			throw new Api\Exception\AssertionFailed("No pending overall events!");
		}
		$ids = array_keys($events);
		$bo = new \timesheet_bo();
		// check if we already have a timesheet for the current periode
		if (($period_ts = $bo->periodeWorkingTimesheet(reset($events)['tse_time'])))
		{
			$events = array_merge(self::get(['ts_id' => $period_ts['ts_id']], $period_total), $events);
			$time += $period_total;
		}
		$title = self::workingTimeTitle($events, $start);
		$bo->init($period_ts);
		$bo->save([
			'ts_title' => $title,
			'cat_id' => self::workingTimeCat(),
			'ts_start' => $start,
			'start_time' => Api\DateTime::server2user($start, 'H:s'),
			'end_time' => '',
			'ts_duration' => $minutes = round($time / 60),
			'ts_quantity' => $minutes / 60.0,
			'ts_owner' => $this->user,
		]);
		self::addToTimesheet($bo->data['ts_id'], $ids);

		return $minutes;
	}

	/**
	 * Name of session variable to not ask again if user denied starting working time
	 */
	const DONT_ASK_AGAIN_WORKING_TIME = 'dont-ask-again-working-time';

	/**
	 * Get state of timer
	 *
	 * @return array[]|void
	 */
	public static function timerState()
	{
		try {
			$state = [
				'overall' => [
					'offset' => 0,
					'start' => null,
					'paused' => false,
					'last' => null,
					'dont_ask' => Api\Cache::getSession(__CLASS__, self::DONT_ASK_AGAIN_WORKING_TIME),
				],
				'specific' => [
					'offset' => 0,
					'start' => null,
					'paused' => false,
					'last' => null,
				],
			];
			foreach(self::getInstance()->search('', false, 'tse_id', '', '', false, 'AND', false, [
				'ts_id' => null,
				'account_id' => self::getInstance()->user,
			]) as $row)
			{
				if ($row['tse_type'] & self::OVERALL)
				{
					self::evaluate($state['overall'], $row);
				}
				else
				{
					self::evaluate($state['specific'], $row);

					// if event is associated with an app:id, set it again
					if ($row['tse_app'] && $row['tse_app_id'])
					{
						$state['specific']['app_id'] = $row['tse_app'].'::'.$row['tse_app_id'];
					}
				}
			}
			// format start-times in UTZ as JavaScript Date() understands
			foreach($state as &$timer)
			{
				foreach(['start', 'started', 'last'] as $name)
				{
					if (isset($timer[$name]))
					{
						$timer[$name] = (new Api\DateTime($timer[$name], new \DateTimeZone('UTC')))->format(Api\DateTime::ET2);
					}
				}
			}
			// send timer configuration to client-side
			$config = Api\Config::read(self::APP);
			$state['disable'] = $config['disable_timer'] ?? [];

			return $state;
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
		}
	}

	/**
	 * Remember for 18h or forever to not ask again to start working time
	 *
	 * @param ?bool $never true: never ask again, set preference, otherwise remember in session for 18h
	 * @return void
	 */
	static function ajax_dontAskAgainWorkingTime(bool $never=null)
	{
		if ($never)
		{
			$prefs = new Api\Preferences($GLOBALS['egw_info']['user']['account_id']);
			$prefs->read_repository();
			$prefs->user['timesheet']['workingtime_session'] = 'no';
			$prefs->save_repository();
		}
		else
		{
			Api\Cache::setSession(__CLASS__, self::DONT_ASK_AGAIN_WORKING_TIME, true, 18*3600);
		}
	}

	/**
	 * Evaluate events
	 *
	 * Returned time and offset/duration are always rounded to full minutes, independent of their actual unit!
	 * This is done, as we only show full minutes in the UI, and we want to avoid seconds in timestamps
	 * leading to unexpected results in the minutes display.
	 *
	 * @param array& $timer array with keys 'start', 'offset' and 'paused'
	 * @param array $row
	 * @return int? time in ms for stop or pause events, null for start
	 */
	protected static function evaluate(array &$timer, array $row)
	{
		if ($row['tse_type'] & self::START)
		{
			$timer['start'] = $timer['started'] = $row['tse_time'];
			$timer['started_id'] = $row['tse_id'];
			$timer['paused'] = false;
		}
		elseif ($timer['start'])
		{
			$timer['offset'] += $time = 60000 * round(($row['tse_time']->getTimestamp() - $timer['start']->getTimestamp())/60);
			$timer['start'] = null;
			$timer['paused'] = ($row['tse_type'] & self::PAUSE) === self::PAUSE;
		}
		else    // stop of paused timer
		{
			$timer['paused'] = ($row['tse_type'] & self::PAUSE) === self::PAUSE;
		}
		$timer['last'] = $row['tse_time'];
		$timer['id'] = $row['tse_id'];
		return $time ?? null;
	}

	/**
	 * Get events of given ts_id or a filter
	 *
	 * Not stopped events-sequences are NOT returned (stopped sequences end with a stop event).
	 *
	 * @param int|array $filter
	 * @param int &$total=null on return time in seconds
	 * @return array[] tse_id => array pairs plus extra key sum (time-sum in seconds)
	 */
	public static function get($filter, int &$total=null)
	{
		if (!is_array($filter))
		{
			$filter = ['ts_id' => $filter];
		}
		$timer = $init_timer = [
			'start' => null,
			'offset' => 0,
			'paused' => false,
		];
		$total = $open = 0;
		$events = [];
		foreach(self::getInstance()->search('', false, 'tse_id', '', '',
			false, 'AND', false, $filter) as $row)
		{
			$time = self::evaluate($timer, $row);
			++$open;

			if ($row['tse_type'] & self::STOP)
			{
				$row['total'] = $total += $timer['offset'] / 1000;
				$timer = $init_timer;
				$open = 0;
			}
			elseif ($row['tse_type'] & self::PAUSE)
			{
				$row['total'] = $total + $timer['offset'] / 1000;
			}
			$row['time'] = $time / 1000;
			$events[$row['tse_id']] = $row;
		}
		// remove open / unstopped timer events
		if ($open)
		{
			$events = array_slice($events, 0, -$open, true);
		}
		return $events;
	}

	/**
	 * Get pending (overall) events of (current) user
	 *
	 * Not stopped events-sequences are NOT returned (stopped sequences end with a stop event).
	 *
	 * @param bool $overall
	 * @param int &$time=null on return total time in seconds
	 * @return array[] tse_id => array pairs
	 */
	public static function getPending($overall=false, int &$time=null)
	{
		return self::get([
			'ts_id' => null,
			'account_id' => self::getInstance()->user,
			($overall ? '' : 'NOT ').'(tse_type & '.self::OVERALL.')',
		], $time);
	}

	/**
	 * Add given events to timesheet / set ts_id parameter
	 *
	 * @param int $ts_id
	 * @param int[] $events array of tse_id's
	 * @return Api\ADORecordSet|false|int
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public static function addToTimesheet(int $ts_id, array $events)
	{
		return self::getInstance()->db->update(self::TABLE, ['ts_id' => $ts_id], ['tse_id' => $events], __LINE__, __FILE__, self::APP);
	}

	/**
	 * Register site config validation hooks
	 */
	public static function config_validate()
	{
		$GLOBALS['egw_info']['server']['found_validation_hook'] = [
			'final_validation' => self::class.'::final_validation',
		];
	}

	/**
	 * Final validation called after storing the config
	 *
	 * @param array $config
	 * @param Api\Config $c
	 */
	public static function final_validation($config, Api\Config $c)
	{
		// check if category for 'working time' is configured, otherwise create and store it
		if ($config['working_time_cat'] === '')
		{
			$c->config_data['working_time_cat'] = self::workingTimeCat();
		}
	}

	/**
	 * Get working time category, create it if not yet configured
	 *
	 * @return int
	 */
	public static function workingTimeCat()
	{
		$config = Api\Config::read(self::APP);
		if (empty($config['working_time_cat']) || !Api\Categories::read($config['working_time_cat']))
		{
			$cats = new Api\Categories(Api\Categories::GLOBAL_ACCOUNT, Api\Categories::GLOBAL_APPNAME);
			Api\Config::save_value('working_time_cat', $config['working_time_cat'] = $cats->add([
				'name' => lang('Working time'),
				//'data' => ['color' => '#ffb6c1'],
				'description' => lang('Created by TimeSheet configuration'),
			]), self::APP);
		}
		return $config['working_time_cat'];
	}
}