<?php
/**
 * EGroupware API - ADS Authentication
 *
 * To be able to use SSL or TLS you either need:
 * a) ldap to have certificate store INCL. used certificate!
 * b) add to /etc/openldap/ldap.conf: TLS_REQCERT     never
 *    to tell ldap not to validate certificates (insecure)
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
	 * @param string $_passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $_passwd, $passwd_type='text')
	{
		unset($passwd_type);	// not used by required in function signature
		if (preg_match('/[()|&=*,<>!~]/',$username))
		{
			return False;
		}
		// harden ldap auth, by removing \000 bytes, causing passwords to be not empty by php, but empty to c libaries
		$passwd = str_replace("\000", '', $_passwd);

		$adldap = accounts_ads::get_adldap();
		// bind with username@ads_domain, only if a non-empty password given, in case anonymous search is enabled
		if(empty($passwd) || !$adldap->authenticate($username, $passwd))
		{
			$authenticated = false;
			// check if password need to be set on next login (AD will not authenticate user!)
			if (!empty($passwd) && ($data = $adldap->user()->info($username, array('pwdlastset'))) &&
				((string)$data[0]['pwdlastset'][0] === '0') &&
				// reset pwdlastset, to we can check authentication
				ldap_modify($adldap->getLdapConnection(), $data[0]['dn'], array('pwdlastset' => -1)))
			{
				$authenticated = $adldap->authenticate($username, $passwd);
				// set pwdlastset=0 again
				ldap_modify($adldap->getLdapConnection(), $data[0]['dn'], array('pwdlastset' => 0));
			}
			if (!$authenticated)
			{
				error_log(__METHOD__."('$username', ".(empty($passwd) ? "'') passwd empty" : '$passwd) adldap->authenticate() returned false')." --> returning false");
				return False;
			}
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
					error_log(__METHOD__."('$username') username has wrong case!");
					return false;
				}
			}
			if (($id = $GLOBALS['egw']->accounts->name2id($username,'account_lid','u')))
			{
				$ret = $GLOBALS['egw']->accounts->id2name($id,'account_status') == 'A';
				if (!$ret) error_log(__METHOD__."('$username') account_status check returning ".array2string($ret));
				return $ret;
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
		error_log(__METHOD__."('$username') authenticated, but user NOT found!");
		/* dn not found or password wrong */
		return False;
	}

	/**
	 * Fetch the last pwd change for the user
	 *
	 * Required by EGroupware to force user to change password.
	 *
	 * @param string $username username of account to authenticate
	 * @return mixed false on error, 0 if user must change on next login,
	 *	or NULL if user never changed his password or timestamp of last change
	 */
	static function getLastPwdChange($username)
	{
		$ret = false;
		if (($adldap = accounts_ads::get_adldap()) &&
			($data = $adldap->user()->info($username, array('pwdlastset'))))
		{
			$ret = !$data[0]['pwdlastset'][0] ? $data[0]['pwdlastset'][0] :
				$adldap->utilities()->convertWindowsTimeToUnixTime($data[0]['pwdlastset'][0]);
		}
		//error_log(__METHOD__."('$username') pwdlastset=".array2string($data[0]['pwdlastset'][0])." returned ".array2string($ret));
		return $ret;
	}

	/**
	 * changes account_lastpwd_change in ldap datababse
	 *
	 * Samba4 does not understand -1 for current time, but Win2008r2 only allows to set -1 (beside 0).
	 *
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param string $passwd must be cleartext, usually not used, but may be used to authenticate as user to do the change -> ldap
	 * @param int $lastpwdchange must be a unixtimestamp or 0 (force user to change pw) or -1 for current time
	 * @param boolean $return_mod =false true return ldap modification instead of executing it
	 * @return boolean|array true if account_lastpwd_change successful changed, false otherwise or array if $return_mod
	 */
	static function setLastPwdChange($account_id=0, $passwd=NULL, $lastpwdchange=NULL, $return_mod=false)
	{
		unset($passwd);	// not used but required by function signature
		if (!($adldap = accounts_ads::get_adldap())) return false;

		if ($lastpwdchange)
		{
			// Samba4 can NOT set -1 for current time
			$ldapServerInfo = ldapserverinfo::get($adldap->getLdapConnection(), $GLOBALS['egw_info']['server']['ads_host']);
			if ($ldapServerInfo->serverType == SAMBA4_LDAPSERVER)
			{
				if ($lastpwdchange == -1) $lastpwdchange = time();
			}
			// while Windows only allows to set -1 for current time (or 0 to force user to change password)
			else
			{
				$lastpwdchange = -1;
			}
		}
		if ($lastpwdchange && $lastpwdchange != -1)
		{
			$lastpwdchange = accounts_ads::convertUnixTimeToWindowsTime($lastpwdchange);
		}
		$mod = array('pwdlastset' => $lastpwdchange);
		if ($return_mod) return $mod;

		$ret = false;
		if ($account_id && ($username = accounts::id2name($account_id, 'account_lid')) &&
			($data = $adldap->user()->info($username, array('pwdlastset'))))
		{
			$ret = ldap_modify($adldap->getLdapConnection(), $data[0]['dn'], $mod);
			//error_log(__METHOD__."($account_id, $passwd, $lastpwdchange, $return_mod) ldap_modify(, '{$data[0]['dn']}', array('pwdlastset' => $lastpwdchange)) returned ".array2string($ret));
		}
		return $ret;
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
		if (!($adldap = accounts_ads::get_adldap()))
		{
			error_log(__METHOD__."(\$old_passwd, \$new_passwd, $account_id) accounts_ads::get_adldap() returned false");
			return false;
		}

		if (!($adldap->getUseSSL() || $adldap->getUseTLS()))
		{
			throw new egw_exception(lang('Failed to change password.').' '.lang('Active directory requires SSL or TLS to change passwords!'));
		}

		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$admin = false;
			$username = $GLOBALS['egw_info']['user']['account_lid'];
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}
		else
		{
			$admin = true;
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}
		// Check the old_passwd to make sure this is legal, if we dont support password change
		if (!$admin && (!method_exists($adldap->user(), 'passwordChangeSupported') ||
			!$adldap->user()->passwordChangeSupported()) && !$this->authenticate($username, $old_passwd))
		{
			//error_log(__METHOD__."() old password '$old_passwd' for '$username' is wrong!");
			return false;
		}
		try {
			$ret = $adldap->user()->password($username, $new_passwd, false, $old_passwd);
			//error_log(__METHOD__."('$old_passwd', '$new_passwd', $account_id) admin=$admin adldap->user()->password('$username', '$new_passwd') returned ".array2string($ret));
			return $ret;
		}
		catch (Exception $e) {
			// as we cant detect what the problem is, we do a password strength check and throw it's message, if it fails
			$error = auth::crackcheck($new_passwd,
				// if admin has nothing configured use windows default of 3 char classes, 7 chars min and name-part-check
				$GLOBALS['egw_info']['server']['force_pwd_strength'] ? $GLOBALS['egw_info']['server']['force_pwd_strength'] : 3,
				$GLOBALS['egw_info']['server']['force_pwd_length'] ? $GLOBALS['egw_info']['server']['force_pwd_length'] : 7,
				'yes',	// always check with "passwd_forbid_name" enabled
				$account_id);
			$msg = strtr($e->getMessage(), array(		// translate possible adLDAP and LDAP error
				'Error' => lang('Error'),
				'Server is unwilling to perform.' => lang('Server is unwilling to perform.'),
				'Your password might not match the password policy.' => lang('Your password might not match the password policy.'),
			));
			throw new egw_exception('<p>'.lang('Failed to change password.')."</p>\n".$msg.($error ? "\n<p>".$error."</p>\n" : ''));
		}
		return false;
	}
}
