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

  $phpgw_info["flags"]["currentapp"] = "admin";
  $phpgw_info["flags"]["disable_message_class"] = True;
  $phpgw_info["flags"]["disable_send_class"] = True;
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
      $ordermethod = "order by account_lastname,account_firstname,account_lid asc";

  if (! $sort)
     $sort = "desc";

  if ($query) {
     $querymethod = " where account_firstname like '%$query%' OR account_lastname like '%$query%' OR account_lid "
		        . "like '%$query%' ";
  }

  $phpgw->db->query("select count(*) from accounts $querymethod");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);

  $t->set_var("bg_color",$phpgw_info["theme"]["bg_color"]);
  $t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $t->set_var("left_next_matchs",$phpgw->nextmatchs->left("accounts.php",$start,$total));
  $t->set_var("lang_user_accounts",lang("user accounts"));
  $t->set_var("right_next_matchs",$phpgw->nextmatchs->right("accounts.php",$start,$total));

  $t->set_var("lang_lastname",$phpgw->nextmatchs->show_sort_order($sort,"account_lastname",$order,"accounts.php",lang("last name")));
  $t->set_var("lang_firstname",$phpgw->nextmatchs->show_sort_order($sort,"account_firstname",$order,"accounts.php",lang("first name")));

  $t->set_var("lang_edit",lang("Edit"));
  $t->set_var("lang_delete",lang("Delete"));
  $t->set_var("lang_view",lang("View"));

  $t->parse("out","header");

  $phpgw->db->query("select account_id,account_firstname,account_lastname,account_lid from accounts $querymethod "
	             . "$ordermethod limit $limit");

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $t->set_var("tr_color",$tr_color);

    $lastname  = $phpgw->db->f("account_lastname");
    $firstname = $phpgw->db->f("account_firstname");

    if (! $lastname)  $lastname  = '&nbsp;';
    if (! $firstname) $firstname = '&nbsp;';

    $t->set_var("row_firstname",$firstname);
    $t->set_var("row_lastname",$lastname);
    $t->set_var("row_edit",'<a href="'.$phpgw->link("editaccount.php","account_id="
				     . $phpgw->db->f("account_id")) . '"> ' . lang("Edit") . ' </a>');

    if ($phpgw_info["user"]["userid"] != $phpgw->db->f("account_lid")) {
       $t->set_var("row_delete",'<a href="' . $phpgw->link("deleteaccount.php",'account_id='
						. $phpgw->db->f("account_id")) . '"> '.lang("Delete").' </a>');
    } else {
       $t->set_var("row_delete","&nbsp;");
    }

    $t->set_var("row_view",'<a href="' . $phpgw->link("viewaccount.php", "account_id="
				     . $phpgw->db->f("account_id")) . '"> ' . lang("View") . ' </a>');

    if ($phpgw->db->num_rows() == 1) {
       $t->set_var("output","");
    }
    if ($phpgw->db->num_rows() != ++$i) {
       $t->parse("output","row",True);
    }

  }

  $t->set_var("actionurl",$phpgw->link("newaccount.php"));
  $t->set_var("lang_add",lang("add"));
  $t->set_var("lang_search",lang("search"));

  $t->pparse("out","footer");

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
