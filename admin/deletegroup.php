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

  $phpgw_info = array();
  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "admin");

  if (! $group_id)
     Header("Location: " . $phpgw->link("groups.php"));

  include("../header.inc.php");

  if ((($group_id) && ($confirm)) || $removeusers) {
     if ($removeusers) {
        $phpgw->db->query("select account_id,account_groups from accounts where account_groups like '%$group_id%'");
        while ($phpgw->db->next_record()) {
          $groups[$phpgw->db->f("account_id")] = $phpgw->db->f("account_groups");
        }

        while ($user = each($groups)) {
          $user_[1] = ereg_replace(",$group_id:[0-9]+,",",",$user[1]);
          if ($user_[1] == ",") {
             $user_[1] = "";
          }
          $phpgw->db->query("update accounts set account_groups='$user_[1]' where account_id=$user[0]");
        }
        $confirm = True;
     }

     $phpgw->db->query("select group_name from groups where group_id=$group_id");
     $phpgw->db->next_record();

     $group_name = $phpgw->db->f("group_name");

     $phpgw->db->query("select count(*) from accounts where account_groups like '%$group_id%'");
     $phpgw->db->next_record();
     if ($phpgw->db->f(0) != 0) {
        $phpgw->common->phpgw_header();
        $phpgw->common->navbar();

        echo '<p><center>';
	   echo lang("Sorry, the follow users are still a member of the group x",$group_name)
	      . '<br>' . lang("They must be removed before you can continue")
	      . '</td></tr>';

        echo '<table border="0"><tr><td>';

        $phpgw->db->query("select account_id,account_lid from accounts where account_groups like '%$group_id%'");
        while ($phpgw->db->next_record()) {
          echo '<tr><td><a href="' . $phpgw->link("editaccount.php","account_=" . $phpgw->db->f("account_id")) . '">' . $phpgw->db->f("loginid") . '</a></tr></td>';
        }
        echo "</table></center>";
        echo "<a href=\"" . $phpgw->link("deletegroup.php","group_id=" . $group_id . "&removeusers=True")
	   . "\">" . lang("Remove all users from this group") . "</a>";
        exit;
     }

     if ($confirm) {
        $phpgw->db->query("select group_name from groups where group_id=$group_id");
        $phpgw->db->next_record();
        $group_name = $phpgw->db->f("group_name");

        $phpgw->db->query("delete from groups where group_id=$group_id");

        $sep = $phpgw->common->filesystem_separator();

        $basedir = $phpgw_info["server"]["files_dir"] . $sep . "groups" . $sep;

        if (! @rmdir($basedir . $group_name)) {
   	   $cd = 38;
        } else {
           $cd = 32;
        }

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
     }
  }

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();
  ?>
     <center>
      <table border=0 with=65%>
       <tr colspan=2>
        <td align=center>
         <?php echo lang("Are you sure you want to delete this group ?"); ?>
        <td>
       </tr>
       <tr>
         <td>
           <a href="<?php echo $phpgw->link("groups.php") . "\">" . lang("No") . "</a>"; ?>
         </td>
         <td>
           <a href="<?php echo $phpgw->link("deletegroup.php","group_id=$group_id&confirm=true") . "\">" . lang("Yes") . "</a>"; ?>
         </td>
       </tr>
      </table>
     </center>
     <?php
	$phpgw->common->phpgw_footer();
?>
