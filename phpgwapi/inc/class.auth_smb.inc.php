<?php
	/**************************************************************************\
	* phpGroupWare API - Auth to SMB                                           *
	* This file written by Miles Lott <milosch@phpgroupware.org>               *
	* Authentication to SMB PDC/BDC                                            *
	* Copyright (C) 2002 Miles Lott                                            *
	* -------------------------------------------------------------------------*
	* This requires the module by <mfischer@guru.josefine.at>                  *
	*   http://php-smb.sourceforge.net/                                        *
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

			$pdc = $GLOBALS['phpgw_info']['server']['smb_pdc'] ? $GLOBALS['phpgw_info']['server']['smb_pdc'] : 'localhost';
			$bdc = $GLOBALS['phpgw_info']['server']['smb_bdc'] ? $GLOBALS['phpgw_info']['server']['smb_bdc'] : '';
			$domain = $GLOBALS['phpgw_info']['server']['smb_dom'] ? $GLOBALS['phpgw_info']['server']['smb_dom'] : '';

			/* smb_user_validate(string username, string password, string server [, string backup [, string domain]]) */
			$err = smb_user_validate($username, $passwd, $pdc, $bdc, $domain);
			switch($err)
			{
				case 1: /* Authenticated */
					return True;
					break;
				case -3: /* SMB_ERROR_LOGON */
				case -2: /* SMB_ERROR_PROTOCOL */
				case -1: /* SMB_ERROR_CONNECT */
				case -255: /* SMB_ERROR_UNKNOWN */
					return False;
			}
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
