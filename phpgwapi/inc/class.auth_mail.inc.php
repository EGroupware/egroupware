<?php
  /**************************************************************************\
  * phpGroupWare API - Auth from Mail server                                 *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Authentication based on mail server                                      *
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
			error_reporting(error_reporting() - 2);

			if ($GLOBALS['phpgw_info']['server']['mail_login_type'] == 'vmailmgr')
			{
				$username = $username . '@' . $GLOBALS['phpgw_info']['server']['mail_suffix'];
			}
			if ($GLOBALS['phpgw_info']['server']['mail_server_type']=='imap')
			{
				$GLOBALS['phpgw_info']['server']['mail_port'] = '143';
			}
			elseif ($GLOBALS['phpgw_info']['server']['mail_server_type']=='pop3')
			{
				$GLOBALS['phpgw_info']['server']['mail_port'] = '110';
			}
 			elseif ($GLOBALS['phpgw_info']['server']['mail_server_type']=='imaps')
 			{
 				$GLOBALS['phpgw_info']['server']['mail_port'] = '993';
 			}
 			elseif ($GLOBALS['phpgw_info']['server']['mail_server_type']=='pop3s')
 			{
 				$GLOBALS['phpgw_info']['server']['mail_port'] = '995';
 			}

			if( $GLOBALS['phpgw_info']['server']['mail_server_type']=='pop3')
			{
				$mailauth = imap_open('{'.$GLOBALS['phpgw_info']['server']['mail_server'].'/pop3'
					.':'.$GLOBALS['phpgw_info']['server']['mail_port'].'}INBOX', $username , $passwd);
			}
 			elseif ( $GLOBALS['phpgw_info']['server']['mail_server_type']=='imaps' )
 			{
 				// IMAPS support:
 				$mailauth = imap_open('{'.$GLOBALS['phpgw_info']['server']['mail_server']."/ssl/novalidate-cert"
                                         .':993}INBOX', $username , $passwd);
 			}
 			elseif ( $GLOBALS['phpgw_info']['server']['mail_server_type']=='pop3s' )
 			{
 				// POP3S support:
 				$mailauth = imap_open('{'.$GLOBALS['phpgw_info']['server']['mail_server']."/ssl/novalidate-cert"
                                         .':995}INBOX', $username , $passwd);
			}
			else
			{
				/* assume imap */
				$mailauth = imap_open('{'.$GLOBALS['phpgw_info']['server']['mail_server']
					.':'.$GLOBALS['phpgw_info']['server']['mail_port'].'}INBOX', $username , $passwd);
			}

			error_reporting(error_reporting() + 2);
			if ($mailauth == False)
			{
				return False;
			}
			else
			{
				imap_close($mailauth);
				return True;
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
