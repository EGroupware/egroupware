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
      //  error_reporting MUST be set to zero, otherwise you'll get nasty LDAP errors with a bad login/pass...
      //  these are just "warnings" and can be ignored.....
      error_reporting(0); 
      
      $ldap = ldap_connect($phpgw_info["server"]["ldap_host"]);
      
      // find the dn for this uid, the uid is not always in the dn
      $sri = ldap_search($ldap, $phpgw_info["server"]["ldap_context"], "uid=$username");
      $allValues = ldap_get_entries($ldap, $sri);
      if($allValues["count"] > 0)
      {
      	// we only care about the first dn
      	$userDN = $allValues[0]["dn"];

      	// generate a bogus password to pass if the user doesn't give us one 
	// this gets around systems that are anonymous search enabled 
        if (empty($passwd)) $passwd = crypt(microtime()); 
      	// try to bind as the user with user suplied password
      	if (ldap_bind($ldap,$userDN, $passwd)) return True;
      }

      // Turn error reporting back to normal
      error_reporting(7);

      // dn not found or password wrong
      return False;
    } 
    
    function change_password($old_passwd, $new_passwd) {
      global $phpgw_info, $phpgw;
      $ldap = ldap_connect($phpgw_info["server"]["ldap_host"]);

      if (! @ldap_bind($ldap, $phpgw_info["server"]["ldap_root_dn"], $phpgw_info["server"]["ldap_root_pw"])) {
         echo "<p><b>Error binding to LDAP server.  Check your config</b>";
         $phpgw->common->phpgw_exit();
      }

      $encrypted_passwd = $phpgw->common->encrypt_password($new_passwd);
      $entry["userpassword"] = $encrypted_passwd;
      $entry["phpgw_lastpasswd_change"] = time();

      $dn = $phpgw_info["user"]["account_dn"];
      @ldap_modify($ldap, $dn, $entry);
      return $encrypted_passwd;
    }
  }
?>
