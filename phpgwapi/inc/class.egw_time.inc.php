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
 * @link http://www.php.net/manual/en/class.datetime.php
 * @link http://www.php.net/manual/en/class.datetimezone.php
 */
class egw_time extends DateTime
{
	/**
	 * DateTimeZone of server, read via date_default_timezone_get(), set by self::init()
	 *
	 * @var DateTimeZone
	 */
	static public $server_timezone;

	/**
	 * DateTimeZone of user, read from user prefs, set by self::init()
	 *
	 * @var DateTimeZone
	 */
	static public $user_timezone;

	/**
	 * Time format from user prefs, set by self::init()
	 *
	 * @var string
	 */
	static public $user_time_format = 'H:i';

	/**
	 * Date format from user prefs, set by self::init()
	 *
	 * @var string
	 */
	static public $user_date_format = 'Y-m-d';

	/**
	 * Constructor
	 *
	 * @param int|string|array|DateTime $time='now' integer timestamp, string with date+time, DateTime object or
	 * 	array with values for keys('year','month','day') or 'full' plus 'hour','minute' and optional 'second'
	 * @param DateTimeZone $tz=null timezone, default user time (PHP DateTime default to server time!)
	 * @param string &$type=null on return type of $time (optional)
	 * @return egw_time
	 */
	public function __construct($time='now',DateTimeZone $tz=null,&$type=null)
	{
		if (is_null($tz))
		{
			if (is_null(self::$user_timezone)) self::init();
			$tz = self::$user_timezone;
		}
		switch(($type = gettype($time)))
		{
			case 'NULL':
			case 'boolean':	// depricated use in calendar for 'now'
				$time = 'now';
				$type = 'string';
				// fall through
			case 'string':
				if (!(is_numeric($time) && $time > 21000000))
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
		if (is_null(self::$user_timezone)) self::init();

		$this->setTimezone(self::$user_timezone);
	}

	/**
	 * Set server timezone: converts current time to server time
	 *
	 * Does nothing if self::$server_timezone is current timezone!
	 */
	public function setServer()
	{
		if (is_null(self::$server_timezone)) self::init();

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
					$type = $type ? self::$user_date_format : self::$user_time_format;
				}
				else
				{
					$type = self::$user_date_format.', '.self::$user_time_format;
				}
				// fall through
			case 'string':
				return parent::format('Y-m-d H:i:s');

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
				return $this;

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
		}
		// default $type contains string with format
		return parent::format($type);
	}

	/**
	 * Convert a server time into a user time
	 *
	 * @param int|string|array|DateTime $time
	 * @param string $type=null type or return-value, default (null) same as $time
	 * @return int|string|array|datetime
	 */
	public static function server2user($time,$type=null)
	{
		if (is_null(self::$user_timezone)) self::init();

		if (!is_a($time,$typeof='egw_time')) $time = new egw_time($time,self::$server_timezone,$typeof);
		$time->setUser();

		if (is_null($type)) $type = $typeof;

		//echo "<p>".__METHOD__."($time,$type) = ".print_r($datetime->format($type),true)."</p>\n";
		return $time->format($type);
	}

	/**
	 * Convert a user time into a server time
	 *
	 * @param int|string|array|datetime $time
	 * @param string $type=null type or return-value, default (null) same as $time
	 * @return int|string|array|datetime
	 */
	public static function user2server($time,$type=null)
	{
		if (is_null(self::$user_timezone)) self::init();

		if (!is_a($time,$typeof='egw_time')) $time = new egw_time($time,self::$user_timezone,$typeof);
		$time->setServer();

		if (is_null($type)) $type = $typeof;

		return $time->format($type);
	}

	/**
	 * Convert time to a specific format or string, static version of egw_time::format()
	 *
	 * @param int|string|array|DateTime $time='now' see constructor
	 * @param string $type='' 'integer'|'ts'=timestamp, 'server'=timestamp in servertime, 'string'='Y-m-d H:i:s', 'object'=DateTime,
	 * 		'array'=array with values for keys ('year','month','day','hour','minute','second','full','raw') or string with format
	 * 		true = date only, false = time only as in user prefs, '' = date+time as in user prefs
	 * @return int|string|array|datetime see $type
	 */
	public static function to($time='now',$type='')
	{
		if (!is_a($time,'egw_time')) $time = new egw_time($time);

		return $time->format($type);
	}

	/**
	 * Init static variables, reading user prefs
	 */
	private static function init()
	{
		if (is_null(self::$server_timezone))
		{
			self::$server_timezone = new DateTimeZone(date_default_timezone_get());
		}
		if (is_null(self::$user_timezone) && isset($GLOBALS['egw_info']['user']['preferences']['common']))
		{
			if (empty($GLOBALS['egw_info']['user']['preferences']['common']['tz']))
			{
				throw new egw_exception_wrong_userinput(lang('You need to %1set your timezone preference%2.','<a href="'.egw::link('/index.php',array(
					'menuaction' => 'preferences.uisettings.index',
					'appname'    => 'preferences')).'">','</a>'));
			}
			self::$user_timezone = new DateTimeZone($GLOBALS['egw_info']['user']['preferences']['common']['tz']);
		}
	}
}
/*
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
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