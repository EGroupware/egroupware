<?php
  /**************************************************************************\
  * phpGroupWare - preferences                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);

  $phpgw_info["flags"]["currentapp"] = "preferences";
  include("../header.inc.php");

  if (!$phpgw->acl->check("changepassword", 1)){
    Header("Location: index.php");
    exit;
  }    

  if (! $submit) {
     $phpgw->common->phpgw_header();
     $phpgw->common->navbar();

    ?>
   <form method="POST" acion="<?php echo $phpgw->link("changepassword.php"); ?>">
    <table border="0">
     <tr>
       <td>
        <?php echo lang("enter your new password"); ?>
       </td>
       <td>
        <input type="password" name="n_passwd">
       </td>
     </tr>
     <tr>
       <td>
        <?php echo lang("re-enter your password"); ?>
       </td>
       <td>
        <input type="password" name="n_passwd_2">
       </td>
     </tr>
     <tr>
       <td colspan="2">
        <input type="submit" name="submit" value="<?php echo lang("change"); ?>">
       </td>
     </tr>
    </table>
   </form>
   <br>
   <?php
     if ($phpgw_info["server"]["auth_type"] != "ldap") {
        echo "<pre>" . lang("note: This feature does *not* change your email password. This will "
            	   	   . "need to be done manually.") . "</pre>";
     }
     $phpgw->common->phpgw_footer();
     
} else {
   if ($n_passwd != $n_passwd_2)
      $error = lang("the two passwords are not the same");

   if (! $n_passwd)
      $error = lang("you must enter a password");

   if ($error) {
      $phpgw->common->navbar();
      echo "<p><br>$error</p>";
      exit;
   }

   if ($phpgw_info["server"]["auth_type"] == "sql") {
      $phpgw->db->query("update accounts set account_pwd='" . md5($n_passwd) . "' "
	                  . "where account_lid='" . $phpgw_info["user"]["userid"] . "'");
   }

   if ($phpgw_info["server"]["auth_type"] == "ldap") {
      $ldap = ldap_connect($phpgw_info["server"]["ldap_host"]);

      if (! @ldap_bind($ldap, $phpgw_info["server"]["ldap_root_dn"], $phpgw_info["server"]["ldap_root_pw"])) {
         echo "<p><b>Error binding to LDAP server.  Check your config</b>";
         exit;
      }

      $entry["userpassword"] = $phpgw->common->encrypt_password($n_passwd);
      $entry["phpgw_lastpasswd_change"] = time();

      $dn = $phpgw_info["user"]["account_dn"];
      @ldap_modify($ldap, $dn, $entry);
   }

   // Since they are logged in, we need to change the password in sessions
   // in case they decied to check there mail.   
   $phpgw->db->query("update phpgw_sessions set session_pwd='" . $phpgw->common->encrypt($n_passwd)
 	               . "' where session_lid='" . $phpgw_info["user"]["userid"] . "'");

   // Update there last password change
   $phpgw->db->query("update accounts set account_lastpwd_change='" . time() . "' where account_id='"
   			    	. $phpgw_info["user"]["account_id"] . "'");

   Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/","cd=18"));
}
?>
