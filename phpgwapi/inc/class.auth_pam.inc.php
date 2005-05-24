<?php
  /**************************************************************************\
  * eGroupWare API - Auth from PAM                                           *
  * -------------------------------------------------------------------------*
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
		function authenticate($username, $passwd)
		{
			if (pam_auth($username, get_magic_quotes_gpc() ? stripslashes($passwd) : $passwd, &$error)) 
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		function change_password($old_passwd, $new_passwd, $account_id='')
		{
			// deny password changes.
			return( False );
		}

		function update_lastlogin($account_id, $ip)
		{
			$account_id = get_account_id($account_id);
	
			$GLOBALS['phpgw']->db->query('update phpgw_accounts set account_lastloginfrom='
			        . $GLOBALS['phpgw']->db->quote($ip).', account_lastlogin=' . time()
			        . ' where account_id='.(int)$account_id,__LINE__,__FILE__);
		}
	}
?>
