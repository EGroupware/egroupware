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
  if ($submit) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }
  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");
  
  if (! $group_id) {
     Header("Location: " . $phpgw->link("groups.php"));
  }

  $phpgw->template->set_file(array("form"	=> "groups_form.tpl"));

  if ($submit) {
     $phpgw->db->query("select group_name from groups where group_id=$group_id");
     $phpgw->db->next_record();

     $old_group_name = $phpgw->db->f("group_name");

     $phpgw->db->query("select count(*) from groups where group_name='" . $n_group . "'");
     $phpgw->db->next_record();

     if ($phpgw->db->f(0) != 0 && $n_group != $old_group_name) {
        $error = lang("Sorry, that group name has already been taking.");
     }

     if (! $error) {
        $phpgw->db->lock(array("accounts","groups"));

        $phpgw->accounts->add_app($n_group_permissions);        
        $phpgw->db->query("update groups set group_name='$n_group', group_apps='"
				    . $phpgw->accounts->add_app("",True)
				    . "' where group_id=$group_id");
        $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='$n_group'");
	$phpgw->db->next_record();
        $group_con = $phpgw->db->f("group_id");

        for ($i=0; $i<count($n_users);$i++) {
           $phpgw->db->query("SELECT account_groups FROM accounts WHERE account_id=".$n_users[$i]);
	   $phpgw->db->next_record();
           $user_groups = $phpgw->db->f("account_groups") . ",$group_con:0,";

           $user_groups = ereg_replace(",,",",",$user_groups);
           $phpgw->db->query("UPDATE accounts SET account_groups='$user_groups' WHERE account_id=".$n_users[$i]);
        }

        $sep = $phpgw->common->filesystem_separator();


        if ($old_group_name <> $n_group) {
	   $basedir = $phpgw_info["server"]["files_dir"] . $sep . "groups" . $sep;
           if (! @rename($basedir . $old_group_name, $basedir . $n_group)) {
	      $cd = 39;
           } else {
              $cd = 33;
           }
        } else {
           $cd = 33;
        }

        $phpgw->db->unlock();

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
        exit;
     }
  }

  if ($error) {
     $phpgw->common->phpgw_header();
     $phpgw->common->navbar();
     $phpgw->template->set_var("error","<p><center>$error</center>");
  } else {
     $phpgw->template->set_var("error","");
  }

  if ($submit) {
     $phpgw->template->set_var("group_name_value",$n_group_name);

     for ($i=0; $i<count($n_users); $i++) {
        $selected_users[$n_user[$i]] = " selected";
     }

     for ($i=0; $i<count($n_group_permissions); $i++) {
        $selected_permissions[$n_group_permissions[$i]] = " selected";
     }
  } else {
     $phpgw->db->query("select group_name from groups where group_id=$group_id");
     $phpgw->db->next_record();

     $phpgw->template->set_var("group_name_value",$phpgw->db->f("group_name"));

     $phpgw->db->query("select account_id from accounts where account_groups like '%,$group_id:%'");

     while ($phpgw->db->next_record()) {
        $selected_users[$phpgw->db->f("account_id")] = " selected";
     }

     $gp = $phpgw->accounts->read_group_apps($group_id);

     for ($i=0; $i<count($gp); $i++) {
        $selected_permissions[$gp[$i]] = " selected";
     }
  }

  $phpgw->db->query("select * from groups where group_id=$group_id");
  $phpgw->db->next_record();

  $phpgw->template->set_var("form_action",$phpgw->link("editgroup.php"));
  $phpgw->template->set_var("hidden_vars","<input type=\"hidden\" name=\"group_id\" value=\"" . $group_id . "\">");

  $phpgw->template->set_var("lang_group_name",lang("group name"));
  $phpgw->template->set_var("group_name_value",$phpgw->db->f("group_name"));

  $phpgw->db->query("select count(*) from accounts where account_status !='L'");
  $phpgw->db->next_record();

  if ($phpgw->db->f(0) < 5) {
     $phpgw->template->set_var("select_size",$phpgw->db->f(0));
  } else {
     $phpgw->template->set_var("select_size","5");
  }

  $phpgw->template->set_var("lang_include_user",lang("Select users for inclusion"));
  $phpgw->db->query("SELECT account_id,account_firstname,account_lastname,account_lid FROM accounts where "
	  	        . "account_status != 'L' ORDER BY account_lastname,account_firstname,account_lid asc");
  while ($phpgw->db->next_record()) {
     $user_list .= "<option value=\"" . $phpgw->db->f("account_id") . "\""
    	            . $selected_users[$phpgw->db->f("account_id")] . ">"
	            . $phpgw->common->display_fullname($phpgw->db->f("account_lid"),
						       $phpgw->db->f("account_firstname"),
						       $phpgw->db->f("account_lastname")) . "</option>";
  }
  $phpgw->template->set_var("user_list",$user_list);

  $phpgw->template->set_var("lang_permissions",lang("Permissions this group has"));
  while ($permission = each($phpgw_info["apps"])) {
     if ($permission[1]["enabled"]) {
        $permissions_list .= "<option value=\"" . $permission[0] . "\""
	   			   . $selected_permissions[$permission[0]] . ">"
	   			   . $permission[1]["title"] . "</option>";
     }
  }
  $phpgw->template->set_var("permissions_list",$permissions_list);
  $phpgw->template->set_var("lang_submit_button",lang("submit changes"));

  $phpgw->template->pparse("out","form");

  $phpgw->common->phpgw_footer();
?>
