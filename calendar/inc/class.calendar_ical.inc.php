<?php
/**
 * EGroupware - Calendar iCal import and export via Horde iCalendar classes
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage export
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * iCal import and export via Horde iCalendar classes
 *
 * @ToDo: NOT changing default-timezone as it messes up timezone calculation of timestamps eg. in calendar_boupdate::send_update
 * 	(currently fixed by restoring server-timezone in calendar_boupdate::send_update)
 */
class calendar_ical extends calendar_boupdate
{
	/**
	 * @var array $supportedFields array containing the supported fields of the importing device
	 */
	var $supportedFields;

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
	 * === false => use event's TZ
	 * === null  => export in UTC
	 * string    => device TZ
	 *
	 * @var string|boolean
	 */
	var $tzid = null;

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
	 * Event callback
	 * If set, this will be called on each discovered event so it can be
	 * modified.  Event is passed by reference, return true to keep the event
	 * or false to skip it.
	 *
	 * @var callable
	 */
	var $event_callback = null;

	/**
	 * Conflict callback
	 * If set, conflict checking will be enabled, and the event as well as
	 * conflicts are passed as parameters to this callback
	 */
	var $conflict_callback = null;

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
		$this->vCalendar = new Horde_Icalendar;
		$this->addressbook = new Api\Contacts;
	}


	/**
	 * Exports one calendar event to an iCalendar item
	 *
	 * @param int|array $events (array of) cal_id or array of the events with timestamps in server time
	 * @param string $version ='1.0' could be '2.0' too
	 * @param string $method ='PUBLISH'
	 * @param int $recur_date =0	if set export the next recurrence at or after the timestamp,
	 *                          default 0 => export whole series (or events, if not recurring)
	 * @param string $principalURL ='' Used for CalDAV exports
	 * @param string $charset ='UTF-8' encoding of the vcalendar, default UTF-8
	 * @param int|string $current_user =0 uid of current user to only export that one as participant for method=REPLY
	 * @return string|boolean string with iCal or false on error (e.g. no permission to read the event)
	 */
	function exportVCal($events, $version='1.0', $method='PUBLISH', $recur_date=0, $principalURL='', $charset='UTF-8', $current_user=0)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				"($version, $method, $recur_date, $principalURL, $charset)\n",
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
			'RECURRENCE-ID' => 'recurrence',
			'SEQUENCE'		=> 'etag',
			'STATUS'		=> 'status',
			'ATTACH'        => 'attachments',
		);

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		if ($this->productManufacturer == '' )
		{	// syncevolution is broken
			$version = '2.0';
		}

		$vcal = new Horde_Icalendar;
		$vcal->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
		$vcal->setAttribute('VERSION', $version);
		if ($method) $vcal->setAttribute('METHOD', $method);
		$events_exported = false;

		if (!is_array($events)) $events = array($events);

		$vtimezones_added = array();
		foreach ($events as $event)
		{
			$organizerURL = '';
			$organizerCN = false;
			$recurrence = $this->date2usertime($recur_date);
			$tzid = null;

			if ((!is_array($event) || empty($event['tzid']) && ($event = $event['id'])) &&
				!($event = $this->read($event, $recurrence, false, 'server')))
			{
				if ($this->read($event, $recurrence, true, 'server'))
				{
					if ($this->check_perms(calendar_bo::ACL_FREEBUSY, $event, 0, 'server'))
					{
						$this->clear_private_infos($event, array($this->user, $event['owner']));
					}
					else
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								'() User does not have the permission to read event ' . $event['id']. "\n",
								3,$this->logfile);
						}
						return -1; // Permission denied
					}
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

			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'() export event UID: ' . $event['uid'] . ".\n",
					3, $this->logfile);
			}

			if ($this->tzid)
			{
				// explicit device timezone
				$tzid = $this->tzid;
			}
			elseif ($this->tzid === false)
			{
				// use event's timezone
				$tzid = $event['tzid'];
			}

			if (!isset(self::$tz_cache[$event['tzid']]))
			{
				try {
					self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
				}
				catch (Exception $e) {
					// log unknown timezones
					if (!empty($event['tzid'])) _egw_log_exception($e);
					// default for no timezone and unkown to user timezone
					self::$tz_cache[$event['tzid']] = Api\DateTime::$user_timezone;
				}
			}

			if ($this->so->isWholeDay($event)) $event['whole_day'] = true;

			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'(' . $event['id']. ',' . $recurrence . ")\n" .
					array2string($event)."\n",3,$this->logfile);
			}

			if ($recurrence)
			{
				if (!($master = $this->read($event['id'], 0, true, 'server'))) continue;

				if (!isset($this->supportedFields['participants']))
				{
					$days = $this->so->get_recurrence_exceptions($master, $tzid, 0, 0, 'tz_rrule');
					if (isset($days[$recurrence]))
					{
						$recurrence = $days[$recurrence]; // use remote representation
					}
					else
					{
						// We don't need status only exceptions
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								"(, $recurrence) Gratuitous pseudo exception, skipped ...\n",
								3,$this->logfile);
						}
						continue; // unsupported status only exception
					}
				}
				else
				{
					$days = $this->so->get_recurrence_exceptions($master, $tzid, 0, 0, 'rrule');
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
							array2string($days)."\n",3,$this->logfile);
					}
					$recurrence = $days[$recurrence]; // use remote representation
				}
				// force single event
				foreach (array('recur_enddate','recur_interval','recur_exception','recur_data','recur_date','id','etag') as $name)
				{
					unset($event[$name]);
				}
				$event['recur_type'] = MCAL_RECUR_NONE;
			}

			// check if tzid of event (not only recuring ones) is already added to export
			if ($tzid && $tzid != 'UTC' && !in_array($tzid,$vtimezones_added))
			{
				// check if we have vtimezone component data for tzid of event, if not default to user timezone (default to server tz)
				if (calendar_timezones::add_vtimezone($vcal, $tzid) ||
					!in_array($tzid = Api\DateTime::$user_timezone->getName(), $vtimezones_added) &&
						calendar_timezones::add_vtimezone($vcal, $tzid))
				{
					$vtimezones_added[] = $tzid;
					if (!isset(self::$tz_cache[$tzid]))
					{
						self::$tz_cache[$tzid] = calendar_timezones::DateTimeZone($tzid);
					}
				}
			}
			if ($this->productManufacturer != 'file' && $this->uidExtension)
			{
				// Append UID to DESCRIPTION
				if (!preg_match('/\[UID:.+\]/m', $event['description'])) {
					$event['description'] .= "\n[UID:" . $event['uid'] . "]";
				}
			}

			$vevent = Horde_Icalendar::newComponent('VEVENT', $vcal);
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
				if (!in_array($this->productManufacturer,array('file','groupdav')))
				{
					$filter = isset($this->supportedFields['participants']) ? 'rrule' : 'tz_rrule';
					$exceptions = $this->so->get_recurrence_exceptions($event, $tzid, 0, 0, $filter);
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."(EXCEPTIONS)\n" .
							array2string($exceptions)."\n",3,$this->logfile);
					}
				}
				elseif (is_array($event['recur_exception']))
				{
					$exceptions = array_unique($event['recur_exception']);
					sort($exceptions);
				}
				$event['recur_exception'] = $exceptions;
			}
			foreach ($egwSupportedFields as $icalFieldName => $egwFieldName)
			{
				if (!isset($this->supportedFields[$egwFieldName]))
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							'(' . $event['id'] . ") [$icalFieldName] not supported\n",
							3,$this->logfile);
					}
					continue;
				}
				$values[$icalFieldName] = array();
				switch ($icalFieldName)
				{
					case 'ATTENDEE':
						foreach ((array)$event['participants'] as $uid => $status)
						{
							$quantity = $role = null;
							calendar_so::split_status($status, $quantity, $role);
							// do not include event owner/ORGANIZER as participant in his own calendar, if he is only participant
							if (count($event['participants']) == 1 && $event['owner'] == $uid && $uid == $this->user) continue;

							if (!($info = $this->resource_info($uid))) continue;

							if (in_array($status, array('X','E'))) continue;	// dont include deleted participants

							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
									'()attendee:' . array2string($info) ."\n",3,$this->logfile);
							}
							$participantCN = str_replace(array('\\', ',', ';', ':'),
												array('\\\\', '\\,', '\\;', '\\:'),
												trim(empty($info['cn']) ? $info['name'] : $info['cn']));
							if ($version == '1.0')
							{
								$participantURL = trim('"' . $participantCN . '"' . (empty($info['email']) ? '' : ' <' . $info['email'] .'>'));
							}
							else
							{
								$participantURL = empty($info['email']) ? '' : 'mailto:' . $info['email'];
							}
							// RSVP={TRUE|FALSE}	// resonse expected, not set in eGW => status=U
							$rsvp = $status == 'U' ? 'TRUE' : 'FALSE';
							if ($role == 'CHAIR')
							{
								$organizerURL = $participantURL;
								$rsvp = '';
								$organizerCN = $participantCN;
								$organizerUID = ($info['type'] != 'e' ? (string)$uid : '');
							}
							// iCal method=REPLY only exports replying / current user, except external organiser / chair above
							if ($method == 'REPLY' && $current_user && (string)$current_user !== (string)$uid)
							{
								continue;
							}
							// PARTSTAT={NEEDS-ACTION|ACCEPTED|DECLINED|TENTATIVE|DELEGATED|COMPLETED|IN-PROGRESS} everything from delegated is NOT used by eGW atm.
							$status = $this->status_egw2ical[$status];
							// CUTYPE={INDIVIDUAL|GROUP|RESOURCE|ROOM|UNKNOWN}
							switch ($info['type'])
							{
								case 'g':
									$cutype = 'GROUP';
									$participantURL = 'urn:uuid:'.Api\CalDAV::generate_uid('accounts', $uid);
									if (!isset($event['participants'][$this->user]) &&
										($members = $GLOBALS['egw']->accounts->members($uid, true)) && in_array($this->user, $members))
									{
										$user = $this->resource_info($this->user);
										$attributes['ATTENDEE'][] = 'mailto:' . $user['email'];
										$parameters['ATTENDEE'][] = array(
											'CN'		=>	$user['name'],
											'ROLE'		=> 'REQ-PARTICIPANT',
											'PARTSTAT'	=> 'NEEDS-ACTION',
											'CUTYPE'	=> 'INDIVIDUAL',
											'RSVP'		=> 'TRUE',
											'X-EGROUPWARE-UID'	=> (string)$this->user,
										);
										$event['participants'][$this->user] = true;
									}
									break;
								case 'r':
									$participantURL = 'urn:uuid:'.Api\CalDAV::generate_uid('resources', substr($uid, 1));
									$cutype = Api\CalDAV\Principals::resource_is_location(substr($uid, 1)) ? 'ROOM' : 'RESOURCE';
									// unset resource email (email of responsible user) as iCal at least has problems,
									// if resonpsible is also pariticipant or organizer
									unset($info['email']);
									break;
								case 'u':	// account
								case 'c':	// contact
								case 'e':	// email address
									$cutype = 'INDIVIDUAL';
									break;
								default:
									$cutype = 'UNKNOWN';
									break;
							}
							// generate urn:uuid, if we have no other participant URL
							if (empty($participantURL) && $info && $info['app'])
							{
								$participantURL = 'urn:uuid:'.Api\CalDAV::generate_uid($info['app'], substr($uid, 1));
							}
							// ROLE={CHAIR|REQ-PARTICIPANT|OPT-PARTICIPANT|NON-PARTICIPANT|X-*}
							$options = array();
							if (!empty($participantCN)) $options['CN'] = $participantCN;
							if (!empty($role)) $options['ROLE'] = $role;
							if (!empty($status)) $options['PARTSTAT'] = $status;
							if (!empty($cutype)) $options['CUTYPE'] = $cutype;
							if (!empty($rsvp)) $options['RSVP'] = $rsvp;
							if (!empty($info['email']) && $participantURL != 'mailto:'.$info['email'])
							{
								$options['EMAIL'] = $info['email'];	// only add EMAIL attribute, if not already URL, as eg. Akonadi is reported to have problems with it
							}
							if ($info['type'] != 'e') $options['X-EGROUPWARE-UID'] = (string)$uid;
							if ($quantity > 1)
							{
								$options['X-EGROUPWARE-QUANTITY'] = (string)$quantity;
								$options['CN'] .= ' ('.$quantity.')';
							}
							$attributes['ATTENDEE'][] = $participantURL;
							$parameters['ATTENDEE'][] = $options;
						}
						break;

					case 'CLASS':
						if ($event['public']) continue 2;	// public is default, no need to export, fails CalDAVTester if added as default
						$attributes['CLASS'] = $event['public'] ? 'PUBLIC' : 'PRIVATE';
						// Apple iCal on OS X uses X-CALENDARSERVER-ACCESS: CONFIDENTIAL on VCALANDAR (not VEVENT!)
						if (!$event['public'] && $this->productManufacturer == 'groupdav')
						{
							$vcal->setAttribute('X-CALENDARSERVER-ACCESS', 'CONFIDENTIAL');
						}
						break;

					case 'ORGANIZER':
						if (!$organizerURL)
						{
							$organizerCN = '"' . trim($GLOBALS['egw']->accounts->id2name($event['owner'],'account_firstname')
								. ' ' . $GLOBALS['egw']->accounts->id2name($event['owner'],'account_lastname')) . '"';
							$organizerEMail = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');
							if ($version == '1.0')
							{
								$organizerURL = trim($organizerCN . (empty($organizerURL) ? '' : ' <' . $organizerURL .'>'));
							}
							else
							{
								$organizerURL = empty($organizerEMail) ? '' : 'mailto:' . $organizerEMail;
							}
							$organizerUID = $event['owner'];
						}
						// do NOT use ORGANIZER for events without further participants or a different organizer
						if (is_array($event['participants']) && (count($event['participants']) > 1 || !isset($event['participants'][$event['owner']])) || $event['owner'] != $this->user)
						{
							$attributes['ORGANIZER'] = $organizerURL;
							$parameters['ORGANIZER']['CN'] = $organizerCN;
							if (!empty($organizerUID))
							{
								$parameters['ORGANIZER']['X-EGROUPWARE-UID'] = $organizerUID;
							}
						}
						break;

					case 'DTSTART':
						if (empty($event['whole_day']))
						{
							$attributes['DTSTART'] = self::getDateTime($event['start'],$tzid,$parameters['DTSTART']);
						}
						break;

					case 'DTEND':
						if (empty($event['whole_day']))
						{
							// Hack for CalDAVTester to export duration instead of endtime
							if ($tzid == 'UTC' && ($duration = Api\DateTime::to($event['end'], 'ts') - Api\DateTime::to($event['start'], 'ts')) <= 86400)
								$attributes['duration'] = $duration;
							else
								$attributes['DTEND'] = self::getDateTime($event['end'],$tzid,$parameters['DTEND']);
						}
						else
						{
							// write start + end of whole day events as dates
							if(!is_object($event['end']))
							{
								$event['end'] = new Api\DateTime($event['end']);
							}
							$event['end-nextday'] = clone $event['end'];
							$event['end-nextday']->add("1 day");	// we need the date of the next day, as DTEND is non-inclusive (= exclusive) in rfc2445
							foreach (array('start' => 'DTSTART','end-nextday' => 'DTEND') as $f => $t)
							{
								$time = new Api\DateTime($event[$f],Api\DateTime::$server_timezone);
								$arr = Api\DateTime::to($time,'array');
								$vevent->setAttribute($t, array('year' => $arr['year'],'month' => $arr['month'],'mday' => $arr['day']),
									array('VALUE' => 'DATE'));
							}
							unset($attributes['DTSTART']);
							// Outlook does NOT care about type of DTSTART/END, only setting X-MICROSOFT-CDO-ALLDAYEVENT is used to determine an event is a whole-day event
							$vevent->setAttribute('X-MICROSOFT-CDO-ALLDAYEVENT','TRUE');
						}
						break;

					case 'RRULE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;		// no recuring event
						$rriter = calendar_rrule::event2rrule($event, false, $tzid);
						$rrule = $rriter->generate_rrule($version);
						if ($event['recur_enddate'])
						{
							if (!$tzid || $version != '1.0')
							{
								if (!isset(self::$tz_cache['UTC']))
								{
									self::$tz_cache['UTC'] = calendar_timezones::DateTimeZone('UTC');
								}
								if (empty($event['whole_day']))
								{
									$rrule['UNTIL']->setTimezone(self::$tz_cache['UTC']);
									$rrule['UNTIL'] = $rrule['UNTIL']->format('Ymd\THis\Z');
								}
								// for whole-day events UNTIL must be just the inclusive date
								else
								{
									$rrule['UNTIL'] = $rrule['UNTIL']->format('Ymd');
								}
							}
						}
						if ($version == '1.0')
						{
							if ($event['recur_enddate'] && $tzid)
							{
								$rrule['UNTIL'] = self::getDateTime($rrule['UNTIL'],$tzid);
							}
							$attributes['RRULE'] = $rrule['FREQ'].' '.$rrule['UNTIL'];
						}
						else // $version == '2.0'
						{
							$attributes['RRULE'] = '';
							foreach($rrule as $n => $v)
							{
								$attributes['RRULE'] .= ($attributes['RRULE']?';':'').$n.'='.$v;
							}
						}
						break;

					case 'EXDATE':
						if ($event['recur_type'] == MCAL_RECUR_NONE) break;
						if (!empty($event['recur_exception']))
						{
							if (empty($event['whole_day']))
							{
								foreach ($event['recur_exception'] as $key => $timestamp)
								{
									// current Horde_Icalendar 2.1.4 exports EXDATE always in UTC, postfixed with a Z :(
									// so if we set a timezone here, we have to remove the Z, see the hack at the end of this method
									// Apple calendar on OS X 10.11.4 uses a timezone, so does Horde eg. for Recurrence-ID
									$ex_date = new Api\DateTime($timestamp, Api\DateTime::$server_timezone);
									$event['recur_exception'][$key] = self::getDateTime($ex_date->format('ts') + $ex_date->getOffset(), $tzid, $parameters['EXDATE']);
								}
							}
							else
							{
								// use 'DATE' instead of 'DATE-TIME' on whole day events
								foreach ($event['recur_exception'] as $id => $timestamp)
								{
									$time = new Api\DateTime($timestamp,Api\DateTime::$server_timezone);
									$time->setTimezone(self::$tz_cache[$event['tzid']]);
									$arr = Api\DateTime::to($time,'array');
									$days[$id] = array(
										'year'  => $arr['year'],
										'month' => $arr['month'],
										'mday'  => $arr['day'],
									);
								}
								$event['recur_exception'] = $days;
								if ($version != '1.0') $parameters['EXDATE']['VALUE'] = 'DATE';
							}
							$vevent->setAttribute('EXDATE', $event['recur_exception'], $parameters['EXDATE']);
						}
						break;

					case 'PRIORITY':
						if (!$event['priority']) continue 2;	// 0=undefined is default, no need to export, fails CalDAVTester if our default is added
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
						if (!$event['non_blocking']) continue 2;	// OPAQUE is default, no need to export, fails CalDAVTester if added as default
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
							// We handle a pseudo exception
							if (empty($event['whole_day']))
							{
								$attributes[$icalFieldName] = self::getDateTime($recur_date,$tzid,$parameters[$icalFieldName]);
							}
							else
							{
								$time = new Api\DateTime($recur_date,Api\DateTime::$server_timezone);
								$time->setTimezone(self::$tz_cache[$event['tzid']]);
								$arr = Api\DateTime::to($time,'array');
								$vevent->setAttribute($icalFieldName, array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}
						}
						elseif ($event['recurrence'] && $event['reference'])
						{
							// $event['reference'] is a calendar_id, not a timestamp
							if (!($revent = $this->read($event['reference']))) break;	// referenced event does not exist

							if (empty($revent['whole_day']))
							{
								$attributes[$icalFieldName] = self::getDateTime($event['recurrence'],$tzid,$parameters[$icalFieldName]);
							}
							else
							{
								$time = new Api\DateTime($event['recurrence'],Api\DateTime::$server_timezone);
								$time->setTimezone(self::$tz_cache[$event['tzid']]);
								$arr = Api\DateTime::to($time,'array');
								$vevent->setAttribute($icalFieldName, array(
									'year' => $arr['year'],
									'month' => $arr['month'],
									'mday' => $arr['day']),
									array('VALUE' => 'DATE')
								);
							}

							unset($revent);
						}
						break;

					case 'ATTACH':
						if (!empty($event['id']))
						{
							Api\CalDAV::add_attach('calendar', $event['id'], $attributes, $parameters);
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
								continue 2; // skip field
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

			// for CalDAV add all X-Properties previously parsed
			if ($this->productManufacturer == 'groupdav' || $this->productManufacturer == 'file')
			{
				foreach($event as $name => $value)
				{
					if (substr($name, 0, 2) == '##')
					{
						$attr_name = substr($name, 2);
						// Apply prefix to our stuff
						if(in_array($attr_name,['videoconference','notify_externals']))
						{
							$attr_name = 'X-EGROUPWARE-'.$attr_name;
						}
						// fix certain stock fields like GEO, which are not in EGroupware schema, but Horde Icalendar requires a certain format
						switch($name)
						{
							case '##GEO':
								if (!is_array($value))
								{
									if (strpos($value, ';'))
									{
										list($lat, $long) = explode(';', $value);
									}
									else
									{
										list($long, $lat) = explode(',', $value);
									}
									$value = ['latitude' => $lat, 'logitude' => $long];
								}
								break;
						}
						if ($value[0] === '{' && ($attr = json_decode($value, true)) && is_array($attr))
						{
							// check if attribute was stored compressed --> uncompress it
							if (count($attr) === 1 && !empty($attr['gzcompress']))
							{
								$attr = json_decode(gzuncompress(base64_decode($attr['gzcompress'])), true);
								if (!is_array($attr)) continue;
							}
							$vevent->setAttribute($attr_name, $attr['value'], $attr['params'], true, $attr['values']);
						}
						else
						{
							$vevent->setAttribute($attr_name, $value);
						}
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

			if ($event['created'] || $event['modified'])
			{
				$attributes['CREATED'] = $event['created'] ? $event['created'] : $event['modified'];
			}
			if ($event['modified'])
			{
				$attributes['LAST-MODIFIED'] = $event['modified'];
			}
			$attributes['DTSTAMP'] = time();
			foreach ((array)$event['alarm'] as $alarmData)
			{
				// skip over alarms that don't have the minimum required info
				if (!isset($alarmData['offset']) && !isset($alarmData['time'])) continue;

				// skip alarms not being set for all users and alarms owned by other users
				if ($alarmData['all'] != true && $alarmData['owner'] != $this->user)
				{
					continue;
				}

				if ($alarmData['offset'])
				{
					$alarmData['time'] = Api\DateTime::to($event['start'], 'ts') - $alarmData['offset'];
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
					$attributes['DALARM'] = self::getDateTime($alarmData['time'],$tzid,$parameters['DALARM']);
					$attributes['AALARM'] = self::getDateTime($alarmData['time'],$tzid,$parameters['AALARM']);
					// lets take only the first alarm
					break;
				}
				else
				{
					// VCalendar 2.0 / RFC 2445

					// RFC requires DESCRIPTION for DISPLAY
					if (!$event['title'] && !$description) $description = 'Alarm';

					/* Disabling for now
					// Lightning infinitly pops up alarms for recuring events, if the only use an offset
					if ($this->productName == 'lightning' && $event['recur_type'] != MCAL_RECUR_NONE)
					{
						// return only future alarms to lightning
						if (($nextOccurence = $this->read($event['id'], $this->now_su + $alarmData['offset'], false, 'server')))
						{
							$alarmData['time'] = $nextOccurence['start'] - $alarmData['offset'];
							$alarmData['offset'] = false;
						}
						else
						{
							continue;
						}
					}*/

					// for SyncML non-whole-day events always use absolute times
					// (probably because some devices have no clue about timezones)
					// GroupDAV uses offsets, as web UI assumes alarms are relative too
					// (with absolute times GroupDAV clients do NOT move alarms, if events move!)
					if ($this->productManufacturer != 'groupdav' &&
						!empty($event['whole_day']) && $alarmData['offset'])
					{
						$alarmData['offset'] = false;
					}

					$valarm = Horde_Icalendar::newComponent('VALARM',$vevent);
					if ($alarmData['offset'] !== false)
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
					if (!empty($alarmData['uid']))
					{
						$valarm->setAttribute('UID', $alarmData['uid']);
						$valarm->setAttribute('X-WR-ALARMUID', $alarmData['uid']);
					}
					// set evtl. existing attributes set by iCal clients not used by EGroupware
					if (isset($alarmData['attrs']))
					{
						foreach($alarmData['attrs'] as $attr => $data)
						{
							$valarm->setAttribute($attr, $data['value'], $data['params']);
						}
					}
					// set default ACTION and DESCRIPTION, if not set by a client
					if (!isset($alarmData['attrs']) || !isset($alarmData['attrs']['ACTION']))
					{
						$valarm->setAttribute('ACTION','DISPLAY');
					}
					if (!isset($alarmData['attrs']) || !isset($alarmData['attrs']['DESCRIPTION']))
					{
						$valarm->setAttribute('DESCRIPTION',$event['title'] ? $event['title'] : $description);
					}
					$vevent->addComponent($valarm);
				}
			}

			foreach ($attributes as $key => $value)
			{
				foreach (is_array($value) && $parameters[$key]['VALUE']!='DATE' ? $value : array($value) as $valueID => $valueData)
				{
					$valueData = Api\Translation::convert($valueData,Api\Translation::charset(),$charset);
	                $paramData = (array) Api\Translation::convert(is_array($value) ?
	                		$parameters[$key][$valueID] : $parameters[$key],
	                        Api\Translation::charset(),$charset);
	                $valuesData = (array) Api\Translation::convert($values[$key],
	                		Api\Translation::charset(),$charset);
	                $content = $valueData . implode(';', $valuesData);

					if ($version == '1.0' && (preg_match('/[^\x20-\x7F]/', $content) ||
						($paramData['CN'] && preg_match('/[^\x20-\x7F]/', $paramData['CN']))))
					{
						$paramData['CHARSET'] = $charset;
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
									if (preg_match(Api\CalDAV\Handler::REQUIRE_QUOTED_PRINTABLE_ENCODING, $valueData))
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

		// hack to fix iCalendar exporting EXDATE always postfixed with a Z
		// EXDATE can have multiple values and therefore be folded into multiple lines
		return preg_replace_callback("/\nEXDATE;TZID=[^:]+:[0-9TZ \r\n,]+/", function($matches)
			{
				return preg_replace('/([0-9 ])Z/', '$1', $matches[0]);
			}, $retval);
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
			return Api\DateTime::to($time,'ts');
		}
		if (!is_a($time,'DateTime'))
		{
			$time = new Api\DateTime($time,Api\DateTime::$server_timezone);
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
	 * Number of events imported in last call to importVCal
	 *
	 * @var int
	 */
	var $events_imported;

	/**
	 * Import an iCal
	 *
	 * @param string|resource $_vcalData
	 * @param int $cal_id=-1 must be -1 for new entries!
	 * @param string $etag=null if an etag is given, it has to match the current etag or the import will fail
	 * @param boolean $merge=false	merge data with existing entry
	 * @param int $recur_date=0 if set, import the recurrence at this timestamp,
	 *                          default 0 => import whole series (or events, if not recurring)
	 * @param string $principalURL='' Used for CalDAV imports
	 * @param int $user=null account_id of owner, default null
	 * @param string $charset  The encoding charset for $text. Defaults to
	 *                         utf-8 for new format, iso-8859-1 for old format.
	 * @param string $caldav_name=null name from CalDAV client or null (to use default)
	 * @return int|boolean|null cal_id > 0 on success, false on failure or 0 for a failed etag|permission denied or null for "403 Forbidden"
	 */
	function importVCal($_vcalData, $cal_id=-1, $etag=null, $merge=false, $recur_date=0, $principalURL='', $user=null, $charset=null, $caldav_name=null,$skip_notification=false)
	{
		//error_log(__METHOD__."(, $cal_id, $etag, $merge, $recur_date, $principalURL, $user, $charset, $caldav_name)");
		$this->events_imported = 0;
		$replace = $delete_exceptions= false;

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		if (!($events = $this->icaltoegw($_vcalData, $principalURL, $charset)))
		{
			return false;
		}
		if (!is_array($events)) $cal_id = -1;	// just to be sure, as iterator does NOT allow array access (eg. $events[0])

		if ($cal_id > 0)
		{
			if (count($events) == 1)
			{
				$replace = $recur_date == 0;
				$events[0]['id'] = $cal_id;
				if (!is_null($etag)) $events[0]['etag'] = (int) $etag;
				if ($recur_date) $events[0]['recurrence'] = $recur_date;
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
			$events[0]['recur_type'] != MCAL_RECUR_NONE)
		{
			calendar_groupdav::fix_series($events);
		}

		if ($this->tzid)
		{
			$tzid = $this->tzid;
		}
		else
		{
			$tzid = Api\DateTime::$user_timezone->getName();
		}

		date_default_timezone_set($tzid);

		$msg = $master = null;
		foreach ($events as $event)
		{
			if (!is_array($event)) continue; // the iterator may return false

			// Run event through callback
			if($this->event_callback && is_callable($this->event_callback))
			{
				if(!call_user_func_array($this->event_callback, array(&$event)))
				{
					// Callback cancelled event
					continue;
				}
			}
			++$this->events_imported;

			if ($this->so->isWholeDay($event)) $event['whole_day'] = true;
			if (is_array($event['category']))
			{
				$event['category'] = $this->find_or_add_categories($event['category'],
					isset($event['id']) ? $event['id'] : -1);
			}
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					."($cal_id, $etag, $recur_date, $principalURL, $user, $charset)\n"
					. array2string($event)."\n",3,$this->logfile);
			}

			$updated_id = false;

			if ($replace)
			{
				$event_info['type'] = $event['recur_type'] == MCAL_RECUR_NONE ?
					'SINGLE' : 'SERIES-MASTER';
				$event_info['acl_edit'] = $this->check_perms(Acl::EDIT, $cal_id);
				if (($event_info['stored_event'] = $this->read($cal_id, 0, false, 'server')) &&
					$event_info['stored_event']['recur_type'] != MCAL_RECUR_NONE &&
					($event_info['stored_event']['recur_type'] != $event['recur_type']
					|| $event_info['stored_event']['recur_interval'] != $event['recur_interval']
					|| $event_info['stored_event']['recur_data'] != $event['recur_data']
					|| $event_info['stored_event']['start'] != $event['start']))
				{
					// handle the old exceptions
					$recur_exceptions = $this->so->get_related($event_info['stored_event']['uid']);
					foreach ($recur_exceptions as $id)
					{
						if ($delete_exceptions)
						{
							$this->delete($id,0,false,$skip_notification);
						}
						else
						{
							if (!($exception = $this->read($id))) continue;
							$exception['uid'] = Api\CalDAV::generate_uid('calendar', $id);
							$exception['reference'] = $exception['recurrence'] = 0;
							$this->update($exception, true,true,false,true,$msg,$skip_notification);
						}
					}
				}
			}
			else
			{
				$event_info = $this->get_event_info($event);
			}

			// common adjustments for existing events
			if (is_array($event_info['stored_event']))
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
						. "(UPDATE Event)\n"
						. array2string($event_info['stored_event'])."\n",3,$this->logfile);
				}
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
						// Work around problems with Outlook CalDAV Synchronizer (https://caldavsynchronizer.org/)
						// - always sends all participants back with status NEEDS-ACTION --> resets status of all participant, if user has edit rights
						// --> allow only updates with other status then NEEDS-ACTION and therefore allow accepting or denying meeting requests for the user himself
						if ($status[0] === 'X' || calendar_groupdav::get_agent() === 'caldavsynchronizer' && $status[0] === 'U')
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
						// restore resource-quantity from existing event as neither iOS nor Thunderbird returns our X-EGROUPWARE-QUANTITY
						elseif ($uid[0] === 'r' && isset($event_info['stored_event']['participants'][$uid]))
						{
							$quantity = $role = $old_quantity = null;
							calendar_so::split_status($status, $quantity, $role);
							calendar_so::split_status($event_info['stored_event']['participants'][$uid], $old_quantity);
							if ($old_quantity > 1)
							{
								$event['participants'][$uid] = calendar_so::combine_status('U', $old_quantity, $role);
							}
						}
					}
				}
				// unset old X-* attributes stored in custom-fields with exception of our videoconference and notify-externals
				foreach ($event_info['stored_event'] as $key => $value)
				{
					if ($key[0] == '#' && $key[1] == '#' && !isset($event[$key]))
					{
						$event[$key] = in_array($key, ['##videoconference', '##notify_externals']) ?
							$value : '';
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
								continue 2;	// +1 for switch

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
					if(!isset($this->supportedFields['category']))
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
					// since we export now all participants in CalDAV as urn:uuid, if they have no email,
					// we dont need and dont want that special treatment anymore, as it keeps client from changing resources
					elseif ($this->productManufacturer != 'groupdav')
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

					/* Modifying an existing event with timezone different from default timezone of user
					 * to a whole-day event (no timezone allowed according to iCal rfc)
					 * --> code to modify start- and end-time here creates a one day longer event!
					 * Skipping that code, creates the event correct in default timezone of user
 					if (!empty($event['whole_day']) && $event['tzid'] != $event_info['stored_event']['tzid'])
					{
						// Adjust dates to original TZ
						$time = new Api\DateTime($event['start'],Api\DateTime::$server_timezone);
						$time =& $this->so->startOfDay($time, $event_info['stored_event']['tzid']);
						$event['start'] = Api\DateTime::to($time,'server');
						//$time = new Api\DateTime($event['end'],Api\DateTime::$server_timezone);
						//$time =& $this->so->startOfDay($time, $event_info['stored_event']['tzid']);
						//$time->setTime(23, 59, 59);
						$time->modify('+'.round(($event['end']-$event['start'])/DAY_s).' day');
						$event['end'] = Api\DateTime::to($time,'server');
						if ($event['recur_type'] != MCAL_RECUR_NONE)
						{
							foreach ($event['recur_exception'] as $key => $day)
							{
								$time = new Api\DateTime($day,Api\DateTime::$server_timezone);
								$time =& $this->so->startOfDay($time, $event_info['stored_event']['tzid']);
								$event['recur_exception'][$key] = Api\DateTime::to($time,'server');
							}
						}
						elseif ($event['recurrence'])
						{
							$time = new Api\DateTime($event['recurrence'],Api\DateTime::$server_timezone);
							$time =& $this->so->startOfDay($time, $event_info['stored_event']['tzid']);
							$event['recurrence'] = Api\DateTime::to($time,'server');
						}
						error_log(__METHOD__."() TZ adjusted {$event_info['stored_event']['tzid']} --> {$event['tzid']} event=".array2string($event));
					}*/

					calendar_rrule::rrule2tz($event, $event_info['stored_event']['start'],
						$event_info['stored_event']['tzid']);

					$event['tzid'] = $event_info['stored_event']['tzid'];
					// avoid that iCal changes the organizer, which is not allowed
					$event['owner'] = $event_info['stored_event']['owner'];
				}
				$event['caldav_name'] = $event_info['stored_event']['caldav_name'];

				// as we no longer export event owner/ORGANIZER as only participant, we have to re-add owner as participant
				// to not loose him, as EGroupware knows events without owner/ORGANIZER as participant
				if (isset($event_info['stored_event']['participants'][$event['owner']]) && !isset($event['participants'][$event['owner']]))
				{
					$event['participants'][$event['owner']] = $event_info['stored_event']['participants'][$event['owner']];
				}
			}
			else // common adjustments for new events
			{
				unset($event['id']);
				if ($caldav_name) $event['caldav_name'] = $caldav_name;
				// set non blocking all day depending on the user setting
				if (!empty($event['whole_day']) && $this->nonBlockingAllday)
				{
					$event['non_blocking'] = 1;
				}

				if (!is_null($user))
				{
					if ($user > 0 && $this->check_perms(Acl::ADD, 0, $user))
					{
						$event['owner'] = $user;
					}
					elseif ($user > 0)
					{
						date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
						return 0; // no permission
					}
					else
					{
						// add group or resource invitation
						$event['owner'] = $this->user;
						if (!isset($event['participants'][$this->user]))
						{
							$event['participants'][$this->user] = calendar_so::combine_status('A', 1, 'CHAIR');
						}
						// for resources check which new-status to give (eg. with direct booking permision 'A' instead 'U')
						$event['participants'][$user] = calendar_so::combine_status(
							$user < 0 || !isset($this->resources[$user[0]]['new_status']) ? 'U' :
							ExecMethod($this->resources[$user[0]]['new_status'], substr($user, 1)));
					}
				}
				// check if an owner is set and the current user has add rights
				// for that owners calendar; if not set the current user
				elseif (!isset($event['owner'])
					|| !$this->check_perms(Acl::ADD, 0, $event['owner']))
				{
					$event['owner'] = $this->user;
				}

				if (!$event['participants']
					|| !is_array($event['participants'])
					|| !count($event['participants'])
					// for new events, allways add owner as participant. Users expect to participate too, if they invite further participants.
					// They can now only remove themselfs, if that is desired, after storing the event first.
					|| !isset($event['participants'][$event['owner']]))
				{
					$status = calendar_so::combine_status($event['owner'] == $this->user ? 'A' : 'U', 1, 'CHAIR');
					if (!is_array($event['participants'])) $event['participants'] = array();
					$event['participants'][$event['owner']] = $status;
				}
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

			// update alarms depending on the given event type
			if (count($event['alarm']) > 0 || isset($this->supportedFields['alarm']) && isset($event['alarm']))
			{
				switch ($event_info['type'])
				{
					case 'SINGLE':
					case 'SERIES-MASTER':
					case 'SERIES-EXCEPTION':
					case 'SERIES-EXCEPTION-PROPAGATE':
						$this->sync_alarms($event, (array)$event_info['stored_event']['alarm'], $this->user);
						if ($event_info['type'] === 'SERIES-MASTER') $master = $event;
						break;

					case 'SERIES-PSEUDO-EXCEPTION':
						// only sync alarms for pseudo exceptions, if we have a master
						if (!isset($master) && isset($event_info['master_event'])) $master = $event_info['master_event'];
						if ($master) $this->sync_alarms($event, (array)$event_info['stored_event']['alarm'], $this->user, $master);
						break;
				}
			}

			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ . '('
					. $event_info['type'] . ")\n"
					. array2string($event)."\n",3,$this->logfile);
			}

			// Android (any maybe others) delete recurrences by setting STATUS: CANCELLED
			// as we ignore STATUS we have to delete the recurrence by calling delete
			if (in_array($event_info['type'], array('SERIES-EXCEPTION', 'SERIES-EXCEPTION-PROPAGATE', 'SERIES-PSEUDO-EXCEPTION')) &&
				$event['status'] == 'CANCELLED')
			{
				if (!$this->delete($event['id'] ? $event['id'] : $cal_id, $event['recurrence'],false,$skip_notification))
				{
					// delete fails (because no rights), reject recurrence
					$this->set_status($event['id'] ? $event['id'] : $cal_id, $this->user, 'R', $event['recurrence'],false,true,$skip_notification);
				}
				continue;
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
						$event_to_store = $event; // prevent $event from being changed by the update method
						$this->server2usertime($event_to_store);
						$updated_id = $this->update($event_to_store, true,true,false,true,$msg,$skip_notification);
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
						$filter = isset($this->supportedFields['participants']) ? 'map' : 'tz_map';
						$days = $this->so->get_recurrence_exceptions($event_info['stored_event'], $this->tzid, 0, 0, $filter);
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."(EXCEPTIONS MAPPING):\n" .
								array2string($days)."\n",3,$this->logfile);
						}
						if (is_array($days))
						{
							$recur_exceptions = array();

							foreach ($event['recur_exception'] as $recur_exception)
							{
								if (isset($days[$recur_exception]))
								{
									$recur_exceptions[] = $days[$recur_exception];
								}
							}
							$event['recur_exception'] = $recur_exceptions;
						}

						$event_to_store = $event; // prevent $event from being changed by the update method
						$this->server2usertime($event_to_store);
						$updated_id = $this->update($event_to_store, true,true,false,true,$msg,$skip_notification);
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
							if (empty($event['recurrence']))
							{
								// find an existing exception slot
								$occurence = $exception = false;
								foreach ($event_info['master_event']['recur_exception'] as $exception)
								{
									if ($exception > $event['start']) break;
									$occurence = $exception;
								}
								if (!$occurence)
								{
									if (!$exception)
									{
										// use start as dummy recurrence
										$event['recurrence'] = $event['start'];
									}
									else
									{
										$event['recurrence'] = $exception;
									}
								}
								else
								{
									$event['recurrence'] = $occurence;
								}
							}
							else
							{
								$event_info['master_event']['recur_exception'] =
									array_unique(array_merge($event_info['master_event']['recur_exception'],
										array($event['recurrence'])));
							}

							$event['reference'] = $event_info['master_event']['id'];
							$event['category'] = $event_info['master_event']['category'];
							$event['owner'] = $event_info['master_event']['owner'];
							$event_to_store = $event_info['master_event']; // prevent the master_event from being changed by the update method
							$this->server2usertime($event_to_store);
							$this->update($event_to_store, true,true,false,true,$msg,$skip_notification);
							unset($event_to_store);
						}

						$event_to_store = $event; // prevent $event from being changed by update method
						$this->server2usertime($event_to_store);
						$updated_id = $this->update($event_to_store, true,true,false,true,$msg,$skip_notification);
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
							if ($recur_exception != $event['recurrence'])
							{
								$recur_exceptions[] = $recur_exception;
							}
						}
						$event_info['master_event']['recur_exception'] = $recur_exceptions;

						// save the series master with the adjusted exceptions
						$event_to_store = $event_info['master_event']; // prevent the master_event from being changed by the update method
						$this->server2usertime($event_to_store);
						$updated_id = $this->update($event_to_store, true, true, false, false,$msg,$skip_notification);
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
								$this->update_status($event, $event_info['stored_event'],0,$skip_notification);
							}
							elseif (isset($event['participants'][$this->user]) || isset($event_info['stored_event']['participants'][$this->user]))
							{
								// update the users status only
								$this->set_status($event_info['stored_event']['id'], $this->user,
									($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), 0, true,true,$skip_notification);
							}
						}
						break;

					case 'SERIES-PSEUDO-EXCEPTION':
						if (is_array($event_info['master_event'])) // status update requires a stored master event
						{
							$recurrence = $this->date2usertime($event['recurrence']);
							if ($event_info['acl_edit'])
							{
								// update all participants if we have the right to do that
								$this->update_status($event, $event_info['stored_event'], $recurrence,$skip_notification);
							}
							elseif (isset($event['participants'][$this->user]) || isset($event_info['master_event']['participants'][$this->user]))
							{
								// update the users status only
								$this->set_status($event_info['master_event']['id'], $this->user,
									($event['participants'][$this->user] ? $event['participants'][$this->user] : 'R'), $recurrence, true,true,$skip_notification);
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
						// we have to keep the SERIES-PSEUDO-EXCEPTION id and keep the event untouched
						$return_id = $event_info['master_event']['id'] . ':' . $event['recurrence'];
					}
					break;
			}

			// handle ATTACH attribute for managed attachments
			if ($updated_id && Api\CalDAV::handle_attach('calendar', $updated_id, $event['attach'], $event['attach-delete-by-put']) === false)
			{
				$return_id = null;
			}

			if ($this->log)
			{
				$event_info['stored_event'] = $this->read($event_info['stored_event']['id']);
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()[$updated_id]\n" .
					array2string($event_info['stored_event'])."\n",3,$this->logfile);
			}
		}
		date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);

		return $updated_id === 0 ? 0 : $return_id;
	}

	/**
	 * Override parent update function to handle conflict checking callback, if set
	 *
	 * @param array &$event event-array, on return some values might be changed due to set defaults
	 * @param boolean $ignore_conflicts =false just ignore conflicts or do a conflict check and return the conflicting events.
	 *	Set to false if $this->conflict_callback is set
	 * @param boolean $touch_modified =true NOT USED ANYMORE (was only used in old csv-import), modified&modifier is always updated!
	 * @param boolean $ignore_acl =false should we ignore the acl
	 * @param boolean $updateTS =true update the content history of the event
	 * @param array &$messages=null messages about because of missing ACL removed participants or categories
	 * @param boolean $skip_notification =false true: send NO notifications, default false = send them
	 * @return mixed on success: int $cal_id > 0, on error or conflicts false.
	 *	Conflicts are passed to $this->conflict_callback
	 */
	public function update(&$event,$ignore_conflicts=false,$touch_modified=true,$ignore_acl=false,$updateTS=true,&$messages=null, $skip_notification=false)
	{
		if($this->conflict_callback !== null)
		{
			// calendar_ical overrides search(), which breaks conflict checking
			// so we make sure to use the original from parent
			static $bo = null;
			if(!$bo)
			{
				$bo = new calendar_boupdate();
			}
			$conflicts = $bo->conflicts($event);
			if(is_array($conflicts) && count($conflicts) > 0)
			{
				call_user_func_array($this->conflict_callback, array(&$event, &$conflicts));
				return false;
			}
		}
		return parent::update($event, $ignore_conflicts, $touch_modified, $ignore_acl, $updateTS, $messages, $skip_notification);
	}

	/**
	 * Sync alarms of current user: add alarms added on client and remove the ones removed
	 *
	 * Currently alarms of pseudo exceptions will always be added to series master (and searched for there too)!
	 * Alarms are search by uid first, but if no uid match found, we also consider same offset as a match.
	 *
	 * @param array& $event
	 * @param array $old_alarms
	 * @param int $user account_id of user to create alarm for
	 * @param array $master =null master for pseudo exceptions
	 * @return int number of modified alarms
	 */
	public function sync_alarms(array &$event, array $old_alarms, $user, array &$master=null)
	{
		if ($this->debug) error_log(__METHOD__."(".array2string($event).', old_alarms='.array2string($old_alarms).", $user, master=".")");
		$modified = 0;
		foreach($event['alarm'] as &$alarm)
		{
			// check if alarm is already stored or from other users
			$found = false;
			foreach($old_alarms as $id => $old_alarm)
			{
				// not current users alarm --> ignore
				if (!$old_alarm['all'] && $old_alarm['owner'] != $user)
				{
					unset($old_alarm[$id]);
					continue;
				}
				// alarm with matching uid found --> stop
				if (!empty($alarm['uid']) && $alarm['uid'] == $old_alarm['uid'])
				{
					$found = true;
					unset($old_alarms[$id]);
					break;
				}
				// alarm only matching offset, remember first matching one, in case no uid match
				if ($alarm['offset'] == $old_alarm['offset'] && $found === false)
				{
					$found = $id;
				}
			}
			// no uid, but offset match found --> use it like an uid match
			if (!is_bool($found))
			{
				$found = true;
				$old_alarm = $old_alarms[$id];
				unset($old_alarms[$id]);
			}
			if ($this->debug) error_log(__METHOD__."($event[title] (#$event[id]), ..., $user) processing ".($found?'existing':'new')." alarm ".array2string($alarm));
			if (!empty($alarm['attrs']['X-LIC-ERROR']))
			{
				if ($this->debug) error_log(__METHOD__."($event[title] (#$event[id]), ..., $user) ignored X-LIC-ERROR=".array2string($alarm['X-LIC-ERROR']));
				unset($alarm['attrs']['X-LIC-ERROR']);
			}
			// search not found alarm in master
			if ($master && !$found)
			{
				foreach($master['alarm'] ?? [] as $m_alarm)
				{
					if ($alarm['offset'] == $m_alarm['offset'] &&
						($m_alarm['all'] || $m_alarm['owner'] == $user))
					{
						if ($this->debug) error_log(__METHOD__."() alarm of pseudo exception already in master --> ignored alarm=".json_encode($alarm).", master=".json_encode($m_alarm));
						continue 2;
					}
				}
			}
			// alarm not found --> add it (to master as $event['id'] is the one from the master)
			if (!$found)
			{
				$alarm['owner'] = $user;
				if (!isset($alarm['time'])) $alarm['time'] = $event['start'] - $alarm['offset'];
				if ($alarm['time'] < time()) calendar_so::shift_alarm($event, $alarm);
				if ($this->debug) error_log(__METHOD__."() adding new alarm from client ".array2string($alarm));
				if ($event['id'] || $master) $alarm['id'] = $this->save_alarm($event['id'] ?? $master['id'], $alarm);
				// adding alarm to master to be found for further pseudo exceptions (it will be added to DB below)
				if ($master) $master['alarm'][] = $alarm;
				++$modified;
			}
			// existing alarm --> update it
			else
			{
				if (!isset($alarm['time'])) $alarm['time'] = $event['start'] - $alarm['offset'];
				if ($alarm['time'] < time()) calendar_so::shift_alarm($event, $alarm);
				$alarm = array_merge($old_alarm, $alarm);
				if ($this->debug) error_log(__METHOD__."() updating existing alarm from client ".array2string($alarm));
				$alarm['id'] = $this->save_alarm($event['id'], $alarm);
				++$modified;
			}
		}
		// remove all old alarms left from current user
		foreach($old_alarms as $id => $old_alarm)
		{
			// not current users alarm --> ignore
			if (!$old_alarm['all'] && $old_alarm['owner'] != $user)
			{
				unset($old_alarm[$id]);
				continue;
			}
			if ($this->debug) error_log(__METHOD__."() deleting alarm '$id' deleted on client ".array2string($old_alarm));
			$this->delete_alarm($id);
			++$modified;
		}
		return $modified;
	}

	/**
	 * get the value of an attribute by its name
	 *
	 * @param array $components
	 * @param string $name eg. 'DTSTART'
	 * @param string $what ='value'
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

	/**
	 * Parsing a valarm component preserving all attributes unknown to EGw
	 *
	 * @param array &$alarms on return alarms parsed
	 * @param Horde_Icalendar_Valarm $valarm valarm component
	 * @param int $duration in seconds to be able to convert RELATED=END
	 * @return int number of parsed alarms
	 */
	static function valarm2egw(&$alarms, Horde_Icalendar_Valarm $valarm, $duration)
	{
		$alarm = array();
		foreach ($valarm->getAllAttributes() as $vattr)
		{
			switch ($vattr['name'])
			{
				case 'TRIGGER':
					$vtype = (isset($vattr['params']['VALUE']))
						? $vattr['params']['VALUE'] : 'DURATION'; //default type
					switch ($vtype)
					{
						case 'DURATION':
							if (isset($vattr['params']['RELATED']) && $vattr['params']['RELATED'] == 'END')
							{
								$alarm['offset'] = $duration -$vattr['value'];
							}
							elseif (isset($vattr['params']['RELATED']) && $vattr['params']['RELATED'] != 'START')
							{
								error_log("Unsupported VALARM offset anchor ".$vattr['params']['RELATED']);
								return;
							}
							else
							{
								$alarm['offset'] = -$vattr['value'];
							}
							break;
						case 'DATE-TIME':
							$alarm['time'] = $vattr['value'];
							break;
						default:
							error_log('VALARM/TRIGGER: unsupported value type:' . $vtype);
					}
					break;

				case 'UID':
				case 'X-WR-ALARMUID':
					$alarm['uid'] = $vattr['value'];
					break;

				default:	// store all other attributes, so we dont loose them
					$alarm['attrs'][$vattr['name']] = array(
						'params' => $vattr['params'],
						'value'  => $vattr['value'],
					);
			}
		}
		if (isset($alarm['offset']) || isset($alarm['time']))
		{
			//error_log(__METHOD__."(..., ".$valarm->exportvCalendar().", $duration) alarm=".array2string($alarm));
			$alarms[] = $alarm;
			return 1;
		}
		return 0;
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
			/*
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'() ' . array2string($deviceInfo) . "\n",3,$this->logfile);
			}
			*/
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
				switch ($deviceInfo['tzid'])
				{
					case -1:
						$this->tzid = false; // use event's TZ
						break;
					case -2:
						$this->tzid = null; // use UTC for export
						break;
					default:
						$this->tzid = $deviceInfo['tzid'];
				}
			}
			elseif (strpos($this->productName, 'palmos') !== false)
			{
				// for palmos we have to use user-time and NO timezone
				$this->tzid = false;
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
						if ((int)$owner && $this->check_perms(Acl::EDIT, 0, $owner))
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
			'whole_day'			=> 'whole_day',
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
			'recurrence'			=> 'recurrence',
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
			'recurrence'		=> 'recurrence',
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
					case 'e72-1':
					case 'e75-1':
					case 'e66':
					case '6120c':
					case 'nokia 6131':
					case 'n97':
					case 'n97 mini':
					case '5800 xpressmusic':
						$this->supportedFields = $defaultFields['s60'];
						break;
					default:
						if ($this->productName[0] == 'e')
						{
							$model = 'E90';
							$this->supportedFields = $defaultFields['s60'];
						}
						else
						{
							$model = 'E61';
							$this->supportedFields = $defaultFields['minimal'];
						}
						error_log("Unknown Nokia phone '$_productName', assuming same as '$model'");
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
				if ($this->cal_prefs['export_timezone'])
				{
					$this->tzid = $this->cal_prefs['export_timezone'];
				}
				else	// not set or '0' = use event TZ
				{
					$this->tzid = false; // use event's TZ
				}
				$this->supportedFields = $defaultFields['full'];
				break;

			case 'full':
			case 'groupdav':		// all GroupDAV access goes through here
				$this->tzid = false; // use event's TZ
				switch ($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields['full'];
						unset($this->supportedFields['whole_day']);
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
				($this->tzid ? $this->tzid : Api\DateTime::$user_timezone->getName()) .
				', ' . $this->calendarOwner . ")\n" , 3, $this->logfile);
		}

		//Horde::logMessage('setSupportedFields(' . $this->productManufacturer . ', '
		//	. $this->productName .', ' .
		//	($this->tzid ? $this->tzid : Api\DateTime::$user_timezone->getName()) .')',
		//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
	}

	/**
	 * Convert vCalendar data in EGw events
	 *
	 * @param string|resource $_vcalData
	 * @param string $principalURL ='' Used for CalDAV imports
	 * @param string $charset  The encoding charset for $text. Defaults to
	 *                         utf-8 for new format, iso-8859-1 for old format.
	 * @return Iterator|array|boolean Iterator if resource given or array of events on success, false on failure
	 */
	function icaltoegw($_vcalData, $principalURL='', $charset=null)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($principalURL, $charset)\n" .
				array2string($_vcalData)."\n",3,$this->logfile);
		}

		if (!is_array($this->supportedFields)) $this->setSupportedFields();

		// we use Api\CalDAV\IcalIterator only on resources, as calling importVCal() accesses single events like an array (eg. $events[0])
		if (is_resource($_vcalData))
		{
			return new Api\CalDAV\IcalIterator($_vcalData, 'VCALENDAR', $charset,
				// true = add container as last parameter to callback parameters
				array($this, '_ical2egw_callback'), array($this->tzid, $principalURL), true);
		}

		if ($this->tzid)
		{
			$tzid = $this->tzid;
		}
		else
		{
			$tzid = Api\DateTime::$user_timezone->getName();
		}

		date_default_timezone_set($tzid);

		$events = array();
		$vcal = new Horde_Icalendar;
		if ($charset && $charset != 'utf-8')
		{
			$_vcalData = Api\Translation::convert($_vcalData, $charset, 'utf-8');
		}
		if (!$vcal->parsevCalendar($_vcalData, 'VCALENDAR'))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"(): No vCalendar Container found!\n",3,$this->logfile);
			}
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
			return false;
		}
		foreach ($vcal->getComponents() as $component)
		{
			if (($event = $this->_ical2egw_callback($component,$this->tzid,$principalURL,$vcal)))
			{
				$events[] = $event;
			}
		}
		date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);

		return $events;
	}

	/**
	 * Get email of organizer of first vevent in given iCalendar
	 *
	 * @param string $_ical
	 * @return string|boolean
	 */
	public static function getIcalOrganizer($_ical)
	{
		$vcal = new Horde_Icalendar;
		if (!$vcal->parsevCalendar($_ical, 'VCALENDAR'))
		{
			return false;
		}
		if (($vevent = $vcal->findComponentByAttribute('Vevent', 'ORGANIZER')))
		{
			$organizer = $vevent->getAttribute('ORGANIZER');
			if (stripos($organizer, 'mailto:') === 0)
			{
				return substr($organizer, 7);
			}
			$params = $vevent->getAttribute('ORGANIZER', true);
			return $params['email'];
		}
		return false;
	}

	/**
	 * Callback for Api\CalDAV\IcalIterator to convert Horde_iCalendar_Vevent to EGw event array
	 *
	 * @param Horde_iCalendar $component
	 * @param string $tzid timezone
	 * @param string $principalURL ='' Used for CalDAV imports
	 * @param Horde_Icalendar $container =null container to access attributes on container
	 * @return array|boolean event array or false if $component is no Horde_Icalendar_Vevent
	 */
	function _ical2egw_callback(Horde_Icalendar $component, $tzid, $principalURL='', Horde_Icalendar $container=null)
	{
		//unset($component->_container); _debug_array($component);

		if ($this->log)
		{
			error_log(__FILE__ . '[' . __LINE__ . '] ' . __METHOD__ . '() ' . get_class($component) . " found\n", 3, $this->logfile);
		}

		// eg. Mozilla holiday calendars contain only a X-WR-TIMEZONE on vCalendar component
		if (!$tzid && $container && ($tz = $container->getAttributeDefault('X-WR-TIMEZONE')))
		{
			$tzid = $tz;
		}

		if (!is_a($component, 'Horde_Icalendar_Vevent') ||
				!($event = $this->vevent2egw($component, $container ? $container->getAttributeDefault('VERSION', '2.0') : '2.0',
						$this->supportedFields, $principalURL, null, $container)))
		{
			return false;
		}
		//common adjustments
		if ($this->productManufacturer == '' && $this->productName == '' && !empty($event['recur_enddate']))
		{
			// syncevolution needs an adjusted recur_enddate
			$event['recur_enddate'] = (int)$event['recur_enddate'] + 86400;
		}
		if ($event['recur_type'] != MCAL_RECUR_NONE)
		{
			// No reference or RECURRENCE-ID for the series master
			$event['reference'] = $event['recurrence'] = 0;
		}
		if (Api\DateTime::to($event['start'], 'H:i:s') == '00:00:00' && Api\DateTime::to($event['end'], 'H:i:s') == '00:00:00')
		{
			// 'All day' event that ends at midnight the next day, avoid that
			$event['end']--;
		}

		// Remove videoconference link appended to description in calendar_groupdav->iCal()
		if (class_exists('EGroupware\Status\Videoconference\Call'))
		{
			$regex = "/^(\r?\n)?(Videoconference|" . lang('Videoconference') . "):\r?\n" . str_replace('/','\/',EGroupware\Status\Videoconference\Call::getMeetingRegex()) ."(\r?\n)*/im";
			$event['description'] = preg_replace($regex, '', $event['description']);
		}

		// handle the alarms
		$alarms = $event['alarm'];
		foreach ($component->getComponents() as $valarm)
		{
			if (is_a($valarm, 'Horde_Icalendar_Valarm'))
			{
				self::valarm2egw($alarms, $valarm, $event['end'] - $event['start']);
			}
		}
		$event['alarm'] = $alarms;
		if ($tzid || empty($event['tzid']))
		{
			$event['tzid'] = $tzid;
		}
		return $event;
	}

	/**
	 * Parse a VEVENT
	 *
	 * @param array $component			VEVENT
	 * @param string $version			vCal version (1.0/2.0)
	 * @param array $supportedFields	supported fields of the device
	 * @param string $principalURL =''	Used for CalDAV imports, no longer used in favor of Api\CalDAV\Principals::url2uid()
	 * @param string $check_component ='Horde_Icalendar_Vevent'
	 * @param Horde_Icalendar $container =null container to access attributes on container
	 * @return array|boolean			event on success, false on failure
	 */
	function vevent2egw($component, $version, $supportedFields, $principalURL='', $check_component='Horde_Icalendar_Vevent', Horde_Icalendar $container=null)
	{
		unset($principalURL);	// not longer used, but required in function signature

		if ($check_component && !is_a($component, $check_component))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.'()' .
					get_class($component)." found\n",3,$this->logfile);
			}
			return false;
		}

		if (!empty($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$isDate = false;
		$event		= array();
		$alarms		= array();
		$organizer_status = $organizer_uid = null;
		$vcardData	= array(
			'recur_type'		=> MCAL_RECUR_NONE,
			'recur_exception'	=> array(),
			'priority'          => 0,	// iCalendar default is 0=undefined, not EGroupware 5=normal
			'public'            => 1,
		);
		// we need to parse DTSTART, DTEND or DURATION (in that order!) first
		foreach (array_merge(
			$component->getAllAttributes('DTSTART'),
			$component->getAllAttributes('DTEND'),
			$component->getAllAttributes('DURATION')) as $attributes)
		{
			//error_log(__METHOD__."() attribute=".array2string($attributes));
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

					// set event timezone from dtstart, if specified there
					if (!empty($attributes['params']['TZID']))
					{
						// import TZID, if PHP understands it (we only care about TZID of starttime,
						// as we store only a TZID for the whole event)
						try
						{
							$tz = calendar_timezones::DateTimeZone($attributes['params']['TZID']);
							// sometimes we do not get an Api\DateTime object but no exception is thrown
							// may be php 5.2.x related. occurs when a NokiaE72 tries to open Outlook invitations
							if ($tz instanceof DateTimeZone)
							{
								$event['tzid'] = $tz->getName();
							}
							else
							{
								error_log(__METHOD__ . '() unknown TZID='
									. $attributes['params']['TZID'] . ', defaulting to timezone "'
									. date_default_timezone_get() . '".'.array2string($tz));
								$event['tzid'] = date_default_timezone_get();	// default to current timezone
							}
						}
						catch(Exception $e)
						{
							error_log(__METHOD__ . '() unknown TZID='
								. $attributes['params']['TZID'] . ', defaulting to timezone "'
								. date_default_timezone_get() . '".'.$e->getMessage());
							$event['tzid'] = date_default_timezone_get();	// default to current timezone
						}
					}
					// if no timezone given and one is specified in class (never the case for CalDAV)
					elseif ($this->tzid)
					{
						$event['tzid'] = $this->tzid;
					}
					// Horde seems not to distinguish between an explicit UTC time postfixed with Z and one without
					// assuming for now UTC to pass CalDAVTester tests
					// ToDo: fix Horde_Icalendar to return UTC for timestamp postfixed with Z
					elseif (!$isDate)
					{
						$event['tzid'] = 'UTC';
					}
					// default to use timezone to better kope with floating time
					else
					{
						$event['tzid'] = Api\DateTime::$user_timezone->getName();
					}
					break;

				case 'DTEND':
					$dtend_ts = is_numeric($attributes['value']) ? $attributes['value'] : $this->date2ts($attributes['value']);
					if (date('H:i:s',$dtend_ts) == '00:00:00')
					{
						$dtend_ts -= 1;
					}
					$vcardData['end']	= $dtend_ts;
					break;

				case 'DURATION':	// clients can use DTSTART+DURATION, instead of DTSTART+DTEND
					if (!isset($vcardData['end']))
					{
						$vcardData['end'] = $vcardData['start'] + $attributes['value'];
					}
					else
					{
						error_log(__METHOD__."() find DTEND AND DURATION --> ignoring DURATION");
					}
					break;
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
		// if neither duration nor dtend specified, default for dtstart as date is 1 day
		if (!isset($vcardData['end']) && !$isDate)
		{
			$end = new Api\DateTime($vcardData['start']);
			$end->add('1 day');
			$vcardData['end'] = $end->format('ts');
		}
		// lets see what we can get from the vcard
		foreach ($component->getAllAttributes() as $attributes)
		{
			switch ($attributes['name'])
			{
				case 'X-MICROSOFT-CDO-ALLDAYEVENT':
					if (isset($supportedFields['whole_day']))
					{
						$event['whole_day'] = (isset($attributes['value'])?strtoupper($attributes['value'])=='TRUE':true);
					}
					break;
				case 'AALARM':
				case 'DALARM':
					$alarmTime = $attributes['value'];
					$alarms[$alarmTime] = array(
						'time' => $alarmTime
					);
					break;
				case 'CLASS':
					$vcardData['public'] = (int)(strtolower($attributes['value']) == 'public');
					break;
				case 'DESCRIPTION':
					$vcardData['description'] = str_replace("\r\n", "\n", $attributes['value']);
					$matches = null;
					if (preg_match('/\s*\[UID:(.+)?\]/Usm', $attributes['value'], $matches))
					{
						if (!isset($vcardData['uid'])
								&& strlen($matches[1]) >= $minimum_uid_length)
						{
							$vcardData['uid'] = $matches[1];
						}
					}
					break;
				case 'RECURRENCE-ID':
				case 'X-RECURRENCE-ID':
					if (is_array($attributes['value'])) // whole-day event recurrence-id is returned as array
					{
						$attributes['value'] = mktime(0, 0, 0,
							$attributes['value']['month'], $attributes['value']['mday'], $attributes['value']['year']);
					}
					$vcardData['recurrence'] = $attributes['value'];
					break;
				case 'LOCATION':
					$vcardData['location']	= str_replace("\r\n", "\n", $attributes['value']);
					break;
				case 'RRULE':
					unset($vcardData['recur_type']);	// it wont be set by +=
					$vcardData += calendar_rrule::parseRrule($attributes['value'], false, $vcardData);
					if (!empty($vcardData['recur_enddate'])) self::check_fix_endate ($vcardData);
					break;
				case 'EXDATE':	// current Horde_Icalendar returns dates, no timestamps
					if ($attributes['values'])
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
					break;
				case 'SUMMARY':
					$vcardData['title'] = str_replace("\r\n", "\n", $attributes['value']);
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
				case 'ORGANIZER':
					$event['organizer'] = $attributes['value'];	// no egw field, but needed in AS
					if (strtolower(substr($event['organizer'],0,7)) == 'mailto:')
					{
						$event['organizer'] = substr($event['organizer'],7);
					}
					if (!empty($attributes['params']['CN']))
					{
						$event['organizer'] = $attributes['params']['CN'].' <'.$event['organizer'].'>';
					}
					// fall throught
				case 'ATTENDEE':
					// work around Ligthning sending @ as %40
					$attributes['value'] = str_replace('%40', '@', $attributes['value']);
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
					// CN explicit given --> use it
					if (strtolower(substr($attributes['value'], 0, 7)) == 'mailto:' &&
						!empty($attributes['params']['CN']))
					{
						$email = substr($attributes['value'], 7);
						$cn = $attributes['params']['CN'];
					}
					// try parsing email and cn from attendee
					elseif (preg_match('/mailto:([@.a-z0-9_-]+)|mailto:"?([.a-z0-9_ -]*)"?[ ]*<([@.a-z0-9_-]*)>/i',
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
					// check if we need to replace email domain with something else in participants (mail address migration)
					if (!empty($email) && !empty($GLOBALS['egw_info']['server']['calendar_domain_replace']) &&
						!empty($GLOBALS['egw_info']['server']['calendar_domain_replace_with']))
					{
						$email = preg_replace('/@'.preg_quote($GLOBALS['egw_info']['server']['calendar_domain_replace'], '/').'(>|$)/i',
							'@'.$GLOBALS['egw_info']['server']['calendar_domain_replace_with'].'$1', $e=$email);
					}
					// try X-EGROUPWARE-UID, but only if it resolves to same email (otherwise we are in trouble if different EGw installs talk to each other)
					if (!$uid && !empty($attributes['params']['X-EGROUPWARE-UID']) &&
						($res_info = $this->resource_info($attributes['params']['X-EGROUPWARE-UID'])) &&
						!strcasecmp($res_info['email'], $email))
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
					// check principal url from CalDAV here after X-EGROUPWARE-UID and to get optional X-EGROUPWARE-QUANTITY
					if (!$uid) $uid = Api\CalDAV\Principals::url2uid($attributes['value'], null, $cn);

					// try to find an email address
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
							$cn = str_replace(array('\\,', '\\;', '\\:', '\\\\'),
										array(',', ';', ':', '\\'),
										$attributes['params']['CN']);
							if ($cn[0] == '"' && substr($cn,-1) == '"')
							{
								$cn = substr($cn,1,-1);
							}
							// not searching for $cn, as match can be not unique or without an email address
							// --> notification will fail, better store just as email
						}

						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. "() Search participant: '$cn', '$email'\n",3,$this->logfile);
						}

						//elseif (//$attributes['params']['CUTYPE'] == 'GROUP'
						if (preg_match('/(.*) '. lang('Group') . '/', $cn, $matches))
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
									if (($members = $GLOBALS['egw']->accounts->members($uid, true)) &&
										in_array($this->user, $members))
									{
										//Horde::logMessage("vevent2egw: set status to " . $status,
										//		__FILE__, __LINE__, PEAR_LOG_DEBUG);
										$vcardData['participants'][$this->user] =
											calendar_so::combine_status($status,$quantity,$role);
									}
								}
								$status = 'U'; // keep the group
							}
							else continue 2; // can't find this group
						}
						elseif (empty($searcharray))
						{
							continue 2;	// participants without email AND CN --> ignore it
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
							$uid = 'e'. ($cn ? $cn . ' <' . $email . '>' : $email);
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
							if (!isset($attributes['params']['ROLE']) &&
								isset($event['owner']) && $event['owner'] == $uid)
							{
								$attributes['params']['ROLE'] = 'CHAIR';
							}
							if (!isset($vcardData['participants'][$uid]) ||
									$vcardData['participants'][$uid][0] != 'A')
							{
								// keep role 'CHAIR' from an external organizer, even if he is a regular participant with a different role
								// as this is currently the only way to store an external organizer and send him iMip responses
								$q = $r = null;
								if (isset($vcardData['participants'][$uid]) && ($s=$vcardData['participants'][$uid]) &&
									calendar_so::split_status($s, $q, $r) && $r == 'CHAIR')
								{
									$role = 'CHAIR';
								}
								// for multiple entries the ACCEPT wins
								// add quantity and role
								$vcardData['participants'][$uid] =
									calendar_so::combine_status(
										// Thunderbird: if there is a PARTICIPANT for the ORGANIZER AND ORGANZIER has PARTSTAT
										// --> use the one from ORGANIZER
										$uid === $organizer_uid && !empty($organizer_status) && $organizer_status !== 'X' ?
										$organizer_status : $status, $quantity, $role);

								try {
									if (!$this->calendarOwner && is_numeric($uid) && $role == 'CHAIR')
										$component->getAttribute('ORGANIZER');
								}
								catch(Horde_Icalendar_Exception $e)
								{
									// we can store the ORGANIZER as event owner
									$event['owner'] = $uid;
								}
							}
							break;

						case 'ORGANIZER':
							// remember evtl. set PARTSTAT from ORGANIZER, as TB sets it on ORGANIZER not PARTICIPANT!
							$organizer_uid = $uid;
							$organizer_status = $status;
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
					break;
				case 'STATUS':	// currently no EGroupware event column, but needed as Android uses it to delete single recurrences
					$event['status'] = $attributes['value'];
					break;

				case 'X-EGROUPWARE-NOTIFY_EXTERNALS':
					$event['##notify_externals'] = $attributes['value'];
					break;
				case 'X-EGROUPWARE-VIDEOCONFERENCE':
					$event['##videoconference'] = $attributes['value'];
					break;

				// ignore all PROPS, we dont want to store like X-properties or unsupported props
				case 'DTSTAMP':
				case 'SEQUENCE':
				case 'CREATED':
				case 'LAST-MODIFIED':
				case 'DTSTART':
				case 'DTEND':
				case 'DURATION':
				case 'X-LIC-ERROR':	// parse errors from libical, makes no sense to store them
					break;

				case 'ATTACH':
					if ($attributes['params'] && !empty($attributes['params']['FMTTYPE'])) break;	// handeled by managed attachment code
					// fall throught to store external attachment url
				default:	// X- attribute or other by EGroupware unsupported property
					//error_log(__METHOD__."() $attributes[name] = ".array2string($attributes));
					// for attributes with multiple values in multiple lines, merge the values
					if (isset($event['##'.$attributes['name']]))
					{
						//error_log(__METHOD__."() taskData['##$attribute[name]'] = ".array2string($taskData['##'.$attribute['name']]));
						$attributes['values'] = array_merge(
							is_array($event['##'.$attributes['name']]) ? $event['##'.$attributes['name']]['values'] : (array)$event['##'.$attributes['name']],
							$attributes['values']);
					}
					$event['##'.$attributes['name']] = $attributes['params'] || count($attributes['values']) > 1 ?
						json_encode($attributes) : $attributes['value'];

					// check if json_encoded attribute is to big for our table
					if (($attributes['params'] || count($attributes['values']) > 1) &&
						strlen($event['##'.$attributes['name']]) >
							$GLOBALS['egw']->db->get_column_attribute('cal_extra_value', 'egw_cal_extra', 'calendar', 'precision'))
					{
						// store content compressed (Outlook/Exchange HTML garbadge is very good compressable)
						if (function_exists('gzcompress'))
						{
							$event['##'.$attributes['name']] = json_encode(array(
								'gzcompress' => base64_encode(gzcompress($event['##'.$attributes['name']]))
							));
						}
						// if that's not enough --> unset it, as truncating the json gives nothing
						if (strlen($event['##'.$attributes['name']]) >
							$GLOBALS['egw']->db->get_column_attribute('cal_extra_value', 'egw_cal_extra', 'calendar', 'precision'))
						{
							unset($event['##'.$attributes['name']]);
						}
					}
					break;
			}
		}
		// check if the entry is a birthday
		// this field is only set from NOKIA clients
		try {
			$agendaEntryType = $component->getAttribute('X-EPOCAGENDAENTRYTYPE');
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
			}
		}
		catch (Horde_Icalendar_Exception $e) {}

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
				case 'recur_count':
				case 'whole_day':
					// not handled here
					break;

				case 'recur_type':
					$event['recur_type'] = $vcardData['recur_type'];
					if ($event['recur_type'] != MCAL_RECUR_NONE)
					{
						$event['reference'] = 0;
						foreach (array('recur_interval','recur_enddate','recur_data','recur_exception','recur_count') as $r)
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
		if ($event['recur_enddate'])
		{
			// reset recure_enddate to 00:00:00 on the last day
			$rriter = calendar_rrule::event2rrule($event, false);
			$last = $rriter->normalize_enddate();
			if(!is_object($last))
			{
				if($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
					" Unable to determine recurrence end date.  \n".array2string($event),3, $this->logfile);
				}
				return false;
			}
			$last->setTime(0, 0, 0);
			//error_log(__METHOD__."() rrule=$recurence --> ".array2string($rriter)." --> enddate=".array2string($last).'='.Api\DateTime::to($last, ''));
			$event['recur_enddate'] = Api\DateTime::to($last, 'server');
		}
		// translate COUNT into an enddate, as we only store enddates
		elseif($event['recur_count'])
		{
			$rriter = calendar_rrule::event2rrule($event, false);
			$last = $rriter->count2date($event['recur_count']);
			if(!is_object($last))
			{
				if($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__,
					" Unable to determine recurrence end date.  \n".array2string($event),3, $this->logfile);
				}
				return false;
			}
			$last->setTime(0, 0, 0);
			$event['recur_enddate'] = Api\DateTime::to($last, 'server');
			unset($event['recur_count']);
		}

		// Apple iCal on OS X uses X-CALENDARSERVER-ACCESS: CONFIDENTIAL on VCALANDAR (not VEVENT!)
		try {
			if ($this->productManufacturer == 'groupdav' && $container &&
				($x_calendarserver_access = $container->getAttribute('X-CALENDARSERVER-ACCESS')))
			{
				$event['public'] =  (int)(strtoupper($x_calendarserver_access) == 'PUBLIC');
			}
			//error_log(__METHOD__."() X-CALENDARSERVER-ACCESS=".array2string($x_calendarserver_access).' --> public='.array2string($event['public']));
		}
		catch (Horde_Icalendar_Exception $e) {}

		// if no end is given in iCal we use the default lenght from user prefs
		// whole day events get one day in calendar_boupdate::save()
		if (!isset($event['end']))
		{
			$event['end'] = $event['start'] + 60 * $this->cal_prefs['defaultlength'];
		}

		if ($this->calendarOwner) $event['owner'] = $this->calendarOwner;

		// parsing ATTACH attributes for managed attachments
		$event['attach-delete-by-put'] = $component->getAttributeDefault('X-EGROUPWARE-ATTACH-INCLUDED', null) === 'TRUE';
		$event['attach'] = $component->getAllAttributes('ATTACH');

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($event)."\n",3,$this->logfile);
		}
		//Horde::logMessage("vevent2egw:\n" . print_r($event, true),
	    //    	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $event;
	}

	function iCalSearch($_vcalData, $contentID=null, $relax=false, $charset=null)
	{
		if (($events = $this->icaltoegw($_vcalData, $charset)))
		{
			// this function only supports searching a single event
			if (count($events) == 1)
			{
				$filter = $relax ? 'relax' : 'check';
				$event = array_shift($events);
				$eventId = -1;
				if ($this->so->isWholeDay($event)) $event['whole_day'] = true;
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
	 * iCal defines enddate to be a time and eg. Apple sends 1s less then next recurance, if they split events
	 *
	 * We need to fix that situation by moving end one day back.
	 *
	 * @param array& $vcardData values for keys "start" and "recur_enddate", later get's modified if neccessary
	 */
	static function check_fix_endate(array &$vcardData)
	{
		$end = new Api\DateTime($vcardData['recur_enddate']);
		$start = new Api\DateTime($vcardData['start']);
		$start->setDate($end->format('Y'), $end->format('m'), $end->format('d'));

		if ($end->format('ts') < $start->format('ts'))
		{
			$end->modify('-1day');
			$vcardData['recur_enddate'] = $end->format('ts');
			//error_log(__METHOD__."($vcardData[event_title]) fix recure_enddate to ".$end->format('Y-m-d H:i:s'));
		}
	}

	/**
	 * Create a freebusy vCal for the given user(s)
	 *
	 * @param int $user account_id
	 * @param mixed $end =null end-date, default now+1 month
	 * @param boolean $utc =true if false, use severtime for dates
	 * @param string $charset ='UTF-8' encoding of the vcalendar, default UTF-8
	 * @param mixed $start =null default now
	 * @param string $method ='PUBLISH' or eg. 'REPLY'
	 * @param array $extra =null extra attributes to add
	 * 	X-CALENDARSERVER-MASK-UID can be used to not include an event specified by this uid as busy
	 */
	function freebusy($user,$end=null,$utc=true, $charset='UTF-8', $start=null, $method='PUBLISH', array $extra=null)
	{
		if (!$start) $start = time();	// default now
		if (!$end) $end = time() + 100*DAY_s;	// default next 100 days

		$vcal = new Horde_Icalendar;
		$vcal->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware Calendar '.$GLOBALS['egw_info']['apps']['calendar']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));
		$vcal->setAttribute('VERSION','2.0');
		$vcal->setAttribute('METHOD',$method);

		$vfreebusy = Horde_Icalendar::newComponent('VFREEBUSY',$vcal);

		$attributes = array(
			'DTSTAMP' => time(),
			'DTSTART' => $this->date2ts($start,true),	// true = server-time
			'DTEND' => $this->date2ts($end,true),	// true = server-time
		);
		if (!$utc)
		{
			foreach ($attributes as $attr => $value)
			{
				$attributes[$attr] = date('Ymd\THis', $value);
			}
		}
		if (is_null($extra)) $extra = array(
			'URL' => $this->freebusy_url($user),
			'ORGANIZER' => 'mailto:'.$GLOBALS['egw']->accounts->id2name($user,'account_email'),
		);
		foreach($attributes+$extra as $attr => $value)
		{
			$vfreebusy->setAttribute($attr, $value);
		}
		$events = parent::search(array(
			'start' => $start,
			'end'   => $end,
			'users' => $user,
			'date_format' => 'server',
			'show_rejected' => false,
		));
		if (is_array($events))
		{
			$fbdata = array();

			foreach ($events as $event)
			{
				if ($event['non_blocking']) continue;
				if ($event['uid'] === $extra['X-CALENDARSERVER-MASK-UID']) continue;
				$status = $event['participants'][$user];
				$quantity = $role = null;
				calendar_so::split_status($status, $quantity, $role);
				if ($status == 'R' || $role == 'NON-PARTICIPANT') continue;

				$fbtype = $status == 'T' ? 'BUSY-TENTATIVE' : 'BUSY';

				// hack to fix end-time to be non-inclusive
				// all-day events end in our data-model at 23:59:59 (of given TZ)
				if (date('is', $event['end']) == '5959') ++$event['end'];

				$fbdata[$fbtype][] = $event;
			}
			foreach($fbdata as $fbtype => $events)
			{
				foreach($this->aggregate_periods($events, $start, $end) as $event)
				{
					if ($utc)
					{
						$vfreebusy->setAttribute('FREEBUSY',array(array(
							'start' => $event['start'],
							'end' => $event['end'],
						)), array('FBTYPE' => $fbtype));
					}
					else
					{
						$vfreebusy->setAttribute('FREEBUSY',array(array(
							'start' => date('Ymd\THis',$event['start']),
							'end' => date('Ymd\THis',$event['end']),
						)), array('FBTYPE' => $fbtype));
					}
				}
			}
		}
		$vcal->addComponent($vfreebusy);

		return $vcal->exportvCalendar($charset);
	}

	/**
	 * Aggregate multiple, possibly overlapping events cliped by $start and $end
	 *
	 * @param array $events array with values for keys "start" and "end"
	 * @param int $start
	 * @param int $end
	 * @return array of array with values for keys "start" and "end"
	 */
	public function aggregate_periods(array $events, $start, $end)
	{
		// sort by start datetime
		uasort($events, function($a, $b)
		{
			$diff = $a['start'] - $b['start'];

			return $diff == 0 ? 0 : ($diff < 0 ? -1 : 1);
		});

		$fbdata = array();
		foreach($events as $event)
		{
			error_log(__METHOD__."(..., $start, $end) event[start]=$event[start], event[end]=$event[end], fbdata=".array2string($fbdata));
			if ($event['end'] <= $start || $event['start'] >= $end) continue;

			if (!$fbdata)
			{
				$fbdata[] = array(
					'start' => $event['start'] < $start ? $start : $event['start'],
					'end' => $event['end'],
				);
				continue;
			}
			$last =& $fbdata[count($fbdata)-1];

			if ($last['end'] >= $event['start'])
			{
				if ($last['end'] < $event['end'])
				{
					$last['end'] = $event['end'];
				}
			}
			else
			{
				$fbdata[] = array(
					'start' => $event['start'],
					'end' => $event['end'],
				);
			}
		}
		$last =& $fbdata[count($fbdata)-1];

		if ($last['end'] > $end) $last['end'] = $end;

		error_log(__METHOD__."(..., $start, $end) returning ".array2string($fbdata));
		return $fbdata;
	}
}
