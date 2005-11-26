<?php
	/***************************************************************************\
	* EGroupWare - LDAPManager                                                  *
	* http://www.egroupware.org                                                 *
	* Written by : Andreas Krause (ak703@users.sourceforge.net                  *
	* based on EmailAdmin by Lars Kneschke [lkneschke@egroupware.org]           *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/

	class soldap_mgr
	{
		function soldap_mgr()
		{
			$this->db = clone($GLOBALS['egw']->db);
			include(EGW_INCLUDE_ROOT.'/emailadmin/setup/tables_current.inc.php');
			$this->tables = &$phpgw_baseline;
			unset($phpgw_baseline);
			$this->table = &$this->tables['phpgw_emailadmin'];
		}

		function getUserData($_accountID)
		{
			$ldap = $GLOBALS['egw']->common->ldapConnect();
			$filter = "(&(uidnumber=$_accountID))";

			$sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],$filter);
			if ($sri)
			{
				$allValues = ldap_get_entries($ldap, $sri);
				if ($allValues['count'] > 0)
				{
					#print 'found something<br>';
					$userData['mail']					= $allValues[0]['mail'][0];
					$userData['mailAlternateAddress']	= $allValues[0]['mailalternateaddress'];
					$userData['accountStatus']			= $allValues[0]['accountstatus'][0];
					$userData['mailForwardingAddress']	= $allValues[0]['mailforwardingaddress'][0];
					$userData['deliveryMode']			= $allValues[0]['deliverymode'][0];

					unset($userData['mailAlternateAddress']['count']);
					unset($userData['mailForwardingAddress']['count']);

					return $userData;
				}
			}

			// if we did not return before, return false
			return false;
		}

		function saveUserData($_accountID, $_accountData)
		{
			$ldap = $GLOBALS['egw']->common->ldapConnect();
			// need to be fixed
			if(is_numeric($_accountID))
			{
				$filter = "uidnumber=$_accountID";
			}
			else
			{
				$filter = "uid=$_accountID";
			}

			$sri = @ldap_search($ldap,$GLOBALS['egw_info']['server']['ldap_context'],$filter);
			if ($sri)
			{
				$allValues 		= ldap_get_entries($ldap, $sri);
				$accountDN 		= $allValues[0]['dn'];
				$uid	   		= $allValues[0]['uid'][0];
				$homedirectory	= $allValues[0]['homedirectory'][0];
				$objectClasses	= $allValues[0]['objectclass'];

				unset($objectClasses['count']);
			}
			else
			{
				return false;
			}

			if(empty($homedirectory))
			{
				$homedirectory = "/home/".$uid;
			}

			// the old code for qmail ldap
			$newData = array
			(
				'mail'					=> $_accountData['mail'],
				'mailAlternateAddress'	=> $_accountData['mailAlternateAddress'],
				'mailForwardingAddress'	=> $_accountData['mailForwardingAddress'],
//				'homedirectory'			=> $homedirectory,
//				'mailMessageStore'		=> $homedirectory.'/Maildir/',
//				'gidnumber'				=> '1000',
//				'qmailDotMode'			=> $_accountData['qmailDotMode'],
//				'deliveryProgramPath'	=> $_accountData['deliveryProgramPath']
			);

			if(!in_array('qmailUser',$objectClasses) &&
				!in_array('qmailuser',$objectClasses))
			{
				$objectClasses[]	= 'qmailuser';
			}

			// the new code for postfix+cyrus+ldap
			$newData = array
			(
				'mail'				=> $_accountData['mail'],
				'accountStatus'		=> $_accountData['accountStatus'],
				'objectclass'		=> $objectClasses
			);

			if(is_array($_accountData['mailAlternateAddress']))
			{
				$newData['mailAlternateAddress'] = $_accountData['mailAlternateAddress'];
			}
			else
			{
				$newData['mailAlternateAddress'] = array();
			}

			if($_accountData['accountStatus'] == 'active')
			{
				$newData['accountStatus'] = 'active';
			}
			else
			{
				$newData['accountStatus'] = 'disabled';
			}
/*
			if(!empty($_accountData['deliveryMode']))
			{
				$newData['deliveryMode'] = $_accountData['deliveryMode'];
			}
			else
			{
				$newData['deliveryMode'] = array();
			}
*/

//			if(is_array($_accountData['mailForwardingAddress']))
//			{
				$newData['mailForwardingAddress'] = $_accountData['mailForwardingAddress'];
//			}
//			else
//			{
//				$newData['mailForwardingAddress'] = array();
//			}

			#print "<br>DN: $accountDN<br>";
			ldap_mod_replace ($ldap, $accountDN, $newData);

			// also update the account_email field in egw_accounts
			// when using sql account storage
			if($GLOBALS['egw_info']['server']['account_repository'] == 'sql')
			{
				$this->db->update('egw_accounts',array(
						'account_email'	=> $_accountData['mail']
					),
					array(
						'account_id'	=> $_accountID
					),__LINE__,__FILE__
				);
			}
		}
	}
?>
