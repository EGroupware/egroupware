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
	
	class dbmaildbmailuser extends defaultimap {
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

			$filter		= '(&(objectclass=posixaccount)(uid='. $_username .')(dbmailGID='. sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id'])) .'))';
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
			$justthese	= array('dn', 'objectclass', 'dbmailUID', 'dbmailGID', 'mail');
			$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $justthese);

			if($info = ldap_get_entries($ds, $sri)) {
				if((!in_array('dbmailuser',$info[0]['objectclass']) && !in_array('dbmailUser',$info[0]['objectclass'])) && $info[0]['mail']) {
					$newData['objectclass'] = $info[0]['objectclass'];
					unset($newData['objectclass']['count']);
					$newData['objectclass'][] = 'dbmailuser';
					sort($newData['objectclass']);
					$newData['dbmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));
					$newData['dbmailUID']	= (!empty($this->domainName)) ? $_hookValues['account_lid'] .'@'. $this->domainName : $_hookValues['account_lid'];
					
					if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
						#print ldap_error($ds);
					}
					
					return true;
				} else {
					$newData = array();
					$newData['dbmailUID']	= (!empty($this->domainName)) ? $_hookValues['account_lid'] .'@'. $this->domainName : $_hookValues['account_lid'];
					$newData['dbmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));
						
					if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
						print ldap_error($ds);
						_debug_array($newData);
						exit;
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
			$justthese	= array('dn', 'objectclass', 'dbmailGID', 'dbmailUID', 'mail');
			$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $justthese);

			if($info = ldap_get_entries($ds, $sri)) {
				$validLDAPConfig = false;
				if(in_array('dbmailuser',$info[0]['objectclass']) || in_array('dbmailUser',$info[0]['objectclass'])) {
					$validLDAPConfig = true;
				}

				if(!in_array('dbmailuser',$info[0]['objectclass']) && !in_array('dbmailUser',$info[0]['objectclass']) && $info[0]['mail']) {
					$newData['objectclass'] = $info[0]['objectclass'];
					unset($newData['objectclass']['count']);
					$newData['objectclass'][] = 'dbmailUser';
					sort($newData['objectclass']);
					$newData['dbmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));
					$newData['dbmailUID']	= (!empty($this->domainName)) ? $_username .'@'. $this->domainName : $_username;
					
					if(ldap_modify($ds, $info[0]['dn'], $newData)) {
						$validLDAPConfig = true;
					}
				} else {
					if ((in_array('dbmailuser',$info[0]['objectclass']) || in_array('dbmailUser',$info[0]['objectclass'])) && !$info[0]['dbmailuid']) {
						$newData = array();
						$newData['dbmailUID']	= (!empty($this->domainName)) ? $_username .'@'. $this->domainName : $_username;

						if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
							#print ldap_error($ds);
							#return false;
						}
					}
				
					if ((in_array('dbmailuser',$info[0]['objectclass']) || in_array('dbmailUser',$info[0]['objectclass'])) && !$info[0]['dbmailgid']) {
						$newData = array();
						$newData['dbmailGID']	= sprintf("%u", crc32($GLOBALS['egw_info']['server']['install_id']));

						if(!ldap_modify($ds, $info[0]['dn'], $newData)) {
							#print ldap_error($ds);
							#return false;
						}
					}
				}
					
				if($validLDAPConfig) {
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
				}
				return true;
			}

			return false;
		}

	}
?>
