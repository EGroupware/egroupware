<?php
  /**************************************************************************\
  * phpGroupWare - NNTP administration                                       *
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

  $phpgw_info["flags"]["currentapp"] = "preferences";
  include("../header.inc.php");
  $phpgw->translation->add_app("nntp");
  function get_tg()
  {
    global $phpgw;

    $phpgw->db->query("SELECT count(con) FROM newsgroups WHERE active='Y'");
    $phpgw->db->next_record();
    return $phpgw->db->f(0);
  }

  if(!$submit && !$nntplist) {

    $phpgw->db->query("SELECT con FROM accounts WHERE loginid='".$phpgw_info["user"]["userid"]."'");
    $phpgw->db->next_record();
    $usercon = $phpgw->db->f("con");

    $urlname = $phpgw_info["server"]["webserver_url"]."/preferences/nntp.php";

    $t = new Template($phpgw_info["server"]["template_dir"]);

    $t->set_file(array( "nntp_header"	=> "nntp.tpl",
			"nntp_list"	=> "nntp.tpl",
			"nntp_footer"	=> "nntp.tpl" ));

    $t->set_block("nntp_header","nntp_list","nntp_footer","output");

    if (! $tg)
    {
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
	  $orderby = " ORDER BY GROUP $sort";
	  break;
	case 3:
	  $orderby = " ORDER BY ACTIVE $sort";
	  break;
      }
    }

    if ($search || $next) {
      if ($search) {
	$query_result = 0;
      } else
	$query_result++;
      $phpgw->db->query("SELECT name FROM newsgroups WHERE active='Y'$orderby");
      $j = 0;
      $i = 0;
      while($phpgw->db->next_record())
      {
	if (stristr($phpgw->db->f("name"),$query)) {
	  if($i==$query_result) {
	    $start = $j;
	    break;
	  } else
	    $i++;
        }
	$j++;
      }
    }

    $phpgw->db->query("SELECT con, name FROM newsgroups WHERE active='Y'$orderby LIMIT "
	            .$phpgw->nextmatchs->sql_limit($start));

    while($phpgw->db->next_record())
    {
      $nntpavailgroups["con"][] = $phpgw->db->f("con");
      $nntpavailgroups["name"][] = $phpgw->db->f("name");
    }

    $first = min($nntpavailgroups["con"]);

    $common_hidden_vars = "<input type=\"hidden\" name=\"start\" value=\"".$start."\">\n"
		        . "<input type=\"hidden\" name=\"first\" value=\"".$first."\">\n"
		        . "<input type=\"hidden\" name=\"tg\" value=\"".$tg."\">\n"
		        . "<input type=\"hidden\" name=\"usercon\" value=\"".$usercon."\">\n"
		        . "<input type=\"hidden\" name=\"order\" value=\"".$order."\">\n"
		        . "<input type=\"hidden\" name=\"sort\" value=\"".$sort."\">\n"
		        . "<input type=\"hidden\" name=\"query_result\" value=\"".$query_result."\">\n";

    $t->set_var("search_value",$query);
    $t->set_var("search",lang("search"));
    $t->set_var("next",lang("next"));

    $t->set_var("nml",$phpgw->nextmatchs->left(	$urlname,
					$start,
					$tg,
					"&tg=$tg&sort=$sort&order=$order"));
    $t->set_var("nmr",$phpgw->nextmatchs->right($urlname,
					$start,
					$tg,
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

    $phpgw->db->query("select newsgroup from users_newsgroups where owner=$usercon");
    while ($phpgw->db->next_record())
    {
      $found[$phpgw->db->f("newsgroup")] = " checked";
    }

    for($i=0;$i<count($nntpavailgroups["con"]);$i++)
    {
      $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

      $t->set_var("tr_color",$tr_color);
      $con = $nntpavailgroups["con"][$i];
      $t->set_var("con",$con);

      if (! $nntpavailgroups["name"][$i]) $nntpavailgroups["name"][$i]  = "&nbsp;";
      $t->set_var("group",$nntpavailgroups["name"][$i]);

      $active_var = "<input type=\"checkbox\" name=\"nntplist[]\" value=\"$con\"".$found[$con].">";

      $t->set_var("active",$active_var);

      if ($i+1 <> count($nntpavailgroups["con"]))
	$t->parse("output","nntp_list",True);
    }
    $t->set_var("lang_update",lang("update"));
    $t->set_var("checkmark",$phpgw_info["server"]["webserver_url"]."/email/images/check.gif");

    $t->pparse("out","nntp_footer");
    include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
  } else { 
    $phpgw->db->lock(array("users_newsgroups","accounts"));

    $orderby = "";
    if ($order)
    {
      switch ($order)
      {
	case 1:
	  $orderby = " ORDER BY CON $sort";
	  break;
	case 2:
	  $orderby = " ORDER BY GROUP $sort";
	  break;
	case 3:
	  $orderby = " ORDER BY ACTIVE $sort";
	  break;
      }
    }
    $phpgw->db->query("DELETE FROM users_newsgroups WHERE newsgroup>=$first AND owner=$usercon$orderby LIMIT "
		    .$phpgw->nextmatchs->sql_limit(0));

    for ($i=0;$i<count($nntplist);$i++)
    {
      $phpgw->db->query("INSERT INTO users_newsgroups VALUES($usercon,".$nntplist[$i].")");
    }
    $phpgw->db->unlock();

    Header("Location: " . $phpgw->link($urlname,"start=$start&tg=$tg"));
  }
?>
