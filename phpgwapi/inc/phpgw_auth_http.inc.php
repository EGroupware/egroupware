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
      global $phpgw_info, $phpgw, $PHP_AUTH_USER;
      
      if (isset($PHP_AUTH_USER)) {
        return True;
      } else {
        return False;
      }
    }
    function change_password($old_passwd, $new_passwd) {
      global $phpgw_info, $phpgw;
      return $old_passwd;
    }
  }
?>