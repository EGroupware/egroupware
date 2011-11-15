<?php
/**
 * eGroupWare API - ADS Authentication
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de> based on auth_ldap from:
 * @author Lars Kneschke <lkneschke@linux-at-work.de>
 * @author Joseph Engo <jengo@phpgroupware.org>
 * Copyright (C) 2000, 2001 Joseph Engo
 * Copyright (C) 2002, 2003 Lars Kneschke
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Authentication agains a ADS Server
 */
class auth_ads implements auth_backend
{
	var $previous_login = -1;

	/**
	 * password authentication
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
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

		$attributes	= array('samaccountname','givenName','sn','mail','homeDirectory');
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
			// store homedirectory for egw_session->read_repositories
			$GLOBALS['auto_create_acct'] = array();
			if (isset($allValues[0]['homedirectory']))
			{
				$GLOBALS['auto_create_acct']['homedirectory'] = $allValues[0]['homedirectory'];
			}
			if ($GLOBALS['egw_info']['server']['auto_create_acct'])
			{
				// create a global array with all availible info about that account
				foreach(array(
					'givenname' => 'firstname',
					'sn'        => 'lastname',
					'mail'      => 'email',
				) as $ldap_name => $acct_name)
				{
					$GLOBALS['auto_create_acct'][$acct_name] =
						translation::convert($allValues[0][$ldap_name][0],'utf-8');
				}
				return True;
			}
		}
		/* dn not found or password wrong */
		return False;
	}

	function change_password($old_passwd, $new_passwd, $_account_id=0)
	{
		return false;		// Cant change passwd in ADS
	}
}
