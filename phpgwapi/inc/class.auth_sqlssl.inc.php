<?php
	/**************************************************************************\
	* phpGroupWare API - Auth from SQL, with optional SSL authentication       *
	* This file written by Andreas 'Count' Kotes <count@flatline.de>           *
	* Authentication based on SQL table and X.509 certificates                 *
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
			$db = $GLOBALS['phpgw']->db;

			$local_debug = False;

			if($local_debug)
			{
				echo "<b>Debug SQL: uid - $username passwd - $passwd</b>";
			}

			# Apache + mod_ssl provide the data in the environment
			# Certificate (chain) verification occurs inside mod_ssl
			# see http://www.modssl.org/docs/2.8/ssl_howto.html#ToC6
			if(!isset($GLOBALS['HTTP_SERVER_VARS']['SSL_CLIENT_S_DN']))
			{
				# if we're not doing SSL authentication, behave like auth_sql
				$db->query("SELECT * FROM phpgw_accounts WHERE account_lid = '$username' AND "
					. "account_pwd='" . md5($passwd) . "' AND account_status ='A'",__LINE__,__FILE__);
				$db->next_record();
			}
			else
			{
				# use username only for authentication, ignore X.509 subject in $passwd for now
				$db->query("SELECT * FROM phpgw_accounts WHERE account_lid = '$username' AND account_status ='A'",__LINE__,__FILE__);
				$db->next_record();
			}

			if($db->f('account_lid'))
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		function change_password($old_passwd, $new_passwd, $account_id = '')
		{
			if(!$account_id)
			{
				$account_id = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$encrypted_passwd = md5($new_passwd);

			$GLOBALS['phpgw']->db->query("UPDATE phpgw_accounts SET account_pwd='" . md5($new_passwd) . "',"
				. "account_lastpwd_change='" . time() . "' WHERE account_id='" . $account_id . "'",__LINE__,__FILE__);

			$GLOBALS['phpgw']->session->appsession('password','phpgwapi',$new_passwd);

			return $encrypted_passwd;
		}

		function update_lastlogin($account_id, $ip)
		{
			$GLOBALS['phpgw']->db->query("SELECT account_lastlogin FROM phpgw_accounts WHERE account_id='$account_id'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();
			$this->previous_login = $GLOBALS['phpgw']->db->f('account_lastlogin');

			$GLOBALS['phpgw']->db->query("UPDATE phpgw_accounts SET account_lastloginfrom='"
				. "$ip', account_lastlogin='" . time()
				. "' WHERE account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
