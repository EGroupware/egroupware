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
  if (! $submit) {
     ?>
       <form action="creategroup.php">
        <?php echo $phpgw->session->hidden_var(); ?>
	<center>
         <p><?php echo lang_admin("New group name"); ?> &nbsp;<input name="n_group">
	 <br><?php echo lang_admin("Select users for inclusion"); ?> &nbsp;
	 <?php 
	    echo "<select name=\"n_users[]\" multiple size=10>\n";

               $phpgw->db->query("SELECT con, firstname, lastname FROM accounts ORDER BY lastname asc");
	       while ($phpgw->db->next_record()) {
                 echo "<option value=\"" . $phpgw->db->f("con") . "\">"
		    . $phpgw->db->f("firstname") . " " . $phpgw->db->f("lastname") . "</option>";
	       }
               echo "</select>\n";
	 ?>
 	 <br><input type="submit" name="submit" value="<?php echo lang_admin("Create Group"); ?>">
	</center>
       </form>
     <?php
    include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
  } else {

     $phpgw->db->lock(array("accounts","groups"));

     $phpgw->db->query("INSERT INTO groups (group_name) VALUES ('$n_group') ");
     $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='$n_group'");
     $groups_con = $phpgw->db->f("group_id");

     for($i=0; $i<count($n_users);$i++)
     {
       $phpgw->db->query("SELECT groups FROM accounts WHERE con=".$n_users[$i]);
       $user_groups = $phpgw->db->f("groups") . ",$group_con,";
       $user_groups = ereg_replace(",,",",",$user_groups);
       $phpgw->db->query("UPDATE accounts SET groups='$user_groups' WHERE con=".$n_users[$i]);
     }

     $basedir = $phpgw_info["server"]["server_root"] 
	       . $phpgw_info["server"]["dir_separator"]
	       . "filemanager"
	       . $phpgw_info["server"]["dir_separator"]
	       . "groups"
	       . $phpgw_info["server"]["dir_separator"];

     $cd = 31;

     if (!mkdir ($basedir . $n_group, 0707)) $cd = 37;

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
  }
