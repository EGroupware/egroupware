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
    $phpgw->common->phpgw_exit();
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
    $phpgw->common->phpgw_exit();
  }

  $o_passwd = $phpgw_info["user"]["passwd"];
  $passwd_changed = $phpgw->auth->change_password($o_passwd, $n_passwd);
  if (!$passwd_changed){
    // This need to be changed to show a different message based on the result
    Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/","cd=38"));
  }else{
    $phpgw_info["user"]["passwd"] = $phpgw->auth->change_password($o_passwd, $n_passwd);
    $phpgw->accounts->sync();
    Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/preferences/","cd=18"));
  }
}
?>
