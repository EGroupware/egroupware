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
  $phpgw_info["flags"]["enable_nextmatchs_class"] = True;

  include("../header.inc.php");

  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));

  function display_row($label, $value)
  {
     global $phpgw,$p;
     $p->set_var("tr_color",$phpgw->nextmatchs->alternate_row_color());
     $p->set_var("label",$label);
     $p->set_var("value",$value);

     $p->parse("rows","row",True);
  }

	$p->set_file(array(
		"form" => "application_form.tpl",
		"row"  => "application_form_row.tpl"
	));

  if ($submit) {
     if (! $app_order) {
        $app_order = 0;
     }

     $totalerrors = 0;
  
     if (! $n_app_name)
        $error[$totalerrors++] = lang("You must enter an application name.");
     
     if (! $n_app_title)
        $error[$totalerrors++] = lang("You must enter an application title.");

     if ($old_app_name != $n_app_name) {
        $phpgw->db->query("select count(*) from phpgw_applications where app_name='"
     			   	. addslashes($n_app_name) . "'",__LINE__,__FILE__);
        $phpgw->db->next_record();
     
        if ($phpgw->db->f(0) != 0) {
           $error[$totalerrors++] = lang("That application name already exists.");
        }
     }
 
     if (! $totalerrors) {
        $phpgw->db->query("update phpgw_applications set app_name='" . addslashes($n_app_name) . "',"
                	     . "app_title='" . addslashes($n_app_title) . "', app_enabled='"
                        . "$n_app_status',app_order='$app_order' where app_name='$old_app_name'",__LINE__,__FILE__);

        if($n_app_anonymous) {
          $phpgw->acl->add($n_app_name,'everywhere',0,'g',PHPGW_ACL_READ);
        } else {
          $phpgw->acl->delete($n_app_name,'everywhere',0,'g');
        }

        Header("Location: " . $phpgw->link("/admin/applications.php"));
        $phpgw->common->phpgw_exit();
     }
  }
  $phpgw->db->query("select * from phpgw_applications where app_name='$app_name'",__LINE__,__FILE__);
  $phpgw->db->next_record();

  if ($totalerrors) {
     $phpgw->common->phpgw_header();
     echo parse_navbar();

     $p->set_var("error","<p><center>" . $phpgw->common->error_list($error) . "</center><br>");
  } else {
     $p->set_var("error","");
     
     $n_app_name   = $phpgw->db->f("app_name");
     $n_app_title  = $phpgw->db->f("app_title");
     $n_app_status = $phpgw->db->f("app_enabled");
     $old_app_name = $phpgw->db->f("app_name");
     $app_order    = $phpgw->db->f("app_order");
     $n_app_anonymous = $phpgw->acl->check('', PHPGW_ACL_READ, $n_app_name);
  }
 
  $p->set_var("lang_header",lang("Edit application"));
  $p->set_var("hidden_vars",'<input type="hidden" name="old_app_name" value="' . $old_app_name . '">');
  $p->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
  $p->set_var("form_action",$phpgw->link("/admin/editapplication.php"));

  display_row(lang("application name"),'<input name="n_app_name" value="' . $n_app_name . '">');
  display_row(lang("application title"),'<input name="n_app_title" value="' . $n_app_title . '">');

  $p->set_var("lang_status",lang("Status"));
  $p->set_var("lang_submit_button",lang("edit"));

  $selected[$n_app_status] = " selected";
  $status_html = '<option value="0"' . $selected[0] . '>' . lang("Disabled") . '</option>'
               . '<option value="1"' . $selected[1] . '>' . lang("Enabled")  . '</option>'
               . '<option value="2"' . $selected[2] . '>' . lang("Enabled - Hidden from navbar")  . '</option>';

  display_row(lang("Status"),'<select name="n_app_status">' . $status_html . '</select>');
  display_row(lang("Select which location this app should appear on the navbar, lowest (left) to highest (right)"),'<input name="app_order" value="' . $app_order . '">');

  $str = '<input type="checkbox" name="n_app_anonymous" value="True"';
  if ($n_app_anonymous) {
    $str .= " checked";
  }
  $str .= ">";
  
  display_row(lang("Allow Anonymous access to this app"),$str);
  
  $p->set_var("select_status",$status_html);

  $p->pparse("out","form");

  $phpgw->common->phpgw_footer();
?>
