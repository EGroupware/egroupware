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
  $phpgw_info["flags"] = array("currentapp" => "admin", "noheader" => True, "nonavbar" => True);
  include("../header.inc.php");

  //$phpgw->template->set_unknowns("remove");
  $phpgw->template->set_file(array("form"	=> "application_form.tpl"));

  if ($submit) {
     $phpgw->templateotalerrors = 0;
  
     $phpgw->db->query("select count(*) from applications where app_name='"
     				. addslashes($n_app_name) . "'",__LINE__,__FILE__);
     $phpgw->db->next_record();
     
     if ($phpgw->db->f(0) != 0) {
        $error[$phpgw->templateotalerrors++] = lang("That application name already exsists.");
     }
  
     if (! $n_app_name)
        $error[$phpgw->templateotalerrors++] = lang("You must enter an application name.");

     if (! $n_app_title)
        $error[$phpgw->templateotalerrors++] = lang("You must enter an application title.");
     
     if (! $phpgw->templateotalerrors) {
        $phpgw->db->query("insert into applications (app_name,app_title,app_enabled) values('"
			            . addslashes($n_app_name) . "','" . addslashes($n_app_title) . "',"
			            . "$n_app_status)",__LINE__,__FILE__);

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

  $phpgw->template->set_var("hidden_vars","");
  $phpgw->template->set_var("form_action",$phpgw->link("newapplication.php"));
  $phpgw->template->set_var("lang_app_name",lang("application name"));
  $phpgw->template->set_var("lang_app_title",lang("application title"));
  $phpgw->template->set_var("lang_status",lang("Status"));
  $phpgw->template->set_var("lang_submit_button",lang("add"));

  $phpgw->template->set_var("app_name_value",$n_app_name);
  $phpgw->template->set_var("app_title_value",$n_app_value);

  $selected[$n_app_status] = " selected";
  $status_html = '<option value="0"' . $selected[0] . '>' . lang("Disabled") . '</option>'
               . '<option value="1"' . $selected[1] . '>' . lang("Enabled")  . '</option>'
               . '<option value="2"' . $selected[2] . '>' . lang("Enabled - Hidden from navbar")  . '</option>';
  $phpgw->template->set_var("select_status",$status_html);

  $phpgw->template->pparse("out","form");

  $phpgw->common->phpgw_footer();
?>
