<?php
	/**************************************************************************\
	* phpGroupWare API - Auth to PAM                                           *
	* This file written by Miles Lott <milosch@phpgroupware.org>               *
	* Authentication to PAM source on localhost                                *
	* Copyright (C) 2002 Miles Lott                                            *
	* -------------------------------------------------------------------------*
	* This requires the module by <ccunning@math.ohio-state.edu> available at: *
	*   http://www.math.ohio-state.edu/~ccunning/pam_auth.html                 *
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
			/* generate a bogus password to pass if the user doesn't give us one */
			if(empty($passwd))
			{
				$passwd = crypt(microtime());
			}
			/* try to bind as the user with user supplied password */
			if(@pam_auth($username, $passwd))
			{
				return True;
			}

			/* password wrong */
			return False;
		}

		function change_password($old_passwd, $new_passwd, $_account_id='')
		{
			/* We can't do that...  Bummer. */
			return False;
		}

		function update_lastlogin($account_id, $ip)
		{
			$GLOBALS['phpgw']->db->query("SELECT account_lastlogin FROM phpgw_accounts WHERE account_id='$account_id'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->next_record();
			$this->previous_login = $GLOBALS['phpgw']->db->f('account_lastlogin');

			$now = time();

			$GLOBALS['phpgw']->db->query("UPDATE phpgw_accounts SET account_lastloginfrom='"
				. "$ip', account_lastlogin='" . $now
				. "' WHERE account_id='$account_id'",__LINE__,__FILE__);
		}
	}
?>
