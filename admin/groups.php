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

  $phpgw->template->set_file(array("list"   => "groups.tpl",
              			         "row"    => "groups_row.tpl"));

  if (! $start)
     $start = 0;

  if ($order)
      $ordermethod = "order by $order $sort";
   else
      $ordermethod = "order by group_name asc";

  if (! $sort)
     $sort = "asc";

  if ($query) {
     $querymethod = " where group_name like '%$query%'";
  }

  $phpgw->db->query("select count(*) from groups $querymethod");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->db->limit($start,$total);
  
  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
  $phpgw->template->set_var("left_nextmatchs",$phpgw->nextmatchs->left("groups.php",$start,$total));
  $phpgw->template->set_var("right_nextmatchs",$phpgw->nextmatchs->right("groups.php",$start,$total));
  $phpgw->template->set_var("lang_groups",lang("user groups"));

  $phpgw->template->set_var("sort_name",$phpgw->nextmatchs->show_sort_order($sort,"group_name",$order,"groups.php",lang("name")));
  $phpgw->template->set_var("header_edit",lang("Edit"));
  $phpgw->template->set_var("header_delete",lang("Delete"));

  $phpgw->db->query("select * from groups $querymethod $ordermethod $limit");
  while ($phpgw->db->next_record()) {
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
 
     $phpgw->template->set_var("tr_color",$tr_color);
 
     $group_name = $phpgw->db->f("group_name");
 
     if (! $group_name)  $group_name  = '&nbsp;';

     $phpgw->template->set_var("group_name",$group_name); 
     $phpgw->template->set_var("edit_link",'<a href="' . $phpgw->link("editgroup.php","group_id=" . $phpgw->db->f("group_id")) . '"> ' . lang("Edit") . ' </a>');
     $phpgw->template->set_var("delete_link",'<a href="' . $phpgw->link("deletegroup.php","group_id=" . $phpgw->db->f("group_id")) . '"> ' . lang("Delete") . ' </a>');
 
     $phpgw->template->parse("rows","row",True);
  }

  $phpgw->template->set_var("new_action",$phpgw->link("newgroup.php"));
  $phpgw->template->set_var("lang_add",lang("add"));

  $phpgw->template->set_var("search_action",$phpgw->link("groups.php"));
  $phpgw->template->set_var("lang_search",lang("search"));

  $phpgw->template->pparse("out","list");

  $phpgw->common->phpgw_footer();
?>
