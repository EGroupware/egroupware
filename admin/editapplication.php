<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($submit) {
     $phpgw_flags = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");
  if ($submit) {
     if (! $n_app_name || ! $n_app_title) {
        $error = lang_admin("You must enter an application name and title.");
     } else {
        $phpgw->db->query("update applications set app_name='" . addslashes($n_app_name) . "',"
			    . "app_title='" . addslashes($n_app_title) . "', app_enabled='"
			    . "$n_app_enabled' where app_name='$old_app_name'");

        Header("Location: " . $phpgw->link("applications.php"));
        exit;
     }
  }
  $phpgw->db->query("select * from applications where app_name='$app_name'");
  $phpgw->db->next_record();

  if ($error) {
     $phpgw->common->header();
     $phpgw->common->navbar();
  }
 
  echo "<p>" . lang_admin("Edit application") . "<hr>";
  if ($error) {
     echo "<p><center>$error</center><br>";
  }

  ?>       
       <form action="editapplication.php">
        <input type="hidden" name="old_app_name" value="<?php echo $phpgw->db->f("app_name"); ?>">
        <?php echo $phpgw->session->hidden_var(); ?>

        <table border="0" width="35%" align="center">
         <tr>
          <td><?php echo lang_admin("application name"); ?></td>
          <td><input name="n_app_name" value="<?php echo $phpgw->db->f("app_name"); ?>"></td>
         </tr>

         <tr>
          <td><?php echo lang_admin("application title"); ?></td>
          <td><input name="n_app_title" value="<?php echo $phpgw->db->f("app_title"); ?>"></td>
         </tr>

         <tr>
          <td><?php echo lang_admin("enabled"); ?></td>
          <td><input type="checkbox" name="n_app_enabled" value="1"<?php echo ($phpgw->db->f("app_enabled") == 1?" checked":""); ?>></td>
         </tr>

         <tr>
          <td colspan="2" align="center"><input type="submit" name="submit" value="<?php echo lang_common("Change"); ?>"></td>
         </tr>
	</table>
       </form>
     <?php
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

