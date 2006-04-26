<?php
	/**************************************************************************\
	* eGroupWare - LDAP wrapper class for contacts                             *
	* http://www.egroupware.org                                                *
	* Written by Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.contacts.inc.php');

	define('ADDRESSBOOK_ALL',0);
	define('ADDRESSBOOK_ACCOUNTS',1);
	define('ADDRESSBOOK_PERSONAL',2);
	define('ADDRESSBOOK_GROUP',3);

	/**
	 * Wrapper class for phpgwapi.contacts_ldap
	 * This makes it compatible with vars and parameters of so_sql
	 * Maybe one day this becomes a generalized ldap storage object :-)
	 *
	 * @package addressbook
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
	class so_ldap extends contacts {
	
		var $data;
		//var $db_data_cols;
		//var $db_key_cols;
		var $groupName = 'Default';
		
		/**
		* @var string $accountName holds the accountname of the current user
		*/
		var $accountName;

		/**
		* @var object $ldapServerInfo holds the information about the current used ldap server
		*/
		var $ldapServerInfo;
		
		/**
		* @var int $ldapLimit how many rows to fetch from ldap server
		*/
		var $ldapLimit = 300;
		
		/**
		* @var string $personalContactsDN holds the base DN for the personal addressbooks
		*/
		var $personalContactsDN;

		/**
		* @var string $sharedContactsDN holds the base DN for the shared addressbooks
		*/
		var $sharedContactsDN;
		
		/**
		* @var int $total holds the total count of found rows
		*/
		var $total;
		
		# jpegPhoto
		var $inetOrgPersonFields = array(
			'n_fn'			=> 'cn',
			'n_given'		=> 'givenname',
			'n_family'		=> 'sn',
			#'n_middle'            => 'phpgwmiddlename',
			#'n_prefix'            => 'phpgwprefix',
			#'n_suffix'            => 'phpgwsuffix',
			'sound'			=> 'audio',
			#'bday'                => 'phpgwbirthday',
			'note'			=> 'description',
			#'tz'                  => 'phpgwtz',
			#'geo'                 => 'phpgwgeo',
			'url'			=> 'labeleduri',
			#'pubkey'              => 'phpgwpublickey',

			'org_name'		=> 'o',
			'org_unit'		=> 'ou',
			'title'			=> 'title',

			'adr_one_street'	=> 'street',
			'adr_one_locality'	=> 'l',
			'adr_one_region'	=> 'st',
			'adr_one_postalcode'	=> 'postalcode',
			#'adr_one_countryname'	=> 'co',
			#'adr_one_type'        => 'phpgwadronetype',
			#'label'               => 'phpgwaddresslabel',

			#'adr_two_street'      => 'phpgwadrtwostreet',
			#'adr_two_locality'    => 'phpgwadrtwolocality',
			#'adr_two_region'      => 'phpgwadrtworegion',
			#'adr_two_postalcode'  => 'phpgwadrtwopostalcode',
			#'adr_two_countryname' => 'phpgwadrtwocountryname',
			#'adr_two_type'        => 'phpgwadrtwotype',

			'tel_work'		=> 'telephonenumber',
			'tel_home'		=> 'homephone',
			#'tel_voice'           => 'phpgwvoicetelephonenumber',
			'tel_fax'		=> 'facsimiletelephonenumber',
			#'tel_msg'             => 'phpgwmsgtelephonenumber',
			#'tel_cell'            => 'phpgwcelltelephonenumber',
			#'tel_pager'           => 'phpgwpagertelephonenumber',
			#'tel_bbs'             => 'phpgwbbstelephonenumber',
			#'tel_modem'           => 'phpgwmodemtelephonenumber',
			#'tel_car'             => 'phpgwmobiletelephonenumber',
			#'tel_isdn'            => 'phpgwisdnphonenumber',
			#'tel_video'           => 'phpgwvideophonenumber',
			#'tel_prefer'          => 'phpgwpreferphone',
			'email'			=> 'mail',
			#'email_type'          => 'phpgwmailtype',
			#'email_home'          => 'phpgwmailhome',
			#'email_home_type'     => 'phpgwmailhometype',
			'room'			=> 'roomnumber',
		);

		#displayName
		#mozillaCustom1
		#mozillaCustom2
		#mozillaCustom3
		#mozillaCustom4
		#mozillaHomeUrl
		#mozillaNickname
		#mozillaUseHtmlMail
		#nsAIMid
		#postOfficeBox
		var $mozillaAbPersonFields = array(
			#'fn'			=> 'cn',
			#'n_given'		=> 'givenname',
			#'n_family'		=> 'sn',
			#'n_middle'            => 'phpgwmiddlename',
			#'n_prefix'            => 'phpgwprefix',
			#'n_suffix'            => 'phpgwsuffix',
			#'sound'               => 'phpgwaudio',
			#'bday'                => 'phpgwbirthday',
			#'note'			=> 'description',
			#'tz'                  => 'phpgwtz',
			#'geo'                 => 'phpgwgeo',
			#'url'			=> 'mozillaworkurl',
			#'pubkey'              => 'phpgwpublickey',

			#'org_name'		=> 'o',
			#'org_unit'		=> 'ou',
			#'title'			=> 'title',

			#'adr_one_street'	=> 'street',
			'adr_one_street2'	=> 'mozillaworkstreet2',
			#'adr_one_locality'	=> 'l',
			#'adr_one_region'	=> 'st',
			#'adr_one_postalcode'	=> 'postalcode',
			#'adr_one_countryname'	=> 'c',
			#'adr_one_type'        => 'phpgwadronetype',
			#'label'               => 'phpgwaddresslabel',

			'adr_two_street'	=> 'mozillahomestreet',
			'adr_two_street2'	=> 'mozillahomestreet2',
			'adr_two_locality'	=> 'mozillahomelocalityname',
			'adr_two_region'	=> 'mozillahomestate',
			'adr_two_postalcode'	=> 'mozillahomepostalcode',
			'adr_two_countryname'	=> 'mozillahomecountryname',
			#'adr_two_type'        => 'phpgwadrtwotype',

			#'tel_work'		=> 'telephonenumber',
			#'tel_home'		=> 'homephone',
			#'tel_voice'           => 'phpgwvoicetelephonenumber',
			'tel_fax'		=> 'fax',
			#'tel_msg'             => 'phpgwmsgtelephonenumber',
			'tel_cell'		=> 'mobile',
			'tel_pager'		=> 'pager',
			#'tel_bbs'             => 'phpgwbbstelephonenumber',
			#'tel_modem'           => 'phpgwmodemtelephonenumber',
			#'tel_car'             => 'phpgwmobiletelephonenumber',
			#'tel_isdn'            => 'phpgwisdnphonenumber',
			#'tel_video'           => 'phpgwvideophonenumber',
			#'tel_prefer'          => 'phpgwpreferphone',
			#'email'			=> 'mail',
			#'email_type'          => 'phpgwmailtype',
			'email_home'		=> 'mozillasecondemail',
			#'email_home_type'     => 'phpgwmailhometype',
			'url_home'		=> 'mozillahomeurl',
		);
	
		# homeFacsimileTelephoneNumber
		# otherPhone
		# businessRole
		# managerName
		# assistantName
		# otherPostalAddress
		# mailer
		# anniversary
		# spouseName
		# companyPhone 
		# callbackPhone
		# otherFacsimileTelephoneNumber
		# radio
		# telex
		# tty
		# categories(deprecated)
		# calendarURI
		# freeBusyURI
		var $evolutionPersonFields = array(
			'fn'			=> 'fileas',
			#'n_given'		=> 'givenname',
			#'n_family'		=> 'sn',
			#'n_middle'            => 'phpgwmiddlename',
			#'n_prefix'            => 'phpgwprefix',
			#'n_suffix'            => 'phpgwsuffix',
			#'sound'               => 'phpgwaudio',
			'bday'			=> 'birthdate',
			'note'			=> 'note',
			#'tz'                  => 'phpgwtz',
			#'geo'                 => 'phpgwgeo',
			#'url'			=> 'mozillaworkurl',
			#'pubkey'              => 'phpgwpublickey',

			#'org_name'		=> 'o',
			#'org_unit'		=> 'ou',
			#'title'			=> 'title',

			#'adr_one_street'	=> 'street',
			#'adr_one_locality'	=> 'l',
			#'adr_one_region'	=> 'st',
			#'adr_one_postalcode'	=> 'postalcode',
			#'adr_one_countryname'	=> 'c',
			#'adr_one_type'        => 'phpgwadronetype',
			#'label'               => 'phpgwaddresslabel',

			#'adr_two_street'	=> 'mozillahomestreet',
			#'adr_two_locality'	=> 'mozillahomelocalityname',
			#'adr_two_region'	=> 'mozillahomestate',
			#'adr_two_postalcode'	=> 'mozillahomepostalcode',
			#'adr_two_countryname'	=> 'mozillahomecountryname',
			#'adr_two_type'        => 'phpgwadrtwotype',

			#'tel_work'		=> 'telephonenumber',
			#'tel_home'		=> 'homephone',
			#'tel_voice'           => 'phpgwvoicetelephonenumber',
			#'tel_fax'		=> 'fax',
			#'tel_msg'             => 'phpgwmsgtelephonenumber',
			#'tel_cell'		=> 'mobile',
			#'tel_pager'		=> 'pager',
			#'tel_bbs'             => 'phpgwbbstelephonenumber',
			#'tel_modem'           => 'phpgwmodemtelephonenumber',
			'tel_car'		=> 'carphone',
			#'tel_isdn'            => 'phpgwisdnphonenumber',
			#'tel_video'           => 'phpgwvideophonenumber',
			'tel_prefer'		=> 'primaryphone',
			#'email'		=> 'mail',
			#'email_type'		=> 'phpgwmailtype',
			#'email_home'		=> 'mozillasecondemail',
			#'email_home_type'	=> 'phpgwmailhometype',
			'cat_id'		=> 'category',
			'role'			=> 'businessrole',
			'tel_assistent'		=> 'assistantphone',
			'assistent'		=> 'assistantname',
			'n_fileas'		=> 'fileas',
		);
	
		/**
		 * constructor of the class
		 *
		 */
		function so_ldap()
		{
			//$this->db_data_cols 	= $this->stock_contact_fields + $this->non_contact_fields;
			$this->accountName 		= $GLOBALS['egw_info']['user']['account_lid'];
			
			$this->personalContactsDN	= 'ou=personal,ou=contacts,'. $GLOBALS['egw_info']['server']['ldap_contact_context'];
			$this->sharedContactsDN		= 'ou=shared,ou=contacts,'. $GLOBALS['egw_info']['server']['ldap_contact_context'];
			
			$this->ldap = CreateObject('phpgwapi.ldap');
			$this->ds = $this->ldap->ldapConnect(
				$GLOBALS['egw_info']['server']['ldap_contact_host'],
				$GLOBALS['egw_info']['user']['account_dn'],
				$GLOBALS['egw_info']['user']['passwd']
			);
			$this->ldapServerInfo = $this->ldap->getLDAPServerInfo($GLOBALS['egw_info']['server']['ldap_contact_host']);
		}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($keys,$extra_cols='',$join='')
	{
		$attributes = array_unique(array_merge(array_values($this->inetOrgPersonFields), 
						       array_values($this->mozillaAbPersonFields),
						       array_values($this->evolutionPersonFields)
		));
		
		#$rows = $this->searchLDAP($GLOBALS['egw_info']['server']['ldap_contact_context'], "(|(entryUUID=$keys)(uid=$keys))", $attributes, ADDRESSBOOK_ALL);
		
		#_debug_array($rows);
		
		#return $rows[0];
	
		sort($attributes);
		$attributes[] = 'entryUUID';
		$attributes[] = 'objectClass';
		$attributes[] = 'uid';
		if($result = ldap_search($this->ds, $GLOBALS['egw_info']['server']['ldap_contact_context'], "(|(entryUUID=$keys)(uid=$keys))", $attributes)) {
			$entry = ldap_get_entries($this->ds, $result);
			#_debug_array($entry);
			$contacts[0]['id'] = $entry[0]['uid'][0] ? $entry[0]['uid'][0] : $entry[0]['entryuuid'][0];
			for($i=0; $i<$entry[0]['objectclass']['count']; $i++) {
				switch(strtolower($entry[0]['objectclass'][$i])) {
					case 'inetorgperson':
						foreach($this->inetOrgPersonFields as $egwFieldName => $ldapFieldName) {
							if(!empty($entry[0][$ldapFieldName][0]) && !isset($contacts[0][$egwFieldName])) {
								$contacts[0][$egwFieldName] = $GLOBALS['egw']->translation->convert(($entry[0][$ldapFieldName][0]),'utf-8');
							}
						}

						#print $entry[0]['cn'][0]."<br>";
						#print $entry[0]['sn'][0]."<br>";
						if(empty($entry[0]['givenname'][0])) {
							$parts = preg_split('/'.$entry[0]['sn'][0].'/', $entry[0]['cn'][0]);
							$contacts[0]['n_prefix'] = trim($parts[0]);
							$contacts[0]['n_suffix'] = trim($parts[1]);
						} else {
							$parts = preg_split('/'. $entry[0]['givenname'][0] .'.*'. $entry[0]['sn'][0] .'/', $entry[0]['cn'][0]);
							$contacts[0]['n_prefix'] = trim($parts[0]);
							$contacts[0]['n_suffix'] = trim($parts[1]);
							if(preg_match('/'. $entry[0]['givenname'][0] .' (.*) '. $entry[0]['sn'][0] .'/',$entry[0]['cn'][0], $matches)) {
								$contacts[0]['n_middle'] = $matches[1];
							}
						}
						break;

					case 'mozillaabpersonalpha':
						foreach($this->mozillaAbPersonFields as $egwFieldName => $ldapFieldName) {
							if(!empty($entry[0][$ldapFieldName][0]) && !isset($contacts[0][$egwFieldName])) {
								$contacts[0][$egwFieldName] = $GLOBALS['egw']->translation->convert(($entry[0][$ldapFieldName][0]),'utf-8');
							}
						}
						break;

					case 'evolutionperson':
						foreach($this->evolutionPersonFields as $egwFieldName => $ldapFieldName) {
							if(!empty($entry[0][$ldapFieldName][0]) && !isset($contacts[0][$egwFieldName])) {
								switch($egwFieldName) {
									case 'cat_id':
										for($ii=0; $ii<$entry[0][$ldapFieldName]['count']; $ii++) {
											if(!empty($contacts[0][$egwFieldName])) $contacts[0][$egwFieldName] .= ',';
											$contacts[0][$egwFieldName] .= ExecMethod('phpgwapi.categories.name2id',$entry[0][$ldapFieldName][$ii]);
										}
										break;

					#				case 'bday':
					#					$bdayParts = explode('-',$entry[0][$ldapFieldName][0]);
					#					$contacts[0][$egwFieldName] = $bdayParts[1]. '/' .$bdayParts[2]. '/' .$bdayParts[0];
					#					break;
					
									default;
										if(!empty($entry[0][$ldapFieldName][0]) && !isset($contacts[0][$egwFieldName])) {
											$contacts[0][$egwFieldName] = $GLOBALS['egw']->translation->convert(($entry[0][$ldapFieldName][0]),'utf-8');
										}
										break;
								}
							}
							if(!empty($entry[0]['fileas'][0])) {
								$contacts[0]['fn'] = $GLOBALS['egw']->translation->convert(($entry[0]['fileas'][0]),'utf-8');
							}
						}
						break;
				}
			}
				$contacts[0]['tid'] = 'n';
				if(strpos($entry[0]['dn'],$this->personalContactsDN)) {
					$contacts[0]['access'] = 'private';
					$contacts[0]['owner'] = $GLOBALS['egw_info']['user']['account_id'];
				} else {
					$contacts[0]['access'] = 'public';
					$contacts[0]['owner'] = -1000;
				}
#			if(strpos($entry[0]['dn'],$this->personalContactsDN)) {
#				$contacts[0]['access'] = 'private';
#			} else {
#				$contacts[0]['access'] = 'public';
#			}
		}
#		_debug_array($contacts);
		return $contacts[0];
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null)
	{
		$contactUID = '';
		
		$isUpdate = false;
		$newObjectClasses = array();
		$ldapContact = array();
		$data =& $this->data;
		
		#_debug_array($data);exit;
		// generate addressbook dn
		
		if((int)$data['owner'] < 0) {
			// group address book
			if(!$groupName = strtolower($GLOBALS['egw']->accounts->id2name((int)$data['owner']))) {
				return false;
			}
			$baseDN = 'cn='. $groupName .','. $this->sharedContactsDN;
			$cn	= $groupName;
		} elseif((int)$data['owner'] > 0) {
			// personal addressbook
			$baseDN = 'cn='. strtolower($this->accountName) .','. $this->personalContactsDN;
			$cn	= strtolower($this->accountName);
		} else {
			return false;
		}

		// check if $baseDN exists. If not create new one
		if(!$result = ldap_read($this->ds, $baseDN, 'objectclass=*')) {
			if(ldap_errno($this->ds) == 32) {
				// create a admin connection to add the needed DN
				$adminLDAP = CreateObject('phpgwapi.ldap');
				$adminDS = $adminLDAP->ldapConnect();

				// emtry does not exist, lets try to create it
				$baseDNData['objectClass'] = 'organizationalRole';
				$baseDNData['cn']	= $cn;
				if(!ldap_add($adminDS, $baseDN, $baseDNData)) {
					$adminLDAP->ldapDisconnect();
					return false;
				}
				$adminLDAP->ldapDisconnect();
			} else {
				return false;
			}
		}

		$attributes = array('dn','cn','objectClass','uid');
		if(!empty($this->data[$this->contacts_id])) {
			$contactUID	= $this->data[$this->contacts_id];
			
			$result = ldap_search($this->ds, $GLOBALS['egw_info']['server']['ldap_contact_context'], "(|(entryUUID=$contactUID)(uid=$contactUID))", $attributes);
			
			$oldContactInfo	= ldap_get_entries($this->ds, $result);
			$oldObjectclass	= $oldContactInfo[0]['objectclass'];
		   	$isUpdate = true;
		}
		
		if(!$contactUID) {
			$contactUID = md5($GLOBALS['egw']->common->randomstring(15));
		}

		$ldapContact['uid'] = $contactUID;

		if($this->ldapServerInfo->supportsObjectClass('inetOrgPerson')) {
			if(!$isUpdate) {
				$ldapContact['objectclass'][] = 'inetOrgPerson';
				$ldapContact['objectclass'][] = 'person';
			} else {
				if(!in_array('person', $oldObjectclass)) {
					$newObjectClasses['objectClass'][] = 'person';
				}
				if(!in_array('inetOrgPerson', $oldObjectclass) && !in_array('inetorgperson', $oldObjectclass)) {
					$newObjectClasses['objectClass'][] = 'inetOrgPerson';
				}
			}
			foreach($this->inetOrgPersonFields as $egwFieldName => $ldapFieldName) {
				if(!empty($data[$egwFieldName])) {
					switch($ldapFieldName) {
						case 'cn':
							$cneGWFields = array('n_prefix', 'n_given', 'n_middle', 'n_family', 'n_suffix');
							foreach($cneGWFields as $cn_eGWField) {
								if(!empty($data[$cn_eGWField])) {
									$ldapContact[$ldapFieldName] .= $data[$cn_eGWField].' ';
								}
							}
							$ldapContact[$ldapFieldName] = trim($ldapContact[$ldapFieldName]);
							break;
						default:
							$ldapContact[$ldapFieldName] = $GLOBALS['egw']->translation->convert(trim($data[$egwFieldName]),$GLOBALS['egw']->translation->charset(),'utf-8');
							break;
					}
				} elseif($isUpdate) {
					$ldapContact[$ldapFieldName] = array();
				}
			}
		}

		if($this->ldapServerInfo->supportsObjectClass('mozillaAbPersonAlpha')) {
			if(!$isUpdate) {
				$ldapContact['objectclass'][] = 'mozillaAbPersonAlpha';
			} else {
				if(!in_array('mozillaAbPersonAlpha', $oldObjectclass) && !in_array('mozillaabpersonalpha', $oldObjectclass)) {
					$newObjectClasses['objectClass'][] = 'mozillaAbPersonAlpha';
				}
			}
			foreach($this->mozillaAbPersonFields as $egwFieldName => $ldapFieldName) {
				if(empty($ldapContact[$ldapFieldName]) && !empty($data[$egwFieldName])) {
					$ldapContact[$ldapFieldName] = $GLOBALS['egw']->translation->convert(trim($data[$egwFieldName]),$GLOBALS['egw']->translation->charset(),'utf-8');
				} elseif(empty($ldapContact[$ldapFieldName]) && $isUpdate) {
					$ldapContact[$ldapFieldName] = array();
				}
			}
		}

		if($this->ldapServerInfo->supportsObjectClass('mozillaOrgPerson')) {
			if(!$isUpdate) {
				$ldapContact['objectclass'][] = 'mozillaOrgPerson';
			} else {
				if(!in_array('mozillaOrgPerson', $oldObjectclass) && !in_array('mozillaorgperson', $oldObjectclass)) {
					$newObjectClasses['objectClass'][] = 'mozillaOrgPerson';
				}
			}
			foreach($this->mozillaAbPersonFields as $egwFieldName => $ldapFieldName) {
				if(empty($ldapContact[$ldapFieldName]) && !empty($data[$egwFieldName])) {
					$ldapContact[$ldapFieldName] = $GLOBALS['egw']->translation->convert(trim($data[$egwFieldName]),$GLOBALS['egw']->translation->charset(),'utf-8');
				} elseif(empty($ldapContact[$ldapFieldName]) && $isUpdate) {
					$ldapContact[$ldapFieldName] = array();
				}
			}
		}

		if($this->ldapServerInfo->supportsObjectClass('evolutionPerson')) {
			if(!$isUpdate) {
				$ldapContact['objectclass'][] = 'evolutionPerson';
			} else {
				if(!in_array('evolutionPerson', $oldObjectclass) && !in_array('evolutionperson', $oldObjectclass)) {
					$newObjectClasses['objectClass'][] = 'evolutionPerson';
				}
			}
			foreach($this->evolutionPersonFields as $egwFieldName => $ldapFieldName) {
				if(empty($ldapContact[$ldapFieldName]) && !empty($data[$egwFieldName])) {
					switch($egwFieldName) {
						case 'cat_id':
							if(!empty($data[$egwFieldName])) {
								$catIDs = explode(',',$data[$egwFieldName]);
								foreach($catIDs as $value) {
									$ldapContact[$ldapFieldName][] = $GLOBALS['egw']->translation->convert(ExecMethod('phpgwapi.categories.id2name',$value),$GLOBALS['egw']->translation->charset(),'utf-8');
								}
							}
							break;

					#	case 'bday':
					#		$dateParts = explode('/',$data[$egwFieldName]);
					#		$bday = $dateParts[2] .'-'. $dateParts[0] .'-'. $dateParts[1];
					#		$ldapContact[$ldapFieldName] = $bday;
					#		break;

						default:
							$ldapContact[$ldapFieldName] = $GLOBALS['egw']->translation->convert(trim($data[$egwFieldName]),$GLOBALS['egw']->translation->charset(),'utf-8');
							break;
					}
				} elseif(empty($ldapContact[$ldapFieldName]) && $isUpdate) {
					$ldapContact[$ldapFieldName] = array();
				}
				$postalAddress = $data['adr_one_street'] .'$'. $data['adr_one_locality'] .', '. $data['adr_one_region'] .'$'. $data['adr_one_postalcode'] .'$$'. $data['adr_one_countryname'];
				if($postalAddress != '$, $$$') {
					$ldapContact['postalAddress'] = $GLOBALS['egw']->translation->convert($postalAddress,$GLOBALS['egw']->translation->charset(),'utf-8');
				} elseif($isUpdate) {
					$ldapContact['postalAddress'] = array();
				}
				$homePostalAddress = $data['adr_two_street'] .'$'. $data['adr_two_locality'] .', '. $data['adr_two_region'] .'$'. $data['adr_two_postalcode'] .'$$'. $data['adr_two_countryname'];
				if($homePostalAddress != '$, $$$') {
					$ldapContact['homePostalAddress'] = $GLOBALS['egw']->translation->convert($homePostalAddress,$GLOBALS['egw']->translation->charset(),'utf-8');
				} elseif($isUpdate) {
					$ldapContact['homePostalAddress'] = array();
				}
			}
			#if(!empty($ldapContact['givenname']) && !empty($ldapContact['sn'])) {
			#	$ldapContact['fileas'] = $ldapContact['sn'] .', '. $ldapContact['givenname'];
			#} else {
			#	$ldapContact['fileas'] = $ldapContact['sn'];
			#}
		}

		if($isUpdate) {
			// update entry
			$dn = $oldContactInfo[0]['dn'];
			$needRecreation = false;

			// add missing objectclasses
			if(count($newObjectClasses) > 0) {
				$result = @ldap_mod_add($this->ds, $dn, $newObjectClasses);
				if(!$result) {
					#print 'class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')';exit;
					if(ldap_errno($this->ds) == 69) {
						// need to modify structural objectclass
						$needRecreation = true;
					} else {
						error_log('class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
						return false;
					}
				}
			}
			
			// check if we need to rename the DN or need to recreate the contact
			$newRDN = 'uid='. $contactUID;
			$newDN = $newRDN .','. $baseDN;
			if(strtolower($dn) != strtolower($newDN) || $needRecreation) {
				$result = ldap_read($this->ds, $dn, 'objectclass=*');
				$oldContact = ldap_get_entries($this->ds, $result);
				foreach($oldContact[0] as $key => $value) {
					if(is_array($value)) {
						unset($value['count']);
						$newContact[$key] = $value;
					}
				}
				$newContact['uid'] = $contactUID;

				if(is_array($newObjectClasses['objectClass']) && count($newObjectClasses['objectClass']) > 0) {
					$newContact['objectclass'] = array_merge($newContact['objectclass'], $newObjectClasses['objectClass']);
				}

				if(ldap_delete($this->ds, $dn)) {
					if(!ldap_add($this->ds, $newDN, $newContact)) {
						print 'class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')';_debug_array($newContact);exit;
					}
				} else {
					error_log('class.so_ldap.inc.php ('. __LINE__ .') delete of old '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
					return false;
				}

				$dn = $newDN;
			}

			#_debug_array($ldapContact);exit;
			$result = ldap_modify($this->ds, $dn, $ldapContact);
			if (!$result) {
				error_log('class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
				#print 'class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')';exit;
				#_debug_array($ldapContact);exit;
				return false;
			}
		} else {
			$dn = 'uid='. $ldapContact['uid'] .','. $baseDN;
			
			#print "Save $dn<bR>";_debug_array($ldapContact);exit;
			$result = ldap_add($this->ds, $dn, $ldapContact);
			
			if (!$result) {
				error_log('class.so_ldap.inc.php ('. __LINE__ .') add of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')');
				return false;
			}
		}

		return true;
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		// single entry
		if($keys[$this->contacts_id]) $keys = array( 0 => $keys);
		
		if(!is_array($keys)) {
			$keys = array( 0 => $keys);
		}

		$ret = 0;

		$attributes = array('dn');

		foreach($keys as $entry)
		{
			if($result = ldap_search($this->ds, $GLOBALS['egw_info']['server']['ldap_contact_context'], "(|(entryUUID=$entry)(uid=$entry))", $attributes)) {
				$contactInfo = ldap_get_entries($this->ds, $result);
				if(ldap_delete($this->ds, $contactInfo[0]['dn'])) {
					$ret++;
				}
			}
		}
		return $ret;
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		#_debug_array($criteria); print "OrderBY: $order_by";_debug_array($extra_cols);_debug_array($filter);
		#$order_by = explode(',',$order_by);
		#$order_by = explode(' ',$order_by);
		#$sort = $order_by[0];
		#$order = $order_by[1];
		#$query = $criteria;
		#$fields = $only_keys ? ($only_keys === true ? $this->contacts_id : $only_keys) : '';
		#$limit = $need_full_no_count ? 0 : $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		#return parent::read($start,$limit,$fields,$query,$filter,$sort,$order);

		$searchFilter = '';
		$categoryFilter = '';
		
		$ownerID = (int)$filter['owner'];
		
		if($ownerID < 0) {
			if($groupName = $GLOBALS['egw']->accounts->id2name($ownerID)) {
				$searchDN = 'cn='. strtolower($groupName) .','. $this->sharedContactsDN;
				$addressbookType = ADDRESSBOOK_GROUP;
			} else {
				return false;
			}
		} elseif($ownerID > 0) {
			$searchDN = 'cn='. strtolower($this->accountName) .','. $this->personalContactsDN;
			$addressbookType = ADDRESSBOOK_PERSONAL;
		} else {
			$searchDN = $GLOBALS['egw_info']['server']['ldap_contact_context'];
			$addressbookType = ADDRESSBOOK_ALL;
		}
		
		// create the search filter
		switch($addressbookType) {
			case ADDRESSBOOK_ALL:
				$objectFilter = '(|(objectclass=inetorgperson)(objectclass=posixaccount))';
				$attributes = array_unique(array_merge(array_values($this->inetOrgPersonFields), 
								       array_values($this->mozillaAbPersonFields),
								       array_values($this->evolutionPersonFields)
				));
				break;
			case ADDRESSBOOK_ACCOUNTS:
				$objectFilter = '(objectclass=posixaccount)';
				$attributes = array_unique(array_merge(array_values($this->inetOrgPersonFields), 
								       array_values($this->mozillaAbPersonFields),
								       array_values($this->evolutionPersonFields)
				));
				break;
				break;
			default:
				$objectFilter = '(objectclass=inetorgperson)';
				$attributes = array_unique(array_merge(array_values($this->inetOrgPersonFields), 
								       array_values($this->mozillaAbPersonFields),
								       array_values($this->evolutionPersonFields)
				));
				break;
		}
		sort($attributes);
		
		if($catID = (int)$filter['cat_id']) {
			$catName = $GLOBALS['egw']->translation->convert(ExecMethod('phpgwapi.categories.id2name',$catID),$GLOBALS['egw']->translation->charset(),'utf-8');
			$categoryFilter = "(category=$catName)";
		}
		
		# 
		if(is_array($criteria) && count($criteria) > 0) {
			$searchFilter = '';
			foreach($criteria as $egwSearchKey => $searchValue) {
				$ldapSearchKey = '';
				if($ldapSearchKey = $this->inetOrgPersonFields[$egwSearchKey]) {
					#print $ldapSearchKey.$searchValue.'<br>';
					$searchString = $GLOBALS['egw']->translation->convert($searchValue,$GLOBALS['egw']->translation->charset(),'utf-8');
					$searchFilter .= "($ldapSearchKey=$wildcard$searchString$wildcard)";
				}
			}
			if($op == 'AND') {
				$searchFilter = "(&$searchFilter)";
			} else {
				$searchFilter = "(|$searchFilter)";
			}
		}
		$ldapFilter = "(&$objectFilter$categoryFilter$searchFilter)";
		
		$rows = $this->searchLDAP($searchDN, $ldapFilter, $attributes, $addressbookType);

		#_debug_array($rows);
		return $rows;
	}
	
	function searchLDAP($_ldapContext, $_filter, $_attributes, $_addressbooktype) {
		$this->total = 0;
		
		$_attributes[] = 'entryUUID';
		$_attributes[] = 'uid';
		$_attributes[] = 'objectClass';
		$_attributes[] = 'createTimestamp';
		$_attributes[] = 'modifyTimestamp';

		if($_addressbooktype == ADDRESSBOOK_ALL) {
			$result = ldap_search($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit);
		} else {
			$result = ldap_list($this->ds, $_ldapContext, $_filter, $_attributes, 0, $this->ldapLimit);
		}

		#print 'class.so_ldap.inc.php ('. __LINE__ .') update of '. $dn .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')';		
		if($result) {
			$entries = ldap_get_entries($this->ds, $result);
			$this->total = $entries['count'];
			for($i=0; $i<$entries['count']; $i++) {
				$contacts[$i]['id'] = $entries[$i]['uid'][0] ? $entries[$i]['uid'][0] : $entries[$i]['entryuuid'][0];
				for($ii=0; $ii<$entries[$i]['objectclass']['count']; $ii++) {
					switch(strtolower($entries[$i]['objectclass'][$ii])) {
						case 'inetorgperson':
							foreach($this->inetOrgPersonFields as $egwFieldName => $ldapFieldName) {
								if(!empty($entries[$i][$ldapFieldName][0]) && !isset($contacts[$i][$egwFieldName])) {
									$contacts[$i][$egwFieldName] = $GLOBALS['egw']->translation->convert(($entries[$i][$ldapFieldName][0]),'utf-8');
								}
							}
							
							#print $entries[$i]['cn'][0]."<br>";
							#print $entries[$i]['sn'][0]."<br>";
							if(empty($entries[$i]['givenname'][0])) {
								$parts = preg_split('/'.$entries[$i]['sn'][0].'/', $entries[$i]['cn'][0]);
								$contacts[$i]['n_prefix'] = trim($parts[0]);
								$contacts[$i]['n_suffix'] = trim($parts[1]);
							} else {
								$parts = preg_split('/'. $entries[$i]['givenname'][0] .'.*'. $entries[$i]['sn'][0] .'/', $entries[$i]['cn'][0]);
								$contacts[$i]['n_prefix'] = trim($parts[0]);
								$contacts[$i]['n_suffix'] = trim($parts[1]);
								if(preg_match('/'. $entries[$i]['givenname'][0] .' (.*) '. $entries[$i]['sn'][0] .'/',$entries[$i]['cn'][0], $matches)) {
									$contacts[$i]['n_middle'] = $matches[1];
								}
							}
							break;
							
						case 'mozillaabpersonalpha':
							foreach($this->mozillaAbPersonFields as $egwFieldName => $ldapFieldName) {
								if(!empty($entries[$i][$ldapFieldName][0]) && !isset($contacts[$i][$egwFieldName])) {
									$contacts[$i][$egwFieldName] = $GLOBALS['egw']->translation->convert(($entries[$i][$ldapFieldName][0]),'utf-8');
								}
							}
							break;

						case 'evolutionperson':
							foreach($this->evolutionPersonFields as $egwFieldName => $ldapFieldName) {
								if(!empty($entries[$i][$ldapFieldName][0]) && !isset($contacts[$i][$egwFieldName])) {
									switch($egwFieldName) {
										case 'cat_id':
											for($iii=0; $iii<$entries[$i][$ldapFieldName]['count']; $iii++) {
												if(!empty($contacts[$i][$egwFieldName])) $contacts[$i][$egwFieldName] .= ',';
												$contacts[$i][$egwFieldName] .= ExecMethod('phpgwapi.categories.name2id',$entries[$i][$ldapFieldName][$iii]);
											}
											break;

										case 'bday':
											$bdayParts = explode('-',$entries[$i][$ldapFieldName][0]);
											$contacts[$i][$egwFieldName] = $bdayParts[1]. '/' .$bdayParts[2]. '/' .$bdayParts[0];
											break;
										default;
											if(!empty($entries[$i][$ldapFieldName][0]) && !isset($contacts[$i][$egwFieldName])) {
												$contacts[$i][$egwFieldName] = $GLOBALS['egw']->translation->convert(($entries[$i][$ldapFieldName][0]),'utf-8');
											}
											break;
									}
								}
								if(!empty($entries[$i]['fileas'][0])) {
									$contacts[$i]['fn'] = $GLOBALS['egw']->translation->convert(($entries[$i]['fileas'][0]),'utf-8');
								}
							}
							break;
					}
				}
				// the template id for the addressbook
				$contacts[$i]['tid'] = 'n';
				
				if(strpos($entries[$i]['dn'],$this->personalContactsDN)) {
					$contacts[$i]['access'] = 'private';
					$contacts[$i]['owner'] = $GLOBALS['egw_info']['user']['account_id'];
				} else {
					$contacts[$i]['access'] = 'public';
					$contacts[$i]['owner'] = -1000;
				}

				# modifier
				# creator
				if(!empty($entries[$i]['createtimestamp'][0])) {
					$year	= substr($entries[$i]['createtimestamp'][0],0,4);
					$month	= substr($entries[$i]['createtimestamp'][0],4,2);
					$day	= substr($entries[$i]['createtimestamp'][0],6,2);
					$hour	= substr($entries[$i]['createtimestamp'][0],8,2);
					$minute	= substr($entries[$i]['createtimestamp'][0],10,2);
					$second	= substr($entries[$i]['createtimestamp'][0],12,2);
					$contacts[$i]['created'] = mktime($hour, $minute, $second, $month, $day, $year);
				}

				if(!empty($entries[$i]['modifytimestamp'][0])) {
					$year	= substr($entries[$i]['modifytimestamp'][0],0,4);
					$month	= substr($entries[$i]['modifytimestamp'][0],4,2);
					$day	= substr($entries[$i]['modifytimestamp'][0],6,2);
					$hour	= substr($entries[$i]['modifytimestamp'][0],8,2);
					$minute	= substr($entries[$i]['modifytimestamp'][0],10,2);
					$second	= substr($entries[$i]['modifytimestamp'][0],12,2);
					$contacts[$i]['modified'] = mktime($hour, $minute, $second, $month, $day, $year);
				}
			}
			
			#_debug_array($contacts);
			#exit;
			return $contacts;
		}
		#print 'class.so_ldap.inc.php ('. __LINE__ .') renaming of '. $dn .' to '. $newDN .' failed errorcode: '. ldap_errno($this->ds) .' ('. ldap_error($this->ds) .')';
		#print "bad<br>";
		return array();
	}

}
