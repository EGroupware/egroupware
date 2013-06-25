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

		$adldap = accounts_ads::get_adldap();
		// bind with username@ads_domain, only if a non-empty password given, in case anonymous search is enabled
		if(empty($passwd) || !$adldap->authenticate($username, $passwd))
		{
			//error_log(__METHOD__."('$username', ".(empty($passwd) ? "'') passwd empty" : '$passwd) adldap->authenticate() returned false')." --> returning false");
			return False;
		}

		$attributes	= array('samaccountname','givenName','sn','mail','homeDirectory');
		if (($allValues = $adldap->user()->info($username, $attributes)))
		{
			$allValues[0]['objectsid'][0] = $adldap->utilities()->getTextSID($allValues[0]['objectsid'][0]);
		}
		//error_log(__METHOD__."('$username', \$passwd) allValues=".array2string($allValues));

		if ($allValues && $allValues['count'] > 0)
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
				$GLOBALS['auto_create_acct']['homedirectory'] = $allValues[0]['homedirectory'][0];
			}
			if ($GLOBALS['egw_info']['server']['auto_create_acct'])
			{
				$GLOBALS['auto_create_acct']['account_id'] = accounts_ads::sid2account_id($allValues[0]['objectsid'][0]);

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
				//error_log(__METHOD__."() \$GLOBALS[auto_create_acct]=".array2string($GLOBALS['auto_create_acct']));
				return True;
			}
		}
		/* dn not found or password wrong */
		return False;
	}

	/**
	 * changes password
	 *
	 * @param string $old_passwd must be cleartext
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 * @throws egw_exception_wrong_userinput
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		if (!($adldap = accounts_ads::get_adldap()) || !($adldap->getUseSSL() || $adldap->getUseTLS()))
		{
			error_log(__METHOD__."('$old_passwd', '$new_passwd', $account_id) adldap=".array2string($adldap)." returning false");
			return false;		// Cant change passwd in ADS
		}

		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$admin = false;
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$admin = true;
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}
		// Check the old_passwd to make sure this is legal
		if(!$admin && !$adldap->authenticate($username, $old_passwd))
		{
			//error_log(__METHOD__."() old password '$old_passwd' for '$username' is wrong!");
			return false;
		}
		try {
			$ret = $adldap->user()->password($username, $new_passwd);
			//error_log(__METHOD__."('$old_passwd', '$new_passwd', $account_id) admin=$admin adldap->user()->password('$username', '$new_passwd') returned ".array2string($ret));
			return $ret;
		}
		catch (Exception $e) {
			// as we cant (todo) detect what the problem is, we do a password strength check and throw it's message, if it fails
			if (($error = auth::crackcheck($new_passwd, 4, 8)))	// 4 char classes and 8 chars min
			{
				throw new egw_exception_wrong_userinput($error);
			}
			else
			{
				throw new egw_exception(lang('Failed to change password.  Please contact your administrator.').' ('.$e->getMessage().')');
			}
		}
		return false;
	}
}
