<?php
  /**************************************************************************\
  * phpGroupWare API - Auth from SQL                                         *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Encryption types other than md5() added by                               *
  *  Miles Lott <milos@groupwhere.org> based on code from                    *
  *  http://www.thomas-alfeld.de/frank/                                      *
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
			copyobj($GLOBALS['phpgw']->db,$this->db);
		}

		function authenticate($username, $passwd, $passwd_type)
		{
			if($passwd_type == 'text')
			{
				/* normal web form login */
				$type = @$GLOBALS['phpgw_info']['server']['sql_encryption_type']
					? strtolower($GLOBALS['phpgw_info']['server']['sql_encryption_type'])
					: 'md5';
				switch($type)
				{
					case 'smd5':
						$this->db->query("SELECT account_lid,account_pwd FROM phpgw_accounts WHERE account_lid = '$username' AND "
							. " account_type='u' AND "
							. " account_status ='A'",__LINE__,__FILE__);
						$this->db->next_record();

						if($GLOBALS['phpgw_info']['server']['case_sensitive_username'] == true)
						{
							if($this->db->f('account_lid') != $username)
							{
								return false;
							}
						}
						if($this->db->f('account_pwd'))
						{
							return $this->smd5_compare($passwd,$this->db->f('account_pwd'));
						}
						break;
					case 'sha':
						$this->db->query("SELECT account_lid,account_pwd FROM phpgw_accounts WHERE account_lid = '$username' AND "
							. " account_type='u' AND "
							. " account_status ='A'",__LINE__,__FILE__);
						$this->db->next_record();

						if($GLOBALS['phpgw_info']['server']['case_sensitive_username'] == true)
						{
							if($this->db->f('account_lid') != $username)
							{
								return false;
							}
						}
						if($this->db->f('account_pwd'))
						{
							return $this->sha_compare($passwd,$this->db->f('account_pwd'));
						}
						break;
					case 'ssha':
						$this->db->query("SELECT account_lid,account_pwd FROM phpgw_accounts WHERE account_lid = '$username' AND "
							. " account_type='u' AND "
							. " account_status ='A'",__LINE__,__FILE__);
						$this->db->next_record();

						if($GLOBALS['phpgw_info']['server']['case_sensitive_username'] == true)
						{
							if($this->db->f('account_lid') != $username)
							{
								return false;
							}
						}
						if($this->db->f('account_pwd'))
						{
							return $this->ssha_compare($passwd,$this->db->f('account_pwd'));
						}
						break;
					case 'md5_crypt':
					case 'blowfish_crypt':
					case 'ext_crypt':
					case 'crypt':
						$this->db->query("SELECT account_lid,account_pwd FROM phpgw_accounts WHERE account_lid = '$username' AND "
							. " account_type='u' AND "
							. " account_status ='A'",__LINE__,__FILE__);
						$this->db->next_record();

						if($GLOBALS['phpgw_info']['server']['case_sensitive_username'] == true)
						{
							if($this->db->f('account_lid') != $username)
							{
								return false;
							}
						}
						if($this->db->f('account_pwd'))
						{
							return $this->crypt_compare($passwd,$this->db->f('account_pwd'),$type);
						}
						break;
					case 'md5':
					default:
						$this->db->query("SELECT * FROM phpgw_accounts WHERE account_lid = '$username' AND "
							. " account_type='u' AND "
							. "account_pwd='" . md5($passwd) . "' AND account_status ='A'",__LINE__,__FILE__);
						$this->db->next_record();

						if($GLOBALS['phpgw_info']['server']['case_sensitive_username'] == true)
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
						break;
				}
			}
			elseif($passwd_type == 'md5')
			{
				/* Where is this used? */
				$this->db->query("SELECT * FROM phpgw_accounts WHERE account_lid = '$username' AND "
//					. " account_type='u' AND "
					. "account_pwd='" . $passwd . "' AND account_status ='A'",__LINE__,__FILE__);
				$this->db->next_record();

				if($GLOBALS['phpgw_info']['server']['case_sensitive_username'] == true)
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
			else
			{
				return False;
			}
		}

		function change_password($old_passwd, $new_passwd, $account_id = '')
		{
			$admin = True;
			// Don't allow password changes for other accounts when using XML-RPC
			if(!$account_id || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'login')
			{
				$admin = False;
				$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			}
			$encrypted_passwd = $this->encrypt_sql($new_passwd);

			/* Grab configured type, or default to md5() (old method) */
			$type = @$GLOBALS['phpgw_info']['server']['sql_encryption_type']
				? strtolower($GLOBALS['phpgw_info']['server']['sql_encryption_type'])
				: 'md5';
			switch($type)
			{
				case 'smd5':
					$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
						. "' AND " // . " account_type='u' AND "
						. " account_status ='A'",__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->f('account_pwd'))
					{
						if(!$admin)
						{
							/* Check the old_passwd to make sure this is legal */
							if(!$this->smd5_compare($old_passwd,$this->db->f('account_pwd')))
							{
								return False;
							}
						}
						/* old password ok, or admin called the function from
						 * the admin application (no old passwd available).
						 */
						return $this->_update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin,__FILE__);
					}
					return False;
				case 'sha':
					$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
						. "' AND " // . " account_type='u' AND "
						. " account_status ='A'",__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->f('account_pwd'))
					{
						if(!$admin)
						{
							/* Check the old_passwd to make sure this is legal */
							if(!$this->sha_compare($old_passwd,$this->db->f('account_pwd')))
							{
								return False;
							}
						}
						/* old password ok, or admin called the function from
						 * the admin application (no old passwd available).
						 */
						return $this->_update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin,__FILE__);
					}
					return False;
				case 'ssha':
					$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
						. "' AND " // . " account_type='u' AND "
						. " account_status ='A'",__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->f('account_pwd'))
					{
						if(!$admin)
						{
							/* Check the old_passwd to make sure this is legal */
							if(!$this->ssha_compare($old_passwd,$this->db->f('account_pwd')))
							{
								return False;
							}
						}
						/* old password ok, or admin called the function from
						 * the admin application (no old passwd available).
						 */
						return $this->_update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin,__FILE__);
					}
					return False;
				case 'crypt':
				case 'ext_crypt':
				case 'md5_crypt':
				case 'blowfish_crypt':
					$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
						. "' AND " // . " account_type='u' AND "
						. " account_status ='A'",__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->f('account_pwd'))
					{
						if(!$admin)
						{
							/* Check the old_passwd to make sure this is legal */
							if(!$this->crypt_compare($old_passwd,$this->db->f('account_pwd'),$type))
							{
								return False;
							}
						}
						/* old password ok, or admin called the function from
						 * the admin application (no old passwd available).
						 */
						return $this->_update_passwd($encrypted_passwd,$new_passwd,$account_id,$admin,__FILE__);
					}
					return False;
				case 'md5':
				default:
					$pwd_check = '';
					if(!$admin)
					{
						$pwd_check = " AND account_pwd='" . $this->encrypt_sql($old_passwd) . "'";
					}
					$this->db->query("UPDATE phpgw_accounts SET account_pwd='" . $encrypted_passwd . "',"
						. "account_lastpwd_change='" . time() . "' WHERE account_id='" . $account_id . "'" . $pwd_check,__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->affected_rows())
					{
						if(!$admin)
						{
							$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);
						}
						return $encrypted_passwd;
					}
					else
					{
						return False;
					}
			}
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
					$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);
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
			$GLOBALS['phpgw']->db->query("UPDATE phpgw_accounts SET account_lastloginfrom='"
				. "$ip', account_lastlogin='" . time()
				. "' WHERE account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
