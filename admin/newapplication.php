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
  $phpgw_info["flags"] = array("currentapp" => "admin", "noheader" => True, "nonavbar" => True,
                               "enable_nextmatchs_class" => True);
  include("../header.inc.php");

  $phpgw->template->set_file(array("form" => "application_form.tpl",
                                   "row"  => "application_form_row.tpl"
                                  ));

  function display_row($label, $value)
  {
     global $phpgw;
     $phpgw->template->set_var("tr_color",$phpgw->nextmatchs->alternate_row_color());
     $phpgw->template->set_var("label",$label);
     $phpgw->template->set_var("value",$value);

     $phpgw->template->parse("rows","row",True);
  }

  if ($submit) {
     $totalerrors = 0;
  
     $phpgw->db->query("select count(*) from applications where app_name='"
     				. addslashes($n_app_name) . "'",__LINE__,__FILE__);
     $phpgw->db->next_record();
     
     if ($phpgw->db->f(0) != 0) {
        $error[$totalerrors++] = lang("That application name already exsists.");
     }
     
     if (preg_match("/\D/",$app_order)) {
        $error[$totalerrors++] = lang("That application order must be a number.");
     }
  
     if (! $n_app_name)
        $error[$totalerrors++] = lang("You must enter an application name.");

     if (! $n_app_title)
        $error[$totalerrors++] = lang("You must enter an application title.");
     
     if (! $totalerrors) {
        $phpgw->db->query("insert into applications (app_name,app_title,app_enabled,app_order) values('"
			            . addslashes($n_app_name) . "','" . addslashes($n_app_title) . "',"
			            . "$n_app_status,$app_order)",__LINE__,__FILE__);

        $phpgw->db->query("SELECT preference_owner, preference_value FROM preferences");
	if($phpgw->db->num_rows()) {
	  while($phpgw->db->next_record()) {
	    if($phpgw->db->f("preference_owner") != $phpgw_info["user"]["account_id"]) {
	      $phpgw_newuser["user"]["preferences"] = unserialize($phpgw->db->f("preference_value"));
	      $phpgw->common->hook_single("add_def_pref", $n_app_name);
	      $phpgw->preferences->commit_user($phpgw->db->f("preference_owner"));
	    } else {
	      $phpgw->common->hook_single("add_def_pref", $n_app_name);
	      $phpgw_info["user"]["preferences"]["$n_app_name"] = $phpgw_newuser["user"]["preferences"]["$n_app_name"];
	      unset($phpgw_newuser);
	      $phpgw->preferences->commit();
	    }
	  }
	}
        Header("Location: " . $phpgw->link("applications.php"));
        exit;
     } else {
        $phpgw->template->set_var("error","<p><center>" . $phpgw->common->error_list($error) . "</center><br>");
     }
  } else {		// else submit
     $phpgw->template->set_var("error","");
  }
  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();

  $phpgw->template->set_var("lang_header",lang("Add new application"));
  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $phpgw->template->set_var("hidden_vars","");
  $phpgw->template->set_var("form_action",$phpgw->link("newapplication.php"));

  display_row(lang("application name"),'<input name="n_app_name" value="' . $n_app_name . '">');
  display_row(lang("application title"),'<input name="n_app_title" value="' . $n_app_title . '">');

  $selected[$n_app_status] = " selected";
  $status_html = '<option value="0"' . $selected[0] . '>' . lang("Disabled") . '</option>'
               . '<option value="1"' . $selected[1] . '>' . lang("Enabled")  . '</option>'
               . '<option value="2"' . $selected[2] . '>' . lang("Enabled - Hidden from navbar")  . '</option>';
  display_row(lang("Status"),'<select name="n_app_status">' . $status_html . '</select>');
  display_row(lang("Select which location this app should appear on the navbar, lowest (left) to highest (right)"),'<input name="app_order" value="' . $app_order . '">');

  $phpgw->template->set_var("lang_submit_button",lang("add"));

  $phpgw->template->pparse("out","form");
  $phpgw->common->phpgw_footer();
?>
