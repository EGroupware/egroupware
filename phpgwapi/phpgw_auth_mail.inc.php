<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id $ */

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
  }
?>