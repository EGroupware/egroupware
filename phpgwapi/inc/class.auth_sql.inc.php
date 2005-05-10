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
			copyobj($GLOBALS['egw']->db,$this->db);
			$this->type = @$GLOBALS['egw_info']['server']['sql_encryption_type']
				? strtolower($GLOBALS['egw_info']['server']['sql_encryption_type'])
				: 'md5';
		}

		/*!
		@function authenticate
		@abstract password authentication against password stored in sql datababse
		@param $username username of account to authenticate
		@param $passwd corresponding password
		@param $passwd_type 'text' for cleartext passwords
		*/
		function authenticate($username, $passwd, $passwd_type)
		{
			/* normal web form login */
			if($passwd_type == 'text')
			{
				$this->db->query("SELECT account_lid,account_pwd,account_lastlogin FROM phpgw_accounts WHERE account_lid = '$username' AND "
					. " account_type='u' AND "
					. " account_status ='A'",__LINE__,__FILE__);
				$this->db->next_record();
				
				if($GLOBALS['egw_info']['server']['case_sensitive_username'] == true)
				{
					if($this->db->f('account_lid') != $username) return false;
				}
				if(!$this->db->f('account_pwd')) return false;
				if(!$this->compare_password($passwd,$this->db->f('account_pwd'),$this->type,strtolower($username)))
				{
					// do we have to migrate an old password ?
					if($GLOBALS['egw_info']['server']['passwd_migration_allowed'])
					{
						foreach($GLOBALS['egw_info']['server']['passwd_migration_types'] as $type)
						{
							if($this->compare_password($passwd,$this->db->f('account_pwd'),$type,strtolower($username)))
							{
								$account_id = $GLOBALS['egw_info']['user']['account_id'];
								$encrypted_passwd = $this->encrypt_sql($passwd);
								$this->_update_passwd($encrypted_passwd,$passwd,$account_id,false,__FILE__);
								break;
							}
						}
					}
					else
					{
						return false;
					}
				}

				/* if this point is reached, auth was successfull */
				$this->previous_login = $this->db->f('account_lastlogin');
				return true;
			}
			/* Auth via crypted password. NOTE: mail needs cleartext password to authenticate against mailserver! */
			else
			{
				$this->db->query("SELECT * FROM phpgw_accounts WHERE account_lid = '$username' AND "
					. "account_pwd='" . $passwd . "' AND account_status ='A'",__LINE__,__FILE__);
				$this->db->next_record();

				if($GLOBALS['egw_info']['server']['case_sensitive_username'] == true)
				{
					if($this->db->f('account_lid') != $username)
					{
						return false;
					}
				}
				if($this->db->f('account_lid'))
				{
					$this->previous_login = $this->db->f('account_lastlogin');
					return True;
				}
				else
				{
					return False;
				}
			}
		}

		/*!
		@function change_password
		@abstract changes password in sql datababse
		@param $old_passwd must be cleartext
		@param $new_passwd must be cleartext
		@param $account_id account id of user whose passwd should be changed
		*/
		function change_password($old_passwd, $new_passwd, $account_id = '')
		{
			$admin = True;
			// Don't allow password changes for other accounts when using XML-RPC
			if(!$account_id || $GLOBALS['egw_info']['flags']['currentapp'] == 'login')
			{
				$admin = False;
				$account_id = $GLOBALS['egw_info']['user']['account_id'];
			}
					
			$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
				. "' AND " // . " account_type='u' AND "
				. " account_status ='A'",__LINE__,__FILE__);
			$this->db->next_record();
			if(!$this->db->f('account_pwd')) return false;

			/* Check the old_passwd to make sure this is legal */
			if(!$admin)
			{
				if(!$this->compare_password($old_passwd,$this->db->f('account_pwd'),$this->type,strtolower($username))) return false;
			}

			/* old password ok, or admin called the function from the admin application (no old passwd available).*/
			$encrypted_passwd = $this->encrypt_sql($new_passwd);
			return $this->_update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin,__FILE__);
		}

		function _update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin=False,$file='')
		{
			/* This should only be called from this file */
			if($file != PHPGW_API_INC . SEP . 'class.auth_sql.inc.php')
			{
				return False;
			}
			$this->db->query("UPDATE phpgw_accounts SET account_pwd='" . $encrypted_passwd . "',"
				. "account_lastpwd_change='" . time()
				. "' WHERE account_id=" . (int)$account_id,__LINE__,__FILE__);
			$this->db->next_record();
			if($this->db->affected_rows())
			{
				if(!$admin)
				{
					$GLOBALS['egw']->session->appsession('password','phpgwapi',$new_passwd);
				}
				return $encrypted_passwd;
			}
			else
			{
				return False;
			}
		}

		function update_lastlogin($account_id, $ip)
		{
			$GLOBALS['egw']->db->query("UPDATE phpgw_accounts SET account_lastloginfrom='"
				. "$ip', account_lastlogin='" . time()
				. "' WHERE account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
