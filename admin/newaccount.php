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

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "disable_message_class" => True,
  						 "disable_send_class" => True, "currentapp" => "admin");

  include("../header.inc.php");
  include($phpgw_info["server"]["server_root"] . "/admin/inc/accounts_"
        . $phpgw_info["server"]["auth_type"] . ".inc.php");

  
  if ($submit) {
     $totalerrors = 0;
  
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
        
        Header("Location: " . $phpgw->link("accounts.php","cd=$cd"));
        exit;
     }
  }
  
  $t = new Template($phpgw_info["server"]["template_dir"]);
  $t->set_file(array("form"	=> "account_form.tpl"));

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();

  $t->set_var("lang_action",lang("Add new account"));

  if ($totalerrors) {
     $t->set_var("error_messages","<center>" . $phpgw->common->error_list($error) . "</center>");
  } else {
     $t->set_var("error_messages","");
  }
  
  $t->set_var("form_action",$phpgw->link("newaccount.php"));
  $t->set_var("lang_loginid",lang("LoginID"));
  $t->set_var("n_loginid_value",$n_loginid);

  $t->set_var("lang_password",lang("Password"));
  $t->set_var("n_passwd_value",$n_passwd);
  
  $t->set_var("lang_reenter_password",lang("Re-Enter Password"));
  $t->set_var("n_passwd_2_value",$n_passwd_2);

  $t->set_var("lang_firstname",lang("First Name"));
  $t->set_var("n_firstname_value",$n_firstname);

  $t->set_var("lang_lastname",lang("Last Name"));
  $t->set_var("n_lastname_value",$n_lastname);

  $t->set_var("lang_groups",lang("Groups"));
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
  $t->set_var("groups_select",$group_select);

  $t->set_var("","");
  $i = 0;
  while ($permission = each($phpgw_info["apps"])) {
    if ($permission[1]["enabled"]) {
       $perm_display[$i][0] = $permission[0];
       $perm_display[$i][1] = $permission[1]["title"];
       $i++;
    }
  }

  for ($i=0;$i<200;) {		// The $i<200 is only used for a brake
      if (! $perm_display[$i][1]) break;

      $perms_html .= '<tr><td>' . lang($perm_display[$i][1]) . '</td>'
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
  $t->set_var("permissions_list",$perms_html);

  $t->set_var("lang_button",Lang("Add"));
  $t->pparse("out","form");
  $phpgw->common->phpgw_footer();
?>
