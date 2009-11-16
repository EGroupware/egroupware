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

		function authenticate($username, $passwd)
		{
			if (preg_match('/[()|&=*,<>!~]/',$username))
			{
				return False;
			}

			if(!$ldap = @ldap_connect($GLOBALS['egw_info']['server']['ads_host']))
			{
				//echo "<p>Failed connecting to ADS server '".$GLOBALS['egw_info']['server']['ads_host']."' for authenication, execution stopped</p>\n";
				$GLOBALS['egw']->log->message('F-Abort, Failed connecting to ADS server for authenication, execution stopped');
				$GLOBALS['egw']->log->commit();
				return False;
			}
			//echo "<p>Connected to LDAP server '".$GLOBALS['egw_info']['server']['ads_host']."' for authenication</p>\n";

			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

			// bind with username@ads_domain, only if a non-empty password given, in case anonymous search is enabled
			if(empty($passwd) || !@ldap_bind($ldap,$username.'@'.$GLOBALS['egw_info']['server']['ads_domain'],$passwd))
			{
				//echo "<p>Cant bind with '$username@".$GLOBALS['egw_info']['server']['ads_domain']."' with PW '$passwd' !!!</p>\n";
				return False;
			}
			//echo "<p>Bind with '$username@".$GLOBALS['egw_info']['server']['ads_domain']."' with PW '$passwd'.</p>\n";

			$attributes	= array('samaccountname','givenName','sn','mail');
			$filter = "(samaccountname=$username)";
			// automatic create dn from domain: domain.com ==> DC=domain,DC=com
			$base_dn = array();
			foreach(explode('.',$GLOBALS['egw_info']['server']['ads_domain']) as $dc)
			{
				$base_dn[] = 'DC='.$dc;
			}
			$base_dn = implode(',',$base_dn);

			//echo "<p>Trying ldap_search(,$base_dn,$filter,".print_r($attributes,true)."</p>\n";
			$sri = ldap_search($ldap, $base_dn, $filter, $attributes);
			$allValues = ldap_get_entries($ldap, $sri);
			//_debug_array($allValues);

			if ($allValues['count'] > 0)
			{
				if($GLOBALS['egw_info']['server']['case_sensitive_username'] == true)
				{
					if($allValues[0]['samaccountname'][0] != $username)
					{
						return false;
					}
				}
				if (($id = $GLOBALS['egw']->accounts->name2id($username,'account_lid','u')))
				{
					return $GLOBALS['egw']->accounts->id2name($id,'account_status') == 'A';
				}
				if ($GLOBALS['egw_info']['server']['auto_create_acct'])
				{
					// create a global array with all availible info about that account
					$GLOBALS['auto_create_acct'] = array();
					foreach(array(
						'givenname' => 'firstname',
						'sn'        => 'lastname',
						'mail'      => 'email',
					) as $ldap_name => $acct_name)
					{
						$GLOBALS['auto_create_acct'][$acct_name] =
							$GLOBALS['egw']->translation->convert($allValues[0][$ldap_name][0],'utf-8');
					}
					return True;
				}
			}
			/* dn not found or password wrong */
			return False;
		}

		function change_password($old_passwd, $new_passwd, $_account_id='')
		{
			return false;		// Cant change passwd in ADS
		}
	}
?>
