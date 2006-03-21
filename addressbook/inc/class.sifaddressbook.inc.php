<?php
	/**************************************************************************\
	* eGroupWare - iCalendar Parser                                            *
	* http://www.egroupware.org                                                *
	* Written by Lars Kneschke <lkneschke@egroupware.org>                      *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	\**************************************************************************/

	/* $Id$ */

	require_once EGW_SERVER_ROOT.'/addressbook/inc/class.boaddressbook.inc.php';
	#require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/Horde/iCalendar.php';

	class sifaddressbook extends boaddressbook
	{
		var $sifMapping = array(
			'Anniversary'			=> '',
			'AssistantName'			=> '',
			'AssistantTelephoneNumber'	=> '',
			'BillingInformation'		=> '',
			'Birthday'			=> 'bday',
			'Body'				=> 'note',
			'Business2TelephoneNumber'	=> '',
			'BusinessAddressCity'		=> 'adr_one_locality',
			'BusinessAddressCountry'	=> 'adr_one_countryname',
			'BusinessAddressPostalCode'	=> 'adr_one_postalcode',
			'BusinessAddressPostOfficeBox'	=> '',
			'BusinessAddressState'		=> 'adr_one_region',
			'BusinessAddressStreet'		=> 'adr_one_street',
			'BusinessFaxNumber'		=> 'tel_fax',
			'BusinessTelephoneNumber'	=> 'tel_work',
			'CallbackTelephoneNumber'	=> '',
			'CarTelephoneNumber'		=> '',
			'Categories'			=> 'cat_id',
			'Children'			=> '',
			'Companies'			=> '',
			'CompanyMainTelephoneNumber'	=> '',
			'CompanyName'			=> 'org_name',
			'ComputerNetworkName'		=> '',
			'Department'			=> 'org_unit',
			'Email1Address'			=> 'email',
			'Email1AddressType'		=> '',
			'Email2Address'			=> 'email_home',
			'Email2AddressType'		=> '',
			'Email3Address'			=> '',
			'Email3AddressType'		=> '',
			'FileAs'			=> '',
			'FirstName'			=> 'n_given',
			'Hobby'				=> '',
			'Home2TelephoneNumber'		=> '',
			'HomeAddressCity'		=> 'adr_two_locality',
			'HomeAddressCountry'		=> 'adr_two_countryname',
			'HomeAddressPostalCode'		=> 'adr_two_postalcode',
			'HomeAddressPostOfficeBox'	=> '',
			'HomeAddressState'		=> 'adr_two_region',
			'HomeAddressStreet'		=> 'adr_two_street',
			'HomeFaxNumber'			=> '',
			'HomeTelephoneNumber'		=> 'tel_home',
			'Importance'			=> '',
			'Initials'			=> '',
			'JobTitle'			=> 'title',
			'Language'			=> '',
			'LastName'			=> 'n_family',
			'ManagerName'			=> '',
			'MiddleName'			=> 'n_middle',
			'Mileage'			=> '',
			'MobileTelephoneNumber'		=> 'tel_cell',
			'NickName'			=> '',
			'OfficeLocation'		=> '',
			'OrganizationalIDNumber'	=> '',
			'OtherAddressCity'		=> '',
			'OtherAddressCountry'		=> '',
			'OtherAddressPostalCode'	=> '',
			'OtherAddressPostOfficeBox'	=> '',
			'OtherAddressState'		=> '',
			'OtherAddressStreet'		=> '',
			'OtherFaxNumber'		=> '',
			'OtherTelephoneNumber'		=> '',
			'PagerNumber'			=> 'tel_pager',
			'PrimaryTelephoneNumber'	=> '',
			'Profession'			=> '',
			'RadioTelephoneNumber'		=> '',
			'Sensitivity'			=> 'access',
			'Spouse'			=> '',
			'Subject'			=> '',
			'Suffix'			=> 'n_suffix',
			'TelexNumber'			=> '',
			'Title'				=> 'n_prefix',
			'WebPage'			=> 'url',
			'YomiCompanyName'		=> '',
			'YomiFirstName'			=> '',
			'YomiLastName'			=> '',
			'HomeWebPage'			=> '',
			'Folder'			=> '',
		);

		function startElement($_parser, $_tag, $_attributes) {
		}

		function endElement($_parser, $_tag) {
			if(!empty($this->sifMapping[$_tag])) {
				$this->contact[$this->sifMapping[$_tag]] = $this->sifData;
			}
			unset($this->sifData);
		}
		
		function characterData($_parser, $_data) {
			$this->sifData .= $_data;
		}
		
		function siftoegw($_sifdata) {
			$sysCharSet	= $GLOBALS['egw']->translation->charset();
			$sifData	= base64_decode($_sifdata);

			#$tmpfname = tempnam('/tmp/sync/contents','sifc_');

			#$handle = fopen($tmpfname, "w");
			#fwrite($handle, $sifdata);
			#fclose($handle);

			$this->xml_parser = xml_parser_create('UTF-8');
			xml_set_object($this->xml_parser, $this);
			xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, false);
			xml_set_element_handler($this->xml_parser, "startElement", "endElement");
			xml_set_character_data_handler($this->xml_parser, "characterData");
			$this->strXmlData = xml_parse($this->xml_parser, $sifdata);
			if(!$this->strXmlData) {
				error_log(sprintf("XML error: %s at line %d",
					xml_error_string(xml_get_error_code($this->xml_parser)),
					xml_get_current_line_number($this->xml_parser)));
				return false;
			}

			foreach($this->contact as $key => $value) {
				$value = $GLOBALS['egw']->translation->convert($value, 'utf-8', $sysCharSet);
				switch($key) {
					case 'access':
						$finalContact[$key] = ((int)$value > 0) ? 'private' : 'public';
						break;
						
					case 'cat_id':
						if(!empty($value)) {
							$isAdmin = $GLOBALS['egw']->acl->check('run',1,'admin');
							$egwCategories =& CreateObject('phpgwapi.categories',$GLOBALS['egw_info']['user']['account_id'],'addressbook');
							$categories = explode('; ',$value);
							$cat_id = '';
							foreach($categories as $categorieName) {
								$categorieName = trim($categorieName);
								if(!($cat_id = $egwCategories->name2id($categorieName)) && $isAdmin) {
									$cat_id = $egwCategories->add(array('name' => $categorieName, 'descr' => $categorieName));
								}
								if($cat_id) {
									if(!empty($finalContact[$key])) $finalContact[$key] .= ',';
									 $finalContact[$key] .= $cat_id;
								}
							}
						}
						break;
						
					case 'bday':
						if(!empty($value)) {
							$bdayParts = explode('-',$value);
							$finalContact[$key] = $bdayParts[1]. '/' .$bdayParts[2]. '/' .$bdayParts[0];
						}
						break;
						
					default:
						$finalContact[$key] = $value;
						break;
				}
			}
			
			$middleName = ($finalContact['n_middle']) ? ' '.trim($finalContact['n_middle']) : '';
			$finalContact['fn']  = trim($finalContact['n_given']. $middleName .' '. $finalContact['n_family']);

			
			return $finalContact;
		}
		
		function search($_sifdata) {
			if(!$contact = $this->siftoegw($_sifdata)) {
				return false;
			}
			
			if($foundContacts = $this->read_entries(array('query' => $contact))) {
				error_log(print_r($foundContacts,true));
				return $foundContacts[0][id];
			}
			
			return false;
		}

		/**
		* @return int contact id
		* @param string	$_vcard		the vcard
		* @param int	$_abID		the internal addressbook id
		* @desc import a vard into addressbook
		*/
		function addSIF($_sifdata, $_abID)
		{
			#error_log('ABID: '.$_abID);
			#error_log(base64_decode($_sifdata));
			
			if(!$contact = $this->siftoegw($_sifdata)) {
				return false;
			}

			if($_abID > 0)
			{
				// update entry
				$contact['ab_id'] = $_abID;
				return $this->update_entry($contact);
			}
			else
			{
				// add entry
				return $this->add_entry($contact);
			}
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

			if($this->check_perms($_id,EGW_ACL_READ))
			{
				$sifContact = '<contact>';
				//$data = array('id' => $_id, 'fields' => $fields);
				$entry = $this->so->read_entry($_id,$fields);
				$entry = $this->strip_html($entry);
				if($this->xmlrpc)
				{
					$entry = $this->data2xmlrpc($entry);
				}
				#error_log(print_r($entry,true));
				$sysCharSet	= $GLOBALS['egw']->translation->charset();

				foreach($this->sifMapping as $sifField => $egwField)
				{
					if(empty($egwField)) continue;
					
					#error_log("$sifField => $egwField");
					#error_log('VALUE1: '.$entry[0][$egwField]);
					$value = $GLOBALS['egw']->translation->convert($entry[0][$egwField], $sysCharSet, 'utf-8');
					#error_log('VALUE2: '.$value);

					switch($sifField)
					{
						// TODO handle multiple categories
						case 'Categories':
							if(!empty($value)) {
								$egwCategories =& CreateObject('phpgwapi.categories',$GLOBALS['egw_info']['user']['account_id'],'addressbook');
								$categories = explode(',',$value);
								$value = '';
								foreach($categories as $cat_id) {
									if($catData = $egwCategories->return_single($cat_id)) {
										if(!empty($value)) $value .= '; ';
										$value .= $catData[0]['name'];
									}
								}
							}
							$sifContact .= "<$sifField>$value</$sifField>";							
							break;
							
						case 'Sensitivity':
							$value = ($value == 'private' ? '2' : '0');
							$sifContact .= "<$sifField>$value</$sifField>";							
							break;
							
						case 'Birthday':
							if(!empty($value)) {
								$dateParts = explode('/',$value);
								$value = sprintf('%04-d%02-d%02',$dateParts[2],$dateParts[0],$dateParts[1]);
							}
							$sifContact .= "<$sifField>$value</$sifField>";
							break;
							
						case 'Folder':
							# skip currently. This is the folder where Outlook stores the contact.
							#$sifContact .= "<$sifField>/</$sifField>";
							break;
							
						default:
							$sifContact .= "<$sifField>$value</$sifField>";
							break;
					}
				}
				$sifContact .= "</contact>";

				return base64_encode($sifContact);
			}

			if($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return False;
		}

	}
