<?php
  /**************************************************************************\
  * phpGroupWare API - Auth from Mail server                                 *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Authentication based on mail server                                      *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of phpGroupWare (http://www.phpgroupware.org)       * 
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

    function authenticate($username, $passwd) {
      global $phpgw_info, $phpgw;
      error_reporting(error_reporting() - 2);


      if ($phpgw_info["server"]["mail_login_type"] == "vmailmgr") {
        $username = $username . "@" . $phpgw_info[server][mail_suffix];
      }
      if ($phpgw_info["server"]["mail_server_type"]=="imap") {
         $phpgw_info["server"]["mail_port"] = "143";
      } elseif ($phpgw_info["server"]["mail_server_type"]=="pop3") {
         $phpgw_info["server"]["mail_port"] = "110";
      }

      $mailauth = imap_open("{".$phpgw_info["server"]["mail_server"]
			     .":".$phpgw_info["server"]["mail_port"]."}INBOX", $username , $passwd);

      error_reporting(error_reporting() + 2);
      if ($mailauth == False) {
        return False;
      } else {
        imap_close($mailauth);
        return True;
      }
    }
    function change_password($old_passwd, $new_passwd) {
      global $phpgw_info, $phpgw;
      return False;
    }
  }
?>