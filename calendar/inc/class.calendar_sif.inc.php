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
	 * @param boolean $utc=true if true, return timespamps in UTC, else in server time
	 * @return mixed attribute value to set: integer timestamp if $tzid == 'UTC' otherwise Ymd\THis string IN $tzid
	 */
	function getDateTime($time, $utc=true)
	{
		if ($utc)
		{
			return $this->vCalendar->_exportDateTime($time);
		}
		return date('Ymd\THis', $time);
	}

	function siftoegw($sifData, $_calID=-1)
	{
		$finalEvent	= array();
		$this->event = array();
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

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
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
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
							$finalEvent['recur_enddate'] = mktime(0, 0, 0,
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
					$finalEvent[$key] = str_replace("\r\n", "\n", $value);
					break;
			}
		}

		if ($this->calendarOwner) $finalEvent['owner'] = $this->calendarOwner;

		date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);

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


		if ($recur_date) $event['reference'] = $recur_date;
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
					if ($key == 'participants')
					{
						unset($event[$key]);
						continue;
					}
					if (!empty($value))	$event[$key] = $value;
				}
			}
			else
			{
				// not merge
				// SIF clients do not support participants => add them back
				unset($event['participants']);

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

				// update the event
				if ($event_info['acl_edit'])
				{
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
		$utc = true;

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

		if ($this->isWholeDay($event)) $event['whole_day'] = true;

		if ($recur_date)
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"($_id, $recur_date) Unsupported status only exception, skipped ...\n",
					3, $this->logfile);
			}
			return false; // unsupported pseudo exception
		}

		if (date('e', $event['start']) != 'UTC'
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
				}
			}
			else
			{
				$utc = false;
			}
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
					if ($event['recur_enddate'] == 0)
					{
						$sifEvent .= '<NoEndDate>1</NoEndDate>';
					}
					else
					{
						$recurrences = $this->so->get_recurrences($_id);
						$occurrences = count($recurrences) + count($event['recur_exception']) - 1;
						end($recurrences);
						$last = key($recurrences);
						if ($last < end($event['recur_exception']))
						{
							$last = end($event['recur_exception']);
						}
						$recurEndDate = $last - $event['start'] + $event['end'];
						$sifEvent .= '<NoEndDate>0</NoEndDate>';
						$sifEvent .= '<PatternEndDate>'. self::getDateTime($recurEndDate,$utc) .'</PatternEndDate>';
					}

					$eventInterval = ($event['recur_interval'] > 1 ? $event['recur_interval'] : 1);

					switch ($event['recur_type'])
					{

						case MCAL_RECUR_DAILY:
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursDaily .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$utc) .'</PatternStartDate>';
							if ($event['recur_enddate'])
							{
								$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
							}
							break;

						case MCAL_RECUR_WEEKLY:
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursWeekly .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$utc) .'</PatternStartDate>';
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
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$utc) .'</PatternStartDate>';
							break;

						case MCAL_RECUR_MONTHLY_WDAY:
							$weekMaskMap = array('Sun' => self::olSunday, 'Mon' => self::olMonday, 'Tue' => self::olTuesday,
								'Wed' => self::olWednesday, 'Thu' => self::olThursday, 'Fri' => self::olFriday,
								'Sat' => self::olSaturday);
							$sifEvent .= "<$sifField>1</$sifField>";
							$sifEvent .= '<RecurrenceType>'. self::olRecursMonthNth .'</RecurrenceType>';
							$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
							$sifEvent .= '<PatternStartDate>'. self::getDateTime($event['start'],$utc) .'</PatternStartDate>';
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
								$sifEvent .= '<ExcludeDate>' . date('Y-m-d', $day) . '</ExcludeDate>';
							}
							else
							{
								$sifEvent .= '<ExcludeDate>' . self::getDateTime($day,$utc) . '</ExcludeDate>';
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
						$sifEvent .= '<Start>' . date('Y-m-d', $event['start']) . '</Start>';
						$sifEvent .= '<End>' . date('Y-m-d', $event['end']) . '</End>';
						$sifEvent .= "<AllDayEvent>1</AllDayEvent>";
					}
					else
					{
						$sifEvent .= '<Start>' . self::getDateTime($event['start'],$utc) . '</Start>';
						$sifEvent .= '<End>' . self::getDateTime($event['end'],$utc) . '</End>';
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
				$this->useServerTZ = ($deviceInfo['tzid'] == 1);
			}
			if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['calendar_owner']))
			{
				$owner = $GLOBALS['egw_info']['user']['preferences']['syncml']['calendar_owner'];
				if ($owner == 0)
				{
					$owner = $GLOBALS['egw_info']['user']['account_primary_group'];
				}
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
