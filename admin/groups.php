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
  $p->set_file(array("list"   => "groups.tpl",
                     "row"    => "groups_row.tpl"));

  if (! $start)
     $start = 0;

  if ($order)
      $ordermethod = "ORDER BY $order $sort";
   else
      $ordermethod = "ORDER BY account_lid asc";

  if (! $sort)
     $sort = "asc";

  if ($query) {
     $querymethod = "AND account_lid like '%$query%'";
  }

  $phpgw->db->query("SELECT count(*) FROM phpgw_accounts WHERE account_type='g' $querymethod");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->db->limit($start,$total);
  
  $p->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
  $p->set_var("left_nextmatchs",$phpgw->nextmatchs->left("groups.php",$start,$total));
  $p->set_var("right_nextmatchs",$phpgw->nextmatchs->right("groups.php",$start,$total));
  $p->set_var("lang_groups",lang("user groups"));

  $p->set_var("sort_name",$phpgw->nextmatchs->show_sort_order($sort,"account_lid",$order,"groups.php",lang("name")));
  $p->set_var("header_edit",lang("Edit"));
  $p->set_var("header_delete",lang("Delete"));

  $phpgw->db->query("SELECT * FROM phpgw_accounts WHERE account_type='g' $querymethod $ordermethod $limit");
  while ($phpgw->db->next_record()) {
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
 
     $p->set_var("tr_color",$tr_color);
 
     $group_name = $phpgw->db->f("account_lid");
 
     if (! $group_name)  $group_name  = '&nbsp;';

     $p->set_var("group_name",$group_name); 
     $p->set_var("edit_link",'<a href="' . $phpgw->link("editgroup.php","group_id=" . $phpgw->db->f("account_id")) . '"> ' . lang("Edit") . ' </a>');
     $p->set_var("delete_link",'<a href="' . $phpgw->link("deletegroup.php","group_id=" . $phpgw->db->f("account_id")) . '"> ' . lang("Delete") . ' </a>');
 
     $p->parse("rows","row",True);
  }

  $p->set_var("new_action",$phpgw->link("newgroup.php"));
  $p->set_var("lang_add",lang("add"));

  $p->set_var("search_action",$phpgw->link("groups.php"));
  $p->set_var("lang_search",lang("search"));

  $p->pparse("out","list");

  $phpgw->common->phpgw_footer();
?>
