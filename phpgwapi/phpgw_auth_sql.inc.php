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
  }
?>