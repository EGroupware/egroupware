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
  $t->set_file(array( "header"	=> "accesslog.tpl",
			  "row"		=> "accesslog.tpl",
			  "footer"	=> "accesslog.tpl" ));

  $t->set_block("header","row","footer");

  $show_maxlog = 30;

  $t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
  $t->set_var("lang_last_x_logins",lang("Last x logins",$show_maxlog));

  $t->set_var("lang_loginid",lang("LoginID"));
  $t->set_var("lang_ip",lang("IP"));
  $t->set_var("lang_login",lang("Login"));
  $t->set_var("lang_logout",lang("Logout"));
  $t->set_var("lang_total",lang("Total"));

  $t->parse("out","header");

  $phpgw->db->query("select loginid,ip,li,lo from access_log order by li desc "
	         . "limit $show_maxlog");
  while ($phpgw->db->next_record()) {

    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $t->set_var("tr_color",$tr_color);

    // In case there was a problem creating there session. eg, bad login time
    // I still want it to be printed here.  This will alert the admin there
    // is a problem.
    if ($phpgw->db->f("li") && $phpgw->db->f("lo")) {
       $total = $phpgw->db->f("lo") - $phpgw->db->f("li");
       if ($total > 86400 && $total > 172800)
          $total = gmdate("z \d\a\y\s - G:i:s",$total);
       else if ($total > 172800)
          $total = gmdate("z \d\a\y - G:i:s",$total);
       else
          $total = gmdate("G:i:s",$total);
    } else
       $total = "&nbsp;";

    if ($phpgw->db->f("li"))
       $li = $phpgw->common->show_date($phpgw->db->f("li"));
    else
       $li = "&nbsp;";

    if ($phpgw->db->f("lo"))
       $lo = $phpgw->common->show_date($phpgw->db->f("lo"));
    else
       $lo = "&nbsp;";

    $t->set_var("row_loginid",$phpgw->db->f("loginid"));
    $t->set_var("row_ip",$phpgw->db->f("ip"));
    $t->set_var("row_li",$li);
    $t->set_var("row_lo",$li);
    $t->set_var("row_total",$total);

    if ($phpgw->db->num_rows() == 1) {
       $t->set_var("output","");
    }
    if ($phpgw->db->num_rows() != ++$i) {
       $t->parse("output","row",True);
    }
  }

  $phpgw->db->query("select count(*) from access_log");
  $phpgw->db->next_record();
  $total = $phpgw->db->f(0);

  $phpgw->db->query("select count(*) from access_log where lo!='0'");
  $phpgw->db->next_record();
  $loggedout = $phpgw->db->f(0);

  $percent = round((10000 * ($loggedout / $total)) / 100);

  $t->set_var("bg_color",$phpgw_info["themes"]["bg_color"]);
  $t->set_var("footer_total",lang("Total records") . ": $total");
  $t->set_var("lang_percent",lang("Percent of users that logged out") . ": $percent%");

  $t->pparse("out","footer");

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

