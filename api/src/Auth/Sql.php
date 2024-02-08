<?php
/**
 * EGroupware API - Authentication from SQL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Joseph Engo <jengo@phpgroupware.org>
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;

/**
 * eGroupWare API - Authentication based on SQL table of accounts
 *
 * Encryption types other than md5() added by Miles Lott <milos@groupwhere.org>
 * based on code from http://www.thomas-alfeld.de/frank/
 *
 * Massive code cleanup and added password migration by Cornelius Weiss <egw@von-und-zu-weiss.de
 */
class Sql implements Backend
{
	/**
	 * Reference to the global db object
	 *
	 * @var Api\Db
	 */
	var $db;
	var $table = 'egw_accounts';
	var $previous_login = -1;

	function __construct()
	{
		$this->db = $GLOBALS['egw']->db;

		$this->type = @$GLOBALS['egw_info']['server']['sql_encryption_type'] ?
			strtolower($GLOBALS['egw_info']['server']['sql_encryption_type']) : 'md5';
	}

	/**
	 * password authentication against password stored in sql datababse
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		/* normal web form login */
		$where = array(
			'account_lid'    => $username,
			'account_type'   => 'u',
			'account_status' => 'A'
		);
		if (empty($GLOBALS['egw_info']['server']['case_sensitive_username']))	// = is case-sensitive e.g. on postgres, but not on mysql!
		{
			$where[] = 'account_lid '.$this->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($username);
			unset($where['account_lid']);
		}
		if($passwd_type == 'text')
		{
			if (!($row = $this->db->select($this->table,'account_lid,account_pwd,account_lastlogin,account_id',$where,__LINE__,__FILE__)->fetch()) ||
				empty($row['account_pwd']) ||
				!empty($GLOBALS['egw_info']['server']['case_sensitive_username']) && $row['account_lid'] != $username)
			{
				return false;
			}
			$type = null;
			if(!($match = Api\Auth::compare_password($passwd, $row['account_pwd'], $this->type, strtolower($username), $type)) ||
				$type != $this->type && in_array($type, explode(',',strtolower($GLOBALS['egw_info']['server']['pwd_migration_types']))))
			{
				// do we have to migrate an old password ?
				if (!empty($GLOBALS['egw_info']['server']['pwd_migration_allowed']) && !empty($GLOBALS['egw_info']['server']['pwd_migration_types']))
				{
					if (!$match)
					{
						foreach(explode(',', $GLOBALS['egw_info']['server']['pwd_migration_types']) as $type)
						{
							if(($match = Api\Auth::compare_password($passwd,$row['account_pwd'],$type,strtolower($username))))
							{
								break;
							}
						}
					}
					if ($match && ($encrypted_passwd = Api\Auth::encrypt_sql($passwd)))
					{
						$this->_update_passwd($encrypted_passwd, $row['account_id'], false, true);
					}
				}
				if (!$match) return false;
			}
		}
		/* Auth via crypted password. NOTE: mail needs cleartext password to authenticate against mailserver! */
		else
		{
			$where['account_pwd'] = $passwd;
			if (!($row = $this->db->select($this->table,'account_lid,account_lastlogin',$where,__LINE__,__FILE__)->fetch()) ||
				$GLOBALS['egw_info']['server']['case_sensitive_username'] && $row['account_lid'] != $username)
			{
				return false;
			}
		}
		// if this point is reached, auth was successfull
		$this->previous_login = $row['account_lastlogin'];

		return true;
	}

	/**
	 * fetch the last pwd change for the user
	 *
	 * @param string $username username of account to authenticate
	 * @return mixed false or account_lastpwd_change
	 */
	function getLastPwdChange($username)
	{
		/* normal web form login */
		$where = array(
			'account_lid'    => $username,
			'account_type'   => 'u',
			'account_status' => 'A'
		);
		if (!$GLOBALS['egw_info']['server']['case_sensitive_username'])	// = is case sensitiv eg. on postgres, but not on mysql!
		{
			$where[] = 'account_lid '.$this->db->capabilities[Api\Db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($username);
			unset($where['account_lid']);
		}
		if (!($row = $this->db->select($this->table,'account_lid,account_lastpwd_change',$where,__LINE__,__FILE__)->fetch()) ||
			$GLOBALS['egw_info']['server']['case_sensitive_username'] && $row['account_lid'] != $username)
		{
			return false;
		}
		// if this point is reached, we found a user with that name and return the account_lastpwd_change
		$rv = $row['account_lastpwd_change'];

		return $rv;
	}

	/**
	 * changes account_lastpwd_change in sql datababse
	 *
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param string $passwd must be cleartext, usually not used, but may be used to authenticate as user to do the change -> ldap
	 * @param int $_lastpwdchange =null must be a unixtimestamp
	 * @return boolean true if account_lastpwd_change successful changed, false otherwise
	 */
	function setLastPwdChange($account_id=0, $passwd=NULL, $_lastpwdchange=NULL)
	{
		$admin = True;
		// Don't allow password changes for other accounts when using XML-RPC
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

		if (($pw = $this->db->select($this->table,'account_pwd',array(
			'account_id'     => $account_id,
			'account_type'   => 'u',
			'account_status' => 'A',
		),__LINE__,__FILE__)->fetchColumn()) === false)
		{
			return false;	// account not found
		}
		// Check the passwd to make sure this is legal
		if(!$admin && !Api\Auth::compare_password($passwd,$pw,$this->type,strtolower($username)))
		{
			return false;
		}
		$lastpwdchange = (is_null($_lastpwdchange) || $_lastpwdchange < 0 ? time() : $_lastpwdchange);
		$this->db->update($this->table,array(
			'account_lastpwd_change' => $lastpwdchange,
		),array(
			'account_id' => $account_id,
		),__LINE__,__FILE__);

		if(!$this->db->affected_rows()) return false;
		if (!$admin) Api\Cache::setSession('phpgwapi', 'auth_alpwchange_val', $lastpwdchange);
		return true;
	}

	/**
	 * changes password in sql datababse
	 *
	 * @param string $old_passwd must be cleartext
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		$admin = True;
		// Don't allow password changes for other accounts when using XML-RPC
		if(!$account_id)
		{
			$admin = False;
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = $GLOBALS['egw']->accounts->id2name($account_id);
		}

		if (($pw = $this->db->select($this->table,'account_pwd',array(
			'account_id'     => $account_id,
			'account_type'   => 'u',
		),__LINE__,__FILE__)->fetchColumn()) === false)
		{
			return false;	// account not found
		}
		// Check the old_passwd to make sure this is legal
		if(!$admin && !Api\Auth::compare_password($old_passwd,$pw,$this->type,strtolower($username)))
		{
			return false;
		}

		if (!($encrypted_passwd = Api\Auth::encrypt_sql($new_passwd)))
		{
			return false;
		}

		// old password ok, or admin called the function from the admin application (no old passwd available).
		return $this->_update_passwd($encrypted_passwd, $account_id, $admin);
	}

	/**
	 * changes password in sql datababse
	 *
	 * @param string $encrypted_passwd
	 * @param string $new_passwd cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param boolean $admin =false called by admin, if not update password in the session
	 * @param boolean $update_lastpw_change =true
	 * @return boolean true if password successful changed, false otherwise
	 */
	private function _update_passwd($encrypted_passwd, $account_id, $admin=false, $update_lastpw_change=true)
	{
		$update = array('account_pwd' => $encrypted_passwd);
		if ($update_lastpw_change) $update['account_lastpwd_change'] = time();

		if (!$this->db->update($this->table,$update,array(
			'account_id' => $account_id,
		),__LINE__,__FILE__))
		{
			return false;
		}

		if(!$admin)
		{
			Api\Cache::setSession('phpgwapi','auth_alpwchange_val',$update['account_lastpwd_change']);
		}
		return true;
	}
}