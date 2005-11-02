<?php
	/**************************************************************************\
	* eGroupWare API - Auth from SQL                                           *
	* This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	* and Joseph Engo <jengo@phpgroupware.org>                                 *
	* Encryption types other than md5() added by                               *
	*  Miles Lott <milos@groupwhere.org> based on code from                    *
	*  http://www.thomas-alfeld.de/frank/                                      *
	* massive code cleanup and                                                 *
	* added password migration by                                              *
	*  Cornelius Weiss <egw@von-und-zu-weiss.de                                *
	* Authentication based on SQL table                                        *
	* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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
		var $db = '';
		var $previous_login = -1;

		function auth_()
		{
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('phpgwapi');
			$this->table = 'egw_accounts';

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
			if($passwd_type == 'text')
			{
				$this->db->select($this->table,'account_lid,account_pwd,account_lastlogin',array(
					'account_lid'    => $username,
					'account_type'   => 'u',
					'account_status' => 'A'
				),__LINE__,__FILE__);
				
				if(!$this->db->next_record() || !$this->db->f('account_pwd') ||
					$GLOBALS['egw_info']['server']['case_sensitive_username'] && $this->db->f('account_lid') != $username)
				{
					return false;
				}
				if(!$this->compare_password($passwd,$this->db->f('account_pwd'),$this->type,strtolower($username)))
				{
					$match = false;
					// do we have to migrate an old password ?
					if($GLOBALS['egw_info']['server']['pwd_migration_allowed'] && !emplty($GLOBALS['egw_info']['server']['pwd_migration_types']))
					{
						foreach(explode(',', $GLOBALS['egw_info']['server']['pwd_migration_types']) as $type)
						{
							if($this->compare_password($passwd,$this->db->f('account_pwd'),$type,strtolower($username)))
							{
								$account_id = $GLOBALS['egw_info']['user']['account_id'];
								$encrypted_passwd = $this->encrypt_sql($passwd);
								$this->_update_passwd($encrypted_passwd,$passwd,$account_id);
								$match = true;
								break;
							}
						}
					}
					if (!$match) return false;
				}
			}
			/* Auth via crypted password. NOTE: mail needs cleartext password to authenticate against mailserver! */
			else
			{
				$this->db->select($this->table,'account_lid,account_lastlogin',array(
					'account_lid' => $username,
					'account_type'   => 'u',
					'account_status' => 'A',
					'account_pwd' => $passwd,
				),__LINE__,__FILE__);
				
				if(!$this->db->next_record() ||
					$GLOBALS['egw_info']['server']['case_sensitive_username'] && $this->db->f('account_lid') != $username)
				{
					return false;
				}
			}
			// if this point is reached, auth was successfull
			$this->previous_login = $this->db->f('account_lastlogin');

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
					
			$this->db->select($this->table,'account_pwd',array(
				'account_id'     => $account_id,
				'account_type'   => 'u',
				'account_status' => 'A',
			),__LINE__,__FILE__);

			if(!$this->db->next_record()) return false;	// account not found

			/* Check the old_passwd to make sure this is legal */
			if(!$admin && !$this->compare_password($old_passwd,$this->db->f('account_pwd'),$this->type,strtolower($username)))
			{
				return false;
			}

			/* old password ok, or admin called the function from the admin application (no old passwd available).*/
			return $this->_update_passwd($this->encrypt_sql($new_passwd),$new_passwd,$account_id,$admin);
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
