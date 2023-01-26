<?php
/**
 * EGroupware time and timezone handling
 *
 * @package api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2009-16 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api;

// we do not have an own implementation/extensions
use DateTimeZone;
use DateInterval;

/**
 * EGroupware time and timezone handling class extending PHP's DateTime
 *
 * Api\DateTime class knows 2 timezones:
 * 1. Api\DateTime::$user_timezone   timezone of the user, defined in his prefs $GLOBALS['egw_info']['user']['preferences']['common']['tz']
 * 2. Api\DateTime::$server_timezone timezone of the server, read via date_default_timezone_get()
 *
 * The class extends PHP5.2's DateTime object to:
 * - format date and time according to format in user prefs ($type===true: date, $type===false: time, $type==='' date+time)
 * - defaulting to a user timezone according to user prefs (not server timezone as DateTime!)
 * - deal with integer unix timestamps and DB timestamps in server-time ($type: 'ts'='integer' or 'server' timestamp in servertime)
 *
 * There are two static methods for simple conversation between server and user time:
 * - Api\DateTime::server2user($time,$type=null)
 * - Api\DateTime::user2server($time,$type=null)
 * (Replacing in 1.6 and previous used adding of tz_offset, which is only correct for current time)
 *
 * An other static method allows to format any time in several ways: Api\DateTime::to($time,$type) (exceed date($type,$time)).
 *
 * The constructor of Api\DateTime understand - in addition to DateTime - integer timestamps, array with values for
 * keys: ('year', 'month', 'day') or 'full' plus 'hour', 'minute' and optional 'second' or a DateTime object as parameter.
 * It defaults to user-time, not server time as DateTime!
 *
 * The constructor itself throws an \Exception in that case (to be precise it does not handle the one thrown by DateTime constructor).
 * Static methods server2user, user2server and to return NULL, if given time could not be parsed.
 *
 * Please note: EGroupware historically uses timestamps, which are NOT in UTC!
 * -----------
 * So in general the following thre are NOT the same value:
 * a) (new Api\DateTime($time))->getTimestamp() - regular timestamp in UTC like time()
 * b) (new Api\DateTime($time))->format('ts')   - EGroupware timestamp in user-timezone, UI and BO objects
 * c) Api\DateTime($time)::user2server('ts')    - EGroupware timestamp in server-timezone, SO / integer in database
 *
 * @link http://www.php.net/manual/en/class.datetime.php
 * @link http://www.php.net/manual/en/class.datetimezone.php
 */
class DateTime extends \DateTime
{
	/**
	 * Database timestamp format: Y-m-d H:i:s
	 */
	const DATABASE = 'Y-m-d H:i:s';

	/**
	 * etemplate2 format for ignoring timezones in the browser
	 */
	const ET2 = 'Y-m-d\TH:i:s\Z';
	/**
	 * DateTimeZone of server, read from $GLOBALS['egw_info']['server']['server_timezone'], set by self::init()
	 *
	 * @var DateTimeZone
	 */
	static public $server_timezone;

	/**
	 * DateTimeZone of user, read from user prefs, set by self::init() or self::setUserPrefs()
	 *
	 * @var DateTimeZone
	 */
	static public $user_timezone;

	/**
	 * Time format from user prefs, set by self::setUserPrefs()
	 *
	 * @var string
	 */
	static public $user_timeformat = 'H:i';

	/**
	 * Date format from user prefs, set by self::setUserPrefs()
	 *
	 * @var string
	 */
	static public $user_dateformat = 'Y-m-d';

	/**
	 * Constructor
	 *
	 * @param int|string|array|DateTime $time ='now' integer timestamp, string with date+time, DateTime object or
	 * 	array with values for keys('year','month','day') or 'full' plus 'hour','minute' and optional 'second'
	 * @param DateTimeZone $tz =null timezone, default user time (PHP DateTime default to server time!)
	 * @param string &$type=null on return type of $time (optional)
	 * @throws Exception if $time can NOT be parsed
	 */
	public function __construct($time='now',DateTimeZone $tz=null,&$type=null)
	{
		if (is_null($tz)) $tz = self::$user_timezone;	// default user timezone

		switch(($type = gettype($time)))
		{
			case 'NULL':
			case 'boolean':	// depricated use in calendar for 'now'
				$time = 'now';
				$type = 'string';
				// fall through
			case 'string':
				if (!(is_numeric($time) && ($time > 21000000 || $time < 19000000)))
				{
					$t_str = $time;
					if (is_numeric($time) && strlen($time) == 8) $t_str .= 'T000000';	// 'Ymd' string used in calendar to represent a date
					// $time ending in a Z (Zulu or UTC time), is unterstood by DateTime class itself
					try {
						parent::__construct($t_str,$tz);
						break;
					}
					catch(Exception $e) {
						// if string is nummeric, ignore the exception and treat string as timestamp
						if (!is_numeric($time)) throw $e;
					}
				}
				$type = 'integer';
				// fall through for timestamps
			case 'double':	// 64bit integer (timestamps > 2038) are treated on 32bit systems as double
			case 'integer':
				/* ToDo: Check if PHP5.3 setTimestamp does the same, or always expects UTC timestamp
				if (PHP_VERSION >= 5.3)
				{
					parent::__construct('now',$tz);
					$datetime->setTimestamp($time);
				}
				else*/
				{
					parent::__construct(date('Y-m-d H:i:s',$time),$tz);
				}
				break;

			case 'array':
				// JSON serialized DateTime object
				if (isset($time['timezone_type']) && !empty($time['date']) && !empty($time['timezone']))
				{
					parent::__construct($time['date'], new DateTimeZone($time['timezone']));
					break;
				}
				parent::__construct('now',$tz);
				if (isset($time['Y']))	// array format used in eTemplate
				{
					$time = array(
						'year'   => $time['Y'],
						'month'  => $time['m'],
						'day'    => $time['d'],
						'hour'   => $time['H'],
						'minute' => $time['i'],
						'second' => $time['s'],
					);
				}
				if (!empty($time['full']) && empty($time['year']))
				{
					$time['year']  = (int)substr($time['full'],0,4);
					$time['month'] = (int)substr($time['full'],4,2);
					$time['day']   = (int)substr($time['full'],6,2);
				}
				if (isset($time['year'])) $this->setDate((int)$time['year'],(int)$time['month'],isset($time['day']) ? (int)$time['day'] : (int)$time['mday']);
				$this->setTime((int)$time['hour'],(int)$time['minute'],(int)$time['second']);
				break;

			case 'object':
				if ($time instanceof \DateTime)
				{
					parent::__construct($time->format('Y-m-d H:i:s'),$time->getTimezone());
					$this->setTimezone($tz);
					break;
				}
				// fall through
			default:
				throw new Exception\AssertionFailed("Not implemented for type ($type)$time!");
		}
	}

	/**
	 * Like DateTime::add, but additional allow to use a string run through DateInterval::createFromDateString
	 *
	 * @param DateInterval|string $interval eg. '1 day', '-2 weeks'
	 */
	public function add($interval) : \DateTime
	{
		if (is_string($interval)) $interval = DateInterval::createFromDateString($interval);

		return parent::add($interval);
	}

	/**
	 * Set date to beginning of the week taking into account calendar weekdaystarts preference
	 */
	public function setWeekstart()
	{
		$wday = (int) $this->format('w'); // 0=sun, ..., 6=sat
		switch($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'])
		{
			case 'Sunday':
				$wstart = -$wday;
				break;
			case 'Saturday':
				$wstart =  -(6-$wday);
				break;
			case 'Monday':
			default:
				$wstart = -($wday ? $wday-1 : 6);
				break;
		}
		if ($wstart) $this->add($wstart.'days');
	}

	/**
	 * return SQL implementing filtering by date
	 *
	 * @param string $name
	 * @param int &$start
	 * @param int &$end
	 * @param string $column name of timestamp column to use in returned sql
	 * @param array $filters $name => list($syear,$smonth,$sday,$sweek,$eyear,$emonth,$eday,$eweek) pairs with offsets
	 * @return string
	 */
	public static function sql_filter($name, &$start, &$end, $column, array $filters=array())
	{
		if ($name == 'custom' && $start)
		{
			$start = new DateTime($start);
			$start->setTime(0, 0, 0);

			if ($end)
			{
				$end = new DateTime($end);
				$end->setTime(0, 0, 0);
				$end->add('+1day');
			}
		}
		else
		{
			if (!isset($filters[$name]))
			{
				return '1=1';
			}
			$start = new DateTime('now');
			$start->setTime(0, 0, 0);
			$end   = new DateTime('now');
			$end->setTime(0, 0, 0);

			$year  = (int) $start->format('Y');
			$month = (int) $start->format('m');

			list($syear,$smonth,$sday,$sweek,$eyear,$emonth,$eday,$eweek) = $filters[$name];

			// Handle quarters
			if(stripos($name, 'quarter') !== false)
			{
				$start->setDate($year, ((int)floor(($smonth+$month) / 3.1)) * 3 + 1, 1);
				$end->setDate($year, ((int)floor(($emonth+$month) / 3.1)+1) * 3 + 1, 1);
			}
			elseif ($syear || $eyear)
			{
				$start->setDate($year+$syear, 1, 1);
				$end->setDate($year+$eyear, 1, 1);
			}
			elseif ($smonth || $emonth)
			{
				$start->setDate($year, $month+$smonth, 1);
				$end->setDate($year, $month+$emonth, 1);
			}
			elseif ($sday || $eday)
			{
				if ($sday) $start->add($sday.'days');
				if ($eday) $end->add($eday.'days');
			}
			elseif ($sweek || $eweek)
			{
				$start->setWeekstart();
				if ($sweek) $start->add($sweek.'weeks');
				$end->setWeekstart();
				if ($eweek) $end->add($eweek.'weeks');
			}
		}
		// convert start + end from user to servertime for the filter
		$sql = '('.DateTime::user2server($start, 'ts').' <= '.$column;
		if($end)
		{
			$sql .=' AND '.$column.' < '.DateTime::user2server($end, 'ts');

			// returned timestamps: $end is an inclusive date, eg. for today it's equal to start!
			$end->add('-1day');
			$end = $end->format('ts');
		}
		$sql .= ')';
		//error_log(__METHOD__."('$name', ...) syear=$syear, smonth=$smonth, sday=$sday, sweek=$sweek, eyear=$eyear, emonth=$emonth, eday=$eday, eweek=$eweek --> start=".$start->format().', end='.$end->format().", sql='$sql'");

		$start = $start->format('ts');

		return $sql;
	}

	/**
	 * Set user timezone, according to user prefs: converts current time to user time
	 *
	 * Does nothing if self::$user_timezone is current timezone!
	 *
	 * @return self to allow chaining
	 */
	public function setUser()
	{
		$this->setTimezone(self::$user_timezone);

		return $this;
	}

	/**
	 * Set server timezone: converts current time to server time
	 *
	 * Does nothing if self::$server_timezone is current timezone!
	 *
	 * @return self to allow chaining
	 */
	public function setServer()
	{
		$this->setTimezone(self::$server_timezone);

		return $this;
	}

	/**
	 * Format DateTime object as a specific type or string
	 *
	 * EGroupware's integer timestamp is NOT the usual UTC timestamp, but has a timezone offset applied!
	 * Use $type === 'utc' or getTimestamp() method to get a regular timestamp in UTC.
	 *
	 * @param string $type ='' 'integer'|'ts'=timestamp, 'server'=timestamp in servertime, 'string'='Y-m-d H:i:s', 'object'=DateTime,
	 * 		'array'=array with values for keys ('year','month','day','hour','minute','second','full','raw') or string with format
	 * 		true = date only, false = time only as in user prefs, '' = date+time as in user prefs, 'utc'=regular timestamp in UTC
	 * @return int|string|array|datetime see $type
	 */
	#[\ReturnTypeWillChange]
	public function format($type='')
	{
		switch((string)$type)
		{
			case '':	// empty string:  date and time as in user prefs
			//case '':	// boolean false: time as in user prefs
			case '1':	// boolean true:  date as in user prefs
				if (is_bool($type))
				{
					$type = $type ? self::$user_dateformat : self::$user_timeformat;
				}
				else
				{
					$type = self::$user_dateformat.', '.self::$user_timeformat;
				}
				break;

			case 'string':
				$type = self::DATABASE;
				break;

			case 'server':	// timestamp in servertime
				$this->setServer();
				// fall through
			case 'integer':
			case 'ts':
				// EGroupware's integer timestamp is NOT the usual UTC timestamp, but has a timezone offset applied!
				return mktime(parent::format('H'),parent::format('i'),parent::format('s'),parent::format('m'),parent::format('d'),parent::format('Y'));
			case 'utc':	// alias for "U" / timestamp in UTC
				return $this->getTimestamp();
			case 'object':
			case 'datetime':
			case 'egw_time':
			case 'DateTime':
				return clone($this);

			case 'array':
				$arr = array(
					'year'   => (int)parent::format('Y'),
					'month'  => (int)parent::format('m'),
					'day'    => (int)parent::format('d'),
					'hour'   => (int)parent::format('H'),
					'minute' => (int)parent::format('i'),
					'second' => (int)parent::format('s'),
					'full'   => parent::format('Ymd'),
				);
				$arr['raw'] = mktime($arr['hour'],$arr['minute'],$arr['second'],$arr['month'],$arr['day'],$arr['year']);
				return $arr;

			case 'date_array':	// array with short keys used by date: Y, m, d, H, i, s (used in eTemplate)
				return array(
					'Y' => (int)parent::format('Y'),
					'm' => (int)parent::format('m'),
					'd' => (int)parent::format('d'),
					'H' => (int)parent::format('H'),
					'i' => (int)parent::format('i'),
					's' => (int)parent::format('s'),
				);
		}
		// default $type contains string with format
		return parent::format($type);
	}

	/**
	 * Convert a server time into a user time
	 *
	 * @param int|string|array|DateTime $time
	 * @param string $type =null type or return-value, default (null) same as $time
	 * @return int|string|array|datetime null if time could not be parsed
	 */
	public static function server2user($time,$type=null)
	{
		$typeof='DateTime';
		if (!($time instanceof DateTime))
		{
			try
			{
				$time = new DateTime($time, self::$server_timezone, $typeof);
			}
			catch(Exception $e)
			{
				unset($e);
				return null;	// time could not be parsed
			}
		}
		$time->setUser();

		if (is_null($type)) $type = $typeof;

		//echo "<p>".__METHOD__."($time,$type) = ".print_r($format->format($type),true)."</p>\n";
		return $time->format($type);
	}

	/**
	 * Convert a user time into a server time
	 *
	 * @param int|string|array|datetime $time
	 * @param string $type =null type or return-value, default (null) same as $time
	 * @return int|string|array|datetime null if time could not be parsed
	 */
	public static function user2server($time,$type=null)
	{
		$typeof='DateTime';
		if (!($time instanceof DateTime))
		{
			try
			{
				$time = new DateTime($time,self::$user_timezone,$typeof);
			}
			catch(Exception $e)
			{
				unset($e);
				return null;	// time could not be parsed
			}
		}
		$time->setServer();

		if (is_null($type)) $type = $typeof;

		//echo "<p>".__METHOD__."($time,$type) = ".print_r($format->format($type),true)."</p>\n";
		return $time->format($type);
	}

	/**
	 * Convert time to a specific format or string, static version of DateTime::format()
	 *
	 * @param int|string|array|DateTime $time ='now' see constructor
	 * @param string $type ='' 'integer'|'ts'=timestamp, 'server'=timestamp in servertime, 'string'='Y-m-d H:i:s', 'object'=DateTime,
	 * 		'array'=array with values for keys ('year','month','day','hour','minute','second','full','raw') or string with format
	 * 		true = date only, false = time only as in user prefs, '' = date+time as in user prefs
	 * @return int|string|array|datetime see $type, null if time could not be parsed
	 */
	public static function to($time='now',$type='')
	{
		if (!($time instanceof DateTime))
		{
			try
			{
				// Try user format first
				$time = static::createFromUserFormat($time);
			}
			catch(\Exception $e)
			{
				unset($e);
				return null;	// time could not be parsed
			}
		}
		return $time->format($type);
	}

	/**
	 * Some user formats are conflicting and cannot be reliably parsed by the
	 * normal means.  Here we agressively try various date formats (based on
	 * user's date preference) to coerce a troublesome date into a DateTime.
	 *
	 * ex: 07/08/2018 could be DD/MM/YYYY or MM/DD/YYYY
	 *
	 * Rather than trust DateTime to guess right based on its settings, we use
	 * the user's date preference to resolve the ambiguity.
	 *
	 * @param string $time
	 * @return DateTime or null
	 */
	public static function createFromUserFormat($time)
	{
		$date = null;

		// If numeric, just let normal constructor do it
		if(is_numeric($time) || is_array($time))
		{
			return new DateTime($time);
		}

		// Various date formats in decreasing preference
		$formats = array(
			'!'.static::$user_dateformat . ' ' .static::$user_timeformat.':s',
			'!'.static::$user_dateformat . '*' .static::$user_timeformat.':s',
			'!'.static::$user_dateformat . '* ' .static::$user_timeformat,
			'!'.static::$user_dateformat . '*',
			'!'.static::$user_dateformat,
			'!Y-m-d\TH:i:s'
		);
		// Try the different formats, stop when one works
		foreach($formats as $f)
		{
			try {
				$date = static::createFromFormat(
					$f,
					$time,
					static::$user_timezone
				);
				if($date) break;
			} catch (\Exception $e) {

			}
		}
		// Need correct class, createFromFormat() gives parent
		return new DateTime($date ? $date : $time);
	}

	/**
	 * Setter for user timezone, should be called after reading user preferences
	 *
	 * @param string $tz timezone, eg. 'Europe/Berlin' or 'UTC'
	 * @param string $dateformat ='' eg. 'Y-m-d' or 'd.m.Y'
	 * @param string|int $timeformat ='' integer 12, 24, or format string eg. 'H:i'
	 * @return DateTimeZone
	 */
	public static function setUserPrefs($tz,$dateformat='',$timeformat='')
	{
		//echo "<p>".__METHOD__."('$tz','$dateformat','$timeformat') ".function_backtrace()."</p>\n";
		if (!empty($dateformat)) self::$user_dateformat = $dateformat;

		switch($timeformat)
		{
			case '':
				break;
			case '24':
				self::$user_timeformat = 'H:i';
				break;
			case '12':
				self::$user_timeformat = 'h:i a';
				break;
			default:
				self::$user_timeformat = $timeformat;
				break;
		}
		try {
			self::$user_timezone = new DateTimeZone($tz);
		}
		catch(\Exception $e)
		{
			unset($e);
			// silently use server timezone, as we have no means to report the wrong timezone to the user from this class
			self::$user_timezone = clone(self::$server_timezone);
		}
		return self::$user_timezone;
	}

	/**
	 * Get offset in seconds between user and server time at given time $time
	 *
	 * Compatibility method for old code. It is only valid for the given time, because of possible daylight saving changes!
	 *
	 * @param int|string|DateTime $time ='now'
	 * @return int difference in seconds between user and server time (for the given time!)
	 */
	public static function tz_offset_s($time='now')
	{
		if (!($time instanceof DateTime)) $time = new DateTime($time);

		return self::$user_timezone->getOffset($time) - self::$server_timezone->getOffset($time);
	}

	/**
	 * Init static variables, reading user prefs
	 */
	public static function init()
	{
		// if no server timezone set, use date_default_timezone_get() to determine it
		if (empty($GLOBALS['egw_info']['server']['server_timezone']))
		{
			$GLOBALS['egw_info']['server']['server_timezone'] = date_default_timezone_get();
		}
		// make sure we have a valid server timezone set
		try {
			self::$server_timezone = new DateTimeZone($GLOBALS['egw_info']['server']['server_timezone']);
		}
		catch(\Exception $e)
		{
			try {
				self::$server_timezone = new DateTimeZone(date_default_timezone_get());
			}
			catch(\Exception $e)
			{
				self::$server_timezone = new DateTimeZone('Europe/Berlin');
			}
			error_log(__METHOD__."() invalid server_timezone='{$GLOBALS['egw_info']['server']['server_timezone']}' setting now '".self::$server_timezone->getName()."'!");
			Config::save_value('server_timezone',$GLOBALS['egw_info']['server']['server_timezone'] = self::$server_timezone->getName(),'phpgwapi');
		}
		if (!isset($GLOBALS['egw_info']['user']['preferences']['common']['tz']))
		{
			$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $GLOBALS['egw_info']['server']['server_timezone'];
		}
		self::setUserPrefs($GLOBALS['egw_info']['user']['preferences']['common']['tz'],
			$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],
			$GLOBALS['egw_info']['user']['preferences']['common']['timeformat']);
	}

	/**
	 * Return "beautified" timezone list:
	 * - no depricated timezones
	 * - return UTC and oceans at the end
	 * - if (user lang is a european language), move Europe to top
	 *
	 * @return array continent|ocean => array(tz-name => tz-label incl. current time)
	 */
	public static function getTimezones()
	{
		// prepare list of timezones from php, ignoring depricated ones and sort as follows
		$tzs = array(
			'Africa'     => array(),	// Contients
			'America'    => array(),
			'Asia'       => array(),
			'Australia'  => array(),
			'Europe'     => array(),
			'Atlantic'   => array(),	// Oceans
			'Pacific'    => array(),
			'Indian'     => array(),
			'Antarctica' => array(),	// Poles
			'Arctic'     => array(),
			'UTC'        => array('UTC' => 'UTC'),
		);
		// no VTIMEZONE available in calendar_timezones --> do NOT return them
		static $no_vtimezone = array(
			'Europe/Tiraspol',
			'America/Atka',
			'America/Buenos_Aires',
			'America/Catamarca',
			'America/Coral_Harbour',
			'America/Cordoba',
			'America/Ensenada',
			'America/Fort_Wayne',
			'America/Indianapolis',
			'America/Jujuy',
			'America/Knox_IN',
			'America/Mendoza',
			'America/Porto_Acre',
			'America/Rosario',
			'America/Virgin',
			'Asia/Ashkhabad',
			'Asia/Beijing',
			'Asia/Chungking',
			'Asia/Dacca',
			'Asia/Macao',
			'Asia/Riyadh87',
			'Asia/Riyadh88',
			'Asia/Riyadh89',
			'Asia/Tel_Aviv',
			'Asia/Thimbu',
			'Asia/Ujung_Pandang',
			'Asia/Ulan_Bator',
			'Australia/ACT',
			'Australia/Canberra',
			'Australia/LHI',
			'Australia/North',
			'Australia/NSW',
			'Australia/Queensland',
			'Australia/South',
			'Australia/Tasmania',
			'Australia/Victoria',
			'Australia/West',
			'Australia/Yancowinna',
			'Pacific/Samoa',
		);
		foreach(DateTimeZone::listIdentifiers() as $name)
		{
			if (in_array($name,$no_vtimezone)) continue;	// do NOT allow to set in EGroupware, as we have not VTIMEZONE component for it
			list($continent) = explode('/',$name,2);
			if (!isset($tzs[$continent])) continue;	// old depricated timezones
			$datetime = new DateTime('now',new DateTimeZone($name));
			$tzs[$continent][$name] = str_replace(array('_','/'),array(' ',' / '),$name)."  ".$datetime->format();
			unset($datetime);
		}
		foreach($tzs as $continent => &$data)
		{
			natcasesort($data);	// sort cities
		}
		unset($data);

		// if user lang or installed langs contain a european language --> move Europe to top of tz list
		$langs = class_exists('EGroupware\\Api\\Translation') ? Translation::get_installed_langs() : array();
		if (array_intersect(array($GLOBALS['egw_info']['user']['preferences']['common']['lang'])+array_keys($langs),
			array('de','fr','it','nl','bg','ca','cs','da','el','es-es','et','eu','fi','hr','hu','lt','no','pl','pt','sk','sl','sv','tr','uk')))
		{
			$tzs = array_merge(array('Europe' => $tzs['Europe']),$tzs);
		}
		return $tzs;
	}

	/**
	 * Get user timezones (the ones user selected in his prefs), plus evtl. an extra one
	 *
	 * @param string $extra extra timezone to add, if not already included in user timezones
	 * @return array tzid => label
	 */
	public static function getUserTimezones($extra=null)
	{
		$tz = $GLOBALS['egw_info']['user']['preferences']['common']['tz'];
		$user_tzs = $GLOBALS['egw_info']['user']['preferences']['common']['tz_selection'];
		if (!is_array($user_tzs))
		{
			$user_tzs = $user_tzs ? explode(',', $user_tzs) : array();
		}
		if ($tz && !in_array($tz, $user_tzs))
		{
			$user_tzs = array_merge(array($tz),$user_tzs);
		}
		if (!$user_tzs)	// if we have no user timezones, eg. user set no pref --> use server default
		{
			$user_tzs = array($GLOBALS['egw_info']['server']['server_timezone']);
		}
		if ($extra && !in_array($extra,$user_tzs))
		{
			$user_tzs = array_merge(array($extra),$user_tzs);
		}
		$ret_user_tzs = array_combine($user_tzs,$user_tzs);
		foreach($ret_user_tzs as &$label)
		{
			$label = str_replace(array('_','/'),array(' ',' / '),$label);
		}
		return $ret_user_tzs;
	}
}
DateTime::init();