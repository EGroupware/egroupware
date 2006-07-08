<?php
/**
 * Addressbook - vCard / iCal parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

require_once EGW_SERVER_ROOT.'/addressbook/inc/class.bocontacts.inc.php';
require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

class vcaladdressbook extends bocontacts
{
	/**
	* import a vard into addressbook
	* 
	* @param string	$_vcard		the vcard
	* @param int	$_abID		the internal addressbook id
	* @param int	$_vcardProfile	profile id for mapping from vcard values to egw addressbook
	* @return int contact id
	*/
	function addVCard($_vcard, $_abID)
	{
		if(!$contact = $this->vcardtoegw($_vcard)) {
			return false;
		}
		
		if($_abID)
		{
			// update entry
			$contact['ab_id'] = $_abID;
		}
		return $this->save($contact);
	}

	/**
	* return a vcard
	*
	* @param int/string	$_id the id of the contact
	* @param int $_vcardProfile	profile id for mapping from vcard values to egw addressbook
	* @return string containing the vcard
	*/
	function getVCard($_id)
	{
		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar/vcard.php');

		$vCard =& new Horde_iCalendar_vcard;

		if(!is_array($this->supportedFields))
		{
			$this->setSupportedFields();
		}
		$sysCharSet = $GLOBALS['egw']->translation->charset();

		if(!($entry = $this->read($_id)))
		{
			return false;
		}
		foreach($this->supportedFields as $vcardField => $databaseFields)
		{
			$options = array();
			$value = '';
			foreach($databaseFields as $databaseField)
			{
				$tempVal = ';';
				if(!empty($databaseField))
				{
					$tempVal = trim($entry[$databaseField]).';';
				}
				$value .= $tempVal;
			}
			// remove the last ;
			$value = substr($value, 0, -1);

			switch($vcardField)
			{
				// TODO handle multiple categories
				case 'CATEGORIES':
					$catData = ExecMethod('phpgwapi.categories.return_single',$value);
					$value = $catData[0]['name'];
					break;
				case 'CLASS':
					$value = $value ? 'PRIVATE' : 'PUBLIC';
					break;
				case 'BDAY':
					if(!empty($value))
					{
						$value = str_replace('-','',$value).'T000000Z';
					}
					break;
			}
			if ($databaseField != 'jpegphoto') 
			{
				$value = $GLOBALS['egw']->translation->convert($value,$sysCharSet,'utf-8');
			}
			// don't add the entry if it contains only ';'
			if(strlen(str_replace(';','',$value)) != 0)
			{
				$vCard->setAttribute($vcardField, $value);
			}
			if(preg_match('/([\000-\012\015\016\020-\037\075])/',$value))
			{
				$options['ENCODING'] = 'QUOTED-PRINTABLE';
			}
			if(preg_match('/([\177-\377])/',$value))
			{
				$options['CHARSET'] = 'UTF-8';
			}
			$vCard->setParameter($vcardField, $options);
		}

		$result = $vCard->exportvCalendar();

		return $result;
	}

	function search($_vcard) 
	{
		if(!($contact = $this->vcardtoegw($_vcard))) 
		{
			return false;
		}
		
		if(($foundContacts = $this->search($contact)))
		{
			return $foundContacts[0]['id'];
		}
		return false;
	}

	function setSupportedFields($_productManufacturer='file', $_productName='')
	{
		/**
		 * ToDo Lars: 
		 * + changes / renamed fields in 1.3+:
		 *   - access           --> private (already done by Ralf)
		 *   - tel_msg          --> tel_assistent
		 *   - tel_modem        --> tel_fax_home
		 *   - tel_isdn         --> tel_cell_private
		 *   - tel_voice/ophone --> tel_other
		 *   - address2         --> adr_one_street2
		 *   - address3         --> adr_two_street2
		 *   - freebusy_url     --> freebusy_uri (i instead l !)
		 *   - fn               --> n_fn
		 *   - last_mod         --> modified
		 * + new fields in 1.3+:
		 *   - n_fileas
		 *   - role
		 *   - assistent
		 *   - room
		 *   - calendar_uri
		 *   - url_home
		 *   - created
		 *   - creator (preset with owner)
		 *   - modifier
		 *   - jpegphoto
		 */
		$defaultFields[0] = array(
			'ADR' 		=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'CATEGORIES' 	=> array('cat_id'),
			'CLASS'		=> array('private'),
			'EMAIL'		=> array('email'),
			'N'		=> array('n_family','n_given','','',''),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name',''),
			'TEL;CELL'	=> array('tel_cell'),
			'TEL;FAX'	=> array('tel_fax'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
		);

		$defaultFields[1] = array(
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'CATEGORIES'	=> array('cat_id'),
			'EMAIL;INTERNET;WORK' => array('email'),
			'EMAIL;INTERNET;HOME' => array('email_home'),
			'N'		=> array('n_family','n_given','n_middle','n_prefix','n_suffix'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name','org_unit'),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
		);

		$defaultFields[2] = array(
			'ADR;HOME' 		=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'BDAY'		=> array('bday'),
			'CATEGORIES' 	=> array('cat_id'),
			'CLASS'		=> array('private'),
			'EMAIL'		=> array('email'),
			'N'		=> array('n_family','n_given','','',''),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name',''),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL'		=> array('url'),
		);

		$defaultFields[3] = array(
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'EMAIL;INTERNET;WORK' => array('email'),
			'EMAIL;INTERNET;HOME' => array('email_home'),
			'N'		=> array('n_family','n_given','','',''),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name','org_unit'),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
		);

		switch(strtolower($_productManufacturer))
		{
			case 'nexthaus corporation':
				switch(strtolower($_productName))
				{
					case 'syncje outlook edition':
					default:
						$this->supportedFields = $defaultFields[1];
						break;
				}
				break;

			// multisync does not provide anymore information then the manufacturer
			// we suppose multisync with evolution
			case 'the multisync project':
				switch(strtolower($_productName))
				{
					default:
						$this->supportedFields = $defaultFields[0];
						break;
				}
				break;

			case 'siemens':
				switch(strtolower($_productName))
				{
					case 'sx1':
					default:
						$this->supportedFields = $defaultFields[3];
						break;
				}
				break;
				
			case 'sonyericsson':
				switch(strtolower($_productName))
				{
					case 'd750i':
					default:
						$this->supportedFields = $defaultFields[2];
						break;
				}
				break;
				
			case 'synthesis ag':
				switch(strtolower($_productName))
				{
					default:
						$this->supportedFields = $defaultFields[0];
						break;
				}
				break;
				
			case 'file':	// used outside of SyncML, eg. by the calendar itself ==> all possible fields
				$this->supportedFields = $defaultFields[1];
				break;

			// the fallback for SyncML
			default:
				error_log("Client not found: $_productManufacturer $_productName");
				$this->supportedFields = $defaultFields[0];
				break;
		}
	}
	
	function vcardtoegw($_vcard) 
	{
		// convert from utf-8 to eGW's charset
		$_vcard = $GLOBALS['egw']->translation->convert($_vcard,'utf-8');

		if(!is_array($this->supportedFields))
		{
			$this->setSupportedFields();
		}

		$this->supportedFields[0] = array(
		);

		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php');

		$vCard = Horde_iCalendar::newComponent('vcard', $container);

		if(!$vCard->parsevCalendar($_vcard,'VCARD'))
		{
			return False;
		}
		$vcardValues = $vCard->getAllAttributes();

		#print "<pre>$_vcard</pre>";

		#_debug_array($vcardValues);

		foreach($vcardValues as $key => $vcardRow)
		{
			$rowName  = $vcardRow['name'];

			if(isset($vcardRow['params']['INTERNET']))
			{
				$rowName .= ";INTERNET";
			}
			if(isset($vcardRow['params']['CELL']))
			{
				$rowName .= ';CELL';
			}
			if(isset($vcardRow['params']['FAX']))
			{
				$rowName .= ';FAX';
			}
			if(isset($vcardRow['params']['PAGER']))
			{
				$rowName .= ';PAGER';
			}
			if(isset($vcardRow['params']['WORK']))
			{
				$rowName .= ';WORK';
			}
			if(isset($vcardRow['params']['HOME']))
			{
				$rowName .= ';HOME';
			}

			$rowNames[$rowName] = $key;
		}

		#error_log('rowNames: '.print_r($rowNames,true));

		// now we have all rowNames the vcard provides
		// we just need to map to the right addressbook fieldnames
		// we need also to take care about ADR for example. we do not
		// support this. We support only ADR;WORK or ADR;HOME

		foreach($rowNames as $rowName => $vcardKey)
		{
			switch($rowName)
			{
				case 'ADR':
				case 'TEL':
				case 'TEL;FAX':
				case 'TEL;CELL':
				case 'TEL;PAGER':
					if(!isset($rowNames[$rowName. ';WORK']))
					{
						$finalRowNames[$rowName. ';WORK'] = $vcardKey;
					}
					break;
				case 'EMAIL':
				case 'EMAIL;WORK':
					if(!isset($rowNames['EMAIL;INTERNET;WORK']))
					{
						$finalRowNames['EMAIL;INTERNET;WORK'] = $vcardKey;
					}
					break;
				case 'EMAIL;HOME':
					if(!isset($rowNames['EMAIL;INTERNET;HOME']))
					{
						$finalRowNames['EMAIL;INTERNET;HOME'] = $vcardKey;
					}
					break;

				case 'CATEGORIES':
					#cat_id = 7,8
					$vcardData['category'] = array();
					if ($attributes['value'])
					{
						if (!is_object($this->cat))
						{
							if (!is_object($GLOBALS['egw']->categories))
							{
								$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories',$this->owner,'addressbook');
							}
							$this->cat =& $GLOBALS['egw']->categories;
						}
						foreach(explode(',',$attributes['value']) as $cat_name)
						{
							if (!($cat_id = $this->cat->name2id($cat_name)))
							{
								$cat_id = $this->cat->add( array('name' => $cat_name,'descr' => $cat_name ));
							}
							$vcardData['category'][] = $cat_id;
						}
					}
					break;	

				case 'VERSION':
					break;
				default:
					$finalRowNames[$rowName] = $vcardKey;
					break;
			}
		}

		#_debug_array($finalRowNames);
		$contact = array();

		foreach($finalRowNames as $key => $vcardKey)
		{
			if(isset($this->supportedFields[$key]))
			{
				$fieldNames = $this->supportedFields[$key];
				foreach($fieldNames as $fieldKey => $fieldName)
				{
					if(!empty($fieldName))
					{
						switch($fieldName)
						{
							case 'private':
								$contact[$fieldName] = (int) $vcardValues[$vcardKey]['values'][$fieldKey] == 'PRIVATE';
								break;
							case 'cat_id':
								if (!is_object($this->cat))
								{
									if (!is_object($GLOBALS['egw']->categories))
									{
										$GLOBALS['egw']->categories =& CreateObject('phpgwapi.categories',$GLOBALS['egw_info']['user']['account_id'],'addressbook');
									}
									$this->cat =& $GLOBALS['egw']->categories;
								}
								foreach(explode(',',$vcardValues[$vcardKey]['values'][$fieldKey]) as $cat_name)
								{
									if (!($cat_id = $this->cat->name2id($cat_name)))
									{
										$cat_id = $this->cat->add( array('name' => $cat_name,'descr' => $cat_name ));
									}
									$contact[$fieldName] = $cat_id;
								}
								break;
							default:
								$contact[$fieldName] = $vcardValues[$vcardKey]['values'][$fieldKey];
								break;
						}
					}
				}
			}
		}
		$contact['n_fn']  = trim($contact['n_given'].' '.$contact['n_family']);
		
		return $contact;
	}
	
	/**
	 * Exports some contacts: download or write to a file
	 *
	 * @param array $ids contact-ids
	 * @param string $file filename or null for download
	 */
	function export($ids,$file=null)
	{
		if (!$file)
		{
			$browser =& CreateObject('phpgwapi.browser');
			$browser->content_header('addressbook.vcf','text/x-vcard');
		}
		if (!($fp = fopen($file ? $file : 'php://output','w')))
		{
			return false;
		}
		foreach($ids as $id)
		{
			fwrite($fp,$this->getVCard($id));
		}
		fclose($fp);
		
		if (!$file)
		{
			$GLOBALS['egw']->common->egw_exit();
		}		
		return true;
	}
}
