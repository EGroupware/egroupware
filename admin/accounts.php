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

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");

  $t = new Template($phpgw_info["server"]["template_dir"]);
  $t->set_file(array( "header"	=> "accounts.tpl",
			       "row"		=> "accounts.tpl",
			       "footer"	=> "accounts.tpl" ));

  $t->set_block("header","row","footer");

  if (! $start)
     $start = 0;

  if ($order)
      $ordermethod = "order by $order $sort";
   else
      $ordermethod = "order by lastname,firstname,loginid asc";

  if (! $sort)
     $sort = "desc";

  if ($query) {
     $querymethod = " where firstname like '%$query%' OR lastname like '%$query%' OR loginid "
		        . "like '%$query%' ";
  }

  $phpgw->db->query("select count(*) from accounts $querymethod");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);

  $t->set_var("bg_color",$phpgw_info["theme"]["bg_color"]);
  $t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $t->set_var("left_next_matchs",$phpgw->nextmatchs->left("accounts.php",$start,$total));
  $t->set_var("lang_user_accounts",lang_admin("user accounts"));
  $t->set_var("right_next_matchs",$phpgw->nextmatchs->right("accounts.php",$start,$total));

  $t->set_var("lang_lastname",$phpgw->nextmatchs->show_sort_order($sort,"lastname",$order,"accounts.php",lang_common("last name")));
  $t->set_var("lang_firstname",$phpgw->nextmatchs->show_sort_order($sort,"firstname",$order,"accounts.php",lang_common("first name")));

  $t->set_var("lang_edit",lang_common("Edit"));
  $t->set_var("lang_delete",lang_common("Delete"));
  $t->set_var("lang_view",lang_common("View"));

  $t->parse("out","header");

  $phpgw->db->query("select con,firstname,lastname,loginid from accounts $querymethod "
	             . "$ordermethod limit $limit");

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $t->set_var("tr_color",$tr_color);

    $lastname  = $phpgw->db->f("lastname");
    $firstname = $phpgw->db->f("firstname");

    if (! $lastname)  $lastname  = '&nbsp;';
    if (! $firstname) $firstname = '&nbsp;';

    $t->set_var("row_firstname",$firstname);
    $t->set_var("row_lastname",$lastname);
    $t->set_var("row_edit",'<a href="'.$phpgw->link("editaccount.php","con="
				  . $phpgw->db->f("con")) . '"> ' . lang_common("Edit") . ' </a>');

    if ($phpgw->session->loginid != $phpgw->db->f("loginid")) {
       $t->set_var("row_delete",'<a href="' . $phpgw->link("deleteaccount.php",'con='
						. $phpgw->db->f("con")) . '"> '.lang_common("Delete").' </a>');
    } else {
       $t->set_var("row_delete","&nbsp;");
    }

    $t->set_var("row_view",'<a href="' . $phpgw->link("viewaccount.php", "con="
				 . $phpgw->db->f("con")) . '"> ' . lang_common("View") . ' </a>');

    if ($phpgw->db->num_rows() == 1) {
       $t->set_var("output","");
    }
    if ($phpgw->db->num_rows() != ++$i) {
       $t->parse("output","row",True);
    }

  }

  $t->set_var("actionurl",$phpgw->link("newaccount.php"));
  $t->set_var("lang_add",lang_common("add"));
  $t->set_var("lang_search",lang_common("search"));

  $t->pparse("out","footer");

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
