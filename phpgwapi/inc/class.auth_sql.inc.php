<?php
  /**************************************************************************\
  * phpGroupWare API - Auth from SQL                                         *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Authentication based on SQL table                                        *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
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

	class auth
	{
		var $previous_login = -1;

		function authenticate($username, $passwd)
		{
			global $phpgw_info, $phpgw;
			$db = $phpgw->db;

			$db->query("SELECT * FROM phpgw_accounts WHERE account_lid = '$username' AND "
				. "account_pwd='" . md5($passwd) . "' AND account_status ='A'",__LINE__,__FILE__);
			$db->next_record();

			if ($db->f('account_lid'))
			{
				$this->previous_login = $db->f('account_lastlogin');
				return True;
			}
			else
			{
				return False;
			}
		}

		function change_password($old_passwd, $new_passwd, $account_id = '')
		{
			global $phpgw_info, $phpgw;

			if (! $account_id)
			{
				$account_id = $phpgw_info['user']['account_id'];
			}

			$encrypted_passwd = md5($new_passwd);

			$phpgw->db->query("update phpgw_accounts set account_pwd='" . md5($new_passwd) . "',"
				. "account_lastpwd_change='" . time() . "' where account_id='" . $account_id . "'",__LINE__,__FILE__);

			$phpgw->session->appsession('password','phpgwapi',$new_passwd);

			return $encrypted_passwd;
		}

		function update_lastlogin($account_id, $ip)
		{
			global $phpgw;

			$phpgw->db->query("update phpgw_accounts set account_lastloginfrom='"
				. "$ip', account_lastlogin='" . time()
				. "' where account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
