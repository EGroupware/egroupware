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
  $phpgw_info["flags"] = array("currentapp" => "admin", 
  			       "noheader" => True, 
  			       "nonavbar" => True,
  			       "parent_page" => "accounts.php");
  include("../header.inc.php");
  include($phpgw_info["server"]["app_inc"]."/accounts_".$phpgw_info["server"]["account_repository"].".inc.php");

  if ($submit) {
     $totalerrors = 0;

     if ($phpgw_info["server"]["account_repository"] == "ldap" && ! $allow_long_loginids) {
        if (strlen($n_loginid) > 8) {
           $error[$totalerrors++] = lang("The loginid can not be more then 8 characters");
        }
     }
  
     if (! $n_loginid)
        $error[$totalerrors++] = lang("You must enter a loginid");

     if (! $n_passwd)
        $error[$totalerrors++] = lang("You must enter a password");

     if ($n_passwd == $n_loginid)
        $error[$totalerrors++] = lang("The login and password can not be the same");

     if ($n_passwd != $n_passwd_2)
        $error[$totalerrors++] = lang("The two passwords are not the same");

     if (count($new_permissions) == 0)
        $error[$totalerrors++] = lang("You must add at least 1 permission to this account");
        
     if (count($n_groups) == 0)
        $error[$totalerrors++] = lang("Account must belong to at least 1 group");

     if (account_exsists($n_loginid)) {
        $error[$totalerrors++] = lang("That loginid has already been taken");
     }

     if (! $error) {
        $cd = account_add(array("loginid"   => $n_loginid,   "permissions" => $new_permissions,
            				        "firstname" => $n_firstname, "lastname"    => $n_lastname,
            				        "passwd"    => $n_passwd,
            				        "groups"    => $phpgw->accounts->groups_array_to_string($n_groups)));
        $phpgw->db->query("SELECT account_permissions FROM accounts WHERE account_lid='$n_loginid'",__LINE__,__FILE__);
        $phpgw->db->next_record();
        $apps = explode(":",$phpgw->db->f("account_permissions"));
        $phpgw->common->hook_single("add_def_pref", "admin");
        for ($i=1;$i<sizeof($apps) - 1;$i++) {
           $phpgw->common->hook_single("add_def_pref", $apps[$i]);
        }
        $phpgw->preferences->commit_newuser($n_loginid);
        Header("Location: " . $phpgw->link("accounts.php","cd=$cd"));
        $phpgw->common->phpgw_exit();
     }
  }

  $phpgw->template->set_file(array("form"	=> "account_form.tpl"));

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();

  $phpgw->template->set_var("lang_action",lang("Add new account"));

  if ($totalerrors) {
     $phpgw->template->set_var("error_messages","<center>" . $phpgw->common->error_list($error) . "</center>");
  } else {
     $phpgw->template->set_var("error_messages","");
  }

  $phpgw->template->set_var("tr_color1",$phpgw_info["theme"]["row_on"]);
  $phpgw->template->set_var("tr_color2",$phpgw_info["theme"]["row_off"]);
  
  $phpgw->template->set_var("form_action",$phpgw->link("newaccount.php"));
  $phpgw->template->set_var("lang_loginid",lang("LoginID"));
  $phpgw->template->set_var("n_loginid_value",$n_loginid);

  $phpgw->template->set_var("lang_account_active",lang("Account active"));

  $phpgw->template->set_var("lang_password",lang("Password"));
  $phpgw->template->set_var("n_passwd_value",$n_passwd);
  
  $phpgw->template->set_var("lang_reenter_password",lang("Re-Enter Password"));
  $phpgw->template->set_var("n_passwd_2_value",$n_passwd_2);

  $phpgw->template->set_var("lang_firstname",lang("First Name"));
  $phpgw->template->set_var("n_firstname_value",$n_firstname);

  $phpgw->template->set_var("lang_lastname",lang("Last Name"));
  $phpgw->template->set_var("n_lastname_value",$n_lastname);

  $phpgw->template->set_var("lang_groups",lang("Groups"));
  $group_select = '<select name="n_groups[]" multiple>';
  $phpgw->db->query("select * from groups");
  while ($phpgw->db->next_record()) {
    $group_select .= "<option value=\"" . $phpgw->db->f("group_id") . "\"";
    if ($n_groups[$phpgw->db->f("group_id")]) {
       $group_select .= " selected";
    }
    $group_select .= ">" . $phpgw->db->f("group_name") . "</option>";
  }
  $group_select .= "</select>";
  $phpgw->template->set_var("groups_select",$group_select);

  $phpgw->template->set_var("","");
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

  for ($i=0;$i<200;) {		// The $i<200 is only used for a brake
      if (! $perm_display[$i][1]) break;

      $perms_html .= '<tr bgcolor="'.$phpgw_info["theme"]["row_on"].'"><td>' . lang($perm_display[$i][1]) . '</td>'
                  . '<td><input type="checkbox" name="new_permissions['
		          . $perm_display[$i][0] . ']" value="True"';
      if ($new_permissions[$perm_display[$i][0]]) {
         $perms_html .= " checked";
      }
      $perms_html .= "></td>";

      $i++;

     if (! $perm_display[$i][1]) break;

     $perms_html .= '<td>' . lang($perm_display[$i][1]) . '</td>'
                  . '<td><input type="checkbox" name="new_permissions['
		          . $perm_display[$i][0] . ']" value="True"';
     if ($new_permissions[$perm_display[$i][0]]) {
        $perms_html .= " checked";
     }
	 $perms_html .= "></td></tr>";

     $i++;
  }
  $phpgw->template->set_var("permissions_list",$perms_html);

  $phpgw->template->set_var("lang_button",Lang("Add"));
  $phpgw->template->pparse("out","form");
  
  account_close();
  $phpgw->common->phpgw_footer();
?>
