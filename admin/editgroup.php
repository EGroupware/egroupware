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

  if (! $group_id)
     Header("Location: " . $phpgw->link("groups.php"));

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");
  if (! $submit) {
     $phpgw->db->query("select group_name from groups where group_id='$group_id'");
     $phpgw->db->next_record();
     ?>
       <form action="editgroup.php">
        <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
        <?php echo $phpgw->session->hidden_var(); ?>

        <center>
         <p><?php echo lang_admin("Group name"); ?> <input name="n_group" value="<?php echo $phpgw->db->f("group_name"); ?>">
         <br><input type="submit" name="submit" value="<?php echo lang_common("Change"); ?>">
	</center>
       </form>
     <?php
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

  } else {
     $phpgw->db->query("select group_name from groups where group_id='$group_id'");
     $phpgw->db->next_record();

     $group_name = $phpgw->db->f("group_name");

     $phpgw->db->query("update groups set group_name='" . addslashes($n_group)
		    . "' where group_id='$group_id'");

     $sep = $phpgw->common->filesystem_sepeartor();

     $basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep . "groups"
	       . $sep;

     if (! rename($basedir . $group_name,$basedir . $n_group)) {
	$cd = 39;
     } else {
        $cd = 33;
     }

     Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
  }

