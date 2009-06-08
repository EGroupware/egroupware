<?php
/**
 * eGroupWare calendar - SIF Parser for SyncML
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage export
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

/**
 * SIF Parser for SyncML
 */
class calendar_sif extends calendar_boupdate
{
	var $sifMapping = array(
		'Start'				=> 'start',
		'End'				=> 'end',
		'AllDayEvent'			=> 'alldayevent',
		'BillingInformation'		=> '',
		'Body'				=> 'description',
		'BusyStatus'			=> '',
		'Categories'			=> 'category',
		'Companies'			=> '',
		'Importance'			=> 'priority',
		'IsRecurring'			=> 'isrecurring',
		'Location'			=> 'location',
		'MeetingStatus'			=> '',
		'Mileage'			=> '',
		'ReminderMinutesBeforeStart'	=> 'reminderstart',
		'ReminderSet'			=> 'reminderset',
		'ReplyTime'			=> '',
		'Sensitivity'			=> 'public',
		'Subject'			=> 'title',
		'RecurrenceType'		=> 'recur_type',
		'Interval'			=> 'recur_interval',
		'MonthOfYear'			=> '',
		'DayOfMonth'			=> '',
		'DayOfWeekMask'			=> 'recur_weekmask',
		'Instance'			=> '',
		'PatternStartDate'		=> '',
		'NoEndDate'			=> 'recur_noenddate',
		'PatternEndDate'		=> 'recur_enddate',
		'Occurrences'			=> '',
	);

	// the calendar event array
	var $event;

	// constants for recurence type
	const olRecursDaily	= 0;
	const olRecursWeekly	= 1;
	const olRecursMonthly	= 2;
	const olRecursMonthNth	= 3;
	const olRecursYearly	= 5;
	const olRecursYearNth	= 6;

	// constants for weekdays
	const olSunday = 1;
	const olMonday = 2;
	const olTuesday = 4;
	const olWednesday = 8;
	const olThursday = 16;
	const olFriday = 32;
	const olSaturday = 64;

	function startElement($_parser, $_tag, $_attributes) {
	}

	function endElement($_parser, $_tag) {
		//error_log('endElem: ' . $_tag .' => '. trim($this->sifData));
		if(!empty($this->sifMapping[$_tag])) {
			$this->event[$this->sifMapping[$_tag]] = trim($this->sifData);
		}
		unset($this->sifData);
	}

	function characterData($_parser, $_data) {
		$this->sifData .= $_data;
	}

	function siftoegw($_sifdata) {
		$vcal		= new Horde_iCalendar;
		$finalEvent	= array();
		$sysCharSet	= $GLOBALS['egw']->translation->charset();
		$sifData	= base64_decode($_sifdata);
		#error_log($sifData);

		$tmpfname = tempnam('/tmp/sync/contents','sife_');

		$handle = fopen($tmpfname, "w");
		fwrite($handle, $sifData);
		fclose($handle);

		$this->xml_parser = xml_parser_create('UTF-8');
		xml_set_object($this->xml_parser, $this);
		xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($this->xml_parser, "startElement", "endElement");
		xml_set_character_data_handler($this->xml_parser, "characterData");
		$this->strXmlData = xml_parse($this->xml_parser, $sifData);
		if(!$this->strXmlData) {
			error_log(sprintf("XML error: %s at line %d",
				xml_error_string(xml_get_error_code($this->xml_parser)),
				xml_get_current_line_number($this->xml_parser)));
			return false;
		}
		#error_log(print_r($this->event, true));

		foreach($this->event as $key => $value) {
			$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);
			#error_log("$key => $value");
			switch($key) {
				case 'alldayevent':
					if($value == 1) {
						$startParts = explode('-',$this->event['start']);
						$finalEvent['start'] = mktime(0, 0, 0, $startParts[1], $startParts[2], $startParts[0]);
						$endParts = explode('-',$this->event['end']);
						$finalEvent['end'] = mktime(23, 59, 59, $endParts[1], $endParts[2], $endParts[0]);
					}
					break;

				case 'public':
					$finalEvent[$key] = ((int)$value > 0) ? 0 : 1;
					break;

				case 'category':
					if(!empty($value)) {
						$finalEvent[$key] = implode(',',$this->find_or_add_categories(explode(';', $value)));
					}
					break;

				case 'end':
				case 'start':
					if($this->event['alldayevent'] < 1) {
						$finalEvent[$key] = $vcal->_parseDateTime($value);
						error_log("event ".$key." val=".$value.", parsed=".$finalEvent[$key]);
					}
					break;

				case 'isrecurring':
					if($value == 1) {
						$finalEvent['recur_interval'] = $this->event['recur_interval'];
						if($this->event['recur_noenddate'] == 0) {
							$finalEvent['recur_enddate'] = $vcal->_parseDateTime($this->event['recur_enddate']);
						}
						switch($this->event['recur_type']) {
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
					$finalEvent[$key] = $value+1;
					break;

				case 'reminderset':
					if($value == 1) {
						$finalEvent['alarm'] = $this->event['reminderstart'];
					}
					break;

				case 'recur_type':
				case 'recur_enddate':
				case 'recur_interval':
				case 'recur_weekmask':
				case 'reminderstart':
					// do nothing, get's handled in isrecuring clause
					break;

				default:
					$finalEvent[$key] = $value;
					break;
			}
		}

		#$middleName = ($finalEvent['n_middle']) ? ' '.trim($finalEvent['n_middle']) : '';
		#$finalEvent['fn']  = trim($finalEvent['n_given']. $middleName .' '. $finalEvent['n_family']);

		#error_log(print_r($finalEvent, true));

		return $finalEvent;
	}

	function search($_sifdata, $contentID=null) {
		if(!$event = $this->siftoegw($_sifdata)) {
			return false;
		}

		$query = array(
			'cal_start='.$this->date2ts($event['start'],true),	// true = Server-time
			'cal_end='.$this->date2ts($event['end'],true),
		);
		
		if ($contentID) {
			$query[] = 'egw_cal.cal_id='.(int)$contentID;
		}
		
		#foreach(array('title','location','priority','public','non_blocking') as $name) {
		foreach(array('title','location','public','non_blocking') as $name) {
			if (isset($event[$name])) $query['cal_'.$name] = $event[$name];
		}

		if($foundEvents = parent::search(array(
			'user'  => $this->user,
			'query' => $query,
		))) {
			if(is_array($foundEvents)) {
				$event = array_shift($foundEvents);
				return $event['id'];
			}
		}
		return false;

		$search['start'] = $event['start'];
		$search['end']	= $event['end'];

		unset($event['description']);
		unset($event['start']);
		unset($event['end']);

		foreach($event as $key => $value) {
			if (substr($key,0,6) != 'recur_' && substr($key,0,5) != 'alarm') {
				$search['query']['cal_'.$key] = $value;
			} else {
				#$search['query'][$key] = $value;
			}
		}

		if($foundEvents = parent::search($search)) {
			if(is_array($foundEvents)) {
				$event = array_shift($foundEvents);
				return $event['id'];
			}
		}

		return false;
	}

	/**
	* @return int contact id
	* @param string	$_vcard		the vcard
	* @param int	$_abID		the internal addressbook id
	* @desc import a vard into addressbook
	*/
	function addSIF($_sifdata, $_calID)
	{
		$calID = false;

		#error_log('ABID: '.$_abID);
		#error_log(base64_decode($_sifdata));

		if(!$event = $this->siftoegw($_sifdata)) {
			return false;
		}

		if(isset($event['alarm'])) {
			$alarm = $event['alarm'];
			unset($event['alarm']);
		}

		if($_calID > 0)
		{
			// update entry
			$event['id'] = $_calID;
		}

		if($eventID = $this->update($event, TRUE)) {
			$updatedEvent = $this->read($eventID);
			foreach($updatedEvent['alarm'] as $alarmID => $alarmData)
			{
				$this->delete_alarm($alarmID);
			}

			if(isset($alarm)) {
				$alarmData['time']	= $event['start'] - ($alarm*60);
				$alarmData['offset']	= $alarm*60;
				$alarmData['all']	= 1;
				$alarmData['owner']	= $GLOBALS['egw_info']['user']['account_id'];
				$this->save_alarm($eventID, $alarmData);
			}
		}

		return $eventID;
	}

	/**
	* return a vcard
	*
	* @param int	$_id		the id of the contact
	* @param int	$_vcardProfile	profile id for mapping from vcard values to egw addressbook
	* @return string containing the vcard
	*/
	function getSIF($_id)
	{
		$fields = array_unique(array_values($this->sifMapping));
		sort($fields);

		#$event = $this->read($_id,null,false,'server');
		#error_log("FOUND EVENT: ". print_r($event, true));

		if($event = $this->read($_id,null,false,'server')) {
			$sysCharSet	= $GLOBALS['egw']->translation->charset();
			$vcal		= new Horde_iCalendar;


			$sifEvent = '<appointment>';

			foreach($this->sifMapping as $sifField => $egwField)
			{
				if(empty($egwField)) continue;

				#error_log("$sifField => $egwField");
				#error_log('VALUE1: '.$event[$egwField]);
				$value = $GLOBALS['egw']->translation->convert($event[$egwField], $sysCharSet, 'utf-8');
				#error_log('VALUE2: '.$value);

				switch($sifField)
				{
					case 'Categories':
						if(!empty($value)) {
							$value = implode('; ', $this->get_categories(explode(',',$value)));
							$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
						}
						$sifEvent .= "<$sifField>$value</$sifField>";
						break;

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
						switch($event['recur_type']) {
							case MCAL_RECUR_NONE:
								$sifEvent .= "<$sifField>0</$sifField>";
								break;

							case MCAL_RECUR_DAILY:
								$eventInterval = ($event['recur_interval'] > 1 ? $event['recur_interval'] : 1);
								$recurStartDate = mktime(0,0,0,date('m',$event['start']), date('d', $event['start']), date('Y', $event['start']));

								$sifEvent .= "<$sifField>1</$sifField>";
								$sifEvent .= '<RecurrenceType>'. self::olRecursDaily .'</RecurrenceType>';
								$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
								$sifEvent .= '<PatternStartDate>'. $vcal->_exportDateTime($recurStartDate) .'</PatternStartDate>';
								if($event['recur_enddate'] == 0) {
									$sifEvent .= '<NoEndDate>1</NoEndDate>';
								} else {
									$recurEndDate = mktime(24,0,0,date('m',$event['recur_enddate']), date('d', $event['recur_enddate']), date('Y', $event['recur_enddate']));

									$sifEvent .= '<NoEndDate>0</NoEndDate>';
									$sifEvent .= '<PatternEndDate>'. $vcal->_exportDateTime($recurEndDate) .'</PatternEndDate>';
									$totalDays = ($recurEndDate - $recurStartDate) / 86400;
									$occurrences = ceil($totalDays / $eventInterval);
									$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
								}
								break;

							case MCAL_RECUR_WEEKLY:
								$eventInterval = ($event['recur_interval'] > 1 ? $event['recur_interval'] : 1);
								$recurStartDate = mktime(0,0,0,date('m',$event['start']), date('d', $event['start']), date('Y', $event['start']));

								$sifEvent .= "<$sifField>1</$sifField>";
								$sifEvent .= '<RecurrenceType>'. self::olRecursWeekly .'</RecurrenceType>';
								$sifEvent .= '<Interval>'. $eventInterval .'</Interval>';
								$sifEvent .= '<PatternStartDate>'. $vcal->_exportDateTime($recurStartDate) .'</PatternStartDate>';
								$sifEvent .= '<DayOfWeekMask>'. $event['recur_data'] .'</DayOfWeekMask>';
								if($event['recur_enddate'] == 0) {
									$sifEvent .= '<NoEndDate>1</NoEndDate>';
								} else {
									$recurEndDate = mktime(24, 0, 0, date('m',$event['recur_enddate']), date('d', $event['recur_enddate']), date('Y', $event['recur_enddate']));

									$daysPerWeek = substr_count(decbin($event['recur_data']),'1');
									$sifEvent .= '<NoEndDate>0</NoEndDate>';
									$sifEvent .= '<PatternEndDate>'. $vcal->_exportDateTime($recurEndDate) .'</PatternEndDate>';
									$totalWeeks = floor(($recurEndDate - $recurStartDate) / (86400*7));
									#error_log("AAA: $daysPerWeek $totalWeeks");
									$occurrences = ($totalWeeks / $eventInterval) * $daysPerWeek;
									for($i = $recurEndDate; $i > $recurStartDate + ($totalWeeks * 86400*7); $i = $i - 86400) {
										switch(date('w', $i-1)) {
											case 0:
												if($event['recur_data'] & 1) $occurrences++;
												break;
											// monday
											case 1:
												if($event['recur_data'] & 2) $occurrences++;
												break;
											case 2:
												if($event['recur_data'] & 4) $occurrences++;
												break;
											case 3:
												if($event['recur_data'] & 8) $occurrences++;
												break;
											case 4:
												if($event['recur_data'] & 16) $occurrences++;
												break;
											case 5:
												if($event['recur_data'] & 32) $occurrences++;
												break;
											case 6:
												if($event['recur_data'] & 64) $occurrences++;
												break;
										}
									}
									$sifEvent .= '<Occurrences>'. $occurrences .'</Occurrences>';
								}
								break;
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
						if($event['end'] - $event['start'] == 86399 && date('Y-m-d', $event['end']) == date('Y-m-d', $event['start'])) {
							$value = date('Y-m-d', $event['start']);
							$sifEvent .= "<Start>$value</Start>";
							$sifEvent .= "<End>$value</End>";
							$sifEvent .= "<AllDayEvent>1</AllDayEvent>";
						} else {
							$value = $vcal->_exportDateTime($event['start']);
							$sifEvent .= "<Start>$value</Start>";
							$value = $vcal->_exportDateTime($event['end']);
							$sifEvent .= "<End>$value</End>";
							$sifEvent .= "<AllDayEvent>0</AllDayEvent>";
						}
						break;

					case 'ReminderMinutesBeforeStart':
						break;

					case 'ReminderSet':
						if(count((array)$event['alarm']) > 0) {
							$sifEvent .= "<$sifField>1</$sifField>";
							foreach($event['alarm'] as $alarmID => $alarmData)
							{
								$sifEvent .= '<ReminderMinutesBeforeStart>'. $alarmData['offset']/60 .'</ReminderMinutesBeforeStart>';
								// lets take only the first alarm
								break;
							}
						} else {
							$sifEvent .= "<$sifField>0</$sifField>";
						}
						break;

					default:
						$sifEvent .= "<$sifField>$value</$sifField>";
						break;
				}
			}
			$sifEvent .= '</appointment>';

			return base64_encode($sifEvent);
		}

		if($this->xmlrpc)
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
		}
		return False;
	}

}
