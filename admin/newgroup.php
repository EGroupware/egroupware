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
     $phpgw->db->query("select count(*) from groups where group_name='" . $n_group . "'");
     $phpgw->db->next_record();

     if ($phpgw->db->f(0) != 0) {
        $error = "<p><center>" . lang_admin("Sorry, that group name has already been taking.") . "</center>";
     }

     if (! $error) {
        $phpgw->db->lock(array("accounts","groups"));

        $phpgw->db->query("INSERT INTO groups (group_name) VALUES ('$n_group') ");
        $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='$n_group'");
        $groups_con = $phpgw->db->f("group_id");

        for ($i=0; $i<count($n_users);$i++) {
           $phpgw->db->query("SELECT groups FROM accounts WHERE con=".$n_users[$i]);
           $user_groups = $phpgw->db->f("groups") . ",$group_con,";
           $user_groups = ereg_replace(",,",",",$user_groups);
           $phpgw->db->query("UPDATE accounts SET groups='$user_groups' WHERE con="
			       . $n_users[$i]);
        }

        $sep = $phpgw->common->filesystem_sepeartor();

        $basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep
	         . "groups" . $sep;

        $cd = 31;

        if (! mkdir ($basedir . $n_group, 0707)) $cd = 37;

        $phpgw->db->unlock();

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
        exit;
     }
  }

  if ($error) {
     $phpgw->common->header();
     $phpgw->common->navbar();
     echo "<p><center>$error</center>";
  }
  ?>
   <center>
   <table border="0" width="50%">
   <form action="newgroup.php">
    <?php
      echo $phpgw->session->hidden_var() . "<tr><td>" . lang_admin("New group name")
	 . '</td> <td><input name="n_group" value="' . $n_group . '"></td></tr>';

      $phpgw->db->query("select count(*) from accounts where status !='L'");
      $phpgw->db->next_record();

      if ($phpgw->db->f(0) < 5) {
         $size = $phpgw->db->f(0);
      } else {
         $size = 5;
      }

      echo "<tr><td>" . lang_admin("Select users for inclusion") . "</td> <td>"
        .  "<select name=\"n_users[]\" multiple size=$size>\n";

      for ($i=0; $i<count($n_users); $i++) {
         $selected_users[$n_users[$i]] = " selected";
      }

      $phpgw->db->query("SELECT con,firstname,lastname, loginid FROM accounts where "
			  . "status != 'L' ORDER BY lastname,firstname,loginid asc");
      while ($phpgw->db->next_record()) {
         echo "<option value=\"" . $phpgw->db->f("con") . "\""
	    . $selected_users[$phpgw->db->f("con")] . ">"
	    . $phpgw->common->display_fullname($phpgw->db->f("loginid"),
									   $phpgw->db->f("firstname"),
									   $phpgw->db->f("lastname")) . "</option>";
      }
      echo "</select></td></tr>\n";

      ?>
       <tr>
        <td colspan="2" align="center">
         <input type="submit" name="submit" value="<?php echo lang_admin("Create Group"); ?>">
        </td>
       </tr>
      </center>
     </form>
    </table>
   <?php
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
