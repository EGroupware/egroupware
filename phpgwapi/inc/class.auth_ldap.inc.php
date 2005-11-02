<?php
	/**************************************************************************\
	* eGroupWare API - Auth from LDAP                                          *
	* This file written by Lars Kneschke <lkneschke@linux-at-work.de>          *
	* and Joseph Engo <jengo@phpgroupware.org>                                 *
	* Authentication based on LDAP Server                                      *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
	* Copyright (C) 2002, 2003 Lars Kneschke                                   *
	* ------------------------------------------------------------------------ *
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            *
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/

	/* $Id$ */

	class auth_
	{
		var $previous_login = -1;

		/**
		 * authentication against LDAP
		 *
		 * @param string $username username of account to authenticate
		 * @param string $passwd corresponding password
		 * @return boolean true if successful authenticated, false otherwise
		 */
		function authenticate($username, $passwd)
		{
			if (ereg('[()|&=*,<>!~]',$username))
			{
				return False;
			}

			if(!$ldap = @ldap_connect($GLOBALS['egw_info']['server']['ldap_host']))
			{
				$GLOBALS['egw']->log->message('F-Abort, Failed connecting to LDAP server for authenication, execution stopped');
				$GLOBALS['egw']->log->commit();
				return False;
			}

			if($GLOBALS['egw_info']['server']['ldap_version3'])
			{
				ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			}

			/* Login with the LDAP Admin. User to find the User DN.  */
			if(!@ldap_bind($ldap, $GLOBALS['egw_info']['server']['ldap_root_dn'], $GLOBALS['egw_info']['server']['ldap_root_pw']))
			{
				return False;
			}
			/* find the dn for this uid, the uid is not always in the dn */
			$attributes	= array('uid','dn','givenName','sn','mail','uidNumber','gidNumber');

			$filter = $GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)';
			$filter = str_replace(array('%user','%domain'),array($username,$GLOBALS['egw_info']['user']['domain']),$filter);

			if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap')
			{
				$filter = "(&$filter(phpgwaccountstatus=A))";
			}

			$sri = ldap_search($ldap, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);
			$allValues = ldap_get_entries($ldap, $sri);

			if ($allValues['count'] > 0)
			{
				if($GLOBALS['egw_info']['server']['case_sensitive_username'] == true)
				{
					if($allValues[0]['uid'][0] != $username)
					{
						return false;
					}
				}
				/* we only care about the first dn */
				$userDN = $allValues[0]['dn'];
				/*
				generate a bogus password to pass if the user doesn't give us one
				this gets around systems that are anonymous search enabled
				*/
				if (empty($passwd))
				{
					$passwd = crypt(microtime());
				}
				/* try to bind as the user with user suplied password */
				if (@ldap_bind($ldap, $userDN, $passwd))
				{
					if ($GLOBALS['egw_info']['server']['account_repository'] != 'ldap')
					{
						$account =& CreateObject('phpgwapi.accounts',$username,'u');
						if (!$account->account_id && $GLOBALS['egw_info']['server']['auto_create_acct'])
						{
							// create a global array with all availible info about that account
							$GLOBALS['auto_create_acct'] = array();
							foreach(array(
								'givenname' => 'firstname',
								'sn'        => 'lastname',
								'uidnumber' => 'id',
								'mail'      => 'email',
								'gidnumber' => 'primary_group',
							) as $ldap_name => $acct_name)
							{
								$GLOBALS['auto_create_acct'][$acct_name] =
									$GLOBALS['egw']->translation->convert($allValues[0][$ldap_name][0],'utf-8');
							}
							return True;
						}
						$data = $account->read_repository();
						return $data['status'] == 'A';
					}
					return True;
				}
			}
			/* dn not found or password wrong */
			return False;
		}

		/**
		 * changes password in LDAP
		 *
		 * @param string $old_passwd must be cleartext or empty to not to be checked
		 * @param string $new_passwd must be cleartext
		 * @param int $account_id account id of user whose passwd should be changed
		 * @return boolean true if password successful changed, false otherwise
		 */
		function change_password($old_passwd, $new_passwd, $account_id=0)
		{
			if (!$_account_id)
			{
				$username = $GLOBALS['egw_info']['user']['account_lid'];
			}
			else
			{
				$username = $GLOBALS['egw']->accounts->id2name($account_id);
			}
			$filter = $GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)';
			$filter = str_replace(array('%user','%domain'),array($username,$GLOBALS['egw_info']['user']['domain']),$filter);

			$ds = $GLOBALS['egw']->common->ldapConnect();
			$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter);
			$allValues = ldap_get_entries($ds, $sri);

			$entry['userpassword'] = $this->encrypt_password($new_passwd);
			$dn = $allValues[0]['dn'];

			if ($old_passwd && $GLOBALS['egw']->auth->encrypt_password($old_passwd) != $allValues[0]['userpassword'] || !@ldap_modify($ds, $dn, $entry))
			{
				return false;
			}
			if($old_passwd)	// if old password given (not called by admin) update the password in the session
			{
				$GLOBALS['egw']->session->appsession('password','phpgwapi',$new_passwd);
			}
			return $entry['userpassword'];
		}
	}
