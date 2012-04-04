<?php
/**
 * Addressbook - vCard / iCal parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package addressbook
 * @subpackage export
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';
require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/SyncML/State.php');

/**
 * Addressbook - vCard parser
 *
 */
class addressbook_vcal extends addressbook_bo
{
	/**
	 * product manufacturer from setSupportedFields (lowercase!)
	 *
	 * @var string
	 */
	var $productManufacturer = 'file';
	/**
	 * product name from setSupportedFields (lowercase!)
	 *
	 * @var string
	 */
	var $productName;
	/**
	 * supported fields for vCard file and groupdav import/export
	 *
	 * @var array
	 */
	var $supportedFields = array( // all entries e.g. for groupdav
			'ADR;WORK'			=> array('','adr_one_street2','adr_one_street','adr_one_locality','adr_one_region',
									'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'			=> array('','adr_two_street2','adr_two_street','adr_two_locality','adr_two_region',
									'adr_two_postalcode','adr_two_countryname'),
			'BDAY'				=> array('bday'),
			'CLASS'				=> array('private'),
			'CATEGORIES'		=> array('cat_id'),
			'EMAIL;WORK'		=> array('email'),
			'EMAIL;HOME'		=> array('email_home'),
			'N'					=> array('n_family','n_given','n_middle',
									'n_prefix','n_suffix'),
			'FN'				=> array('n_fn'),
			'NOTE'				=> array('note'),
			'ORG'				=> array('org_name','org_unit','room'),
			'TEL;CELL;WORK'		=> array('tel_cell'),
			'TEL;CELL;HOME'		=> array('tel_cell_private'),
			'TEL;CAR'			=> array('tel_car'),
			'TEL;OTHER'			=> array('tel_other'),
			'TEL;VOICE;WORK'	=> array('tel_work'),
			'TEL;FAX;WORK'		=> array('tel_fax'),
			'TEL;HOME;VOICE'	=> array('tel_home'),
			'TEL;FAX;HOME'		=> array('tel_fax_home'),
			'TEL;PAGER'			=> array('tel_pager'),
			'TITLE'				=> array('title'),
			'URL;WORK'			=> array('url'),
			'URL;HOME'			=> array('url_home'),
			'ROLE'				=> array('role'),
			'NICKNAME'			=> array('label'),
			'FBURL'				=> array('freebusy_uri'),
			'PHOTO'				=> array('jpegphoto'),
			'X-ASSISTANT'		=> array('assistent'),
			'X-ASSISTANT-TEL'	=> array('tel_assistent'),
			'UID'				=> array('uid'),
			'REV'				=> array('modified'),
			//set for Apple: 'X-ABSHOWAS' => array('fileas_type'),	// Horde vCard class uses uppercase prop-names!
		);

	/**
	 * VCard version
	 *
	 * @var string
	 */
	var $version;
	/**
	 * Client CTCap Properties
	 *
	 * @var array
	 */
	var $clientProperties;
	/**
	* Set Logging
	*
	* @var string
	* off = false;
	*/
	var $log = false;
	var $logfile="/tmp/log-vcard";
	/**
	* Constructor
	*
	* @param string $contact_app			the current application
	* @param string	$_contentType			the content type (version)
	* @param array $_clientProperties		client properties
	*/
	function __construct($contact_app='addressbook', $_contentType='text/x-vcard', &$_clientProperties = array())
	{
		parent::__construct($contact_app);
		if ($this->log)
		{
			$this->logfile = $GLOBALS['egw_info']['server']['temp_dir']."/log-vcard";
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($_contentType)."\n",3,$this->logfile);
		}
		switch($_contentType)
		{
			case 'text/vcard':
				$this->version = '3.0';
				break;
			default:
				$this->version = '2.1';
			break;
		}
		$this->clientProperties = $_clientProperties;
	}
	/**
	* import a vard into addressbook
	*
	* @param string	$_vcard		the vcard
	* @param int/string	$_abID=null		the internal addressbook id or !$_abID for a new enty
	* @param boolean $merge=false	merge data with existing entry
	* @param string $charset  The encoding charset for $text. Defaults to
    *                         utf-8 for new format, iso-8859-1 for old format.
	* @return int contact id
	*/
	function addVCard($_vcard, $_abID=null, $merge=false, $charset=null)
	{
		if (!($contact = $this->vcardtoegw($_vcard, $charset))) return false;

		if ($_abID)
		{
			if (($old_contact = $this->read($_abID)))
			{
				if ($merge)
				{
					foreach ($contact as $key => $value)
					{
						if (!empty($old_contact[$key]))
						{
							$contact[$key] = $old_contact[$key];
						}
					}
				}
				else
				{
					if (isset($old_contact['account_id']))
					{
						$contact['account_id'] = $old_contact['account_id'];
					}
					if (is_array($contact['cat_id']))
					{
						$contact['cat_id'] = implode(',',$this->find_or_add_categories($contact['cat_id'], $_abID));
					}
					else
					{
						// restore from orignal
						$contact['cat_id'] = $old_contact['cat_id'];
					}
				}
			}
			// update entry
			$contact['id'] = $_abID;
		}
		else
    	{
    		if (is_array($contact['cat_id']))
			{
				$contact['cat_id'] = implode(',',$this->find_or_add_categories($contact['cat_id'], -1));
			}
    	}
    	if (isset($contact['owner']) && $contact['owner'] != $this->user)
    	{
    		$contact['private'] = 0;	// foreign contacts are never private!
    	}
    	if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($contact)."\n",3,$this->logfile);
		}
		return $this->save($contact);
	}

	/**
	* return a vcard
	*
	* @param int/string	$_id the id of the contact
	* @param string $_charset='UTF-8' encoding of the vcard, default UTF-8
	* @param boolean $extra_charset_attribute=true GroupDAV/CalDAV dont need the charset attribute and some clients have problems with it
	* @return string containing the vcard
	*/
	function getVCard($_id,$_charset='UTF-8',$extra_charset_attribute=true)
	{
		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar/vcard.php');

		#Horde::logMessage("vCalAddressbook clientProperties:\n" . print_r($this->clientProperties, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$vCard = new Horde_iCalendar_vcard($this->version);
		$vCard->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware Addressbook '.$GLOBALS['egw_info']['apps']['phpgwapi']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));

		$sysCharSet = translation::charset();

		// KAddressbook and Funambol4BlackBerry always requires non-ascii chars to be qprint encoded.
		if ($this->productName == 'kde' ||
			($this->productManufacturer == 'funambol' && $this->productName == 'blackberry plug-in'))
		{
			$extra_charset_attribute = true;
		}

		if (!($entry = $this->read($_id)))
		{
			return false;
		}

		$this->fixup_contact($entry);

		foreach ($this->supportedFields as $vcardField => $databaseFields)
		{
			$values = array();
			$options = array();
			$hasdata = 0;
			// seperate fields from their options/attributes
			$vcardFields = explode(';', $vcardField);
			$vcardField = $vcardFields[0];
			$i = 1;
			while (isset($vcardFields[$i]))
			{
				list($oname, $oval) = explode('=', $vcardFields[$i]);
				if (!$oval && ($this->version == '3.0'))
				{
					// declare OPTION as TYPE=OPTION
					$options['TYPE'][] = $oname ;
				}
				else
				{
					$options[$oname] = $oval;
				}
				$i++;
			}
			if (is_array($options['TYPE']))
			{
				$oval = implode(",", $options['TYPE']);
				unset($options['TYPE']);
				$options['TYPE'] = $oval;
			}
			if (isset($this->clientProperties[$vcardField]['Size']))
			{
				$size = $this->clientProperties[$vcardField]['Size'];
				$noTruncate = $this->clientProperties[$vcardField]['NoTruncate'];
				if ($this->log && $size > 0)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
						"() $vcardField Size: $size, NoTruncate: " .
						($noTruncate ? 'TRUE' : 'FALSE') . "\n",3,$this->logfile);
				}
				//Horde::logMessage("vCalAddressbook $vcardField Size: $size, NoTruncate: " .
				//	($noTruncate ? 'TRUE' : 'FALSE'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			}
			else
			{
				$size = -1;
				$noTruncate = false;
			}
			foreach ($databaseFields as $databaseField)
			{
				$value = '';

				if (!empty($databaseField))
				{
					$value = trim($entry[$databaseField]);
				}

				switch ($databaseField)
				{
					case 'modified':
						$value = gmdate("Y-m-d\TH:i:s\Z",egw_time::user2server($value));
						$hasdata++;
						break;

					case 'private':
						$value = $value ? 'PRIVATE' : 'PUBLIC';
						$hasdata++;
						break;

					case 'bday':
						if (!empty($value))
						{
							if ($size == 8)
							{
								$value = str_replace('-','',$value);
							}
							elseif (isset($options['TYPE']) && (
								$options['TYPE'] == 'BASIC'))
							{
								unset($options['TYPE']);
								// used by old SyncML implementations
								$value = str_replace('-','',$value).'T000000Z';
							}
							$hasdata++;
						}
						break;

					case 'jpegphoto':
						if (!empty($value) &&
								(($size < 0) || (strlen($value) < $size)))
						{
							if (!isset($options['TYPE']))
							{
								$options['TYPE'] = 'JPEG';
							}
							if (!isset($options['ENCODING']))
							{
								$options['ENCODING'] = 'BASE64';
							}
							$hasdata++;
						}
						else
						{
							$value = '';
						}
						break;

					case 'cat_id':
						if (!empty($value) && ($values = /*str_replace(',','\\,',*/$this->get_categories($value)))//)
						{
							$values = (array) translation::convert($values, $sysCharSet, $_charset);
							$value = implode(',', $values); // just for the CHARSET recognition
							if (($size > 0) && strlen($value) > $size)
							{
								// let us try with only the first category
								$value = $values[0];
								if (strlen($value) > $size)
								{
									if ($this->log)
									{
										error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
										"() $vcardField omitted due to maximum size $size\n",3,$this->logfile);
									}
									// Horde::logMessage("vCalAddressbook $vcardField omitted due to maximum size $size",
									//		__FILE__, __LINE__, PEAR_LOG_WARNING);
									continue;
								}
								$values = array();
							}
							if (preg_match('/[^\x20-\x7F]/', $value))
							{
								if ($extra_charset_attribute || $this->productName == 'kde')
								{
									$options['CHARSET'] = $_charset;
								}
								// KAddressbook requires non-ascii chars to be qprint encoded, other clients eg. nokia phones have trouble with that
								if ($this->productName == 'kde')
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								elseif ($this->productManufacturer == 'funambol')
								{
										$options['ENCODING'] = 'FUNAMBOL-QP';
								}
								elseif (preg_match('/([\000-\012\015\016\020-\037\075])/', $value))
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								elseif (!$extra_charset_attribute)
								{
									$options['ENCODING'] = '';
								}
							}
							$hasdata++;
						}
						break;

					case 'n_fn':
					case 'fileas_type':
						// mark entries with fileas_type == 'org_name' as X-ABSHOWAS:COMPANY (Apple AB specific)
						if (isset($this->supportedFields['X-ABSHOWAS']) &&
							$entry['org_name'] == $entry['n_fileas'] && $entry['fileas_type'] == 'org_name')
						{
							if ($vcardField == 'X-ABSHOWAS') $value = 'COMPANY';
							if ($databaseField == 'n_fn') $value = $entry['org_name'];
						}
						//error_log("vcardField='$vcardField', databaseField='$databaseField', this->supportedFields['X-ABSHOWAS']=".array2string($this->supportedFields['X-ABSHOWAS'])." --> value='$value'");
						// fall-through

					default:
						if (($size > 0) && strlen(implode(',', $values) . $value) > $size)
						{
							if ($noTruncate)
							{
								if ($this->log)
								{
									error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
										"() $vcardField omitted due to maximum size $size\n",3,$this->logfile);
								}
								// Horde::logMessage("vCalAddressbook $vcardField omitted due to maximum size $size",
								//		__FILE__, __LINE__, PEAR_LOG_WARNING);
								continue;
							}
							// truncate the value to size
							$cursize = strlen(implode('', $values));
							$left = $size - $cursize - count($databaseFields) + 1;
							if ($left > 0)
							{
								$value = substr($value, 0, $left);
							}
							else
							{
								$value = '';
							}
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
									"() $vcardField truncated to maximum size $size\n",3,$this->logfile);
							}
							//Horde::logMessage("vCalAddressbook $vcardField truncated to maximum size $size",
							//		__FILE__, __LINE__, PEAR_LOG_INFO);
						}
						if (!empty($value) // required field
							|| in_array($vcardField,array('FN','ORG','N'))
							|| ($size >= 0 && !$noTruncate))
						{
							$value = translation::convert(trim($value), $sysCharSet, $_charset);
							$values[] = $value;
							if (preg_match('/[^\x20-\x7F]/', $value))
							{
								if ($extra_charset_attribute || $this->productName == 'kde')
								{
									$options['CHARSET'] = $_charset;
								}
								// KAddressbook requires non-ascii chars to be qprint encoded, other clients eg. nokia phones have trouble with that
								if ($this->productName == 'kde')
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								elseif ($this->productManufacturer == 'funambol')
								{
									$options['ENCODING'] = 'FUNAMBOL-QP';
								}
								elseif (preg_match('/([\000-\012\015\016\020-\037\075])/', $value))
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
								elseif (!$extra_charset_attribute)
								{
									$options['ENCODING'] = '';
								}
							}
							if ($vcardField == 'TEL' && $entry['tel_prefer'] &&
								($databaseField == $entry['tel_prefer']))
							{
								if ($options['TYPE'])
								{
									$options['TYPE'] .= ',';
								}
								$options['TYPE'] .= 'PREF';
							}
							$hasdata++;
						}
						else
						{
							$values[] = '';
						}
						break;
				}
			}

			if ($hasdata <= 0)
			{
				// don't add the entry if there is no data for this field,
				// except it's a mendatory field
				continue;
			}

			$vCard->setAttribute($vcardField, $value, $options, true, $values);
		}

		$result = $vCard->exportvCalendar($_charset);
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__ .
				"() '$this->productManufacturer','$this->productName'\n",3,$this->logfile);
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($result)."\n",3,$this->logfile);
		}
		return $result;
	}

	function search($_vcard, $contentID=null, $relax=false, $charset=null)
	{
		$result = array();

		if (($contact = $this->vcardtoegw($_vcard, $charset)))
		{
			if (is_array($contact['category']))
			{
					$contact['category'] = implode(',',$this->find_or_add_categories($contact['category'],
						$contentID ? $contentID : -1));
			}
			if ($contentID)
			{
				$contact['id'] = $contentID;
			}
			$result = $this->find_contact($contact, $relax);
		}
		return $result;
	}

	function setSupportedFields($_productManufacturer='file', $_productName='', $_supportedFields = null)
	{

		$this->productManufacturer = strtolower($_productManufacturer);
		$this->productName = strtolower($_productName);

		if (is_array($_supportedFields)) $this->supportedFields = $_supportedFields;
	}

	/**
     * Parses a string containing vCard data.
     *
     * @param string $_vcard   The data to parse.
     * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
     *
     * @return array|boolean   The contact data or false on errors.
     */
	function vcardtoegw($_vcard, $charset=null)
	{
		// the horde class does the charset conversion. DO NOT CONVERT HERE.
		// be as flexible as possible

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($_vcard)."\n",3,$this->logfile);
		}

		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php');

		$container = false;
		$vCard = Horde_iCalendar::newComponent('vcard', $container);

		if (!$vCard->parsevCalendar($_vcard, 'VCARD', $charset))
		{
			return False;
		}
		$vcardValues = $vCard->getAllAttributes();

		if (!empty($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		#print "<pre>$_vcard</pre>";

		#error_log(print_r($vcardValues, true));
		//Horde::logMessage("vCalAddressbook vcardtoegw: " . print_r($vcardValues, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$email = 1;
		$tel = 1;
		$cell = 1;
		$url = 1;
		$pref_tel = false;

		$rowNames = array();
		foreach($vcardValues as $key => $vcardRow)
		{
			$rowName  = strtoupper($vcardRow['name']);
			if ($vcardRow['value'] == ''  && implode('', $vcardRow['values']) == '')
			{
				unset($vcardRow);
				continue;
			}
			$rowTypes = array();

			$vcardRow['uparams'] = array();
			foreach ($vcardRow['params'] as $pname => $params)
			{
				$pname = strtoupper($pname);
				$vcardRow['uparams'][$pname] = $params;
			}


			// expand 3.0 TYPE paramters to 2.1 qualifiers
			$vcardRow['tparams'] = array();
			foreach ($vcardRow['uparams'] as $pname => $params)
			{
				switch ($pname)
				{
					case 'TYPE':
						if (is_array($params))
						{
							$rowTypes = array();
							foreach ($params as $param)
							{
								$rowTypes[] = strtoupper($param);
							}
						}
						else
						{
							$rowTypes[] = strtoupper($params);
						}
						foreach ($rowTypes as $type)
						{
							switch ($type)
							{

								case 'OTHER':
								case 'WORK':
								case 'HOME':
									$vcardRow['tparams'][$type] = '';
									break;
								case 'CELL':
								case 'PAGER':
								case 'FAX':
								case 'VOICE':
								case 'CAR':
								case 'PREF':
								case 'X-CUSTOMLABEL-CAR':
								case 'X-CUSTOMLABEL-IPHONE':
								case 'IPHONE':
									if ($vcardRow['name'] == 'TEL')
									{
										$vcardRow['tparams'][$type] = '';
									}
								default:
									break;
							}
						}
						break;
					default:
						break;
				}
			}

			$vcardRow['uparams'] += $vcardRow['tparams'];
			ksort($vcardRow['uparams']);

			foreach ($vcardRow['uparams'] as $pname => $params)
			{
				switch ($pname)
				{
					case 'PREF':
						if (substr($rowName,0,3) == 'TEL' && !$pref_tel)
						{
							$pref_tel = $key;
						}
						break;
					case 'FAX':
					case 'PAGER':
					case 'VOICE':
					case 'OTHER':
					case 'CELL':
						if ($rowName != 'TEL') break;
					case 'WORK':
					case 'HOME':
						$rowName .= ';' . $pname;
						break;
					case 'CAR':
					case 'X-CUSTOMLABEL-CAR':
						if ($rowName == 'TEL')
						{
							$rowName = 'TEL;CAR';
						}
						break;
					case 'X-CUSTOMLABEL-IPHONE':
					case 'IPHONE':
						if ($rowName == 'TEL' || $rowName == 'TEL;CELL')
						{
							$rowName = 'TEL;IPHONE';
						}
						break;
					default:
						if (strpos($pname, 'X-FUNAMBOL-') === 0)
						{
							// Propriatary Funambol extension will be ignored
							$rowName .= ';' . $pname;
						}
						break;
				}
			}

			if ($rowName == 'EMAIL')
			{
				$rowName .= ';X-egw-Ref' . $email++;
			}

			if (($rowName == 'TEL;CELL') ||
					($rowName == 'TEL;CELL;VOICE'))
			{
				$rowName = 'TEL;CELL;X-egw-Ref' . $cell++;
			}

			if (($rowName == 'TEL') ||
					($rowName == 'TEL;VOICE'))
			{
				$rowName = 'TEL;X-egw-Ref' . $tel++;
			}

			if ($rowName == 'URL')
			{
				$rowName = 'URL;X-egw-Ref' . $url++;
			}

			// current algorithm cant cope with multiple attributes of same name
			// --> cumulate them in values, so they can be used later (works only for values, not for parameters!)
			if (($k = array_search($rowName, $rowNames)) != false)
			{
				$vcardValues[$k]['values'] = array_merge($vcardValues[$k]['values'],$vcardValues[$key]['values']);
			}
			$rowNames[$key] = $rowName;
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($rowNames)."\n",3,$this->logfile);
		}

		// All rowNames of the vCard are now concatenated with their qualifiers.
		// If qualifiers are missing we apply a default strategy.
		// E.g. ADR will be either ADR;WORK, if no ADR;WORK is given,
		// or else ADR;HOME, if not available elsewhere.

		$finalRowNames = array();

		foreach ($rowNames as $vcardKey => $rowName)
		{
			switch ($rowName)
			{
				case 'VERSION':
					break;
				case 'ADR':
					if (!in_array('ADR;WORK', $rowNames)
							&& !isset($finalRowNames['ADR;WORK']))
					{
						$finalRowNames['ADR;WORK'] = $vcardKey;
					}
					elseif (!in_array('ADR;HOME', $rowNames)
							&& !isset($finalRowNames['ADR;HOME']))
					{
						$finalRowNames['ADR;HOME'] = $vcardKey;
					}
					break;
				case 'TEL;FAX':
					if (!in_array('TEL;FAX;WORK', $rowNames)
							&& !isset($finalRowNames['TEL;FAX;WORK']))
					{
						$finalRowNames['TEL;FAX;WORK'] = $vcardKey;
					}
					elseif (!in_array('TEL;FAX;HOME', $rowNames)
						&& !isset($finalRowNames['TEL;FAX;HOME']))
					{
						$finalRowNames['TEL;FAX;HOME'] = $vcardKey;
					}
					break;
				case 'TEL;WORK':
					if (!in_array('TEL;VOICE;WORK', $rowNames)
							&& !isset($finalRowNames['TEL;VOICE;WORK']))
					{
						$finalRowNames['TEL;VOICE;WORK'] = $vcardKey;
					}
					break;
				case 'TEL;HOME':
					if (!in_array('TEL;HOME;VOICE', $rowNames)
							&& !isset($finalRowNames['TEL;HOME;VOICE']))
					{
						$finalRowNames['TEL;HOME;VOICE'] = $vcardKey;
					}
					break;
				case 'TEL;OTHER;VOICE':
				    if (!in_array('TEL;OTHER', $rowNames)
							&& !isset($finalRowNames['TEL;OTHER']))
					{
						$finalRowNames['TEL;OTHER'] = $vcardKey;
					}
					break;
				case 'TEL;PAGER;WORK':
				case 'TEL;PAGER;HOME':
					if (!in_array('TEL;PAGER', $rowNames)
							&& !isset($finalRowNames['TEL;PAGER']))
					{
						$finalRowNames['TEL;PAGER'] = $vcardKey;
					}
					break;
				case 'TEL;CAR;VOICE':
				case 'TEL;CAR;CELL':
				case 'TEL;CAR;CELL;VOICE':
					if (!isset($finalRowNames['TEL;CAR']))
					{
						$finalRowNames['TEL;CAR'] = $vcardKey;
					}
					break;
				case 'TEL;X-egw-Ref1':
					if (!in_array('TEL;VOICE;WORK', $rowNames)
							&& !in_array('TEL;WORK', $rowNames)
							&& !isset($finalRowNames['TEL;VOICE;WORK']))
					{
						$finalRowNames['TEL;VOICE;WORK'] = $vcardKey;
						break;
					}
				case 'TEL;X-egw-Ref2':
					if (!in_array('TEL;HOME;VOICE', $rowNames)
							&& !in_array('TEL;HOME', $rowNames)
							&& !isset($finalRowNames['TEL;HOME;VOICE']))
					{
						$finalRowNames['TEL;HOME;VOICE'] = $vcardKey;
						break;
					}
				case 'TEL;X-egw-Ref3':
					if (!in_array('TEL;OTHER', $rowNames)
							&& !in_array('TEL;OTHER;VOICE', $rowNames)
							&& !isset($finalRowNames['TEL;OTHER']))
					{
						$finalRowNames['TEL;OTHER'] = $vcardKey;
					}
					break;
				case 'TEL;CELL;X-egw-Ref1':
					$supported = isset($this->supportedFields['TEL;CELL']) ? 'TEL;CELL' : 'TEL;CELL;WORK';
					if (!in_array($supported, $rowNames) && !isset($finalRowNames[$supported]))
					{
						$finalRowNames[$supported] = $vcardKey;
						break;
					}
				case 'TEL;CELL;X-egw-Ref2':
					$supported = isset($this->supportedFields['TEL;IPHONE']) ? 'TEL;IPHONE' : 'TEL;CELL;HOME';
					if (!in_array($supported, $rowNames) && !isset($finalRowNames[$supported]))
					{
						$finalRowNames[$supported] = $vcardKey;
						break;
					}
				case 'TEL;CELL;X-egw-Ref3':
					if (!in_array('TEL;CAR', $rowNames)
							&& !in_array('TEL;CAR;VOICE', $rowNames)
							&& !in_array('TEL;CAR;CELL', $rowNames)
							&& !in_array('TEL;CAR;CELL;VOICE', $rowNames)
							&& !isset($finalRowNames['TEL;CAR']))
					{
						$finalRowNames['TEL;CAR'] = $vcardKey;
					}
					break;
				case 'EMAIL;X-egw-Ref1':
					if (!in_array('EMAIL;WORK', $rowNames) &&
							!isset($finalRowNames['EMAIL;WORK']))
					{
						$finalRowNames['EMAIL;WORK'] = $vcardKey;
						break;
					}
				case 'EMAIL;X-egw-Ref2':
					if (!in_array('EMAIL;HOME', $rowNames) &&
							!isset($finalRowNames['EMAIL;HOME']))
					{
						$finalRowNames['EMAIL;HOME'] = $vcardKey;
					}
					break;
				case 'URL;X-egw-Ref1':
					if (!in_array('URL;WORK', $rowNames) &&
							!isset($finalRowNames['URL;WORK']))
					{
						$finalRowNames['URL;WORK'] = $vcardKey;
						break;
					}
				case 'URL;X-egw-Ref2':
					if (!in_array('URL;HOME', $rowNames) &&
							!isset($finalRowNames['URL;HOME']))
					{
						$finalRowNames['URL;HOME'] = $vcardKey;
					}
					break;
				case 'X-EVOLUTION-ASSISTANT':
					if (!isset($finalRowNames['X-ASSISTANT']))
					{
						$finalRowNames['X-ASSISTANT'] = $vcardKey;
					}
					break;
				default:
					if (!isset($finalRowNames[$rowName]))
					{
						$finalRowNames[$rowName] = $vcardKey;
					}
					break;
			}
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($finalRowNames)."\n",3,$this->logfile);
		}

		$contact = array();
		// to be able to delete fields, we have to set all supported fields to at least null
		foreach($this->supportedFields as $fields)
		{
			foreach($fields as $field)
			{
				if ($field != 'fileas_type') $contact[$field] = null;
			}
		}

		foreach ($finalRowNames as $key => $vcardKey)
		{
			if (isset($this->supportedFields[$key]))
			{
				$fieldNames = $this->supportedFields[$key];
				foreach ($fieldNames as $fieldKey => $fieldName)
				{
					if (!empty($fieldName))
					{
						$value = trim($vcardValues[$vcardKey]['values'][$fieldKey]);
						if ($pref_tel && (($vcardKey == $pref_tel) ||
								($vcardValues[$vcardKey]['name'] == 'TEL') &&
								($vcardValues[$vcardKey]['value'] == $vcardValues[$pref_tel]['value'])))
						{
							$contact['tel_prefer'] = $fieldName;
						}
						switch($fieldName)
						{
							case 'bday':
								$contact[$fieldName] = $vcardValues[$vcardKey]['values']['year'] .
									'-' . $vcardValues[$vcardKey]['values']['month'] .
									'-' . $vcardValues[$vcardKey]['values']['mday'];
								break;

							case 'private':
								$contact[$fieldName] = (int) ( strtoupper($value) == 'PRIVATE');
								break;

							case 'cat_id':
								$contact[$fieldName] = $vcardValues[$vcardKey]['values'];
								break;

							case 'jpegphoto':
								$contact[$fieldName] = $vcardValues[$vcardKey]['value'];
								break;

							case 'note':
								$contact[$fieldName] = str_replace("\r\n", "\n", $vcardValues[$vcardKey]['value']);
								break;

							case 'fileas_type':
								// store Apple's X-ABSHOWAS:COMPANY as fileas_type == 'org_name'
								if ($vcardValues[$vcardKey]['value'] == 'COMPANY')
								{
									$contact[$fieldName] = 'org_name';
								}
								break;

							case 'uid':
								if (strlen($value) < $minimum_uid_length) {
									// we don't use it
									break;
								}
							default:
								$contact[$fieldName] = $value;
							break;
						}
					}
				}
			}
			// add unsupported attributes as with '##' prefix
			elseif(($attribute = $vcardValues[$vcardKey]) && !in_array($attribute['name'],array('PRODID','REV')))
			{
				// for attributes with multiple values in multiple lines, merge the values
				if (isset($contact['##'.$attribute['name']]))
				{
					error_log(__METHOD__."() contact['##$attribute[name]'] = ".array2string($contact['##'.$attribute['name']]));
					$attribute['values'] = array_merge(
						is_array($contact['##'.$attribute['name']]) ? $contact['##'.$attribute['name']]['values'] : (array)$contact['##'.$attribute['name']],
						$attribute['values']);
				}
				$contact['##'.$attribute['name']] = $attribute['params'] || count($attribute['values']) > 1 ?
					serialize($attribute) : $attribute['value'];
			}
		}

		$this->fixup_contact($contact);

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__	.
				"() '$this->productManufacturer','$this->productName'\n",3,$this->logfile);
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n" .
				array2string($contact)."\n",3,$this->logfile);
		}
		return $contact;
	}

	/**
	 * Exports some contacts: download or write to a file
	 *
	 * @param array $ids contact-ids
	 * @param string $file filename or null for download
	 */
	function export($ids, $file=null)
	{
		if (!$file)
		{
			html::content_header('addressbook.vcf','text/x-vcard');
		}
		if (!($fp = fopen($file ? $file : 'php://output','w')))
		{
			return false;
		}
		if (isset($GLOBALS['egw_info']['user']['preferences']['addressbook']['vcard_charset']))
		{
			$charset = $GLOBALS['egw_info']['user']['preferences']['addressbook']['vcard_charset'];
		}
		else
		{
			$charset = 'utf-8';
		}
		foreach ($ids as $id)
		{
			fwrite($fp,$this->getVCard($id, $charset));
		}
		fclose($fp);

		if (!$file)
		{
			common::egw_exit();
		}
		return true;
	}

	/**
	 * return a groupVCard
	 *
	 * @param array $list values for 'list_uid', 'list_name', 'list_modified', 'members'
	 * @param string $version='3.0' vcard version
	 * @return string containing the vcard
	 */
	function getGroupVCard(array $list,$version='3.0')
	{
		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar/vcard.php');

		$vCard = new Horde_iCalendar_vcard($version);
		$vCard->setAttribute('PRODID','-//EGroupware//NONSGML EGroupware Addressbook '.$GLOBALS['egw_info']['apps']['phpgwapi']['version'].'//'.
			strtoupper($GLOBALS['egw_info']['user']['preferences']['common']['lang']));

		$vCard->setAttribute('N',$list['list_name'],array(),true,array($list['list_name'],'','','',''));
		$vCard->setAttribute('FN',$list['list_name']);

		$vCard->setAttribute('X-ADDRESSBOOKSERVER-KIND','group');
		foreach($list['members'] as $uid)
		{
			$vCard->setAttribute('X-ADDRESSBOOKSERVER-MEMBER','urn:uuid:'.$uid);
		}
		$vCard->setAttribute('REV',egw_time::to($list['list_modified'],'Y-m-d\TH:i:s\Z'));
		$vCard->setAttribute('UID',$list['list_uid']);

		return $vCard->exportvCalendar();
	}
}
