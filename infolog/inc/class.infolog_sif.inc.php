<?php
/**
 * InfoLog -  SIF Parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package infolog
 * @subpackage syncml
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

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
		'ActualWork'			=> '',
		'BillingInformation'	=> '',
		'Body'					=> 'info_des',
		'Categories'			=> 'info_cat',
		'Companies'				=> '',
		'Complete'				=> 'complete',
		'DateCompleted'			=> 'info_datecompleted',
		'DueDate'				=> 'info_enddate',
		'Importance'			=> 'info_priority',
		'IsRecurring'			=> '',
		'Mileage'				=> '',
		'PercentComplete'		=> 'info_percent',
		'ReminderSet'			=> '',
		'ReminderTime'			=> '',
		'Sensitivity'			=> 'info_access',
		'StartDate'				=> 'info_startdate',
		'Status'				=> 'info_status',
		'Subject'				=> 'info_subject',
		'TeamTask'				=> '',
		'TotalWork'				=> '',
		'RecurrenceType'		=> '',
		'Interval'				=> '',
		'MonthOfYear'			=> '',
		'DayOfMonth'			=> '',
		'DayOfWeekMask'			=> '',
		'Instance'				=> '',
		'PatternStartDate'		=> '',
		'NoEndDate'				=> '',
		'PatternEndDate'		=> '',
		'Occurrences'			=> '',
	);

	// standard headers
    const xml_decl = '<?xml version="1.0" encoding="UTF-8"?>';
	const SIF_decl = '<SIFVersion>1.1</SIFVersion>';

	/**
	* name and version of the sync-client
	*
	* @var string
	*/
	var $productName = 'mozilla plugin';
	var $productSoftwareVersion = '0.3';

	/**
	* Shall we use the UID extensions of the description field?
	*
	* @var boolean
	*/
	var $uidExtension = false;


	function startElement($_parser, $_tag, $_attributes)
	{
		// nothing to do
	}

	function endElement($_parser, $_tag)
	{
		#error_log("infolog: tag=$_tag data=".trim($this->sifData));
		if (!empty($this->_currentSIFMapping[$_tag]))
		{
			$this->_extractedSIFData[$this->_currentSIFMapping[$_tag]] = trim($this->sifData);
		}
		unset($this->sifData);
	}

	function characterData($_parser, $_data)
	{
		$this->sifData .= $_data;
	}

	/**
	 * Convert SIF data into a eGW infolog entry
	 *
	 * @param string $sifData 	the SIF data
	 * @param string $_sifType	type (note/task)
	 * @param int $_id=-1		the infolog id
	 * @return array infolog entry or false on error
	 */
	function siftoegw($sifData, $_sifType, $_id=-1)
	{
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

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

		if (!$this->strXmlData)
		{
			error_log(sprintf("XML error: %s at line %d",
				xml_error_string(xml_get_error_code($this->xml_parser)),
				xml_get_current_line_number($this->xml_parser)));
			return false;
		}

		if (!array($this->_extractedSIFData)) return false;

		switch ($_sifType)
		{
			case 'task':
				$taskData	= array();
				$vcal		= new Horde_iCalendar;

				$taskData['info_type'] = 'task';
				$taskData['info_status'] = 'not-started';

				if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
				{
					$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
				}
				else
				{
					$minimum_uid_length = 8;
				}

				foreach ($this->_extractedSIFData as $key => $value)
				{
					$value = preg_replace('/<\!\[CDATA\[(.+)\]\]>/Usim', '$1', $value);
					$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);
					#error_log("infolog key=$key => value=$value");
					if (empty($value)) continue;

					switch($key)
					{
						case 'info_access':
							$taskData[$key] = ((int)$value > 0) ? 'private' : 'public';
							break;

						case 'info_datecompleted':
						case 'info_enddate':
						case 'info_startdate':
							if (!empty($value))
							{
								$taskData[$key] = $vcal->_parseDateTime($value);
								// somehow the client always deliver a timestamp about 3538 seconds, when no startdate set.
								if ($taskData[$key] < 10000) unset($taskData[$key]);
							}
							break;


						case 'info_cat':
							if (!empty($value))
							{
								$categories = $this->find_or_add_categories(explode(';', $value), $_id);
								$taskData['info_cat'] = $categories[0];
							}
							break;

						case 'info_priority':
							$taskData[$key] = (int)$value;
							break;

						case 'info_status':
							switch ($value)
							{
								case '0':
									$taskData[$key] = 'not-started';
									break;
								case '1':
									$taskData[$key] = 'ongoing';
									break;
								case '2':
									$taskData[$key] = 'done';
									$taskData['info_percent'] = 100;
									break;
								case '3':
									$taskData[$key] = 'waiting';
									break;
								case '4':
									if ($this->productName == 'blackberry plug-in')
									{
										$taskData[$key] = 'deferred';
									}
									else
									{
										$taskData[$key] = 'cancelled';
									}
									break;
								default:
									$taskData[$key] = 'ongoing';
									break;
							}
							break;

						case 'complete':
							$taskData['info_status'] = 'done';
							$taskData['info_percent'] = 100;
							break;

						case 'info_des':
							// extract our UID and PARENT_UID information
							if (preg_match('/\s*\[UID:(.+)?\]/Usm', $value, $matches))
							{
								if (strlen($matches[1]) >= $minimum_uid_length)
								{
									$taskData['info_uid'] = $matches[1];
								}
								//$value = str_replace($matches[0], '', $value);
							}
							if (preg_match('/\s*\[PARENT_UID:(.+)?\]/Usm', $value, $matches))
							{
								if (strlen($matches[1]) >= $minimum_uid_length)
								{
									$taskData['info_id_parent'] = $this->getParentID($matches[1]);
								}
								//$value = str_replace($matches[0], '', $value);
							}

						default:
							$taskData[$key] = $value;
							break;
					}
					#error_log("infolog task key=$key => value=" . $taskData[$key]);
				}

				return $taskData;
				break;

			case 'note':
				$noteData = array();
				$noteData['info_type'] = 'note';
				$vcal		= new Horde_iCalendar;

				foreach ($this->_extractedSIFData as $key => $value)
				{
					$value = preg_replace('/<\!\[CDATA\[(.+)\]\]>/Usim', '$1', $value);
					$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);

					#error_log("infolog client key=$key => value=" . $value);
					switch ($key)
					{
						case 'info_startdate':
							if (!empty($value))
							{
								$noteData[$key] = $vcal->_parseDateTime($value);
								// somehow the client always deliver a timestamp about 3538 seconds, when no startdate set.
								if ($noteData[$key] < 10000) $noteData[$key] = '';
							}
							else
							{
								$noteData[$key] = '';
							}
							break;

						case 'info_cat':
							if (!empty($value))
							{
								$categories = $this->find_or_add_categories(explode(';', $value), $_id);
								$noteData['info_cat'] = $categories[0];
							}
							break;

						default:
							$noteData[$key] = $value;
							break;
					}
					#error_log("infolog note key=$key => value=".$noteData[$key]);
				}
				return $noteData;
				break;


			default:
				return false;
		}
	}

	/**
	 * Search for SIF data a matching infolog entry
	 *
	 * @param string $sifData		the SIF data
	 * @param string $_sifType		type (note/task)
	 * @param int $contentID=null 	infolog_id (or null, if unkown)
	 * @param boolean $relax=false 	if true, a weaker match algorithm is used
	 * @return infolog_id of a matching entry or false, if nothing was found
	 */
	function searchSIF($_sifData, $_sifType, $contentID=null, $relax=false)
	{
		if (!($egwData = $this->siftoegw($_sifData, $_sifType, $contentID))) return false;

		if ($contentID) $egwData['info_id'] = $contentID;

		if ($_sifType == 'task') return $this->findVTODO($egwData, $relax);

		if ($_sifType == 'note') unset($egwData['info_startdate']);

		$filter = array();

		$filter['col_filter'] = $egwData;

		if ($foundItems = $this->search($filter))
		{
			if (count($foundItems) > 0)
			{
				$itemIDs = array_keys($foundItems);
				return $itemIDs[0];
			}
		}

		return false;
	}

	/**
	 * Add SIF data entry
	 *
	 * @param string $sifData		the SIF data
	 * @param string $_sifType		type (note/task)
	 * @param boolean $merge=false	reserved for future use
	 * @return infolog_id of the new entry or false, for errors
	 */
	function addSIF($_sifData, $_id, $_sifType, $merge=false)
	{
		if (!($egwData = $this->siftoegw($_sifData, $_sifType, $_id))) return false;

		if ($_id > 0) $egwData['info_id'] = $_id;

		if (empty($taskData['info_datecompleted']))
		{
			$taskData['info_datecompleted'] = 0;
		}

		$egwID = $this->write($egwData, false, true, false);

		return $egwID;
	}


	/**
	 * Export an infolog entry as SIF data
	 *
	 * @param int $_id			the infolog_id of the entry
	 * @param string $_sifType	type (note/task)
	 * @return string SIF representation of the infolog entry
	 */
	function getSIF($_id, $_sifType)
	{
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

		switch($_sifType)
		{
			case 'task':
				if (($taskData = $this->read($_id, true, 'server')))
				{
					$vcal		= new Horde_iCalendar('1.0');

					if ($taskData['info_id_parent'])
					{
						$parent = $this->read($taskData['info_id_parent']);
						$taskData['info_id_parent'] = $parent['info_uid'];
					}
					else
					{
						$taskData['info_id_parent'] = '';
					}

					if (!preg_match('/\[UID:.+\]/m', $taskData['info_des']))
					{
						$taskData['info_des'] .= "\r\n[UID:" . $taskData['info_uid'] . "]";
						if ($taskData['info_id_parent'] != '')
						{
							$taskData['info_des'] .= "\r\n[PARENT_UID:" . $taskData['info_id_parent'] . "]";
						}
					}

					$sifTask = self::xml_decl . "\n<task>" . self::SIF_decl;

					foreach ($this->_sifTaskMapping as $sifField => $egwField)
					{
						if (empty($egwField)) continue;

						$value = $GLOBALS['egw']->translation->convert($taskData[$egwField], $sysCharSet, 'utf-8');

						switch ($sifField)
						{

							case 'Complete':
								// is handled with DateCompleted
								break;

							case 'DateCompleted':
								if ($taskData[info_status] == 'done')
								{
									$sifTask .= "<Complete>1</Complete>";
								}
								else
								{
									$sifTask .= "<DateCompleted></DateCompleted><Complete>0</Complete>";
									continue;
								}
							case 'DueDate':
								if (!empty($value))
								{
									$hdate	= new Horde_Date($value);
									$value = $vcal->_exportDate($hdate, '000000Z');
									$sifTask .= "<$sifField>$value</$sifField>";
								}
								else
								{
									$sifTask .= "<$sifField></$sifField>";
								}
								break;
							case 'StartDate':
								if (!empty($value))
								{
									$value = $vcal->_exportDateTime($value);
									$sifTask .= "<$sifField>$value</$sifField>";
								}
								else
								{
									$sifTask .= "<$sifField></$sifField>";
								}
								break;

							case 'Importance':
								if ($value > 3) $value = 3;
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Sensitivity':
								$value = ($value == 'private' ? '2' : '0');
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Status':
								switch ($value)
								{
									case 'cancelled':
									case 'deferred':
										$value = '4';
										break;
									case 'waiting':
									case 'nonactive':
										$value = '3';
										break;
									case 'done':
									case 'archive':
									case 'billed':
										$value = '2';
										break;
									case 'not-started':
									case 'template':
										$value = '0';
										break;
									default: //ongoing
										$value = 1;
										break;
								}
								$sifTask .= "<$sifField>$value</$sifField>";
								break;

							case 'Categories':
								if (!empty($value) && $value)
								{
									$value = implode('; ', $this->get_categories(array($value)));
									$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
								}
								else
								{
									break;
								}

							default:
								$value = @htmlspecialchars($value, ENT_QUOTES, 'utf-8');
								$sifTask .= "<$sifField>$value</$sifField>";
								break;
						}
					}
					$sifTask .= '<ActualWork>0</ActualWork><IsRecurring>0</IsRecurring></task>';
					return $sifTask;
				}
				break;

			case 'note':
				if (($taskData = $this->read($_id, true, 'server')))
				{
					$vcal		= new Horde_iCalendar('1.0');

					$sifNote = self::xml_decl . "\n<note>" . self::SIF_decl;

					foreach ($this->_sifNoteMapping as $sifField => $egwField)
					{
						if(empty($egwField)) continue;

						$value = $GLOBALS['egw']->translation->convert($taskData[$egwField], $sysCharSet, 'utf-8');

						switch ($sifField)
						{
							case 'Date':
								if (!empty($value))
								{
									$value = $vcal->_exportDateTime($value);
								}
								$sifNote .= "<$sifField>$value</$sifField>";
								break;

							case 'Categories':
								if (!empty($value))
								{
									$value = implode('; ', $this->get_categories(array($value)));
									$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
								}
								else
								{
									break;
								}

							default:
								$value = @htmlspecialchars($value, ENT_QUOTES, 'utf-8');
								$sifNote .= "<$sifField>$value</$sifField>";
								break;
						}
					}
					$sifNote .= '</note>';
					return $sifNote;
				}
				break;

			default;
				return false;
		}

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
		$state = &$_SESSION['SyncML.state'];
		$deviceInfo = $state->getClientDeviceInfo();

		if (isset($deviceInfo) && is_array($deviceInfo))
		{
			if (isset($deviceInfo['uidExtension']) &&
				$deviceInfo['uidExtension'])
			{
					$this->uidExtension = true;
			}
		}
		// store product name and version, to be able to use it elsewhere
		if ($_productName)
		{
			$this->productName = strtolower($_productName);
			if (preg_match('/^[^\d]*(\d+\.?\d*)[\.|\d]*$/', $_productSoftwareVersion, $matches))
			{
				$this->productSoftwareVersion = $matches[1];
			}
		}
	}
}
