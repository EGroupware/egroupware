<?php
	/**************************************************************************\
	* phpGroupWare API - Auth from NIS	                                     *
	* Authentication based on NIS maps                                         *
	* by Dylan Adams <dadams@jhu.edu>                                          *
	* Copyright (C) 2001 Dylan Adams                                           *
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
		function authenticate($username, $passwd)
		{
			$domain = yp_get_default_domain();
			if( !empty($GLOBALS['phpgw_info']['server']['nis_domain']) )
			{
				$domain = $GLOBALS['phpgw_info']['server']['nis_domain'];
			}

			$map = "passwd.byname";
			if( !empty($GLOBALS['phpgw_info']['server']['nis_map']) )
			{
				$map = $GLOBALS['phpgw_info']['server']['nis_map']);
			}
			$entry = yp_match( $domain, $map, $username );

            /*
             * we assume that the map is structured in the usual
             * unix passwd flavor
             */
			$entry_array = explode( ':', $entry );
			$stored_passwd = $entry_array[1];

			$encrypted_passwd = crypt( $passwd, $stored_passwd );

			return( $encrypted_passwd == $stored_passwd );
		}

		function change_password($old_passwd, $new_passwd, $account_id = '')
		{
			// can't change passwords unless server runs as root (bad idea)
			return( False );
		}

		function update_lastlogin($account_id, $ip)
		{
			$account_id = get_account_id($account_id);

			$GLOBALS['phpgw']->db->query("update phpgw_accounts set account_lastloginfrom='"
				. "$ip', account_lastlogin='" . time()
				. "' where account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
