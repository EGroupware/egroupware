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
  $phpgw_info["flags"] = array("currentapp" => "admin", "enable_nextmatchs_class" => True);
  include("../header.inc.php");

  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
  $p->set_file(array("list" => "applications.tpl",
              			         "row"  => "applications_row.tpl"));

  if ($order) {
     $ordermethod = "order by $order $sort";
  } else {
     $ordermethod = "order by app_title asc";
  }

  if (! $sort) {
     $sort = "desc";
  }

  $p->set_var("lang_installed",lang("Installed applications"));
  $p->set_var("bg_color",$phpgw_info["theme"]["bg_color"]);
  $p->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $p->set_var("sort_title",$phpgw->nextmatchs->show_sort_order($sort,"app_title",$order,"applications.php",lang("title")));
  $p->set_var("lang_edit",lang("Edit"));
  $p->set_var("lang_delete",lang("Delete"));
  $p->set_var("lang_enabled",lang("Enabled"));

  $phpgw->db->query("select * from phpgw_applications $ordermethod",__LINE__,__FILE__);
  while ($phpgw->db->next_record()) {
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $name = $phpgw->db->f("app_title");
 
     if (! $phpgw->db->f("app_title")) $name = $phpgw->db->f("app_name");
     if (! $name)                      $name = "&nbsp;";

     $p->set_var("tr_color",$tr_color);
     $p->set_var("name",$name);
     $p->set_var("edit",'<a href="' . $phpgw->link("editapplication.php","app_name=" . urlencode($phpgw->db->f("app_name"))) . '"> ' . lang("Edit") . ' </a>');
     $p->set_var("delete",'<a href="' . $phpgw->link("deleteapplication.php","app_name=" . urlencode($phpgw->db->f("app_name"))) . '"> ' . lang("Delete")  . ' </a>');

     if ($phpgw->db->f("app_enabled") != 0) {
        $status = lang("Yes");
     } else {
        $status = "<b>" . lang("No") . "</b>";
     }
     $p->set_var("status",$status);

     $p->parse("rows","row",True);
  }

  $p->set_var("new_action",$phpgw->link("newapplication.php"));
  $p->set_var("lang_add",lang("add"));
  
  $p->pparse("out","list");
  $p->common->phpgw_footer();
?>
