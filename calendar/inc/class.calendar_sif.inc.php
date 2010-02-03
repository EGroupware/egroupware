<?php
/**
 * eGroupWare calendar - SIF Parser for SyncML
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage export
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

/**
 * SIF Parser for SyncML
 */
class calendar_sif extends calendar_boupdate
{
	var $sifMapping = array(
		'Start'				=> 'start',
		'End'				=> 'end',
		'AllDayEvent'		=> 'alldayevent',
		'Attendees'			=> '',
		'BillingInformation'		=> '',
		'Body'				=> 'description',
		'BusyStatus'		=> '',
		'Categories'		=> 'category',
		'Companies'			=> '',
		'Importance'		=> 'priority',
		'IsRecurring'		=> 'isrecurring',
		'Location'			=> 'location',
		'MeetingStatus'		=> '',
		'Mileage'			=> '',
		'ReminderMinutesBeforeStart'	=> 'reminderstart',
		'ReminderSet'		=> 'reminderset',
		'ReminderSoundFile'	=> '',
		'ReminderOptions'	=> '',
		'ReminderInterval'	=> '',
		'ReminderRepeatCount'		=> '',
		'Exceptions'		=> '',
		'ReplyTime'			=> '',
		'Sensitivity'		=> 'public',
		'Subject'			=> 'title',
		'RecurrenceType'	=> 'recur_type',
		'Interval'			=> 'recur_interval',
		'MonthOfYear'		=> '',
		'DayOfMonth'		=> '',
		'DayOfWeekMask'		=> 'recur_weekmask',
		'Instance'			=> '',
		'PatternStartDate'	=> '',
		'NoEndDate'			=> 'recur_noenddate',
		'PatternEndDate'	=> 'recur_enddate',
		'Occurrences'		=> '',
	);

	/**
	 *  the calendar event array for the XML Parser
	 */
	var $event;

	/**
	 * name and sorftware version of the Funambol client
	 *
	 * @var string
	 */
	var $productName = 'mozilla plugin';
	var $productSoftwareVersion = '0.3';

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
	var $logfile="/tmp/log-sifcal";


	// constants for recurence type
	const olRecursDaily		= 0;
	const olRecursWeekly	= 1;
	const olRecursMonthly	= 2;
	const olRecursMonthNth	= 3;
	const olRecursYearly	= 5;
	const olRecursYearNth	= 6;

	// constants for weekdays
	const olSunday		= 1;
	const olMonday		= 2;
	const olTuesday 	= 4;
	const olWednesday	= 8;
	const olThursday	= 16;
	const olFriday		= 32;
	const olSaturday	= 64;

	// standard headers
	const xml_decl = '<?xml version="1.0" encoding="UTF-8"?>';
	const SIF_decl = '<SIFVersion>1.1</SIFVersion>';


	/**
	 * Constructor
	 *
	 * @param array $_clientProperties		client properties
	 */
	function __construct(&$_clientProperties = array())
	{
		parent::__construct();
		if ($this->log) $this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-sifcal";
		$this->clientProperties = $_clientProperties;
		$this->vCalendar = new Horde_iCalendar;
	}


	function startElement($_parser, $_tag, $_attributes)
	{
	}

	function endElement($_parser, $_tag)
	{
		switch (strtolower($_tag))
		{
			case 'excludedate':
				$this->event['recur_exception'][] = trim($this->sifData);
				break;

			default:
				if(!empty($this->sifMapping[$_tag]))
				{
					$this->event[$this->sifMapping[$_tag]] = trim($this->sifData);
				}
		}
		unset($this->sifData);
	}

	function characterData($_parser, $_data)
	{
		$this->sifData .= $_data;
	}

	/**
	 * Get DateTime value for a given time and timezone
	 *
	 * @param int|string|DateTime $time in server-time as returned by calendar_bo for $data_format='server'
	 * @param string $tzid TZID of event or 'UTC' or NULL for palmos timestamps in usertime
	 * @return mixed attribute value to set: integer timestamp if $tzid == 'UTC' otherwise Ymd\THis string IN $tzid
	 */
	function getDateTime($time, $tzid)
	{
		if (empty($tzid) || $tzid == 'UTC')
		{
			return $this->vCalendar->_exportDateTime(egw_time::to($time,'ts'));
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

		return $this->vCalendar->_exportDateTime($time->format('Ymd\THis'));
	}

	function siftoegw($sifData, $_calID=-1)
	{
		$finalEvent	= array();
		$this->event = array();
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

		if ($this->tzid)
		{
			// enforce device settings
			date_default_timezone_set($this->tzid);
			$finalEvent['tzid'] = $this->tzid;
		}


		$this->xml_parser = xml_parser_create('UTF-8');
		xml_set_object($this->xml_parser, $this);
		xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($this->xml_parser, "startElement", "endElement");
		xml_set_character_data_handler($this->xml_parser, "characterData");
		$this->strXmlData = xml_parse($this->xml_parser, $sifData);
		if (!$this->strXmlData)
		{
			error_log(sprintf("XML error: %s at line %d",
				xml_error_string(xml_get_error_code($this->xml_parser)),
				xml_get_current_line_number($this->xml_parser)));
			if ($this->tzid)
			{
				date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
			}
			return false;
		}

		foreach ($this->event as $key => $value)
		{
			$value = preg_replace('/<\!\[CDATA\[(.+)\]\]>/Usim', '$1', $value);
			$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);
			/*
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"() $key => $value\n",3,$this->logfile);
			}
			*/
			switch ($key)
			{
				case 'alldayevent':
					if ($value == 1)
					{
						$finalEvent['whole_day'] = true;
						$startParts = explode('-',$this->event['start']);
						$finalEvent['startdate']['hour'] = $finalEvent['startdate']['minute'] = $finalEvent['startdate']['second'] = 0;
						$finalEvent['startdate']['year'] = $startParts[0];
						$finalEvent['startdate']['month'] = $startParts[1];
						$finalEvent['startdate']['day'] = $startParts[2];
						$finalEvent['start'] = $this->date2ts($finalEvent['startdate']);
						$endParts = explode('-',$this->event['end']);
						$finalEvent['enddate']['hour'] = 23; $finalEvent['enddate']['minute'] = $finalEvent['enddate']['second'] = 59;
						$finalEvent['enddate']['year'] = $endParts[0];
						$finalEvent['enddate']['month'] = $endParts[1];
						$finalEvent['enddate']['day'] = $endParts[2];
						$finalEvent['end'] = $this->date2ts($finalEvent['enddate']);
					}
					break;

				case 'public':
					$finalEvent[$key] = ((int)$value > 0) ? 0 : 1;
					break;

				case 'category':
					if (!empty($value))
					{
						$categories1 = explode(',', $value);
						$categories2 = explode(';', $value);
						$categories = count($categories1) > count($categories2) ? $categories1 : $categories2;
						$finalEvent[$key] = $this->find_or_add_categories($categories, $_calID);
					}
					break;

				case 'end':
				case 'start':
					if ($this->event['alldayevent'] < 1)
					{
						$finalEvent[$key] = $this->vCalendar->_parseDateTime($value);
					}
					break;

				case 'isrecurring':
					if ($value == 1)
					{
						$finalEvent['recur_exception'] = array();
						if (is_array($this->event['recur_exception']))
						{
							foreach ($this->event['recur_exception'] as $day)
							{
								$finalEvent['recur_exception'][] = $this->vCalendar->_parseDateTime($day);
							}
							array_unique($finalEvent['recur_exception']);
						}
						$finalEvent['recur_interval'] = $this->event['recur_interval'];
						$finalEvent['recur_data'] = 0;
						if ($this->event['recur_noenddate'] == 0)
						{
							$recur_enddate = $this->vCalendar->_parseDateTime($this->event['recur_enddate']);
							$finalEvent['recur_enddate'] = mktime(
									date('H', 23),
									date('i', 59),
									date('s', 59),
									date('m', $recur_enddate),
									date('d', $recur_enddate),
									date('Y', $recur_enddate));
						}
						switch ($this->event['recur_type'])
						{
							case self::olRecursDaily:
								$finalEvent['recur_type']	= MCAL_RECUR_DAILY;
								break;

							case self::olRecursWeekly:
								$finalEvent['recur_type']	= MCAL_RECUR_WEEKLY;
								$finalEvent['recur_data']	= $this->event['recur_weekmask'];
								break;

							case self::olRecursMonthly:
								$finalEvent['recur_type']	= MCAL_RECUR_MONTHLY_MDAY;
								break;

							case self::olRecursMonthNth:
								$finalEvent['recur_type']	= MCAL_RECUR_MONTHLY_WDAY;
								break;

							case self::olRecursYearly:
								$finalEvent['recur_type']	= MCAL_RECUR_YEARLY;
								$finalEvent['recur_interval'] = 1;
								break;
						}
					}
					break;

				case 'priority':
					$finalEvent[$key] = $value + 1;
					break;

				case 'reminderset':
					if ($value == 1)
					{
						$finalEvent['alarm'] = $this->event['reminderstart'];
					}
					break;

				case 'recur_type':
				case 'recur_enddate':
				case 'recur_interval':
				case 'recur_weekmask':
				case 'reminderstart':
				case 'recur_exception':
					// do nothing, get's handled in isrecuring clause
					break;

				case 'description':
					if (preg_match('/\s*\[UID:(.+)?\]/Usm', $value, $matches))
					{
						$finalEvent['uid'] = $matches[1];
					}

				default:
					$finalEvent[$key] = $value;
					break;
			}
		}

		if ($this->calendarOwner) $finalEvent['owner'] = $this->calendarOwner;

		if ($this->tzid)
		{
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
		}

		if ($_calID > 0) $finalEvent['id'] = $_calID;

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($finalEvent)."\n",3,$this->logfile);
		}

		return $finalEvent;
	}

	function search($_sifdata, $contentID=null, $relax=false)
	{
		$result = array();
		$filter = $relax ? 'relax' : 'exact';

		if ($event = $this->siftoegw($_sifdata, $contentID))
		{
			if ($contentID) {
				$event['id'] = $contentID;
			}
			$result = $this->find_event($event, $filter);
		}
		return $result;
	}

	/**
	* @return int event id
	* @param string	$_sifdata   the SIFE data
	* @param int	$_calID=-1	the internal addressbook id
	* @param boolean $merge=false	merge data with existing entry
	* @param int $recur_date=0 if set, import the recurrence at this timestamp,
	*                          default 0 => import whole series (or events, if not recurring)
	* @desc import a SIFE into the calendar
	*/
	function addSIF($_sifdata, $_calID=-1, $merge=false, $recur_date=0)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($_sifdata)."\n",3,$this->logfile);
		}
		if (!$event = $this->siftoegw($_sifdata, $_calID))
		{
			return false;
		}

		if ($event['recur_type'] != MCAL_RECUR_NONE)
		{
			// Adjust the event start -- no exceptions before and at the start
			$length = $event['end'] - $event['start'];
			$rriter = calendar_rrule::event2rrule($event, false);
			$rriter->rewind();
			if (!$rriter->valid()) continue; // completely disolved into exceptions

			$newstart = egw_time::to($rriter->current, 'server');
			if ($newstart != $event['start'])
			{
				// leading exceptions skiped
				$event['start'] = $newstart;
				$event['end'] = $newstart + $length;
			}

			$exceptions = $event['recur_exception'];
			foreach($exceptions as $key => $day)
			{
				// remove leading exceptions
				if ($day <= $event['start'])
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							'(): event SERIES-MASTER skip leading exception ' .
							$day . "\n",3,$this->logfile);
					}
					unset($exceptions[$key]);
				}
			}
			$event['recur_exception'] = $exceptions;
		}

		if ($recur_date) $event['recurrence'] = $recur_date;
		$event_info = $this->get_event_info($event);

		// common adjustments for existing events
		if (is_array($event_info['stored_event']))
		{
			if (empty($event['uid']))
			{
				$event['uid'] = $event_info['stored_event']['uid']; // restore the UID if it was not delivered
			}
			if ($merge)
			{
				// overwrite with server data for merge
				foreach ($event_info['stored_event'] as $key => $value)
				{
					if (!empty($value))	$event[$key] = $value;
				}
			}
			else
			{
				// not merge
				// SIF clients do not support participants => add them back
				$event['participants'] = $event_info['stored_event']['participants'];
				$event['participant_types'] = $event_info['stored_event']['participant_types'];
				if ($event['whole_day'] && $event['tzid'] != $event_info['stored_event']['tzid'])
				{
					if (!isset(self::$tz_cache[$event_info['stored_event']['tzid']]))
					{
						self::$tz_cache[$event_info['stored_event']['tzid']] =
							calendar_timezones::DateTimeZone($event_info['stored_event']['tzid']);
					}
					// Adjust dates to original TZ
					$time = new egw_time($event['startdate'],self::$tz_cache[$event_info['stored_event']['tzid']]);
					$event['start'] = egw_time::to($time, 'server');
					$time = new egw_time($event['enddate'],self::$tz_cache[$event_info['stored_event']['tzid']]);
					$event['end'] = egw_time::to($time, 'server');
					if ($event['recur_type'] != MCAL_RECUR_NONE)
					{
						foreach ($event['recur_exception'] as $key => $day)
						{
							$time = new egw_time($day,egw_time::$server_timezone);
							$time =& $this->so->startOfDay($time, $event_info['stored_event']['tzid']);
							$event['recur_exception'][$key] = egw_time::to($time,'server');
						}
					}
				}

				calendar_rrule::rrule2tz($event, $event_info['stored_event']['start'],
					$event_info['stored_event']['tzid']);

				$event['tzid'] = $event_info['stored_event']['tzid'];
				// avoid that iCal changes the organizer, which is not allowed
				$event['owner'] = $event_info['stored_event']['owner'];
			}
		}
		else // common adjustments for new events
		{
			// set non blocking all day depending on the user setting
			if (isset($event['whole_day'])
				&& $event['whole_day']
				&& $this->nonBlockingAllday)
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

			$status = $event['owner'] == $this->user ? 'A' : 'U';
			$status = calendar_so::combine_status($status, 1, 'CHAIR');
			$event['participants'] = array($event['owner'] => $status);
		}

		unset($event['startdate']);
		unset($event['enddate']);

		$alarmData = array();
		if (isset($event['alarm']))
		{
			$alarmData['offset'] = $event['alarm'] * 60;
			$alarmData['time'] = $event['start'] - $alarmData['offset'];
			$alarmData['owner']	= $this->user;
			$alarmData['all'] = false;
		}

		// update alarms depending on the given event type
		if (!empty($alarmData) || isset($this->supportedFields['alarm']))
		{
			switch ($event_info['type'])
			{
				case 'SINGLE':
				case 'SERIES-MASTER':
				case 'SERIES-EXCEPTION':
				case 'SERIES-EXCEPTION-PROPAGATE':
					if (isset($event['alarm']))
					{
						if (is_array($event_info['stored_event'])
								&& count($event_info['stored_event']['alarm']) > 0)
						{
							foreach ($event_info['stored_event']['alarm'] as $alarm_id => $alarm_data)
							{
								if ($alarmData['time'] == $alarm_data['time'] &&
									($alarm_data['all'] || $alarm_data['owner'] == $this->user))
								{
									unset($alarmData);
									unset($event_info['stored_event']['alarm'][$alarm_id]);
									break;
								}
							}
							if (isset($alarmData)) $event['alarm'][] = $alarmData;
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
					unset($event['recurrence']);
					$event['reference'] = 0;
					$event_to_store = $event; // prevent $event from being changed by the update method
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
					$days = $this->so->get_recurrence_exceptions($event_info['stored_event'], $this->tzid, 0, 0, 'tz_map');
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."(EXCEPTIONS MAPPING):\n" .
							array2string($days)."\n",3,$this->logfile);
					}
					if (is_array($days))
					{
						$exceptions = array();
						foreach ($event['recur_exception'] as $recur_exception)
						{
							if (isset($days[$recur_exception]))
							{
								$exceptions[] = $days[$recur_exception];
							}
						}
						$event['recur_exception'] = $exceptions;
					}

					$event_to_store = $event; // prevent $event from being changed by the update method
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
								array($event['recurrence'])));

						// Adjust the event start -- must not be an exception
						$length = $event_info['master_event']['end'] - $event_info['master_event']['start'];
						$rriter = calendar_rrule::event2rrule($event_info['master_event'], false);
						$rriter->rewind();
						if ($rriter->valid())
						{
							$newstart = egw_time::to($rriter->current, 'server');
							foreach($event_info['master_event']['recur_exception'] as $key => $day)
							{
								// remove leading exceptions
								if ($day < $newstart)
								{
									unset($event_info['master_event']['recur_exception'][$key]);
								}
							}
						}
						if ($event_info['master_event']['start'] < $newstart)
						{
							$event_info['master_event']['start'] = $newstart;
							$event_info['master_event']['end'] = $newstart + $length;
							$event_to_store = $event_info['master_event']; // prevent the master_event from being changed by the update method
							$this->server2usertime($event_to_store);
							$this->update($event_to_store, true);
							unset($event_to_store);
						}
						$event['reference'] = $event_info['master_event']['id'];
						$event['category'] = $event_info['master_event']['category'];
						$event['owner'] = $event_info['master_event']['owner'];
					}

					$event_to_store = $event; // prevent $event from being changed by update method
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
		}

		// read stored event into info array for fresh stored (new) events
		if (!is_array($event_info['stored_event']) && $updated_id > 0)
		{
			$event_info['stored_event'] = $this->read($updated_id);
		}

		// choose which id to return to the client
		switch ($event_info['type'])
		{
			case 'SINGLE':
			case 'SERIES-MASTER':
			case 'SERIES-EXCEPTION':
				$return_id = $updated_id;
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

		if ($this->log)
		{
			$recur_date = $this->date2usertime($event_info['stored_event']['start']);
			$event_info['stored_event'] = $this->read($event_info['stored_event']['id'], $recur_date);
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($event_info['stored_event'])."\n",3,$this->logfile);
		}

		return $return_id;
	}

	/**
	* return a sife
	*
	* @param int	$_id		the id of the event
	* @param int $recur_date=0	if set export the next recurrence at or after the timestamp,
	*                          	default 0 => export whole series (or events, if not recurring)
	* @return string containing the SIFE
	*/
	function getSIF($_id, $recur_date=0)
	{
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
			"($_id, $recur_date)\n",3,$this->logfile);
		}
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

		$fields = array_unique(array_values($this->sifMapping));
		sort($fields);
		$tzid = null;

		if (!($event = $this->read($_id, $recur_date, false, 'server')))
		{
			if ($this->read($_id, $recur_date, true, 'server'))
			{
				$retval = -1; // Permission denied
				if($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],
						$GLOBALS['xmlrpcstr']['no_access']);
				}
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						"() User does not have the permission to read event $_id.\n",
						3,$this->logfile);
				}
			}
			else
			{
				$retval = false;  // Entry does not exist
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						"() Event $_id not found.\n",3,$this->logfile);
				}
			}
			return $retval;
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($event)."\n",3,$this->logfile);
		}

		if ($this->tzid)
		{
			// explicit device timezone
			$tzid = $this->tzid;
		}
		else
		{
			// use event's timezone
			$tzid = $event['tzid'];
		}

		if ($this->so->isWholeDay($event)) $event['whole_day'] = true;

		if ($tzid)
		{
			if (!isset(self::$tz_cache[$tzid]))
			{
				self::$tz_cache[$tzid] = calendar_timezones::DateTimeZone($tzid);
			}
		}
		if (!isset(self::$tz_cache[$event['tzid']]))
		{
			self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
		}

		if ($recur_date && ($master = $this->read($_id, 0, true, 'server')))
		{
			$days = $this->so->get_recurrence_exceptions($master, $tzid, 0, 0, 'tz_rrule');
			if (isset($days[$recur_date]))
			{
				$recur_date = $days[$recur_date]; // use remote representation
			}
			else
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						"($_id, $recur_date) Unsupported status only exception, skipped ...\n",
						3,$this->logfile);
				}
				return false; // unsupported pseudo exception
			}
			/*
			$time = new egw_time($master['start'], egw_time::$server_timezone);
			$time->setTimezone(self::$tz_cache[$tzid]);
			$first_start = $time->format('His');
			$time = new egw_time($event['start'], egw_time::$server_timezone);
			$time->setTimezone(self::$tz_cache[$tzid]);
			$recur_start = $time->format('His');
			if ($first_start == $recur_start) return false; // Nothing to export
			*/
			$event['recur_type'] = MCAL_RECUR_NONE;
		}
		elseif (!$recur_date &&
			$event['recur_type'] != MCAL_RECUR_NONE &&
			!isset($event['whole_day'])) // whole-day events are not shifted
		{
			// Add the timezone transition related pseudo exceptions
			$exceptions = $this->so->get_recurrence_exceptions($event, $tzid, 0, 0, 'tz_rrule');
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."(EXCEPTIONS)\n" .
					array2string($exceptions)."\n",3,$this->logfile);
			}
			$event['recur_exception'] = $exceptions;
			// Adjust the event start -- must not be an exception
			$length = $event['end'] - $event['start'];
			$rriter = calendar_rrule::event2rrule($event, false, $tzid);
			$rriter->rewind();
			if (!$rriter->valid()) return false; // completely disolved into exceptions

			$event['start'] = egw_time::to($rriter->current, 'server');
			$event['end'] = $event['start'] + $length;
			foreach($exceptions as $key => $day)
			{
				// remove leading exceptions
				if ($day <= $event['start']) unset($exceptions[$key]);
			}
			$event['recur_exception'] = $exceptions;
			calendar_rrule::rrule2tz($event, $event['start'], $tzid);
		}

		if ($this->uidExtension)
		{
			if (!preg_match('/\[UID:.+\]/m', $event['description']))
			{
				$event['description'] .= "\n[UID:" . $event['uid'] . "]";
			}
		}

		$sifEvent = self::xml_decl . "<appointment>" . self::SIF_decl;

		foreach ($this->sifMapping as $sifField => $egwField)
		{
			if (empty($egwField)) continue;

			$value = $GLOBALS['egw']->translation->convert($event[$egwField], $sysCharSet, 'utf-8');

			switch ($sifField)
			{
				case 'Importance':
					$value = $value-1;
					$sifEvent .= "<$sifField>$value</$sifField>";
					break;

				case 'RecurrenceType':
				case 'Interval':
				case 'PatternStartDate':
				case 'NoEndDate':
				case 'DayOfWeekMask':
				case 'PatternEndDate':
					break;

				case 'IsRecurring':
					if ($event['recur_type'] == MCAL_RECUR_NONE)
					{
						$sifEvent .= "<$sifField>0</$sifField>";
						break;
					}
					$occurrences = 0;
					if ($event['recur_enddate'] == 0)
					{
						$sifEvent .= '<NoEndDate>1</NoEndDate>';
					}
					else
					{
						$rriter = calendar_rrule::event2rrule($event, false, $tzid);
						$rriter->rewind();

						while ($rriter->valid())
						{
							$occurrences++;
							$recur_date = $rriter->current();
							if (!$rriter->exceptions || !in_array($recur_date->format('Ymd'),$rriter->exceptions))
							{
								$recur_end = $recur_date;
							}
							$rriter->next_no_exception();
						}
						$recurEndDate = egw_time::to($recur_end, 'server');
						$sifEvent .= '<NoEndDate>0</NoEndDate>';
						$sifEvent .= '<PatternEndDate>'. self::getDateTime($recurEndDate,$tzid) .'</PatternEndDate>';
					}

					$eventInterval = ($event['recur_interval'] > 1 ? $event['recur_interval'] : 1);

					switch ($event['recur_type'])
					{

						case MCAL_RECUR_DAILY:
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursDaily .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$tzid) .'</PatternStartDate>';
							if ($event['recur_enddate'])
							{
								$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
							}
							break;

						case MCAL_RECUR_WEEKLY:
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursWeekly .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$tzid) .'</PatternStartDate>';
							$sifEvent .= '<DayOfWeekMask>'. $event['recur_data'] .'</DayOfWeekMask>';
							if ($event['recur_enddate'])
							{
								$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
							}
							break;

						case MCAL_RECUR_MONTHLY_MDAY:
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursMonthly .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$tzid) .'</PatternStartDate>';
							break;

						case MCAL_RECUR_MONTHLY_WDAY:
							$weekMaskMap = array('Sun' => self::olSunday, 'Mon' => self::olMonday, 'Tue' => self::olTuesday,
								'Wed' => self::olWednesday, 'Thu' => self::olThursday, 'Fri' => self::olFriday,
								'Sat' => self::olSaturday);
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursMonthNth .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$tzid) .'</PatternStartDate>';
							$sifEvent .= '<Instance>' . (1 + (int) ((date('d',$event['start'])-1) / 7)) . '</Instance>';
							$sifEvent .= '<DayOfWeekMask>' . $weekMaskMap[date('D',$event['start'])] . '</DayOfWeekMask>';
							break;

						case MCAL_RECUR_YEARLY:
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursYearly .'</RecurrenceType>';
							break;
					}
					if (is_array($event['recur_exception']))
					{
						$sifEvent .= '<Exceptions>';
						foreach ($event['recur_exception'] as $day)
						{
							if (isset($event['whole_day']))
							{
								if (!is_a($day,'DateTime'))
								{
									$day = new egw_time($day,egw_time::$server_timezone);
									$day->setTimezone(self::$tz_cache[$event['tzid']]);
								}
								$sifEvent .= '<ExcludeDate>' . $day->format('Y-m-d') . '</ExcludeDate>';
							}
							else
							{
								if ($this->log && is_a($day,'DateTime'))
								{
									error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
										'() exception[' . $day->getTimezone()->getName() . ']: ' .
										$day->format('Ymd\THis') . "\n",3,$this->logfile);
								}
								$sifEvent .= '<ExcludeDate>' . self::getDateTime($day,$tzid) . '</ExcludeDate>';
							}
						}
						$sifEvent .= '</Exceptions>';
					}
					break;

				case 'Sensitivity':
					$value = (!$value ? '2' : '0');
					$sifEvent .= "<$sifField>$value</$sifField>";
					break;

				case 'Folder':
					# skip currently. This is the folder where Outlook stores the contact.
					#$sifEvent .= "<$sifField>/</$sifField>";
					break;

				case 'AllDayEvent':
				case 'End':
					// get's handled by Start clause
					break;

				case 'Start':
					if (isset($event['whole_day']))
					{
						// for whole-day events we use the date in event timezone
						$time = new egw_time($event['start'],egw_time::$server_timezone);
						$time->setTimezone(self::$tz_cache[$event['tzid']]);
						$sifEvent .= '<Start>' . $time->format('Y-m-d') . '</Start>';
						$time = new egw_time($event['end'],egw_time::$server_timezone);
						$time->setTimezone(self::$tz_cache[$event['tzid']]);
						$sifEvent .= '<End>' . $time->format('Y-m-d') . '</End>';
						$sifEvent .= "<AllDayEvent>1</AllDayEvent>";
					}
					else
					{
						$sifEvent .= '<Start>' . self::getDateTime($event['start'],$tzid) . '</Start>';
						$sifEvent .= '<End>' . self::getDateTime($event['end'],$tzid) . '</End>';
						$sifEvent .= "<AllDayEvent>0</AllDayEvent>";
					}
					break;

				case 'ReminderMinutesBeforeStart':
					break;

				case 'ReminderSet':
					if (count((array)$event['alarm']) > 0)
					{
						$sifEvent .= "<$sifField>1</$sifField>";
						foreach ($event['alarm'] as $alarmID => $alarmData)
						{
							$sifEvent .= '<ReminderMinutesBeforeStart>'. $alarmData['offset']/60 .'</ReminderMinutesBeforeStart>';
							// lets take only the first alarm
							break;
						}
					}
					else
					{
						$sifEvent .= "<$sifField>0</$sifField>";
					}
					break;

				case 'Categories':
					if (!empty($value) && ($values = $this->get_categories($value)))
					{
						$value = implode(', ', $values);
						$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
					}
					else
					{
						break;
					}

				default:
					$value = @htmlspecialchars($value, ENT_QUOTES, 'utf-8');
					$sifEvent .= "<$sifField>$value</$sifField>";
			}
		}
		$sifEvent .= "</appointment>";

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
				"() '$this->productName','$this->productSoftwareVersion'\n",3,$this->logfile);
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
				"()\n".array2string($sifEvent)."\n",3,$this->logfile);
		}

		return $sifEvent;
	}

	/**
	* Set the supported fields
	*
	* Currently we only store name and version, manucfacturer is always Funambol
	*
	* @param string $_productName
	* @param string $_productSoftwareVersion
	*/
	function setSupportedFields($_productName='', $_productSoftwareVersion='')
	{
		$state =& $_SESSION['SyncML.state'];
		if (isset($state))
		{
			$deviceInfo = $state->getClientDeviceInfo();
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
		}
		// store product name and software version for futher usage
		if ($_productName)
		{
			$this->productName = strtolower($_productName);
			if (preg_match('/^[^\d]*(\d+\.?\d*)[\.|\d]*$/', $_productSoftwareVersion, $matches))
			{
				$this->productSoftwareVersion = $matches[1];
			}
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				'(' . $this->productName .
				', '. $this->productSoftwareVersion . ")\n",3,$this->logfile);
		}
	}
}
