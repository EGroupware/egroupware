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

  $phpgw_flags = array("noheader" => True, "nonavbar" => True);

  $phpgw_flags["currentapp"] = "preferences";
  include("../header.inc.php");
  if ($phpgw_info["user"]["permissions"]["anonymous"]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"));
     exit;
  }

if (! $submit) {
   $phpgw->common->header();
   $phpgw->common->navbar();

    ?>
   <form method="POST" acion="<?php echo $phpgw->link("changepassword.php"); ?>">
    <table border="0">
     <tr>
       <td>
        <?php echo lang_pref("enter your new password"); ?>
       </td>
       <td>
        <input type="password" name="n_passwd">
       </td>
     </tr>
     <tr>
       <td>
        <?php echo lang_pref("re-enter your password"); ?>
       </td>
       <td>
        <input type="password" name="n_passwd_2">
       </td>
     </tr>
     <tr>
       <td colspan="2">
        <input type="submit" name="submit" value="<?php echo lang_common("change"); ?>">
       </td>
     </tr>
    </table>
   </form>
   <br>
   <pre><?php echo lang_pref("note: This feature does *not* change your email password. This will "
            		   . "need to be done manually."); ?>
   </pre>
<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
} else {
   if ($n_passwd != $n_passwd_2)
      $error = lang_pref("the two passwords are not the same");

   if (! $n_passwd)
      $error = lang_pref("you must enter a password");

   if ($error) {
      phpgw_navbar();
      echo "<p><br>$error</p>";
      exit;
   }

   $phpgw->db->query("update accounts set passwd='" . md5($n_passwd) . "', "
	          . "lastpasswd_change='" . time() . "' where loginid='"
	          . $phpgw_info["user"]["userid"] . "'");

   // Since they are logged in, we need to change the password in sessions
   // in case they decied to check there mail.
   $phpgw->db->query("update sessions set passwd='" . addslashes($n_passwd)
	          . "' where loginid='" . $phpgw_info["user"]["userid"] . "'");

   Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
	. "/preferences/","cd=18"));
}
