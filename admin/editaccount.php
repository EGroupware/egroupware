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
  $phpgw_info["flags"] = array("noheader" => True, 
                "nonavbar" => True, 
                "currentapp" => "admin",
                "parent_page" => "accounts.php");
  include("../header.inc.php");
  include($phpgw_info["server"]["app_inc"]."/accounts_".$phpgw_info["server"]["account_repository"].".inc.php");

  function is_odd($n)
  {
     $ln = substr($n,-1);
     if ($ln == 1 || $ln == 3 || $ln == 5 || $ln == 7 || $ln == 9) {
        return True;
     } else {
        return False;
     }
  }

  if (! $account_id) {
     Header("Location: " . $phpgw->link("accounts.php"));
  }

  if ($submit) {
     $totalerrors = 0;

     if ($phpgw_info["server"]["account_repository"] == "ldap" && ! $allow_long_loginids) {
        if (strlen($n_loginid) > 8) {
           $error[$totalerrors++] = lang("The loginid can not be more then 8 characters");
        }
     }
    
     if ($old_loginid != $n_loginid) {
        if (account_exsists($n_loginid)) {
           $error[$totalerrors++] = lang("That loginid has already been taken");
        }
//        $c_loginid = $n_loginid;
//        $n_loginid = $old_loginid;
     }
  
     if ($n_passwd || $n_passwd_2) {
        if ($n_passwd != $n_passwd_2) {
           $error[$totalerrors++] = lang("The two passwords are not the same");
        }
        if (! $n_passwd){
           $error[$totalerrors++] = lang("You must enter a password");
        }
     }

     if (!count($new_permissions) || !count($n_groups)) {
        $error[$totalerrors++] = "<br>" . lang("You must add at least 1 permission or group to this account");
     }
     
     if (! $totalerrors) {
       $phpgw->db->lock(array('accounts','preferences','phpgw_sessions','phpgw_acl','applications'));
       $phpgw->db->query("SELECT account_id FROM accounts WHERE account_lid='" . $old_loginid . "'",__LINE__,__FILE__);
       $phpgw->db->next_record();
       $account_id = intval($phpgw->db->f("account_id"));

       $apps = CreateObject('phpgwapi.applications',array(intval($account_id),'u'));
       $apps->read_installed_apps();
       $apps_before = $apps->read_account_specific();

       // Read Old Group ID's
       $old_groups = $phpgw->accounts->read_groups($account_id);
       // Read Old Group Apps
       if ($old_groups) {
         $apps->account_type = 'g';
         reset($old_groups);
         while($groups = each($old_groups)) {
           $apps->account_id = $groups[0];
           $old_app_groups = $apps->read_account_specific();
           @reset($old_app_groups);
           while($old_group_app = each($old_app_groups)) {
             if(!$apps_before[$old_group_app[0]]) {
               $apps_before[$old_group_app[0]] = $old_app_groups[$old_group_app[0]];
             }
           }
           // delete old groups user was associated to
           $phpgw->acl->delete('phpgw_group',$groups[0],$account_id,'u');
         }
       }

       $apps->account_type = 'u';
       $apps->account_id = $account_id;
       $apps->account_apps = Array(Array());
       while($app = each($new_permissions)) {
         if($app[1]) {
           $apps->add_app($app[0]);
           if(!$apps_before[$app[0]]) {
             $apps_after[] = $app[0];
           }
         }
       }
       $apps->save_apps();

       $cd = account_edit(array('loginid'        => $n_loginid,        'firstname'   => $n_firstname,
                                'lastname'       => $n_lastname,       'passwd'      => $n_passwd,
                                'account_status' => $n_account_status, 'old_loginid' => $old_loginid,
                                'account_id'     => rawurldecode($account_id)));

       // If the user is logged in, it will force a refresh of the session_info
       //$phpgw->db->query("update phpgw_sessions set session_info='' where session_lid='$new_loginid@" . $phpgw_info["user"]["domain"] . "'",__LINE__,__FILE__);

       // Add new groups user is associated to
       for($i=0;$i<count($n_groups);$i++) {
         $phpgw->acl->add('phpgw_group',$n_groups[$i],$account_id,'u',1);
       }
       
       // The following sets any default preferences needed for new applications..
       // This is smart enough to know if previous preferences were selected, use them.
       
       $pref = CreateObject('phpgwapi.preferences',intval($account_id));
       $t = $pref->get_preferences();
         
       $docommit = False;
       $after_apps = explode(':',$apps_after);
       for($i=1;$i<count($after_apps) - 1;$i++) {
         if($after_apps[$i]=='admin') {
           $check = 'common';
         } else {
           $check = $after_apps[$i];
		 }
         if (!$t["$check"]) {
           $phpgw->common->hook_single('add_def_pref', $after_apps[$i]);
           $docommit = True;
         }
       }
       
       if ($docommit) {
		 $pref->commit();
	   }

       $apps->account_apps = Array(Array());
       $apps_after = Array(Array());

       // Read new Group ID's
       $new_groups = $phpgw->accounts->read_groups($account_id);
       // Read new Group Apps
       if ($new_groups) {
         $apps->account_type = 'g';
         reset($new_groups);
         while($groups = each($new_groups)) {
           $apps->account_id = intval($groups[0]);
           $new_app_groups = $apps->read_account_specific();
           @reset($new_app_groups);
           while($new_group_app = each($new_app_groups)) {
             if(!$apps_after[$new_group_app[0]]) {
               $apps_after[$new_group_app[0]] = $new_app_groups[$new_group_app[0]];
             }
           }
         }
       }

       $apps->account_type = 'u';
       $apps->account_id = $account_id;
       $new_app_user = $apps->read_account_specific();
       while($new_user_app = each($new_app_user)) {
         if(!$apps_after[$new_user_app[0]]) {
           $apps_after[$new_user_app[0]] = $new_app_user[$new_user_app[0]];
         }
       }

       // start including other admin tools
       while($app = each($apps_after))
       {
         $phpgw->common->hook_single('update_user_data', $app[0]);
       }       

       $phpgw->db->unlock();
       
       Header('Location: ' . $phpgw->link('accounts.php', 'cd='.$cd));
       $phpgw->common->phpgw_exit();
     }

  }                    // if $submit

  $phpgw->common->phpgw_header();
  echo parse_navbar();

  $phpgw->template->set_file(array("form" => "account_form.tpl"));
  
  if ($totalerrors) {
     $phpgw->template->set_var("error_messages","<center>" . $phpgw->common->error_list($error) . "</center>");
  } else {
     $phpgw->template->set_var("error_messages","");
  }

  $userData = $phpgw->accounts->read_userData($account_id);

  if (! $submit) {
     $n_loginid   = $userData["account_lid"];
     $n_firstname = $userData["firstname"];
     $n_lastname  = $userData["lastname"];
     $apps = CreateObject('phpgwapi.applications',array(intval($userData["account_id"]),'u'));
     $apps->read_installed_apps();
     $db_perms = $apps->read_account_specific();
  }

  if ($phpgw_info["server"]["account_repository"] == "ldap") {
     $phpgw->template->set_var("form_action",$phpgw->link("editaccount.php","account_id=" . rawurlencode($userData["account_dn"]) . "&old_loginid=" . $userData["account_lid"]));
  } else {
     $phpgw->template->set_var("form_action",$phpgw->link("editaccount.php","account_id=" . $userData["account_id"] . "&old_loginid=" . $userData["account_lid"]));
  }

  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
  $phpgw->template->set_var("tr_color1",$phpgw_info["theme"]["row_on"]);
  $phpgw->template->set_var("tr_color2",$phpgw_info["theme"]["row_off"]);

  $phpgw->template->set_var("lang_action",lang("Edit user account"));

  $phpgw->template->set_var("lang_loginid",lang("LoginID"));
  $phpgw->template->set_var("n_loginid_value",$n_loginid);

  $phpgw->template->set_var("lang_account_active",lang("Account active"));
  if ($userData["status"]) {
     $phpgw->template->set_var("account_checked","checked");
  } else {
     $phpgw->template->set_var("account_checked","");
  }

  $phpgw->template->set_var("lang_password",lang("Password"));
  $phpgw->template->set_var("n_passwd_value",$n_passwd);

  $phpgw->template->set_var("lang_reenter_password",lang("Re-Enter Password"));
  $phpgw->template->set_var("n_passwd_2_value",$n_passwd_2);

  $phpgw->template->set_var("lang_firstname",lang("First Name"));
  $phpgw->template->set_var("n_firstname_value",$n_firstname);

  $phpgw->template->set_var("lang_lastname",lang("Last Name"));
  $phpgw->template->set_var("n_lastname_value",$n_lastname);

  $phpgw->template->set_var("lang_groups",lang("Groups"));
  $user_groups = $phpgw->accounts->read_group_names($userData["account_lid"]);

  $groups_select = '<select name="n_groups[]" multiple>';
  $phpgw->db->query("select * from groups");
  while ($phpgw->db->next_record()) {
     $groups_select .= '<option value="' . $phpgw->db->f("group_id") . '"';
     for ($i=0; $i<count($user_groups); $i++) {
        if ($user_groups[$i][0] == $phpgw->db->f("group_id")) {
           $groups_select .= " selected";
        }
     }
     $groups_select .= ">" . $phpgw->db->f("group_name") . "</option>\n";
  }
  $groups_select .= "</select>";
  $phpgw->template->set_var("groups_select",$groups_select);

  $i = 0;
  $sorted_apps = $phpgw_info["apps"];
  @asort($sorted_apps);
  @reset($sorted_apps);
  while ($permission = each($sorted_apps)) {
     if ($permission[1]["enabled"]) {
        $perm_display[$i][0] = $permission[0];
        $perm_display[$i][1] = $permission[1]["title"];
        $i++;
     }
  }

  @reset($db_perms);
  for ($i=0;$i<200;) {     // The $i<200 is only used for a brake
     if (! $perm_display[$i][1]) break;
     $perm_html .= '<tr bgcolor="'.$phpgw_info["theme"]["row_on"].'"><td>' . lang($perm_display[$i][1]) . '</td>'
                 . '<td><input type="checkbox" name="new_permissions['
                 . $perm_display[$i][0] . ']" value="True"';
     if ($new_permissions[$perm_display[$i][0]] || $db_perms[$perm_display[$i][0]]) {
        $perm_html .= " checked";
     }
     $perm_html .= "></td>";
     $i++;

     if ($i == count($perm_display) && is_odd(count($perm_display))) {
        $perm_html .= '<td colspan="2">&nbsp;</td></tr>';
     }

     if (! $perm_display[$i][1]) break;
     $perm_html .= '<td>' . lang($perm_display[$i][1]) . '</td>'
                 . '<td><input type="checkbox" name="new_permissions['
                 . $perm_display[$i][0] . ']" value="True"';
     if ($new_permissions[$perm_display[$i][0]] || $db_perms[$perm_display[$i][0]]) {
        $perm_html .= " checked";
     }
     $perm_html .= "></td></tr>\n";
     $i++;
  }

  $phpgw->template->set_var("permissions_list",$perm_html);	
  
  $apps->account_apps = Array(Array());

  // Read new Group ID's
  $new_groups = $phpgw->accounts->read_groups($account_id);
  $apps_after = Array(Array());
  // Read new Group Apps
  if ($new_groups) {
    $apps->account_type = 'g';
    reset($new_groups);
    while($groups = each($new_groups)) {
      $apps->account_id = intval($groups[0]);
      $new_app_groups = $apps->read_account_specific();
      @reset($new_app_groups);
      while($new_group_app = each($new_app_groups)) {
        if(!$apps_after[$new_group_app[0]]) {
          $apps_after[$new_group_app[0]] = $new_app_groups[$new_group_app[0]];
        }
      }
    }
  }

  $apps->account_type = 'u';
  $apps->account_id = intval($userData["account_id"]);
  $new_app_user = $apps->read_account_specific();
  while($new_user_app = each($new_app_user)) {
    if(!$apps_after[$new_user_app[0]]) {
      $apps_after[$new_user_app[0]] = $new_app_user[$new_user_app[0]];
    }
  }

  $includedSomething = False;
  // start inlcuding other admin tools
  while($app = each($apps_after))
  {
	// check if we have something included, when not ne need to set
	// {gui_hooks} to ""
  	if ($phpgw->common->hook_single('show_user_data', $app[0])) $includedSomething=True;
  }       
  if (!$includedSomething) $phpgw->template->set_var('gui_hooks','');

  $phpgw->template->set_var("lang_button",lang('Save'));
  $phpgw->template->pparse('out','form');

  account_close();
  $phpgw->common->phpgw_footer();
?>
