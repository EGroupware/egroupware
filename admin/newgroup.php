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

  $phpgw->template->set_file(array("form"	=> "groups_form.tpl"));

  if ($submit) {
     $phpgw->db->query("select count(*) from groups where group_name='" . $n_group . "'");
     $phpgw->db->next_record();

     if ($phpgw->db->f(0) != 0) {
        $error = "<br>" . lang("Sorry, that group name has already been taken.");
     }
     if (! $n_group) {
        $error = "<br>" . lang("You must enter a group name.");
     }

     if (! $error) {
        $phpgw->db->lock(array("accounts","groups"));

        $apps = CreateObject('phpgwapi.applications');
        $app_string = $apps->add_group($group_id,$n_group_permissions);
        $apps->save_group($group_id);

        $phpgw->db->query("INSERT INTO groups (group_name) VALUES ('$n_group')");
        $phpgw->db->query("SELECT group_id FROM groups WHERE group_name='$n_group'");
        $phpgw->db->next_record();
        $group_con = $phpgw->db->f("group_id");
        $after_apps = explode(":",$apps);
        for ($i=1;$i<count($after_apps) - 1;$i++) {
          $new_apps[] = $after_apps[$i];
        }
        for ($i=0; $i<count($n_users);$i++) {
          $phpgw->db->query("SELECT account_groups FROM accounts WHERE account_id=".$n_users[$i]);
          $phpgw->db->next_record();
          $user_groups = $phpgw->db->f("account_groups") . ",$group_con:0,";

          $user_groups = ereg_replace(",,",",",$user_groups);
          $phpgw->db->query("UPDATE accounts SET account_groups='$user_groups' WHERE account_id=".$n_users[$i]);

          $pref = CreateObject('phpgwapi.preferences',intval($n_users[$i]));
          $t = $pref->get_preferences();

          $docommit = False;
          for ($j=0;$j<count($new_apps);$j++) {
            if($new_apps[$j]=="admin") {
              $check = "common";
            } else {
              $check = $new_apps[$j];
            }
            if (!$t["$check"]) {
              $phpgw->common->hook_single("add_def_pref", $new_apps[$j]);
              $docommit = True;
            }
          }
          if ($docommit) {
            $pref->commit();
          }
        }

        $sep = $phpgw->common->filesystem_separator();

        $basedir = $phpgw_info["server"]["files_dir"] . $sep . "groups" . $sep;

        $cd = 31;

        umask(000);
        if (! @mkdir ($basedir . $n_group, 0707)) $cd = 37;

        $phpgw->db->unlock();

        Header("Location: " . $phpgw->link("groups.php","cd=$cd"));
        $phpgw->common->phpgw_exit();
     }
  }

  if ($error) {
     $phpgw->common->phpgw_header();
     echo parse_navbar();
     $phpgw->template->set_var("error","<p><center>$error</center>");
  } else {
     $phpgw->template->set_var("error","");
  }

  $phpgw->template->set_var("form_action",$phpgw->link("newgroup.php"));
  $phpgw->template->set_var("hidden_vars","");
  $phpgw->template->set_var("lang_group_name",lang("New group name"));
  $phpgw->template->set_var("group_name_value","");

  $phpgw->db->query("select count(*) from accounts where account_status !='L'");
  $phpgw->db->next_record();

  if ($phpgw->db->f(0) < 5) {
     $phpgw->template->set_var("select_size",$phpgw->db->f(0));
  } else {
     $phpgw->template->set_var("select_size","5");
  }

  $phpgw->template->set_var("lang_include_user",lang("Select users for inclusion"));
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
  $phpgw->template->set_var("user_list",$user_list);

  $phpgw->template->set_var("lang_permissions",lang("Permissions this group has"));

  $i = 0;
  $sorted_apps = $phpgw_info["apps"];
  @asort($sorted_apps);
  @reset($sorted_apps);
  while ($permission = each($phpgw_info["apps"])) {
     if ($permission[1]["enabled"]) {
        $perm_display[$i][0] = $permission[0];
        $perm_display[$i][1] = $permission[1]["title"];
        $i++;
     }
  }

  $perm_html = "";
  for ($i=0;$i<200;) {     // The $i<200 is only used for a brake
     if (! $perm_display[$i][1]) break;
     $perm_html .= '<tr bgcolor="'.$phpgw_info["theme"]["row_on"].'"><td>' . lang($perm_display[$i][1]) . '</td>'
                 . '<td><input type="checkbox" name="n_group_permissions['
                 . $perm_display[$i][0] . ']" value="True"';
     if ($n_group_permissions[$perm_display[$i][0]] || $db_perms[$perm_display[$i][0]]) {
        $perm_html .= " checked";
     }
     $perm_html .= "></td>";
     $i++;

     if ($i == count($perm_display) && is_odd(count($perm_display))) {
        $perm_html .= '<td colspan="2">&nbsp;</td></tr>';
     }

     if (! $perm_display[$i][1]) break;
     $perm_html .= '<td>' . lang($perm_display[$i][1]) . '</td>'
                 . '<td><input type="checkbox" name="n_group_permissions['
                 . $perm_display[$i][0] . ']" value="True"';
     if ($n_group_permissions[$perm_display[$i][0]] || $db_perms[$perm_display[$i][0]]) {
        $perm_html .= " checked";
     }
     $perm_html .= "></td></tr>\n";
     $i++;
  }

  $phpgw->template->set_var("permissions_list",$perm_html);	
  $phpgw->template->set_var("lang_submit_button",lang("Create Group"));

  $phpgw->template->pparse("out","form");

  $phpgw->common->phpgw_footer();
?>
