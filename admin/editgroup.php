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

  $t = new Template($phpgw_info["server"]["template_dir"]);
  $t->set_file(array("form"	=> "groups_form.tpl"));

  if ($submit) {
     $phpgw->db->query("select group_name from groups where group_id='$group_id'");
     $phpgw->db->next_record();

     $old_group_name = $phpgw->db->f("group_name");

     $phpgw->db->query("select count(*) from groups where group_name='" . $n_group . "'");
     $phpgw->db->next_record();

     if ($phpgw->db->f(0) != 0 && $n_group != $old_group_name) {
        $error = lang_admin("Sorry, that group name has already been taking.");
     }

     if (! $error) {
        $phpgw->db->lock(array("accounts","groups"));

        $phpgw->accounts->add_app($n_group_permissions);        
        $phpgw->db->query("update groups set group_name='$n_group', group_apps='"
				    . $phpgw->accounts->add_app("",True)
				    . "' where group_id='$group_id'");
        $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='$n_group'");
	   $phpgw->db->next_record();
        $group_con = $phpgw->db->f("group_id");

        for ($i=0; $i<count($n_users);$i++) {
           $phpgw->db->query("SELECT groups FROM accounts WHERE con=".$n_users[$i]);
	      $phpgw->db->next_record();
           $user_groups = $phpgw->db->f("groups") . ",$group_con:0,";

           $user_groups = ereg_replace(",,",",",$user_groups);
           $phpgw->db->query("UPDATE accounts SET groups='$user_groups' WHERE con='" . $n_users[$i] ."'");
        }

        $sep = $phpgw->common->filesystem_separator();


        if ($old_group_name <> $n_group) {
           $basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep . "groups" . $sep;

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
     $phpgw->common->header();
     $phpgw->common->navbar();
     $t->set_var("error","<p><center>$error</center>");
  } else {
     $t->set_var("error","");
  }

  if ($submit) {
     $t->set_var("group_name_value",$n_group_name);

     for ($i=0; $i<count($n_users); $i++) {
        $selected_users[$n_user[$i]] = " selected";
     }

     for ($i=0; $i<count($n_group_permissions); $i++) {
        $selected_permissions[$n_group_permissions[$i]] = " selected";
     }
  } else {
     $phpgw->db->query("select group_name from groups where group_id='$group_id'");
     $phpgw->db->next_record();

     $t->set_var("group_name_value",$phpgw->db->f("group_name"));

     $phpgw->db->query("select con from accounts where groups like '%,$group_id:%'");

     while ($phpgw->db->next_record()) {
        $selected_users[$phpgw->db->f("con")] = " selected";
     }

     $gp = $phpgw->accounts->read_group_apps($group_id);

     for ($i=0; $i<count($gp); $i++) {
        $selected_permissions[$gp[$i]] = " selected";
     }
  }

  $phpgw->db->query("select * from groups where group_id='$group_id'");
  $phpgw->db->next_record();

  $t->set_var("form_action","editgroup.php");
  $t->set_var("hidden_vars",$phpgw->session->hidden_var()
				  . '<input type="hidden" name="group_id" value="' . $group_id . '">');

  $t->set_var("lang_group_name",lang_admin("group name"));
  $t->set_var("group_name_value",$phpgw->db->f("group_name"));

  $phpgw->db->query("select count(*) from accounts where status !='L'");
  $phpgw->db->next_record();

  if ($phpgw->db->f(0) < 5) {
     $t->set_var("select_size",$phpgw->db->f(0));
  } else {
     $t->set_var("select_size","5");
  }

  $t->set_var("lang_include_user",lang_admin("Select users for inclusion"));
  $phpgw->db->query("SELECT con,firstname,lastname, loginid FROM accounts where "
	  	  . "status != 'L' ORDER BY lastname,firstname,loginid asc");
  while ($phpgw->db->next_record()) {
     $user_list .= "<option value=\"" . $phpgw->db->f("con") . "\""
    	            . $selected_users[$phpgw->db->f("con")] . ">"
	            . $phpgw->common->display_fullname($phpgw->db->f("loginid"),
								   		  $phpgw->db->f("firstname"),
								   		  $phpgw->db->f("lastname")) . "</option>";
  }
  $t->set_var("user_list",$user_list);

  $t->set_var("lang_permissions",lang_admin("Permissions this group has"));
  while ($permission = each($phpgw_info["apps"])) {
     if ($permission[1]["enabled"]) {
        $permissions_list .= "<option value=\"" . $permission[0] . "\""
	   			   . $selected_permissions[$permission[0]] . ">"
	   			   . $permission[1]["title"] . "</option>";
     }
  }
  $t->set_var("permissions_list",$permissions_list);
  $t->set_var("lang_submit_button",lang_admin("submit changes"));

  $t->pparse("out","form");

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
