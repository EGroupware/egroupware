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
	 * Constructor
	 *
	 * @param array $_clientProperties		client properties
	 */
	function __construct(&$_clientProperties = array()) {
		parent::__construct();

		$this->clientProperties = $_clientProperties;
		$this->vCalendar = new Horde_iCalendar;
	}


	/**
	 * Exports one calendar event to an iCalendar item
	 *
	 * @param int/array $events (array of) cal_id or array of the events
	 * @param string $version='1.0' could be '2.0' too
	 * @param string $method='PUBLISH'
	 * @param int $recur_date=0	if set export the next recurrance at or after the timestamp,
	 *                          	default 0 => export whole series (or events, if not recurring)
	 * @return string/boolean string with iCal or false on error (eg. no permission to read the event)
	 */
	function &exportVCal($events, $version='1.0', $method='PUBLISH', $recur_date=0)
	{
		// error_log(__FILE__ . __METHOD__ ."exportVCal is called ");
		$egwSupportedFields = array(
			'CLASS'			=> array('dbName' => 'public'),
			'SUMMARY'		=> array('dbName' => 'title'),
			'DESCRIPTION'	=> array('dbName' => 'description'),
			'LOCATION'		=> array('dbName' => 'location'),
			'DTSTART'		=> array('dbName' => 'start'),
			'DTEND'			=> array('dbName' => 'end'),
			'ORGANIZER'		=> array('dbName' => 'owner'),
			'ATTENDEE'		=> array('dbName' => 'participants'),
			'RRULE'			=> array('dbName' => 'recur_type'),
			'EXDATE'		=> array('dbName' => 'recur_exception'),
			'PRIORITY'		=> array('dbName' => 'priority'),
			'TRANSP'		=> array('dbName' => 'non_blocking'),
			'CATEGORIES'	=> array('dbName' => 'category'),
			'UID'			=> array('dbName' => 'uid'),
			'RECURRENCE-ID' => array('dbName' => 'reference'),
		);

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		if ($this->productManufacturer == '' )
		{	// syncevolution is broken
			$version = '2.0';
		}

		$servertime = false;
		$date_format = 'server';
		if (strpos($this->productName, "palmos") )
		{
			$servertime = true;
			$date_format = 'ts';
		}

		$vcal = new Horde_iCalendar;
		$vcal->setAttribute('PRODID','-//eGroupWare//NONSGML eGroupWare Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
		$vcal->setAttribute('VERSION', $version);
		$vcal->setAttribute('METHOD', $method);

		if (!is_array($events)) $events = array($events);

		while ($event = array_pop($events))
		{
			if (!is_array($event)
				&& !($event = $this->read($event, $recur_date, false, $date_format)))
			{
				// server = timestamp in server-time(!)
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
			//_debug_array($event);

			// correct daylight saving time
			/* causes times wrong by one hour, if exporting events with DST different from the current date,
			which this fix is suppost to fix. Maybe the problem has been fixed in the horde code too.
			$currentDST = date('I', mktime());
			$eventDST = date('I', $event['start']);
			$DSTCorrection = ($currentDST - $eventDST) * 3600;
			$event['start']	= $event['start'] + $DSTCorrection;
			$event['end']	= $event['end'] + $DSTCorrection;
			*/

			if ($this->productManufacturer != 'file'
				&& $this->uidExtension) {
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

			foreach($egwSupportedFields as $icalFieldName => $egwFieldInfo)
			{
				if ($this->supportedFields[$egwFieldInfo['dbName']])
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
								// ROLE={CHAIR|REQ-PARTICIPANT|OPT-PARTICIPANT|NON-PARTICIPANT} NOT used by eGW atm.
								$role = $uid == $event['owner'] ? 'CHAIR' : 'REQ-PARTICIPANT';
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
								)+($info['type'] != 'e' ? array('X-EGROUPWARE-UID' => $uid) : array());
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
									if ($servertime)
									{
										$rrule['UNTIL'] = date('Ymd\THis', $recur_enddate);
									}
									else
									{
										$rrule['UNTIL'] = $vcal->_exportDateTime($recur_enddate);
									}
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
										if ($servertime)
										{
											$rrule['UNTIL'] = date('Ymd\THis', $recur_enddate);
										}
										else
										{
											$rrule['UNTIL'] = $vcal->_exportDateTime($recur_enddate);
										}
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
							if (is_array($event['recur_exception']))
							{
								$days = $days + $event['recur_exception'];
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
									}
									else
									{
										$attributes['RECURRENCE-ID'] = $recur_date;
									}
								}
							}
							elseif ($event['reference'])
							{
								if ($this->isWholeDay($event))
								{
									$arr = $this->date2array($event['reference']);
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
										$attributes['RECURRENCE-ID'] = date('Ymd\THis', $event['reference']);
									}
									else
									{
										$attributes['RECURRENCE-ID'] = $event['reference'];
									}
								}
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
							$value = $event[$egwFieldInfo['dbName']];
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
				if ($servertime)
				{
					$attributes['CREATED'] = date('Ymd\THis', $created);
				}
				else
				{
					$attributes['CREATED'] = $created;
				}
			}
			if (!$modified) $modified = $event['modified'];
			if ($modified)
			{
				if ($servertime)
				{
					$attributes['LAST-MODIFIED'] = date('Ymd\THis', $modified);
				}
				else
				{
					$attributes['LAST-MODIFIED'] = $modified;
				}
			}
			if ($servertime)
			{
				$attributes['DTSTAMP'] = date('Ymd\THis', time());
			}
			else
			{
				$attributes['DTSTAMP'] = time();
			}
			foreach($event['alarm'] as $alarmID => $alarmData) {
				if ($version == '1.0') {
					if ($servertime)
					{
						$attributes['DALARM'] = date('Ymd\THis', $alarmData['time']);
						$attributes['AALARM'] = date('Ymd\THis', $alarmData['time']);
					}
					else
					{
						$attributes['DALARM'] = $alarmData['time'];
						$attributes['AALARM'] = $alarmData['time'];
					}
					// lets take only the first alarm
					break;
				} else {
					// VCalendar 2.0 / RFC 2445

					$description = trim(preg_replace("/\r?\n?\\[[A-Z_]+:.*\\]/i", '', $event['description']));

					// skip over alarms that don't have the minimum required info
					if (!$alarmData['offset'] && !$alarmData['time']) {
						error_log("Couldn't add VALARM (no alarm time info)");
						continue;
					}

					// RFC requires DESCRIPTION for DISPLAY
					if (!$event['title'] && !$description) {
						error_log("Couldn't add VALARM (no description)");
						continue;
					}

					$valarm = Horde_iCalendar::newComponent('VALARM',$vevent);
					if ($alarmData['offset']) {
						$valarm->setAttribute('TRIGGER', -$alarmData['offset'],
								array('VALUE' => 'DURATION', 'RELATED' => 'START'));
					} else {
						if ($servertime)
						{
							$value = date('Ymd\THis', $alarmData['time']);
						}
						else
						{
							$value = $alarmData['time'];
						}
						$valarm->setAttribute('TRIGGER', $value, array('VALUE' => 'DATE-TIME'));
					}

					$valarm->setAttribute('ACTION','DISPLAY');
					$valarm->setAttribute('DESCRIPTION',$event['title'] ? $event['title'] : $description);
					$vevent->addComponent($valarm);
				}
			}

			foreach($attributes as $key => $value) {
				foreach(is_array($value)&&$parameters[$key]['VALUE']!='DATE' ? $value : array($value) as $valueID => $valueData) {
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
						error_log(__FILE__ . __METHOD__ ."Has invalid XML data :$valueData");
					}
					$vevent->setParameter($key, $options);
				}
			}
			$vcal->addComponent($vevent);
		}
		//_debug_array($vcal->exportvCalendar());

		$retval = $vcal->exportvCalendar();
                Horde::logMessage("exportVCAL:\n" . print_r($retval, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $retval;

	}

	/**
	 * Import an iCal
	 *
	 * @param string $_vcalData
	 * @param int $cal_id=-1 must be -1 for new entries!
	 * @param string $etag=null if an etag is given, it has to match the current etag or the import will fail
	 * @param boolean $merge=false	merge data with existing entry
	 * @param int $recur_date=0 if set, import the recurrance at this timestamp,
	 *                          default 0 => import whole series (or events, if not recurring)
	 * @return int|boolean cal_id > 0 on success, false on failure or 0 for a failed etag
	 */
	function importVCal($_vcalData, $cal_id=-1, $etag=null, $merge=false, $recur_date=0)
	{
		$Ok = false;	// returning false, if file contains no components

		$vcal = new Horde_iCalendar;
		if (!$vcal->parsevCalendar($_vcalData))
		{
			return $Ok;
		}

		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'])) {
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$version = $vcal->getAttribute('VERSION');

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		foreach ($vcal->getComponents() as $component)
		{
			if (is_a($component, 'Horde_iCalendar_vevent'))
			{

				$event = $this->vevent2egw($component, $version, $this->supportedFields);

				if ($cal_id > 0) {
					$event['id'] = $cal_id;
				}

				if ($this->productManufacturer == '' && $this->productName == ''
					&& !empty($event['recur_enddate']))
				{
					// syncevolution needs an adjusted recur_enddate
					$event['recur_enddate'] = (int)$event['recur_enddate'] + 86400;
				}

 				if ($cal_id < 0 && (!isset($this->supportedFields['participants']) ||
 					!isset($event['participants'][$GLOBALS['egw_info']['user']['account_id']])))
 				{
 					// add ourself to new events as participant
					$event['participants'][$GLOBALS['egw_info']['user']['account_id']] = 'A';
 				}

 				if ($event['recur_type'] != MCAL_RECUR_NONE)
 				{
 					// No RECCURENCE-ID for series events
 					$event['reference'] = 0;
 				}

				if ($cal_id > 0 && ($egw_event = $this->read($cal_id, $recur_date)))
				{
					// overwrite with server data for merge
					if ($merge)
					{
						if ($egw_event['recur_type'] != MCAL_RECUR_NONE && $recur_date)
						{
							// update only the stati of the exception
							if ($this->check_perms(EGW_ACL_EDIT, $cal_id))
							{
								$this->update_status($event, $egw_event, $recur_date);
							}
							$Ok = $cal_id . ':' . $recur_date;
							continue; // nothing more to do
						}
						foreach ($egw_event as $key => $value)
						{
							switch ($key)
							{
								case 'participants_types':
									continue;

								case 'participants':
									foreach ($egw_event['participants'] as $uid => $status)
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
					else // not merge
					{
						if (!isset($this->supportedFields['participants']) || !count($event['participants']))
						{
							// If this is an updated meeting, and the client doesn't support
							// participants OR the event no longer contains participants, add them back
							$event['participants'] = $egw_event['participants'];
							$event['participant_types'] = $egw_event['participant_types'];
						}

						foreach ($egw_event['participants'] as $uid => $status)
						{
							// Is it a resource and no longer present in the event?
							if ( $uid[0] == 'r' && !isset($event['participants'][$uid]) )
							{
								// Add it back in
								$event['participants'][$uid] = $event['participant_types']['r'][substr($uid,1)] = $status;
							}
						}
						// avoid that iCal changes the organizer, which is not allowed
						$event['owner'] = $egw_event['owner'];
					}
				}
				else
				{
					// new event
					$cal_id = -1;
					$recur_date = $event['reference'];
				}

				if (empty($event['uid']) && $cal_id > 0 &&
					($egw_event = $this->read($cal_id)))
				{
					$event['uid'] = $egw_event['uid'];
				}

				if ($event['recur_type'] == MCAL_RECUR_NONE
						&& !empty($event['uid']) && $recur_date)
				{
					// We handle a recurrence exception
					$recur_exceptions = $this->so->get_related($event['uid']);
					$recur_id = array_search($recur_date, $recur_exceptions);
					if ($recur_id === false || !($egw_event = $this->read($recur_id)))
					{
						// We found no real exception, let't try "status only"
						if (($egw_event = $this->read($event['uid']))
							&& $egw_event['recur_type'] != MCAL_RECUR_NONE)
						{
							$unchanged = true;
							foreach (array('uid','owner','title','description',
								'location','priority','public','special','non_blocking') as $key)
							{
								//Horde::logMessage('importVCAL test ' .$key . ': '. $egw_event[$key] . ' == ' .$event[$key],
								//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
								if (!empty($event[$key]) //|| !empty($egw_event[$key]))
									&& $egw_event[$key] != $event[$key])
								{
									$unchanged = false;
									break;
								}
							}
							if ($unchanged)
							{
								Horde::logMessage('importVCAL event unchanged',
									__FILE__, __LINE__, PEAR_LOG_DEBUG);

								// We can handle this without an exception entry
								$recur_exceptions = array();
								foreach ($egw_event['recur_exception'] as $recur_exception)
								{
									//Horde::logMessage('importVCAL exception ' .$recur_exception,
									//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
									if ($recur_exception != $recur_date)
									{
										$recur_exceptions[] = $recur_exception;
									}
								}
								//Horde::logMessage("importVCAL exceptions\n" . print_r($recur_exceptions, true),
								//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
								$egw_event['recur_exception'] = $recur_exceptions;
								$this->update($egw_event, true);

								// update the stati from the exception
								if ($this->check_perms(EGW_ACL_EDIT, $egw_event['id']))
								{
									$this->update_status($event, $egw_event, $recur_date);
								}
								elseif (isset($egw_event['participants'][$this->user]))
								{
									// check if current user is an attendee and tried to change his status
									$this->set_status($egw_event, $this->user,
										($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), $recur_date, true);
								}
								$Ok = $egw_event['id'] . ':' . $recur_date;
								continue; // nothing more to do
							}
							else
							{
								// We need to create an new exception
								$egw_event['recur_exception'] = array_unique(array_merge($egw_event['recur_exception'], array($recur_date)));
								$this->update($egw_event, true);
								$event['category'] = $egw_event['category'];
								$cal_id = -1;
							}
						}
						else
						{
							// The series event was not found
							$cal_id = -1;
						}
					}
					else
					{
						// We update an existing exception
						$cal_id = $egw_event['id'];
						$event['id'] = $egw_event['id'];
						$event['category'] = $egw_event['category'];
					}
				}
				else
				{
					// We handle a single event or the series master
					$days = $this->so->get_recurrence_exceptions($event);
					if (is_array($days))
					{
						Horde::logMessage("importVCAL event\n" . print_r($event, true),
							__FILE__, __LINE__, PEAR_LOG_DEBUG);
						Horde::logMessage("importVCAL days\n" . print_r($days, true),
							__FILE__, __LINE__, PEAR_LOG_DEBUG);
						// remove all known "stati only" exceptions
						$recur_exceptions = array();
						foreach ($event['recur_exception'] as $recur_exception)
						{
							if (!in_array($recur_exception, $days))
							{
								$recur_exceptions[] = $recur_exception;
							}
						}
						Horde::logMessage("importVCAL exceptions\n" . print_r($recur_exceptions, true),
							__FILE__, __LINE__, PEAR_LOG_DEBUG);
						$event['recur_exception'] = $recur_exceptions;
					}
				}

				if ($cal_id <= 0)
				{
					// new entry
					if ($this->isWholeDay($event) && $this->nonBlockingAllday)
					{
						$event['non_blocking'] = 1;
					}

					if (!isset($event['owner'])
							|| !$this->check_perms(EGW_ACL_ADD,0,$event['owner']))
					{
						// check for new events if an owner is set and the current user has add rights
						// for that owners calendar; if not set the current user
						$event['owner'] = $GLOBALS['egw_info']['user']['account_id'];
					}
				}

				// if an etag is given, include it in the update
				if (!is_null($etag))
				{
					$event['etag'] = $etag;
				}

				$original_event = $event;

				if (!($Ok = $this->update($event, true)))
				{
					if ($Ok === false && $cal_id > 0 && ($egw_event = $this->read($cal_id)))
					{
						$unchanged = true;
						if ($event['recur_type'] != MCAL_RECUR_NONE)
						{
							// Check if a recurring event "status only" exception is created by the client
							foreach (array('uid','owner','title','description',
								'location','priority','public','special','non_blocking') as $key)
							{
								//Horde::logMessage('importVCAL test ' .$key . ': '. $egw_event[$key] . ' == ' .$event[$key],
								//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
								if (!empty($event[$key]) //|| !empty($egw_event[$key]))
										&& $egw_event[$key] != $event[$key])
								{
									$unchanged = false;
									break;
								}
							}
						}
						// check if current user is an attendee and tried to change his status
						if (isset($egw_event['participants'][$this->user]))
						{
							$this->set_status($egw_event, $this->user,
								($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), $recur_date);

							$Ok = $cal_id;
							continue;
						}

						if ($unchanged)
						{
							$Ok = $cal_id;
							continue;
						}
					}
					break;	// stop with the first error
				}
				else
				{
					$eventID = &$Ok;
					/* if(isset($egw_event)
						&& $original_event['participants'] != $egw_event['participants'])
					{
						$this->update_status($original_event, $egw_event, $recur_date);
					} */

					$alarms = $event['alarm'];

					// handle the alarms
					foreach ($component->getComponents() as $valarm)
					{
						if (is_a($valarm, 'Horde_iCalendar_valarm'))
						{
							$this->valarm2egw($alarms, $valarm);
						}
					}

					if (count($alarms) > 0
						|| (isset($this->supportedFields['alarms'])
							&& count($alarms) == 0))
					{
						// delete the old alarms
						$updatedEvent = $this->read($eventID);
						foreach ($updatedEvent['alarm'] as $alarmID => $alarmData)
						{
							$this->delete_alarm($alarmID);
						}
					}

					foreach ($alarms as $alarm)
					{
						if (!isset($alarm['offset']))
						{
							$alarm['offset'] = $event['start'] - $alarm['time'];
						}
						if (!isset($alarm['time']))
						{
							$alarm['time'] = $event['start'] - $alarm['offset'];
						}
						$alarm['owner'] = $GLOBALS['egw_info']['user']['account_id'];
						$alarm['all'] = true;
						$this->save_alarm($eventID, $alarm);
					}
				}
				$cal_id = -1;
			}
			$egw_event = $this->read($eventID);
            Horde::logMessage("importVCAL:\n" . print_r($egw_event, true),
            	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		}
		return $Ok;
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
			'reference'			=> 'reference',
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
			'reference'			=> 'reference',
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

	function icaltoegw($_vcalData, $cal_id=-1)
	{
		$event = false;	// returning false, if file contains no components

		$vcal = new Horde_iCalendar;
		if (!$vcal->parsevCalendar($_vcalData)) return false;

		$version = $vcal->getAttribute('VERSION');

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		foreach ($vcal->getComponents() as $component)
		{
			if (is_a($component, 'Horde_iCalendar_vevent'))
			{
				// We expect only a single VEVENT
				$event = $this->vevent2egw($component, $version, $this->supportedFields);
				if ($event)
				{
					if ($cal_id > 0) {
						$event['id'] = $cal_id;
					}
					else
					{
						if($this->isWholeDay($event)
							&& $this->nonBlockingAllday)
						{
							$event['non_blocking'] = 1;
						}
					}
					if ($this->productManufacturer == '' && $this->productName == ''
						&& !empty($event['recur_enddate']))
					{
						// syncevolution needs an adjusted recur_enddate
						$event['recur_enddate'] = (int)$event['recur_enddate'] + 86400;
					}
				}
				return $event;
			}
		}
		return false;
	}

	/**
	 * Parse an VEVENT
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
					$vcardData['reference']	= $attributes['value'];
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
							} else {
								break;
							}
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
					/*elseif($attributes['params']['CUTYPE'] == 'RESOURCE')
					 {

					 }*/
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
							if (isset($attributes['params']['PARTSTAT']))
							{
								$event['participants'][$uid] = $this->status_ical2egw[strtoupper($attributes['params']['PARTSTAT'])];
							}
							elseif (isset($attributes['params']['STATUS']))
							{
								$event['participants'][$uid] = $this->status_ical2egw[strtoupper($attributes['params']['STATUS'])];
							}
							else
							{
								$event['participants'][$uid] = ($uid == $event['owner'] ? 'A' : 'U');
							}
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
		$result = false;

		if($event = $this->icaltoegw($_vcalData))
		{
			if ($contentID) {
				$event['id'] = $contentID;
			}
			$result = $this->find_event($event, $relax);
		}
		return $result;
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
	function update_status($new_event, $old_event , $recur_date)
   	{
		//error_log(__FILE__ . __METHOD__ . "\nold_event:" . print_r($old_event, true)
		//			. "\nnew_event:" . print_r($new_event, true));

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
        	//error_log(__FILE__ . __METHOD__ . "\n$userid => $status:");
			$this->set_status($old_event, $userid, $status, $recur_date, true);
        }
    }

}
