<?php
  /**************************************************************************\
  * phpGroupWare module (NNTP)                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <mpeters@satx.rr.com>                             *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($submit && $nntplist) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "admin";
  $phpgw_info["flags"]["disable_network_class"] = True;
  $phpgw_info["flags"]["disable_message_class"] = True;
  $phpgw_info["flags"]["disable_send_class"] = True;
  $phpgw_info["flags"]["disable_vfs_class"] = True;
  include("../header.inc.php");

  $phpgw->include_lang("nntp");

   function get_tg()
  {
    global $phpgw;

    $phpgw->db->query("SELECT count(con) FROM newsgroups");
    $phpgw->db->next_record();
    return $phpgw->db->f(0);
  }

  if(!$submit && !$nntplist) {

    $t = new Template($phpgw_info["server"]["template_dir"]);

    $t->set_file(array( "nntp_header"	=> "nntp.tpl",
			"nntp_list"	=> "nntp.tpl",
			"nntp_footer"	=> "nntp.tpl" ));

    $t->set_block("nntp_header","nntp_list","nntp_footer","output");

    if (! $tg)
    {
      $tg = get_tg();
    }

    if ($tg == 0)
    {
      set_time_limit(0);
      include($phpgw_info["server"]["include_root"]
		. "/../nntp/inc/functions.inc.php");
      $nntp = new NNTP;
      $nntp->load_table();
      $tg = get_tg();
    }

    if (! $start) $start = 0;
     
    if (! $query_result) $query_result = 0;

    $orderby = "";
    if ($order)
    {
      switch ($order)
      {
	case 1:
	  $orderby = " ORDER BY CON $sort";
	  break;
	case 2:
	  $orderby = " ORDER BY NAME $sort";
	  break;
	case 3:
	  $orderby = " ORDER BY ACTIVE $sort";
	  break;
      }
    }

    if ($search || $next) {
      if ($search)
	$query_result = 0;
      else
	$query_result++;
      $phpgw->db->query("SELECT con FROM newsgroups "
		    ."WHERE name LIKE '%$query%'$orderby LIMIT "
		    .$phpgw->nextmatchs->sql_limit($query_result));
      $phpgw->db->next_record();
      $start = $phpgw->db->f("con") - 1;
    }
     
    $urlname = $phpgw_info["server"]["webserver_url"]."/admin/nntp.php";

    $common_hidden_vars = "<input type=\"hidden\" name=\"start\" value=\"".$start."\">\n"
		        . "<input type=\"hidden\" name=\"stop\" value=\""
			. ($start + $phpgw_info["user"]["preferences"]["maxmatchs"])."\">\n"
		        . "<input type=\"hidden\" name=\"tg\" value=\"".$tg."\">\n"
		        . "<input type=\"hidden\" name=\"query_result\" value=\"".$query_result."\">\n";

    $t->set_var("search_value",$query);
    $t->set_var("search",lang("search"));
    $t->set_var("next",lang("next"));

    $t->set_var("nml",$phpgw->nextmatchs->left($urlname,$start,$tg,
					"&tg=$tg&sort=$sort&order=$order"));
    $t->set_var("nmr",$phpgw->nextmatchs->right($urlname,$start,$tg,
				  	"&tg=$tg&sort=$sort&order=$order"));
    $t->set_var("title",lang("Newsgroups"));
    $t->set_var("action_url",$phpgw->link($urlname));
    $t->set_var("common_hidden_vars",$common_hidden_vars);
    $t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
    $t->set_var("th_font",$phpgw_info["theme"]["font"]);
    $t->set_var("sort_con",$phpgw->nextmatchs->show_sort_order($sort,"1",$order,$urlname," # ","&tg=$tg"));
    $t->set_var("sort_group",$phpgw->nextmatchs->show_sort_order($sort,"2",$order,$urlname,"Group","&tg=$tg"));
    $t->set_var("sort_active",$phpgw->nextmatchs->show_sort_order($sort,"3",$order,$urlname," Active ","&tg=$tg"));

    $t->parse("out","nntp_header");

    if ($phpgw_info["user"]["preferences"]["maxmatchs"] <= $tg - $start)
      $totaltodisplay = $phpgw_info["user"]["preferences"]["maxmatchs"];
    else
      $totaltodisplay = ($tg - $start);

    $orderby = "";
    if ($order)
    {
      switch ($order)
      {
	case 1:
	  $orderby = " ORDER BY CON $sort";
	  break;
	case 2:
	  $orderby = " ORDER BY NAME $sort";
	  break;
	case 3:
	  $orderby = " ORDER BY ACTIVE $sort";
	  break;
      }
    }

    $phpgw->db->query("SELECT con, name, active FROM newsgroups$orderby LIMIT "
		    .$phpgw->nextmatchs->sql_limit($start,$totaltodisplay));

    for ($i=0;$i<$totaltodisplay;$i++)
    {
      $phpgw->db->next_record();
      $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
      $t->set_var("tr_color",$tr_color);
      $con = $phpgw->db->f("con");
      $t->set_var("con",$con);

      $name = $phpgw->db->f("name");
      if (! $name) $name  = "&nbsp;";
      $group_name = "<a href=\"" . $phpgw->link(
					$phpgw_info["server"]["webserver_url"]
		  		        ."/admin/editnntp.php","con=$con")
		  . "\">$name</a>";
      $t->set_var("group",$group_name);

      $active = $phpgw->db->f("active");
      if ($active == "Y") $checked = " checked"; else $checked = "";
      $active_var = "<input type=\"checkbox\" name=\"nntplist[]\" value=\"$con\"$checked>";
      $t->set_var("active",$active_var);

      if ($i+1 <> $totaltodisplay)
	$t->parse("output","nntp_list",True);
    }
    $t->set_var("lang_update",lang("update"));
    $t->set_var("checkmark",$phpgw_info["server"]["webserver_url"]."/email/images/check.gif");

    $t->pparse("out","nntp_footer");
 
    include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

  } else { 
    $phpgw->db->lock("newsgroups");

    $phpgw->db->query("UPDATE newsgroups SET active='N' WHERE con>=$start AND con<=$stop");

    for ($i=0;$i<count($nntplist);$i++)
    {
      $phpgw->db->query("UPDATE newsgroups SET active='Y' WHERE con=".$nntplist[$i]);
    }
    $phpgw->db->unlock();

    Header("Location: " . $phpgw->link($urlname,"start=$start&tg=$tg"));
  }
?>
