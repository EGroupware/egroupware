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
							/* Start with the first char after {SHA} */
							$hash = base64_decode(substr($this->db->f('account_pwd'),5));
							$new_hash = mhash(MHASH_SHA1, $passwd);
//							echo '<br>  DB: ' . base64_encode($orig_hash) . '<br>FORM: ' . base64_encode($new_hash);

							if(strcmp($hash,$new_hash) == 0)
							{
								$this->previous_login = $this->db->f('account_lastlogin');
								return True;
							}
							else
							{
								return False;
							}
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
							/* Start with the first char after {SSHA} */
							$hash = base64_decode(substr($this->db->f('account_pwd'), 6));

							// SHA-1 hashes are 160 bits long
							$orig_hash = substr($hash, 0, 20);
							$salt = substr($hash, 20);
							$new_hash = mhash(MHASH_SHA1, $passwd . $salt);
//							echo '<br>  DB: ' . base64_encode($orig_hash) . '<br>FORM: ' . base64_encode($new_hash);exit;

							if(strcmp($orig_hash,$new_hash) == 0)
							{
								$this->previous_login = $this->db->f('account_lastlogin');
								return True;
							}
							else
							{
								return False;
							}
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
							$saltlen = array(
								'blowfish_crypt' => 16,
								'md5_crypt' => 12,
								'ext_crypt' => 9,
								'crypt' => 2
							);
							$hash = $this->db->f('account_pwd');

							// PHP's crypt(): salt + hash
							// notice: "The encryption type is triggered by the salt argument."
							$salt = substr($hash, 0, (int)$saltlen[$type]);
							$new_hash = crypt($passwd, $salt);
//							echo "$hash<br>" . $new_hash;

							if(strcmp($hash,$new_hash) == 0)
							{
								$this->previous_login = $this->db->f('account_lastlogin');
								return True;
							}
							else
							{
								return False;
							}
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
			// Don't allow password changes for other accounts when using XML-RPC
			if(!$account_id || $GLOBALS['phpgw_info']['flags']['currentapp'] == 'login')
			{
				$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			}
			$encrypted_passwd = $this->encrypt_sql($new_passwd);
			echo 'old: ' . $old_passwd . 'new: ' . $new_passwd . ' ' . $account_id;
			echo $encrypted_passwd;exit;

			$type = @$GLOBALS['phpgw_info']['server']['sql_encryption_type']
				? strtolower($GLOBALS['phpgw_info']['server']['sql_encryption_type'])
				: 'md5';
			switch($type)
			{
				case 'sha':
					$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
						. "' AND " // . " account_type='u' AND "
						. " account_status ='A'",__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->f('account_pwd'))
					{
						/* Start with the first char after {SHA} */
						$hash = base64_decode(substr($this->db->f('account_pwd'),5));
						$new_hash = mhash(MHASH_SHA1, $old_passwd);
						if(strcmp($orig_hash,$new_hash) == 0)
						{
							/* old password ok */
							$this->db->query("UPDATE phpgw_accounts SET account_pwd='" . $encrypted_passwd . "',"
								. "account_lastpwd_change='" . time()
								. "' WHERE account_id=" . (int)$account_id,__LINE__,__FILE__);
							$this->db->next_record();
							if($this->db->affected_rows())
							{
								$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);
								return $encrypted_passwd;
							}
							else
							{
								return False;
							}
						}
					}
					break;
				case 'ssha':
					$this->db->query("SELECT account_pwd FROM phpgw_accounts WHERE account_id = '" . (int)$account_id
						. "' AND " // . " account_type='u' AND "
						. " account_status ='A'",__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->f('account_pwd'))
					{
						/* Start with the first char after {SSHA} */
						$hash = base64_decode(substr($this->db->f('account_pwd'), 6));

						// SHA-1 hashes are 160 bits long
						$orig_hash = substr($hash, 0, 20);
						$salt = substr($hash, 20);
						$new_hash = mhash(MHASH_SHA1, $old_passwd . $salt);
						if(strcmp($orig_hash,$new_hash) == 0)
						{
							/* old password ok */
							$this->db->query("UPDATE phpgw_accounts SET account_pwd='" . $encrypted_passwd . "',"
								. "account_lastpwd_change='" . time()
								. "' WHERE account_id=" . (int)$account_id,__LINE__,__FILE__);
							$this->db->next_record();
							if($this->db->affected_rows())
							{
								$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);
								return $encrypted_passwd;
							}
							else
							{
								return False;
							}
						}
					}
					break;
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
						$saltlen = array(
							'blowfish_crypt' => 16,
							'md5_crypt' => 12,
							'ext_crypt' => 9,
							'crypt' => 2
						);
						$hash = $this->db->f('account_pwd');

						// PHP's crypt(): salt + hash
						// notice: "The encryption type is triggered by the salt argument."
						$salt = substr($hash, 0, (int)$saltlen[$type]);
						$new_hash = crypt($old_passwd, $salt);
//						echo "$hash<br>" . $new_hash;

						if(strcmp($hash,$new_hash) == 0)
						{
							/* old password ok */
							$this->db->query("UPDATE phpgw_accounts SET account_pwd='" . $encrypted_passwd . "',"
								. "account_lastpwd_change='" . time()
								. "' WHERE account_id=" . (int)$account_id,__LINE__,__FILE__);
							$this->db->next_record();
							if($this->db->affected_rows())
							{
								$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);
								return $encrypted_passwd;
							}
							else
							{
								return False;
							}
						}
					}
					return False;
				case 'md5':
				default:
					$pwd_check = " AND account_pwd='" . $GLOBALS['phpgw']->common->sql_encrypt_password($old_passwd) . "'";
					$this->db->query("UPDATE phpgw_accounts SET account_pwd='" . $encrypted_passwd . "',"
						. "account_lastpwd_change='" . time() . "' WHERE account_id='" . $account_id . "'" . $pwd_check,__LINE__,__FILE__);
					$this->db->next_record();
					if($this->db->affected_rows())
					{
						$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);
						return $encrypted_passwd;
					}
					else
					{
						return False;
					}
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
