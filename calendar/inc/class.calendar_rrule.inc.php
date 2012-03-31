<?php
/**
 * eGroupWare - Calendar recurrence rules
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2009 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Recurrence rule iterator
 *
 * The constructor accepts times only as DateTime (or decendents like egw_date) to work timezone-correct.
 * The timezone of the event is determined by timezone of the startime, other times get converted to that timezone.
 *
 * There's a static factory method calendar_rrule::event2rrule(array $event,$usertime=true), which converts an
 * event read by calendar_bo::read() or calendar_bo::search() to a rrule iterator.
 *
 * The rrule iterator object can be casted to string, to get a human readable description of the rrule.
 *
 * There's an interactive test-form, if the class get's called directly: http://localhost/egroupware/calendar/inc/class.calendar_rrule.inc.php
 *
 * @todo Integrate iCal import and export, so all recurrence code resides just in this class
 * @todo Implement COUNT, can be stored in enddate assuming counts are far smaller then timestamps (eg. < 1000 is a count)
 * @todo Implement WKST (week start day), currently WKST=SU is used (this is not stored in current DB schema, it's a user preference)
 */
class calendar_rrule implements Iterator
{
	/**
	 * No recurrence
	 */
	const NONE = 0;
	/**
	 * Daily recurrence
	 */
	const DAILY = 1;
	/**
	 * Weekly recurrance on day(s) specified by bitfield in $data
	 */
	const WEEKLY = 2;
	/**
	 * Monthly recurrance iCal: monthly_bymonthday
	 */
	const MONTHLY_MDAY = 3;
	/**
	 * Monthly recurrance iCal: BYDAY (by weekday, eg. 1st Friday of month)
	 */
	const MONTHLY_WDAY = 4;
	/**
	 * Yearly recurrance
	 */
	const YEARLY = 5;
	/**
	 * Translate recure types to labels
	 *
	 * @var array
	 */
	static public $types = Array(
		self::NONE         => 'None',
		self::DAILY        => 'Daily',
		self::WEEKLY       => 'Weekly',
		self::MONTHLY_WDAY => 'Monthly (by day)',
		self::MONTHLY_MDAY => 'Monthly (by date)',
		self::YEARLY       => 'Yearly'
	);

	/**
	 * @var array $recur_egw2ical_2_0 converstaion of egw recur-type => ical FREQ
	 */
	static private $recur_egw2ical_2_0 = array(
		self::DAILY        => 'DAILY',
		self::WEEKLY       => 'WEEKLY',
		self::MONTHLY_WDAY => 'MONTHLY',	// BYDAY={1..7, -1}{MO..SO, last workday}
		self::MONTHLY_MDAY => 'MONTHLY',	// BYMONHTDAY={1..31, -1 for last day of month}
		self::YEARLY       => 'YEARLY',
	);

	/**
	 * @var array $recur_egw2ical_1_0 converstaion of egw recur-type => ical FREQ
	 */
	static private $recur_egw2ical_1_0 = array(
		self::DAILY        => 'D',
		self::WEEKLY       => 'W',
		self::MONTHLY_WDAY => 'MP',	// BYDAY={1..7,-1}{MO..SO, last workday}
		self::MONTHLY_MDAY => 'MD',	// BYMONHTDAY={1..31,-1}
		self::YEARLY       => 'YM',
	);

	/**
	 * RRule type: NONE, DAILY, WEEKLY, MONTHLY_MDAY, MONTHLY_WDAY, YEARLY
	 *
	 * @var int
	 */
	public $type = self::NONE;

	/**
	 * Interval
	 *
	 * @var int
	 */
	public $interval = 1;

	/**
	 * Number for monthly byday: 1, ..., 5, -1=last weekday of month
	 *
	 * EGroupware Calendar does NOT explicitly store it, it's only implicitly defined by series start date
	 *
	 * @var int
	 */
	public $monthly_byday_num;

	/**
	 * Number for monthly bymonthday: 1, ..., 31, -1=last day of month
	 *
	 * EGroupware Calendar does NOT explicitly store it, it's only implicitly defined by series start date
	 *
	 * @var int
	 */
	public $monthly_bymonthday;

	/**
	 * Enddate of recurring event or null, if not ending
	 *
	 * @var DateTime
	 */
	public $enddate;
	/**
	 * Enddate of recurring event, as Ymd integer (eg. 20091111)
	 *
	 * @var int
	 */
	public $enddate_ymd;

	const SUNDAY    = 1;
	const MONDAY    = 2;
	const TUESDAY   = 4;
	const WEDNESDAY = 8;
	const THURSDAY  = 16;
	const FRIDAY    = 32;
	const SATURDAY  = 64;
	const WORKDAYS  = 62;	// Mo, ..., Fr
	const ALLDAYS   = 127;
	/**
	 * Translate weekday bitmasks to labels
	 *
	 * @var array
	 */
	static public $days = array(
		self::MONDAY    => 'Monday',
		self::TUESDAY   => 'Tuesday',
		self::WEDNESDAY => 'Wednesday',
		self::THURSDAY  => 'Thursday',
		self::FRIDAY    => 'Friday',
		self::SATURDAY  => 'Saturday',
		self::SUNDAY    => 'Sunday',
	);
	/**
	 * Bitmask of valid weekdays for weekly repeating events: self::SUNDAY|...|self::SATURDAY
	 *
	 * @var integer
	 */
	public $weekdays;

	/**
	 * Array of exception dates (Ymd strings)
	 *
	 * @var array
	 */
	public $exceptions=array();

	/**
	 * Array of exceptions as DateTime/egw_time objects
	 *
	 * @var array
	 */
	public $exceptions_objs=array();

	/**
	 * Starttime of series
	 *
	 * @var DateTime
	 */
	public $time;

	/**
	 * Current "position" / time
	 *
	 * @var DateTime
	 */
	public $current;

	/**
	 * Last day of the week according to user preferences
	 *
	 * @var int
	 */
	protected $lastdayofweek;

	/**
	 * Cached timezone data
	 *
	 * @var array id => data
	 */
	protected static $tz_cache = array();

	/**
	 * Constructor
	 *
	 * The constructor accepts on DateTime (or decendents like egw_date) for all times, to work timezone-correct.
	 * The timezone of the event is determined by timezone of $time, other times get converted to that timezone.
	 *
	 * @param DateTime $time start of event in it's own timezone
	 * @param int $type self::NONE, self::DAILY, ..., self::YEARLY
	 * @param int $interval=1 1, 2, ...
	 * @param DateTime $enddate=null enddate or null for no enddate (in which case we user '+5 year' on $time)
	 * @param int $weekdays=0 self::SUNDAY=1|self::MONDAY=2|...|self::SATURDAY=64
	 * @param array $exceptions=null DateTime objects with exceptions
	 */
	public function __construct(DateTime $time,$type,$interval=1,DateTime $enddate=null,$weekdays=0,array $exceptions=null)
	{
		switch($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'])
		{
			case 'Sunday':
				$this->lastdayofweek = self::SATURDAY;
				break;
			case 'Saturday':
				$this->lastdayofweek = self::FRIDAY;
				break;
			default: // Monday
				$this->lastdayofweek = self::SUNDAY;
		}

		$this->time = $time;

		if (!in_array($type,array(self::NONE, self::DAILY, self::WEEKLY, self::MONTHLY_MDAY, self::MONTHLY_WDAY, self::YEARLY)))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."($time,$type,$interval,$enddate,$data,...) type $type is NOT valid!");
		}
		$this->type = $type;

		// determine only implicit defined rules for RRULE=MONTHLY,BYDAY={-1, 1, ..., 5}{MO,..,SU}
		if ($type == self::MONTHLY_WDAY)
		{
			// check for last week of month
			if (($day = $this->time->format('d')) >= 21 && $day > self::daysInMonth($this->time)-7)
			{
				$this->monthly_byday_num = -1;
			}
			else
			{
				$this->monthly_byday_num = 1 + floor(($this->time->format('d')-1) / 7);
			}
		}
		elseif($type == self::MONTHLY_MDAY)
		{
			$this->monthly_bymonthday = (int)$this->time->format('d');
			// check for last day of month
			if ($this->monthly_bymonthday >= 28)
			{
				$test = clone $this->time;
				$test->modify('1 day');
				if ($test->format('m') != $this->time->format('m'))
				{
					$this->monthly_bymonthday = -1;
				}
			}
		}

		if ((int)$interval < 1)
		{
			$interval = 1;	// calendar stores no (extra) interval as null, so using default 1 here
		}
		$this->interval = (int)$interval;

		$this->enddate = $enddate;
		// no recurrence --> current date is enddate
		if ($type == self::NONE)
		{
			$enddate = clone $this->time;
		}
		// set a maximum of 5 years if no enddate given
		elseif (is_null($enddate))
		{
			$enddate = clone $this->time;
			$enddate->modify('5 year');
		}
		// convert enddate to timezone of time, if necessary
		else
		{
			$enddate->setTimezone($this->time->getTimezone());
		}
		$this->enddate_ymd = (int)$enddate->format('Ymd');

		// if no valid weekdays are given for weekly repeating, we use just the current weekday
		if (!($this->weekdays = (int)$weekdays) && ($type == self::WEEKLY || $type == self::MONTHLY_WDAY))
		{
			$this->weekdays = self::getWeekday($this->time);
		}
		if ($exceptions)
		{
			foreach($exceptions as $exception)
			{
				$exception->setTimezone($this->time->getTimezone());
				$this->exceptions[] = $exception->format('Ymd');
			}
			$this->exceptions_objs = $exceptions;
		}
	}

	/**
	 * Get number of days in month of given date
	 *
	 * @param DateTime $time
	 * @return int
	 */
	private static function daysInMonth(DateTime $time)
	{
		list($year,$month) = explode('-',$time->format('Y-m'));
		$last_day = new egw_time();
		$last_day->setDate($year,$month+1,0);

		return (int)$last_day->format('d');
	}

	/**
	 * Return the current element
	 *
	 * @return DateTime
	 */
	public function current()
	{
		return clone $this->current;
	}

	/**
	 * Return the key of the current element, we use a Ymd integer as key
	 *
	 * @return int
	 */
	public function key()
	{
		return (int)$this->current->format('Ymd');
	}

	/**
	 * Move forward to next recurence, not caring for exceptions
	 */
	public function next_no_exception()
	{
		switch($this->type)
		{
			case self::NONE:	// need to add at least one day, to end "series", as enddate == current date
			case self::DAILY:
				$this->current->modify($this->interval.' day');
				break;

			case self::WEEKLY:
				// advance to next valid weekday
				do
				{
					// interval in weekly means event runs on valid days eg. each 2. week
					// --> on the last day of the week we have to additionally advance interval-1 weeks
					if ($this->interval > 1 && self::getWeekday($this->current) == $this->lastdayofweek)
					{
						$this->current->modify(($this->interval-1).' week');
					}
					$this->current->modify('1 day');
					//echo __METHOD__.'() '.$this->current->format('l').', '.$this->current.": $this->weekdays & ".self::getWeekday($this->current)."<br />\n";
				}
				while(!($this->weekdays & self::getWeekday($this->current)));
				break;

			case self::MONTHLY_WDAY:	// iCal: BYDAY={1, ..., 5, -1}{MO..SO}
				// advance to start of next month
				list($year,$month) = explode('-',$this->current->format('Y-m'));
				$month += $this->interval+($this->monthly_byday_num < 0 ? 1 : 0);
				$this->current->setDate($year,$month,$this->monthly_byday_num < 0 ? 0 : 1);
				//echo __METHOD__."() $this->monthly_byday_num".substr(self::$days[$this->monthly_byday_wday],0,2).": setDate($year,$month,1): ".$this->current->format('l').', '.$this->current."<br />\n";
				// now advance to n-th week
				if ($this->monthly_byday_num > 1)
				{
					$this->current->modify(($this->monthly_byday_num-1).' week');
					//echo __METHOD__."() $this->monthly_byday_num".substr(self::$days[$this->monthly_byday_wday],0,2).': modify('.($this->monthly_byday_num-1).' week): '.$this->current->format('l').', '.$this->current."<br />\n";
				}
				// advance to given weekday
				while(!($this->weekdays & self::getWeekday($this->current)))
				{
					$this->current->modify(($this->monthly_byday_num < 0 ? -1 : 1).' day');
					//echo __METHOD__."() $this->monthly_byday_num".substr(self::$days[$this->monthly_byday_wday],0,2).': modify(1 day): '.$this->current->format('l').', '.$this->current."<br />\n";
				}
				break;

			case self::MONTHLY_MDAY:	// iCal: monthly_bymonthday={1, ..., 31, -1}
				list($year,$month) = explode('-',$this->current->format('Y-m'));
				$day = $this->monthly_bymonthday+($this->monthly_bymonthday < 0 ? 1 : 0);
				$month += $this->interval+($this->monthly_bymonthday < 0 ? 1 : 0);
				$this->current->setDate($year,$month,$day);
				//echo __METHOD__."() setDate($year,$month,$day): ".$this->current->format('l').', '.$this->current."<br />\n";
				break;

			case self::YEARLY:
				$this->current->modify($this->interval.' year');
				break;

			default:
				throw new egw_exception_assertion_failed(__METHOD__."() invalid type #$this->type !");
		}
	}

	/**
	 * Move forward to next recurence, taking into account exceptions
	 */
	public function next()
	{
		do
		{
			$this->next_no_exception();
		}
		while($this->exceptions && in_array($this->current->format('Ymd'),$this->exceptions));
	}

	/**
	 * Get weekday of $time as self::SUNDAY=1, ..., self::SATURDAY=64 integer mask
	 *
	 * @param DateTime $time
	 * @return int self::SUNDAY=1, ..., self::SATURDAY=64
	 */
	static protected function getWeekday(DateTime $time)
	{
		//echo __METHOD__.'('.$time->format('l').' '.$time.') 1 << '.$time->format('w').' = '.(1 << (int)$time->format('w'))."<br />\n";
		return 1 << (int)$time->format('w');
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind()
	{
		$this->current = clone $this->time;
		while ($this->valid() &&
			$this->exceptions &&
			in_array($this->current->format('Ymd'),$this->exceptions))
		{
			$this->next_no_exception();
		}
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid ()
	{
		return $this->current->format('Ymd') <= $this->enddate_ymd;
	}

	/**
	 * Return string represenation of RRule
	 *
	 * @return string
	 */
	function __toString( )
	{
		$str = '';
		// Repeated Events
		if($this->type != self::NONE)
		{
			list($str) = explode(' (',lang(self::$types[$this->type]));	// remove (by day/date) from Monthly

			$str_extra = array();
			switch ($this->type)
			{
				case self::MONTHLY_MDAY:
					$str_extra[] = ($this->monthly_bymonthday == -1 ? lang('last') : $this->monthly_bymonthday.'.').' '.lang('day');
					break;

				case self::WEEKLY:
				case self::MONTHLY_WDAY:
					$repeat_days = array();
					if ($this->weekdays == self::ALLDAYS)
					{
						$repeat_days[] = $this->type == self::WEEKLY ? lang('all') : lang('day');
					}
					elseif($this->weekdays == self::WORKDAYS)
					{
						$repeat_days[] = $this->type == self::WEEKLY ? lang('workdays') : lang('workday');
					}
					else
					{
						foreach (self::$days as $mask => $label)
						{
							if ($this->weekdays & $mask)
							{
								$repeat_days[] = lang($label);
							}
						}
					}
					if($this->type == self::WEEKLY && count($repeat_days))
					{
						$str_extra[] = lang('days repeated').': '.implode(', ',$repeat_days);
					}
					elseif($this->type == self::MONTHLY_WDAY)
					{
						$str_extra[] = ($this->monthly_byday_num == -1 ? lang('last') : $this->monthly_byday_num.'.').' '.implode(', ',$repeat_days);
					}
					break;

			}
			if($this->interval > 1)
			{
				$str_extra[] = lang('Interval').': '.$this->interval;
			}
			if ($this->enddate)
			{
				if ($this->enddate->getTimezone()->getName() != egw_time::$user_timezone->getName())
				{
					$this->enddate->setTimezone(egw_time::$user_timezone);
				}
				$str_extra[] = lang('ends').': '.lang($this->enddate->format('l')).', '.$this->enddate->format(egw_time::$user_dateformat);
			}
			if ($this->time->getTimezone()->getName() != egw_time::$user_timezone->getName())
			{
				$str_extra[] = $this->time->getTimezone()->getName();
			}
			if(count($str_extra))
			{
				$str .= ' ('.implode(', ',$str_extra).')';
			}
		}
		return $str;
	}

	/**
	 * Generate a VEVENT RRULE
	 * @param string $version='1.0' could be '2.0', too
	 *
	 * $return array	vCalendar RRULE
	 */
	public function generate_rrule($version='1.0')
	{
		$repeat_days = array();
		$rrule = array();

		if ($this->type == self::NONE) return false;	// no recuring event

		if ($version == '1.0')
		{
			$rrule['FREQ'] = self::$recur_egw2ical_1_0[$this->type] . $this->interval;
			switch ($this->type)
			{
				case self::WEEKLY:
					foreach (self::$days as $mask => $label)
					{
						if ($this->weekdays & $mask)
						{
							$repeat_days[] = strtoupper(substr($label,0,2));
						}
					}
					$rrule['BYDAY'] = implode(' ', $repeat_days);
					$rrule['FREQ'] = $rrule['FREQ'].' '.$rrule['BYDAY'];
					break;

				case self::MONTHLY_MDAY:	// date of the month: BYMONTDAY={1..31}
					break;

				case self::MONTHLY_WDAY:	// weekday of the month: BDAY={1..5}+ {MO..SO}
					$rrule['BYDAY'] = abs($this->monthly_byday_num);
					$rrule['BYDAY'] .= ($this->monthly_byday_num < 0) ? '- ' : '+ ';
					$rrule['BYDAY'] .= strtoupper(substr($this->time->format('l'),0,2));
					$rrule['FREQ'] = $rrule['FREQ'].' '.$rrule['BYDAY'];
					break;
			}

			if (!$this->enddate)
			{
				$rrule['UNTIL'] = '#0';
			}
		}
		else // $version == '2.0'
		{
			$rrule['FREQ'] = self::$recur_egw2ical_2_0[$this->type];
			switch ($this->type)
			{
				case self::WEEKLY:
					foreach (self::$days as $mask => $label)
					{
						if ($this->weekdays & $mask)
						{
							$repeat_days[] = strtoupper(substr($label,0,2));
						}
					}
					$rrule['BYDAY'] = implode(',', $repeat_days);
					break;

				case self::MONTHLY_MDAY:	// date of the month: BYMONTDAY={1..31}
					$rrule['BYMONTHDAY'] = $this->monthly_bymonthday;
					break;

				case MCAL_RECUR_MONTHLY_WDAY:	// weekday of the month: BDAY={1..5}{MO..SO}
					$rrule['BYDAY'] = $this->monthly_byday_num .
						strtoupper(substr($this->time->format('l'),0,2));
					break;
			}
			if ($this->interval > 1)
			{
				$rrule['INTERVAL'] = $this->interval;
			}
		}

		if ($this->enddate)
		{
			$this->rewind();
			$enddate = $this->current();
			do
			{
				$this->next_no_exception();
				$occurrence = $this->current();
			}
			while ($this->valid() && ($enddate = $occurrence));
			$rrule['UNTIL'] = $enddate;
		}

		return $rrule;
	}

	/**
	 * Get instance for a given event array
	 *
	 * @param array $event
	 * @param boolean $usertime=true true: event timestamps are usertime (default for calendar_bo::(read|search), false: servertime
	 * @param string $to_tz			timezone for exports (null for event's timezone)
	 *
	 * @return calendar_rrule		false on error
	 */
	public static function event2rrule(array $event,$usertime=true,$to_tz=null)
	{
		if (!is_array($event)  || !isset($event['tzid'])) return false;
		if (!$to_tz) $to_tz = $event['tzid'];
		$timestamp_tz = $usertime ? egw_time::$user_timezone : egw_time::$server_timezone;
		$time = is_a($event['start'],'DateTime') ? $event['start'] : new egw_time($event['start'],$timestamp_tz);

		if (!isset(self::$tz_cache[$to_tz]))
		{
			self::$tz_cache[$to_tz] = calendar_timezones::DateTimeZone($to_tz);
		}

		self::rrule2tz($event, $time, $to_tz);

		$time->setTimezone(self::$tz_cache[$to_tz]);

		if ($event['recur_enddate'])
		{
			$enddate = is_a($event['recur_enddate'],'DateTime') ? $event['recur_enddate'] : new egw_time($event['recur_enddate'],$timestamp_tz);
		}
		if (is_array($event['recur_exception']))
		{
			foreach($event['recur_exception'] as $exception)
			{
				$exceptions[] = is_a($exception,'DateTime') ? $exception : new egw_time($exception,$timestamp_tz);
			}
		}
		return new calendar_rrule($time,$event['recur_type'],$event['recur_interval'],$enddate,$event['recur_data'],$exceptions);
	}

	/**
	 * Get recurrence data (keys 'recur_*') to merge into an event
	 *
	 * @return array
	 */
	public function rrule2event()
	{
		return array(
			'recur_type' => $this->type,
			'recur_interval' => $this->interval,
			'recur_enddate' => $this->enddate ? $this->enddate->format('ts') : null,
			'recur_data' => $this->weekdays,
			'recur_exception' => $this->exceptions,
		);
	}

	/**
	 * Shift a recurrence rule to a new timezone
	 *
	 * @param array $event			recurring event
	 * @param DateTime/string		starttime of the event (in servertime)
	 * @param string $to_tz			new timezone
	 */
	public static function rrule2tz(array &$event,$starttime,$to_tz)
	{
		// We assume that the difference between timezones can result
		// in a maximum of one day

		if (!is_array($event) ||
			!isset($event['recur_type']) ||
			$event['recur_type'] == MCAL_RECUR_NONE ||
			empty($event['recur_data']) || $event['recur_data'] == ALLDAYS ||
			empty($event['tzid']) || empty($to_tz) ||
			$event['tzid'] == $to_tz) return;

		if (!isset(self::$tz_cache[$event['tzid']]))
		{
			self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
		}
		if (!isset(self::$tz_cache[$to_tz]))
		{
			self::$tz_cache[$to_tz] = calendar_timezones::DateTimeZone($to_tz);
		}

		$time = is_a($starttime,'DateTime') ?
			$starttime : new egw_time($starttime, egw_time::$server_timezone);
		$time->setTimezone(self::$tz_cache[$event['tzid']]);
		$remote = clone $time;
		$remote->setTimezone(self::$tz_cache[$to_tz]);
		$delta = (int)$remote->format('w') - (int)$time->format('w');
		if ($delta)
		{
			// We have to generate a shifted rrule
			switch ($event['recur_type'])
			{
				case self::MONTHLY_WDAY:
				case self::WEEKLY:
					$mask = (int)$event['recur_data'];

					if ($delta == 1 || $delta == -6)
					{
						$mask = $mask << 1;
						if ($mask & 128) $mask = $mask - 127; // overflow
					}
					else
					{
						if ($mask & 1) $mask = $mask + 128; // underflow
						$mask = $mask >> 1;
					}
					$event['recur_data'] = $mask;
			}
		}
	}
}

if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
	ini_set('display_errors',1);
	error_reporting(E_ALL & ~E_NOTICE);
	function lang($str) { return $str; }
	$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $_REQUEST['user-tz'] ? $_REQUEST['user-tz'] : 'Europe/Berlin';
	require_once('../../phpgwapi/inc/class.egw_time.inc.php');
	require_once('../../phpgwapi/inc/class.html.inc.php');
	require_once('../../phpgwapi/inc/class.egw_exception.inc.php');

	if (!isset($_REQUEST['time']))
	{
		$now = new egw_time('now',new DateTimeZone($_REQUEST['tz'] = 'UTC'));
		$_REQUEST['time'] = $now->format();
		$_REQUEST['type'] = calendar_rrule::WEEKLY;
		$_REQUEST['interval'] = 2;
		$now->modify('2 month');
		$_REQUEST['enddate'] = $now->format('Y-m-d');
		$_REQUEST['user-tz'] = 'Europe/Berlin';
	}
	echo "<html>\n<head>\n\t<title>Test calendar_rrule class</title>\n</head>\n<body>\n<form method='GET'>\n";
	echo "<p>Date+Time: ".html::input('time',$_REQUEST['time']).
		html::select('tz',$_REQUEST['tz'],egw_time::getTimezones())."</p>\n";
	echo "<p>Type: ".html::select('type',$_REQUEST['type'],calendar_rrule::$types)."\n".
		"Interval: ".html::input('interval',$_REQUEST['interval'])."</p>\n";
	echo "<table><tr><td>\n";
	echo "Weekdays:<br />".html::checkbox_multiselect('weekdays',$_REQUEST['weekdays'],calendar_rrule::$days,false,'','7',false,'height: 150px;')."\n";
	echo "</td><td>\n";
	echo "<p>Exceptions:<br />".html::textarea('exceptions',$_REQUEST['exceptions'],'style="height: 150px;"')."\n";
	echo "</td></tr></table>\n";
	echo "<p>Enddate: ".html::input('enddate',$_REQUEST['enddate'])."</p>\n";
	echo "<p>Display recurances in ".html::select('user-tz',$_REQUEST['user-tz'],egw_time::getTimezones())."</p>\n";
	echo "<p>".html::submit_button('calc','Calculate')."</p>\n";
	echo "</form>\n";

	$tz = new DateTimeZone($_REQUEST['tz']);
	$time = new egw_time($_REQUEST['time'],$tz);
	if ($_REQUEST['enddate']) $enddate = new egw_time($_REQUEST['enddate'],$tz);
	$weekdays = 0; foreach((array)$_REQUEST['weekdays'] as $mask) $weekdays |= $mask;
	if ($_REQUEST['exceptions']) foreach(preg_split("/[,\r\n]+ ?/",$_REQUEST['exceptions']) as $exception) $exceptions[] = new egw_time($exception);

	$rrule = new calendar_rrule($time,$_REQUEST['type'],$_REQUEST['interval'],$enddate,$weekdays,$exceptions);
	echo "<h3>".$time->format('l').', '.$time.' ('.$tz->getName().') '.$rrule."</h3>\n";
	foreach($rrule as $rtime)
	{
		$rtime->setTimezone(egw_time::$user_timezone);
		echo ++$n.': '.$rtime->format('l').', '.$rtime."<br />\n";
	}
	echo "</body>\n</html>\n";
}