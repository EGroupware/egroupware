<?php
	/**************************************************************************\
	* eGroupWare API - Auth from SQL, with optional SSL authentication         *
	* This file written by Andreas 'Count' Kotes <count@flatline.de>           *
	* Authentication based on SQL table and X.509 certificates                 *
	* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	* ------------------------------------------------------------------------ *
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            *
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
		}

		function authenticate($username, $passwd)
		{
			$local_debug = False;

			if($local_debug)
			{
				echo "<b>Debug SQL: uid - $username passwd - $passwd</b>";
			}
			$this->db->select($this->table,'account_lid,account_pwd',array(
				'account_lid' => $username,
				'account_status' => 'A',
				'account_type'   => 'u',
			),__LINE__,__FILE__);
			
			if (!$this->db->next_record() || $GLOBALS['egw_info']['server']['case_sensitive_username'] && $db->f('account_lid') != $username)
			{
				return false;
			}

			# Apache + mod_ssl provide the data in the environment
			# Certificate (chain) verification occurs inside mod_ssl
			# see http://www.modssl.org/docs/2.8/ssl_howto.html#ToC6
			if(!isset($_SERVER['SSL_CLIENT_S_DN']))
			{
				# if we're not doing SSL authentication, behave like auth_sql
				return $this->compare_password($passwd,$this->db->f('account_pwd'),$this->type,strtolower($username));
			}
			return True;
		}

		function change_password($old_passwd, $new_passwd, $account_id = '')
		{
			if(!$account_id)
			{
				$account_id = $GLOBALS['egw_info']['user']['account_id'];
			}

			$encrypted_passwd = $this->encrypt_sql($new_passwd);

			$GLOBALS['egw']->db->update($this->table,array(
				'account_pwd' => $encrypted_passwd,
				'account_lastpwd_change' => time(),
			),array(
				'account_id' => $account_id,
			),__LINE__,__FILE__);

			$GLOBALS['egw']->session->appsession('password','phpgwapi',$new_passwd);

			return $encrypted_passwd;
		}
	}
