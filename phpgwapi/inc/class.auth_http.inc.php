<?php
  /**************************************************************************\
  * phpGroupWare API - Auth from HTTP                                        *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Authentication based on HTTP auth                                        *
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
			if (isset($_SERVER['PHP_AUTH_USER']))
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		function change_password($old_passwd, $new_passwd)
		{
			return False;
		}

		// Since there account data will still be stored in SQL, this should be safe to do. (jengo)
		function update_lastlogin($account_id, $ip)
		{
			$GLOBALS['phpgw']->db->query("select account_lastlogin from phpgw_accounts where account_id='$account_id'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();
			$this->previous_login = $GLOBALS['phpgw']->db->f('account_lastlogin');

			$GLOBALS['phpgw']->db->query("update phpgw_accounts set account_lastloginfrom='"
				. "$ip', account_lastlogin='" . time()
				. "' where account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
