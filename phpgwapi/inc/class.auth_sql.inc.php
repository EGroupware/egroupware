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

  /* $ Id $ */

  class auth
  {

    function authenticate($username, $passwd) {
      global $phpgw_info, $phpgw;
      
      $db = $phpgw->db;
      
      $local_debug = false;
     
      if ($local_debug) {
         echo "<b>Debug SQL: uid - $username passwd - $passwd</b>";
      }
      
      $db->query("SELECT * FROM accounts WHERE account_lid = '$username' AND "
               . "account_pwd='" . md5($passwd) . "' AND account_status ='A'",__LINE__,__FILE__);
      $db->next_record();

      if ($db->f("account_lid")) {
        return True;
      } else {
        return False;
      }
    }

    function change_password($old_passwd, $new_passwd) {
      global $phpgw_info, $phpgw;
      $encrypted_passwd = md5($new_passwd);
      $phpgw->db->query("update accounts set account_pwd='" . md5($new_passwd) . "' "
	                  . "where account_lid='" . $phpgw_info["user"]["userid"] . "'",__LINE__,__FILE__);
      $phpgw->db->query("update accounts set account_lastpwd_change='" . time() . "' where account_id='"
   			    	. $phpgw_info["user"]["account_id"] . "'",__LINE__,__FILE__);

      return $encrypted_passwd;
    }
  }
?>
