<?php
/**
 * eGroupWare API - Authentication from SQL
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

/**
 * eGroupWare API - Authentication based on SQL table of accounts
 *
 * Encryption types other than md5() added by Miles Lott <milos@groupwhere.org>
 * based on code from http://www.thomas-alfeld.de/frank/
 *
 * Massive code cleanup and added password migration by Cornelius Weiss <egw@von-und-zu-weiss.de
 */
class auth_sql implements auth_backend
{
	/**
	 * Reference to the global db object
	 *
	 * @var egw_db
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
	 * @param string $passwd_type='text' 'text' for cleartext passwords (default)
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
		if (!$GLOBALS['egw_info']['server']['case_sensitive_username'])	// = is case sensitiv eg. on postgres, but not on mysql!
		{
			$where[] = 'account_lid '.$this->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote($username);
			unset($where['account_lid']);
		}
		if($passwd_type == 'text')
		{
			if (!($row = $this->db->select($this->table,'account_lid,account_pwd,account_lastlogin',$where,__LINE__,__FILE__)->fetch()) ||
				empty($row['account_pwd']) ||
				$GLOBALS['egw_info']['server']['case_sensitive_username'] && $row['account_lid'] != $username)
			{
				return false;
			}
			if(!auth::compare_password($passwd,$row['account_pwd'],$this->type,strtolower($username)))
			{
				$match = false;
				// do we have to migrate an old password ?
				if($GLOBALS['egw_info']['server']['pwd_migration_allowed'] && !empty($GLOBALS['egw_info']['server']['pwd_migration_types']))
				{
					foreach(explode(',', $GLOBALS['egw_info']['server']['pwd_migration_types']) as $type)
					{
						if(auth::compare_password($passwd,$row['account_pwd'],$type,strtolower($username)))
						{
							$account_id = $GLOBALS['egw_info']['user']['account_id'];
							$encrypted_passwd = $this->encrypt_sql($passwd);
							$this->_update_passwd($encrypted_passwd,$passwd,$account_id);
							$match = true;
							break;
						}
					}
				}
				if (!$match)
				{
					return false;
				}
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
		if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
		{
			$admin = False;
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}

		if (($pw = $this->db->select($this->table,'account_pwd',array(
			'account_id'     => $account_id,
			'account_type'   => 'u',
			'account_status' => 'A',
		),__LINE__,__FILE__)->fetchColumn()) === false)
		{
			return false;	// account not found
		}
		// Check the old_passwd to make sure this is legal
		if(!$admin && !auth::compare_password($old_passwd,$pw,$this->type,strtolower($username)))
		{
			return false;
		}

		// old password ok, or admin called the function from the admin application (no old passwd available).
		return $this->_update_passwd(auth::encrypt_sql($new_passwd),$new_passwd,$account_id,$admin);
	}

	/**
	 * changes password in sql datababse
	 *
	 * @internal
	 * @param string $encrypted_passwd
	 * @param string $new_passwd cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param boolean $admin=false called by admin, if not update password in the session
	 * @return boolean true if password successful changed, false otherwise
	 */
	function _update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin=false)
	{
		$this->db->update($this->table,array(
			'account_pwd' => $encrypted_passwd,
			'account_lastpwd_change' => time(),
		),array(
			'account_id' => $account_id,
		),__LINE__,__FILE__);

		if(!$this->db->affected_rows()) return false;

		if(!$admin)
		{
			$GLOBALS['egw']->session->appsession('password','phpgwapi',$new_passwd);
		}
		return $encrypted_passwd;
	}
}
