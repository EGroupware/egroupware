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
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");

  $t = new Template($phpgw_info["server"]["template_dir"]);
  $t->set_file(array("form"	=> "groups_form.tpl"));

  if ($submit) {
     $phpgw->db->query("select count(*) from groups where group_name='" . $n_group . "'");
     $phpgw->db->next_record();

     if ($phpgw->db->f(0) != 0) {
        $error = "<br>" . lang("Sorry, that group name has already been taking.");
     }
     if (! $n_group) {
        $error = "<br>" . lang("You must enter a group name.");
     }

     if (! $error) {
        $phpgw->db->lock(array("accounts","groups"));

        $phpgw->db->query("INSERT INTO groups (group_name,group_apps) VALUES "
				. "('$n_group','"
				. $phpgw->accounts->array_to_string("none",$n_group_permissions) . "') ");
        $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='$n_group'");
        $phpgw->db->next_record();
        $group_con = $phpgw->db->f("group_id");

        for ($i=0; $i<count($n_users);$i++) {
           $phpgw->db->query("SELECT account_groups FROM accounts WHERE account_id=".$n_users[$i]);
	      $phpgw->db->next_record();
           $user_groups = $phpgw->db->f("account_groups") . ",$group_con,";

           $user_groups = ereg_replace(",,",",",$user_groups);
           $phpgw->db->query("UPDATE accounts SET account_groups='$user_groups' WHERE account_id=".$n_users[$i]);
        }

        $sep = $phpgw->common->filesystem_separator();

        $basedir = $phpgw_info["server"]["files_dir"] . $sep . "groups" . $sep;

        $cd = 31;

        if (! @mkdir ($basedir . $n_group, 0707)) $cd = 37;

        $phpgw->db->unlock();

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
        exit;
     }
  }

  if ($error) {
     $phpgw->common->phpgw_header();
     $phpgw->common->navbar();
     $t->set_var("error","<p><center>$error</center>");
  } else {
     $t->set_var("error","");
  }

  $t->set_var("form_action",$phpgw->link("newgroup.php"));
  $t->set_var("hidden_vars","");
  $t->set_var("lang_group_name",lang("New group name"));
  $t->set_var("group_name_value","");

  $phpgw->db->query("select count(*) from accounts where account_status !='L'");
  $phpgw->db->next_record();

  if ($phpgw->db->f(0) < 5) {
     $t->set_var("select_size",$phpgw->db->f(0));
  } else {
     $t->set_var("select_size","5");
  }

  $t->set_var("lang_include_user",lang("Select users for inclusion"));
  for ($i=0; $i<count($n_users); $i++) {
     $selected_users[$n_users[$i]] = " selected";
  }

  $phpgw->db->query("SELECT account_id,account_firstname,account_lastname,account_lid FROM accounts where "
	  	        . "account_status != 'L' ORDER BY account_lastname,account_firstname,account_lid asc");
  while ($phpgw->db->next_record()) {
     $user_list .= "<option value=\"" . $phpgw->db->f("account_id") . "\""
    	         . $selected_users[$phpgw->db->f("account_id")] . ">"
	         . $phpgw->common->display_fullname($phpgw->db->f("account_lid"),
								   	    $phpgw->db->f("account_firstname"),
								   	    $phpgw->db->f("account_lastname")) . "</option>";
  }
  $t->set_var("user_list",$user_list);

  $t->set_var("lang_permissions",lang("Select permissions this group will have"));
  for ($i=0; $i<count($n_group_permissions); $i++) {
     $selected_permissions[$n_group_permissions[$i]] = " selected";
  }

  while ($permission = each($phpgw_info["apps"])) {
     if ($permission[1]["enabled"]) {
        $permissions_list .= "<option value=\"" . $permission[0] . "\""
	   			   . $selected_permissions[$permission[0]] . ">"
	   			   . $permission[1]["title"] . "</option>";
     }
  }
  $t->set_var("permissions_list",$permissions_list);
  $t->set_var("lang_submit_button",lang("Create Group"));

  $t->pparse("out","form");

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
