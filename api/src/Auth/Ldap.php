<?php
/**
 * EGroupware API - LDAP Authentication
 *
 * To be able to use SSL or TLS you either need:
 * a) ldap to have certificate store INCL. used certificate!
 * b) add to /etc/openldap/ldap.conf: TLS_REQCERT     never
 *    to tell ldap not to validate certificates (insecure)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @author Lars Kneschke <lkneschke@linux-at-work.de>
 * @author Joseph Engo <jengo@phpgroupware.org>
 * Copyright (C) 2000, 2001 Joseph Engo
 * Copyright (C) 2002, 2003 Lars Kneschke
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;

/**
 * Authentication agains a LDAP Server
 */
class Ldap implements Backend
{
	var $previous_login = -1;
	/**
	 * Switch this on to get messages in Apache error_log, why authtication fails
	 *
	 * @var boolean
	 */
	var $debug = false;

	/**
	 * authentication against LDAP
	 *
	 * @param string $_username username of account to authenticate
	 * @param string $_passwd corresponding password
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($_username, $_passwd, $passwd_type='text')
	{
		unset($passwd_type);	// not used by required by function signature

		// allow non-ascii in username & password
		$username = Api\Translation::convert($_username,Api\Translation::charset(),'utf-8');
		// harden ldap auth, by removing \000 bytes, causing passwords to be not empty by php, but empty to c libaries
		$passwd = str_replace("\000", '', Api\Translation::convert($_passwd,Api\Translation::charset(),'utf-8'));

		// Login with the LDAP Admin. User to find the User DN.
		try {
			$ldap = Api\Ldap::factory();
		}
		catch(Api\Exception\NoPermission $e)
		{
			unset($e);
			if ($this->debug) error_log(__METHOD__."('$username',\$password) can NOT bind with ldap_root_dn to search!");
			return False;
		}
		/* find the dn for this uid, the uid is not always in the dn */
		$attributes	= array('uid','dn','givenName','sn','mail','uidNumber','shadowExpire','homeDirectory');

		$filter = str_replace(array('%user','%domain'),array(Api\Ldap::quote($username),$GLOBALS['egw_info']['user']['domain']),
			$GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)');

		if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap')
		{
			$filter = "(&$filter(objectclass=posixaccount))";
		}
		$sri = ldap_search($ldap, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);
		$allValues = ldap_get_entries($ldap, $sri);

		if ($allValues['count'] > 0)
		{
			if ($GLOBALS['egw_info']['server']['case_sensitive_username'] == true &&
				$allValues[0]['uid'][0] != $username)
			{
				if ($this->debug) error_log(__METHOD__."('$username',\$password) wrong case in username!");
				return false;
			}
			if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
				isset($allValues[0]['shadowexpire']) && $allValues[0]['shadowexpire'][0]*24*3600 < time())
			{
				if ($this->debug) error_log(__METHOD__."('$username',\$password) account is expired!");
				return false;	// account is expired
			}
			$userDN = $allValues[0]['dn'];

			// try to bind as the user with user suplied password
			// only if a non-empty password given, in case anonymous search is enabled
			if (!empty($passwd) && ($ret = @ldap_bind($ldap, $userDN, $passwd)))
			{
				if ($GLOBALS['egw_info']['server']['account_repository'] != 'ldap')
				{
					// store homedirectory for egw_session->read_repositories
					$GLOBALS['auto_create_acct'] = array();
					if (isset($allValues[0]['homedirectory']))
					{
						$GLOBALS['auto_create_acct']['homedirectory'] = $allValues[0]['homedirectory'][0];
					}
					if (!($id = $GLOBALS['egw']->accounts->name2id($username,'account_lid','u')))
					{
						// account does NOT exist, check if we should create it
						if ($GLOBALS['egw_info']['server']['auto_create_acct'])
						{
							// create a global array with all availible info about that account
							foreach(array(
								'givenname' => 'firstname',
								'sn'        => 'lastname',
								'uidnumber' => 'account_id',
								'mail'      => 'email',
							) as $ldap_name => $acct_name)
							{
								$GLOBALS['auto_create_acct'][$acct_name] =
									Api\Translation::convert($allValues[0][$ldap_name][0],'utf-8');
							}
							$ret = true;
						}
						else
						{
							$ret = false;
							if ($this->debug) error_log(__METHOD__."('$username',\$password) bind as user failed!");
						}
					}
					// account exists, check if it is acctive
					else
					{
						$ret = $GLOBALS['egw']->accounts->id2name($id,'account_status') == 'A';

						if ($this->debug && !$ret) error_log(__METHOD__."('$username',\$password) account NOT active!");
					}
				}
				// account-repository is ldap --> check if passwd hash migration is enabled
				elseif ($GLOBALS['egw_info']['server']['pwd_migration_allowed'] &&
					!empty($GLOBALS['egw_info']['server']['pwd_migration_types']))
				{
					$matches = null;
					// try to query password from ldap server (might fail because of ACL) and check if we need to migrate the hash
					if (($sri = ldap_search($ldap, $userDN,"(objectclass=*)", array('userPassword'))) &&
						($values = ldap_get_entries($ldap, $sri)) && isset($values[0]['userpassword'][0]) &&
						($type = preg_match('/^{(.+)}/',$values[0]['userpassword'][0],$matches) ? strtolower($matches[1]) : 'plain') &&
						// for crypt use Api\Auth::crypt_compare to detect correct sub-type, strlen("{crypt}")=7
						($type != 'crypt' || Api\Auth::crypt_compare($passwd, substr($values[0]['userpassword'][0], 7), $type)) &&
						in_array($type, explode(',',strtolower($GLOBALS['egw_info']['server']['pwd_migration_types']))))
					{
						$this->change_password($passwd, $passwd, $allValues[0]['uidnumber'][0], false);
					}
				}
				return $ret;
			}
		}
		if ($this->debug) error_log(__METHOD__."('$_username', '$_passwd') dn not found or password wrong!");
		// dn not found or password wrong
		return False;
	}

	/**
	 * fetch the last pwd change for the user
	 *
	 * @param string $_username username of account to authenticate
	 * @return mixed false or shadowlastchange*24*3600
	 */
	function getLastPwdChange($_username)
	{
		// allow non-ascii in username & password
		$username = Api\Translation::convert($_username,Api\Translation::charset(),'utf-8');

		// Login with the LDAP Admin. User to find the User DN.
		try {
			$ldap = Api\Ldap::factory();
		}
		catch (Api\Exception\NoPermission $ex) {
			unset($ex);
			if ($this->debug) error_log(__METHOD__."('$username') can NOT bind with ldap_root_dn to search!");
			return false;
		}
		/* find the dn for this uid, the uid is not always in the dn */
		$attributes	= array('uid','dn','shadowexpire','shadowlastchange');

		$filter = str_replace(array('%user','%domain'),array(Api\Ldap::quote($username),$GLOBALS['egw_info']['user']['domain']),
			$GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)');

		if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap')
		{
			$filter = "(&$filter(objectclass=posixaccount))";
		}
		$sri = ldap_search($ldap, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);
		$allValues = ldap_get_entries($ldap, $sri);

		if ($allValues['count'] > 0)
		{
			if (!isset($allValues[0]['shadowlastchange']))
			{
				if ($this->debug) error_log(__METHOD__."('$username') no shadowlastchange attribute!");
				return false;
			}
			if ($GLOBALS['egw_info']['server']['case_sensitive_username'] == true &&
				$allValues[0]['uid'][0] != $username)
			{
				if ($this->debug) error_log(__METHOD__."('$username') wrong case in username!");
				return false;
			}
			if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
				isset($allValues[0]['shadowexpire']) && $allValues[0]['shadowexpire'][0]*24*3600 < time())
			{
				if ($this->debug) error_log(__METHOD__."('$username',\$password) account is expired!");
				return false;	// account is expired
			}
			return $allValues[0]['shadowlastchange'][0]*24*3600;
		}
		if ($this->debug) error_log(__METHOD__."('$username') dn not found or password wrong!");
		// dn not found or password wrong
		return false;
	}

	/**
	 * changes account_lastpwd_change in ldap database
	 *
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param string $passwd must be cleartext, usually not used, but may be used to authenticate as user to do the change -> ldap
	 * @param int $lastpwdchange must be a unixtimestamp
	 * @return boolean true if account_lastpwd_change successful changed, false otherwise
	 */
	function setLastPwdChange($account_id=0, $passwd=NULL, $lastpwdchange=NULL)
	{
		$admin = True;
		// Don't allow password changes for other accounts when using XML-RPC
		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$admin = False;
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = Api\Translation::convert($GLOBALS['egw']->accounts->id2name($account_id),
				Api\Translation::charset(),'utf-8');
		}
		//echo "<p>auth_Api\Ldap::change_password('$old_passwd','$new_passwd',$account_id) username='$username'</p>\n";

		$filter = str_replace(array('%user','%domain'),array($username,$GLOBALS['egw_info']['user']['domain']),
			$GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)');

		$ds = Api\Ldap::factory();
		$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter);
		$allValues = ldap_get_entries($ds, $sri);

		$entry['shadowlastchange'] = (is_null($lastpwdchange) || $lastpwdchange<0 ? round((time()-date('Z')) / (24*3600)):$lastpwdchange);

		$dn = $allValues[0]['dn'];

		if(!$admin && $passwd)	// if old password given (not called by admin) --> bind as that user to change the pw
		{
			$ds = Api\Ldap::factory(true, '', $dn, $passwd);
		}
		if (!@ldap_modify($ds, $dn, $entry))
		{
			return false;
		}
		// using time() is sufficient to represent the current time, we do not need the timestamp written to the storage
		if (!$admin) Api\Cache::setSession('phpgwapi','auth_alpwchange_val',(is_null($lastpwdchange) || $lastpwdchange<0 ? time():$lastpwdchange));
		return true;
	}

	/**
	 * changes password in LDAP
	 *
	 * If $old_passwd is given, the password change is done binded as user and NOT with the
	 * "root" dn given in the configurations.
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param boolean $update_lastchange =true
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0, $update_lastchange=true)
	{
		if (!$account_id)
		{
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = Api\Translation::convert($GLOBALS['egw']->accounts->id2name($account_id),
				Api\Translation::charset(),'utf-8');
		}
		if ($this->debug) error_log(__METHOD__."('$old_passwd','$new_passwd',$account_id, $update_lastchange) username='$username'");

		$filter = str_replace(array('%user','%domain'),array($username,$GLOBALS['egw_info']['user']['domain']),
			$GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)');

		$ds = $ds_admin = Api\Ldap::factory();
		$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter);
		$allValues = ldap_get_entries($ds, $sri);

		$entry['userpassword'] = Api\Auth::encrypt_password($new_passwd);
		if ($update_lastchange)
		{
			$entry['shadowlastchange'] = round((time()-date('Z')) / (24*3600));
		}

		$dn = $allValues[0]['dn'];

		if($old_passwd)	// if old password given (not called by admin) --> bind as that user to change the pw
		{
			try {
				$ds = Api\Ldap::factory(true, '', $dn, $old_passwd);
			}
			catch (Api\Exception\NoPermission $e) {
				unset($e);
				return false;	// wrong old user password
			}
		}
		// try changing password bind as user or as admin, to cater for all sorts of ldap configuration
		// where either only user is allowed to change his password, or only admin user is allowed to
		if (!@ldap_modify($ds, $dn, $entry) && (!$old_passwd || !@ldap_modify($ds_admin, $dn, $entry)))
		{
			return false;
		}
		if($old_passwd)	// if old password given (not called by admin) update the password in the session
		{
			// using time() is sufficient to represent the current time, we do not need the timestamp written to the storage
			Api\Cache::setSession('phpgwapi','auth_alpwchange_val',time());
		}
		return $entry['userpassword'];
	}
}
