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
	}

	public static function timerState()
	{
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
}