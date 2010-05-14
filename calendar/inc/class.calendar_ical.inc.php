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
		'D' => 'DELEGATED'
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
		'DELEGATED'    => 'D',
		'X-UNINVITED'  => 'G', // removed
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
	 * user preference: calendar to synchronize with
	 *
	 * @var int
	 */
	var $calendarOwner = 0;

	/**
	 * user preference: use server timezone for exports to device
	 *
	 * @var boolean
	 */
	var $useServerTZ = false;


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
	 * Addressbook BO instance
	 *
	 * @var array
	 */
	var $addressbook;

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
		$this->addressbook = new addressbook_bo;
	}


	/**
	 * Exports one calendar event to an iCalendar item
	 *
	 * @param int|array $events (array of) cal_id or array of the events
	 * @param string $version='1.0' could be '2.0' too
	 * @param string $method='PUBLISH'
	 * @param int $recur_date=0	if set export the next recurrence at or after the timestamp,
	 *                          	default 0 => export whole series (or events, if not recurring)
	 * @param string $principalURL='' Used for CalDAV exports
	 * @return string|boolean string with iCal or false on error (eg. no permission to read the event)
	 */
	function &exportVCal($events, $version='1.0', $method='PUBLISH', $recur_date=0, $principalURL='')
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				"($version, $method, $recur_date, $principalURL)\n",
				3, $this->logfile);
		}
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
			'RECURRENCE-ID' => 'reference',
			'SEQUENCE'		=> 'etag',
			'STATUS'		=> 'status',
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
		$serverTZ = false;
		$events_exported = false;

		if (!is_array($events)) $events = array($events);

		foreach ($events as $event)
		{
			$organizerURL = '';
			$organizerCN = false;
			$recurrence = $this->date2usertime($recur_date);
			if (!is_array($event)
				&& !($event = $this->read($event, $recurrence, false, 'server')))
			{
				if ($this->read($event, $recurrence, true, 'server'))
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							'() User does not have the permission to read event ' . $event['id']. "\n",
							3,$this->logfile);
					}
					return -1; // Permission denied
				}
				else
				{
					$retval = false;  // Entry does not exist
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"() Event $event not found.\n",
							3, $this->logfile);
					}
				}
				continue;
			}
			if ($this->isWholeDay($event))	$event['whole_day'] = true;

			if (strpos($this->productName, 'palmos'))
			{
				$utc = false;

				if (isset($event['whole_day']))
				{
					if (isset($event['reference']))
					{
						$event['reference'] = mktime(0, 0, 0,
							date('m', $event['reference']),
							date('d', $event['reference']),
							date('Y', $event['reference'])
						);
					}

					foreach((array)$event['recur_exception'] as $n => $date)
					{
						$event['recur_exception'][$n] = mktime(0, 0, 0,
							date('m', $date),
							date('d', $date),
							date('Y', $date)
						);
					}
					if (isset($event['alarm']) && is_array($event['alarm']))
					{
						foreach($event['alarm'] as $n => $alarm)
						{
							$event['alarm'][$n]['time'] = $this->date2usertime($alarm['time']);
						}
					}
				}
				else
				{
					$new_events = array($event);
					$this->db2data($new_events, 'ts');
					$event = array_shift($new_events);
				}
			}
			else
			{
				$utc = true;
			}

			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'(' . $event['id']. ',' . $recurrence . ")\n" .
					array2string($event)."\n",3,$this->logfile);
			}

			if ($recurrence)
			{
				if (!isset($this->supportedFields['participants']))
				{
					// We don't need status only exceptions
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"($_id, $recurrence) Gratuitous pseudo exception, skipped ...\n",
							3,$this->logfile);
					}
					continue; // unsupported status only exception
				}

				// force single event
				foreach (array('recur_enddate','recur_interval','recur_exception','recur_data','recur_date','id','etag') as $name)
				{
					unset($event[$name]);
				}
				$event['recur_type'] = MCAL_RECUR_NONE;
			}
			elseif ($event['recur_enddate'])
			{
				$event['recur_enddate'] = mktime(23, 59, 59,
					date('m', $event['recur_enddate']),
					date('d', $event['recur_enddate']),
					date('Y', $event['recur_enddate'])
				);
			}

			if (!$serverTZ && date('e', $event['start']) != 'UTC'
				&& ($event['recur_type'] != MCAL_RECUR_NONE
					|| $this->useServerTZ))
			{
				if (!$this->useServerTZ &&
					$event['recur_type'] != MCAL_RECUR_NONE
					&& $event['recur_enddate'])
				{
					$startDST = date('I', $event['start']);
					$finalDST = date('I', $event['recur_enddate']);
					// Different DST or more than half a year?
					if ($startDST != $finalDST ||
						($event['recur_enddate'] - $event['start']) > 15778800)
					{
						$utc = false;
						$serverTZ = true;
					}
				}
				else
				{
					$utc = false;
					$serverTZ = true;
				}
				if ($serverTZ)
				{
					$serverTZ = self::generate_vtimezone($event, $vcal);
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

			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$exceptions = array();

				// dont use "virtual" exceptions created by participant status for GroupDAV or file export
				if (!in_array($this->productManufacturer,array('file','groupdav')) &&
					isset($this->supportedFields['participants']))
				{
					$exceptions = $this->so->get_recurrence_exceptions($event);
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."(PSEUDO EXCEPTIONS)\n" .
							array2string($exceptions)."\n",3,$this->logfile);
					}
				}
				if (is_array($event['recur_exception']))
				{
					$exceptions = array_unique(array_merge($exceptions, $event['recur_exception']));
					sort($exceptions);
				}
				$event['recur_exception'] = $exceptions;
			}

			foreach ($egwSupportedFields as $icalFieldName => $egwFieldName)
			{
				if (!isset($this->supportedFields[$egwFieldName])) continue;

				$values[$icalFieldName] = array();
				switch ($icalFieldName)
				{
					case 'ATTENDEE':
						foreach ((array)$event['participants'] as $uid => $status)
						{
							if (!($info = $this->resource_info($uid))) continue;
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
									'()attendee:' . array2string($info) ."\n",3,$this->logfile);
							}
							$participantCN = '"' . (empty($info['cn']) ? $info['name'] : $info['cn']) . '"';
							if ($version == '1.0')
							{
								$participantURL = trim($participantCN . (empty($info['email']) ? '' : ' <' . $info['email'] .'>'));
							}
							else
							{
								$participantURL = empty($info['email']) ? '' : 'MAILTO:' . $info['email'];
							}
							calendar_so::split_status($status, $quantity, $role);
							if ($uid == $event['owner'])
							{
								$role = 'CHAIR';
							}
							else
							{
								$role = 'REQ-PARTICIPANT';
							}
							$attributes['ATTENDEE'][]	= $participantURL;
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
						$attributes['CLASS'] = $event['public'] ? 'PUBLIC' : 'PRIVATE';
						break;

    				case 'ORGANIZER':
	    				$organizerCN = '"' . trim($GLOBALS['egw']->accounts->id2name($event['owner'],'account_firstname')
		    				. ' ' . $GLOBALS['egw']->accounts->id2name($event['owner'],'account_lastname')) . '"';
	    				$organizerURL = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');
	    				if ($version == '1.0')
	    				{
		    				$organizerURL = trim($organizerCN . (empty($organizerURL) ? '' : ' <' . $organizerURL .'>'));
	    				}
	    				else
	    				{
		    				$organizerURL = empty($organizerURL) ? '' : 'MAILTO:' . $organizerURL;
	    				}
	    				if (!isset($event['participants'][$event['owner']]))
	    				{
		    				$attributes['ATTENDEE'][] = $organizerURL;
		    				$parameters['ATTENDEE'][] = array(
			    				'CN'       => $organizerCN,
								'ROLE'     => 'CHAIR',
								'PARTSTAT' => 'DELEGATED',
								'CUTYPE'   => 'INDIVIDUAL',
								'RSVP'     => 'FALSE',
								'X-EGROUPWARE-UID' => $event['owner'],
		    				);
	    				}
	    				if ($this->productManufacturer != 'groupdav'
			    				|| !$this->check_perms(EGW_ACL_EDIT,$event['id']))
	    				{
		    				$attributes['ORGANIZER'] = $organizerURL;
		    				$parameters['ORGANIZER']['CN'] = $organizerCN;
		    				$parameters['ORGANIZER']['X-EGROUPWARE-UID'] = $event['owner'];
	    				}
	    				break;

					case 'DTSTART':
						if (!isset($event['whole_day']))
						{
							if ($utc)
							{
								$attributes['DTSTART'] = $event['start'];
							}
							else
							{
								$attributes['DTSTART'] = date('Ymd\THis', $event['start']);
								if ($serverTZ) $parameters['DTSTART']['TZID'] = $serverTZ;
							}
						}
						break;

					case 'DTEND':
						// write start + end of whole day events as dates
						if (isset($event['whole_day']))
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
							if ($utc)
							{
								$attributes['DTEND'] = $event['end'];
							}
							else
							{
								$attributes['DTEND'] = date('Ymd\THis', $event['end']);
								if ($serverTZ) $parameters['DTEND']['TZID'] = $serverTZ;
							}
						}
						break;

					case 'RRULE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;		// no recuring event
						if ($version == '1.0')
						{
							$interval = ($event['recur_interval'] > 1) ? $event['recur_interval'] : 1;
							$rrule = array('FREQ' => $this->recur_egw2ical_1_0[$event['recur_type']].$interval);
							switch ($event['recur_type'])
							{
								case MCAL_RECUR_WEEKLY:
									$days = array();
									foreach ($this->recur_days_1_0 as $id => $day)
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
								$rrule['UNTIL'] = $vcal->_exportDateTime($event['recur_enddate']);
							}
							else
							{
								$rrule['UNTIL'] = '#0';
							}
							$attributes['RRULE'] = $rrule['FREQ'].' '.$rrule['UNTIL'];
						}
						else // $version == '2.0'
						{
							$rrule = array('FREQ' => $this->recur_egw2ical_2_0[$event['recur_type']]);
							switch ($event['recur_type'])
							{
								case MCAL_RECUR_WEEKLY:
									$days = array();
									foreach ($this->recur_days as $id => $day)
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
								// UNTIL should be a UTC timestamp
								$rrule['UNTIL'] = $vcal->_exportDateTime($event['recur_enddate']);
							}
							$attributes['RRULE'] = '';
							$parameters['RRULE'] = $rrule;
						}
						break;

					case 'EXDATE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;
						$days = $event['recur_exception'];
						if (!empty($days))
						{
							// use 'DATE' instead of 'DATE-TIME' on whole day events
							if (isset($event['whole_day']))
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
								if (!$utc)
								{
									foreach ($days as $id => $timestamp)
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
						if ($this->productManufacturer == 'funambol' &&
							(strpos($this->productName, 'outlook') !== false
								|| strpos($this->productName, 'pocket pc') !== false))
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

					case 'STATUS':
						$attributes['STATUS'] = 'CONFIRMED';
						break;

					case 'CATEGORIES':
						if ($event['category'] && ($values['CATEGORIES'] = $this->get_categories($event['category'])))
						{
							if (count($values['CATEGORIES']) == 1)
							{
								$attributes['CATEGORIES'] = array_shift($values['CATEGORIES']);
							}
							else
							{
								$attributes['CATEGORIES'] = '';
							}
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
							if (isset($event['whole_day']))
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
								if ($utc)
								{
									$attributes[$icalFieldName] = $recur_date;
								}
								else
								{
									$attributes[$icalFieldName] = date('Ymd\THis', $recur_date);
									if ($serverTZ) $parameters[$icalFieldName]['TZID'] = $serverTZ;
								}
							}
						}
						elseif ($event['reference'])
						{
							if (isset($event['whole_day']))
							{
								$arr = $this->date2array($event['reference']);
								$vevent->setAttribute($icalFieldName, array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}
							else
							{
								if ($utc)
								{
									$attributes[$icalFieldName] = $event['reference'];
								}
								else
								{
									$attributes[$icalFieldName] = date('Ymd\THis', $event['reference']);
									if ($serverTZ) $parameters[$icalFieldName]['TZID'] = $serverTZ;
								}
							}
						}
						break;

					default:
						if (isset($this->clientProperties[$icalFieldName]['Size']))
						{
							$size = $this->clientProperties[$icalFieldName]['Size'];
							$noTruncate = $this->clientProperties[$icalFieldName]['NoTruncate'];
							if ($this->log && $size > 0)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
									"() $icalFieldName Size: $size, NoTruncate: " .
									($noTruncate ? 'TRUE' : 'FALSE') . "\n",3,$this->logfile);
							}
							//Horde::logMessage("vCalendar $icalFieldName Size: $size, NoTruncate: " .
							//	($noTruncate ? 'TRUE' : 'FALSE'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
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
								if ($this->log)
								{
									error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
										"() $icalFieldName omitted due to maximum size $size\n",3,$this->logfile);
								}
								//Horde::logMessage("vCalendar $icalFieldName omitted due to maximum size $size",
								//	__FILE__, __LINE__, PEAR_LOG_WARNING);
								continue; // skip field
							}
							// truncate the value to size
							$value = substr($value, 0, $size - 1);
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
									"() $icalFieldName truncated to maximum size $size\n",3,$this->logfile);
							}
							//Horde::logMessage("vCalendar $icalFieldName truncated to maximum size $size",
							//	__FILE__, __LINE__, PEAR_LOG_INFO);
						}
						if (!empty($value) || ($size >= 0 && !$noTruncate))
						{
							$attributes[$icalFieldName] = $value;
						}
				}
			}

			if ($this->productManufacturer == 'nokia')
			{
				if ($event['special'] == '1')
				{
					$attributes['X-EPOCAGENDAENTRYTYPE'] = 'ANNIVERSARY';
					$attributes['DTEND'] = $attributes['DTSTART'];
				}
				elseif ($event['special'] == '2' || !empty($event['whole_day']))
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
				// skip over alarms that don't have the minimum required info
				if (!$alarmData['offset'] && !$alarmData['time']) continue;

				// skip alarms not being set for all users and alarms owned by other users
				if ($alarmData['all'] != true && $alarmData['owner'] != $this->user)
				{
					continue;
				}

				if ($alarmData['offset'])
				{
					$alarmData['time'] = $event['start'] - $alarmData['offset'];
				}

				$description = trim(preg_replace("/\r?\n?\\[[A-Z_]+:.*\\]/i", '', $event['description']));

				if ($version == '1.0')
				{
					if ($event['title']) $description = $event['title'];
					if ($description)
					{
						$values['DALARM']['snooze_time'] = '';
						$values['DALARM']['repeat count'] = '';
						$values['DALARM']['display text'] = $description;
						$values['AALARM']['snooze_time'] = '';
						$values['AALARM']['repeat count'] = '';
						$values['AALARM']['display text'] = $description;
					}
					if ($utc)
					{
						$attributes['DALARM'] = $alarmData['time'];
						$attributes['AALARM'] = $alarmData['time'];
					}
					else
					{
						$attributes['DALARM'] = date('Ymd\THis', $alarmData['time']);
						if ($serverTZ) $parameters['DALARM']['TZID'] = $serverTZ;
						$attributes['AALARM'] = date('Ymd\THis', $alarmData['time']);
						if ($serverTZ) $parameters['AALARM']['TZID'] = $serverTZ;
					}
					// lets take only the first alarm
					break;
				}
				else
				{
					// VCalendar 2.0 / RFC 2445

					// RFC requires DESCRIPTION for DISPLAY
					if (!$event['title'] && !$description) continue;

					if (isset($event['whole_day']) && $alarmData['offset'])
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
						if ($utc)
						{
							$value = $alarmData['time'];
						}
						else
						{
							$value = date('Ymd\THis', $alarmData['time']);
							if ($serverTZ) $params['TZID'] = $serverTZ;
						}
						$valarm->setAttribute('TRIGGER', $value, $params);
					}

					$valarm->setAttribute('ACTION','DISPLAY');
					$valarm->setAttribute('DESCRIPTION',$event['title'] ? $event['title'] : $description);
					$vevent->addComponent($valarm);
				}
			}

			foreach ($attributes as $key => $value)
			{
				foreach (is_array($value) && $parameters[$key]['VALUE']!='DATE' ? $value : array($value) as $valueID => $valueData)
				{
					$valueData = $GLOBALS['egw']->translation->convert($valueData,$GLOBALS['egw']->translation->charset(),'UTF-8');
                    $paramData = (array) $GLOBALS['egw']->translation->convert(is_array($value) ?
                    		$parameters[$key][$valueID] : $parameters[$key],
                            $GLOBALS['egw']->translation->charset(),'UTF-8');
                    $valuesData = (array) $GLOBALS['egw']->translation->convert($values[$key],
                    		$GLOBALS['egw']->translation->charset(),'UTF-8');
                    $content = $valueData . implode(';', $valuesData);

					if (preg_match('/[^\x20-\x7F]/', $content) ||
						($paramData['CN'] && preg_match('/[^\x20-\x7F]/', $paramData['CN'])))
					{
						$paramData['CHARSET'] = 'UTF-8';
						switch ($this->productManufacturer)
						{
							case 'groupdav':
								if ($this->productName == 'kde')
								{
									$paramData['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								else
								{
									$paramData['CHARSET'] = '';
									if (preg_match('/([\000-\012\015\016\020-\037\075])/', $valueData))
									{
										$paramData['ENCODING'] = 'QUOTED-PRINTABLE';
									}
									else
									{
										$paramData['ENCODING'] = '';
									}
								}
								break;
							case 'funambol':
								$paramData['ENCODING'] = 'FUNAMBOL-QP';
						}
					}
					/*
					if (preg_match('/([\000-\012])/', $valueData))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
								"() Has invalid XML data: $valueData",3,$this->logfile);
						}
					}
					*/
					$vevent->setAttribute($key, $valueData, $paramData, true, $valuesData);
				}
			}
			$vcal->addComponent($vevent);
			$events_exported = true;
		}

		$retval = $events_exported ? $vcal->exportvCalendar() : false;
 		if ($this->log)
 		{
 			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
				"() '$this->productManufacturer','$this->productName'\n",3,$this->logfile);
 			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
				"()\n".array2string($retval)."\n",3,$this->logfile);
 		}
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
	 * @param string $principalURL='' Used for CalDAV imports
	 * @param int $user=null account_id of owner, default null
	 * @return int|boolean cal_id > 0 on success, false on failure or 0 for a failed etag
	 */
	function importVCal($_vcalData, $cal_id=-1, $etag=null, $merge=false, $recur_date=0, $principalURL='', $user=null)
	{
		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		if (!($events = $this->icaltoegw($_vcalData, $principalURL)))
		{
			return false;
		}

		if ($cal_id > 0)
		{
			if (count($events) == 1)
			{
				$events[0]['id'] = $cal_id;
				if (!is_null($etag)) $events[0]['etag'] = (int) $etag;
				if ($recur_date) $events[0]['reference'] = $recur_date;
			}
			elseif (($foundEvent = $this->find_event(array('id' => $cal_id), 'exact')) &&
					($eventId = array_shift($foundEvent)) &&
					($egwEvent = $this->read($eventId)))
			{
				foreach ($events as $k => $event)
				{
					if (!isset($event['uid'])) $events[$k]['uid'] = $egwEvent['uid'];
				}
			}
		}

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
			if ($this->isWholeDay($event, true)) $event['whole_day'] = true;
			if (is_array($event['category']))
			{
				$event['category'] = $this->find_or_add_categories($event['category'],
					isset($event['id']) ? $event['id'] : -1);
			}
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					."($cal_id, $etag, $recur_date, $principalURL, $user)\n"
					. array2string($event)."\n",3,$this->logfile);
			}
			$updated_id = false;
			$event_info = $this->get_event_info($event);

			// common adjustments for existing events
			if (is_array($event_info['stored_event']))
			{
				if (empty($event['uid']))
				{
					$event['uid'] = $event_info['stored_event']['uid']; // restore the UID if it was not delivered
				}
				elseif (empty($event['id']))
				{
					$event['id'] = $event_info['stored_event']['id']; // CalDAV does only provide UIDs
				}
				if (is_array($event['participants']))
				{
					// if the client does not return a status, we restore the original one
					foreach ($event['participants'] as $uid => $status)
					{
						if ($status[0] == 'X')
						{
							if (isset($event_info['stored_event']['participants'][$uid]))
							{
								if ($this->log)
								{
									error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
										"() Restore status for $uid\n",3,$this->logfile);
								}
								$event['participants'][$uid] = $event_info['stored_event']['participants'][$uid];
							}
							else
							{
								$event['participants'][$uid] = calendar_so::combine_status('U');
							}
						}
					}
				}
				if ($merge)
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"()[MERGE]\n",3,$this->logfile);
					}

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
										$event['participants'][$uid] = $status;
									}
								}
								break;

							default:
								if (!empty($value)) $event[$key] = $value;
						}
					}
				}
				else
				{
					// no merge
					if(!isset($this->supportedFields['category']) || !isset($event['category']))
					{
						$event['category'] = $event_info['stored_event']['category'];
					}
					if (!isset($this->supportedFields['participants'])
						|| !$event['participants']
						|| !is_array($event['participants'])
						|| !count($event['participants']))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"() No participants\n",3,$this->logfile);
						}

						// If this is an updated meeting, and the client doesn't support
						// participants OR the event no longer contains participants, add them back
						unset($event['participants']);
					}
					else
					{
						foreach ($event_info['stored_event']['participants'] as $uid => $status)
						{
							// Is it a resource and no longer present in the event?
							if ($uid[0] == 'r' && !isset($event['participants'][$uid]))
							{
								if ($this->log)
								{
									error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
										"() Restore resource $uid to status $status\n",3,$this->logfile);
								}
								// Add it back in
								$event['participants'][$uid] = $status;
							}
						}
					}

					// avoid that iCal changes the organizer, which is not allowed
					$event['owner'] = $event_info['stored_event']['owner'];
				}
			}
			else // common adjustments for new events
			{
				unset($event['id']);
				// set non blocking all day depending on the user setting
				if (isset($event['whole_day']) && $this->nonBlockingAllday)
				{
					$event['non_blocking'] = 1;
				}

				if (!is_null($user))
				{
					if ($this->check_perms(EGW_ACL_ADD, 0, $user))
					{
						$event['owner'] = $user;
					}
					else
					{
						return false; // no permission
					}
				}
				// check if an owner is set and the current user has add rights
				// for that owners calendar; if not set the current user
				elseif (!isset($event['owner'])
					|| !$this->check_perms(EGW_ACL_ADD, 0, $event['owner']))
				{
					$event['owner'] = $this->user;
				}

				if (!$event['participants']
					|| !is_array($event['participants'])
					|| !count($event['participants']))
				{
					$status = $event['owner'] == $this->user ? 'A' : 'U';
					$status = calendar_so::combine_status($status, 1, 'CHAIR');
					$event['participants'] = array($event['owner'] => $status);
				}
				else
				{
					foreach ($event['participants'] as $uid => $status)
					{
						// if the client did not give us a proper status => set default
						if ($status[0] == 'X')
						{
							if ($uid == $event['owner'])
							{
								$event['participants'][$uid] = calendar_so::combine_status('A', 1, 'CHAIR');
							}
							else
							{
								$event['participants'][$uid] = calendar_so::combine_status('U');
							}
						}
					}
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

					case 'SERIES-PSEUDO-EXCEPTION':
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
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"(): event SINGLE\n",3,$this->logfile);
					}

					// update the event
					if ($event_info['acl_edit'])
					{
						// Force SINGLE
						$event['reference'] = 0;
						$event_to_store = array($event); // prevent $event from being changed by the update method
						$this->db2data($event_to_store);
						$event_to_store = array_shift($event_to_store);
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-MASTER':
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"(): event SERIES-MASTER\n",3,$this->logfile);
					}

					// remove all known pseudo exceptions and update the event
					if ($event_info['acl_edit'])
					{
						$days = $this->so->get_recurrence_exceptions($event_info['stored_event']);
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."(PSEUDO EXCEPTIONS):\n" .
								array2string($days)."\n",3,$this->logfile);
						}
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

						$event_to_store = array($event); // prevent $event from being changed by the update method
						$this->db2data($event_to_store);
						$event_to_store = array_shift($event_to_store);
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-EXCEPTION':
				case 'SERIES-EXCEPTION-PROPAGATE':
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"(): event SERIES-EXCEPTION\n",3,$this->logfile);
					}

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
							unset($event_info['stored_event']);
							$event['recur_type'] = MCAL_RECUR_NONE;
							$event_info['master_event']['recur_exception'] =
								array_unique(array_merge($event_info['master_event']['recur_exception'],
									array($event['reference'])));
							$event['category'] = $event_info['master_event']['category'];
							$event['owner'] = $event_info['master_event']['owner'];
							$event_to_store = array($event_info['master_event']); // prevent the master_event from being changed by the update method
							$this->db2data($event_to_store);
							$event_to_store = array_shift($event_to_store);
							$this->update($event_to_store, true);
							unset($event_to_store);
						}

						$event_to_store = array($event); // prevent $event from being changed by update method
						$this->db2data($event_to_store);
						$event_to_store = array_shift($event_to_store);
						$updated_id = $this->update($event_to_store, true);
						unset($event_to_store);
					}
					break;

				case 'SERIES-PSEUDO-EXCEPTION':
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"(): event SERIES-PSEUDO-EXCEPTION\n",3,$this->logfile);
					}
					//Horde::logMessage('importVCAL event SERIES-PSEUDO-EXCEPTION',
					//	__FILE__, __LINE__, PEAR_LOG_DEBUG);

					if ($event_info['acl_edit'])
					{
						// truncate the status only exception from the series master
						$recur_exceptions = array();
						foreach ($event_info['master_event']['recur_exception'] as $recur_exception)
						{
							if ($recur_exception != $event['reference'])
							{
								$recur_exceptions[] = $recur_exception;
							}
						}
						$event_info['master_event']['recur_exception'] = $recur_exceptions;

						// save the series master with the adjusted exceptions
						$event_to_store = array($event_info['master_event']); // prevent the master_event from being changed by the update method
						$this->db2data($event_to_store);
						$event_to_store = array_shift($event_to_store);
						$updated_id = $this->update($event_to_store, true, true, false, false);
						unset($event_to_store);
					}
					break;
			}

			// read stored event into info array for fresh stored (new) events
			if (!is_array($event_info['stored_event']) && $updated_id > 0)
			{
				$event_info['stored_event'] = $this->read($updated_id, 0, false, 'server');
			}

			if (isset($event['participants']))
			{
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

					case 'SERIES-PSEUDO-EXCEPTION':
						if (is_array($event_info['master_event'])) // status update requires a stored master event
						{
							$recurrence = $this->date2usertime($event['reference']);
							if ($event_info['acl_edit'])
							{
								// update all participants if we have the right to do that
								$this->update_status($event, $event_info['stored_event'], $recurrence);
							}
							elseif (isset($event['participants'][$this->user]) || isset($event_info['master_event']['participants'][$this->user]))
							{
								// update the users status only
								$this->set_status($event_info['master_event']['id'], $this->user,
									($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), $recurrence, true);
							}
						}
						break;
				}
			}
			// choose which id to return to the client
			switch ($event_info['type'])
			{
				case 'SINGLE':
				case 'SERIES-MASTER':
				case 'SERIES-EXCEPTION':
					$return_id = is_array($event_info['stored_event']) ? $event_info['stored_event']['id'] : false;
					break;

				case 'SERIES-PSEUDO-EXCEPTION':
					$return_id = is_array($event_info['master_event']) ? $event_info['master_event']['id'] . ':' . $event['reference'] : false;
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
						// we have to keep the SERIES-PSEUDO-EXCEPTION id and keep the event untouched
						$return_id = $event_info['master_event']['id'] . ':' . $event['reference'];
					}
					break;
			}

			if ($this->log)
			{
				$event_info['stored_event'] = $this->read($event_info['stored_event']['id']);
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()[$updated_id]\n" .
					array2string($event_info['stored_event'])."\n",3,$this->logfile);
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
		$state =& $_SESSION['SyncML.state'];
		if (isset($state))
		{
			$deviceInfo = $state->getClientDeviceInfo();
		}

		// store product manufacturer and name for further usage
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
				$this->useServerTZ = ($deviceInfo['tzid'] == 1);
			}
			elseif (strpos($this->productName, 'palmos') !== false)
			{
				// for palmos we have to use user-time and NO timezone
				$this->useServerTZ = false;
			}
			if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['calendar_owner']))
			{
				$owner = $GLOBALS['egw_info']['user']['preferences']['syncml']['calendar_owner'];
				switch ($owner)
				{
					case 'G':
					case 'P':
					case 0:
					case -1:
						$owner = $this->user;
						break;
					default:
						if ((int)$owner && $this->check_perms(EGW_ACL_EDIT, 0, $owner))
						{
							$this->calendarOwner = $owner;
						}
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
			'recur_exception'	=> 'recur_exception',
			'title'				=> 'title',
			'alarm'				=> 'alarm',
		);

		$defaultFields['basic'] = $defaultFields['minimal'] + array(
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
			'etag'				=> 'etag',
		);

		$defaultFields['funambol'] = $defaultFields['basic'] + array(
			'participants'		=> 'participants',
			'owner'				=> 'owner',
			'category'			=> 'category',
			'non_blocking'		=> 'non_blocking',
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
			'etag'				=> 'etag',
			'status'			=> 'status',
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
					case 'n97':
					case 'n97 mini':
					case '5800 xpressmusic':
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
					case 'w890i':
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
				$this->useServerTZ = true;
				$this->supportedFields = $defaultFields['full'];
				break;

			case 'groupdav':		// all GroupDAV access goes through here
				$this->useServerTZ = true;
				switch ($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['full'];
				}
				break;

			case 'funambol':
				$this->supportedFields = $defaultFields['funambol'];
				break;

			// the fallback for SyncML
			default:
				error_log("Unknown calendar SyncML client: manufacturer='$this->productManufacturer'  product='$this->productName'");
				$this->supportedFields = $defaultFields['synthesis'];
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				'(' . $this->productManufacturer .
				', '. $this->productName .', ' .
				($this->useServerTZ ? 'SERVERTIME' : 'USERTIME') .
				', ' . $this->calendarOwner . ")\n" , 3, $this->logfile);
		}

		//Horde::logMessage('setSupportedFields(' . $this->productManufacturer . ', '
		//	. $this->productName .', ' .
		//	($this->useServerTZ ? 'TRUE' : 'FALSE') .')',
		//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
	}

	/**
	 * Convert vCalendar data in EGw events
	 *
	 * @param string $_vcalData
	 * @param string $principalURL='' Used for CalDAV imports
	 * @return array|boolean events on success, false on failure
	 */
	function icaltoegw($_vcalData, $principalURL='')
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($principalURL)\n" .
				array2string($_vcalData)."\n",3,$this->logfile);
		}

		$events = array();

		$vcal = new Horde_iCalendar;
		if (!$vcal->parsevCalendar($_vcalData))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"(): No vCalendar Container found!\n",3,$this->logfile);
			}
			return false;
		}
		$version = $vcal->getAttribute('VERSION');
		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		foreach ($vcal->getComponents() as $component)
		{
			if (is_a($component, 'Horde_iCalendar_vevent'))
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.'()' .
						get_class($component)." found\n",3,$this->logfile);
				}
				if ($event = $this->vevent2egw($component, $version, $this->supportedFields, $principalURL))
				{
					if ($this->isWholeDay($event)) $event['whole_day'] = true;
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
 						$event['reference'] = 0;
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

					if (strpos($this->productName, 'palmos'))
					{
						if (isset($event['whole_day']))
						{
							$event['start'] = mktime(0, 0, 0,
								date('m', $event['start']),
								date('d', $event['start']),
								date('Y', $event['start'])
							);
							$event['end'] = mktime(23, 59, 59,
								date('m', $event['end']),
								date('d', $event['end']),
								date('Y', $event['end'])
							);
							if (isset($event['reference']))
							{
								$event['reference'] = mktime(0, 0, 0,
									date('m', $event['reference']),
									date('d', $event['reference']),
									date('Y', $event['reference'])
								);
							}
							foreach($event['recur_exception'] as $n => $date)
							{
								$event['recur_exception'][$n] = mktime(0, 0, 0,
									date('m', $date),
									date('d', $date),
									date('Y', $date)
								);
							}
						}
						else
						{
							foreach(array('start','end','recur_enddate','reference') as $ts)
							{
								// we convert here from user-time to timestamps in server-time!
								if (isset($event[$ts])) $event[$ts] = $event[$ts] ? $this->date2ts($event[$ts],true) : 0;
							}
							// same with the recur exceptions
							if (isset($event['recur_exception']) && is_array($event['recur_exception']))
							{
								foreach($event['recur_exception'] as $n => $date)
								{
									$event['recur_exception'][$n] = $this->date2ts($date,true);
								}
							}
						}
						// same with the alarms
						if (isset($event['alarm']) && is_array($event['alarm']))
						{
							foreach($event['alarm'] as &$alarm)
							{
								$alarm['time'] = $this->date2ts($alarm['time'],true);
							}
						}
					}
					if (!empty($event['recur_enddate']))
					{
						// reset recure_enddate to 00:00:00
						$event['recur_enddate'] = mktime(0, 0, 0,
							date('m', $event['recur_enddate']),
							date('d', $event['recur_enddate']),
							date('Y', $event['recur_enddate'])
						);
					}

					$events[] = $event;
				}
			}
			else
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.'()' .
						get_class($component)." found\n",3,$this->logfile);
				}
			}
		}

		return $events;
	}

	/**
	 * Parse a VEVENT
	 *
	 * @param array $component			VEVENT
	 * @param string $version			vCal version (1.0/2.0)
	 * @param array $supportedFields	supported fields of the device
	 * @param string $principalURL=''	Used for CalDAV imports
	 *
	 * @return array|boolean			event on success, false on failure
	 */
	function vevent2egw(&$component, $version, $supportedFields, $principalURL='')
	{
		if (!is_a($component, 'Horde_iCalendar_vevent'))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.'()' .
					get_class($component)." found\n",3,$this->logfile);
			}
			return false;
		}

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
		if (!isset($vcardData['start']))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. "() DTSTART missing!\n",3,$this->logfile);
			}
			return false; // not a valid entry
		}
		// lets see what we can get from the vcard
		foreach ($component->_attributes as $attributes)
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. '(): ' . $attributes['name'] . ' => '
					. $attributes['value'] . "\n",3,$this->logfile);
			}
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
					$vcardData['description'] = str_replace("\r\n", "\n", $attributes['value']);
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
					$vcardData['reference'] = $attributes['value'];
					break;
				case 'LOCATION':
					$vcardData['location']	= str_replace("\r\n", "\n", $attributes['value']);
					break;
				case 'RRULE':
					$recurence = $attributes['value'];
					$vcardData['recur_interval'] = 1;
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
						$vcardData['recur_interval'] = (int) $matches[1] ? (int) $matches[1] : 1;
					}
					$vcardData['recur_data'] = 0;
					switch($type)
					{
						case 'W':
						case 'WEEKLY':
							$days = array();
							if (preg_match('/W(\d+) *((?i: [AEFHMORSTUW]{2})+)?( +([^ ]*))$/',$recurence, $recurenceMatches))		// 1.0
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if (empty($recurenceMatches[2]))
								{
									$days[0] = strtoupper(substr(date('D', $vcardData['start']),0,2));
								}
								else
								{
									$days = explode(' ',trim($recurenceMatches[2]));
								}

								if (preg_match('/#(\d+)/',$recurenceMatches[4],$repeatMatches))
								{
									if ($repeatMatches[1]) $vcardData['recur_count'] = $repeatMatches[1];
								}
								else
								{
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($recurenceMatches[4]);
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
							if (preg_match('/D(\d+) #(\d+)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] > 0 && $vcardData['end'])
								{
									$vcardData['recur_enddate'] = mktime(
										date('H', $vcardData['end']),
										date('i', $vcardData['end']),
										date('s', $vcardData['end']),
										date('m', $vcardData['end']),
										date('d', $vcardData['end']) + ($vcardData['recur_interval']*($recurenceMatches[2]-1)),
										date('Y', $vcardData['end'])
									);
								}
							}
							elseif (preg_match('/D(\d+) (.*)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_interval'] = $recurenceMatches[1];
								$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime(trim($recurenceMatches[2]));
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
							if (preg_match('/MD(\d+)(?: [^ ])? #(\d+)/', $recurence, $recurenceMatches))
							{
								$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_MDAY;
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if ($recurenceMatches[2] > 0 && $vcardData['end'])
								{
									$vcardData['recur_enddate'] = mktime(
										date('H', $vcardData['end']),
										date('i', $vcardData['end']),
										date('s', $vcardData['end']),
										date('m', $vcardData['end']) + ($vcardData['recur_interval']*($recurenceMatches[2]-1)),
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
								$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime(trim($recurenceMatches[2]));
							}
							elseif (preg_match('/MP(\d+) (.*) (.*) (.*)/',$recurence, $recurenceMatches))
							{
								$vcardData['recur_type'] = MCAL_RECUR_MONTHLY_WDAY;
								$vcardData['recur_interval'] = $recurenceMatches[1];
								if (preg_match('/#(\d+)/',$recurenceMatches[4],$recurenceMatches))
								{
									if ($recurenceMatches[1])
									{
										$vcardData['recur_enddate'] = mktime(
											date('H', $vcardData['end']),
											date('i', $vcardData['end']),
											date('s', $vcardData['end']),
											date('m', $vcardData['start']) + ($vcardData['recur_interval']*($recurenceMatches[1]-1)),
											date('d', $vcardData['start']),
											date('Y', $vcardData['start']));
									}
								}
								else
								{
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime(trim($recurenceMatches[4]));
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
								$enddate = trim($recurenceMatches[2]);
								if ($enddate != '#0')
								{
									if (preg_match('/([\d,]+) (.*)/', $enddate, $fixMatches))
									{
										$enddate = trim($fixMatches[2]);
									}
									$vcardData['recur_enddate'] = $this->vCalendar->_parseDateTime($enddate);
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
					if (!$attributes['value']) break;
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
					$vcardData['title'] = str_replace("\r\n", "\n", $attributes['value']);
					break;
				case 'UID':
					if (strlen($attributes['value']) >= $minimum_uid_length)
					{
						$vcardData['uid'] = $attributes['value'];
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
					if ($this->productManufacturer == 'funambol' &&
						(strpos($this->productName, 'outlook') !== false
							|| strpos($this->productName, 'pocket pc') !== false))
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
						$vcardData['category'] = explode(',', $attributes['value']);
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
						if (empty($status)) $status = 'X';
					}
					else
					{
						$status = 'X'; // client did not return the status
					}
					$uid = $email = $cn = '';
					$quantity = 1;
					$role = 'REQ-PARTICIPANT';
					if (!empty($attributes['params']['ROLE']))
					{
						$role = $attributes['params']['ROLE'];
					}
					// try pricipal url from CalDAV
					if (strpos($attributes['value'], 'http') === 0)
					{
						if (!empty($principalURL) && strstr($attributes['value'], $principalURL) !== false)
						{
							$uid = $this->user;
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. "(): Found myself: '$uid'\n",3,$this->logfile);
							}
						}
						else
						{
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. '(): Unknown URI: ' . $attributes['value']
									. "\n",3,$this->logfile);
							}
							$attributes['value'] = '';
						}
					}
					// try X-EGROUPWARE-UID
					if (!$uid && !empty($attributes['params']['X-EGROUPWARE-UID']))
					{
						$uid = $attributes['params']['X-EGROUPWARE-UID'];
						if (!empty($attributes['params']['X-EGROUPWARE-QUANTITY']))
						{
							$quantity = $attributes['params']['X-EGROUPWARE-QUANTITY'];
						}
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. "(): Found X-EGROUPWARE-UID: '$uid'\n",3,$this->logfile);
						}
					}
					elseif ($attributes['value'] == 'Unknown')
					{
							// we use the current user
							$uid = $this->user;
					}
					// try to find an email address
					elseif (preg_match('/MAILTO:([@.a-z0-9_-]+)|MAILTO:"?([.a-z0-9_ -]*)"?[ ]*<([@.a-z0-9_-]*)>/i',
						$attributes['value'],$matches))
					{
						$email = $matches[1] ? $matches[1] : $matches[3];
						$cn = isset($matches[2]) ? $matches[2]: '';
					}
					elseif (!empty($attributes['value']) &&
						preg_match('/"?([.a-z0-9_ -]*)"?[ ]*<([@.a-z0-9_-]*)>/i',
						$attributes['value'],$matches))
					{
						$cn = $matches[1];
						$email = $matches[2];
					}
					elseif (strpos($attributes['value'],'@') !== false)
					{
						$email = $attributes['value'];
					}
					if (!$uid && $email && ($uid = $GLOBALS['egw']->accounts->name2id($email, 'account_email')))
					{
						// we use the account we found
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. "() Found account: '$uid', '$cn', '$email'\n",3,$this->logfile);
						}
					}
					if (!$uid)
					{
						$searcharray = array();
						// search for provided email address ...
						if ($email)
						{
							$searcharray = array('email' => $email, 'email_home' => $email);
						}
						// ... and for provided CN
						if (!empty($attributes['params']['CN']))
						{
							$cn = $attributes['params']['CN'];
							if ($cn[0] == '"' && substr($cn,-1) == '"')
							{
								$cn = substr($cn,1,-1);
							}
							$searcharray['n_fn'] = $cn;
						}
						elseif ($cn)
						{
							$searcharray['n_fn'] = $cn;
						}

						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. "() Search participant: '$cn', '$email'\n",3,$this->logfile);
						}

						//elseif (//$attributes['params']['CUTYPE'] == 'GROUP'
						if (preg_match('/(.*) ' . lang('Group') . '/', $cn, $matches))
						{
							// we found a group
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. "() Found group: '$matches[1]', '$cn', '$email'\n",3,$this->logfile);
							}
							if (($uid =  $GLOBALS['egw']->accounts->name2id($matches[1], 'account_lid', 'g')))
							{
								//Horde::logMessage("vevent2egw: group participant $uid",
								//			__FILE__, __LINE__, PEAR_LOG_DEBUG);
								if (!isset($vcardData['participants'][$this->user]) &&
									$status != 'X' && $status != 'U')
								{
									// User tries to reply to the group invitiation
									$members = $GLOBALS['egw']->accounts->members($uid, true);
									if (in_array($this->user, $members))
									{
										//Horde::logMessage("vevent2egw: set status to " . $status,
										//		__FILE__, __LINE__, PEAR_LOG_DEBUG);
										$vcardData['participants'][$this->user] =
											calendar_so::combine_status($status,$quantity,$role);
									}
								}
								$status = 'U'; // keep the group
							}
							else continue; // can't find this group
						}
						elseif (empty($searcharray))
						{
							continue;	// participants without email AND CN --> ignore it
						}
						elseif ((list($data) = $this->addressbook->search($searcharray,
							array('id','egw_addressbook.account_id as account_id','n_fn'),
							'egw_addressbook.account_id IS NOT NULL DESC, n_fn IS NOT NULL DESC',
							'','',false,'OR')))
						{
							// found an addressbook entry
							$uid = $data['account_id'] ? (int)$data['account_id'] : 'c'.$data['id'];
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. "() Found addressbook entry: '$uid', '$cn', '$email'\n",3,$this->logfile);
							}
						}
						else
						{
							if (!$email)
							{
								$email = 'no-email@egroupware.org';	// set dummy email to store the CN
							}
							$uid = 'e'. ($cn ? '"' . $cn . '" <' . $email . '>' : $email);
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. "() Not Found, create dummy: '$uid', '$cn', '$email'\n",3,$this->logfile);
							}
						}
					}
					switch($attributes['name'])
					{
						case 'ATTENDEE':
							if (!isset($vcardData['participants'][$uid]) ||
									$vcardData['participants'][$uid][0] != 'A')
							{
								// for multiple entries the ACCEPT wins
								// add quantity and role
								$vcardData['participants'][$uid] =
									calendar_so::combine_status($status, $quantity, $role);

								if (!$this->calendarOwner && is_numeric($uid) &&
										$role == 'CHAIR' &&
										is_a($component->getAttribute('ORGANIZER'), 'PEAR_Error'))
								{
									// we can store the ORGANIZER as event owner
									$event['owner'] = $uid;
								}
							}
							break;

						case 'ORGANIZER':
							if (isset($vcardData['participants'][$uid]))
							{
								$status = $vcardData['participants'][$uid];
								calendar_so::split_status($status, $quantity, $role);
								$vcardData['participants'][$uid] =
									calendar_so::combine_status($status, $quantity, 'CHAIR');
							}
							if (!$this->calendarOwner && is_numeric($uid))
							{
								// we can store the ORGANIZER as event owner
								$event['owner'] = $uid;
							}
							else
							{
								// we must insert a CHAIR participant to keep the ORGANIZER
								$event['owner'] = $this->user;
								if (!isset($vcardData['participants'][$uid]))
								{
									// save the ORGANIZER as event CHAIR
									$vcardData['participants'][$uid] =
										calendar_so::combine_status('D', 1, 'CHAIR');
								}
							}
					}
					break;
				case 'CREATED':		// will be written direct to the event
					if ($event['modified']) break;
					// fall through
				case 'LAST-MODIFIED':	// will be written direct to the event
					$event['modified'] = $attributes['value'];
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
		if ($this->calendarOwner) $event['owner'] = $this->calendarOwner;

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($principalURL)\n" .
				array2string($event)."\n",3,$this->logfile);
		}
		//Horde::logMessage("vevent2egw:\n" . print_r($event, true),
        //    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $event;
	}

	function search($_vcalData, $contentID=null, $relax=false)
	{
		if (($events = $this->icaltoegw($_vcalData)))
		{
			// this function only supports searching a single event
			if (count($events) == 1)
			{
				$filter = $relax ? 'relax' : 'check';
				$event = array_shift($events);
				$eventId = -1;
				if ($this->isWholeDay($event, true)) $event['whole_day'] = true;
				if ($contentID)
				{
					$parts = preg_split('/:/', $contentID);
					$event['id'] = $eventId = $parts[0];
				}
				$event['category'] = $this->find_or_add_categories($event['category'], $eventId);
				return $this->find_event($event, $filter);
			}
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."() found:\n" .
					array2string($events)."\n",3,$this->logfile);
			}
		}
		return array();
	}

	/**
	 * Create a freebusy vCal for the given user(s)
	 *
	 * @param int $user account_id
	 * @param mixed $end=null end-date, default now+1 month
	 * @param boolean $utc=true if false, use servertime for dates
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
	 * generate and insert a VTIMEZONE entry to a vcalendar
	 *
	 * @param array $event
	 * @param array $vcal VCALENDAR entry
	 * @return string local timezone name (e.g. 'CET/CEST')
	 */
	static function generate_vtimezone($event, &$vcal)
	{
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
			array("CET/CEST","Europe/Vienna",60,60,"",3,0,5,2,0,10,0,5,3,0),
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
			array("EET/EEST","Europe/Helsinki",120,60,"",3,0,5,2,0,10,0,5,3,0),
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

		$serverTZ = date('e', $event['start']);
		$year = date('Y', $event['start']);
		$i = 0;
		$row = false;
		do
		{
			$tbl_tz_row =& $tbl_tz[$i];
			if ($tbl_tz_row[1] == $serverTZ)
			{
				if ($tbl_tz_row[4] == '' || $year >= (int) $tbl_tz_row[4])
				{
					$row = $i;
				}
				elseif ($row !== false) break;
				elseif ($tbl_tz_row[4] != '' && $year < (int) $tbl_tz_row[4])
				{
					// First DST starts at this year
					$year = (int) $tbl_tz_row[4];
					if (empty($event['recur_enddate'])
						|| $year <= date('Y', $event['recur_enddate']))
					{
						$row = $i;
					}
					break;
				}
			}
			elseif ($row !== false) break;
		}
		while (is_array($tbl_tz[++$i]));

		if ($row === false) return $serverTZ; // UTC or unkown TZ

		$tbl_tz_row =& $tbl_tz[$row];

		if (preg_match('/(.+)\/(.+)/', $tbl_tz_row[0], $matches))
		{
			$stdname = $matches[1];
			$dstname = $matches[2];
		}
		else
		{
			$stdname = $dstname = $tbl_tz_row[0];
		}

		$container = false;
		$vtimezone = Horde_iCalendar::newComponent('VTIMEZONE', $container);
		$vtimezone->setAttribute('TZID', $tbl_tz_row[1]);
		do {
			if (is_array($tbl_tz[++$row]))
			{
				$tbl_next_row =& $tbl_tz[$row];
				if ($tbl_next_row[1] != $serverTZ) $tbl_next_row = false;
			}
			else $tbl_next_row = false;

			$minutes = $tbl_tz_row[2] + $tbl_tz_row[3];
			$value1['ahead'] = ($minutes >= 0);
			$minutes = abs($minutes);
			$value1['hour'] = (int)$minutes / 60;
			$value1['minute'] = $minutes % 60;
			$minutes = $tbl_tz_row[2];
			$value2['ahead'] = ($minutes > 0);
			$minutes = abs($minutes);
			$value2['hour'] = (int)$minutes / 60;
			$value2['minute'] = $minutes % 60;

			$daylight = Horde_iCalendar::newComponent('DAYLIGHT', $container);
			$dtstart = self::calc_dtstart($year, $tbl_tz_row[5],
				$tbl_tz_row[6], $tbl_tz_row[7], $tbl_tz_row[8], $tbl_tz_row[9]);
			$daylight->setAttribute('DTSTART', $dtstart);
			$byday = ($tbl_tz_row[7] == 5 ? '-1' : $tbl_tz_row[7]) . $dayofweek[$tbl_tz_row[6]];
			$rrule = array('FREQ' => 'YEARLY', 'BYMONTH' => $tbl_tz_row[5], 'BYDAY' => $byday);
			if ($tbl_next_row)
			{
				$rrule['UNTIL'] =  $tbl_next_row[4] . '0101T000000Z';
			}
			$daylight->setAttribute('RRULE', '', $rrule);
			$daylight->setAttribute('TZNAME', $dstname);
			$daylight->setAttribute('TZOFFSETFROM', $value2);
			$daylight->setAttribute('TZOFFSETTO', $value1);

			$standard = Horde_iCalendar::newComponent('STANDARD', $container);
			$dtstart = self::calc_dtstart($year, $tbl_tz_row[10], $tbl_tz_row[11],
				$tbl_tz_row[12], $tbl_tz_row[13], $tbl_tz_row[14]);
			$standard->setAttribute('DTSTART', $dtstart);
			$byday = ($tbl_tz_row[12] == 5 ? '-1' : $tbl_tz_row[12]) . $dayofweek[$tbl_tz_row[11]];
			$rrule['BYMONTH'] = $tbl_tz_row[10];
			$rrule['BYDAY'] = $byday;
			$standard->setAttribute('RRULE', '', $rrule);
			$standard->setAttribute('TZNAME', $stdname);
			$standard->setAttribute('TZOFFSETFROM', $value1);
			$standard->setAttribute('TZOFFSETTO', $value2);

			$vtimezone->addComponent($daylight);
			$vtimezone->addComponent($standard);
			if ($tbl_next_row)
			{
				$tbl_tz_row = $tbl_next_row;
				$year = (int) $tbl_tz_row[4];
			}
		} while ($tbl_next_row && (empty($event['recur_enddate']) ||
			$year <= date('Y', $event['recur_enddate'])));

		$vcal->addComponent($vtimezone);

		return $serverTZ;
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
	static function calc_dtstart($wYear, $wMonth, $wDayOfWeek, $wNth, $wHour, $wMinute)
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
