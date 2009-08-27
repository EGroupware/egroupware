<?php
/**
 * Addressbook - vCard / iCal parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @subpackage export
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

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
	var $productManufacturer='file';
	/**
	 * product name from setSupportedFields (lowercase!)
	 *
	 * @var string
	 */
	var $productName;

	/**
	* import a vard into addressbook
	*
	* @param string	$_vcard		the vcard
	* @param int/string	$_abID=null		the internal addressbook id or !$_abID for a new enty
	* @return int contact id
	*/
	function addVCard($_vcard, $_abID)
	{
		if(!$contact = $this->vcardtoegw($_vcard)) {
			return false;
		}

		if($_abID) {
			// update entry
			$contact['id'] = $_abID;
		}
		return $this->save($contact);
	}

	/**
	* return a vcard
	*
	* @param int/string	$_id the id of the contact
	* @param string $_charset='utf-8' encoding of the vcard, default utf-8
	* @param boolean $extra_charset_attribute=true GroupDAV/CalDAV dont need the charset attribute and some clients have problems with it
	* @return string containing the vcard
	*/
	function getVCard($_id,$_charset='utf-8',$extra_charset_attribute=true)
	{
		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar/vcard.php');

		$vCard = new Horde_iCalendar_vcard;

		if(!is_array($this->supportedFields)) {
			$this->setSupportedFields();
		}
		$sysCharSet = $GLOBALS['egw']->translation->charset();
		$called = microtime(true);
		if(!($entry = $this->read($_id))) {
			return false;
		}
		//error_log("entry before fixed:\n".print_r($entry,true));

		$this->fixup_contact($entry);


		foreach($this->supportedFields as $vcardField => $databaseFields)
		{
			$values = array();
			$options = array();
			$hasdata = 0;
			foreach($databaseFields as $databaseField)
			{
				$value = "";

				if (!empty($databaseField))
				{
					$value = trim($entry[$databaseField]);
				}

				switch($databaseField)
				{
					case 'private':
						$value = $value ? 'PRIVATE' : 'PUBLIC';
						$hasdata++;
						break;

					case 'bday':
						if (!empty($value))
						{
							$value = str_replace('-','',$value).'T000000Z';
							$hasdata++;
						}
						break;

					case 'jpegphoto':
						if(!empty($value))
						{
						//error_log(__FILE__ ."\nThis vcard contains a JPEG\n '".$value."'");
							$options['ENCODING'] = 'BASE64';
							$options['TYPE'] = 'JPEG';
							$value = base64_encode($value);
 							$hasdata++;
						}
						break;

					case 'cat_id':
						if (!empty($value))
						{
							$value = implode(",", $this->get_categories($value));
						}
						// fall-through to the normal processing of string values
					default:
						if(!empty($value) || in_array($vcardField,array('FN','ORG','N')))
						{
							$value = $GLOBALS['egw']->translation->convert(trim($value), $sysCharSet,$_charset);

							if(($extra_charset_attribute || $this->productName == 'kde') && preg_match('/([\177-\377])/',$value))
							{
								$options['CHARSET'] = $_charset;
								// KAddressbook requires non-ascii chars to be qprint encoded, other clients eg. nokia phones have trouble with that
								if ($this->productName == 'kde')
								{
									$options['ENCODING'] = 'QUOTED-PRINTABLE';
								}
							}
							// protect the CardDAV
							elseif(($extra_charset_attribute && preg_match('/([\000-\012\015\016\020-\037\075])/',$value)))
							{
								$options['ENCODING'] = 'QUOTED-PRINTABLE';
							}
							$hasdata++;
						}
						break;
				}

				if (empty($value))
				{
					$value = "";
				}
				//if(preg_match('/([\000-\010])/',$value)) error_log(__FILE__."#".__METHOD__."\nThis value has 000-010: $value");

				$values[] = $value;
			}

			if ($hasdata <= 0)
			{
				// don't add the entry if there is no data for this field,
				// except it's a mendatory field
				continue;
			}

			$vCard->setAttribute($vcardField, implode(';', $values));
			$vCard->setParameter($vcardField, $options);
		}
		$result = $vCard->exportvCalendar();
		//error_log(__LINE__.__FILE__ . __METHOD__ ."\export vcard:".print_r($result,true));
		return $result;
	}

	function search($_vcard, $contentID=null)
	{
		if(!($contact = $this->vcardtoegw($_vcard))) {
			return false;
		}

		if ($contentID) {
			$contact['contact_id'] = $contentID;
		}

		unset($contact['private']);
		unset($contact['note']);
		unset($contact['n_fn']);
		unset($contact['email']);
		unset($contact['email_home']);
		unset($contact['url']);
		unset($contact['url_home']);
		unset($contact['n_fileas']);

		// some clients cut the values, because they do not support the same length of data like eGW
		// at least the first 10 characters must match
		$maybeCuttedFields = array('org_unit', 'org_name','title');
		foreach($maybeCuttedFields as $fieldName) {
			if(!empty($contact[$fieldName]) && strlen($contact[$fieldName]) > 10) {
				$contact[$fieldName] .= '*';
			}
		}

		//error_log(print_r($contact, true));

		#if($foundContacts = parent::search($contact, true, '', '', '%')) {
		if($foundContacts = parent::search($contact)) {
			return $foundContacts[0]['id'];
		}
		return false;
	}

	function setSupportedFields($_productManufacturer='file', $_productName='')
	{
		// store product manufacturer and name, to be able to use it elsewhere
		$this->productManufacturer = strtolower($_productManufacturer);
		$this->productName = strtolower($_productName);
		//error_log("prodM- prodN".print_r($_productManufacturer,true).print_r($_productName,true));
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
		$defaultFields[0] = array(	// multisync
			'ADR' 		=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'CATEGORIES' 	=> array('cat_id'),
			'CLASS'		=> array('private'),
			'EMAIL'		=> array('email'),
			'N'		=> array('n_family','n_given','','',''),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name',''),
			'TEL;CELL'	=> array('tel_cell'),
			'TEL;FAX'	=> array('tel_fax'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
		);

		$defaultFields[1] = array(	// all entries, nexthaus corporation, groupdav, ...
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'CATEGORIES'	=> array('cat_id'),
			'EMAIL;INTERNET' => array('email'),//tp4 tp43
			'EMAIL;INTERNET;HOME' => array('email_home'),//tp42 tp42
			#'EMAIL;INTERNET;WORK' => array('#email_work'),//tp43 lost
			'N'		=> array('n_family','n_given','n_middle','n_prefix','n_suffix'),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name','org_unit'),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;CELL;HOME'	=> array('tel_cell_private'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;FAX;HOME'	=> array('tel_fax_home'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
			'ROLE'		=> array('role'),
			'URL;HOME'	=> array('url_home'),
			'FBURL'		=> array('freebusy_uri'),
			'PHOTO'		=> array('jpegphoto'),
			'UID'       => array('uid'),
		);

		$defaultFields[2] = array(	// sony ericson
			'ADR;HOME' 		=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'BDAY'		=> array('bday'),
			'CATEGORIES' 	=> array('cat_id'),
			'CLASS'		=> array('private'),
			'EMAIL'		=> array('email'),
			'N'		=> array('n_family','n_given','','',''),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name',''),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
		);

		$defaultFields[3] = array(	// siemens
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'EMAIL;INTERNET;WORK' => array('email'),
			'EMAIL;INTERNET;HOME' => array('email_home'),
			'N'		=> array('n_family','n_given','','',''),
			'FN'		=> array('n_fn'),
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

		$defaultFields[4] = array(	// nokia 6600
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'EMAIL;INTERNET;WORK' => array('email'),
			'EMAIL;INTERNET;HOME' => array('email_home'),
			'N'		=> array('n_family','n_given','','',''),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name',''),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;CELL;HOME'	=> array('tel_cell_private'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;FAX;HOME'	=> array('tel_fax_home'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
			'URL;HOME'	=> array('url_home'),
		);

		$defaultFields[5] = array(	// nokia e61
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'EMAIL;INTERNET;WORK' => array('email'),
			'EMAIL;INTERNET;HOME' => array('email_home'),
			'N'		=> array('n_family','n_given','','n_prefix','n_suffix'),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name',''),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;CELL;HOME'	=> array('tel_cell_private'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;FAX;HOME'	=> array('tel_fax_home'),
			'TEL;HOME'	=> array('tel_home'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;WORK'	=> array('tel_work'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
			'URL;HOME'	=> array('url_home'),
		);

		$defaultFields[6] = array(	// funambol: fmz-thunderbird-plugin
			'ADR;WORK'      => array('','','adr_one_street','adr_one_locality','adr_one_region',
									'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'      => array('','','adr_two_street','adr_two_locality','adr_two_region',
									'adr_two_postalcode','adr_two_countryname'),
			'EMAIL'         => array('email'),
			'EMAIL;HOME'    => array('email_home'),
			'N'             => array('n_family','n_given','','',''),
			'FN'			=> array('n_fn'),
			'NOTE'          => array('note'),
			'ORG'           => array('org_name','org_unit'),
			'TEL;CELL'      => array('tel_cell'),
			'TEL;HOME;FAX'  => array('tel_fax'),
			'TEL;HOME;VOICE' => array('tel_home'),
			'TEL;PAGER'     => array('tel_pager'),
			'TEL;WORK;VOICE' => array('tel_work'),
			'TITLE'         => array('title'),
			'URL;WORK'      => array('url'),
			'URL'           => array('url_home'),
		);

		$defaultFields[7] = array(	// SyncEvolution
			'N'=>		array('n_family','n_given','n_middle','n_prefix','n_suffix'),
			'TITLE'		=> array('title'),
			'ROLE'		=> array('role'),
			'ORG'		=> array('org_name','org_unit','room'),
			'ADR;WORK'	=> array('','adr_one_street2','adr_one_street','adr_one_locality','adr_one_region', 'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','adr_two_street2','adr_two_street','adr_two_locality','adr_two_region', 'adr_two_postalcode','adr_two_countryname'),
			'TEL;WORK;VOICE'	=> array('tel_work'),
			'TEL;HOME;VOICE'	=> array('tel_home'),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;FAX;HOME'	=> array('tel_fax_home'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;CAR'	=> array('tel_car'),
			'TEL;VOICE'	=> array('tel_other'),
			'EMAIL;INTERNET;WORK'	=> array('email'),
			'EMAIL;INTERNET;HOME'	=> array('email_home'),
			'URL;WORK'		=> array('url'),
			'BDAY'		=> array('bday'),
			'CATEGORIES'	=> array('cat_id'),
			'NOTE'		=> array('note'),
			'X-EVOLUTION-ASSISTANT'		=> array('assistent'),
			'PHOTO'		=> array('jpegphoto'),
		);

		$defaultFields[8] = array_merge($defaultFields[1],array(	// KDE Addressbook, only changes from all=1
			'ORG' => array('org_name'),
			'X-KADDRESSBOOK-X-Department' => array('org_unit'),
			'PHOTO'		=> array('jpegphoto'),
		));

		$defaultFields[9] = array(	// nokia e90
			'ADR;WORK'	=> array('','adr_one_street2','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','adr_two_street2','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'X-CLASS'	=> array('private'),
			'EMAIL;INTERNET;WORK' => array('email'),
			'EMAIL;INTERNET;HOME' => array('email_home'),
			'N'		=> array('n_family','n_given','n_middle','n_prefix','n_suffix'),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name','org_unit'),
			'TEL;CELL;WORK'	=> array('tel_cell'),
			'TEL;CELL;HOME'	=> array('tel_cell_private'),
			'TEL;FAX;WORK'	=> array('tel_fax'),
			'TEL;FAX;HOME'	=> array('tel_fax_home'),
			'TEL;CAR'	=> array('tel_car'),
			'TEL;PAGER;WORK' => array('tel_pager'),
			'TEL;VOICE;WORK' => array('tel_work'),
			'TEL;VOICE;HOME' => array('tel_home'),
			'TITLE'		=> array('title'),
			'URL;WORK'	=> array('url'),
			'URL;HOME'	=> array('url_home'),
			'X-ASSISTANT'		=> array('assistent'),
			'X-ASSISTANT-TEL'	=> array('tel_assistent'),
			'PHOTO'		=> array('jpegphoto'),
		);

		$defaultFields[10] = array(	// nokia 9300
			'ADR;WORK'	=> array('','','adr_one_street','adr_one_locality','adr_one_region',
							'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'	=> array('','','adr_two_street','adr_two_locality','adr_two_region',
							'adr_two_postalcode','adr_two_countryname'),
			'BDAY'		=> array('bday'),
			'EMAIL;INTERNET' => array('email'),
			'N'		=> array('n_family','n_given','n_middle','n_prefix','n_suffix'),
			'FN'		=> array('n_fn'),
			'NOTE'		=> array('note'),
			'ORG'		=> array('org_name','org_unit'),
			'TEL;CELL'	=> array('tel_cell'),
			'TEL;WORK;FAX'	=> array('tel_fax'),
			'TEL;FAX'	=> array('tel_fax_home'),
			'TEL;PAGER' => array('tel_pager'),
			'TEL;WORK;VOICE' => array('tel_work'),
			'TEL;HOME;VOICE' => array('tel_home'),
			'TITLE'		=> array('contact_role'),
			'URL'	=> array('url'),
		);


		$defaultFields[11] = array(	// funambol: iphone
			'ADR;WORK'      => array('','','adr_one_street','adr_one_locality','adr_one_region',
									'adr_one_postalcode','adr_one_countryname'),
			'ADR;HOME'      => array('','','adr_two_street','adr_two_locality','adr_two_region',
									'adr_two_postalcode','adr_two_countryname'),
			'EMAIL;INTERNET;WORK'         => array('email'),
			'EMAIL;INTERNET;HOME'    => array('email_home'),
			'N'		=> array('n_family','n_given','n_middle','n_prefix','n_suffix'),
			'FN'			=> array('n_fn'),
			'NOTE'          => array('note'),
			'ORG'           => array('org_name','org_unit'),
			'TEL;CELL'      => array('tel_cell'),
			'TEL;HOME;FAX'  => array('tel_fax_home'),
			'TEL;WORK;FAX'  => array('tel_fax'),
			'TEL;VOICE;HOME' => array('tel_home'),
			'TEL;VOICE;WORK' => array('tel_work'),
			'TEL;PAGER'     => array('tel_pager'),
			'TEL;CAR'     => array('tel_car'),
			'TITLE'         => array('title'),
			'URL;WORK'      => array('url'),
			'URL;HOME'           => array('url_home'),
			'PHOTO'		=> array('jpegphoto'),
		);

		//error_log("Client: $_productManufacturer $_productName");
		switch($this->productManufacturer)
		{
			case 'funambol':
			case 'funambol inc.':
				switch ($this->productName)
				{
					case 'thunderbird':
						$this->supportedFields = $defaultFields[6];
						break;

					case 'iphone':
						$this->supportedFields = $defaultFields[11];
						break;

					default:
						//error_log("Funambol product '$_productName', assuming same as thunderbird");
						$this->supportedFields = $defaultFields[6];
						break;
				}
				break;

			case 'nexthaus corporation':
			case 'nexthaus corp':
				switch($this->productName)
				{
					case 'syncje outlook edition':
						$this->supportedFields = $defaultFields[1];
						break;
					default:
						error_log("Nethaus product '$_productName', assuming same as 'syncje outlook'");
						$this->supportedFields = $defaultFields[1];
						break;
				}
				break;

			case 'nokia':
				switch($this->productName)
				{
					case 'e61':
						$this->supportedFields = $defaultFields[5];
						break;
					case 'e51':
					case 'e90':
					case 'e71':
						$this->supportedFields = $defaultFields[9];
						break;
					case '9300':
						$this->supportedFields = $defaultFields[10];
						break;
					case '6600':
						$this->supportedFields = $defaultFields[4];
						break;
					default:
						error_log("Unknown Nokia phone '$_productName', assuming same as '6600'");
						$this->supportedFields = $defaultFields[4];
						break;
				}
				break;


			// multisync does not provide anymore information then the manufacturer
			// we suppose multisync with evolution
			case 'the multisync project':
				switch($this->productName)
				{
					default:
						$this->supportedFields = $defaultFields[0];
						break;
				}
				break;

			case 'siemens':
				switch($this->productName)
				{
					case 'sx1':
						$this->supportedFields = $defaultFields[3];
						break;
					default:
						error_log("Unknown Siemens phone '$_productName', assuming same as 'sx1'");
						$this->supportedFields = $defaultFields[3];
						break;
				}
				break;

			case 'sonyericsson':
			case 'sony ericsson':
				switch($this->productName)
				{
					case 'd750i':
						$this->supportedFields = $defaultFields[2];
						break;
					case 'p910i':
					default:
						error_log("unknown Sony Ericsson phone '$_productName', assuming same as 'd750i'");
						$this->supportedFields = $defaultFields[2];
						break;
				}
				break;

			case 'synthesis ag':
				switch($this->productName)
				{
					case 'sysync client pocketpc pro':
					case 'sysync client pocketpc std':
						$this->supportedFields = $defaultFields[1];
						#$this->supportedFields['PHOTO'] = array('jpegphoto');
						break;
					default:
						error_log("Synthesis connector '$_productName', using default fields");
						$this->supportedFields = $defaultFields[0];
						break;
				}
				break;

			case 'patrick ohly':	// SyncEvolution
				$this->supportedFields = $defaultFields[7];
				break;

			case 'file':	// used outside of SyncML, eg. by the calendar itself ==> all possible fields
				$this->supportedFields = $defaultFields[1];
				break;
			case 'groupdav':                // all GroupDAV access goes through here
				switch($this->productName)
				{
					case 'kde':             // KDE Addressbook
						$this->supportedFields = $defaultFields[1];
						error_log(__FILE__ . ":groupdav kde 1");
						break;
					default:
						$this->supportedFields = $defaultFields[1];
				}
				break;
			// the fallback for SyncML
			default:
				error_log(__FILE__. __METHOD__ ."\nManufacturer-Product not found: '$_productManufacturer' '$_productName'");
				$this->supportedFields = $defaultFields[0];
				break;
		}
	}

	function vcardtoegw($_vcard)
	{
		// the horde class does the charset conversion. DO NOT CONVERT HERE.

		//error_log("vcardin vtoe".print_r($_vcard, true));
		if(!is_array($this->supportedFields))
		{
			$this->setSupportedFields();
		}

		require_once(EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php');

		$vCard = Horde_iCalendar::newComponent('vcard', $container);

		// Unfold any folded lines.
		$vCardUnfolded = preg_replace ('/(\r|\n)+ /', ' ', $_vcard);

		if(!$vCard->parsevCalendar($vCardUnfolded, 'VCARD'))
		{
			return False;
		}
		$vcardValues = $vCard->getAllAttributes();

		#print "<pre>$_vcard</pre>";

		#error_log(print_r($vcardValues, true));

		foreach($vcardValues as $key => $vcardRow)
		{
			$rowName  = $vcardRow['name'];

			$vcardElementCount = count($vcardRow['params'],COUNT_RECURSIVE);

			if( $vcardElementCount > 0 )
			{
				foreach($vcardRow['params'] as $VKey => $vcardParam)
				{
					if( ! strlen($vcardParam) )
					{
						$rowName .= ';'.$VKey;
					}
					if( $VKey == 'TYPE' && in_array(strtoupper($vcardParam),array('CELL','FAX','PAGER','WORK','HOME','VOICE','CAR')))
					{
						$rowName .= ';'.strtoupper($vcardParam);
					}
				}
        	}
			$rowNames[$rowName] = $key;
		}


		#error_log(print_r($rowNames, true));

		// now we have all rowNames the vcard provides
		// we just need to map to the right addressbook fieldnames
		// we need also to take care about ADR for example. we do not
		// support this. We support only ADR;WORK or ADR;HOME

		// njv: As the order of tag occurence is undefined and tags 1... to  n are mapped to one addressbook
		//	fieldname and tags 1... to n may have conflicting content eg EMAIL is set but EMAIL;INTERNET is empty
		//  and both are mapped to "email" who wins?

		foreach($rowNames as $rowName => $vcardKey)
		{
			//	error_log("eachrownane:".print_r($rowName,true));

			switch($rowName)
			{
				case 'TEL;VOICE;HOME':
				case 'TEL;VOICE;WORK':	// check if we have a mapping without VOICE
					$replace = str_replace('VOICE;','',$rowName);
					if (!isset($rowNames[$replace]) && array_key_exists($replace, $this->supportedFields))
					{
						$finalRowNames[$replace] = $vcardKey;	// if yes use that
					}
					else
					{
						$finalRowNames[$rowName] = $vcardKey;	// else use existing mapping
					}
					break;

				case 'ADR':
				case 'TEL':
				case 'URL':
				case 'TEL;FAX':
				case 'TEL;CELL':
				case 'TEL;PAGER':
				case 'TEL;VOICE':
					if(!isset($rowNames[$rowName. ';WORK']) && array_key_exists($rowName. ';WORK', $this->supportedFields) )
					{
						$finalRowNames[$rowName. ';WORK'] = $vcardKey;
					}
					else
					{
						$InvolvedValues = explode(';',$rowName.';WORK');
						foreach($this->supportedFields as $suppFields => $suppFieldsValue )
						{
							$InvolvedHits = 0;
							foreach($InvolvedValues as $hlparr => $hlparrKey )
							{
								if( ! stristr( $suppFields,$hlparrKey ) === FALSE )
								{
									$InvolvedHits++;
								}
							}
							if( count($InvolvedValues) == $InvolvedHits && !isset($finalRowNames[$suppFields]) )
							{
								$finalRowNames[$suppFields] = $vcardKey;
								break;   // if a combination of all words in $InvolvedValues were found
							}
						}
						if( count($InvolvedValues) != $InvolvedHits  && ! isset($finalRowNames[$rowName]) &&  array_key_exists($rowName, $this->supportedFields) )
						{
							$finalRowNames[$rowName] = $vcardKey;
						}
					}
					break;
				case 'EMAIL':
				case 'EMAIL;INTERNET':
					$ckey = false;
                                        foreach($this->supportedFields as $tag => $value)
                                        {
                                        if(in_array ('email', $value))
                                            {
                                            $ckey = $tag;
                                            }
                                        }
                        //              error_log("key:$ckey:$vcardKey");

                                        if( $ckey && !empty($vcardValues[$vcardKey]['value']))
                                        {
                        //                error_log("$akey : ".print_r($vcardKey,true));
                                        $finalRowNames[$ckey] = $vcardKey;
                                        }
                                        break;

				case 'EMAIL;WORK':
					$akey = false;
					foreach($this->supportedFields as $tag => $value)
					{
						if(in_array ('email', $value))
						{
							$akey = $tag;
						}
						if(!isset($rowNames['EMAIL;INTERNET;WORK']))
						{
							//error_log(__METHOD__.print_r($rowName,true).'#'.print_r($vcardKey,true));
							if(is_array($value) && in_array ('#email_work', $value))
							{
								$akey = $tag;
							}
						}
						//		error_log("key:$akey:$vcardKey");
					}
					if( $akey && !empty($vcardValues[$vcardKey]['value']))
					{
						//error_log("$akey : ".print_r($vcardKey,true));
						$finalRowNames[$akey] = $vcardKey;
					}
					break;
				case 'EMAIL;HOME':
					if(!isset($rowNames['EMAIL;INTERNET;HOME']))
					{
						$finalRowNames['EMAIL;INTERNET;HOME'] = $vcardKey;
					}

					//	error_log("email_home-key: ".print_r($bkey, true));

					if($bkey && !empty($vcardValues[$vcardKey]['value']))
					{
						//	error_log("$bkey :".print_r($vcardKey,true));
						$finalRowNames[$bkey] = $vcardKey;
					}
					break;

				case 'VERSION':
					break;

				default:
					//error_log("default row map".print_r($vcardKey,true));
					$finalRowNames[$rowName] = $vcardKey;
					break;
			}
		}
		//error_log("finalrownames".print_r($finalRowNames, true));

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
						if ($fieldName == 'jpegphoto' || $vcardValues[$vcardKey]['params']['ENCODING'] == 'b')
						{
							$value = base64_decode($vcardValues[$vcardKey]['values'][$fieldKey]);
						}
						else
						{
							$value = trim($vcardValues[$vcardKey]['values'][$fieldKey]);
						}

						switch($fieldName)
						{
							case 'bday':
								if(!empty($value)) {
									$contact[$fieldName] = date('Y-m-d', $value);
								}
								break;

							case 'private':
								$contact[$fieldName] = (int) ( strtoupper($value) == 'PRIVATE');
								break;

							case 'cat_id':
								$contact[$fieldName] = implode(',',$this->find_or_add_categories(explode(',',$value),'private'));
								break;

							case 'note':
								// note may contain ','s but maybe this needs to be fixed in vcard parser...
								//$contact[$fieldName] = trim($vcardValues[$vcardKey]['value']);
								//break;

							default:
								$contact[$fieldName] = $value;
								break;
						}
					}
				}
			}
		}

		$this->fixup_contact($contact);
		//error_log(__LINE__.__FILE__ . __METHOD__ . "\nContact:\n " .print_r($contact,true));
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
