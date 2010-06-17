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

	/**
	 * user preference: Use this timezone for import from and export to device
	 *
	 * @var string
	 */
	var $tzid = null;

	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	var $logfile="/tmp/log-infolog-sif";

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
		if ($this->log) $this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-infolog-sif";
		$this->vCalendar = new Horde_iCalendar;
	}

	/**
	 * Get DateTime value for a given time and timezone
	 *
	 * @param int|string|DateTime $time in server-time as returned by calendar_bo for $data_format='server'
	 * @param string $tzid TZID of event or 'UTC' or NULL for palmos timestamps in usertime
	 *
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
		// check for date --> export it as such
		if ($time->format('Hi') == '0000')
		{
			$arr = egw_time::to($time, 'array');
			$time = new egw_time($arr, self::$tz_cache[$tzid]);
			$value = $time->format('Y-m-d');
		}
		else
		{
			$time->setTimezone(self::$tz_cache[$tzid]);
			$value = $time->format('Ymd\THis');
		}
		return $value;
	}

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

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($_sifType, $_id)\n" .
				array2string($sifData) . "\n", 3, $this->logfile);
		}

		$sysCharSet	= $GLOBALS['egw']->translation->charset();

		switch ($_sifType)
		{
			case 'note':
				$this->_currentSIFMapping = $this->_sifNoteMapping;
				break;

			case 'task':
				$this->_currentSIFMapping = $this->_sifTaskMapping;
				break;

			default:
				// we don't know how to handle this
				return false;
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

		if (!array($this->_extractedSIFData))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()[PARSER FAILD]\n",
					3, $this->logfile);
			}
			return false;
		}
		$infoData = array();

		switch ($_sifType)
		{
			case 'task':
				$infoData['info_type'] = 'task';
				$infoData['info_status'] = 'not-started';

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
							$infoData[$key] = ((int)$value > 0) ? 'private' : 'public';
							break;

						case 'info_datecompleted':
						case 'info_enddate':
						case 'info_startdate':
							if (!empty($value))
							{
								$infoData[$key] = $this->vCalendar->_parseDateTime($value);
								// somehow the client always deliver a timestamp about 3538 seconds, when no startdate set.
								if ($infoData[$key] < 10000) unset($infoData[$key]);
							}
							break;


						case 'info_cat':
							if (!empty($value))
							{
								$categories = $this->find_or_add_categories(explode(';', $value), $_id);
								$infoData['info_cat'] = $categories[0];
							}
							break;

						case 'info_priority':
							$infoData[$key] = (int)$value;
							break;

						case 'info_status':
							switch ($value)
							{
								case '0':
									$infoData[$key] = 'not-started';
									break;
								case '1':
									$infoData[$key] = 'ongoing';
									break;
								case '2':
									$infoData[$key] = 'done';
									$infoData['info_percent'] = 100;
									break;
								case '3':
									$infoData[$key] = 'waiting';
									break;
								case '4':
									if ($this->productName == 'blackberry plug-in')
									{
										$infoData[$key] = 'deferred';
									}
									else
									{
										$infoData[$key] = 'cancelled';
									}
									break;
								default:
									$infoData[$key] = 'ongoing';
							}
							break;

						case 'complete':
							$infoData['info_status'] = 'done';
							$infoData['info_percent'] = 100;
							break;

						case 'info_des':
							// extract our UID and PARENT_UID information
							if (preg_match('/\s*\[UID:(.+)?\]/Usm', $value, $matches))
							{
								if (strlen($matches[1]) >= $minimum_uid_length)
								{
									$infoData['info_uid'] = $matches[1];
								}
								//$value = str_replace($matches[0], '', $value);
							}
							if (preg_match('/\s*\[PARENT_UID:(.+)?\]/Usm', $value, $matches))
							{
								if (strlen($matches[1]) >= $minimum_uid_length)
								{
									$infoData['info_id_parent'] = $this->getParentID($matches[1]);
								}
								//$value = str_replace($matches[0], '', $value);
							}

						default:
							$infoData[$key] = str_replace("\r\n", "\n", $value);
					}
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
							"key=$key => value=" . $infoData[$key] . "\n", 3, $this->logfile);
					}
				}
				if (empty($infoData['info_datecompleted']))
				{
					$infoData['info_datecompleted'] = 0;
				}
				break;

			case 'note':
				$infoData['info_type'] = 'note';

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
								$infoData[$key] = $this->vCalendar->_parseDateTime($value);
								// somehow the client always deliver a timestamp about 3538 seconds, when no startdate set.
								if ($infoData[$key] < 10000) $infoData[$key] = '';
							}
							else
							{
								$infoData[$key] = '';
							}
							break;

						case 'info_cat':
							if (!empty($value))
							{
								$categories = $this->find_or_add_categories(explode(';', $value), $_id);
								$infoData['info_cat'] = $categories[0];
							}
							break;

						default:
							$infoData[$key] = str_replace("\r\n", "\n", $value);
					}
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
							"key=$key => value=" . $infoData[$key] . "\n", 3, $this->logfile);
					}
				}
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($infoData) . "\n", 3, $this->logfile);
		}
		return $infoData;
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
		if (!($egwData = $this->siftoegw($_sifData, $_sifType, $contentID))) return array();

		if ($contentID) $egwData['info_id'] = $contentID;

		if ($_sifType == 'note') unset($egwData['info_startdate']);

		return $this->findInfo($egwData, $relax, $this->tzid);
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
		if ($this->tzid)
		{
			date_default_timezone_set($this->tzid);
		}
		$egwData = $this->siftoegw($_sifData, $_sifType, $_id);
		if ($this->tzid)
		{
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
		}
		if (!$egwData) return false;

		if ($_id > 0) $egwData['info_id'] = $_id;

		return $this->write($egwData, true, true, false);
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

		if (!($infoData = $this->read($_id, true, 'server'))) return false;

		switch($_sifType)
		{
			case 'task':
				if ($infoData['info_id_parent'])
				{
					$parent = $this->read($infoData['info_id_parent']);
					$infoData['info_id_parent'] = $parent['info_uid'];
				}
				else
				{
					$infoData['info_id_parent'] = '';
				}

				if (!preg_match('/\[UID:.+\]/m', $infoData['info_des']))
				{
					$infoData['info_des'] .= "\r\n[UID:" . $infoData['info_uid'] . "]";
					if ($infoData['info_id_parent'] != '')
					{
						$infoData['info_des'] .= "\r\n[PARENT_UID:" . $infoData['info_id_parent'] . "]";
					}
				}

				$sifTask = self::xml_decl . "\n<task>" . self::SIF_decl;

				foreach ($this->_sifTaskMapping as $sifField => $egwField)
				{
					if (empty($egwField)) continue;

					$value = $GLOBALS['egw']->translation->convert($infoData[$egwField], $sysCharSet, 'utf-8');

					switch ($sifField)
					{

						case 'Complete':
							// is handled with DateCompleted
							break;

						case 'DateCompleted':
							if ($infoData[info_status] != 'done')
							{
								$sifTask .= "<DateCompleted></DateCompleted><Complete>0</Complete>";
								continue;
							}
							$sifTask .= "<Complete>1</Complete>";

						case 'DueDate':
						case 'StartDate':
							$sifTask .= "<$sifField>";
							if (!empty($value))
							{
								$sifTask .= $this->getDateTime($value, $this->tzid);
							}
							$sifTask .= "</$sifField>";
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
							$value = @htmlspecialchars($value, ENT_NOQUOTES, 'utf-8');
						$sifTask .= "<$sifField>$value</$sifField>";
						break;
					}
				}
				$sifTask .= '<ActualWork>0</ActualWork><IsRecurring>0</IsRecurring></task>';
				return $sifTask;

			case 'note':
				$sifNote = self::xml_decl . "\n<note>" . self::SIF_decl;

				foreach ($this->_sifNoteMapping as $sifField => $egwField)
				{
					if(empty($egwField)) continue;

					$value = $GLOBALS['egw']->translation->convert($infoData[$egwField], $sysCharSet, 'utf-8');

					switch ($sifField)
					{
						case 'Date':
							$sifNote .= "<$sifField>";
							if (!empty($value))
							{
								$sifNote .= $this->getDateTime($value, $this->tzid);
							}
							$sifNote .= "</$sifField>";
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
		return false;
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
			if (isset($deviceInfo['tzid']) &&
				$deviceInfo['tzid'])
			{
				switch ($deviceInfo['tzid'])
				{
					case -1:
						$this->tzid = false;
						break;
					case -2:
						$this->tzid = null;
						break;
					default:
						$this->tzid = $deviceInfo['tzid'];
				}
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
