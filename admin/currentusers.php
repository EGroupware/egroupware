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

  $phpgw->template->set_file(array("list" => "currentusers.tpl",
                                   "row"  => "currentusers_row.tpl"));

  if (! $start) {
     $start = 0;
  }

  $limit = $phpgw->nextmatchs->sql_limit($start);
  $phpgw->db->query("select count(*) from phpgw_sessions",__LINE__,__FILE__);
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);

  $phpgw->template->set_var("lang_current_users",lang("List of current users"));
  $phpgw->template->set_var("bg_color",$phpgw_info["theme"][bg_color]);
  $phpgw->template->set_var("left_next_matchs",$phpgw->nextmatchs->left("currentusers.php",$start,$total));
  $phpgw->template->set_var("right_next_matchs",$phpgw->nextmatchs->right("currentusers.php",$start,$total));
  $phpgw->template->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);

  $phpgw->template->set_var("sort_loginid",$phpgw->nextmatchs->show_sort_order($sort,"session_lid",$order,
       					 "currentusers.php",lang("LoginID")));
  $phpgw->template->set_var("sort_ip",$phpgw->nextmatchs->show_sort_order($sort,"session_ip",$order,
                            "currentusers.php",lang("IP")));
  $phpgw->template->set_var("sort_login_time",$phpgw->nextmatchs->show_sort_order($sort,"session_logintime",$order,
                            "currentusers.php",lang("Login Time")));
  $phpgw->template->set_var("sort_idle",$phpgw->nextmatchs->show_sort_order($sort,"session_dla",$order,
                            "currentusers.php",lang("idle")));
  $phpgw->template->set_var("lang_kill",lang("Kill"));

  if ($order) {
     $ordermethod = "order by $order $sort";
  } else {
     $ordermethod = "order by session_dla asc";
  }

  $phpgw->db->query("select * from phpgw_sessions $ordermethod limit $limit",__LINE__,__FILE__);

  $i = 0;
  while ($phpgw->db->next_record()) {
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
     $phpgw->template->set_var("tr_color",$tr_color);

     if (ereg("@",$phpgw->db->f("session_lid"))) {
        $t = split("@",$phpgw->db->f("session_lid"));
        $loginid = $t[0];
     } else {
        $loginid = $phpgw->db->f("session_lid");
     }

     $phpgw->template->set_var("row_loginid",$loginid);
     $phpgw->template->set_var("row_ip",$phpgw->db->f("session_ip"));
     $phpgw->template->set_var("row_logintime",$phpgw->common->show_date($phpgw->db->f("session_logintime")));
     $phpgw->template->set_var("row_idle",gmdate("G:i:s",(time() - $phpgw->db->f("session_dla"))));

     if ($phpgw->db->f("session_id") != $phpgw_info["user"]["sessionid"]) {
        $phpgw->template->set_var("row_kill",'<a href="' . $phpgw->link("killsession.php","ksession="
		                        . $phpgw->db->f("session_id") . "&kill=true") . "\">" . lang("Kill").'</a>');
     } else {
    	$phpgw->template->set_var("row_kill","&nbsp;");
     }

     $phpgw->template->parse("rows","row",True);
   }

   $phpgw->template->pparse("out","list");
   $phpgw->common->phpgw_footer();
?>
