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
  include("../header.inc.php");

  $t = new Template($phpgw_info["server"]["template_dir"]);
  $t->set_file(array( "header"	=> "currentusers.tpl",
			  "row"		=> "currentusers.tpl",
			  "footer"	=> "currentusers.tpl" ));

  $t->set_block("header","row","footer","output");

  if (! $start)
     $start = 0;

  $limit = $phpgw->nextmatchs->sql_limit($start);
  $phpgw->db->query("select count(*) from sessions");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);

  $t->set_var("lang_current_users",lang("List of current users"));
  $t->set_var("bg_color",$phpgw_info["theme"][bg_color]);
  $t->set_var("left_next_matchs",$phpgw->nextmatchs->left("currentusers.php",$start,$total));
  $t->set_var("right_next_matchs",$phpgw->nextmatchs->right("currentusers.php",$start,$total));
  $t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $t->set_var("sort_loginid",$phpgw->nextmatchs->show_sort_order($sort,"loginid",$order,
					 "currentusers.php",lang("LoginID")));
  $t->set_var("sort_ip",$phpgw->nextmatchs->show_sort_order($sort,"ip",$order,
				"currentusers.php",lang("IP")));
  $t->set_var("sort_login_time",$phpgw->nextmatchs->show_sort_order($sort,"logintime",$order,
						"currentusers.php",lang("Login Time")));
  $t->set_var("sort_idle",$phpgw->nextmatchs->show_sort_order($sort,"dla",$order,
				  "currentusers.php",lang("idle")));
  $t->set_var("lang_kill",lang("Kill"));

  $t->parse("out","header");


  if ($order) {
     $ordermethod = "order by $order $sort";
  } else {
     $ordermethod = "order by session_dla asc";
  }

  $phpgw->db->query("select * from sessions $ordermethod limit $limit");

  $i = 0;
  while ($phpgw->db->next_record()) {
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $t->set_var("tr_color",$tr_color);

     $t->set_var("row_loginid",$phpgw->db->f("session_lid"));
     $t->set_var("row_ip",$phpgw->db->f("session_ip"));
     $t->set_var("row_logintime",$phpgw->common->show_date($phpgw->db->f("session_logintime")));
     $t->set_var("row_idle",gmdate("G:i:s",(time() - $phpgw->db->f("session_dla"))));

     if ($phpgw->db->f("session_id") != $phpgw_info["user"]["sessionid"]) {
        $t->set_var("row_kill",'<a href="' . $phpgw->link("killsession.php","ksession="
		  . $phpgw->db->f("session_id") . "&kill=true\">" . lang("Kill")).'</a>');
     } else {
	$t->set_var("row_kill","&nbsp;");
     }

     if ($phpgw->db->num_rows() == 1) {
        $t->set_var("output","");
     }
     if ($phpgw->db->num_rows() != ++$i) {
        $t->parse("output","row",True);
     }
   }

   $t->pparse("out","footer");
   include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
