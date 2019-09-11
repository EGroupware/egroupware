<?php
/**
 * EGroupware - Calendar's timezone information
 *
 * Timezone information get imported from SQLite database, "borrowed" of Lighting TB extension.
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-19 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * Class for timezone information
 *
 * This class serves two purposes:
 * - convert between TZID strings and nummeric tz_id's stored in database
 * - get iCal VTIMEZONE component for a TZID (data from Lighting extension)
 *
 * Recommendations about timezone handling in calendars:
 * @link http://www.calconnect.org/publications/icalendartimezoneproblemsandrecommendationsv1.0.pdf
 *
 * Mapping Windows timezone to standard TZID's:
 * @link http://unicode.org/repos/cldr-tmp/trunk/diff/supplemental/windows_tzid.html
 *
 * UTC is treated specially: it's implicitly mapped to tz_id=-1 (to be able to store it for events),
 * but calendar_ical creates NO VTIMEZONE component for it.
 */
class calendar_timezones
{
	/**
	 * Methods callable via menuation
	 *
	 * @var array
	 */
	public $public_functions = array(
		'update' => true,
	);

	/**
	 * Name of timezone table
	 */
	const TABLE = 'egw_cal_timezones';

	/**
	 * Cached timezone data (reference to session created by init_static)
	 *
	 * @var array id => data
	 */
	protected static $tz_cache = array();

	/**
	 * Cached timezone data (reference to session created by init_static)
	 *
	 * @var array tzid => id
	 */
	protected static $tz2id = array();

	/**
	 * Get DateTimeZone object for a given TZID
	 *
	 * We use our database to replace eg. Windows timezones not understood by PHP with their standard TZID
	 *
	 * @param string $tzid
	 * @return DateTimeZone
	 * @throws Exception if called with an unknown TZID
	 */
	public static function DateTimeZone($tzid)
	{
		if (($id = self::tz2id($tzid,'alias')))
		{
			$tzid = self::id2tz($id);
		}
		return new DateTimeZone($tzid);
	}

	/**
	 * Get the nummeric id (or other data) for a given TZID
	 *
	 * Examples:
	 * - calendar_timezone::tz2id('Europe/Berlin') returns nummeric id for given TZID
	 * - calendar_timezone::tz2id('Europe/Berlin','component') returns VTIMEZONE component for given TZID
	 *
	 * @param string $tzid TZID
	 * @param string $what ='id' what to return, default id, null for whole array
	 * @return int tz_id or null if not found
	 */
	public static function tz2id($tzid,$what='id')
	{
		$id =& self::$tz2id[$tzid];

		if (!isset($id))
		{
			if (($data = $GLOBALS['egw']->db->select(self::TABLE,'*',array(
				'tz_tzid' => $tzid,
			),__LINE__,__FILE__,false,'','calendar')->fetch()))
			{
				$id = $data['tz_id'];
				self::$tz_cache[$id] = Api\Db::strip_array_keys($data,'tz_');
			}
		}
		// check if we can find a 3-part America timezone eg. check 'America/Argentina/Buenos_Aires' for 'America/Buenos_Aires'
		if (!isset($id) && stripos($tzid, 'America/') === 0 && count($parts = explode('/', $tzid)) == 2)
		{
			if (($data = $GLOBALS['egw']->db->select(self::TABLE,'*',array(
				'tz_tzid LIKE '.$GLOBALS['egw']->db->quote($parts[0].'/%/'.$parts[1]),
			),__LINE__,__FILE__,false,'','calendar')->fetch()))
			{
				$id = $data['tz_id'];
				self::$tz_cache[$id] = Api\Db::strip_array_keys($data,'tz_');
			}
		}
		if (isset($id) && $what != 'id')
		{
			return self::id2tz($id,$what);
		}
		return $id;
	}

	/**
	 * Get timezone data for a given nummeric id
	 *
	 * if NOT tzid or alias queried, we automatically resolve an evtl. alias
	 *
	 * Example:
	 * - calendar_timezone::id2tz($id) returns TZID
	 * - calendar_timezone::id2tz($id,'component') returns VTIMEZONE component for the given id
	 *
	 * @param int $id
	 * @param string $what ='tzid' what data to return or null for whole data array, with keys 'id', 'tzid', 'component', 'alias', 'latitude', 'longitude'
	 * @return mixed false: if not found
	 */
	public static function id2tz($id,$what='tzid')
	{
		$data =& self::$tz_cache[$id];

		if (!isset($data))
		{
			if (($data = $GLOBALS['egw']->db->select(self::TABLE,'*',array(
				'tz_id' => $id,
			),__LINE__,__FILE__,false,'','calendar')->fetch()))
			{
				$data = Api\Db::strip_array_keys($data,'tz_');
				self::$tz2id[$data['tzid']] = $id;
			}
		}
		// if not tzid queried, resolve aliases automatically
		if ($data && $data['alias'] && $what != 'tzid' && $what != 'alias')
		{
			$data = self::id2tz($data['alias'],null);
		}
		if ($what === 'component')
		{
			// version 2019b no longer contains BEGIN/END:TIMEZONE and TZID
			return "BEGIN:VTIMEZONE\r\nTZID:$data[tzid]\r\n$data[component]\r\nEND:VTIMEZONE";
		}
		return !$data ? $data : ($what ? $data[$what] : $data);
	}

	/**
	 * Import messages from init_static, if import happend in that request
	 *
	 * @var string
	 */
	protected static $import_msg;

	/**
	 * Init static variables for session and check for updated timezone information
	 *
	 * As we use returned references from the session, we do NOT need to care about storing the information explicitly
	 */
	public static function init_static()
	{
		self::$tz_cache =& Api\Cache::getSession(__CLASS__,'tz_cache');
		self::$tz2id =& Api\Cache::getSession(__CLASS__,'tz2id');

		// init cache with mapping UTC <--> -1, as UTC is no real timezone, but we need to be able to use it in calendar
		if (!is_array(self::$tz2id))
		{
			self::$tz_cache = array('-1' => array(
				'tzid' => 'UTC',
				'id' => -1,
			));
			self::$tz2id = array('UTC' => -1);
		}

		// check for updated timezones once per session
		if (!Api\Cache::getSession(__CLASS__, 'tzs_checked'))
		{
			$updated = false;
			$msg = self::import_zones($updated);
			if ($updated) error_log($msg);	// log that timezones have been updated

			$alias_msg = self::import_tz_aliases($updated);
			if ($updated) error_log($alias_msg);	// log that timezone aliases have been updated

			self::$import_msg = $msg.'<br/>'.$alias_msg;

			Api\Cache::setSession(__CLASS__, 'tzs_checked', true);
		}
	}

	/**
	 * Import timezones from zones.json file from Thunderbird
	 *
	 * @param boolean &$updated=null on return true if update was neccessary, false if tz's were already up to date
	 * @param string $file ='calendar/setup/timezones.sqlite' filename relative to EGW_SERVER_ROOT
	 * @return string message about update
	 * @throws Api\Exception\WrongParameter if $file is not readable or wrong format/version
	 * @throws Api\Exception\WrongUserinput if no PDO sqlite support
	 * @throws Api\Exception\WrongUserinput for broken sqlite extension
	 * @link https://hg.mozilla.org/comm-central/raw-file/tip/calendar/timezones/zones.json
	 */
	public static function import_zones(&$updated=null, $file='calendar/setup/zones.json')
	{
		$path = EGW_SERVER_ROOT.'/'.$file;

		if (!file_exists($path) || !is_readable($path) || !($fp = fopen($path, 'r')))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."('$file') not found or readable!");
		}
		// only read a couple of bytes to parse version
		$json = fread($fp, 80);
		$matches = null;
		if (!preg_match('/"version": *"([^"]+)"/', $json, $matches))
		{
			throw new Api\Exception\WrongParameter('Could not read timezoneversion!');
		}
		$tz_version = $matches[1];
		$config = Api\Config::read('phpgwapi');
		//echo "<p>tz_version($path)=$tz_version, tz_db_version=$config[tz_version]</p>\n";
		if ($tz_version === $config['tz_version'])
		{
			$updated = false;
			fclose($fp);
			return lang('Nothing to update, version is already %1.',$config['tz_version']);	// we already have the right
		}
		$json .= fread($fp, 1024*1024);
		fclose($fp);
		if (!($zones = json_decode($json, true)) || !isset($zones['aliases']) || !isset($zones['zones']))
		{
			throw new Api\Exception\WrongParameter('Could not parse zones.json!');
		}
		// import zones first and then aliases
		$tz2id = array();
		foreach(array('zones', 'aliases') as $type)
		{
			foreach($zones[$type] as $tzid => $data)
			{
				if ($type == 'aliases')
				{
					$data = array('alias' => $tz2id[$data['aliasTo']]);
					if (!$data['alias']) continue;	// there's no such tzid
				}
				// check if already in database
				$tz2id[$tzid] = $GLOBALS['egw']->db->select('egw_cal_timezones','tz_id',array(
						'tz_tzid' => $tzid,
					),__LINE__,__FILE__,false,'','calendar')->fetchColumn();

				$GLOBALS['egw']->db->insert('egw_cal_timezones',array(
					'tz_alias' => $data['alias'],
					'tz_latitude' => $data['latitude'],
					'tz_longitude' => $data['longitude'],
					'tz_component' => implode("\r\n", (array)$data['ics']),
				),array(
					'tz_tzid' => $tzid,
				),__LINE__,__FILE__,'calendar');

				// only query last insert id, if not already in database (gives warning for PostgreSQL)
				if (!$tz2id[$tzid]) $tz2id[$tzid] = $GLOBALS['egw']->db->get_last_insert_id('egw_cal_timezones','tz_id');
			}
		}
		Api\Config::save_value('tz_version', $tz_version, 'phpgwapi');

		//_debug_array($tz2id);
		$updated = true;
		return lang('Timezones updated to version %1 (%2 records updated).',$tz_version,count($tz2id));
	}

	/**
	 * Import timezone aliases
	 *
	 * @param boolean &$updated=null on return true if update was neccessary, false if tz's were already up to date
	 * @param string $file ='calendar/setup/tz_aliases.inc.php' filename relative to EGW_SERVER_ROOT
	 * @return string message about update
	 * @throws Api\Exception\WrongParameter if $file is not readable or wrong format/version
	 */
	public static function import_tz_aliases(&$updated=null,$file='calendar/setup/tz_aliases.inc.php')
	{
		$path = EGW_SERVER_ROOT.'/'.$file;

		if (!file_exists($path) || !is_readable($path))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."('$file') not found or readable!");
		}
		$config = Api\Config::read('phpgwapi');
		$tz_aliases_mtime = date('Y-m-d H:i:s', filemtime($path));
		if ($tz_aliases_mtime === $config['tz_aliases_mtime'])
		{
			$updated = false;
			return lang('Nothing to update, version is already %1.',$tz_aliases_mtime);
		}
		$tz_aliases = array();
		include($path);	// sets $tz_aliases

		$updates = 0;
		foreach($tz_aliases as $alias => $tzid)
		{
			if ((!($alias_id=self::tz2id($alias, 'alias')) || self::id2tz($alias_id, 'tzid') !== $tzid) &&	// not in DB or different
				($tz_id = self::tz2id($tzid)))	// given tzid for alias exists in DB
			{
				$GLOBALS['egw']->db->insert('egw_cal_timezones',array(
					'tz_alias' => $tz_id,
				),array(
					'tz_tzid' => $alias,
				),__LINE__,__FILE__,'calendar');
				++$updates;
			}
			//error_log(__METHOD__."() alias=$alias, tzid=$tzid --> self::tz2id('$alias', 'alias') = ".array2string($alias_id).",  self::tz2id('$tzid')=".array2string($tz_id));
		}
		Api\Config::save_value('tz_aliases_mtime',$tz_aliases_mtime,$app='phpgwapi');

		//_debug_array($tz2id);
		$updated = true;
		return lang('Timezones aliases updated to version %1 (%2 records updated).', $tz_aliases_mtime, $updates);
	}

	/**
	 * Admin >> Update timezones
	 *
	 */
	public function update()
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		if (empty(self::$import_msg))
		{
			self::$import_msg = self::import_zones();
			self::$import_msg .= '<br/>'.self::import_tz_aliases();
		}
		$GLOBALS['egw']->framework->render('<h3>'.self::$import_msg.'</h3>', lang('Update timezones'), false);
	}

	/**
	 * Add VTIMEZONE component to VCALENDAR
	 *
	 * @param Horde_Icalendar $vcal
	 * @param string $tzid
	 * @return boolean false if no vtimezone component available, true on success
	 */
	public static function add_vtimezone(Horde_Icalendar $vcal, $tzid)
	{
		// check if we have vtimezone component data for $tzid
		if (!($vtimezone = calendar_timezones::tz2id($tzid, 'component')))
		{
			return false;
		}
		// $vtimezone is a string with a single VTIMEZONE component, afaik Horde_Icalendar can not add it directly
		// --> we have to parse it and let Horde_Icalendar add it again
		$horde_vtimezone = Horde_Icalendar::newComponent('VTIMEZONE',$container=false);
		$horde_vtimezone->parsevCalendar($vtimezone,'VTIMEZONE');
		// DTSTART is in UTC time, Horde_Icalendar parses it in server timezone, which we need to set again for printing
		$standard = $horde_vtimezone->findComponent('STANDARD');
		if (is_a($standard, 'Horde_Icalendar'))
		{
			$time = $standard->getAttribute('DTSTART');
			$dtstart = new Api\DateTime($time, Api\DateTime::$server_timezone);
			$dtstart->setTimezone(Api\DateTime::$server_timezone);
			$standard->setAttribute('DTSTART', $dtstart->format('Ymd\THis'), array(), false);
		}
		$daylight = $horde_vtimezone->findComponent('DAYLIGHT');
		if (is_a($daylight, 'Horde_Icalendar'))
		{
			$time = $daylight->getAttribute('DTSTART');
			$dtstart = new Api\DateTime($time, Api\DateTime::$server_timezone);
			$dtstart->setTimezone(Api\DateTime::$server_timezone);
			$daylight->setAttribute('DTSTART', $dtstart->format('Ymd\THis'), array(), false);
		}
		//error_log($vtimezone); error_log($horde_vtimezone->_exportvData('VTIMEZONE'));
		$vcal->addComponent($horde_vtimezone);

		return true;
	}

	/**
	 * Query timezone of a given user, returns 'tzid' or VTIMEZONE 'component'
	 *
	 * @param int $user =null
	 * @param string $type ='vcalendar' 'tzid' or everything tz2id supports, default 'vcalendar' = full vcalendar component
	 * @return string
	 */
	public static function user_timezone($user=null, $type='vcalendar')
	{
		if (!$user || $user == $GLOBALS['egw_info']['user']['account_id'])
		{
			$tzid = $GLOBALS['egw_info']['user']['preferences']['common']['tz'];
		}
		else
		{
			$prefs_obj = new Api\Preferences($user);
			$prefs = $prefs_obj->read();
			$tzid = $prefs['common']['tz'];
		}
		if (!$tzid) $tzid = Api\DateTime::$server_timezone->getName();

		switch ($type)
		{
			case 'vcalendar':
				// checking type of $val, now we included the object definition (no need to always include it!)
				$vcal = new Horde_Icalendar;
				$vcal->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
					strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
				self::add_vtimezone($vcal, $tzid);
				$tzid = $vcal->exportvCalendar('utf-8');
				break;
			case 'tzid':
				break;
			default:
				$tzid = self::tz2id($tzid,$type == 'vcalendar' ? 'component' : $type);
				break;
		}
		return $tzid;
	}
}
/*
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login',
		)
	);
	include('../../header.inc.php');
	calendar_timezones::init_static();

//	echo "<h3>Testing availability of VTIMEZONE data for each tzid supported by PHP</h3>\n";
//	foreach(DateTimeZone::listIdentifiers() as $tz)
	echo "<h3>Testing availability of VTIMEZONE data for each TZID supported by EGroupware</h3>\n";
	foreach(call_user_func_array('array_merge',Api\DateTime::getTimezones()) as $tz => $label)
	{
		if (($id = calendar_timezones::tz2id($tz,'component')) || $tz == 'UTC')	// UTC is always supported
		{
			$found[] = $tz;
			//if (substr($tz,0,10) == 'Australia/') echo "$tz: found<br />\n";
		}
		else
		{
			$not_found[] = $tz;
			echo "$tz: <b>NOT</b> found<br />\n";
		}
	}
	echo '<h3>'.count($found).' found, '.count($not_found)." <b>NOT</b> found</h3>\n";

	if ($not_found) echo "<pre>\n\$no_vtimezone = array(\n\t'".implode("',\n\t'",$not_found)."',\n);\n</pre>\n";

	echo "<h3>Testing availability of PHP support for each TZID supported by EGroupware's timezone database:</h3>\n";
	foreach($GLOBALS['egw']->db->select('egw_cal_timezones','*',false,__LINE__,__FILE__,false,'','calendar') as $row)
	{
		try
		{
			$timezone = new DateTimeZone($row['tz_tzid']);
			//$timezone = calendar_timezones::DateTimeZone($row['tz_tzid']);
			echo $row['tz_tzid'].": available<br />\n";
		}
		catch(Exception $e)
		{
			if (($id = calendar_timezones::tz2id($row['tz_tzid'],'alias')) &&
				($alias = calendar_timezones::id2tz($id)))
			{

				try
				{
					$timezone = new DateTimeZone($alias);
					echo $row['tz_tzid']."='$alias': available through <b>alias</b><br />\n";
					unset($e);
				}
				catch(Exception $e)
				{
					// ignore
				}
			}
			if (isset($e)) echo $row['tz_tzid'].": <b>NOT</b> available<br />\n";
		}
	}
}
else*/
calendar_timezones::init_static();
