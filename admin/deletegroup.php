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

  if (! $group_id) {
     Header("Location: " . $phpgw->link("groups.php"));
  }
  include("../header.inc.php");
  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
  $p->set_file(array("body" => "delete_common.tpl"));

  if ((($group_id) && ($confirm)) || $removeusers) {
     if ($removeusers) {
        $old_group_list = $phpgw->acl->get_ids_for_location("$group_id",1,"phpgw_group","u");
        @reset($old_group_list);
        while($old_group_list && $id = each($old_group_list)) {
          $phpgw->acl->delete("phpgw_group","$group_id",intval($id[1]),"u");
        }
     }

     $phpgw->db->query("select group_name from groups where group_id=$group_id",__LINE__,__FILE__);
     $phpgw->db->next_record();

     $group_name = $phpgw->db->f("group_name");

     $old_group_list = $phpgw->acl->get_ids_for_location("$group_id",1,"phpgw_group","u");
     if ($old_group_list) {
        $phpgw->common->phpgw_header();
        echo parse_navbar();

        echo '<p><center>';
	    echo lang("Sorry, the follow users are still a member of the group x",$group_name)
	      . '<br>' . lang("They must be removed before you can continue")
	      . '</td></tr>';

        echo '<table border="0"><tr><td>';

        while (list(,$id) = each($old_group_list)) {
          echo '<tr><td><a href="' . $phpgw->link("editaccount.php","account_=" . $id) . '">' . $phpgw->common->grab_owner_name($id) . '</a></tr></td>';
        }
        echo "</table></center>";
        echo "<a href=\"" . $phpgw->link("deletegroup.php","group_id=" . $group_id . "&removeusers=True")
	   . "\">" . lang("Remove all users from this group") . "</a>";
        $phpgw->common->phpgw_exit();
     }

     if ($confirm) {
        $phpgw->db->query("select group_name from groups where group_id=$group_id",__LINE__,__FILE__);
        $phpgw->db->next_record();
        $group_name = $phpgw->db->f("group_name");

        $phpgw->db->query("delete from groups where group_id=$group_id",__LINE__,__FILE__);

        $sep = $phpgw->common->filesystem_separator();

        $basedir = $phpgw_info["server"]["files_dir"] . $sep . "groups" . $sep;

        if (! @rmdir($basedir . $group_name)) {
   	   $cd = 38;
        } else {
           $cd = 32;
        }

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
        $phpgw->common->phpgw_exit();
     }
  } else {

    $phpgw->common->phpgw_header();
    echo parse_navbar();

    $p->set_var("message_display",lang("Are you sure you want to delete this group ?"));
    $p->parse("messages","message_row");
    $p->set_var("yes",'<a href="' . $phpgw->link("deletegroup.php","group_id=$group_id&confirm=true") . '">' . lang("Yes") . '</a>');
    $p->set_var("no",'<a href="' . $phpgw->link("groups.php") . '">' . lang("No") . '</a>');

    $p->pparse("out","body");

    $phpgw->common->phpgw_footer();
  }
?>
