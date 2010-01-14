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
	function getDateTime($time,$tzid)
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
			$finalEvent['tzid'] = $this->tzid;
		}
		else
		{
			$finalEvent['tzid'] = egw_time::$user_timezone->getName();	// default to user timezone
		}
		#error_log($sifData);

		#$tmpfname = tempnam('/tmp/sync/contents','sife_');

		#$handle = fopen($tmpfname, "w");
		#fwrite($handle, $sifData);
		#fclose($handle);

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
			return false;
		}
		#error_log(print_r($this->event, true));

		foreach ($this->event as $key => $value)
		{
			$value = preg_replace('/<\!\[CDATA\[(.+)\]\]>/Usim', '$1', $value);
			$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"() $key => $value\n",3,$this->logfile);
			}

			switch ($key)
			{
				case 'alldayevent':
					if ($value == 1)
					{
						$finalEvent['whole_day'] = true;
						$startParts = explode('-',$this->event['start']);
						$finalEvent['start']['hour'] = $finalEvent['start']['minute'] = $finalEvent['start']['second'] = 0;
						$finalEvent['start']['year'] = $startParts[0];
						$finalEvent['start']['month'] = $startParts[1];
						$finalEvent['start']['day'] = $startParts[2];
						$finalEvent['start'] = $this->date2ts($finalEvent['start']);
						$endParts = explode('-',$this->event['end']);
						$finalEvent['end']['hour'] = 23; $finalEvent['end']['minute'] = $finalEvent['end']['second'] = 59;
						$finalEvent['end']['year'] = $endParts[0];
						$finalEvent['end']['month'] = $endParts[1];
						$finalEvent['end']['day'] = $endParts[2];
						$finalEvent['end'] = $this->date2ts($finalEvent['end']);
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
						$finalEvent[$key] = implode(',', $this->find_or_add_categories($categories, $_calID));
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
						if (is_array($this->event['recur_exception']))
						{
							$finalEvent['recur_exception'] = array();
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
							$finalEvent['recur_enddate'] = $this->vCalendar->_parseDateTime($this->event['recur_enddate']);
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

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($finalEvent)."\n",3,$this->logfile);
		}

		return $finalEvent;
	}

	function search($_sifdata, $contentID=null, $relax=false)
	{
		$result = false;

		if ($event = $this->siftoegw($_sifdata, $contentID))
		{
			if ($contentID) {
				$event['id'] = $contentID;
			}
			$result = $this->find_event($event, $relax);
		}
		return $result;
	}

	/**
	* @return int event id
	* @param string	$_sifdata   the SIFE data
	* @param int	$_calID=-1	the internal addressbook id
	* @param boolean $merge=false	merge data with existing entry
	* @desc import a SIFE into the calendar
	*/
	function addSIF($_sifdata, $_calID=-1, $merge=false)
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

		if (isset($event['alarm']))
		{
			$alarmData = array();
			$alarmData['offset'] = $event['alarm'] * 60;
			$alarmData['time']	= $event['start'] - $alarmData['offset'];
			$alarmData['owner']	= $this->user;
			$alarmData['all']	= false;
			$event['alarm'] = $alarmData;
		}

		if ($_calID > 0 && ($storedEvent = $this->read($_calID)))
		{
			// update entry
			$event['id'] = $_calID;
			// delete existing alarms
			if (count($storedEvent['alarm']) > 0)
			{
				foreach ($storedEvent['alarm'] as $alarm_id => $alarm_data)
				{
					// only touch own alarms
					if ($alarm_data['all'] == false && $alarm_data['owner'] == $this->user)
					{
						$this->delete_alarm($alarm_id);
					}
				}
			}
		}
		else
		{
			if (isset($event['whole_day'])
				&& $event['whole_day']
				&& $this->nonBlockingAllday)
			{
				$event['non_blocking'] = 1;
			}
		}

		$eventID = $this->update($event, true);

		if ($eventID && $this->log)
		{
			$storedEvent = $this->read($eventID);
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($storedEvent)."\n",3,$this->logfile);
		}

		return $eventID;
	}

	/**
	* return a sife
	*
	* @param int	$_id		the id of the event
	* @return string containing the SIFE
	*/
	function getSIF($_id)
	{
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

		$fields = array_unique(array_values($this->sifMapping));
		sort($fields);

		#$event = $this->read($_id,null,false,'server');
		#error_log("FOUND EVENT: ". print_r($event, true));

		if (($event = $this->read($_id,null,false,'server')))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
					array2string($event)."\n",3,$this->logfile);
			}
			if ($this->uidExtension)
			{
				if (!preg_match('/\[UID:.+\]/m', $event['description']))
				{
					$event['description'] .= "\n[UID:" . $event['uid'] . "]";
				}
			}

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
			if ($tzid && $tzid != 'UTC')
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

			$sifEvent = self::xml_decl . "<appointment>" . self::SIF_decl;

			foreach ($this->sifMapping as $sifField => $egwField)
			{
				if (empty($egwField)) continue;

				#error_log("$sifField => $egwField");
				#error_log('VALUE1: '.$event[$egwField]);
				$value = $GLOBALS['egw']->translation->convert($event[$egwField], $sysCharSet, 'utf-8');
				#error_log('VALUE2: '.$value);

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
							$time = new egw_time($event['recur_enddate'],egw_time::$server_timezone);
							// all calculations in the event's timezone
							$time->setTimezone(self::$tz_cache[$event['tzid']]);
							$time->setTime(23, 59, 59);
							$recurEndDate = $this->date2ts($time);
							$sifEvent .= '<NoEndDate>0</NoEndDate>';
							$sifEvent .= '<PatternEndDate>'. $this->vCalendar->_exportDateTime($recurEndDate) .'</PatternEndDate>';
						}
						$time = new egw_time($event['start'],egw_time::$server_timezone);
						// all calculations in the event's timezone
						$time->setTimezone(self::$tz_cache[$event['tzid']]);
						$time->setTime(0, 0, 0);
						$recurStartDate = $this->date2ts($time);
						$eventInterval = ($event['recur_interval'] > 1 ? $event['recur_interval'] : 1);
						switch ($event['recur_type'])
						{

							case MCAL_RECUR_DAILY:
								$sifEvent .= "<$sifField>1</$sifField>";
								$sifEvent .= '<RecurrenceType>'. self::olRecursDaily .'</RecurrenceType>';
								$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
								$sifEvent .= '<PatternStartDate>'. $this->vCalendar->_exportDateTime($recurStartDate) .'</PatternStartDate>';
								if ($event['recur_enddate'])
								{
									$totalDays = ($recurEndDate - $recurStartDate) / 86400;
									$occurrences = ceil($totalDays / $eventInterval);
									$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
								}
								break;

							case MCAL_RECUR_WEEKLY:
								$sifEvent .= "<$sifField>1</$sifField>";
								$sifEvent .= '<RecurrenceType>'. self::olRecursWeekly .'</RecurrenceType>';
								$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
								$sifEvent .= '<PatternStartDate>'. $this->vCalendar->_exportDateTime($recurStartDate) .'</PatternStartDate>';
								$sifEvent .= '<DayOfWeekMask>'. $event['recur_data'] .'</DayOfWeekMask>';
								if ($event['recur_enddate'])
								{
									$daysPerWeek = substr_count(decbin($event['recur_data']),'1');
									$totalWeeks = floor(($recurEndDate - $recurStartDate) / (86400*7));
									$occurrences = ($totalWeeks / $eventInterval) * $daysPerWeek;
									for($i = $recurEndDate; $i > $recurStartDate + ($totalWeeks * 86400*7); $i = $i - 86400)
									{
										switch (date('w', $i-1))
										{
											case 0:
												if ($event['recur_data'] & 1) $occurrences++;
												break;
											// monday
											case 1:
												if ($event['recur_data'] & 2) $occurrences++;
												break;
											case 2:
												if ($event['recur_data'] & 4) $occurrences++;
												break;
											case 3:
												if ($event['recur_data'] & 8) $occurrences++;
												break;
											case 4:
												if ($event['recur_data'] & 16) $occurrences++;
												break;
											case 5:
												if ($event['recur_data'] & 32) $occurrences++;
												break;
											case 6:
												if ($event['recur_data'] & 64) $occurrences++;
												break;
										}
									}
									$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
								}
								break;

							case MCAL_RECUR_MONTHLY_MDAY:
								$sifEvent .= "<$sifField>1</$sifField>";
								$sifEvent .= '<RecurrenceType>'. self::olRecursMonthly .'</RecurrenceType>';
								$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
								$sifEvent .= '<PatternStartDate>'. $this->vCalendar->_exportDateTime($recurStartDate) .'</PatternStartDate>';
								break;

							case MCAL_RECUR_MONTHLY_WDAY:
								$weekMaskMap = array('Sun' => self::olSunday, 'Mon' => self::olMonday, 'Tue' => self::olTuesday,
													 'Wed' => self::olWednesday, 'Thu' => self::olThursday, 'Fri' => self::olFriday,
													 'Sat' => self::olSaturday);
								$sifEvent .= "<$sifField>1</$sifField>";
								$sifEvent .= '<RecurrenceType>'. self::olRecursMonthNth .'</RecurrenceType>';
								$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
								$sifEvent .= '<PatternStartDate>'. $this->vCalendar->_exportDateTime($recurStartDate) .'</PatternStartDate>';
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
								if ($this->isWholeDay($event))
								{
									$time = new egw_time($day,egw_time::$server_timezone);
									$time->setTimezone(self::$tz_cache[$tzid]);
									$sifEvent .= '<ExcludeDate>' . $time->format('Y-m-d') . '</ExcludeDate>';
								}
								else
								{
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
						if ($this->isWholeDay($event))
						{
							$time = new egw_time($event['start'],egw_time::$server_timezone);
							$time->setTimezone(self::$tz_cache[$tzid]);
							$sifEvent .= '<Start>' . $time->format('Y-m-d') . '</Start>';
							$time = new egw_time($event['end'],egw_time::$server_timezone);
							$time->setTimezone(self::$tz_cache[$tzid]);
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
						break;
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

		if($this->xmlrpc)
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
		}
		return False;
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
