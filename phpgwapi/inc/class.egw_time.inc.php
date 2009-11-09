<?php
/**
 * EGroupware time and timezone handling
 *
 * @package api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2009 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * EGroupware time and timezone handling class extending PHP's DateTime
 *
 * egw_time class knows 2 timezones:
 * 1. egw_time::$user_timezone   timezone of the user, defined in his prefs $GLOBALS['egw_info']['user']['preferences']['common']['tz']
 * 2. egw_time::$server_timezone timezone of the server, read via date_default_timezone_get()
 *
 * The class extends PHP5.2's DateTime object to:
 * - format date and time according to format in user prefs ($type===true: date, $type===false: time, $type==='' date+time)
 * - defaulting to a user timezone according to user prefs (not server timezone as DateTime!)
 * - deal with integer unix timestamps and DB timestamps in server-time ($type: 'ts'='integer' or 'server' timestamp in servertime)
 *
 * There are two static methods for simple conversation between server and user time:
 * - egw_time::server2user($time,$type=null)
 * - egw_time::user2server($time,$type=null)
 * (Replacing in 1.6 and previous used adding of tz_offset, which is only correct for current time)
 *
 * An other static method allows to format any time in several ways: egw_time::to($time,$type) (exceed date($type,$time)).
 *
 * The constructor of egw_time understand - in addition to DateTime - integer timestamps, array with values for
 * keys: ('year', 'month', 'day') or 'full' plus 'hour', 'minute' and optional 'second' or a DateTime object as parameter.
 * It defaults to user-time, not server time as DateTime!
 *
 * The constructor itself throws an Exception in that case (to be precise it does not handle the one thrown by DateTime constructor).
 * Static methods server2user, user2server and to return NULL, if given time could not be parsed.
 *
 * @link http://www.php.net/manual/en/class.datetime.php
 * @link http://www.php.net/manual/en/class.datetimezone.php
 */
class egw_time extends DateTime
{
	/**
	 * Database timestamp format: Y-m-d H:i:s
	 */
	const DATABASE = 'Y-m-d H:i:s';
	/**
	 * DateTimeZone of server, read via date_default_timezone_get(), set by self::init()
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
	 * @param int|string|array|DateTime $time='now' integer timestamp, string with date+time, DateTime object or
	 * 	array with values for keys('year','month','day') or 'full' plus 'hour','minute' and optional 'second'
	 * @param DateTimeZone $tz=null timezone, default user time (PHP DateTime default to server time!)
	 * @param string &$type=null on return type of $time (optional)
	 * @throws Exception if $time can NOT be parsed
	 * @return egw_time
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
					if (is_numeric($time) && strlen($time) == 8) $time .= 'T000000';	// 'Ymd' string used in calendar to represent a date
					// $time ending in a Z (Zulu or UTC time), is unterstood by DateTime class itself
					parent::__construct($time,$tz);
					break;
				}
				$type = 'integer';
				// fall through for timestamps
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
				if (is_a($time,'DateTime'))
				{
					parent::__construct($time->format('Y-m-d H:i:s'),$time->getTimezone());
					$this->setTimezone($tz);
					break;
				}
				// fall through
			default:
				throw new egw_exception_assertion_failed("Not implemented for type ($type)$time!");
		}
	}

	/**
	 * Set user timezone, according to user prefs: converts current time to user time
	 *
	 * Does nothing if self::$user_timezone is current timezone!
	 */
	public function setUser()
	{
		$this->setTimezone(self::$user_timezone);
	}

	/**
	 * Set server timezone: converts current time to server time
	 *
	 * Does nothing if self::$server_timezone is current timezone!
	 */
	public function setServer()
	{
		$this->setTimezone(self::$server_timezone);
	}

	/**
	 * Format DateTime object as a specific type or string
	 *
	 * @param string $type='' 'integer'|'ts'=timestamp, 'server'=timestamp in servertime, 'string'='Y-m-d H:i:s', 'object'=DateTime,
	 * 		'array'=array with values for keys ('year','month','day','hour','minute','second','full','raw') or string with format
	 * 		true = date only, false = time only as in user prefs, '' = date+time as in user prefs
	 * @return int|string|array|datetime see $type
	 */
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
				// ToDo: Check if PHP5.3 getTimestamp does the same, or always returns UTC timestamp
				return mktime(parent::format('H'),parent::format('i'),parent::format('s'),parent::format('m'),parent::format('d'),parent::format('Y'));

			case 'object':
			case 'datetime':
			case 'egw_time':
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
	 * Cast object to a string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->format(self::DATABASE);
	}

	/**
	 * Convert a server time into a user time
	 *
	 * @param int|string|array|DateTime $time
	 * @param string $type=null type or return-value, default (null) same as $time
	 * @return int|string|array|datetime null if time could not be parsed
	 */
	public static function server2user($time,$type=null)
	{
		if (!is_a($time,$typeof='egw_time'))
		{
			try
			{
				$time = new egw_time($time,self::$server_timezone,$typeof);
			}
			catch(Exception $e)
			{
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
	 * @param string $type=null type or return-value, default (null) same as $time
	 * @return int|string|array|datetime null if time could not be parsed
	 */
	public static function user2server($time,$type=null)
	{
		if (!is_a($time,$typeof='egw_time'))
		{
			try
			{
				$time = new egw_time($time,self::$user_timezone,$typeof);
			}
			catch(Exception $e)
			{
				return null;	// time could not be parsed
			}
		}
		$time->setServer();

		if (is_null($type)) $type = $typeof;

		//echo "<p>".__METHOD__."($time,$type) = ".print_r($format->format($type),true)."</p>\n";
		return $time->format($type);
	}

	/**
	 * Convert time to a specific format or string, static version of egw_time::format()
	 *
	 * @param int|string|array|DateTime $time='now' see constructor
	 * @param string $type='' 'integer'|'ts'=timestamp, 'server'=timestamp in servertime, 'string'='Y-m-d H:i:s', 'object'=DateTime,
	 * 		'array'=array with values for keys ('year','month','day','hour','minute','second','full','raw') or string with format
	 * 		true = date only, false = time only as in user prefs, '' = date+time as in user prefs
	 * @return int|string|array|datetime see $type, null if time could not be parsed
	 */
	public static function to($time='now',$type='')
	{
		if (!is_a($time,'egw_time'))
		{
			try
			{
				$time = new egw_time($time);
			}
			catch(Exception $e)
			{
				return null;	// time could not be parsed
			}
		}
		return $time->format($type);
	}

	/**
	 * Setter for user timezone, should be called after reading user preferences
	 *
	 * @param string $tz timezone, eg. 'Europe/Berlin' or 'UTC'
	 * @param string $dateformat='' eg. 'Y-m-d' or 'd.m.Y'
	 * @param string|int $timeformat='' integer 12, 24, or format string eg. 'H:i'
	 * @throws egw_exception_wrong_userinput if invalid $tz parameter
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
		catch(Exception $e)
		{
			throw new egw_exception_wrong_userinput(lang('You need to %1set your timezone preference%2.','<a href="'.egw::link('/index.php',array(
				'menuaction' => 'preferences.uisettings.index',
				'appname'    => 'preferences')).'">','</a>'));
		}
		return self::$user_timezone;
	}

	/**
	 * Get offset in seconds between user and server time at given time $time
	 *
	 * Compatibility method for old code. It is only valid for the given time, because of possible daylight saving changes!
	 *
	 * @param int|string|DateTime $time='now'
	 * @return int difference in seconds between user and server time (for the given time!)
	 */
	public static function tz_offset_s($time='now')
	{
		if (!is_a($time,'DateTime')) $time = new egw_time($time);

		return egw_time::$user_timezone->getOffset($time) - egw_time::$server_timezone->getOffset($time);
	}

	/**
	 * Init static variables, reading user prefs
	 */
	public static function init()
	{
		self::$server_timezone = new DateTimeZone(date_default_timezone_get());
		if (isset($GLOBALS['egw_info']['user']['preferences']['common']['tz']))
		{
			self::setUserPrefs($GLOBALS['egw_info']['user']['preferences']['common']['tz'],
				$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],
				$GLOBALS['egw_info']['user']['preferences']['common']['timeformat']);
		}
		else
		{
			self::$user_timezone = clone(self::$server_timezone);
		}
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
			list($continent,$rest) = explode('/',$name,2);
			if (!isset($tzs[$continent])) continue;	// old depricated timezones
			$datetime = new egw_time('now',new DateTimeZone($name));
			$tzs[$continent][$name] = str_replace(array('_','/'),array(' ',' / '),$name).' &nbsp; '.$datetime->format();
			unset($datetime);
		}
		foreach($tzs as $continent => &$data)
		{
			natcasesort($data);	// sort cities
		}
		unset($data);

		// if user lang or installed langs contain a european language --> move Europe to top of tz list
		$langs = translation::get_installed_langs();
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
		$user_tzs = explode(',',$GLOBALS['egw_info']['user']['preferences']['common']['tz_selection']);
		if (count($user_tzs) <= 1)
		{
			$user_tzs = $tz ? array($tz) : array();
		}
		if ($tz && !in_array($tz,$user_tzs))
		{
			$user_tzs = array_merge(array($tz),$user_tzs);
		}
		if (!$user_tzs)	// if we have no user timezones, eg. user set no pref --> use server default
		{
			$user_tzs = array(date_default_timezone_get());
		}
		if ($extra && !in_array($extra,$user_tzs))
		{
			$user_tzs = array_merge(array($extra),$user_tzs);
		}
		$user_tzs = array_combine($user_tzs,$user_tzs);
		foreach($user_tzs as $name => &$label)
		{
			$label = str_replace(array('_','/'),array(' ',' / '),$label);
		}
		//_debug_array($user_tzs);
		return $user_tzs;
	}
}
egw_time::init();

/*
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
	// test timestamps/dates before 1970
	foreach(array('19690811',-3600,'-119322000') as $ts)
	{
		try {
			echo "<p>egw_time::to($ts,'Y-m-d H:i:s')=".egw_time::to($ts,'Y-m-d H:i:s')."</p>\n";
			$et = new egw_time($ts);
			echo "<p>egw_time($ts)->format('Y-m-d H:i:s')=".$et->format('Y-m-d H:i:s')."</p>\n";
			$dt = new DateTime($ts);
			echo "<p>DateTime($ts)->format('Y-m-d H:i:s')=".$dt->format('Y-m-d H:i:s')."</p>\n";
		} catch(Exception $e) {
			echo "<p><b>Exception</b>: ".$e->getMessage()."</p>\n";
		}
	}
	// user time is UTC
	echo "<p>user timezone = ".($GLOBALS['egw_info']['user']['preferences']['common']['tz'] = 'UTC').", server timezone = ".date_default_timezone_get()."</p>\n";

	$time = time();
	echo "<p>time=$time=".date('Y-m-d H:i:s',$time)."(server) =".egw_time::server2user($time,'Y-m-d H:i:s')."(user) =".egw_time::server2user($time,'ts')."(user)=".date('Y-m-d H:i:s',egw_time::server2user($time,'ts'))."</p>\n";

	echo "egw_time::to(array('full' => '20091020', 'hour' => 12, 'minute' => 0))='".egw_time::to(array('full' => '20091020', 'hour' => 12, 'minute' => 0))."'</p>\n";

	$ts = egw_time::to(array('full' => '20091027', 'hour' => 10, 'minute' => 0),'ts');
	echo "<p>2009-10-27 10h UTC timestamp=$ts --> server time = ".egw_time::user2server($ts,'')." --> user time = ".egw_time::server2user(egw_time::user2server($ts),'')."</p>\n";

	$ts = egw_time::to(array('full' => '20090627', 'hour' => 10, 'minute' => 0),'ts');
	echo "<p>2009-06-27 10h UTC timestamp=$ts --> server time = ".egw_time::user2server($ts,'')." --> user time = ".egw_time::server2user(egw_time::user2server($ts),'')."</p>\n";
}
*/
