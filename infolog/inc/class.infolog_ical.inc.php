<?php
/**
 * EGroupware - InfoLog - iCalendar Parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * InfoLog: Create and parse iCal's
 */
class infolog_ical extends infolog_bo
{
	/**
	 * @var array $priority_egw2ical conversion of the priority egw => ical
	 */
	var $priority_egw2ical = array(
		0 => 9,		// low
		1 => 5,		// normal
		2 => 3,		// high
		3 => 1,		// urgent
	);

	/**
	 * @var array $priority_ical2egw conversion of the priority ical => egw
	 */
	var $priority_ical2egw = array(
		9 => 0,	8 => 0, 7 => 0,	// low
		6 => 1, 5 => 1, 4 => 1, 0 => 1,	// normal
		3 => 2,	2 => 2,	// high
		1 => 3,			// urgent
	);

	/**
	 * @var array $priority_egw2funambol conversion of the priority egw => funambol
	 */
	var $priority_egw2funambol = array(
		0 => 0,		// low
		1 => 1,		// normal
		2 => 2,		// high
		3 => 2,		// urgent
	);

	/**
	 * @var array $priority_funambol2egw conversion of the priority funambol => egw
	 */
	var $priority_funambol2egw = array(
		0 => 0,		// low
		1 => 1,		// normal
		2 => 3,		// high
	);

	/**
	 * manufacturer and name of the sync-client
	 *
	 * @var string
	 */
	var $productManufacturer = 'file';
	var $productName = '';

	/**
	* Shall we use the UID extensions of the description field?
	*
	* @var boolean
	*/
	var $uidExtension = false;

	/**
	 * user preference: Use this timezone for import from and export to device
	 *
	 * @var string
	 */
	var $tzid = null;

	/**
	 * Client CTCap Properties
	 *
	 * @var array
	 */
	var $clientProperties;

	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	var $logfile="/tmp/log-infolog-vcal";


	/**
	 * Constructor
	 *
	 * @param array $_clientProperties		client properties
	 */
	function __construct(&$_clientProperties = array())
	{
		parent::__construct();
		if ($this->log) $this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-infolog-vcal";
		$this->clientProperties = $_clientProperties;
	}

	/**
	 * Exports multiple InfoLogs
	 *
	 * @param array $tasks array of info_ids or task arrays
	 * @param string $_version ='2.0'
	 * @param string $_method =null only set for iTip messages
	 * @param string $charset ='UTF-8'
	 * @return string|boolean string with vCal or false on error (eg. no permission to read the event)
	 */
	function exportVCalendar(array $tasks, $_version='2.0', $_method=null, $charset='UTF-8')
	{
		$vcal = new Horde_Icalendar;

		foreach($tasks as $task)
		{
			if (!$this->exportVTODO($task, $_version, $_method, $charset, $vcal))
			{
				return false;
			}
		}
		return $vcal->exportVCalendar();
	}

	/**
	 * Exports one InfoLog tast to an iCalendar VTODO
	 *
	 * @param int|array $task infolog_id or infolog-tasks data
	 * @param string $_version ='2.0' could be '1.0' too
	 * @param string $_method ='PUBLISH'
	 * @param string $charset ='UTF-8' encoding of the vcalendar, default UTF-8
	 * @param Horde_Icalendar $vcal =null optional iCalendar object to add vtodo to
	 *
	 * @return string|boolean string with vCal or false on error (eg. no permission to read the event)
	 */
	function exportVTODO($task, $_version='2.0',$_method='PUBLISH', $charset='UTF-8',Horde_Icalendar $vcal=null)
	{
		if (is_array($task))
		{
			$taskData = $task;
		}
		else
		{
			if (!($taskData = $this->read($task, true, 'server'))) return false;
		}

		if ($taskData['info_id_parent'])
		{
			$parent = $this->read($taskData['info_id_parent']);
			$taskData['info_id_parent'] = $parent['info_uid'];
		}
		else
		{
			$taskData['info_id_parent'] = '';
		}

		if ($this->uidExtension)
		{
			if (!preg_match('/\[UID:.+\]/m', $taskData['info_des']))
			{
				$taskData['info_des'] .= "\n[UID:" . $taskData['info_uid'] . "]";
				if ($taskData['info_id_parent'] != '')
				{
					$taskData['info_des'] .= "\n[PARENT_UID:" . $taskData['info_id_parent'] . "]";
				}
			}
		}

		if (!empty($taskData['info_cat']))
		{
			$cats = $this->get_categories(array($taskData['info_cat']));
			$taskData['info_cat'] = $cats[0];
		}

		$taskData = Api\Translation::convert($taskData,
			Api\Translation::charset(), $charset);

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($taskData)."\n",3,$this->logfile);
		}

		if (!isset($vcal)) $vcal = new Horde_Icalendar;
		$vcal->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware InfoLog '.$GLOBALS['egw_info']['apps']['infolog']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']),array(),false);
		$vcal->setAttribute('VERSION',$_version,array(),false);
		if ($_method) $vcal->setAttribute('METHOD',$_method,array(),false);

		$tzid = $this->tzid;
		if ($tzid && $tzid != 'UTC')
		{
			// check if we have vtimezone component data for tzid of event, if not default to user timezone (default to server tz)
			if (!calendar_timezones::add_vtimezone($vcal, $tzid))
			{
				error_log(__METHOD__."() unknown TZID='$tzid', defaulting to user timezone '".Api\DateTime::$user_timezone->getName()."'!");
				calendar_timezones::add_vtimezone($vcal, Api\DateTime::$user_timezone->getName());
				$tzid = null;
			}
			if (!isset(self::$tz_cache[$tzid]))
			{
				self::$tz_cache[$tzid] = calendar_timezones::DateTimeZone($tzid);
			}
		}

		$vevent = Horde_Icalendar::newComponent('VTODO',$vcal);

		if (!isset($this->clientProperties['SUMMARY']['Size']))
		{
			// make SUMMARY a required field
			$this->clientProperties['SUMMARY']['Size'] = 0xFFFF;
			$this->clientProperties['SUMMARY']['NoTruncate'] = false;
		}
		// set fields that may contain non-ascii chars and encode them if necessary
		foreach (array(
			'SUMMARY'     => $taskData['info_subject'],
			'DESCRIPTION' => $taskData['info_des'],
			'LOCATION'    => $taskData['info_location'],
			'RELATED-TO'  => $taskData['info_id_parent'],
			'UID'		  => $taskData['info_uid'],
			'CATEGORIES'  => $taskData['info_cat'],
		) as $field => $value)
		{
			if (isset($this->clientProperties[$field]['Size']))
			{
				$size = $this->clientProperties[$field]['Size'];
				$noTruncate = $this->clientProperties[$field]['NoTruncate'];
				if ($this->log && $size > 0)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
						"() $field Size: $size, NoTruncate: " .
						($noTruncate ? 'TRUE' : 'FALSE') . "\n",3,$this->logfile);
				}
				//Horde::logMessage("VTODO $field Size: $size, NoTruncate: " .
				//	($noTruncate ? 'TRUE' : 'FALSE'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			}
			else
			{
				$size = -1;
				$noTruncate = false;
			}
			$cursize = strlen($value);
			if (($size > 0) && $cursize > $size)
			{
				if ($noTruncate)
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
							"() $field omitted due to maximum size $size\n",3,$this->logfile);
					}
					//Horde::logMessage("VTODO $field omitted due to maximum size $size",
					//	__FILE__, __LINE__, PEAR_LOG_WARNING);
					continue; // skip field
				}
				// truncate the value to size
				$value = substr($value, 0, $size -1);
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
						"() $field truncated to maximum size $size\n",3,$this->logfile);
				}
				//Horde::logMessage("VTODO $field truncated to maximum size $size",
				//	__FILE__, __LINE__, PEAR_LOG_INFO);
			}

			if (empty($value) && ($size < 0 || $noTruncate)) continue;

			if ($field == 'RELATED-TO')
			{
				$options = array('RELTYPE'	=> 'PARENT');
			}
			else
			{
				$options = array();
			}

			if ($_version == '1.0' && preg_match('/[^\x20-\x7F]/', $value))
			{
				$options['CHARSET']	= $charset;
				switch ($this->productManufacturer)
				{
					case 'groupdav':
						if ($this->productName == 'kde')
						{
							$options['ENCODING'] = 'QUOTED-PRINTABLE';
						}
						else
						{
							$options['CHARSET'] = '';

							if (preg_match('/([\000-\012\015\016\020-\037\075])/', $value))
							{
								$options['ENCODING'] = 'QUOTED-PRINTABLE';
							}
							else
							{
								$options['ENCODING'] = '';
							}
						}
						break;
					case 'funambol':
						$options['ENCODING'] = 'FUNAMBOL-QP';
				}
			}
			$vevent->setAttribute($field, $value, $options);
		}

		// only export startdate < duedate, as some clients (eg. CalDAV Sync for Android) have problems with that
		if ($taskData['info_startdate'] && (empty($taskData['info_enddate']) || $taskData['info_startdate'] <= $taskData['info_enddate']))
		{
			self::setDateOrTime($vevent, 'DTSTART', $taskData['info_startdate'], $tzid);
		}
		if ($taskData['info_enddate'])
		{
			self::setDateOrTime($vevent, 'DUE', $taskData['info_enddate'], $tzid);
		}
		if ($taskData['info_datecompleted'])
		{
			self::setDateOrTime($vevent, 'COMPLETED', $taskData['info_datecompleted'], $tzid);
		}

		$vevent->setAttribute('DTSTAMP',time());
		$vevent->setAttribute('CREATED', $taskData['info_created']);
		$vevent->setAttribute('LAST-MODIFIED', $taskData['info_datemodified']);
		$vevent->setAttribute('CLASS',$taskData['info_access'] == 'public' ? 'PUBLIC' : 'PRIVATE');
		$vevent->setAttribute('STATUS',$this->status2vtodo($taskData['info_status']));
		// we try to preserv the original infolog status as X-INFOLOG-STATUS, so we can restore it, if the user does not modify STATUS
		$vevent->setAttribute('X-INFOLOG-STATUS',$taskData['info_status']);
		$vevent->setAttribute('PERCENT-COMPLETE',$taskData['info_percent']);
		if ($this->productManufacturer == 'funambol' &&
			(strpos($this->productName, 'outlook') !== false
				|| strpos($this->productName, 'pocket pc') !== false))
		{
			$priority = (int) $this->priority_egw2funambol[$taskData['info_priority']];
		}
		else
		{
			$priority = (int) $this->priority_egw2ical[$taskData['info_priority']];
		}
		$vevent->setAttribute('PRIORITY', $priority);

		// add ATTENDEE and ORGANIZER only if ATTENDEEs other then owner are specified
		if ($taskData['info_responsible'] &&
			(count($taskData['info_responsible']) > 1 ||
				$taskData['info_responsible'][0] != $taskData['info_onwer']))
		{
			if (($url = $GLOBALS['egw']->accounts->id2name($taskData['info_owner'],'account_email')))
			{
				$url = 'MAILTO:'.$url;
			}
			else
			{
				$url = 'urn:uuid:'.Api\CalDAV::generate_uid('accounts', $taskData['info_owner']);
			}
			$vevent->setAttribute('ORGANIZER',$url,array(
				'CN'		=>	$GLOBALS['egw']->accounts->id2name($taskData['info_owner'],'account_fullname'),
				'X-EGROUPWARE-UID'	=> $taskData['info_owner'],
			), true);
			foreach($taskData['info_responsible'] as $responsible)
			{
				if (($url = $GLOBALS['egw']->accounts->id2name($responsible,'account_email')))
				{
					$url = 'MAILTO:'.$url;
				}
				else
				{
					$url = 'urn:uuid:'.Api\CalDAV::generate_uid('accounts', $responsible);
				}
				if ($responsible > 0)
				{
					$vevent->setAttribute('ATTENDEE',$url,array(
						'CN'		=>	$GLOBALS['egw']->accounts->id2name($responsible,'account_fullname'),
						'CUTYPE'	=> 'INDIVIDUAL',
						'X-EGROUPWARE-UID'	=> $responsible,
					), true);
				}
				elseif ($responsible < 0)
				{
					$vevent->setAttribute('ATTENDEE',$url,array(
						'CN'		=>	$GLOBALS['egw']->accounts->id2name($responsible),
						'CUTYPE'	=> 'GROUP',
						'X-EGROUPWARE-UID'	=> $responsible,
					), true);
				}
			}
		}
		// for CalDAV add all X-Properties previously parsed
		if ($this->productManufacturer == 'groupdav')
		{
			foreach($taskData as $name => $value)
			{
				if (substr($name, 0, 2) == '##')
				{
					if ($name[2] == ':')
					{
						if (($v = json_php_unserialize($value)) && is_array($v)) $value = $v;
						foreach((array)$value as $compvData)
						{
							$comp = Horde_Icalendar::newComponent(substr($name,3), $vevent);
							$comp->parsevCalendar($compvData,substr($name,3));
							$vevent->addComponent($comp);
						}
					}
					elseif (($attr = json_php_unserialize($value)) && is_array($attr))
					{
						$vevent->setAttribute(substr($name, 2), $attr['value'], $attr['params'], true, $attr['values']);
					}
					else
					{
						$vevent->setAttribute(substr($name, 2), $value);
					}
				}
			}
		}
		$vcal->addComponent($vevent);

		$retval = $vcal->exportvCalendar();
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($retval)."\n",3,$this->logfile);
		}
		// Horde::logMessage("exportVTODO:\n" . print_r($retval, true),
		//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $retval;
	}

	/**
	 * set date-time attribute to DATE or DATE-TIME depending on value
	 * 	00:00 uses DATE else DATE-TIME
	 *
	 * @param Horde_Icalendar_* $vevent
	 * @param string $attr attribute name
	 * @param int $time timestamp in server-time
	 * @param string $tzid	timezone to use for client, null for user-time, false for server-time
	 */
	static function setDateOrTime(&$vevent, $attr, $time, $tzid)
	{
		$params = array();
		//$time_in = $time;

		if ($tzid)
		{
			if (!isset(self::$tz_cache[$tzid]))
			{
				self::$tz_cache[$tzid] = calendar_timezones::DateTimeZone($tzid);
			}
			$tz = self::$tz_cache[$tzid];
		}
		elseif(is_null($tzid))
		{
			$tz = Api\DateTime::$user_timezone;
		}
		else
		{
			$tz = Api\DateTime::$server_timezone;
		}
		if (!is_a($time,'DateTime'))
		{
			$time = new Api\DateTime($time,Api\DateTime::$server_timezone);
		}
		$time->setTimezone($tz);

		// check for date --> export it as such
		if ($time->format('Hi') == '0000')
		{
			$arr = Api\DateTime::to($time, 'array');
			$value = array(
				'year'  => $arr['year'],
				'month' => $arr['month'],
				'mday'  => $arr['day'],
			);
			$params['VALUE'] = 'DATE';
		}
		else
		{
			if ($tzid == 'UTC')
			{
				$value = $time->format('Ymd\THis\Z');
			}
			elseif ($tzid)
			{
				$value = $time->format('Ymd\THis');
				$params['TZID'] = $tzid;
			}
			else
			{
				$value = Api\DateTime::to($time, 'ts');
			}
		}
		//error_log(__METHOD__."(, '$attr', ".array2string($time_in).', '.array2string($tzid).') tz='.$tz->getName().', value='.array2string($value).(is_int($value)?date('Y-m-d H:i:s',$value):''));
		$vevent->setAttribute($attr, $value, $params);
	}

	/**
	 * Import a VTODO component of an iCal
	 *
	 * @param string $_vcalData
	 * @param int $_taskID =-1 info_id, default -1 = new entry
	 * @param boolean $merge =false	merge data with existing entry (no longer used)
	 * @param int $user =null delegate new task to this account_id, default null
	 * @param string $charset =null The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
     * @param string $caldav_name =null CalDAV URL name-part for new entries
     * @param array $callback_data =null array with callback and further parameters, first param is task to save
     * 	signature array callback($task, $param1, ...)
	 * @return int|boolean integer info_id or false on error
	 */
	function importVTODO(&$_vcalData, $_taskID=-1, $merge=false, $user=null, $charset=null, $caldav_name=null,
		array $callback_data=null)
	{
		unset($merge);	// no longer used, but required by function signature

		if ($this->tzid)
		{
			date_default_timezone_set($this->tzid);
		}
		$taskData = $this->vtodotoegw($_vcalData,$_taskID, $charset);
		if ($this->tzid)
		{
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
		}

		if (!$taskData) return false;

		// keep the dates
		$this->time2time($taskData, $this->tzid, false);

		if (empty($taskData['info_datecompleted']))
		{
			$taskData['info_datecompleted'] = 0;
		}

		// setting owner or responsible for new tasks based on folder
		if (!is_null($user) && $_taskID <= 0)
		{
			if ($this->check_access($taskData, Acl::ADD))
			{
				$taskData['info_owner'] = $user;
			}
			elseif (!in_array($user, (array)$taskData['info_responsible']))
			{
				$taskData['info_responsible'][] = $user;
			}
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($taskData)."\n",3,$this->logfile);
		}

		if ($caldav_name)
		{
			$taskData['caldav_name'] = $caldav_name;
		}

		// make sure not to empty fields not supported by iCal or not allowed to change by CalDAV
		if ($_taskID > 0 && ($old = $this->read($_taskID,true,'server')))
		{
			// remove all values supported by iCal standard
			$old = array_diff_key($old, array_flip(array(
				'info_subject','info_des','info_location','info_cat','info_responsible',
				'info_startdate','info_enddate','info_priority','info_location',
				'info_access','info_status','info_percent','info_datecompleted',
			)));
			// remove all iCal fields not supported by EGroupware (stored like custom fields)
			foreach(array_keys($old) as $name)
			{
				if (substr($name,0,2) == '##') unset($old[$name]);
			}
			// merge in again all infolog fields not supported by iCal or not allowed to change
			$taskData = array_merge($taskData, $old);
		}
		if ($callback_data)
		{
			$callback = array_shift($callback_data);
			array_unshift($callback_data, $taskData);
			$taskData = call_user_func_array($callback, $callback_data);
		}
		return $this->write($taskData, true, true, false, false, false, 'ical');
	}

	/**
	 * Search a matching infolog entry for the VTODO data
	 *
	 * @param string $_vcalData		VTODO
	 * @param int $contentID =null 	infolog_id (or null, if unkown)
	 * @param boolean $relax =false 	if true, a weaker match algorithm is used
	 * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
	 *
	 * @return array of infolog_ids of matching entries
	 */
	function searchVTODO($_vcalData, $contentID=null, $relax=false, $charset=null)
	{
		$result = array();

		if ($this->tzid)
		{
			date_default_timezone_set($this->tzid);
		}
		$taskData = $this->vtodotoegw($_vcalData, $contentID, $charset);
		if ($this->tzid)
		{
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
		}
		if ($taskData)
		{
			if ($contentID)
			{
				$taskData['info_id'] = $contentID;
			}
			$result = $this->findInfo($taskData, $relax, $this->tzid);
		}
		return $result;
	}

	/**
	 * Convert date(-array) to timestamp used in InfoLog
	 *
	 * @param int|array $date
	 * @return int
	 */
	protected static function date2ts($date)
	{
		return is_scalar($date) ? $date : Api\DateTime::to($date, 'ts');
	}

	/**
	 * Convert VTODO into a eGW infolog entry
	 *
	 * @param string $_vcalData 	VTODO data
	 * @param int $_taskID =-1		infolog_id of the entry
	 * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
     *
	 * @return array infolog entry or false on error
	 */
	function vtodotoegw($_vcalData, $_taskID=-1, $charset=null)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_taskID)\n" .
				array2string($_vcalData)."\n",3,$this->logfile);
		}

		$vcal = new Horde_Icalendar;
		if ($charset && $charset != 'utf-8')
		{
			$_vcalData = Api\Translation::convert($_vcalData, $charset, 'utf-8');
		}
		if (!($vcal->parsevCalendar($_vcalData, 'VCALENDAR')))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"(): No vCalendar Container found!\n",3,$this->logfile);
			}
			return false;
		}

		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$taskData = false;

		foreach ($vcal->getComponents() as $component)
		{
			if (!is_a($component, 'Horde_Icalendar_Vtodo'))
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						"(): Not a vTODO container, skipping...\n",3,$this->logfile);
				}
				continue;
			}

			$taskData = array();

			if ($_taskID > 0)
			{
				$taskData['info_id'] = $_taskID;
			}
			// iOS reminder app only sets COMPLETED, but never STATUS nor PERCENT-COMPLETED
			// if we have no STATUS, set STATUS by existence of COMPLETED and/or PERCENT-COMPLETE and X-INFOLOG-STATUS
			// if we have no PERCENT-COMPLETE set it from STATUS: 0=NEEDS-ACTION, 10=IN-PROCESS, 100=COMPLETED
			try {
				$status = $component->getAttribute('STATUS');
			}
			catch (Horde_Icalendar_Exception $e)
			{
				unset($e);
				$completed = $component->getAttributeDefault('COMPLETED', null);
				$x_infolog_status = $component->getAttributeDefault('X-INFOLOG-STATUS', null);
				// check if we have a X-INFOLOG-STATUS and it's completed state is different from given COMPLETED attr
				if (is_scalar($x_infolog_status) &&
					($this->_status2vtodo[$x_infolog_status] === 'COMPLETED') != is_scalar($completed))
				{
					$percent_completed = $component->getAttributeDefault('PERCENT-COMPLETE', null);
					$status = $completed && is_scalar($completed) ? 'COMPLETED' :
						($percent_completed && is_scalar($percent_completed) && $percent_completed > 0 ? 'IN-PROCESS' : 'NEEDS-ACTION');
					$component->setAttribute('STATUS', $status);
					if (!is_scalar($percent_completed))
					{
						$component->setAttribute('PERCENT-COMPLETE', $percent_completed = $status == 'COMPLETED' ?
							100 : ($status == 'NEEDS-ACTION' ? 0 : 10));
					}
					if ($this->log) error_log(__METHOD__."() setting STATUS='$status' and PERCENT-COMPLETE=$percent_completed from COMPLETED and X-INFOLOG-STATUS='$x_infolog_status'\n",3,$this->logfile);
				}
				// new task without status --> set a default status of NEEDS-ACTION, as otherwise task is marked closed
				elseif($_taskID <= 0 && !is_scalar($x_infolog_status) && !is_scalar($completed))
				{
					$component->setAttribute('STATUS', 'NEEDS-ACTION');
				}
				else
				{
					if ($this->log) error_log(__METHOD__."() no STATUS, X-INFOLOG-STATUS='$x_infolog_status', COMPLETED".(is_scalar($completed)?'='.$completed:' not set')." --> leaving status and percent unchanged",3,$this->logfile);
				}
			}
			foreach ($component->getAllAttributes() as $attribute)
			{
				if (!$attribute['value'] && $attribute['value'] !== '0') continue;

				switch ($attribute['name'])
				{
					case 'CLASS':
						$taskData['info_access'] = strtolower($attribute['value']);
						break;

					case 'DESCRIPTION':
						$value = str_replace("\r\n", "\n", $attribute['value']);
						$matches = null;
						if (preg_match('/\s*\[UID:(.+)?\]/Usm', $value, $matches))
						{
							if (!isset($taskData['info_uid'])
									&& strlen($matches[1]) >= $minimum_uid_length)
							{
								$taskData['info_uid'] = $matches[1];
							}
							//$value = str_replace($matches[0], '', $value);
						}
						if (preg_match('/\s*\[PARENT_UID:(.+)?\]/Usm', $value, $matches))
						{
							if (!isset($taskData['info_id_parent'])
									&& strlen($matches[1]) >= $minimum_uid_length)
							{
								$taskData['info_id_parent'] = $this->getParentID($matches[1]);
							}
							//$value = str_replace($matches[0], '', $value);
						}
						$taskData['info_des'] = $value;
						break;

					case 'LOCATION':
						$taskData['info_location'] = str_replace("\r\n", "\n", $attribute['value']);
						break;

					case 'DURATION':
						if (!isset($taskData['info_startdate']))
						{
							$taskData['info_startdate']	= $component->getAttributeDefault('DTSTART', null);
						}
						$attribute['value'] += $taskData['info_startdate'];
						$taskData['##DURATION'] = $attribute['value'];
						// fall throught
					case 'DUE':
						// even as EGroupware only displays the date, we can still store the full value
						// unless infolog get's stored, it does NOT truncate the time
						$taskData['info_enddate'] = self::date2ts($attribute['value']);
						break;

					case 'COMPLETED':
						$taskData['info_datecompleted']	= self::date2ts($attribute['value']);
						break;

					case 'DTSTART':
						$taskData['info_startdate']	= self::date2ts($attribute['value']);
						break;

					case 'PRIORITY':
						if (0 <= $attribute['value'] && $attribute['value'] <= 9)
						{
							if ($this->productManufacturer == 'funambol' &&
								(strpos($this->productName, 'outlook') !== false
									|| strpos($this->productName, 'pocket pc') !== false))
							{
								$taskData['info_priority'] = (int) $this->priority_funambol2egw[$attribute['value']];
							}
							else
							{
								$taskData['info_priority'] = (int) $this->priority_ical2egw[$attribute['value']];
							}
						}
						else
						{
							$taskData['info_priority'] = 1;	// default = normal
						}
						break;

					case 'X-INFOLOG-STATUS':
						break;
					case 'STATUS':
						// check if we (still) have X-INFOLOG-STATUS set AND it would give an unchanged status (no change by the user)
						$taskData['info_status'] = $this->vtodo2status($attribute['value'],
							$component->getAttributeDefault('X-INFOLOG-STATUS', null));
						break;

					case 'SUMMARY':
						$taskData['info_subject'] = str_replace("\r\n", "\n", $attribute['value']);
						break;

					case 'RELATED-TO':
						$taskData['info_id_parent'] = $this->getParentID($attribute['value']);
						break;

					case 'CATEGORIES':
						if (!empty($attribute['value']))
						{
							$cats = $this->find_or_add_categories(explode(',',$attribute['value']), $_taskID);
							$taskData['info_cat'] = $cats[0];
						}
						break;

					case 'UID':
						if (strlen($attribute['value']) >= $minimum_uid_length)
						{
							$taskData['info_uid'] = $attribute['value'];
						}
						break;

					case 'PERCENT-COMPLETE':
						$taskData['info_percent'] = (int) $attribute['value'];
						break;

					case 'ATTENDEE':
						if (($uid = Api\CalDAV\Principals::url2uid($attribute['value'])) && is_numeric($uid))
						{
							$taskData['info_responsible'][] = $uid;
						}
						break;

					case 'ORGANIZER':
						if (($uid = Api\CalDAV\Principals::url2uid($attribute['value'])) && is_numeric($uid))
						{
							$taskData['info_owner'] = $uid;
						}
						break;

					// ignore all PROPS, we dont want to store like X-properties or unsupported props
					case 'DTSTAMP':
					case 'SEQUENCE':
					case 'CREATED':
					case 'LAST-MODIFIED':
						break;

					default:	// X- attribute or other by EGroupware unsupported property
						//error_log(__METHOD__."() $attribute[name] = ".array2string($attribute));
						// for attributes with multiple values in multiple lines, merge the values
						if (isset($taskData['##'.$attribute['name']]))
						{
							//error_log(__METHOD__."() taskData['##$attribute[name]'] = ".array2string($taskData['##'.$attribute['name']]));
							$attribute['values'] = array_merge(
								is_array($taskData['##'.$attribute['name']]) ? $taskData['##'.$attribute['name']]['values'] : (array)$taskData['##'.$attribute['name']],
								$attribute['values']);
						}
						$taskData['##'.$attribute['name']] = $attribute['params'] || count($attribute['values']) > 1 ?
							json_encode($attribute) : $attribute['value'];
						break;
				}
			}
			break;
		}
		// store included, but unsupported components like valarm as x-properties
		foreach($component->getComponents() as $comp)
		{
			$name = '##:'.strtoupper($comp->getType());
			$compvData = $comp->exportvCalendar($comp,'utf-8');
			if (isset($taskData[$name]))
			{
				$taskData[$name] = array($taskData[$name]);
				$taskData[$name][] = $compvData;
			}
			else
			{
				$taskData[$name] = $compvData;
			}
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_taskID)\n" .
				($taskData ? array2string($taskData) : 'FALSE') . "\n",3,$this->logfile);
		}
		return $taskData;
	}

	/**
	 * Export an infolog entry as VNOTE
	 *
	 * @param int $_noteID		the infolog_id of the entry
	 * @param string $_type		content type (e.g. text/plain)
	 * @param string $charset ='UTF-8' encoding of the vcalendar, default UTF-8
	 *
	 * @return string|boolean VNOTE representation of the infolog entry or false on error
	 */
	function exportVNOTE($_noteID, $_type, $charset='UTF-8')
	{
		if(!($note = $this->read($_noteID, true, 'server'))) return false;

		$note = Api\Translation::convert($note,
			Api\Translation::charset(), $charset);

		switch	($_type)
		{
			case 'text/plain':
				$txt = $note['info_subject']."\n\n".$note['info_des'];
				return $txt;

			case 'text/x-vnote':
				if (!empty($note['info_cat']))
				{
					$cats = $this->get_categories(array($note['info_cat']));
					$note['info_cat'] = Api\Translation::convert($cats[0],
						Api\Translation::charset(), $charset);
				}
				$vnote = new Horde_Icalendar_Vnote();
				$vnote->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware InfoLog '.$GLOBALS['egw_info']['apps']['infolog']['version'].'//'.
					strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
				$vnote->setAttribute('VERSION', '1.1');
				foreach (array(	'SUMMARY'		=> $note['info_subject'],
								'BODY'			=> $note['info_des'],
								'CATEGORIES'	=> $note['info_cat'],
							) as $field => $value)
				{
					$options = array();
					if (preg_match('/[^\x20-\x7F]/', $value))
					{
						$options['CHARSET']	= $charset;
						switch ($this->productManufacturer)
						{
							case 'groupdav':
								if ($this->productName == 'kde')
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								else
								{
									$options['CHARSET'] = '';

									if (preg_match('/([\000-\012\015\016\020-\037\075])/', $value))
									{
										$options['ENCODING'] = 'QUOTED-PRINTABLE';
									}
									else
									{
										$options['ENCODING'] = '';
									}
								}
								break;
							case 'funambol':
								$options['ENCODING'] = 'FUNAMBOL-QP';
						}
					}
					$vnote->setAttribute($field, $value, $options);
				}
				if ($note['info_startdate'])
				{
					$vnote->setAttribute('CREATED',$note['info_startdate']);
				}
				else
				{
					$vnote->setAttribute('CREATED',$GLOBALS['egw']->contenthistory->getTSforAction('infolog_note',$_noteID,'add'));
				}
				$vnote->setAttribute('LAST-MODIFIED',$GLOBALS['egw']->contenthistory->getTSforAction('infolog_note',$_noteID,'modify'));

				#$vnote->setAttribute('CLASS',$taskData['info_access'] == 'public' ? 'PUBLIC' : 'PRIVATE');

				$retval = $vnote->exportvCalendar();
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
						array2string($retval)."\n",3,$this->logfile);
				}
				return $retval;
		}
		return false;
	}

	/**
	 * Import a VNOTE component of an iCal
	 *
	 * @param string $_vcalData
	 * @param string $_type		content type (eg.g text/plain)
	 * @param int $_noteID =-1 info_id, default -1 = new entry
	 * @param boolean $merge =false	merge data with existing entry (no longer used)
	 * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
     *
	 * @return int|boolean integer info_id or false on error
	 */
	function importVNOTE(&$_vcalData, $_type, $_noteID=-1, $merge=false, $charset=null)
	{
		unset($merge);	// no longer used, but required by function signature
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($_vcalData)."\n",3,$this->logfile);
		}

		if (!($note = $this->vnotetoegw($_vcalData, $_type, $_noteID, $charset))) return false;

		if($_noteID > 0) $note['info_id'] = $_noteID;

		if (empty($note['info_status'])) $note['info_status'] = 'done';

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($note)."\n",3,$this->logfile);
		}

		return $this->write($note, true, true, false);
	}

	/**
	 * Search a matching infolog entry for the VNOTE data
	 *
	 * @param string $_vcalData		VNOTE
	 * @param int $contentID=null 	infolog_id (or null, if unkown)
	 * @param boolean $relax=false 	if true, a weaker match algorithm is used
	 * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
	 *
	 * @return infolog_id of a matching entry or false, if nothing was found
	 */
	function searchVNOTE($_vcalData, $_type, $contentID=null, $relax=false, $charset=null)
	{
		if (!($note = $this->vnotetoegw($_vcalData, $_type, $contentID, $charset))) return array();

		if ($contentID)	$note['info_id'] = $contentID;

		unset($note['info_startdate']);

		return $this->findInfo($note, $relax, $this->tzid);
	}

	/**
	 * Convert VTODO into a eGW infolog entry
	 *
	 * @param string $_data 	VNOTE data
	 * @param string $_type		content type (eg.g text/plain)
	 * @param int $_noteID =-1	infolog_id of the entry
	 * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
     *
	 * @return array infolog entry or false on error
	 */
	function vnotetoegw($_data, $_type, $_noteID=-1, $charset=null)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_type, $_noteID)\n" .
				array2string($_data)."\n",3,$this->logfile);
		}
		$note = false;

		switch ($_type)
		{
			case 'text/plain':
				$note = array();
				$note['info_type'] = 'note';
				$txt = str_replace("\r\n", "\n", Api\Translation::convert($_data, $charset));

				$match = null;
				if (preg_match('/([^\n]+)\n\n(.*)/ms', $txt, $match))
				{
					$note['info_subject'] = $match[1];
					$note['info_des'] = $match[2];
				}
				else
				{
					$note['info_subject'] = $txt;
				}
				break;

			case 'text/x-vnote':
				$vnote = new Horde_Icalendar;
				if ($charset && $charset != 'utf-8')
				{
					$_data = Api\Translation::convert($_data, $charset, 'utf-8');
				}
				if (!$vnote->parsevCalendar($_data, 'VCALENDAR'))	return false;

				$components = $vnote->getComponent();
				foreach ($components as $component)
				{
					if (is_a($component, 'Horde_Icalendar_Vnote'))
					{
						$note = array();
						$note['info_type'] = 'note';

						foreach ($component->getAllAttributes() as $attribute)
						{
							switch ($attribute['name'])
							{
								case 'BODY':
									$note['info_des'] = str_replace("\r\n", "\n", $attribute['value']);
									break;

								case 'SUMMARY':
									$note['info_subject'] = str_replace("\r\n", "\n", $attribute['value']);
									break;

								case 'CATEGORIES':
									if ($attribute['value'])
									{
										$cats = $this->find_or_add_categories(explode(',',$attribute['value']), $_noteID);
										$note['info_cat'] = $cats[0];
									}
									break;
							}
						}
					}
				}
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_type, $_noteID)\n" .
				($note ? array2string($note) : 'FALSE') ."\n",3,$this->logfile);
		}
		return $note;
	}

	/**
	 * Set the supported fields
	 *
	 * Currently we only store manufacturer and name
	 *
	 * @param string $_productManufacturer
	 * @param string $_productName
	 */
	function setSupportedFields($_productManufacturer='', $_productName='')
	{
		$state = &$_SESSION['SyncML.state'];
		if (isset($state))
		{
			$deviceInfo = $state->getClientDeviceInfo();
		}

		// store product manufacturer and name, to be able to use it elsewhere
		if ($_productManufacturer)
		{
			$this->productManufacturer = strtolower($_productManufacturer);
			$this->productName = strtolower($_productName);
		}

		if (isset($deviceInfo) && is_array($deviceInfo))
		{
			if (!isset($this->productManufacturer)
				|| $this->productManufacturer == ''
				|| $this->productManufacturer == 'file')
			{
				$this->productManufacturer = strtolower($deviceInfo['manufacturer']);
			}
			if (!isset($this->productName) || $this->productName == '')
			{
				$this->productName = strtolower($deviceInfo['model']);
			}
			if (isset($deviceInfo['uidExtension'])
				&& $deviceInfo['uidExtension'])
			{
					$this->uidExtension = true;
			}
			if (isset($deviceInfo['tzid']) &&
				$deviceInfo['tzid'])
			{
				switch ($deviceInfo['tzid'])
				{
					case -1:
						$this->tzid = false;
						break;
					case -2:
						$this->tzid = null;
						break;
					default:
						$this->tzid = $deviceInfo['tzid'];
				}
			}
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				'(' . $this->productManufacturer .
				', '. $this->productName .', ' .
				($this->tzid ? $this->tzid : Api\DateTime::$user_timezone->getName()) .
				")\n" , 3, $this->logfile);
		}

		//Horde::logMessage('setSupportedFields(' . $this->productManufacturer . ', '
		//	. $this->productName .', ' .
		//	($this->tzid ? $this->tzid : Api\DateTime::$user_timezone->getName()) .')',
		//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
	}
}
