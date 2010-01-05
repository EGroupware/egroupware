<?php
/**
 * iCal import and export via Horde iCalendar classes
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage export
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

/**
 * iCal import and export via Horde iCalendar classes
 */
class calendar_ical extends calendar_boupdate
{
	/**
	 * @var array $supportedFields array containing the supported fields of the importing device
	 */
	var $supportedFields;

	var $recur_days_1_0 = array(
		MCAL_M_MONDAY    => 'MO',
		MCAL_M_TUESDAY   => 'TU',
		MCAL_M_WEDNESDAY => 'WE',
		MCAL_M_THURSDAY  => 'TH',
		MCAL_M_FRIDAY    => 'FR',
		MCAL_M_SATURDAY  => 'SA',
		MCAL_M_SUNDAY    => 'SU',
	);
	/**
	 * @var array $status_egw2ical conversation of the participant status egw => ical
	 */
	var $status_egw2ical = array(
		'U' => 'NEEDS-ACTION',
		'A' => 'ACCEPTED',
		'R' => 'DECLINED',
		'T' => 'TENTATIVE',
	);
	/**
	 * @var array conversation of the participant status ical => egw
	 */
	var $status_ical2egw = array(
		'NEEDS-ACTION' => 'U',
		'NEEDS ACTION' => 'U',
		'ACCEPTED'     => 'A',
		'DECLINED'     => 'R',
		'TENTATIVE'    => 'T',
	);

	/**
	 * @var array $priority_egw2ical conversion of the priority egw => ical
	 */
	var $priority_egw2ical = array(
		0 => 0,		// undefined
		1 => 9,		// low
		2 => 5,		// normal
		3 => 1,		// high
	);

	/**
	 * @var array $priority_ical2egw conversion of the priority ical => egw
	 */
	var $priority_ical2egw = array(
		0 => 0,		// undefined
		9 => 1,	8 => 1, 7 => 1, 6 => 1,	// low
		5 => 2,		// normal
		4 => 3, 3 => 3, 2 => 3, 1 => 3,	// high
	);

	/**
	 * @var array $priority_egw2funambol conversion of the priority egw => funambol
	 */
	var $priority_egw2funambol = array(
		0 => 1,		// undefined (mapped to normal since undefined does not exist)
		1 => 0,		// low
		2 => 1,		// normal
		3 => 2,		// high
	);

	/**
	 * @var array $priority_funambol2egw conversion of the priority funambol => egw
	 */
	var $priority_funambol2egw = array(
		0 => 1,		// low
		1 => 2,		// normal
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
	 * user preference: import all-day events as non blocking
	 *
	 * @var boolean
	 */
	var $nonBlockingAllday = false;

	/**
	 * user preference: attach UID entries to the DESCRIPTION
	 *
	 * @var boolean
	 */
	var $uidExtension = false;

	/**
	 * user preference: calendar to synchronize with
	 *
	 * @var int
	 */
	var $calendarOwner = 0;

	/**
	 * user preference: Use this timezone for import from and export to device
	 *
	 * @var string
	 */
	var $tzid = null;

	/**
	 * Cached timezone data
	 *
	 * @var array id => data
	 */
	protected static $tz_cache = array();

	/**
	 * Device CTCap Properties
	 *
	 * @var array
	 */
	var $clientProperties;

	/**
	 * vCalendar Instance for parsing
	 *
	 * @var array
	 */
	var $vCalendar;

	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	var $logfile="/tmp/log-vcal";


	/**
	 * Constructor
	 *
	 * @param array $_clientProperties		client properties
	 */
	function __construct(&$_clientProperties = array())
	{
		parent::__construct();
		if ($this->log) $this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-vcal";
		$this->clientProperties = $_clientProperties;
		$this->vCalendar = new Horde_iCalendar;
	}


	/**
	 * Exports one calendar event to an iCalendar item
	 *
	 * @param int/array $events (array of) cal_id or array of the events
	 * @param string $version='1.0' could be '2.0' too
	 * @param string $method='PUBLISH'
	 * @param int $recur_date=0	if set export the next recurrence at or after the timestamp,
	 *                          	default 0 => export whole series (or events, if not recurring)
	 * @return string/boolean string with iCal or false on error (eg. no permission to read the event)
	 */
	function &exportVCal($events, $version='1.0', $method='PUBLISH', $recur_date=0)
	{
		$egwSupportedFields = array(
			'CLASS'			=> 'public',
			'SUMMARY'		=> 'title',
			'DESCRIPTION'	=> 'description',
			'LOCATION'		=> 'location',
			'DTSTART'		=> 'start',
			'DTEND'			=> 'end',
			'ATTENDEE'		=> 'participants',
			'ORGANIZER'		=> 'owner',
			'RRULE'			=> 'recur_type',
			'EXDATE'		=> 'recur_exception',
			'PRIORITY'		=> 'priority',
			'TRANSP'		=> 'non_blocking',
			'CATEGORIES'	=> 'category',
			'UID'			=> 'uid',
			'RECURRENCE-ID' => 'recurrence',
			'SEQUENCE'		=> 'etag',
		);

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		if ($this->productManufacturer == '' )
		{	// syncevolution is broken
			$version = '2.0';
		}

		$vcal = new Horde_iCalendar;
		$vcal->setAttribute('PRODID','-//eGroupWare//NONSGML eGroupWare Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
		$vcal->setAttribute('VERSION', $version);
		$vcal->setAttribute('METHOD', $method);

		if (!is_array($events)) $events = array($events);

		$vtimezones_added = array();
		foreach($events as $event)
		{
			$mailtoOrganizer = false;
			$organizerCN = false;

			if (strpos($this->productName, 'palmos') !== false)
			{
				$date_format = 'ts';
			}
			else
			{
				$date_format = 'server';
			}
			if (!is_array($event)
				&& !($event = $this->read($event, $recur_date, false, $date_format)))
			{
				return false;	// no permission to read $cal_id
			}
			if ($recur_date)
			{
				// force single event
				foreach (array('recur_enddate','recur_interval','recur_exception','recur_data','recur_date','id','etag') as $name)
				{
					unset($event[$name]);
				}
				$event['recur_type'] = MCAL_RECUR_NONE;
			}
			elseif ($event['recur_enddate'])
			{
				$time = new egw_time($event['start'],egw_time::$server_timezone);
				if (!isset(self::$tz_cache[$event['tzid']]))
				{
					self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
				}
				// all calculations in the event's timezone
				$time->setTimezone(self::$tz_cache[$event['tzid']]);
				$time->setTime(0, 0, 0);
				$delta = $event['end'] - (int)$time->format('U');
				// Adjust recur_enddate to end time
				$event['recur_enddate'] += $delta;
			}

			if ($this->log) error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n".array2string($event)."\n",3,$this->logfile);

			if ($this->tzid === false)
			{
				$tzid = null;
			}
			elseif ($this->tzid)
			{
				$tzid = $this->tzid;
			}
			else
			{
				$tzid = $event['tzid'];
			}
			// check if tzid of event (not only recuring ones) is already added to export
			if ($tzid && $tzid != 'UTC' && !in_array($tzid,$vtimezones_added))
			{
				// check if we have vtimezone component data for tzid of event, if not default to user timezone (default to server tz)
				if (!($vtimezone = calendar_timezones::tz2id($tzid,'component')))
				{
					error_log(__METHOD__."() unknown TZID='$tzid', defaulting to user timezone '".egw_time::$user_timezone->getName()."'!");
					$vtimezone = calendar_timezones::tz2id($tzid=egw_time::$user_timezone->getName(),'component');
				}
				if (!isset(self::$tz_cache[$tzid]))
				{
					self::$tz_cache[$tzid] = calendar_timezones::DateTimeZone($tzid);
				}
				//error_log("in_array('$tzid',\$vtimezones_added)=".array2string(in_array($tzid,$vtimezones_added)).", component=$vtimezone");;
				if (!in_array($tzid,$vtimezones_added))
				{
					// $vtimezone is a string with a single VTIMEZONE component, afaik Horde_iCalendar can not add it directly
					// --> we have to parse it and let Horde_iCalendar add it again
					$horde_vtimezone = Horde_iCalendar::newComponent('VTIMEZONE',$container=false);
					$horde_vtimezone->parsevCalendar($vtimezone,'VTIMEZONE');
					// DTSTART must be in local time!
					$standard = $horde_vtimezone->findComponent('STANDARD');
					$dtstart = $standard->getAttribute('DTSTART');
					$dtstart = new egw_time($dtstart, egw_time::$server_timezone);
					$dtstart->setTimezone(self::$tz_cache[$tzid]);
					$standard->setAttribute('DTSTART', $dtstart->format('Ymd\THis'), array(), false);
					$daylight = $horde_vtimezone->findComponent('DAYLIGHT');
					$dtstart = $daylight->getAttribute('DTSTART');
					$dtstart = new egw_time($dtstart, egw_time::$server_timezone);
					$dtstart->setTimezone(self::$tz_cache[$tzid]);
					$daylight->setAttribute('DTSTART', $dtstart->format('Ymd\THis'), array(), false);
					$vcal->addComponent($horde_vtimezone);
					$vtimezones_added[] = $tzid;
				}
			}
			if ($this->productManufacturer != 'file' && $this->uidExtension)
			{
				// Append UID to DESCRIPTION
				if (!preg_match('/\[UID:.+\]/m', $event['description'])) {
					$event['description'] .= "\n[UID:" . $event['uid'] . "]";
				}
			}

			$vevent = Horde_iCalendar::newComponent('VEVENT', $vcal);
			$parameters = $attributes = $values = array();

			if ($this->productManufacturer == 'sonyericsson')
			{
				$eventDST = date('I', $event['start']);
				if ($eventDST)
				{
					$attributes['X-SONYERICSSON-DST'] = 4;
				}
			}

			foreach ($egwSupportedFields as $icalFieldName => $egwFieldName)
			{
				if (!isset($this->supportedFields[$egwFieldName])) continue;

				$values[$icalFieldName] = array();
				switch ($icalFieldName)
				{
					case 'ATTENDEE':
						//if (count($event['participants']) == 1 && isset($event['participants'][$this->user])) break;
						foreach ((array)$event['participants'] as $uid => $status)
						{
							if (!($info = $this->resource_info($uid))) continue;
							$mailtoParticipant = $info['email'] ? 'MAILTO:'.$info['email'] : '';
							$participantCN = '"' . ($info['cn'] ? $info['cn'] : $info['name']) . '"';
							calendar_so::split_status($status, $quantity, $role);
							if ($role == 'CHAIR' && $uid != $this->user)
							{
								$mailtoOrganizer = $mailtoParticipant;
								$organizerCN = $participantCN;
								if ($status == 'U') continue; // saved ORGANIZER
							}
							// RB: MAILTO href contains only the email-address, NO cn!
							$attributes['ATTENDEE'][]	= $mailtoParticipant;
							// RSVP={TRUE|FALSE}	// resonse expected, not set in eGW => status=U
							$rsvp = $status == 'U' ? 'TRUE' : 'FALSE';
							// PARTSTAT={NEEDS-ACTION|ACCEPTED|DECLINED|TENTATIVE|DELEGATED|COMPLETED|IN-PROGRESS} everything from delegated is NOT used by eGW atm.
							$status = $this->status_egw2ical[$status];
							// CUTYPE={INDIVIDUAL|GROUP|RESOURCE|ROOM|UNKNOWN}
							switch ($info['type'])
							{
								case 'g':
									$cutype = 'GROUP';
									break;
								case 'r':
									$cutype = 'RESOURCE';
									break;
								case 'u':	// account
								case 'c':	// contact
								case 'e':	// email address
									$cutype = 'INDIVIDUAL';
									break;
								default:
									$cutype = 'UNKNOWN';
									break;
							};
							// ROLE={CHAIR|REQ-PARTICIPANT|OPT-PARTICIPANT|NON-PARTICIPANT|X-*}
							$parameters['ATTENDEE'][] = array(
								'CN'       => $participantCN,
								'ROLE'     => $role,
								'PARTSTAT' => $status,
								'CUTYPE'   => $cutype,
								'RSVP'     => $rsvp,
							)+($info['type'] != 'e' ? array('X-EGROUPWARE-UID' => $uid) : array())+
							($quantity > 1 ? array('X-EGROUPWARE-QUANTITY' => $quantity) : array());
						}
						break;

					case 'CLASS':
						$attributes['CLASS'] = $event['public'] ? 'PUBLIC' : 'CONFIDENTIAL';
						break;

    				case 'ORGANIZER':
    					// according to iCalendar standard, ORGANIZER not used for events in the own calendar
	    				if (!$organizerCN &&
	    					($event['owner'] != $this->user
	    						|| $this->productManufacturer != 'groupdav'))
	    				{
		    				$mailtoOrganizer = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');
		    				$mailtoOrganizer = $mailtoOrganizer ? 'MAILTO:'.$mailtoOrganizer : '';
		    				$organizerCN = '"' . trim($GLOBALS['egw']->accounts->id2name($event['owner'],'account_firstname')
		    								. ' ' . $GLOBALS['egw']->accounts->id2name($event['owner'],'account_lastname')) . '"';
	    				}
	    				if ($organizerCN)
	    				{
		    				$attributes['ORGANIZER'] = $mailtoOrganizer;
		    				$parameters['ORGANIZER']['CN'] = $organizerCN;
	    				}
	    				break;

					case 'DTSTART':
						$attributes['DTSTART'] = self::getDateTime($event['start'],$tzid,$parameters['DTSTART']);
						break;

					case 'DTEND':
						// write start + end of whole day events as dates
						if ($this->isWholeDay($event))
						{
							$event['end-nextday'] = $event['end'] + 12*3600;	// we need the date of the next day, as DTEND is non-inclusive (= exclusive) in rfc2445
							foreach (array('start' => 'DTSTART','end-nextday' => 'DTEND') as $f => $t)
							{
								$arr = $this->date2array($event[$f]);
								$vevent->setAttribute($t, array('year' => $arr['year'],'month' => $arr['month'],'mday' => $arr['day']),
									array('VALUE' => 'DATE'));
							}
							unset($attributes['DTSTART']);
						}
						else
						{
							$attributes['DTEND'] = self::getDateTime($event['end'],$tzid,$parameters['DTEND']);
						}
						break;

					case 'RRULE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;		// no recuring event
						$rriter = calendar_rrule::event2rrule($event,$date_format != 'server');
						$rrule = $rriter->generate_rrule($version);
						if ($version == '1.0')
						{
							$attributes['RRULE'] = $rrule['FREQ'].' '.$rrule['UNTIL'];
						}
						else // $version == '2.0'
						{
							$attributes['RRULE'] = '';
							$parameters['RRULE'] = $rrule;
						}
						break;

					case 'EXDATE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;
						$days = array();
						// dont use "virtual" exceptions created by participant status for GroupDAV or file export
						if (!in_array($this->productManufacturer,array('file','groupdav')))
						{
							$tz_id = ($tzid != $event['tzid'] ? $tzid : null);
							$days = $this->so->get_recurrence_exceptions($event, $tz_id);
						}
						if (is_array($event['recur_exception']))
						{
							$days = array_merge($days,$event['recur_exception']);	// can NOT use +, as it overwrites numeric indexes
						}
						if (!empty($days))
						{
							$days = array_unique($days);
							sort($days);
							// use 'DATE' instead of 'DATE-TIME' on whole day events
							if ($this->isWholeDay($event))
							{
								$value_type = 'DATE';
								foreach ($days as $id => $timestamp)
								{
									$arr = $this->date2array($timestamp);
									$days[$id] = array(
										'year'  => $arr['year'],
										'month' => $arr['month'],
										'mday'  => $arr['day'],
									);
								}
							}
							else
							{
								$value_type = 'DATE-TIME';
								foreach ($days as &$timestamp)
								{
									$timestamp = self::getDateTime($timestamp,$tzid,$parameters['EXDATE']);
								}
							}
							$attributes['EXDATE'] = '';
							$values['EXDATE'] = $days;
							$parameters['EXDATE']['VALUE'] = $value_type;
						}
						break;

					case 'PRIORITY':
						if($this->productManufacturer == 'funambol')
						{
							$attributes['PRIORITY'] = (int) $this->priority_egw2funambol[$event['priority']];
						}
						else
						{
							$attributes['PRIORITY'] = (int) $this->priority_egw2ical[$event['priority']];
						}
						break;

					case 'TRANSP':
						if ($version == '1.0')
						{
							$attributes['TRANSP'] = ($event['non_blocking'] ? 1 : 0);
						}
						else
						{
							$attributes['TRANSP'] = ($event['non_blocking'] ? 'TRANSPARENT' : 'OPAQUE');
						}
						break;

					case 'CATEGORIES':
						if ($event['category'])
						{
							$attributes['CATEGORIES'] = '';
							$values['CATEGORIES'] = $this->get_categories($event['category']);
						}
						break;

					case 'RECURRENCE-ID':
						if ($version == '1.0')
						{
								$icalFieldName = 'X-RECURRENCE-ID';
						}
						if ($recur_date)
						{
							// We handle a status only exception
							if ($this->isWholeDay($event))
							{
								$arr = $this->date2array($recur_date);
								$vevent->setAttribute($icalFieldName, array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}
							else
							{
								$attributes[$icalFieldName] = self::getDateTime($recur_date,$tzid,$parameters[$icalFieldName]);
							}
						}
						elseif ($event['recurrence'] && $event['reference'])
						{
							// $event['reference'] is a calendar_id, not a timestamp
							if (!($revent = $this->read($event['reference']))) break;	// referenced event does not exist

							if ($this->isWholeDay($revent))
							{
								$arr = $this->date2array($event['recurrence']);
								$vevent->setAttribute($icalFieldName, array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}
							else
							{
								$attributes[$icalFieldName] = self::getDateTime($event['recurrence'],$tzid,$parameters[$icalFieldName]);
							}
							unset($revent);
						}
						break;

					default:
						if (isset($this->clientProperties[$icalFieldName]['Size']))
						{
							$size = $this->clientProperties[$icalFieldName]['Size'];
							$noTruncate = $this->clientProperties[$icalFieldName]['NoTruncate'];
							#Horde::logMessage("vCalendar $icalFieldName Size: $size, NoTruncate: " .
							#	($noTruncate ? 'TRUE' : 'FALSE'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
						}
						else
						{
							$size = -1;
							$noTruncate = false;
						}
						$value = $event[$egwFieldName];
						$cursize = strlen($value);
						if ($size > 0 && $cursize > $size)
						{
							if ($noTruncate)
							{
								Horde::logMessage("vCalendar $icalFieldName omitted due to maximum size $size",
									__FILE__, __LINE__, PEAR_LOG_WARNING);
								continue; // skip field
							}
							// truncate the value to size
							$value = substr($value, 0, $size - 1);
							Horde::logMessage("vCalendar $icalFieldName truncated to maximum size $size",
								__FILE__, __LINE__, PEAR_LOG_INFO);
						}
						if (!empty($value) || ($size >= 0 && !$noTruncate))
						{
							$attributes[$icalFieldName] = $value;
						}
					break;
				}
			}

			if ($this->productManufacturer == 'nokia')
			{
				if ($event['special'] == '1')
				{
					$attributes['X-EPOCAGENDAENTRYTYPE'] = 'ANNIVERSARY';
					$attributes['DTEND'] = $attributes['DTSTART'];
				}
				elseif ($event['special'] == '2')
				{
					$attributes['X-EPOCAGENDAENTRYTYPE'] = 'EVENT';
				}
				else
				{
					$attributes['X-EPOCAGENDAENTRYTYPE'] = 'APPOINTMENT';
				}
			}

			$modified = $GLOBALS['egw']->contenthistory->getTSforAction('calendar',$event['id'],'modify');
			$created = $GLOBALS['egw']->contenthistory->getTSforAction('calendar',$event['id'],'add');
			if (!$created && !$modified) $created = $event['modified'];
			if ($created)
			{
				$attributes['CREATED'] = $created;
			}
			if (!$modified) $modified = $event['modified'];
			if ($modified)
			{
				$attributes['LAST-MODIFIED'] = $modified;
			}
			$attributes['DTSTAMP'] = time();
			foreach ($event['alarm'] as $alarmID => $alarmData)
			{
				// skip alarms not being set for all users and alarms owned by other users
				if ($alarmData['all'] != true && $alarmData['owner'] != $this->user)
				{
					continue;
				}

				if ($version == '1.0')
				{
					$attributes['DALARM'] = self::getDateTime($alarmData['time'],$tzid,$parameters['DALARM']);
					$attributes['AALARM'] = self::getDateTime($alarmData['time'],$tzid,$parameters['AALARM']);
					// lets take only the first alarm
					break;
				}
				else
				{
					// VCalendar 2.0 / RFC 2445

					$description = trim(preg_replace("/\r?\n?\\[[A-Z_]+:.*\\]/i", '', $event['description']));

					// skip over alarms that don't have the minimum required info
					if (!$alarmData['offset'] && !$alarmData['time']) continue;

					// RFC requires DESCRIPTION for DISPLAY
					if (!$event['title'] && !$description) continue;

					if ($this->isWholeDay($event) && $alarmData['offset'])
					{
						$alarmData['time'] = $event['start'] - $alarmData['offset'];
						$alarmData['offset'] = false;
					}

					$valarm = Horde_iCalendar::newComponent('VALARM',$vevent);
					if ($alarmData['offset'])
					{
						$valarm->setAttribute('TRIGGER', -$alarmData['offset'],
								array('VALUE' => 'DURATION', 'RELATED' => 'START'));
					}
					else
					{
						$params = array('VALUE' => 'DATE-TIME');
						$value = self::getDateTime($alarmData['time'],$tzid,$params);
						$valarm->setAttribute('TRIGGER', $value, $params);
					}

					$valarm->setAttribute('ACTION','DISPLAY');
					$valarm->setAttribute('DESCRIPTION',$event['title'] ? $event['title'] : $description);
					$vevent->addComponent($valarm);
				}
			}

			foreach ($attributes as $key => $value)
			{
				foreach (is_array($value)&&$parameters[$key]['VALUE']!='DATE' ? $value : array($value) as $valueID => $valueData)
				{
					$valueData = $GLOBALS['egw']->translation->convert($valueData,$GLOBALS['egw']->translation->charset(),'UTF-8');
                    $paramData = (array) $GLOBALS['egw']->translation->convert(is_array($value) ?
                    		$parameters[$key][$valueID] : $parameters[$key],
                            $GLOBALS['egw']->translation->charset(),'UTF-8');
                    $valuesData = (array) $GLOBALS['egw']->translation->convert($values[$key],
                    		$GLOBALS['egw']->translation->charset(),'UTF-8');
					//echo "$key:$valueID: value=$valueData, param=".print_r($paramDate,true)."\n";
					$vevent->setAttribute($key, $valueData, $paramData, true, $valuesData);
					$options = array();
					if ($paramData['CN']) $valueData .= $paramData['CN'];	// attendees or organizer CN can contain utf-8 content
					/*if($key != 'RRULE' && preg_match('/([\000-\012\015\016\020-\037\075])/',$valueData)) {
						$options['ENCODING'] = 'QUOTED-PRINTABLE';
					}*/
					if ($this->productManufacturer != 'groupdav' && preg_match('/([\177-\377])/', $valueData))
					{
						$options['CHARSET'] = 'UTF-8';
					}
					if (preg_match('/([\000-\012])/', $valueData))
					{
						if ($this->log) error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."() Has invalid XML data: $valueData",3,$this->logfile);
					}
					$vevent->setParameter($key, $options);
				}
			}
			$vcal->addComponent($vevent);
		}

		$retval = $vcal->exportvCalendar();
 		if ($this->log) error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n".array2string($retval)."\n",3,$this->logfile);

		return $retval;
	}

	/**
	 * Get DateTime value for a given time and timezone
	 *
	 * @param int|string|DateTime $time in server-time as returned by calendar_bo for $data_format='server'
	 * @param string $tzid TZID of event or 'UTC' or NULL for palmos timestamps in usertime
	 * @param array &$params=null parameter array to set TZID
	 * @return mixed attribute value to set: integer timestamp if $tzid == 'UTC' otherwise Ymd\THis string IN $tzid
	 */
	static function getDateTime($time,$tzid,array &$params=null)
	{
		if (empty($tzid) || $tzid == 'UTC')
		{
			return egw_time::to($time,'ts');
		}
		if (!is_a($time,'DateTime'))
		{
			$time = new egw_time($time,egw_time::$server_timezone);
		}
		if (!isset(self::$tz_cache[$tzid]))
		{
			self::$tz_cache[$tzid] = calendar_timezones::DateTimeZone($tzid);
		}
		$time->setTimezone(self::$tz_cache[$tzid]);
		$params['TZID'] = $tzid;

		return $time->format('Ymd\THis');
	}

	/**
	 * Import an iCal
	 *
	 * @param string $_vcalData
	 * @param int $cal_id=-1 must be -1 for new entries!
	 * @param string $etag=null if an etag is given, it has to match the current etag or the import will fail
	 * @param boolean $merge=false	merge data with existing entry
	 * @param int $recur_date=0 if set, import the recurrence at this timestamp,
	 *                          default 0 => import whole series (or events, if not recurring)
	 * @return int|boolean cal_id > 0 on success, false on failure or 0 for a failed etag
	 */
	function importVCal($_vcalData, $cal_id=-1, $etag=null, $merge=false, $recur_date=0)
	{
		if ($this->log) error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n".array2string($_vcalData)."\n",3,$this->logfile);

		if (!($events = $this->icaltoegw($_vcalData,$cal_id,$etag,$recur_date)))
		{
			return false;
		}

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		// check if we are importing an event series with exceptions in CalDAV
		// only first event / series master get's cal_id from URL
		// other events are exceptions and need to be checked if they are new
		// and for real (not status only) exceptions their recurrence-id need
		// to be included as recur_exception to the master
		if ($this->productManufacturer == 'groupdav' && $cal_id > 0 &&
			count($events) > 1 && !$events[1]['id'] &&
			$events[0]['recur_type'] != MCAL_RECUR_NONE)
		{
			calendar_groupdav::fix_series($events);
		}
		foreach ($events as $event)
		{
			$updated_id = false;
			$event_info = $this->get_event_info($event);

			// common adjustments for new events
			if (!is_array($event_info['stored_event']))
			{
				// set non blocking all day depending on the user setting
				if ($this->isWholeDay($event) && $this->nonBlockingAllday)
				{
					$event['non_blocking'] = 1;
				}

				// check if an owner is set and the current user has add rights
				// for that owners calendar; if not set the current user
				if (!isset($event['owner'])
					|| !$this->check_perms(EGW_ACL_ADD, 0, $event['owner']))
				{
					$event['owner'] = $this->user;
				}

				if (!is_array($event['participants']) || !count($event['participants']))
				{
					$status = $event['owner'] == $this->user ? 'A' : 'U';
					$status = calendar_so::combine_status($status, 1, 'CHAIR');
					$event['participants'] = array($event['owner'] => $status);
				}
			}

			// common adjustments for existing events
			if (is_array($event_info['stored_event']))
			{
				if ($merge)
				{
					// overwrite with server data for merge
					foreach ($event_info['stored_event'] as $key => $value)
					{
						switch ($key)
						{
							case 'participants_types':
								continue;

								case 'participants':
								foreach ($event_info['stored_event']['participants'] as $uid => $status)
								{
									// Is a participant and no longer present in the event?
									if (!isset($event['participants'][$uid]))
									{
										// Add it back in
										$event['participants'][$uid] = $event['participant_types']['r'][substr($uid,1)] = $status;
									}
								}
								break;

								default:
								if (!empty($value))
								{
									$event[$key] = $value;
								}
						}
					}
				}
				else
				{
			 		// no merge
					if (!isset($this->supportedFields['participants']) || !count($event['participants']))
					{
						// If this is an updated meeting, and the client doesn't support
						// participants OR the event no longer contains participants, add them back
						$event['participants'] = $event_info['stored_event']['participants'];
						$event['participant_types'] = $event_info['stored_event']['participant_types'];
					}

					foreach ($event_info['stored_event']['participants'] as $uid => $status)
					{
						// Is it a resource and no longer present in the event?
						if ( $uid[0] == 'r' && !isset($event['participants'][$uid]) )
						{
							// Add it back in
							$event['participants'][$uid] = $event['participant_types']['r'][substr($uid,1)] = $status;
						}
					}
					// avoid that iCal changes the organizer, which is not allowed
					$event['owner'] = $event_info['stored_event']['owner'];
				}
			}

			// update alarms depending on the given event type
			if (count($event['alarm']) > 0 || isset($this->supportedFields['alarm']))
			{
				switch ($event_info['type'])
				{
					case 'SINGLE':
					case 'SERIES-MASTER':
					case 'SERIES-EXCEPTION':
					case 'SERIES-EXCEPTION-PROPAGATE':
						if (count($event['alarm']) > 0)
						{
							foreach ($event['alarm'] as $newid => &$alarm)
							{
								if (!isset($alarm['offset']) && isset($alarm['time']))
								{
									$alarm['offset'] = $event['start'] - $alarm['time'];
								}
								if (!isset($alarm['time']) && isset($alarm['offset']))
								{
									$alarm['time'] = $event['start'] - $alarm['offset'];
								}
								$alarm['owner'] = $this->user;
								$alarm['all'] = false;

								if (is_array($event_info['stored_event'])
										&& count($event_info['stored_event']['alarm']) > 0)
								{
									foreach ($event_info['stored_event']['alarm'] as $alarm_id => $alarm_data)
									{
										if ($alarm['time'] == $alarm_data['time'] &&
											($alarm_data['all'] || $alarm_data['owner'] == $this->user))
										{
											unset($event['alarm'][$newid]);
											unset($event_info['stored_event']['alarm'][$alarm_id]);
											continue;
										}
									}
								}
							}
						}
						break;

					case 'SERIES-EXCEPTION-STATUS':
						// nothing to do here
						break;
				}
				if (is_array($event_info['stored_event'])
						&& count($event_info['stored_event']['alarm']) > 0)
				{
					foreach ($event_info['stored_event']['alarm'] as $alarm_id => $alarm_data)
					{
						// only touch own alarms
						if ($alarm_data['all'] == false && $alarm_data['owner'] == $this->user)
						{
							$this->delete_alarm($alarm_id);
						}
					}
				}
			}

			// save event depending on the given event type
			switch ($event_info['type'])
			{
				case 'SINGLE':
					Horde::logMessage('importVCAL event SINGLE',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					// update the event
					if ($event_info['acl_edit'])
					{
						$event_to_store = $event; // prevent $event from being changed by the update method
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-MASTER':
					Horde::logMessage('importVCAL event SERIES-MASTER',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					// remove all known "status only" exceptions and update the event
					if ($event_info['acl_edit'])
					{
						$days = $this->so->get_recurrence_exceptions($event);
						if (is_array($days))
						{
							$recur_exceptions = array();
							foreach ($event['recur_exception'] as $recur_exception)
							{
								if (!in_array($recur_exception, $days))
								{
									$recur_exceptions[] = $recur_exception;
								}
							}
							$event['recur_exception'] = $recur_exceptions;
						}

						$event_to_store = $event; // prevent $event from being changed by the update method
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-EXCEPTION':
				case 'SERIES-EXCEPTION-PROPAGATE':
					Horde::logMessage('importVCAL event SERIES-EXCEPTION',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					// update event
					if ($event_info['acl_edit'])
					{
						if (isset($event_info['stored_event']['id']))
						{
							// We update an existing exception
							$event['id'] = $event_info['stored_event']['id'];
							$event['category'] = $event_info['stored_event']['category'];
						}
						else
						{
							// We create a new exception
							unset($event['id']);
							$event_info['master_event']['recur_exception'] = array_unique(array_merge($event_info['master_event']['recur_exception'], array($event['recurrence'])));
							$event_to_store = $event_info['master_event']; // prevent the master_event from being changed by the update method
							$this->update($event_to_store, true);
							unset($event_to_store);
							$event['reference'] = $event_info['master_event']['id'];
							$event['category'] = $event_info['master_event']['category'];
							$event['owner'] = $event_info['master_event']['owner'];
						}

						$event_to_store = $event; // prevent $event from being changed by update method
						$updated_id = $this->update($event_to_store, true, true, false, false);
						unset($event_to_store);
					}
					break;

				case 'SERIES-EXCEPTION-STATUS':
					Horde::logMessage('importVCAL event SERIES-EXCEPTION-STATUS',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					if ($event_info['acl_edit'])
					{
						// truncate the status only exception from the series master
						$recur_exceptions = array();
						foreach ($event_info['master_event']['recur_exception'] as $recur_exception)
						{
							if ($recur_exception != $event['recurrence'])
							{
								$recur_exceptions[] = $recur_exception;
							}
						}
						$event_info['master_event']['recur_exception'] = $recur_exceptions;

						// save the series master with the adjusted exceptions
						$event_to_store = $event_info['master_event']; // prevent the master_event from being changed by the update method
						$updated_id = $this->update($event_to_store, true, true, false, false);
						unset($event_to_store);
					}

					break;
			}

			// read stored event into info array for fresh stored (new) events
			if (!is_array($event_info['stored_event']) && $updated_id > 0)
			{
				$event_info['stored_event'] = $this->read($updated_id);
			}

			// update status depending on the given event type
			switch ($event_info['type'])
			{
				case 'SINGLE':
				case 'SERIES-MASTER':
				case 'SERIES-EXCEPTION':
				case 'SERIES-EXCEPTION-PROPAGATE':
					if (is_array($event_info['stored_event'])) // status update requires a stored event
					{
						if ($event_info['acl_edit'])
						{
							// update all participants if we have the right to do that
							$this->update_status($event, $event_info['stored_event']);
						}
						elseif (isset($event['participants'][$this->user]) || isset($event_info['stored_event']['participants'][$this->user]))
						{
							// update the users status only
							$this->set_status($event_info['stored_event']['id'], $this->user,
								($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), 0, true);
						}
					}
					break;

				case 'SERIES-EXCEPTION-STATUS':
					if (is_array($event_info['master_event'])) // status update requires a stored master event
					{
						if ($event_info['acl_edit'])
						{
							// update all participants if we have the right to do that
							$this->update_status($event, $event_info['master_event'], $event['recurrence']);
						}
						elseif (isset($event['participants'][$this->user]) || isset($event_info['master_event']['participants'][$this->user]))
						{
							// update the users status only
							$this->set_status($event_info['master_event']['id'], $this->user,
								($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), $event['recurrence'], true);
						}
					}
					break;
			}

			// choose which id to return to the client
			switch ($event_info['type'])
			{
				case 'SINGLE':
				case 'SERIES-MASTER':
				case 'SERIES-EXCEPTION':
					$return_id = is_array($event_info['stored_event']) ? $event_info['stored_event']['id'] : false;
					break;

				case 'SERIES-EXCEPTION-STATUS':
					$return_id = is_array($event_info['master_event']) ? $event_info['master_event']['id'] . ':' . $event['recurrence'] : false;
					break;

				case 'SERIES-EXCEPTION-PROPAGATE':
					if ($event_info['acl_edit'] && is_array($event_info['stored_event']))
					{
						// we had sufficient rights to propagate the status only exception to a real one
						$return_id = $event_info['stored_event']['id'];
					}
					else
					{
						// we did not have sufficient rights to propagate the status only exception to a real one
						// we have to keep the SERIES-EXCEPTION-STATUS id and keep the event untouched
						$return_id = $event_info['master_event']['id'] . ':' . $event['recurrence'];
					}
					break;
			}

			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n".array2string($event_info['stored_event'])."\n",3,$this->logfile);
			}
		}

		return $return_id;
	}

	/**
	 * get the value of an attribute by its name
	 *
	 * @param array $attributes
	 * @param string $name eg. 'DTSTART'
	 * @param string $what='value'
	 * @return mixed
	 */
	static function _get_attribute($components,$name,$what='value')
	{
		foreach ($components as $attribute)
		{
			if ($attribute['name'] == $name)
			{
				return !$what ? $attribute : $attribute[$what];
			}
		}
		return false;
	}

	static function valarm2egw(&$alarms, &$valarm)
	{
		$count = 0;
		foreach ($valarm->_attributes as $vattr)
		{
			switch ($vattr['name'])
			{
				case 'TRIGGER':
					$vtype = (isset($vattr['params']['VALUE']))
						? $vattr['params']['VALUE'] : 'DURATION'; //default type
					switch ($vtype)
					{
						case 'DURATION':
							if (isset($vattr['params']['RELATED'])
								&& $vattr['params']['RELATED'] != 'START')
							{
								error_log("Unsupported VALARM offset anchor ".$vattr['params']['RELATED']);
							}
							else
							{
								$alarms[] = array('offset' => -$vattr['value']);
								$count++;
							}
							break;
						case 'DATE-TIME':
							$alarms[] = array('time' => $vattr['value']);
							$count++;
							break;
						default:
							// we should also do ;RELATED=START|END
							error_log('VALARM/TRIGGER: unsupported value type:' . $vtype);
					}
					break;
				case 'ACTION':
				case 'DISPLAY':
				case 'DESCRIPTION':
				case 'SUMMARY':
				case 'ATTACH':
				case 'ATTENDEE':
					// we ignore these fields silently
				 	break;

				default:
					error_log('VALARM field ' .$vattr['name'] . ': ' . $vattr['value'] . ' HAS NO CONVERSION YET');
			}
		}
		return $count;
	}

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
			if (isset($deviceInfo['uidExtension']) &&
				$deviceInfo['uidExtension'])
			{
				$this->uidExtension = true;
			}
			if (isset($deviceInfo['nonBlockingAllday']) &&
				$deviceInfo['nonBlockingAllday'])
			{
				$this->nonBlockingAllday = true;
			}
			if (isset($deviceInfo['tzid']) &&
				$deviceInfo['tzid'])
			{
				$this->tzid = $deviceInfo['tzid'];
			}
			if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['calendar_owner']))
			{
				$owner = $GLOBALS['egw_info']['user']['preferences']['syncml']['calendar_owner'];
				if (0 < (int)$owner && $this->check_perms(EGW_ACL_EDIT,0,$owner))
				{
					$this->calendarOwner = $owner;
				}
			}
			if (!isset($this->productManufacturer) ||
				 $this->productManufacturer == '' ||
				 $this->productManufacturer == 'file')
			{
				$this->productManufacturer = strtolower($deviceInfo['manufacturer']);
			}
			if (!isset($this->productName) || $this->productName == '')
			{
				$this->productName = strtolower($deviceInfo['model']);
			}
		}

		Horde::logMessage('setSupportedFields(' . $this->productManufacturer
				. ', ' . $this->productName .')', __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$defaultFields['minimal'] = array(
			'public'			=> 'public',
			'description'		=> 'description',
			'end'				=> 'end',
			'start'				=> 'start',
			'location'			=> 'location',
			'recur_type'		=> 'recur_type',
			'recur_interval'	=> 'recur_interval',
			'recur_data'		=> 'recur_data',
			'recur_enddate'		=> 'recur_enddate',
			'title'				=> 'title',
			'alarm'				=> 'alarm',
		);

		$defaultFields['basic'] = $defaultFields['minimal'] + array(
			'recur_exception'	=> 'recur_exception',
			'priority'			=> 'priority',
		);

		$defaultFields['nexthaus'] = $defaultFields['basic'] + array(
			'participants'		=> 'participants',
			'uid'				=> 'uid',
		);

		$defaultFields['s60'] = $defaultFields['basic'] + array(
			'category'			=> 'category',
			'uid'				=> 'uid',
		);

		$defaultFields['synthesis'] = $defaultFields['basic'] + array(
			'participants'		=> 'participants',
			'owner'				=> 'owner',
			'category'			=> 'category',
			'non_blocking'		=> 'non_blocking',
			'uid'				=> 'uid',
			'recurrence'		=> 'recurrence',
			'etag'				=> 'etag',
		);

		$defaultFields['evolution'] = $defaultFields['basic'] + array(
			'participants'		=> 'participants',
			'owner'				=> 'owner',
			'category'			=> 'category',
			'uid'				=> 'uid',
		);

		$defaultFields['full'] = $defaultFields['basic'] + array(
			'participants'		=> 'participants',
			'owner'				=> 'owner',
			'category'			=> 'category',
			'non_blocking'		=> 'non_blocking',
			'uid'				=> 'uid',
			'recurrence'		=> 'recurrence',
			'etag'				=> 'etag',
		);


		switch ($this->productManufacturer)
		{
			case 'nexthaus corporation':
			case 'nexthaus corp':
				switch ($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['nexthaus'];
						break;
				}
				break;

			// multisync does not provide anymore information then the manufacturer
			// we suppose multisync with evolution
			case 'the multisync project':
				switch ($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['basic'];
						break;
				}
				break;

			case 'siemens':
				switch ($this->productName)
				{
					case 'sx1':
						$this->supportedFields = $defaultFields['minimal'];
						break;
					default:
						error_log("Unknown Siemens phone '$_productName', using minimal set");
						$this->supportedFields = $defaultFields['minimal'];
						break;
				}
				break;

			case 'nokia':
				switch ($this->productName)
				{
					case 'e61':
						$this->supportedFields = $defaultFields['minimal'];
						break;
					case 'e51':
					case 'e90':
					case 'e71':
					case 'e66':
					case '6120c':
					case 'nokia 6131':
						$this->supportedFields = $defaultFields['s60'];
						break;
					default:
						error_log("Unknown Nokia phone '$_productName', assuming E61");
						$this->supportedFields = $defaultFields['minimal'];
						break;
				}
				break;

			case 'sonyericsson':
			case 'sony ericsson':
				switch ($this->productName)
				{
					case 'd750i':
					case 'p910i':
					case 'g705i':
						$this->supportedFields = $defaultFields['basic'];
						break;
					default:
						error_log("Unknown Sony Ericsson phone '$this->productName' assuming d750i");
						$this->supportedFields = $defaultFields['basic'];
						break;
				}
				break;

			case 'synthesis ag':
				switch ($this->productName)
				{
					case 'sysync client pocketpc std':
					case 'sysync client pocketpc pro':
					case 'sysync client iphone contacts':
					case 'sysync client iphone contacts+todoz':
					default:
						$this->supportedFields = $defaultFields['synthesis'];
						break;
				}
				break;

			//Syncevolution compatibility
			case 'patrick ohly':
				$this->supportedFields = $defaultFields['evolution'];
				break;

			case '': // seems syncevolution 0.5 doesn't send a manufacturer
				error_log("No vendor name, assuming syncevolution 0.5");
				$this->supportedFields = $defaultFields['evolution'];
				break;

			case 'file':	// used outside of SyncML, eg. by the calendar itself ==> all possible fields
				$this->supportedFields = $defaultFields['full'];
				break;

			case 'groupdav':		// all GroupDAV access goes through here
				switch ($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['full'];
				}
				break;

			case 'funambol':
				$this->supportedFields = $defaultFields['synthesis'];
				break;

			// the fallback for SyncML
			default:
				error_log("Unknown calendar SyncML client: manufacturer='$this->productManufacturer'  product='$this->productName'");
				$this->supportedFields = $defaultFields['synthesis'];
				break;
		}
		// for palmos we have to use user-time and NO timezone
		if (strpos($this->productName, 'palmos') !== false)
		{
			$this->tzid = false;
		}
	}

	function icaltoegw($_vcalData, $cal_id=-1, $etag=null, $recur_date=0)
	{
		$events = array();

		if ($this->tzid)
		{
			date_default_timezone_set($this->tzid);
		}

		$vcal = new Horde_iCalendar;
		if (!$vcal->parsevCalendar($_vcalData))
		{
			if ($this->tzid)
			{
				date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
			}
			return false;
		}
		$version = $vcal->getAttribute('VERSION');
		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		foreach ($vcal->getComponents() as $component)
		{
			if (is_a($component, 'Horde_iCalendar_vevent'))
			{
				if (($event = $this->vevent2egw($component, $version, $this->supportedFields, $cal_id)))
				{
					//common adjustments
					if ($this->productManufacturer == '' && $this->productName == ''
						&& !empty($event['recur_enddate']))
					{
						// syncevolution needs an adjusted recur_enddate
						$event['recur_enddate'] = (int)$event['recur_enddate'] + 86400;
					}
 					if ($event['recur_type'] != MCAL_RECUR_NONE)
 					{
 						// No reference or RECURRENCE-ID for the series master
 						$event['reference'] = $event['recurrence'] = 0;
 					}

 					// handle the alarms
 					$alarms = $event['alarm'];
					foreach ($component->getComponents() as $valarm)
					{
						if (is_a($valarm, 'Horde_iCalendar_valarm'))
						{
							$this->valarm2egw($alarms, $valarm);
						}
					}
					$event['alarm'] = $alarms;

					$events[] = $event;
				}
			}
		}

		if ($this->tzid)
		{
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
		}

		// if cal_id, etag or recur_date is given, use/set it for 1. event
		if ($cal_id > 0) $events[0]['id'] = $cal_id;
		if (!is_null($etag)) $events[0]['etag'] = $etag;
		if ($recur_date) $events[0]['recurrence'] = $recur_date;

		return $events;
	}

	/**
	 * Parse a VEVENT
	 *
	 * @param array $component			VEVENT
	 * @param string $version			vCal version (1.0/2.0)
	 * @param array $supportedFields	supported fields of the device
	 * @param int $cal_id				id of existing event in the content (only used to merge categories)
	 *
	 * @return array|boolean			event on success, false on failure
	 */
	function vevent2egw(&$component, $version, $supportedFields, $cal_id=-1)
	{
		if (!is_a($component, 'Horde_iCalendar_vevent')) return false;

		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'])) {
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$isDate = false;
		$event		= array();
		$alarms		= array();
		$vcardData	= array(
			'recur_type'		=> MCAL_RECUR_NONE,
			'recur_exception'	=> array(),
		);
		// we parse DTSTART and DTEND first
		foreach ($component->_attributes as $attributes)
		{
			switch ($attributes['name'])
			{
				case 'DTSTART':
					if (isset($attributes['params']['VALUE'])
							&& $attributes['params']['VALUE'] == 'DATE')
					{
						$isDate = true;
					}
					$dtstart_ts = is_numeric($attributes['value']) ? $attributes['value'] : $this->date2ts($attributes['value']);
					$vcardData['start']	= $dtstart_ts;


					if ($this->tzid)
					{
						// enforce device settings
						$event['tzid'] = $this->tzid;
					}
					elseif (!empty($attributes['params']['TZID']))
					{
						// import TZID, if PHP understands it (we only care about TZID of starttime, as we store only a TZID for the whole event)
						try
						{
							$tz = calendar_timezones::DateTimeZone($attributes['params']['TZID']);
							$event['tzid'] = $tz->getName();
						}
						catch(Exception $e)
						{
							error_log(__METHOD__."() unknown TZID='{$attributes['params']['TZID']}', defaulting to user timezone '".egw_time::$user_timezone->getName()."'!");
							$tz = egw_time::$user_timezone;
							$event['tzid'] = egw_time::$user_timezone->getName();	// default to user timezone
						}
					}
					else
					{
						$event['tzid'] = egw_time::$user_timezone->getName();	// default to user timezone
					}
					break;
				case 'DTEND':
					$dtend_ts = is_numeric($attributes['value']) ? $attributes['value'] : $this->date2ts($attributes['value']);
					if (date('H:i:s',$dtend_ts) == '00:00:00')
					{
						$dtend_ts -= 1;
					}
					$vcardData['end']	= $dtend_ts;
			}
		}
		if (!isset($vcardData['start'])) return false; // not a valid entry

		// lets see what we can get from the vcard
		foreach ($component->_attributes as $attributes)
		{
			switch ($attributes['name'])
			{
				case 'AALARM':
				case 'DALARM':
					$alarmTime = $attributes['value'];
					$alarms[$alarmTime] = array(
						'time' => $alarmTime
					);
					break;
				case 'CLASS':
					$vcardData['public']		= (int)(strtolower($attributes['value']) == 'public');
					break;
				case 'DESCRIPTION':
					$vcardData['description']	= $attributes['value'];
					if (preg_match('/\s*\[UID:(.+)?\]/Usm', $attributes['value'], $matches))
					{
						if (!isset($vCardData['uid'])
								&& strlen($matches[1]) >= $minimum_uid_length)
						{
							$vcardData['uid'] = $matches[1];
						}
					}
					break;
				case 'RECURRENCE-ID':
				case 'X-RECURRENCE-ID':
					$vcardData['recurrence'] = $attributes['value'];
					break;
				case 'LOCATION':
					$vcardData['location']	= $attributes['value'];
					break;
				case 'RRULE':
					$recurence = $attributes['value'];
					$type = preg_match('/FREQ=([^;: ]+)/i',$recurence,$matches) ? $matches[1] : $recurence[0];
					// vCard 2.0 values for all types
					if (preg_match('/UNTIL=([0-9TZ]+)/',$recurence,$matches))
					{
						$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($matches[1]);
					}
					elseif (preg_match('/COUNT=([0-9]+)/',$recurence,$matches))
					{
						$vcardData['recur_count'] = (int)$matches[1];
					}
					if (preg_match('/INTERVAL=([0-9]+)/',$recurence,$matches))
					{
						// 1 is invalid,, egw uses 0 for interval
						$vcardData['recur_interval'] = (int) $matches[1] != 0 ? (int) $matches[1] : 0;
					}
					$vcardData['recur_data'] = 0;
					switch($type)
					{
						case 'W':
						case 'WEEKLY':
							$days = array();
							if (preg_match('/W(\d+) ([^ ]*)( ([^ ]*))?$/',$recurence, $recurenceMatches))		// 1.0
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if (empty($recurenceMatches[4]))
								{
									if ($recurenceMatches[2] != '#0')
									{
										$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[2]);
									}
									$days[0] = strtoupper(substr(date('D', $vcardData['start']),0,2));
								}
								else
								{
									$days = explode(' ',trim($recurenceMatches[2]));

									if ($recurenceMatches[4] != '#0')
									{
										$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[4]);
									}
								}
								$recur_days = $this->recur_days_1_0;
							}
							elseif (preg_match('/BYDAY=([^;: ]+)/',$recurence,$recurenceMatches))	// 2.0
							{
								$days = explode(',',$recurenceMatches[1]);
								$recur_days = $this->recur_days;
							}
							else	// no day given, use the day of dtstart
							{
								$vcardData['recur_data'] |= 1 << (int)date('w',$vcardData['start']);
								$vcardData['recur_type'] = MCAL_RECUR_WEEKLY;
							}
							if ($days)
							{
								foreach ($recur_days as $id => $day)
								{
									if (in_array(strtoupper(substr($day,0,2)),$days))
									{
										$vcardData['recur_data'] |= $id;
									}
								}
								$vcardData['recur_type'] = MCAL_RECUR_WEEKLY;
							}

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(
									date('H', $vcardData['end']),
									date('i', $vcardData['end']),
									date('s', $vcardData['end']),
									date('m', $vcardData['start']),
									date('d', $vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)*7),
									date('Y', $vcardData['start']));
							}
							break;

						case 'D':	// 1.0
							if (preg_match('/D(\d+) #(.\d)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] > 0 && $vcardData['end'])
								{
									$vcardData['recur_enddate'] = mktime(
										date('H', $vcardData['end']),
										date('i', $vcardData['end']),
										date('s', $vcardData['end']),
										date('m', $vcardData['end']),
										date('d', $vcardData['end']) + ($recurenceMatches[2] * $vcardData['recur_interval']),
										date('Y', $vcardData['end'])
									);
								}
							}
							elseif (preg_match('/D(\d+) (.*)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] != '#0')
								{
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[2]);
								}
							}
							else break;

							// fall-through
						case 'DAILY':	// 2.0
							$vcardData['recur_type'] = MCAL_RECUR_DAILY;

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(
									date('H', $vcardData['end']),
									date('i', $vcardData['end']),
									date('s', $vcardData['end']),
									date('m', $vcardData['start']),
									date('d', $vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)),
									date('Y', $vcardData['start']));
							}
							break;

						case 'M':
							if (preg_match('/MD(\d+) #(.\d)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_MDAY;
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] > 0 && $vcardData['end'])
								{
									$vcardData['recur_enddate'] = mktime(
										date('H', $vcardData['end']),
										date('i', $vcardData['end']),
										date('s', $vcardData['end']),
										date('m', $vcardData['end']) + ($recurenceMatches[2] * $vcardData['recur_interval']),
										date('d', $vcardData['end']),
										date('Y', $vcardData['end'])
									);
								}
							}
							elseif (preg_match('/MD(\d+) (.*)/',$recurence, $recurenceMatches))
							{
								$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_MDAY;
								if ($recurenceMatches[1] > 1)
								{
									$vcardData['recur_interval'] = $recurenceMatches[1];
								}
								if ($recurenceMatches[2] != '#0')
								{
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[2]);
								}
							}
							elseif (preg_match('/MP(\d+) (.*) (.*) (.*)/',$recurence, $recurenceMatches))
							{
								$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_WDAY;
								if ($recurenceMatches[1] > 1)
								{
									$vcardData['recur_interval'] = $recurenceMatches[1];
								}
								if ($recurenceMatches[4] != '#0')
								{
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[4]);
								}
							}
							break;
						case 'MONTHLY':
							$vcardData['recur_type'] = strpos($recurence,'BYDAY') !== false ?
									MCAL_RECUR_MONTHLY_WDAY : MCAL_RECUR_MONTHLY_MDAY;

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(
									date('H', $vcardData['end']),
									date('i', $vcardData['end']),
									date('s', $vcardData['end']),
									date('m', $vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)),
									date('d', $vcardData['start']),
									date('Y', $vcardData['start']));
							}
							break;

						case 'Y':		// 1.0
							if (preg_match('/YM(\d+)[^#]*#(\d+)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] > 0 && $vcardData['end'])
								{
									$vcardData['recur_enddate'] = mktime(
										date('H', $vcardData['end']),
										date('i', $vcardData['end']),
										date('s', $vcardData['end']),
										date('m', $vcardData['end']),
										date('d', $vcardData['end']),
										date('Y', $vcardData['end']) + ($recurenceMatches[2] * $vcardData['recur_interval'])
									);
								}
							}
							elseif (preg_match('/YM(\d+) (.*)/',$recurence, $recurenceMatches))
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] != '#0')
								{
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[2]);
								}
							} else break;

							// fall-through
						case 'YEARLY':	// 2.0
							$vcardData['recur_type'] = MCAL_RECUR_YEARLY;

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(
									date('H', $vcardData['end']),
									date('i', $vcardData['end']),
									date('s', $vcardData['end']),
									date('m', $vcardData['start']),
									date('d', $vcardData['start']),
									date('Y', $vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)));
							}
							break;
					}
					break;
				case 'EXDATE':
					if ((isset($attributes['params']['VALUE'])
							&& $attributes['params']['VALUE'] == 'DATE') ||
						(!isset($attributes['params']['VALUE']) && $isDate))
					{
						$days = array();
						$hour = date('H', $vcardData['start']);
						$minutes = date('i', $vcardData['start']);
						$seconds = date('s', $vcardData['start']);
						foreach ($attributes['values'] as $day)
						{
							$days[] = mktime(
								$hour,
								$minutes,
								$seconds,
								$day['month'],
								$day['mday'],
								$day['year']);
						}
						$vcardData['recur_exception'] = array_merge($vcardData['recur_exception'], $days);
					}
					else
					{
						$vcardData['recur_exception'] = array_merge($vcardData['recur_exception'], $attributes['values']);
					}
					break;
				case 'SUMMARY':
					$vcardData['title']		= $attributes['value'];
					break;
				case 'UID':
					if (strlen($attributes['value']) >= $minimum_uid_length)
					{
						$event['uid'] = $vcardData['uid'] = $attributes['value'];
					}
					break;
				case 'TRANSP':
					if ($version == '1.0')
					{
						$vcardData['non_blocking'] = ($attributes['value'] == 1);
					}
					else
					{
						$vcardData['non_blocking'] = ($attributes['value'] == 'TRANSPARENT');
					}
					break;
				case 'PRIORITY':
					if($this->productManufacturer == 'funambol')
					{
						$vcardData['priority'] = (int) $this->priority_funambol2egw[$attributes['value']];
					}
					else
					{
						$vcardData['priority'] = (int) $this->priority_ical2egw[$attributes['value']];
					}
					break;
				case 'CATEGORIES':
					if ($attributes['value'])
					{
						if($version == '1.0')
						{
							$vcardData['category'] = $this->find_or_add_categories(explode(';',$attributes['value']), $cal_id);
						}
						else
						{
							$vcardData['category'] = $this->find_or_add_categories(explode(',',$attributes['value']), $cal_id);
						}
					}
					else
					{
						$vcardData['category'] = array();
					}
					break;
				case 'ATTENDEE':
				case 'ORGANIZER':	// will be written direct to the event
					if (isset($attributes['params']['PARTSTAT']))
				    {
				    	$attributes['params']['STATUS'] = $attributes['params']['PARTSTAT'];
				    }
				    if (isset($attributes['params']['STATUS']))
					{
						$status = $this->status_ical2egw[strtoupper($attributes['params']['STATUS'])];
					}
					else
					{
						$status = 'U';
					}
					$cn = '';
					if (preg_match('/MAILTO:([@.a-z0-9_-]+)|MAILTO:"?([.a-z0-9_ -]*)"?[ ]*<([@.a-z0-9_-]*)>/i',
						$attributes['value'],$matches))
					{
						$email = $matches[1] ? $matches[1] : $matches[3];
						$cn = isset($matches[2]) ? $matches[2]: '';
					}
					elseif (preg_match('/"?([.a-z0-9_ -]*)"?[ ]*<([@.a-z0-9_-]*)>/i',
						$attributes['value'],$matches))
					{
						$cn = $matches[1];
						$email = $matches[2];
					}
					elseif (strpos($attributes['value'],'@') !== false)
					{
						$email = $attributes['value'];
					}
					else
					{
						$email = false;	// no email given
					}
					$searcharray = array();
					if ($email)
					{
						$searcharray = array('email' => $email, 'email_home' => $email);
					}
					if (isset($attributes['params']['CN']) && $attributes['params']['CN'])
					{
						if ($attributes['params']['CN'][0] == '"'
							&& substr($attributes['params']['CN'],-1) == '"')
						{
							$attributes['params']['CN'] = substr($attributes['params']['CN'],1,-1);
						}
						$searcharray['n_fn'] = $attributes['params']['CN'];
					}
					elseif ($cn)
					{
						$searcharray['n_fn'] = $cn;
					}
					if (($uid = $attributes['params']['X-EGROUPWARE-UID'])
						&& ($info = $this->resource_info($uid))
						&& (!$email || $info['email'] == $email))
					{
						// we use the (checked) X-EGROUPWARE-UID
					}

					//elseif (//$attributes['params']['CUTYPE'] == 'GROUP'
					elseif (preg_match('/(.*) Group/', $searcharray['n_fn'], $matches))
					{
						if (($uid =  $GLOBALS['egw']->accounts->name2id($matches[1], 'account_lid', 'g')))
						{
							//Horde::logMessage("vevent2egw: group participant $uid",
							//			__FILE__, __LINE__, PEAR_LOG_DEBUG);
							if ($status != 'U')
							{
								// User tries to reply to the group invitiation
								$members = $GLOBALS['egw']->accounts->members($uid, true);
								if (in_array($this->user, $members))
								{
									//Horde::logMessage("vevent2egw: set status to " . $status,
									//		__FILE__, __LINE__, PEAR_LOG_DEBUG);
									$event['participants'][$this->user] =
										calendar_so::combine_status($status);
								}
								$status = 'U'; // keep the group
							}
						}
						else continue; // can't find this group
					}
					elseif ($attributes['value'] == 'Unknown')
					{
						$uid = $this->user;
					}
					elseif ($email && ($uid = $GLOBALS['egw']->accounts->name2id($email,'account_email')))
					{
						// we use the account we found
					}
					elseif (!$searcharray)
					{
						continue;	// participants without email AND CN --> ignore it
					}
					elseif ((list($data) = ExecMethod2('addressbook.addressbook_bo.search',$searcharray,
						array('id','egw_addressbook.account_id as account_id','n_fn'),'egw_addressbook.account_id IS NOT NULL DESC, n_fn IS NOT NULL DESC','','',false,'OR')))
					{
						$uid = $data['account_id'] ? (int)$data['account_id'] : 'c'.$data['id'];
					}
					else
					{
						if (!$email)
						{
							$email = 'no-email@egroupware.org';	// set dummy email to store the CN
						}
						$uid = 'e'.($attributes['params']['CN'] ? $attributes['params']['CN'].' <'.$email.'>' : $email);
					}
					switch($attributes['name'])
					{
						case 'ATTENDEE':
							if (!isset($attributes['params']['ROLE']) &&
								isset($event['owner']) && $event['owner'] == $uid)
							{
								$attributes['params']['ROLE'] = 'CHAIR';
							}
							// add quantity and role
							$event['participants'][$uid] =
								calendar_so::combine_status($status,
									$attributes['params']['X-EGROUPWARE-QUANTITY'],
									$attributes['params']['ROLE']);
							break;

						case 'ORGANIZER':
							if (isset($event['participants'][$uid]))
							{
								$status = $event['participants'][$uid];
								calendar_so::split_status($status, $quantity, $role);
								$event['participants'][$uid] =
									calendar_so::combine_status($status, $quantity, 'CHAIR');
							}
							if (is_numeric($uid) && ($uid == $this->calendarOwner || !$this->calendarOwner))
							{
								// we can store the ORGANIZER as event owner
								$event['owner'] = $uid;
							}
							else
							{
								// we must insert a CHAIR participant to keep the ORGANIZER
								$event['owner'] = $this->user;
								if (!isset($event['participants'][$uid]))
								{
									// save the ORGANIZER as event CHAIR
									$event['participants'][$uid] =
										calendar_so::combine_status('U', 1, 'CHAIR');
								}
							}
					}
					break;
				case 'CREATED':		// will be written direct to the event
					if ($event['modified']) break;
					// fall through
				case 'LAST-MODIFIED':	// will be written direct to the event
					$event['modified'] = $attributes['value'];
					break;
			}
		}

		// check if the entry is a birthday
		// this field is only set from NOKIA clients
		$agendaEntryType = $component->getAttribute('X-EPOCAGENDAENTRYTYPE');
		if (!is_a($agendaEntryType, 'PEAR_Error'))
		{
			if (strtolower($agendaEntryType) == 'anniversary')
			{
				$event['special'] = '1';
				$event['non_blocking'] = 1;
				// make it a whole day event for eGW
				$vcardData['end'] = $vcardData['start'] + 86399;
			}
			elseif (strtolower($agendaEntryType) == 'event')
			{
				$event['special'] = '2';
				$event['non_blocking'] = 1;
			}
		}

		$event['priority'] = 2; // default
		$event['alarm'] = $alarms;

		// now that we know what the vard provides,
		// we merge that data with the information we have about the device
		while (($fieldName = array_shift($supportedFields)))
		{
			switch ($fieldName)
			{
				case 'recur_interval':
				case 'recur_enddate':
				case 'recur_data':
				case 'recur_exception':
					// not handled here
					break;

				case 'recur_type':
					$event['recur_type'] = $vcardData['recur_type'];
					if ($event['recur_type'] != MCAL_RECUR_NONE)
					{
						$event['reference'] = 0;
						foreach (array('recur_interval','recur_enddate','recur_data','recur_exception') as $r)
						{
							if (isset($vcardData[$r]))
							{
								$event[$r] = $vcardData[$r];
							}
						}
					}
					break;

				default:
					if (isset($vcardData[$fieldName]))
					{
						$event[$fieldName] = $vcardData[$fieldName];
					}
				break;
			}
		}
		if (!empty($event['recur_enddate']))
		{
			// reset recure_enddate to 00:00:00 on the last day
			$rriter = calendar_rrule::event2rrule($event, false);
			$rriter->rewind();
			$last = clone $rriter->time;
			while ($rriter->current <= $rriter->enddate)
			{
				$last = clone $rriter->current;
				$rriter->next_no_exception();
			}
			$last->setTime(0, 0, 0);
			$event['recur_enddate'] = $this->date2ts($last);
		}

		if ($this->calendarOwner) $event['owner'] = $this->calendarOwner;
		//Horde::logMessage("vevent2egw:\n" . print_r($event, true),
        //    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $event;
	}

	function search($_vcalData, $contentID=null, $relax=false)
	{
		if (($events = $this->icaltoegw($_vcalData,!is_null($contentID) ? $contentID : -1)))
		{
			// this function only supports searching a single event
			if (count($events) == 1)
			{
				$event = array_shift($events);
				return $this->find_event($event, $relax);
			}
		}
		return false;
	}

	/**
	 * Create a freebusy vCal for the given user(s)
	 *
	 * @param int $user account_id
	 * @param mixed $end=null end-date, default now+1 month
	 * @param boolean $utc=true if false, use severtime for dates
	 * @return string
	 */
	function freebusy($user,$end=null,$utc=true)
	{
		if (!$end) $end = $this->now_su + 100*DAY_s;	// default next 100 days

		$vcal = new Horde_iCalendar;
		$vcal->setAttribute('PRODID','-//eGroupWare//NONSGML eGroupWare Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
		$vcal->setAttribute('VERSION','2.0');

		$vfreebusy = Horde_iCalendar::newComponent('VFREEBUSY',$vcal);
		$parameters = array(
			'ORGANIZER' => $GLOBALS['egw']->translation->convert(
				$GLOBALS['egw']->accounts->id2name($user,'account_firstname').' '.
				$GLOBALS['egw']->accounts->id2name($user,'account_lastname'),
				$GLOBALS['egw']->translation->charset(),'utf-8'),
		);
		if ($utc)
		{
			foreach (array(
				'URL' => $this->freebusy_url($user),
				'DTSTART' => $this->date2ts($this->now_su,true),	// true = server-time
				'DTEND' => $this->date2ts($end,true),	// true = server-time
		  		'ORGANIZER' => $GLOBALS['egw']->accounts->id2name($user,'account_email'),
				'DTSTAMP' => time(),
			) as $attr => $value)
			{
				$vfreebusy->setAttribute($attr, $value);
			}
		}
		else
		{
			foreach (array(
				'URL' => $this->freebusy_url($user),
				'DTSTART' => date('Ymd\THis',$this->date2ts($this->now_su,true)),	// true = server-time
				'DTEND' => date('Ymd\THis',$this->date2ts($end,true)),	// true = server-time
		  		'ORGANIZER' => $GLOBALS['egw']->accounts->id2name($user,'account_email'),
				'DTSTAMP' => date('Ymd\THis',time()),
			) as $attr => $value)
			{
				$vfreebusy->setAttribute($attr, $value);
			}
		}
		$fbdata = parent::search(array(
			'start' => $this->now_su,
			'end'   => $end,
			'users' => $user,
			'date_format' => 'server',
			'show_rejected' => false,
		));
		if (is_array($fbdata))
		{
			foreach ($fbdata as $event)
			{
				if ($event['non_blocking']) continue;

				if ($utc)
				{
					$vfreebusy->setAttribute('FREEBUSY',array(array(
						'start' => $event['start'],
						'end' => $event['end'],
					)));
				}
				else
				{
					$vfreebusy->setAttribute('FREEBUSY',array(array(
						'start' => date('Ymd\THis',$event['start']),
						'end' => date('Ymd\THis',$event['end']),
					)));
				}
			}
		}
		$vcal->addComponent($vfreebusy);

		return $vcal->exportvCalendar();
	}

	/**
	 * update the status of all participant for a given recurrence or for all recurrences since now (includes recur_date=0)
	 *
	 * @param array $new_event event-array with the new stati
	 * @param array $old_event event-array with the old stati
	 * @param int $recur_date=0 date to change, or 0 = all since now
	 */
	function update_status($new_event, $old_event , $recur_date=0)
	{
		// check the old list against the new list
		foreach ($old_event['participants'] as $userid => $status)
  		{
            if (!isset($new_event['participants'][$userid])){
            	// Attendee will be deleted this way
            	$new_event['participants'][$userid] = 'G';
            }
            elseif ($new_event['participants'][$userid] == $status)
            {
            	// Same status -- nothing to do.
            	unset($new_event['participants'][$userid]);
            }
		}
		// write the changes
		foreach ($new_event['participants'] as $userid => $status)
		{
			$this->set_status($old_event, $userid, $status, $recur_date, true, false);
		}
    }

    /**
     * classifies an incoming event from the eGW point-of-view
     *
     * exceptions: unlike other calendar apps eGW does not create an event exception
     * if just the participant state changes - therefore we have to distinguish between
     * real exceptions and status only exceptions
     *
     * @param array $event the event to check
     *
     * @return array
     * 	type =>
     * 		SINGLE a single event
     * 		SERIES-MASTER the series master
     * 		SERIES-EXCEPTION event is a real exception
	  * 		SERIES-EXCEPTION-STATUS event is a status only exception
	  * 		SERIES-EXCEPTION-PROPAGATE event was a status only exception in the past and is now a real exception
	  * 	stored_event => if event already exists in the database array with event data or false
	  * 	master_event => for event type SERIES-EXCEPTION, SERIES-EXCEPTION-STATUS or SERIES-EXCEPTION-PROPAGATE
	  * 		the corresponding series master event array
	  * 		NOTE: this param is false if event is of type SERIES-MASTER
     */
    private function get_event_info($event)
    {
			$type = 'SINGLE'; // default
			$return_master = false; //default

			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$type = 'SERIES-MASTER';
			}
			else
			{
				// SINGLE, SERIES-EXCEPTION OR SERIES-EXCEPTON-STATUS
				if (empty($event['uid']) && $event['id'] > 0 && ($stored_event = $this->read($event['id'])))
				{
					$event['uid'] = $stored_event['uid']; // restore the UID if it was not delivered
				}

				if (isset($event['uid'])
					&& $event['recurrence']
					&& ($master_event = $this->read($event['uid']))
					&& isset($master_event['recur_type'])
					&& $master_event['recur_type'] != MCAL_RECUR_NONE)
				{
					// SERIES-EXCEPTION OR SERIES-EXCEPTON-STATUS
					$return_master = true; // we have a valid master and can return it

					if (isset($event['id']) && $master_event['id'] != $event['id'])
					{
						$type = 'SERIES-EXCEPTION'; // this is an existing exception
					}
					else
					{
						$type = 'SERIES-EXCEPTION-STATUS'; // default if we cannot find a proof for a fundamental change
						// the recurrence_event is the master event with start and end adjusted to the recurrence
						$recurrence_event = $master_event;
						$recurrence_event['start'] = $event['recurrence'];
						$recurrence_event['end'] = $event['recurrence'] + ($master_event['end'] - $master_event['start']);
						// check for changed data
						foreach (array('start','end','uid','title','location',
									'priority','public','special','non_blocking') as $key)
						{
							if (!empty($event[$key]) && $recurrence_event[$key] != $event[$key])
							{
								if (isset($event['id']))
								{
									$type = 'SERIES-EXCEPTION-PROPAGATE';
								}
								else
								{
									$type = 'SERIES-EXCEPTION'; // this is a new exception
								}
								break;
							}
						}
						// the event id here is always the id of the master event
						// unset it to prevent confusion of stored event and master event
						unset($event['id']);
					}
				}
				else
				{
					// SINGLE
					$type = 'SINGLE';
				}
			}

			// read existing event
			if (isset($event['id']))
			{
				$stored_event = $this->read($event['id']);
			}

			// check ACL
			if ($return_master)
			{
				$acl_edit = $this->check_perms(EGW_ACL_EDIT, $master_event['id']);
			}
			else
			{
				if (is_array($stored_event))
				{
					$acl_edit = $this->check_perms(EGW_ACL_EDIT, $stored_event['id']);
				}
				else
				{
					$acl_edit = true; // new event
				}
			}

			return array(
				'type' => $type,
				'acl_edit' => $acl_edit,
				'stored_event' => is_array($stored_event) ? $stored_event : false,
				'master_event' => $return_master ? $master_event : false,
			);
    }
}
