<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info = array();
  if (! $account_id) {
     $phpgw_info["flags"] = array("nonavbar" => True, "noheader" => True);
  }

  $phpgw_info["flags"]["enable_nextmatchs_class"] = True;
  $phpgw_info["flags"]["currentapp"]  = "admin";
  $phpgw_info["flags"]["parent_page"] = "accounts.php";

  include("../header.inc.php");
  include($phpgw_info["server"]["app_inc"]."/accounts_".$phpgw_info["server"]["account_repository"].".inc.php");

  if (! $account_id) {
     Header("Location: " . $phpgw->link("accounts.php"));
  }

  function display_row($lable,$value)
  {
     global $phpgw, $tr_color;

     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

     $phpgw->template->set_var("tr_color",$tr_color);
     $phpgw->template->set_var("lable",$lable);
     $phpgw->template->set_var("value",$value);
     
     $phpgw->template->parse("rows","row",True);
  }

  $phpgw->template->set_file(array("display" => "account_view.tpl",
              			         "row"     => "account_view_row.tpl"));  

  $userData = $phpgw->accounts->read_userData($account_id);

  $loginid = $userData["account_lid"];
  $account_lastlogin      = $userData["account_lastlogin"];
  $account_lastloginfrom  = $userData["account_lastloginfrom"];
  $account_status	     = $userData["account_status"];

  $db_perms = $phpgw->accounts->read_apps($loginid);


  #$phpgw->db->query("select account_lid from accounts where account_id='$account_id'");
  #$phpgw->db->next_record();
  #$loginid = $phpgw->db->f("account_lid");
  
  #$account_info = account_view($loginid);

  #$phpgw->db->query("select account_lastlogin,account_lastloginfrom,account_status from accounts "
  #				. "where account_id='$account_id'");
  #$phpgw->db->next_record();

  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  display_row(lang("LoginID"),$loginid);
  display_row(lang("First Name"),$userData["firstname"]);
  display_row(lang("Last Name"),$userData["lastname"]);

  $i = 0;
  while ($permission = each($db_perms)) {
     if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
        $perm_display[$i] = lang($phpgw_info["apps"][$permission[0]]["title"]);
        $i++;
     }
  }
  display_row(lang("account permissions"),implode(", ", $perm_display));

  if ($userData["status"] == "A") {
     $account_status = lang("yes");
  } else {
     $account_status = "<b>" . lang("no") . "</b>";
  }
  display_row(lang("account active"),$account_status);

  $user_groups = $phpgw->accounts->read_group_names($userData["account_lid"]);
  for ($i=0;$i<count($user_groups); $i++) {
      $group_html .= $user_groups[$i][1];
      if (count($user_groups) !=0 && $i != count($user_groups)-1) {
         $group_html .= ", ";
      }
  }
  display_row(lang("Groups"),$group_html);

  if (! $userData["lastlogin"]) {
     $lastlogin = lang("Never");
  } else {
     $lastlogin = $phpgw->common->show_date($userData["lastlogin"]);
  }
  display_row(lang("Last login"),$lastlogin);
  display_row(lang("Last login from"),$userData["lastloginfrom"]);

  $phpgw->template->pparse("out","display");
  $phpgw->common->phpgw_footer();
?>
