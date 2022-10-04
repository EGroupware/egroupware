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

		$this->init();
		$this->save([
			'tse_timestamp' => new Api\DateTime(),
			'tse_time' => new Api\DateTime($state['ts']),
			'account_id' => $this->user,
			'tse_modifier' => $this->user,
			'tse_type' => $type,
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
		$start = array_shift($events)['tse_time'];
		$timespan = Api\DateTime::to($start, true);
		$last = array_pop($events);
		if ($timespan !== ($end = Api\DateTime::to($last['tse_time'], true)))
		{
			$timespan .= ' - '.$end;
		}
		$title = lang('Working time from %1', $timespan);
		$bo = new \timesheet_bo();
		$bo->init();
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
				],
				'specific' => [
					'offset' => 0,
					'start' => null,
					'paused' => false,
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
				}
			}
			// format start-times in UTZ as JavaScript Date() understands
			foreach($state as &$timer)
			{
				if (isset($timer['start']))
				{
					$timer['start'] = (new Api\DateTime($timer['start'], new \DateTimeZone('UTC')))->format(Api\DateTime::ET2);
				}
			}
			return $state;
		}
		catch (\Exception $e) {
			_egw_log_exception($e);
		}
	}

	/**
	 * Evaluate events
	 *
	 * @param array& $timer array with keys 'start', 'offset' and 'paused'
	 * @param array $row
	 * @return void
	 */
	protected static function evaluate(array &$timer, array $row)
	{
		if ($row['tse_type'] & self::START)
		{
			$timer['start'] = $row['tse_time'];
			$timer['paused'] = false;
		}
		elseif ($timer['start'])
		{
			$timer['offset'] += 1000 * ($row['tse_time']->getTimestamp() - $timer['start']->getTimestamp());
			$timer['start'] = null;
			$timer['paused'] = ($row['tse_type'] & self::PAUSE) === self::PAUSE;
		}
		else    // stop of paused timer
		{
			$timer['paused'] = ($row['tse_type'] & self::PAUSE) === self::PAUSE;
		}
	}

	/**
	 * Get events of given ts_id or a filter
	 *
	 * Not stopped events-sequences are NOT returned (stopped sequences end with a stop event).
	 *
	 * @param int|array $filter
	 * @param int &$time=null on return time in seconds
	 * @return array[] tse_id => array pairs plus extra key sum (time-sum in seconds)
	 */
	public static function get($filter, int &$time=null)
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
		$time = $open = 0;
		$events = [];
		foreach(self::getInstance()->search('', false, 'tse_id', '', '',
			false, 'AND', false, $filter) as $row)
		{
			self::evaluate($timer, $row);
			++$open;

			if ($row['tse_type'] & self::STOP)
			{
				$row['total'] = $time += $timer['offset'] / 1000;
				$timer = $init_timer;
				$open = 0;
			}
			elseif ($row['tse_type'] & self::PAUSE)
			{
				$row['total'] = $time + $timer['offset'] / 1000;
			}
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