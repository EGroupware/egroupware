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

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");

  $t = new Template($phpgw_info["server"]["template_dir"]);
  //$t->set_unknowns("remove");
  $t->set_file(array("form"	=> "application_form.tpl"));

  if ($submit) {
     if (! $n_app_name || ! $n_app_title) {
        $error = lang_admin("You must enter an application name and title.");
     } else {
        $phpgw->db->query("insert into applications (app_name,app_title,app_enabled) values('"
			    . addslashes($n_app_name) . "','" . addslashes($n_app_title) . "','"
			    . "$n_app_enabled')");

        Header("Location: " . $phpgw->link("applications.php"));
        exit;
     }
  }
  if ($error) {
     $phpgw->common->header();
     $phpgw->common->navbar();
  }

  $t->set_var("lang_header",lang_admin("Add new application"));

  if ($error) {
     $t->set_var("error","<p><center>$error</center><br>");
  } else {
     $t->set_var("error","");
  }

  $t->set_var("session_hidden_var",$phpgw->session->hidden_var());
  $t->set_var("form_action","newapplication.php");
  $t->set_var("lang_app_name",lang_admin("application name"));
  $t->set_var("lang_app_title",lang_admin("application title"));
  $t->set_var("lang_enabled",lang_admin("enabled"));
  $t->set_var("lang_add",lang_common("add"));

  $t->set_var("app_name_value","");
  $t->set_var("app_title_value","");
  $t->set_var("app_enabled_checked","");

  $t->pparse("out","form");

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
