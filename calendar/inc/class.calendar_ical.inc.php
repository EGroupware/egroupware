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
	 * @var array $status_ical2egw conversion of the priority egw => ical
	 */
	var $priority_egw2ical = array(
		0 => 0,		// undefined
		1 => 9,		// low
		2 => 5,		// normal
		3 => 1,		// high
	);

	/**
	 * @var array $status_ical2egw conversion of the priority ical => egw
	 */
	var $priority_ical2egw = array(
		0 => 0,		// undefined
		9 => 1,	8 => 1, 7 => 1, 6 => 1,	// low
		5 => 2,		// normal
		4 => 3, 3 => 3, 2 => 3, 1 => 3,	// high
	);

	/**
	 * @var array $recur_egw2ical_2_0 converstaion of egw recur-type => ical FREQ
	 */
	var $recur_egw2ical_2_0 = array(
		MCAL_RECUR_DAILY        => 'DAILY',
		MCAL_RECUR_WEEKLY       => 'WEEKLY',
		MCAL_RECUR_MONTHLY_MDAY => 'MONTHLY',	// BYMONHTDAY={1..31}
		MCAL_RECUR_MONTHLY_WDAY => 'MONTHLY',	// BYDAY={1..5}{MO..SO}
		MCAL_RECUR_YEARLY       => 'YEARLY',
	);

	/**
	 * @var array $recur_egw2ical_1_0 converstaion of egw recur-type => ical FREQ
	 */
	var $recur_egw2ical_1_0 = array(
		MCAL_RECUR_DAILY        => 'D',
		MCAL_RECUR_WEEKLY       => 'W',
		MCAL_RECUR_MONTHLY_MDAY => 'MD',	// BYMONHTDAY={1..31}
		MCAL_RECUR_MONTHLY_WDAY => 'MP',	// BYDAY={1..5}{MO..SO}
		MCAL_RECUR_YEARLY       => 'YM',
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
		if($this->log) $this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-vcal";
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
			'ORGANIZER'		=> 'owner',
			'ATTENDEE'		=> 'participants',
			'RRULE'			=> 'recur_type',
			'EXDATE'		=> 'recur_exception',
			'PRIORITY'		=> 'priority',
			'TRANSP'		=> 'non_blocking',
			'CATEGORIES'	=> 'category',
			'UID'			=> 'uid',
			'RECURRENCE-ID' => 'recurrence',
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

		while (($event = array_pop($events)))
		{
			if (strpos($this->productName, 'palmos'))
			{
				$servertime = true;
				$date_format = 'ts';
			}
			else
			{
				$servertime = false;
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

			if ($this->log) error_log(__FILE__.'('.__LINE__.'): '.__METHOD__.' '.array2string($event)."\n",3,$this->logfile);

			if (!$servertime && $event['recur_type'] != MCAL_RECUR_NONE)
			{
				if ($event['recur_enddate'])
				{
					$startDST = date('I', $event['start']);
					$finalDST = date('I', $event['recur_enddate']);
					// Different DST or more than half a year?
					if ($startDST != $finalDST ||
						($event['recur_enddate'] - $event['start']) > 15778800)
					{
						$servertime = true;
						$date_format = 'ts';
						// read the event again with timestamps
						$event = $this->read($event['id'], 0, false, $date_format);
					}
				}
				else
				{
					$servertime = true;
					$date_format = 'ts';
					// read the event again with timestamps
					$event = $this->read($event['id'], 0, false, $date_format);
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

			if ($servertime)
			{
				$serverTZ = $this->generate_vtimezone($event['start'], $vevent);
			}
			else
			{
				$serverTZ = null;
			}

			if ($this->productManufacturer == 'sonyericsson')
			{
				$eventDST = date('I', $event['start']);
				if ($eventDST)
				{
					$attributes['X-SONYERICSSON-DST'] = 4;
				}
			}

			foreach($egwSupportedFields as $icalFieldName => $egwFieldName)
			{
				$values[$icalFieldName] = array();
				switch($icalFieldName)
				{
					case 'ATTENDEE':
						//if (count($event['participants']) == 1 && isset($event['participants'][$this->user])) break;
						foreach((array)$event['participants'] as $uid => $status)
						{
							if (!($info = $this->resource_info($uid))) continue;
							if ($uid == $event['owner']) continue; // Organizer
							// RB: MAILTO href contains only the email-address, NO cn!
							$attributes['ATTENDEE'][]	= $info['email'] ? 'MAILTO:'.$info['email'] : '';
							// ROLE={CHAIR|REQ-PARTICIPANT|OPT-PARTICIPANT|NON-PARTICIPANT|X-*}
							calendar_so::split_status($status,$quantity,$role);
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
							$parameters['ATTENDEE'][] = array(
								'CN'       => '"'.($info['cn'] ? $info['cn'] : $info['name']).'"',
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

    				case 'ORGANIZER':	// according to iCalendar standard, ORGANIZER not used for events in the own calendar
    					//if ($event['owner'] != $this->user)
    					//if (!isset($event['participants'][$event['owner']]) || count($event['participants']) > 1)
    					//{
							$mailtoOrganizer = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');
							$attributes['ORGANIZER'] = $mailtoOrganizer ? 'MAILTO:'.$mailtoOrganizer : '';
							$parameters['ORGANIZER']['CN'] = '"'.trim($GLOBALS['egw']->accounts->id2name($event['owner'],'account_firstname').' '.
								$GLOBALS['egw']->accounts->id2name($event['owner'],'account_lastname')).'"';
    					//}
						break;

					case 'DTSTART':
						if ($servertime)
						{
							$attributes['DTSTART'] = date('Ymd\THis', $event['start']);
							if ($serverTZ) $parameters['DTSTART']['TZID'] = $serverTZ;
						}
						else
						{
							$attributes['DTSTART'] = $event['start'];
						}
						break;

					case 'DTEND':
						// write start + end of whole day events as dates
						if ($this->isWholeDay($event))
						{
							$event['end-nextday'] = $event['end'] + 12*3600;	// we need the date of the next day, as DTEND is non-inclusive (= exclusive) in rfc2445
							foreach(array('start' => 'DTSTART','end-nextday' => 'DTEND') as $f => $t)
							{
								$arr = $this->date2array($event[$f]);
								$vevent->setAttribute($t, array('year' => $arr['year'],'month' => $arr['month'],'mday' => $arr['day']),
									array('VALUE' => 'DATE'));
							}
							unset($attributes['DTSTART']);
						}
						else
						{
							if ($servertime)
							{
								$attributes['DTEND'] = date('Ymd\THis', $event['end']);
								if ($serverTZ) $parameters['DTEND']['TZID'] = $serverTZ;
							}
							else
							{
								$attributes['DTEND'] = $event['end'];
							}
						}
						break;

					case 'RRULE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;		// no recuring event
						if ($version == '1.0') {
							$interval = ($event['recur_interval'] > 1) ? $event['recur_interval'] : 1;
							$rrule = array('FREQ' => $this->recur_egw2ical_1_0[$event['recur_type']].$interval);
							switch ($event['recur_type'])
							{
								case MCAL_RECUR_WEEKLY:
									$days = array();
									foreach($this->recur_days_1_0 as $id => $day)
									{
										if ($event['recur_data'] & $id) $days[] = strtoupper(substr($day,0,2));
									}
									$rrule['BYDAY'] = implode(' ',$days);
									$rrule['FREQ'] = $rrule['FREQ'].' '.$rrule['BYDAY'];
									break;

								case MCAL_RECUR_MONTHLY_MDAY:	// date of the month: BYMONTDAY={1..31}
									break;

									case MCAL_RECUR_MONTHLY_WDAY:	// weekday of the month: BDAY={1..5}{MO..SO}
										$rrule['BYDAY'] = (1 + (int) ((date('d',$event['start'])-1) / 7)).'+ '.
											strtoupper(substr(date('l',$event['start']),0,2));
									$rrule['FREQ'] = $rrule['FREQ'].' '.$rrule['BYDAY'];
									break;
							}

							if ($event['recur_enddate'])
							{
								$recur_enddate = (int)$event['recur_enddate'];
								$recur_enddate += 24 * 60 * 60 - 1;
								$rrule['UNTIL'] = $vcal->_exportDateTime($recur_enddate);
							}
							else
							{
								$rrule['UNTIL'] = '#0';
							}

							$attributes['RRULE'] = $rrule['FREQ'].' '.$rrule['UNTIL'];
						} else {
							$rrule = array('FREQ' => $this->recur_egw2ical_2_0[$event['recur_type']]);
							switch ($event['recur_type'])
							{
								case MCAL_RECUR_WEEKLY:
									$days = array();
									foreach($this->recur_days as $id => $day)
									{
										if ($event['recur_data'] & $id) $days[] = strtoupper(substr($day,0,2));
									}
									$rrule['BYDAY'] = implode(',',$days);
									break;

								case MCAL_RECUR_MONTHLY_MDAY:	// date of the month: BYMONTDAY={1..31}
									$rrule['BYMONTHDAY'] = (int) date('d',$event['start']);
									break;

									case MCAL_RECUR_MONTHLY_WDAY:	// weekday of the month: BDAY={1..5}{MO..SO}
										$rrule['BYDAY'] = (1 + (int) ((date('d',$event['start'])-1) / 7)).
											strtoupper(substr(date('l',$event['start']),0,2));
									break;
							}
							if ($event['recur_interval'] > 1)
							{
								$rrule['INTERVAL'] = $event['recur_interval'];
							}
							if ($event['recur_enddate'])
							{
								// We use end of day in vCal
								$recur_enddate = (int)$event['recur_enddate'];
								$recur_enddate += 24 * 60 * 60 - 1;
								if ($this->isWholeDay($event))
								{
									$rrule['UNTIL'] = date('Ymd', $recur_enddate);
								}
								else
								{
									$rrule['UNTIL'] = $vcal->_exportDateTime($recur_enddate);
								}
							}
							// no idea how to get the Horde parser to produce a standard conformant
							// RRULE:FREQ=... (note the double colon after RRULE, we cant use the $parameter array)
							// so we create one value manual ;-)
							foreach($rrule as $name => $value)
							{
								$attributes['RRULE'][] = $name . '=' . $value;
							}
							$attributes['RRULE'] = implode(';',$attributes['RRULE']);
						}
						break;

					case 'EXDATE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;
						$days = array();
						// dont use "virtual" exceptions created by participant status for GroupDAV or file export
						if (!in_array($this->productManufacturer,array('file','groupdav')))
						{
							$participants = $this->so->get_participants($event['id'], 0);

							// Check if the stati for all participants are identical for all recurrences
							foreach ($participants as $uid => $attendee)
							{
								switch ($attendee['type'])
								{
									case 'u':	// account
									case 'c':	// contact
									case 'e':	// email address
										$recurrences = $this->so->get_recurrences($event['id'], $uid);
										foreach ($recurrences as $rdate => $recur_status)
										{
											if ($rdate && $recur_status != $recurrences[0])
											{
												// Every distinct status results in an exception
												$days[] = $rdate;
											}
										}
										break;
									default: // We don't handle the rest
										break;
								}
							}
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
								foreach($days as $id => $timestamp)
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
								if ($servertime)
								{
									foreach($days as $id => $timestamp)
									{
										$days[$id] = date('Ymd\THis', $timestamp);

									}
								}
							}
							$attributes['EXDATE'] = '';
							$values['EXDATE'] = $days;
							if ($serverTZ) $parameters['EXDATE']['TZID'] = $serverTZ;
							$parameters['EXDATE']['VALUE'] = $value_type;
						}
						break;

					case 'PRIORITY':
							$attributes['PRIORITY'] = (int) $this->priority_egw2ical[$event['priority']];
							break;

						case 'TRANSP':
						if ($version == '1.0') {
							$attributes['TRANSP'] = ($event['non_blocking'] ? 1 : 0);
						} else {
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
						if ($recur_date)
						{
							// We handle a status only exception
							if ($this->isWholeDay($event))
							{
								$arr = $this->date2array($recur_date);
								$vevent->setAttribute('RECURRENCE-ID', array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}
							else
							{
								if ($servertime)
								{
									$attributes['RECURRENCE-ID'] = date('Ymd\THis', $recur_date);
									if ($serverTZ) $parameters['RECURRENCE-ID']['TZID'] = $serverTZ;
								}
								else
								{
									$attributes['RECURRENCE-ID'] = $recur_date;
								}
							}
						}
						elseif ($event['recurrence'] && $event['reference'])
						{
							// $event['reference'] is a calendar_id, not a timestamp
							if (!($revent = $this->read($event['reference']))) break;	// referenced event does not exist

							if ($this->isWholeDay($revent))
							{
								$arr = $this->date2array($event['recurrence']);
								$vevent->setAttribute('RECURRENCE-ID', array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}
							else
							{
								if ($servertime)
								{
									$attributes['RECURRENCE-ID'] = date('Ymd\THis', $event['recurrence']);
									if ($serverTZ) $parameters['RECURRENCE-ID']['TZID'] = $serverTZ;
								}
								else
								{
									$attributes['RECURRENCE-ID'] = $event['recurrence'];
								}
							}
							unset($revent);
						}
						break;

					default:
						if (isset($this->clientProperties[$icalFieldName]['Size'])) {
							$size = $this->clientProperties[$icalFieldName]['Size'];
							$noTruncate = $this->clientProperties[$icalFieldName]['NoTruncate'];
							#Horde::logMessage("vCalendar $icalFieldName Size: $size, NoTruncate: " .
							#	($noTruncate ? 'TRUE' : 'FALSE'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
						} else {
							$size = -1;
							$noTruncate = false;
						}
						$value = $event[$egwFieldName];
						$cursize = strlen($value);
						if (($size > 0) && $cursize > $size) {
							if ($noTruncate) {
								Horde::logMessage("vCalendar $icalFieldName omitted due to maximum size $size",
									__FILE__, __LINE__, PEAR_LOG_WARNING);
								continue; // skip field
							}
							// truncate the value to size
							$value = substr($value, 0, $size - 1);
							Horde::logMessage("vCalendar $icalFieldName truncated to maximum size $size",
								__FILE__, __LINE__, PEAR_LOG_INFO);
						}
						if (!empty($value) || ($size >= 0 && !$noTruncate)) {
							$attributes[$icalFieldName] = $value;
						}
					break;
				}
			}

			if($this->productManufacturer == 'nokia') {
				if($event['special'] == '1') {
					$attributes['X-EPOCAGENDAENTRYTYPE'] = 'ANNIVERSARY';
					$attributes['DTEND'] = $attributes['DTSTART'];
				}
				elseif ($event['special'] == '2') {
					$attributes['X-EPOCAGENDAENTRYTYPE'] = 'EVENT';
				} else {
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
			foreach($event['alarm'] as $alarmID => $alarmData)
			{
				// skip alarms being set for all users or alarms owned by other users
				if($alarmData['all'] == true || $alarmData['owner'] != $GLOBALS['egw_info']['user']['account_id'])
				{
					continue;
				}

				if ($version == '1.0')
				{
					if ($servertime)
					{
						$attributes['DALARM'] = date('Ymd\THis', $alarmData['time']);
						if ($serverTZ) $parameters['DALARM']['TZID'] = $serverTZ;
						$attributes['AALARM'] = date('Ymd\THis', $alarmData['time']);
						if ($serverTZ) $parameters['AALARM']['TZID'] = $serverTZ;
					}
					else
					{
						$attributes['DALARM'] = $alarmData['time'];
						$attributes['AALARM'] = $alarmData['time'];
					}
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
						if ($servertime)
						{
							$value = date('Ymd\THis', $alarmData['time']);
							if ($serverTZ) $params['TZID'] = $serverTZ;
						}
						else
						{
							$value = $alarmData['time'];
						}
						$valarm->setAttribute('TRIGGER', $value, $params);
					}

					$valarm->setAttribute('ACTION','DISPLAY');
					$valarm->setAttribute('DESCRIPTION',$event['title'] ? $event['title'] : $description);
					$vevent->addComponent($valarm);
				}
			}

			foreach($attributes as $key => $value)
			{
				foreach(is_array($value)&&$parameters[$key]['VALUE']!='DATE' ? $value : array($value) as $valueID => $valueData)
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
						if($this->log)error_log(__LINE__.__METHOD__.__FILE__." Has invalid XML data: $valueData",3,$this->logfile);
					}
					$vevent->setParameter($key, $options);
				}
			}
			$vcal->addComponent($vevent);
		}

		$retval = $vcal->exportvCalendar();
 		if($this->log)error_log(__LINE__.__METHOD__.__FILE__.array2string($retval)."\n",3,$this->logfile);
		return $retval;

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
		if($this->log)error_log(__LINE__.__METHOD__.__FILE__.array2string($_vcalData)."\n",3,$this->logfile);

		if(!$events = $this->icaltoegw($_vcalData,$cal_id,$etag,$recur_date))
		{
			return false;
		}

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		foreach ($events as $event)
		{
			$updated_id = false;
			$event_info = $this->get_event_info($event);

			// common adjustments for new events
			if(!is_array($event_info['stored_event']))
			{
				// set non blocking all day depending on the user setting
				if($this->isWholeDay($event) && $this->nonBlockingAllday)
				{
					$event['non_blocking'] = 1;
				}

				// check if an owner is set and the current user has add rights
				// for that owners calendar; if not set the current user
				if(!isset($event['owner'])
					|| !$this->check_perms(EGW_ACL_ADD,0,$event['owner']))
				{
					$event['owner'] = $GLOBALS['egw_info']['user']['account_id'];
				}

				// add ourself to new events as participant
				if(!isset($this->supportedFields['participants'])
					||!isset($event['participants'][$GLOBALS['egw_info']['user']['account_id']]))
 				{
					$event['participants'][$GLOBALS['egw_info']['user']['account_id']] = 'A';
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

			// save event depending on the given event type
			switch($event_info['type'])
			{
				case 'SINGLE':
					Horde::logMessage('importVCAL event SINGLE',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					// update the event
					if($event_info['acl_edit'])
					{
						$event_to_store = $event; // prevent $event from being changed by the update method
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-MASTER':
					Horde::logMessage('importVCAL event SERIES-MASTER',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					// remove all known "status only" exceptions and update the event
					if($event_info['acl_edit'])
					{
						$days = $this->so->get_recurrence_exceptions($event);
						if(is_array($days))
						{
							$recur_exceptions = array();
							foreach($event['recur_exception'] as $recur_exception)
							{
								if(!in_array($recur_exception, $days))
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
					if($event_info['acl_edit'])
					{
						if(isset($event_info['stored_event']['id']))
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
						}

						$event_to_store = $event; // prevent $event from being changed by update method
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-EXCEPTION-STATUS':
					Horde::logMessage('importVCAL event SERIES-EXCEPTION-STATUS',__FILE__, __LINE__, PEAR_LOG_DEBUG);

					if($event_info['acl_edit'])
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
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}

					break;
			}

			// read stored event into info array for fresh stored (new) events
			if(!is_array($event_info['stored_event']) && $updated_id > 0)
			{
				$event_info['stored_event'] = $this->read($updated_id);
			}

			// update status depending on the given event type
			switch($event_info['type'])
			{
				case 'SINGLE':
				case 'SERIES-MASTER':
				case 'SERIES-EXCEPTION':
				case 'SERIES-EXCEPTION-PROPAGATE':
					if(is_array($event_info['stored_event'])) // status update requires a stored event
					{
						if($event_info['acl_edit'])
						{
							// update all participants if we have the right to do that
							$this->update_status($event, $event_info['stored_event']);
						}
						elseif(isset($event['participants'][$this->user]) || isset($event_info['stored_event']['participants'][$this->user]))
						{
							// update the users status only
							$this->set_status($event_info['stored_event']['id'], $this->user,
								($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), 0, true);
						}
					}
					break;

				case 'SERIES-EXCEPTION-STATUS':
					if(is_array($event_info['master_event'])) // status update requires a stored master event
					{
						if($event_info['acl_edit'])
						{
							// update all participants if we have the right to do that
							$this->update_status($event, $event_info['master_event'], $event['recurrence']);
						}
						elseif(isset($event['participants'][$this->user]) || isset($event_info['master_event']['participants'][$this->user]))
						{
							// update the users status only
							$this->set_status($event_info['master_event']['id'], $this->user,
								($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), $event['recurrence'], true);
						}
					}
					break;
			}

			// update alarms depending on the given event type
			if(isset($this->supportedFields['alarm'])
				&& is_array($event_info['stored_event']) // alarm update requires a stored event
			)
			{
				switch($event_info['type'])
				{
					case 'SINGLE':
					case 'SERIES-MASTER':
					case 'SERIES-EXCEPTION':
					case 'SERIES-EXCEPTION-PROPAGATE':
						// delete old alarms
						if(count($event_info['stored_event']['alarm']) > 0)
						{
							foreach ($event_info['stored_event']['alarm'] as $alarm_id => $alarm_data)
							{
								// only touch own alarms
								if($alarm_data['all'] == false && $alarm_data['owner'] == $GLOBALS['egw_info']['user']['account_id'])
								{
									$this->delete_alarm($alarm_id);
								}
							}
						}

						// save given alarms
						if(count($event['alarm']) > 0)
						{
							foreach ($event['alarm'] as $alarm)
							{
								if(!isset($alarm['offset']) && isset($alarm['time']))
								{
									$alarm['offset'] = $event['start'] - $alarm['time'];
								}
								if(!isset($alarm['time']) && isset($alarm['offset']))
								{
									$alarm['time'] = $event['start'] - $alarm['offset'];
								}
								$alarm['owner'] = $GLOBALS['egw_info']['user']['account_id'];
								$alarm['all'] = false;
								$this->save_alarm($event_info['stored_event']['id'], $alarm);
							}
						}
						break;

					case 'SERIES-EXCEPTION-STATUS':
						// nothing to do here
						break;
				}
			}

			// choose which id to return to the client
			switch($event_info['type'])
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
					if($event_info['acl_edit'] && is_array($event_info['stored_event']))
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

			if($this->log)
			{
				$egw_event = $this->read($event['id']);
				error_log(__LINE__.__METHOD__.__FILE__.array2string($egw_event)."\n",3,$this->logfile);
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
		foreach($components as $attribute)
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
		foreach($valarm->_attributes as $vattr)
		{
			switch($vattr['name'])
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
		if (isset($state)) {
			$deviceInfo = $state->getClientDeviceInfo();
		}

		// store product manufacturer and name, to be able to use it elsewhere
		if ($_productManufacturer) {
				$this->productManufacturer = strtolower($_productManufacturer);
				$this->productName = strtolower($_productName);
		}

		if(isset($deviceInfo) && is_array($deviceInfo)) {
			if(isset($deviceInfo['uidExtension']) &&
				$deviceInfo['uidExtension']){
					$this->uidExtension = true;
				}
			if(isset($deviceInfo['nonBlockingAllday']) &&
				$deviceInfo['nonBlockingAllday']){
					$this->nonBlockingAllday = true;
				}
			if(!isset($this->productManufacturer) ||
				 $this->productManufacturer == '' ||
				 $this->productManufacturer == 'file') {
				$this->productManufacturer = strtolower($deviceInfo['manufacturer']);
			}
			if(!isset($this->productName) || $this->productName == '') {
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
		);


		switch($this->productManufacturer)
		{
			case 'nexthaus corporation':
			case 'nexthaus corp':
				switch($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['nexthaus'];
						break;
				}
				break;

			// multisync does not provide anymore information then the manufacturer
			// we suppose multisync with evolution
			case 'the multisync project':
				switch($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['basic'];
						break;
				}
				break;

			case 'siemens':
				switch($this->productName)
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
				switch($this->productName)
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
				switch($this->productName)
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
				switch($this->productName)
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
				switch($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['full'];
				}
				break;

			// the fallback for SyncML
			default:
				error_log("Unknown calendar SyncML client: manufacturer='$this->productManufacturer'  product='$this->productName'");
				$this->supportedFields = $defaultFields['synthesis'];
				break;
		}
	}

	function icaltoegw($_vcalData, $cal_id=-1, $etag=null, $recur_date=0)
	{
		$events = array();

		$vcal = new Horde_iCalendar;
		if (!$vcal->parsevCalendar($_vcalData)) return false;
		$version = $vcal->getAttribute('VERSION');
		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		foreach ($vcal->getComponents() as $component)
		{
			if (is_a($component, 'Horde_iCalendar_vevent'))
			{
				if ($event = $this->vevent2egw($component, $version, $this->supportedFields))
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

		// decide what to return
		if(count($events) == 1)
		{
			$event = array_shift($events);
			if($cal_id > 0) $event['id'] = $cal_id;
			if(!is_null($etag)) $event['etag'] = $etag;
			if($recur_date) $event['recurrence'] = $recur_date;

			return array($event);
		}
		else if($count($events) == 0 || $cal_id > 0 || !is_null($etag) || $recur_date)
		{
			// no events to return
			// or not allowed N:1 relation with params just meant for a single event
			return false;
		}
		else
		{
			return $events;
		}
	}

	/**
	 * Parse a VEVENT
	 *
	 * @param array $component			VEVENT
	 * @param string $version			vCal version (1.0/2.0)
	 * @param array $supportedFields	supported fields of the device
	 *
	 * @return array|boolean			event on success, false on failure
	 */
	function vevent2egw(&$component, $version, $supportedFields)
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
		// we parse DTSTART first
		foreach ($component->_attributes as $attributes)
		{
			if ($attributes['name'] == 'DTSTART')
			{
				if (isset($attributes['params']['VALUE'])
						&& $attributes['params']['VALUE'] == 'DATE')
				{
					$isDate = true;
				}
				$vcardData['start']		= $attributes['value'];
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
					if (preg_match('/\s*\[UID:(.+)?\]/Usm', $attributes['value'], $matches)) {
						if (!isset($vCardData['uid'])
								&& strlen($matches[1]) >= $minimum_uid_length) {
							$vcardData['uid'] = $matches[1];
						}
					}
					break;
				case 'DTEND':
					$dtend_ts = is_numeric($attributes['value']) ? $attributes['value'] : $this->date2ts($attributes['value']);
					if(date('H:i:s',$dtend_ts) == '00:00:00') {
						$dtend_ts -= 60;
					}
					$vcardData['end']		= $dtend_ts;
					break;
				case 'RECURRENCE-ID':
					// ToDo or check:
					// - do we need to set reference (cal_id of orginal series)
					// - do we need to add that recurrence as recure exception to the original series
					// --> original series should be found by searching for a series with same UID (backend)
					// Joerg's answers: All this is handled within importVCal() for SyncML.
					$vcardData['recurrence'] = $attributes['value'];
					break;
				case 'LOCATION':
					$vcardData['location']	= $attributes['value'];
					break;
				case 'RRULE':
					$recurence = $attributes['value'];
					$type = preg_match('/FREQ=([^;: ]+)/i',$recurence,$matches) ? $matches[1] : $recurence[0];
					// vCard 2.0 values for all types
					if (preg_match('/UNTIL=([0-9T]+)/',$recurence,$matches))
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
					if (!isset($vcardData['start']))	// it might not yet be set, because the RRULE is before it
					{
						$vcardData['start'] = self::_get_attribute($component->_attributes,'DTSTART');
						$vcardData['end'] = self::_get_attribute($component->_attributes,'DTEND');
					}
					$vcardData['recur_data'] = 0;
					switch($type)
					{
						case 'W':
						case 'WEEKLY':
							$days = array();
							if(preg_match('/W(\d+) (.*) (.*)/',$recurence, $recurenceMatches))		// 1.0
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								$days = explode(' ',trim($recurenceMatches[2]));
								if($recurenceMatches[3] != '#0')
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[3]);
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
								foreach($recur_days as $id => $day) {
									if (in_array(strtoupper(substr($day,0,2)),$days)) {
										$vcardData['recur_data'] |= $id;
									}
								}
								$vcardData['recur_type'] = MCAL_RECUR_WEEKLY;
							}

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(0,0,0,
									date('m',$vcardData['start']),
									date('d',$vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)*7),
									date('Y',$vcardData['start']));
							}
							break;

						case 'D':	// 1.0
							if(preg_match('/D(\d+) #(.\d)/', $recurence, $recurenceMatches)) {
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if($recurenceMatches[2] > 0 && $vcardData['end']) {
									$vcardData['recur_enddate'] = mktime(
										date('H', $vcardData['end']),
										date('i', $vcardData['end']),
										date('s', $vcardData['end']),
										date('m', $vcardData['end']),
										date('d', $vcardData['end']) + ($recurenceMatches[2] * $vcardData['recur_interval']),
										date('Y', $vcardData['end'])
									);
								}
							} elseif(preg_match('/D(\d+) (.*)/', $recurence, $recurenceMatches)) {
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if($recurenceMatches[2] != '#0') {
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[2]);
								}
							} else {
								break;
							}
							// fall-through
						case 'DAILY':	// 2.0
							$vcardData['recur_type'] = MCAL_RECUR_DAILY;

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(0,0,0,
									date('m',$vcardData['start']),
									date('d',$vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)),
									date('Y',$vcardData['start']));
							}
							break;

						case 'M':
							if (preg_match('/MD(\d+) #(.\d)/', $recurence, $recurenceMatches)) {
								$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_MDAY;
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if($recurenceMatches[2] > 0 && $vcardData['end']) {
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
								if($recurenceMatches[1] > 1)
								{
									$vcardData['recur_interval'] = $recurenceMatches[1];
								}
								if($recurenceMatches[2] != '#0')
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
								$vcardData['recur_enddate'] = mktime(0,0,0,
									date('m',$vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)),
									date('d',$vcardData['start']),
									date('Y',$vcardData['start']));
							}
							break;

						case 'Y':		// 1.0
							if (preg_match('/YM(\d+) #(.\d)/', $recurence, $recurenceMatches))
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
								if($recurenceMatches[2] != '#0') {
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[2]);
								}
							} else break;

							// fall-through
						case 'YEARLY':	// 2.0
							$vcardData['recur_type'] = MCAL_RECUR_YEARLY;

							if (!empty($vcardData['recur_count']))
							{
								$vcardData['recur_enddate'] = mktime(0,0,0,
									date('m',$vcardData['start']),
									date('d',$vcardData['start']),
									date('Y',$vcardData['start']) + ($vcardData['recur_interval']*($vcardData['recur_count']-1)));
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
					$vcardData['priority'] = (int) $this->priority_ical2egw[$attributes['value']];
					break;
				case 'CATEGORIES':
					if ($attributes['value'])
					{
						if($version == '1.0')
						{
							$vcardData['category'] = $this->find_or_add_categories(explode(';',$attributes['value']));
						}
						else
						{
							$vcardData['category'] = $this->find_or_add_categories(explode(',',$attributes['value']));
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
						$status = 0;
					}
					$cn = '';
					if (preg_match('/MAILTO:([@.a-z0-9_-]+)|MAILTO:"?([.a-z0-9_ -]*)"?[ ]*<([@.a-z0-9_-]*)>/i',
						$attributes['value'],$matches)) {
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
					elseif (preg_match('/(.*) Group/', $searcharray['n_fn'], $matches)
							&& $status && $status != 'U')
					{
						if (($uid =  $GLOBALS['egw']->accounts->name2id($matches[1], 'account_lid', 'g')))
						{
							//Horde::logMessage("vevent2egw: group participant $uid",
       	    				//			__FILE__, __LINE__, PEAR_LOG_DEBUG);
							$members = $GLOBALS['egw']->accounts->members($uid, true);
							if (in_array($this->user, $members))
							{
								//Horde::logMessage("vevent2egw: set status to " . $status,
       	    					//		__FILE__, __LINE__, PEAR_LOG_DEBUG);
								$event['participants'][$this->user] = $status;
							}
						}
					}
					elseif ($attributes['value'] == 'Unknown')
					{
						$uid = $GLOBALS['egw_info']['user']['account_id'];
					}
					elseif ($email && ($uid = $GLOBALS['egw']->accounts->name2id($email,'account_email')))
					{
						// we use the account we found
					}
					elseif(!$searcharray)
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
							if ($status)
							{
								$event['participants'][$uid] = $status;
							}
							else
							{
								$event['participants'][$uid] = ($uid == $event['owner'] ? 'A' : 'U');
							}
							// add quantity and role
							$event['participants'][$uid] = calendar_so::combine_status($status,$attributes['params']['X-EGROUPWARE-QUANTITY'],$attributes['params']['ROLE']);
							break;
						case 'ORGANIZER':
							if (is_numeric($uid))
							{
								$event['owner'] = $uid;
							}
							else
							{
								$event['owner'] = $this->user;
							}
							// RalfBecker: this is not allways true, owner can choose to NOT participate in EGroupware
							$event['participants'][$uid] = 'A';
							break;
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
			elseif(strtolower($agendaEntryType) == 'event')
			{
				$event['special'] = '2';
				$event['non_blocking'] = 1;
			}
		}

		if (!empty($vcardData['recur_enddate']))
		{
			// reset recure_enddate to 00:00:00 on the last day
			$vcardData['recur_enddate'] = mktime(0, 0, 0,
				date('m',$vcardData['recur_enddate']),
				date('d',$vcardData['recur_enddate']),
				date('Y',$vcardData['recur_enddate'])
			);
		}

		$event['priority'] = 2; // default
		$event['alarm'] = $alarms;

		// now that we know what the vard provides,
		// we merge that data with the information we have about the device
		while (($fieldName = array_shift($supportedFields)))
		{
			switch($fieldName)
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
						foreach(array('recur_interval','recur_enddate','recur_data','recur_exception') as $r)
						{
							if(isset($vcardData[$r]))
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
		//Horde::logMessage("vevent2egw:\n" . print_r($event, true),
        //    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $event;
	}

	function search($_vcalData, $contentID=null, $relax=false)
	{
		if($events = $this->icaltoegw($_vcalData,!is_null($contentID) ? $contentID : -1))
		{
			// this function only supports searching a single event
			if(count($events) == 1)
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
	 * @param boolean $servertime=false if true, use severtime for dates
	 * @return string
	 */
	function freebusy($user,$end=null,$servertime=false)
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
		if ($servertime)
		{
			foreach(array(
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
		else
		{
			foreach(array(
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

				if ($servertime)
				{
					$vfreebusy->setAttribute('FREEBUSY',array(array(
						'start' => date('Ymd\THis',$event['start']),
						'end' => date('Ymd\THis',$event['end']),
					)));
				}
				else
				{
					$vfreebusy->setAttribute('FREEBUSY',array(array(
						'start' => $event['start'],
						'end' => $event['end'],
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
            if(!isset($new_event['participants'][$userid])){
            	// Attendee will be deleted this way
            	$new_event['participants'][$userid] = 'G';
            } elseif ($new_event['participants'][$userid] == $status){
            	// Same status -- nothing to do.
            	unset($new_event['participants'][$userid]);
            }
		}
      // write the changes
      foreach ($new_event['participants'] as $userid => $status)
      {
			$this->set_status($old_event, $userid, $status, $recur_date, true);
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

			if($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$type = 'SERIES-MASTER';
			}
			else
			{
				// SINGLE, SERIES-EXCEPTION OR SERIES-EXCEPTON-STATUS
				if(empty($event['uid']) && $event['id'] > 0 && ($stored_event = $this->read($event['id'])))
				{
					$event['uid'] = $stored_event['uid']; // restore the UID if it was not delivered
				}

				if(isset($event['uid'])
					&& $event['recurrence']
					&& ($master_event = $this->read($event['uid']))
					&& isset($master_event['recur_type'])
					&& $master_event['recur_type'] != MCAL_RECUR_NONE
				)
				{
					// SERIES-EXCEPTION OR SERIES-EXCEPTON-STATUS
					$return_master = true; // we have a valid master and can return it

					if(isset($event['id']) && $master_event['id'] != $event['id'])
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
						foreach (array('start','end','uid','owner','title','description',
							'location','priority','public','special','non_blocking') as $key)
						{
							if (!empty($event[$key]) && $recurrence_event[$key] != $event[$key])
							{
								if(isset($event['id']))
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
			if(isset($event['id']))
			{
				$stored_event = $this->read($event['id']);
			}

			// check ACL
			if($return_master)
			{
				$acl_edit = $this->check_perms(EGW_ACL_EDIT, $master_event['id']);
			}
			else
			{
				if(is_array($stored_event))
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

    /**
	 * generate and insert a VTIMEZONE entry for an event
	 *
	 * @param int $ts timestamp to evaluate the local timezone and year
	 * @param array $vevent VEVENT representation of the event
	 * @return string local timezone name (e.g. 'CET/CEST')
	 */
	function generate_vtimezone($ts, &$vevent)
	{
		$utc = array('UTC',null,0,0,"",0,0,0,0,0,0,0,0,0,0);
		$dayofweek = array('SU','MO','TU','WE','TH','FR','SA');
		$tbl_tz = array(
			array("Afghanistan","Asia/Kabul",270,0,"",0,0,0,0,0,0,0,0,0,0),
			array("AKST/AKDT","America/Anchorage",-540,60,"",3,0,2,2,0,11,0,1,2,0),
			array("AKST/AKDT","America/Anchorage",-540,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("AKST/AKDT","America/Anchorage",-540,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("HNY/NAY","America/Anchorage",-540,60,"",3,0,2,2,0,11,0,1,2,0),
			array("HNY/NAY","America/Anchorage",-540,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("HNY/NAY","America/Anchorage",-540,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Alaskan","America/Anchorage",-540,60,"",3,0,2,2,0,11,0,1,2,0),
			array("Alaskan","America/Anchorage",-540,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("Alaskan","America/Anchorage",-540,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Arab","Asia/Riyadh",180,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Arabian","Asia/Dubai",240,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Arabic","Asia/Baghdad",180,60,"",4,0,1,3,0,10,0,1,4,0),
			array("AST/ADT","America/Halifax",-240,60,"",3,0,2,2,0,11,0,1,2,0),
			array("AST/ADT","America/Halifax",-240,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("AST/ADT","America/Halifax",-240,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("HNA/HAA","America/Halifax",-240,60,"",3,0,2,2,0,11,0,1,2,0),
			array("HNA/HAA","America/Halifax",-240,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("HNA/HAA","America/Halifax",-240,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Atlantic","America/Halifax",-240,60,"",3,0,2,2,0,11,0,1,2,0),
			array("Atlantic","America/Halifax",-240,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("Atlantic","America/Halifax",-240,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("AUS_Central","Australia/Darwin",570,0,"",0,0,0,0,0,0,0,0,0,0),
			array("AUS_Eastern","Australia/Sydney",600,60,"",10,0,5,2,0,3,0,5,3,0),
			array("Azerbaijan","Asia/Baku",240,60,"",3,0,5,4,0,10,0,5,5,0),
			array("Azores","Atlantic/Azores",-60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Canada_Central","America/Regina",-360,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Cape_Verde","Atlantic/Cape_Verde",-60,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Caucasus","Asia/Tbilisi",240,60,"",3,0,5,2,0,10,0,5,3,0),
			array("ACST/ACDT","Australia/Adelaide",570,60,"",10,0,5,2,0,3,0,5,3,0),
			array("Central_Australia","Australia/Adelaide",570,60,"",10,0,5,2,0,3,0,5,3,0),
			array("Central_America","America/Guatemala",-360,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Central_Asia","Asia/Dhaka",360,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Central_Brazilian","America/Manaus",-240,60,"",11,0,1,0,0,2,0,5,0,0),
			array("Central_Brazilian","America/Manaus",-240,60,"2006",11,0,1,0,0,2,0,2,2,0),
			array("Central_Brazilian","America/Manaus",-240,60,"2007",11,0,1,0,0,2,0,5,0,0),
			array("CET/CEST","Europe/Zurich",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("MEZ/MESZ","Europe/Berlin",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Central_Europe","Europe/Budapest",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Central_European","Europe/Warsaw",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Central_Pacific","Pacific/Guadalcanal",660,0,"",0,0,0,0,0,0,0,0,0,0),
			array("CST/CDT","America/Chicago",-360,60,"",3,0,2,2,0,11,0,1,2,0),
			array("CST/CDT","America/Chicago",-360,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("CST/CDT","America/Chicago",-360,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("HNC/HAC","America/Chicago",-360,60,"",3,0,2,2,0,11,0,1,2,0),
			array("HNC/HAC","America/Chicago",-360,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("HNC/HAC","America/Chicago",-360,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Central","America/Chicago",-360,60,"",3,0,2,2,0,11,0,1,2,0),
			array("Central","America/Chicago",-360,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("Central","America/Chicago",-360,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Central_Mexico","America/Mexico_City",-360,60,"",4,0,1,2,0,10,0,5,2,0),
			array("China","Asia/Shanghai",480,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Dateline","Etc/GMT+12",-720,0,"",0,0,0,0,0,0,0,0,0,0),
			array("East_Africa","Africa/Nairobi",180,0,"",0,0,0,0,0,0,0,0,0,0),
			array("AEST/AEDT","Australia/Brisbane",600,0,"",0,0,0,0,0,0,0,0,0,0),
			array("East_Australia","Australia/Brisbane",600,0,"",0,0,0,0,0,0,0,0,0,0),
			array("EET/EEST","Europe/Minsk",120,60,"",3,0,5,2,0,10,0,5,3,0),
			array("East_Europe","Europe/Minsk",120,60,"",3,0,5,2,0,10,0,5,3,0),
			array("East_South_America","America/Sao_Paulo",-180,60,"",11,0,1,0,0,2,0,5,0,0),
			array("East_South_America","America/Sao_Paulo",-180,60,"2006",11,0,1,0,0,2,0,2,2,0),
			array("East_South_America","America/Sao_Paulo",-180,60,"2007",11,0,1,0,0,2,0,5,0,0),
			array("EST/EDT","America/New_York",-300,60,"",3,0,2,2,0,11,0,1,2,0),
			array("EST/EDT","America/New_York",-300,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("EST/EDT","America/New_York",-300,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("HNE/HAE","America/New_York",-300,60,"",3,0,2,2,0,11,0,1,2,0),
			array("HNE/HAE","America/New_York",-300,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("HNE/HAE","America/New_York",-300,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Eastern","America/New_York",-300,60,"",3,0,2,2,0,11,0,1,2,0),
			array("Eastern","America/New_York",-300,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("Eastern","America/New_York",-300,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Egypt","Africa/Cairo",120,60,"",4,4,5,23,59,9,4,5,23,59),
			array("Ekaterinburg","Asia/Yekaterinburg",300,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Fiji","Pacific/Fiji",720,0,"",0,0,0,0,0,0,0,0,0,0),
			array("FLE","Europe/Kiev",120,60,"",3,0,5,3,0,10,0,5,4,0),
			array("Georgian","Etc/GMT-3",180,0,"",0,0,0,0,0,0,0,0,0,0),
			array("GMT","Europe/London",0,60,"",3,0,5,1,0,10,0,5,2,0),
			array("Greenland","America/Godthab",-180,60,"",4,0,1,2,0,10,0,5,2,0),
			array("Greenwich","Africa/Casablanca",0,0,"",0,0,0,0,0,0,0,0,0,0),
			array("GTB","Europe/Istanbul",120,60,"",3,0,5,3,0,10,0,5,4,0),
			array("HAST/HADT","Pacific/Honolulu",-600,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Hawaiian","Pacific/Honolulu",-600,0,"",0,0,0,0,0,0,0,0,0,0),
			array("India","Asia/Calcutta",330,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Iran","Asia/Tehran",210,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Iran","Asia/Tehran",210,60,"2005",3,0,1,2,0,9,2,4,2,0),
			array("Iran","Asia/Tehran",210,0,"2006",0,0,0,0,0,0,0,0,0,0),
			array("Israel","Asia/Jerusalem",120,60,"",3,5,5,2,0,9,0,3,2,0),
			array("Israel","Asia/Jerusalem",120,0,"2004",0,0,0,0,0,0,0,0,0,0),
			array("Israel","Asia/Jerusalem",120,60,"2005",4,-1,1,2,0,10,-1,9,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2006",3,-1,31,2,0,10,-1,1,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2007",3,-1,30,2,0,9,-1,16,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2008",3,-1,28,2,0,10,-1,5,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2009",3,-1,27,2,0,9,-1,27,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2010",3,-1,26,2,0,9,-1,12,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2011",4,-1,1,2,0,10,-1,2,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2012",3,-1,30,2,0,9,-1,23,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2013",3,-1,29,2,0,9,-1,8,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2014",3,-1,28,2,0,9,-1,28,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2015",3,-1,27,2,0,9,-1,20,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2016",4,-1,1,2,0,10,-1,9,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2017",3,-1,31,2,0,9,-1,24,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2018",3,-1,30,2,0,9,-1,16,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2019",3,-1,29,2,0,10,-1,6,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2020",3,-1,27,2,0,9,-1,27,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2021",3,-1,26,2,0,9,-1,12,2,0),
			array("Israel","Asia/Jerusalem",120,60,"2022",4,-1,1,2,0,10,-1,2,2,0),
			array("Israel","Asia/Jerusalem",120,0,"2023",0,0,0,0,0,0,0,0,0,0),
			array("Jordan","Asia/Amman",120,60,"",3,4,5,0,0,9,5,5,1,0),
			array("Korea","Asia/Seoul",540,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Mexico","America/Mexico_City",-360,60,"",4,0,1,2,0,10,0,5,2,0),
			array("Mexico_2","America/Chihuahua",-420,60,"",4,0,1,2,0,10,0,5,2,0),
			array("Mid_Atlantic","Atlantic/South_Georgia",-120,60,"",3,0,5,2,0,9,0,5,2,0),
			array("Middle_East","Asia/Beirut",120,60,"",3,0,5,0,0,10,6,5,23,59),
			array("Montevideo","America/Montevideo",-180,60,"",10,0,1,2,0,3,0,2,2,0),
			array("MST/MDT","America/Denver",-420,60,"",3,0,2,2,0,11,0,1,2,0),
			array("MST/MDT","America/Denver",-420,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("MST/MDT","America/Denver",-420,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("HNR/HAR","America/Denver",-420,60,"",3,0,2,2,0,11,0,1,2,0),
			array("HNR/HAR","America/Denver",-420,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("HNR/HAR","America/Denver",-420,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Mountain","America/Denver",-420,60,"",3,0,2,2,0,11,0,1,2,0),
			array("Mountain","America/Denver",-420,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("Mountain","America/Denver",-420,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Mountain_Mexico","America/Chihuahua",-420,60,"",4,0,1,2,0,10,0,5,2,0),
			array("Myanmar","Asia/Rangoon",390,0,"",0,0,0,0,0,0,0,0,0,0),
			array("North_Central_Asia","Asia/Novosibirsk",360,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Namibia","Africa/Windhoek",120,-60,"",4,0,1,2,0,9,0,1,2,0),
			array("Nepal","Asia/Katmandu",345,0,"",0,0,0,0,0,0,0,0,0,0),
			array("New_Zealand","Pacific/Auckland",720,60,"",10,0,1,2,0,3,0,3,3,0),
			array("NST/NDT","America/St_Johns",-210,60,"",3,0,2,0,1,11,0,1,0,1),
			array("NST/NDT","America/St_Johns",-210,60,"2006",4,0,1,0,1,10,0,5,0,0),
			array("NST/NDT","America/St_Johns",-210,60,"2007",3,0,2,0,1,11,0,1,0,0),
			array("HNT/HAT","America/St_Johns",-210,60,"",3,0,2,0,1,11,0,1,0,1),
			array("HNT/HAT","America/St_Johns",-210,60,"2006",4,0,1,0,1,10,0,5,0,0),
			array("HNT/HAT","America/St_Johns",-210,60,"2007",3,0,2,0,1,11,0,1,0,0),
			array("Newfoundland","America/St_Johns",-210,60,"",3,0,2,0,1,11,0,1,0,1),
			array("Newfoundland","America/St_Johns",-210,60,"2006",4,0,1,0,1,10,0,5,0,0),
			array("Newfoundland","America/St_Johns",-210,60,"2007",3,0,2,0,1,11,0,1,0,0),
			array("North_Asia_East","Asia/Irkutsk",480,60,"",3,0,5,2,0,10,0,5,3,0),
			array("North_Asia","Asia/Krasnoyarsk",420,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Pacific_SA","America/Santiago",-240,60,"",10,6,2,23,59,3,6,2,23,59),
			array("PST/PDT","America/Los_Angeles",-480,60,"",3,0,2,2,0,11,0,1,2,0),
			array("PST/PDT","America/Los_Angeles",-480,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("PST/PDT","America/Los_Angeles",-480,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("HNP/HAP","America/Los_Angeles",-480,60,"",3,0,2,2,0,11,0,1,2,0),
			array("HNP/HAP","America/Los_Angeles",-480,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("HNP/HAP","America/Los_Angeles",-480,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Pacific","America/Los_Angeles",-480,60,"",3,0,2,2,0,11,0,1,2,0),
			array("Pacific","America/Los_Angeles",-480,60,"2006",4,0,1,2,0,10,0,5,2,0),
			array("Pacific","America/Los_Angeles",-480,60,"2007",3,0,2,2,0,11,0,1,2,0),
			array("Pacific_Mexico","America/Tijuana",-480,60,"",4,0,1,2,0,10,0,5,2,0),
			array("Romance","Europe/Paris",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("Russian","Europe/Moscow",180,60,"",3,0,5,2,0,10,0,5,3,0),
			array("SA_Eastern","Etc/GMT+3",-180,0,"",0,0,0,0,0,0,0,0,0,0),
			array("SA_Pacific","America/Bogota",-300,0,"",0,0,0,0,0,0,0,0,0,0),
			array("SA_Western","America/La_Paz",-240,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Samoa","Pacific/Apia",-660,0,"",0,0,0,0,0,0,0,0,0,0),
			array("SE_Asia","Asia/Bangkok",420,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Singapore","Asia/Singapore",480,0,"",0,0,0,0,0,0,0,0,0,0),
			array("South_Africa","Africa/Johannesburg",120,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Sri_Lanka","Asia/Colombo",330,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Taipei","Asia/Taipei",480,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Tasmania","Australia/Hobart",600,60,"",10,0,1,2,0,3,0,5,3,0),
			array("Tokyo","Asia/Tokyo",540,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Tonga","Pacific/Tongatapu",780,0,"",0,0,0,0,0,0,0,0,0,0),
			array("US_Eastern","Etc/GMT+5",-300,0,"",0,0,0,0,0,0,0,0,0,0),
			array("US_Mountain","America/Phoenix",-420,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Vladivostok","Asia/Vladivostok",600,60,"",3,0,5,2,0,10,0,5,3,0),
			array("West_Australia","Australia/Perth",480,60,"",10,0,5,2,0,3,0,5,3,0),
			array("West_Australia","Australia/Perth",480,0,"2005",0,0,0,0,0,0,0,0,0,0),
			array("West_Australia","Australia/Perth",480,60,"2006",12,-1,1,2,0,1,-1,1,0,0),
			array("West_Australia","Australia/Perth",480,60,"2007",10,0,5,2,0,3,0,5,3,0),
			array("West_Central_Africa","Africa/Lagos",60,0,"",0,0,0,0,0,0,0,0,0,0),
			array("WET/WEST","Europe/Berlin",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("West_Europe","Europe/Berlin",60,60,"",3,0,5,2,0,10,0,5,3,0),
			array("West_Asia","Asia/Karachi",300,0,"",0,0,0,0,0,0,0,0,0,0),
			array("West_Pacific","Pacific/Port_Moresby",600,0,"",0,0,0,0,0,0,0,0,0,0),
			array("Yakutsk","Asia/Yakutsk",540,60,"",3,0,5,2,0,10,0,5,3,0),
		);

		$serverTZ = date('e', $ts);
		$year = date('Y', $ts);
		$i = 0;
		$row =& $utc;
		do
		{
			$tbl_tz_row =& $tbl_tz[$i];
			if ($row[0] != 'UTC' && $row[0] != $tbl_tz_row[0]) break;
			if ($tbl_tz_row[1] == $serverTZ
				&& ($tbl_tz_row[4] != "" || $year >= (int) $tbl_tz_row[4]))
			{
				$row =& $tbl_tz[$i];
			}
			$i++;
		}
		while (is_array($tbl_tz[$i]));
		if (preg_match('/(.+)\/(.+)/', $row[0], $matches))
		{
			$stdname = $matches[1];
			$dstname = $matches[2];
		}
		else
		{
			$stdname = $dstname = $row[0];
		}
		$container = false;
		$vtimezone = Horde_iCalendar::newComponent('VTIMEZONE', $container);
		$vtimezone->setAttribute('TZID', $row[1]);
		$minutes = $row[2] + $row[3];
		$value1['ahead'] = ($minutes > 0);
		$minutes = abs($minutes);
		$value1['hour'] = (int)$minutes / 60;
		$value1['minute'] = $minutes % 60;
		$minutes = $row[2];
		$value2['ahead'] = ($minutes > 0);
		$minutes = abs($minutes);
		$value2['hour'] = (int)$minutes / 60;
		$value2['minute'] = $minutes % 60;

		$daylight = Horde_iCalendar::newComponent('DAYLIGHT', $container);
		$dtstart = $this->calc_dtstart($row[4], $row[5], $row[6], $row[7], $row[8], $row[9]);
		$daylight->setAttribute('DTSTART', $dtstart);
		$byday = ($row[7] == 5 ? '-1' : $row[7]) . $dayofweek[$row[6]];
		$daylight->setAttribute('RRULE', '', array('FREQ' => 'YEARLY', 'BYMONTH' => $row[5], 'BYDAY' => $byday));
		$daylight->setAttribute('TZNAME', $dstname);
		$daylight->setAttribute('TZOFFSETFROM', $value2);
		$daylight->setAttribute('TZOFFSETTO', $value1);

		$standard = Horde_iCalendar::newComponent('STANDARD', $container);
		$dtstart = $this->calc_dtstart($year, $row[10], $row[11], $row[12], $row[13], $row[14]);
		$standard->setAttribute('DTSTART', $dtstart);
		$byday = ($row[12] == 5 ? '-1' : $row[12]) . $dayofweek[$row[11]];
		$standard->setAttribute('RRULE', '', array('FREQ' => 'YEARLY', 'BYMONTH' => $row[10], 'BYDAY' => $byday));
		$standard->setAttribute('TZNAME', $stdname);
		$standard->setAttribute('TZOFFSETFROM', $value1);
		$standard->setAttribute('TZOFFSETTO', $value2);

		$vtimezone->addComponent($daylight);
		$vtimezone->addComponent($standard);
		$vevent->addComponent($vtimezone);

		return $row[1];
	}

	/**
	 * calculate the DTSTART value for a given timezone switch occurrence
	 *
	 * @param int $wYear
	 * @param int $wMonth
	 * @param int $wDayOfWeek
	 * @param int $wNth        n-th day of week (5 last occurrence)
	 * @param int $wHour
	 * @param int $wMinute
	 * @return string DTSTART entry
	 */
	function calc_dtstart($wYear, $wMonth, $wDayOfWeek, $wNth, $wHour, $wMinute)
	{
		if (!$wYear) $wYear = 1981;
		if ($wNth < 5)
		{
			$ts =  mktime($wHour, $wMinute, 0, $wMonth, 1, $wYear);
			$day = $wDayOfWeek - date('w', $ts);
			if ($day < 0) $day += 7;
			$day += 7 * ($wNth - 1) + 1;
			$ts =  mktime($wHour, $wMinute, 0, $wMonth, $day, $wYear);
		}
		else
		{
			$ts =  mktime($wHour, $wMinute, 0, $wMonth, 31, $wYear);
			$day = $wDayOfWeek - date('w', $ts);
			if ($day > 0) $day -= 7;
			$day += 31;
			do
			{
				$ts =  mktime($wHour, $wMinute, 0, $wMonth, $day, $wYear);
				$day -= 7;
			} while ($wMonth < date('n', $ts));
		}
		$dtstart = date('Ymd\T', $ts) . sprintf("%'02u%'02u00", $wHour, $wMinute);
		return $dtstart;
	}
}
