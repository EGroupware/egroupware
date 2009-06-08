<?php
/**
 * InfoLog -  SIF Parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @package infolog
 * @subpackage syncml
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_API_INC.'/horde/Horde/iCalendar.php';

/**
 * InfoLog: Create and parse SIF
 *
 */
class infolog_sif extends infolog_bo
{
	// array containing the result of the xml parser
	var $_extractedSIFData;

	// array containing the current mappings(task or note)
	var $_currentSIFMapping;

	var $_sifNoteMapping = array(
		'Body'			=> 'info_des',
		'Categories'	=> 'info_cat',
		'Color'			=> '',
		'Date'			=> 'info_startdate',
		'Height'		=> '',
		'Left'			=> '',
		'Subject'		=> 'info_subject',
		'Top'			=> '',
		'Width'			=> '',
	);

	// mappings for SIFTask to InfologTask
	var $_sifTaskMapping = array(
		'ActualWork'		=> '',
		'BillingInformation'	=> '',
		'Body'			=> 'info_des',
		'Categories'		=> 'info_cat',
		'Companies'		=> '',
		'Complete'		=> '',
		'DateCompleted'		=> 'info_datecompleted',
		'DueDate'		=> 'info_enddate',
		'Importance'		=> 'info_priority',
		'IsRecurring'		=> '',
		'Mileage'		=> '',
		'PercentComplete'	=> 'info_percent',
		'ReminderSet'		=> '',
		'ReminderTime'		=> '',
		'Sensitivity'		=> 'info_access',
		'StartDate'		=> 'info_startdate',
		'Status'		=> 'info_status',
		'Subject'		=> 'info_subject',
		'TeamTask'		=> '',
		'TotalWork'		=> '',
		'RecurrenceType'	=> '',
		'Interval'		=> '',
		'MonthOfYear'		=> '',
		'DayOfMonth'		=> '',
		'DayOfWeekMask'		=> '',
		'Instance'		=> '',
		'PatternStartDate'	=> '',
		'NoEndDate'		=> '',
		'PatternEndDate'	=> '',
		'Occurrences'		=> '',
	);



	function startElement($_parser, $_tag, $_attributes) {
	}

	function endElement($_parser, $_tag) {
		error_log("infolog: tag=$_tag data=".trim($this->sifData));
		if(!empty($this->_currentSIFMapping[$_tag])) {
			$this->_extractedSIFData[$this->_currentSIFMapping[$_tag]] = trim($this->sifData);
		}
		unset($this->sifData);
	}

	function characterData($_parser, $_data) {
		$this->sifData .= $_data;
	}

	function siftoegw($_sifData, $_sifType) {
		$sysCharSet	= $GLOBALS['egw']->translation->charset();
		$sifData	= base64_decode($_sifData);

		#$tmpfname = tempnam('/tmp/sync/contents','sift_');

		#$handle = fopen($tmpfname, "w");
		#fwrite($handle, $sifData);
		#fclose($handle);

		switch ($_sifType)
		{
			case 'note':
				$this->_currentSIFMapping = $this->_sifNoteMapping;
				break;

			case 'task':
			default:
				$this->_currentSIFMapping = $this->_sifTaskMapping;
				break;
		}

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

		if(!array($this->_extractedSIFData)) {
			return false;
		}

		switch($_sifType) {
			case 'task':
				$taskData	= array();
				$vcal		= new Horde_iCalendar;

				$taskData['info_type'] = 'task';

				foreach($this->_extractedSIFData as $key => $value) {
					$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);
					error_log("infolog key=$key => value=$value");

					switch($key) {
						case 'info_access':
							$taskData[$key] = ((int)$value > 0) ? 'private' : 'public';
							break;

						case 'info_datecompleted':
						case 'info_enddate':
						case 'info_startdate':
							if(!empty($value)) {
								$taskData[$key] = $vcal->_parseDateTime($value);
								// somehow the client always deliver a timestamp about 3538 seconds, when no startdate set.
								if($taskData[$key] < 10000)
									$taskData[$key] = '';
							} else {
								$taskData[$key] = '';
							}
							break;


						case 'info_cat':
							if (!empty($value)) {
								$categories = $this->find_or_add_categories(explode(';', $value));
								$taskData['info_cat'] = $categories[0];
							}
							break;

						case 'info_priority':
							$taskData[$key] = (int)$value;
							break;

						case 'info_status':
							$taskData[$key] = ((int)$value == 2) ? 'done' : 'ongoing';
							switch($value) {
								case '0':
									$taskData[$key] = 'not-started';
									break;
								case '1':
									$taskData[$key] = 'ongoing';
									break;
								case '2':
									$taskData[$key] = 'done';
									break;
								case '4':
									$taskData[$key] = 'cancelled';
									break;
								default:
									$taskData[$key] = 'ongoing';
									break;
							}
							break;

						default:
							$taskData[$key] = $value;
							break;
					}
					error_log("infolog task key=$key => value=".$taskData[$key]);
				}

				return $taskData;
				break;

			case 'note':
				$noteData = array();
				$noteData['info_type'] = 'note';
				$vcal		= new Horde_iCalendar;

				foreach($this->_extractedSIFData as $key => $value)
				{
					$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);

					error_log("infolog client key=$key => value=".$value);
					switch ($key)
					{
						case 'info_startdate':
							if(!empty($value)) {
								$noteData[$key] = $vcal->_parseDateTime($value);
								// somehow the client always deliver a timestamp about 3538 seconds, when no startdate set.
								if($noteData[$key] < 10000)
									$noteData[$key] = '';
							} else {
								$noteData[$key] = '';
							}
							break;

						case 'info_cat':
							if (!empty($value)) {
								$categories = $this->find_or_add_categories(explode(';', $value));
								$taskData['info_cat'] = $categories[0];
							}
							break;

						default:
							$noteData[$key] = $value;
							break;
					}
					error_log("infolog note key=$key => value=".$noteData[$key]);
				}
				return $noteData;
				break;


			default:
				return false;
		}
	}

	function searchSIF($_sifData, $_sifType, $contentID=null) {
		if(!$egwData = $this->siftoegw($_sifData, $_sifType)) {
			return false;
		}
		if ($contentID) {
			$egwData['info_id'] = $contentID;
		}

		$filter = array('col_filter' => $egwData);
		if($foundItems = $this->search($filter)) {
			if(count($foundItems) > 0) {
				$itemIDs = array_keys($foundItems);
				return $itemIDs[0];
			}
		}

		return false;
	}

	function addSIF($_sifData, $_id, $_sifType) {
		if(!$egwData = $this->siftoegw($_sifData, $_sifType)) {
			return false;
		}

		if($_id > 0)
			$egwData['info_id'] = $_id;

		$egwID = $this->write($egwData, false);

		return $egwID;
	}

	function getSIF($_id, $_sifType) {
		switch($_sifType) {
			case 'task':
				if($taskData = $this->read($_id)) {
					$sysCharSet	= $GLOBALS['egw']->translation->charset();
					$vcal		= new Horde_iCalendar;

					$sifTask = '<task>';

					foreach($this->_sifTaskMapping as $sifField => $egwField)
					{
						if(empty($egwField)) continue;

						$value = $GLOBALS['egw']->translation->convert($taskData[$egwField], $sysCharSet, 'utf-8');

						switch($sifField) {
							case 'DateCompleted':
							case 'DueDate':
							case 'StartDate':
								if(!empty($value)) {
									$value = $vcal->_exportDateTime($value);
								}
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Importance':
								if($value > 3) $value = 3;
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Sensitivity':
								$value = ($value == 'private' ? '2' : '0');
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Status':
								switch($value) {
									case 'cancelled':
										$value = '4';
										break;
									case 'done':
										$value = '2';
										break;
									case 'not-started':
										$value = '0';
										break;
									case 'ongoing':
										$value = '1';
										break;
									default:
										$value = 1;
										break;
								}
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Categories':
								if (!empty($value))
								{
									$value = implode('; ', $this->get_categories(array($value)));
									$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
								}
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							default:
								$sifTask .= "<$sifField>$value</$sifField>";
								break;
						}
					}

					$sifTask .= '<ActualWork>0</ActualWork><IsRecurring>0</IsRecurring>';
					return base64_encode($sifTask);

/*						return base64_encode("<task>
					<ActualWork>0</ActualWork>
					<BillingInformation></BillingInformation>
					<Body></Body>
					<Categories></Categories>
					<Companies></Companies>
					<Complete>0</Complete>
					<DateCompleted></DateCompleted>
					<DueDate></DueDate>
					<Importance>1</Importance>
					<IsRecurring>0</IsRecurring>
					<Mileage></Mileage>
					<PercentComplete>0</PercentComplete>
					<ReminderSet>0</ReminderSet>
					<ReminderTime></ReminderTime>
					<Sensitivity>0</Sensitivity>
					<StartDate>45001231T230000Z</StartDate>
					<Status>3</Status>
					<Subject>TARAAA3</Subject>
					<TeamTask>0</TeamTask>
					<TotalWork>0</TotalWork>
					<RecurrenceType>1</RecurrenceType>
					<Interval>1</Interval>
					<MonthOfYear>0</MonthOfYear>
					<DayOfMonth>0</DayOfMonth>
					<DayOfWeekMask>4</DayOfWeekMask>
					<Instance>0</Instance>
					<PatternStartDate>20060320T230000Z</PatternStartDate>
					<NoEndDate>1</NoEndDate>
					<PatternEndDate></PatternEndDate>
					<Occurrences>10</Occurrences>
					</task>
					"); */
				}
				break;

			case 'note':
				if($taskData = $this->read($_id)) {
					$sysCharSet	= $GLOBALS['egw']->translation->charset();
					$vcal		= new Horde_iCalendar;

					$sifNote = '<note>';

					foreach($this->_sifNoteMapping as $sifField => $egwField)
					{
						if(empty($egwField)) continue;

						$value = $GLOBALS['egw']->translation->convert($taskData[$egwField], $sysCharSet, 'utf-8');

						switch($sifField) {
							case 'Date':
								if(!empty($value)) {
									$value = $vcal->_exportDateTime($value);
								}
								$sifNote .= "<$sifField>$value</$sifField>";
								break;

							case 'Body':
								$value = $GLOBALS['egw']->translation->convert($taskData['info_subject'], $sysCharSet, 'utf-8') . "\n" . $value;
								$sifNote .= "<$sifField>$value</$sifField>";
								break;

							case 'Categories':
								if (!empty($value))
								{
									$value = implode('; ', $this->get_categories(array($value)));
									$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
								}
								$sifNote .= "<$sifField>$value</$sifField>";
								break;


							default:
								$sifNote .= "<$sifField>$value</$sifField>";
								break;
						}
					}

					return base64_encode($sifNote);
				}
				break;

			default;
				return false;
		}

	}

	function exportVTODO($_taskID, $_version)
	{
		error_log(__METHOD__."called : $_version ,$_taskID");
		$taskData = $this->read($_taskID);

		$taskData = $GLOBALS['egw']->translation->convert($taskData,$GLOBALS['egw']->translation->charset(),'UTF-8');

		//_debug_array($taskData);

		$taskGUID = $GLOBALS['phpgw']->common->generate_uid('infolog_task',$_taskID);

		$vcal = new Horde_iCalendar;
		$vcal->setAttribute('VERSION',$_version);
		$vcal->setAttribute('METHOD','PUBLISH');

		$vevent = Horde_iCalendar::newComponent('VTODO',$vcal);

		$options = array();

		$vevent->setAttribute('SUMMARY',$taskData['info_subject']);
		$vevent->setAttribute('DESCRIPTION',$taskData['info_des']);
		if($taskData['info_startdate'])
			$vevent->setAttribute('DTSTART',$taskData['info_startdate']);
		if($taskData['info_enddate'])
			$vevent->setAttribute('DUE',$taskData['info_enddate']);
		$vevent->setAttribute('DTSTAMP',time());
		$vevent->setAttribute('CREATED',$GLOBALS['phpgw']->contenthistory->getTSforAction('infolog_task',$_taskID,'add'));
		$vevent->setAttribute('LAST-MODIFIED',$GLOBALS['phpgw']->contenthistory->getTSforAction('infolog_task',$_taskID,'modify'));
		$vevent->setAttribute('UID',$taskGUID);
		$vevent->setAttribute('CLASS',(($taskData['info_access'] == 'public')?'PUBLIC':'PRIVATE'));
		$vevent->setAttribute('STATUS',(($taskData['info_status'] == 'completed')?'COMPLETED':'NEEDS-ACTION'));
		// 3=urgent => 1, 2=high => 2, 1=normal => 3, 0=low => 4
		$vevent->setAttribute('PRIORITY',4-$taskData['info_priority']);

		#$vevent->setAttribute('TRANSP','OPAQUE');
		# status
		# ATTENDEE

		$options = array('CHARSET' => 'UTF-8','ENCODING' => 'QUOTED-PRINTABLE');
		$vevent->setParameter('SUMMARY', $options);
		$vevent->setParameter('DESCRIPTION', $options);

		$vcal->addComponent($vevent);

		#print "<pre>";
		#print $vcal->exportvCalendar();
		#print "</pre>";

		return $vcal->exportvCalendar();
	}

	function importVTODO(&$_vcalData, $_taskID=-1)
	{
		$botranslation  = CreateObject('phpgwapi.translation');

		$vcal = new Horde_iCalendar;
		if(!$vcal->parsevCalendar($_vcalData))
		{
			return FALSE;
		}
		$components = $vcal->getComponents();
		if(count($components) > 0)
		{
			$component = $components[0];
			if(is_a($component, 'Horde_iCalendar_vtodo'))
			{
				if($_taskID>0)
					$taskData['info_id'] = $_taskID;

				foreach($component->_attributes as $attributes)
				{
					#print $attributes['name'].' - '.$attributes['value'].'<br>';
					#$attributes['value'] = $GLOBALS['egw']->translation->convert($attributes['value'],'UTF-8');
					switch($attributes['name'])
					{
						case 'CLASS':
							$taskData['info_access']		= strtolower($attributes['value']);
							break;
						case 'DESCRIPTION':
							$taskData['info_des']			= $attributes['value'];
							break;
						case 'DUE':
							$taskData['info_enddate']		= $attributes['value'];
							break;
						case 'DTSTART':
							$taskData['info_startdate']		= $attributes['value'];
							break;
						case 'PRIORITY':
							// 1 => 3=urgent, 2 => 2=high, 3 => 1=normal, 4 => 0=low
							if (1 <= $attributes['value'] && $attributes['value'] <= 4)
							{
								$taskData['info_priority']	= 4 - $attributes['value'];
							}
							else
							{
								$taskData['info_priority']	= 1;	// default = normal
							}
							break;
						case 'STATUS':
							$taskData['info_status']		= (strtolower($attributes['value']) == 'completed') ? 'done' : 'ongoing';
							break;
						case 'SUMMARY':
							$taskData['info_subject']		= $attributes['value'];
							break;
					}
				}
				#_debug_array($eventData);exit;
				return $this->write($taskData);
			}
		}

		return FALSE;
	}
}
