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
	/* $Id: class.postfixldap.inc.php 22439 2006-09-16 09:19:43Z ralfbecker $ */

	include_once(EGW_SERVER_ROOT."/emailadmin/inc/class.defaultsmtp.inc.php");

	class postfixdbmailuser extends defaultsmtp
	{
		function addAccount($_hookValues)
		{
			$mailLocalAddress = $_hookValues['account_email'] ? $_hookValues['account_email'] :
				$GLOBALS['egw']->common->email_address($_hookValues['account_firstname'],
					$_hookValues['account_lastname'],$_hookValues['account_lid'],$this->defaultDomain);

			$ds = $GLOBALS['egw']->common->ldapConnect();
			
			$filter = "uid=".$_hookValues['account_lid'];

			$sri = @ldap_search($ds,$GLOBALS['egw_info']['server']['ldap_context'],$filter);
			if ($sri)
			{
				$allValues 	= ldap_get_entries($ds, $sri);
				$accountDN 	= $allValues[0]['dn'];
				$objectClasses	= $allValues[0]['objectclass'];
				
				unset($objectClasses['count']);
			}
			else
			{
				return false;
			}
			
			if(!in_array('dbmailUser',$objectClasses) &&
				!in_array('dbmailuser',$objectClasses))
			{
				$objectClasses[]	= 'dbmailuser'; 
			}
			
			// the new code for postfix+cyrus+ldap
			$newData = array 
			(
				'mail'			=> $mailLocalAddress,
				'accountStatus'		=> 'active',
				'objectclass'		=> $objectClasses
			);

			ldap_mod_replace ($ds, $accountDN, $newData);
			#print ldap_error($ds);
		}

		function getAccountEmailAddress($_accountName)
		{
			$emailAddresses	= array();
			$ds = $GLOBALS['egw']->common->ldapConnect();
			$filter 	= sprintf("(&(uid=%s)(objectclass=posixAccount))",$_accountName);
			$attributes	= array('dn','mail','mailAlternateAddress');
			$sri = @ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);
			
			if ($sri)
			{
				$realName = trim($GLOBALS['egw_info']['user']['firstname'] . (!empty($GLOBALS['egw_info']['user']['firstname']) ? ' ' : '') . $GLOBALS['egw_info']['user']['lastname']);
				$allValues = ldap_get_entries($ds, $sri);
				if(isset($allValues[0]['mail'][0]))
				{
					$emailAddresses[] = array
					(
						'name'		=> $realName,
						'address'	=> $allValues[0]['mail'][0],
						'type'		=> 'default'
					);
				}
				if($allValues[0]['mailalternateaddress']['count'] > 0)
				{
					$count = $allValues[0]['mailalternateaddress']['count'];
					for($i=0; $i < $count; $i++)
					{
						$emailAddresses[] = array
						(
							'name'		=> $realName,
							'address'	=> $allValues[0]['mailalternateaddress'][$i],
							'type'		=> 'alternate'
						);
					}
				}
			}
			
			return $emailAddresses;
		}

		function getUserData($_uidnumber) {
			$userData = array();

			$ldap = $GLOBALS['egw']->common->ldapConnect();
			
			if (($sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],"(uidnumber=$_uidnumber)")))
			{
				$allValues = ldap_get_entries($ldap, $sri);
				if ($allValues['count'] > 0)
				{
					#print "found something<br>";
					$userData["mailLocalAddress"]		= $allValues[0]["mail"][0];
					$userData["mailAlternateAddress"]	= $allValues[0]["mailalternateaddress"];
					$userData["accountStatus"]		= $allValues[0]["accountstatus"][0];
					$userData["mailForwardingAddress"]	= $allValues[0]["mailforwardingaddress"];
					$userData["deliveryProgramPath"]	= $allValues[0]["deliveryprogrampath"][0];
					$userData["deliveryMode"]		= $allValues[0]["deliverymode"][0];

					unset($userData["mailAlternateAddress"]["count"]);
					unset($userData["mailForwardingAddress"]["count"]);					

					return $userData;
				}
			}
			
			return $userData;
		}
		
		function setUserData($_uidnumber, $_mailAlternateAddress, $_mailForwardingAddress, $_deliveryMode, $_accountStatus, $_mailLocalAddress) {
			$filter = "uidnumber=$_uidnumber";

			$ldap = $GLOBALS['egw']->common->ldapConnect();

			$sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],$filter);
			if ($sri) {
				$allValues 	= ldap_get_entries($ldap, $sri);

				$accountDN 	= $allValues[0]['dn'];
				$uid	   	= $allValues[0]['uid'][0];
				$objectClasses	= $allValues[0]['objectclass'];
				
				unset($objectClasses['count']);

				if(!in_array('dbmailUser',$objectClasses) &&
					!in_array('dbmailuser',$objectClasses))
				{
					$objectClasses[]	= 'dbmailuser'; 
					sort($objectClasses);
					$newData['objectclass']	= $objectClasses;
				}

				sort($_mailAlternateAddress);
				sort($_mailForwardingAddress);
				
				$newData['mailalternateaddress'] = (array)$_mailAlternateAddress;
				$newData['mailforwardingaddress'] = (array)$_mailForwardingAddress;
				$newData['deliverymode']	= $_deliveryMode ? 'forwardOnly' : array();
				$newData['accountstatus']	= $_accountStatus ? 'active' : array();
				$newData['mail'] = $_mailLocalAddress;

				ldap_mod_replace($ldap, $accountDN, $newData);
			}
			else
			{
				return false;
			}

		}
		
		function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy)
		{
			$ds = $GLOBALS['egw']->common->ldapConnect();
			$filter 	= sprintf("(&(uidnumber=%s)(objectclass=posixAccount))",$_accountID);
			$attributes	= array('dn','mailforwardingaddress','deliverymode','objectclass');
			$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);
			
			if ($sri)
			{
				$newData = array();
				$allValues = ldap_get_entries($ds, $sri);

				$newData['objectclass']	= $allValues[0]['objectclass'];
				
				unset($newData['objectclass']['count']);

				if(!in_array('dbmailUser',$newData['objectclass']) &&
					!in_array('dbmailuser',$newData['objectclass']))
				{
					$newData['objectclass'][]	= 'dbmailuser'; 
				}

				if(!empty($_forwardingAddress))
				{
					if(is_array($allValues[0]['mailforwardingaddress']))
					{
						$newData['mailforwardingaddress'] = $allValues[0]['mailforwardingaddress'];
						unset($newData['mailforwardingaddress']['count']);
						$newData['mailforwardingaddress'][0] = $_forwardingAddress;
					}
					else
					{
						$newData['mailforwardingaddress'][0] = $_forwardingAddress;
					}
					$newData['deliverymode'] = ($_keepLocalCopy == 'yes'? array() : 'forwardOnly');
				}
				else
				{
					$newData['mailforwardingaddress'] = array();
					$newData['deliverymode'] = array();
				}

				ldap_modify ($ds, $allValues[0]['dn'], $newData);
				#print ldap_error($ds);
			}
		}
	}
?>
