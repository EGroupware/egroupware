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

  $phpgw->template->set_file(array("form"	=> "application_form.tpl"));

  if ($submit) {
     $totalerrors = 0;
  
     if (! $n_app_name)
        $error[$totalerrors++] = lang("You must enter an application name.");
     
     if (! $n_app_title)
        $error[$totalerrors++] = lang("You must enter an application title.");

     if ($old_app_name != $n_app_name) {
        $phpgw->db->query("select count(*) from applications where app_name='"
     			   	. addslashes($n_app_name) . "'",__LINE__,__FILE__);
        $phpgw->db->next_record();
     
        if ($phpgw->db->f(0) != 0) {
           $error[$totalerrors++] = lang("That application name already exsists.");
        }
     }
        
     if (! $totalerrors) {
        $phpgw->db->query("update applications set app_name='" . addslashes($n_app_name) . "',"
                	    . "app_title='" . addslashes($n_app_title) . "', app_enabled='"
                        . "$n_app_status' where app_name='$old_app_name'",__LINE__,__FILE__);

        Header("Location: " . $phpgw->link("applications.php"));
        $phpgw->common->phpgw_exit();
     }
  }
  $phpgw->db->query("select * from applications where app_name='$app_name'",__LINE__,__FILE__);
  $phpgw->db->next_record();

  if ($totalerrors) {
     $phpgw->common->phpgw_header();
     $phpgw->common->navbar();

     $phpgw->template->set_var("error","<p><center>" . $phpgw->common->error_list($error) . "</center><br>");
  } else {
     $phpgw->template->set_var("error","");
     
     $n_app_name   = $phpgw->db->f("app_name");
     $n_app_title  = $phpgw->db->f("app_title");
     $n_app_status = $phpgw->db->f("app_enabled");
     $old_app_name = $phpgw->db->f("app_name");
  }
 
  $phpgw->template->set_var("lang_header",lang("Edit application"));
  $phpgw->template->set_var("hidden_vars",'<input type="hidden" name="old_app_name" value="' . $old_app_name . '">');

  $phpgw->template->set_var("form_action",$phpgw->link("editapplication.php"));
  $phpgw->template->set_var("lang_app_name",lang("application name"));
  $phpgw->template->set_var("lang_app_title",lang("application title"));
  $phpgw->template->set_var("lang_status",lang("Status"));
  $phpgw->template->set_var("lang_submit_button",lang("edit"));

  $phpgw->template->set_var("app_name_value",$n_app_name);
  $phpgw->template->set_var("app_title_value",$n_app_title);

  $selected[$n_app_status] = " selected";
  $status_html = '<option value="0"' . $selected[0] . '>' . lang("Disabled") . '</option>'
               . '<option value="1"' . $selected[1] . '>' . lang("Enabled")  . '</option>'
               . '<option value="2"' . $selected[2] . '>' . lang("Enabled - Hidden from navbar")  . '</option>';
  $phpgw->template->set_var("select_status",$status_html);

  $phpgw->template->pparse("out","form");

  $phpgw->common->phpgw_footer();
?>
