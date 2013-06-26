<?php
/**
 * eGroupWare API - LDAP Authentication with fallback to SQL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

/**
 * Authentication agains a LDAP Server with fallback to SQL
 *
 * For other fallback types, simply change auth backends in constructor call
 */
class auth_fallback implements auth_backend
{
	/**
	 * Primary auth backend
	 *
	 * @var auth_backend
	 */
	private $primary_backend;

	/**
	 * Fallback auth backend
	 *
	 * @var auth_backend
	 */
	private $fallback_backend;

	/**
	 * Constructor
	 */
	function __construct($primary='auth_ldap',$fallback='auth_sql')
	{
		$this->primary_backend = new $primary;

		$this->fallback_backend = new $fallback;
	}

	/**
	 * authentication against LDAP with fallback to SQL
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		if ($this->primary_backend->authenticate($username, $passwd, $passwd_type))
		{
			egw_cache::setInstance(__CLASS__,'backend_used-'.$username,'primary');
			// check if fallback has correct password, if not update it
			if (($account_id = $GLOBALS['egw']->accounts->name2id($username)) &&
				!$this->fallback_backend->authenticate($username,$passwd, $passwd_type))
			{
				$backup_currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
				$GLOBALS['egw_info']['flags']['currentapp'] = 'admin';	// otherwise
				$ret = $this->fallback_backend->change_password('', $passwd, $account_id);
				$GLOBALS['egw_info']['flags']['currentapp'] = $backup_currentapp;
				//error_log(__METHOD__."('$username', \$passwd) updated password for #$account_id on fallback ".($ret ? 'successfull' : 'failed!'));
			}
			return true;
		}
		if ($this->fallback_backend->authenticate($username,$passwd, $passwd_type))
		{
			egw_cache::setInstance(__CLASS__,'backend_used-'.$username,'fallback');
			return true;
		}
		return false;
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
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$admin = False;
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}
		if (egw_cache::getInstance(__CLASS__,'backend_used-'.$username) == 'primary')
		{
			if ($ret = $this->primary_backend->change_password($old_passwd, $new_passwd, $account_id))
			{
				// if password successfully changed on primary, also update fallback
				$this->fallback_backend->change_password($old_passwd, $new_passwd, $account_id);
			}
			return $ret;
		}
		return $this->fallback_backend->change_password($old_passwd, $new_passwd, $account_id);
	}

	/**
	 * fetch the last pwd change for the user
	 *
	 * @param string $username username of account to authenticate
	 * @return mixed false or account_lastpwd_change
	 */
	function getLastPwdChange($username)
	{
		if (egw_cache::getInstance(__CLASS__,'backend_used-'.$username) == 'primary')
		{
			if (method_exists($this->primary_backend,'getLastPwdChange')) return $this->primary_backend->getLastPwdChange($username);
		}
		if (method_exists($this->fallback_backend,'getLastPwdChange')) return $this->fallback_backend->getLastPwdChange($username);
		return false;
	}

	/**
	 * changes account_lastpwd_change in sql datababse
	 *
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param string $passwd must be cleartext, usually not used, but may be used to authenticate as user to do the change -> ldap
	 * @param int $lastpwdchange must be a unixtimestamp
	 * @return boolean true if account_lastpwd_change successful changed, false otherwise
	 */
	function setLastPwdChange($account_id=0, $passwd=NULL, $lastpwdchange=NULL)
	{
		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$admin = False;
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}
		if (egw_cache::getInstance(__CLASS__,'backend_used-'.$username) == 'primary')
		{
			if (method_exists($this->primary_backend,'setLastPwdChange')) return $this->primary_backend->setLastPwdChange($username);
		}
		if (method_exists($this->fallback_backend,'setLastPwdChange')) return $this->fallback_backend->setLastPwdChange($account_id, $passwd, $lastpwdchange);
		return false;
	}
}
