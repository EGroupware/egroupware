<?php
	/***************************************************************************\
	* EGroupWare - EMailAdmin                                                   *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id: class.cyrusimap.inc.php,v 1.9 2005/12/02 15:44:31 ralfbecker Exp $ */
	
	include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultimap.inc.php");
	
	class dbmailqmailuser extends defaultimap {
		var $enableSieve = false;
		
		var $sieveHost;
		
		var $sievePort;
		
		function addAccount($_hookValues) {
			return $this->updateAccount($_hookValues);
		}
		
		#function deleteAccount($_hookValues) {
		#}
		function getUserData($_username) {
			$userData = array();
			
			$ds = $GLOBALS['egw']->ldap->ldapConnect(
				$GLOBALS['egw_info']['server']['ldap_host'],
				$GLOBALS['egw_info']['server']['ldap_root_dn'],
				$GLOBALS['egw_info']['server']['ldap_root_pw']
			);
			
			if(!is_resource($ds)) {
				return false;
			}

			$filter		= '(&(objectclass=posixaccount)(uid='. $_username .')(qmailGID='. sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id'])) .'))';
			$justthese	= array('dn', 'objectclass', 'mailQuota');
			if($sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $justthese)) {

				if($info = ldap_get_entries($ds, $sri)) {
					if(isset($info[0]['mailquota'][0])) {
						$userData['quotaLimit'] = $info[0]['mailquota'][0] / 1048576;
					}
				}
			}
			return $userData;
		}

		function updateAccount($_hookValues) {
			if(!$uidnumber = (int)$_hookValues['account_id']) {
				return false;
			}
			
			$ds = $GLOBALS['egw']->ldap->ldapConnect(
				$GLOBALS['egw_info']['server']['ldap_host'],
				$GLOBALS['egw_info']['server']['ldap_root_dn'],
				$GLOBALS['egw_info']['server']['ldap_root_pw']
			);
			
			if(!is_resource($ds)) {
				return false;
			}

			$filter		= '(&(objectclass=posixaccount)(uidnumber='. $uidnumber .'))';
			$justthese	= array('dn', 'objectclass', 'qmailUID', 'qmailGID', 'mail');
			$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $justthese);
			
			if($info = ldap_get_entries($ds, $sri)) {
				if(!in_array('qmailuser',$info[0]['objectclass']) && $info[0]['email']) {
					$newData['objectclass'] = $info[0]['objectclass'];
					unset($newData['objectclass']['count']);
					$newData['objectclass'][] = 'qmailuser';
					sort($newData['objectclass']);
					$newData['qmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));
					#$newData['qmailUID']	= (!empty($this->domainName)) ? $_username .'@'. $this->domainName : $_username;
					
					ldap_modify($ds, $info[0]['dn'], $newData);
					
					return true;
				} else {
					$newData = array();
					$newData['qmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));
					#$newData['qmailUID']	= (!empty($this->domainName)) ? $_username .'@'. $this->domainName : $_username;

					if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
						#print ldap_error($ds);
						#return false;
					}
				}
			}

			return false;
		}

		function setUserData($_username, $_quota) {
			$ds = $GLOBALS['egw']->ldap->ldapConnect(
				$GLOBALS['egw_info']['server']['ldap_host'],
				$GLOBALS['egw_info']['server']['ldap_root_dn'],
				$GLOBALS['egw_info']['server']['ldap_root_pw']
			);
			
			if(!is_resource($ds)) {
				return false;
			}

			$filter		= '(&(objectclass=posixaccount)(uid='. $_username .'))';
			$justthese	= array('dn', 'objectclass', 'qmailGID', 'mail');
			$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $justthese);

			if($info = ldap_get_entries($ds, $sri)) {
				#_debug_array($info);
				if(!in_array('qmailuser',$info[0]['objectclass']) && $info[0]['email']) {
					$newData['objectclass'] = $info[0]['objectclass'];
					unset($newData['objectclass']['count']);
					$newData['objectclass'][] = 'qmailuser';
					sort($newData['objectclass']);
					$newData['qmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));
					
					ldap_modify($ds, $info[0]['dn'], $newData);
				} else {
					if (in_array('qmailuser',$info[0]['objectclass']) && !$info[0]['qmailgid']) {
						$newData = array();
						$newData['qmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));

						if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
							#print ldap_error($ds);
							#return false;
						}
					}
				}
					
				$newData = array();
				
				if((int)$_quota >= 0) {
					$newData['mailQuota'] = (int)$_quota * 1048576;
				} else {
					$newData['mailQuota'] = array();
				}
				
				if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
					#print ldap_error($ds);
					return false;
				}
				
				return true;
			}

			return false;
		}

	}
?>
