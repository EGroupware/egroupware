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

  $phpgw_flags = array("noheader" => True, "nonavbar" => True);

  if (! $group_id)
     Header("Location: " . $phpgw->link("groups.php"));

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");

  if ((($group_id) && ($confirm)) || $removeusers) {
     if ($removeusers) {
        $phpgw->db->query("select con,groups from accounts where groups like '%$group_id%'");
        while ($phpgw->db->next_record()) {
          $groups[$phpgw->db->f("con")] = $phpgw->db->f("groups");
        }

        while ($user = each($groups)) {
          $user_[1] = ereg_replace(",$group_id,",",",$user[1]);
          if ($user_[1] == ",") {
             $user_[1] = "";
          }
          $phpgw->db->query("update accounts set groups='$user_[1]' where con='$user[0]'");
        }
        $confirm = True;
     }

     $phpgw->db->query("select group_name from groups where group_id='$group_id'");
     $phpgw->db->next_record();

     $group_name = $phpgw->db->f("group_name");

     $phpgw->db->query("select con,loginid from accounts where groups like '%$group_id%'");
     if ($phpgw->db->num_rows()) {
        $phpgw->common->header();
        $phpgw->common->navbar();

        echo '<p><center>';
	   echo lang_admin("Sorry, the follow users are still a member of the group x",$group_name)
	      . '<br>' . lang_admin("They must be removed before you can continue")
	      . '</td></tr>';

        echo '<table border="0"><tr><td>';

        while ($phpgw->db->next_record()) {
          echo '<tr><td><a href="' . $phpgw->link("editaccount.php","con=" . $phpgw->db->f("con")) . '">' . $phpgw->db->f("loginid") . '</a></tr></td>';
        }
        echo "</table></center>";
        echo "<a href=\"" . $phpgw->link("deletegroup.php","group_id=" . $group_id . "&removeusers=True")
	   . "\">" . lang_admin("Remove all users from this group") . "</a>";
        exit;
     }

     if ($confirm) {
        $phpgw->db->query("select group_name from groups where group_id='$group_id'");
        $phpgw->db->next_record();
        $group_name = $phpgw->db->f("group_name");

        $phpgw->db->query("delete from groups where group_id='$group_id'");

        $sep = $phpgw->common->filesystem_sepeartor();

        $basedir = $phpgw_info["server"]["files_dir"] . $sep . "groups" . $sep;

        if (! @rmdir($basedir . $group_name)) {
   	   $cd = 38;
        } else {
           $cd = 32;
        }

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
     }
  }

  $phpgw->common->header();
  $phpgw->common->navbar();
  ?>
     <center>
      <table border=0 with=65%>
       <tr colspan=2>
        <td align=center>
         <?php echo lang_admin("Are you sure you want to delete this group ?"); ?>
        <td>
       </tr>
       <tr>
         <td>
           <a href="<?php echo $phpgw->link("groups.php") . "\">" . lang_common("No") . "</a>"; ?>
         </td>
         <td>
           <a href="<?php echo $phpgw->link("deletegroup.php","group_id=$group_id&confirm=true") . "\">" . lang_common("Yes") . "</a>"; ?>
         </td>
       </tr>
      </table>
     </center>
     <?
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

