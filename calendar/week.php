<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_nextmatchs_class" => True);

  if (isset($friendly) && $friendly){
     $phpgw_info["flags"]["noheader"] = True;
     $phpgw_info["flags"]["nonavbar"] = True;
     $phpgw_info["flags"]["noappheader"] = True;
     $phpgw_info["flags"]["noappfooter"] = True;
     $phpgw_info["flags"]["nofooter"] = True;
  } else {
     $friendly = 0;
  }
  include("../header.inc.php");

  if(isset($friendly) && $friendly) {
    if(!isset($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"]))
      $phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] = "Sunday";

    if (isset($date) && strlen($date) > 0) {
       $thisyear  = substr($date, 0, 4);
       $thismonth = substr($date, 4, 2);
       $thisday   = substr($date, 6, 2);
    } else {
       if (!isset($day) || !$day)
          $thisday = $phpgw->calendar->today["day"];
       else
          $thisday = $day;
       if (!isset($month) || !$month)
          $thismonth = $phpgw->calendar->today["month"];
       else
          $thismonth = $month;
       if (!isset($year) || !$year)
          $thisyear = $phpgw->calendar->today["year"];
       else
          $thisyear = $year;
    }
  }

  if(!isset($owner)) { $owner = 0; } 

  if(!isset($owner) || !$owner) {
    $owner = $phpgw_info['user']['account_id'];
    $rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE;
  } else {
    $grants = $phpgw->acl->get_grants('calendar');
    if($grants[$owner])
    {
      $rights = $grants[$owner];
      if (!($rights & PHPGW_ACL_READ))
      {
        $owner = $phpgw_info['user']['account_id'];
      }
    }
  }

  $next = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth,$thisday + 7,$thisyear));
  $prev = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth,$thisday - 7,$thisyear));

  $nextmonth = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth + 1,1,$thisyear));
  $prevmonth = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth - 1,1,$thisyear));

  if($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] == "Sunday") {
    $start = 7200;
  } else {
    $start = 93600;
  }
  $first = $phpgw->calendar->splitdate($phpgw->calendar->get_weekday_start($thisyear, $thismonth, $thisday) + $start);
  $last = $phpgw->calendar->splitdate($first["raw"] + 518400);

  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
  $p->set_file(array("week_t" => "week.tpl"));

  $p->set_block("week_t","week");

  if ($friendly) {
    $p->set_var("printer_friendly","<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">");
  } else {
    $p->set_var("printer_friendly","");
  }

  $p->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);

  $p->set_var("small_calendar_prev",$phpgw->calendar->mini_calendar($thisday,$prevmonth["month"],$prevmonth["year"],"day.php"));
  if (!$friendly) {
    $p->set_var("prev_week_link","<a href=\"".$phpgw->link("week.php","year=".$prev["year"]."&month=".$prev["month"]."&day=".$prev["day"])."\">&lt;&lt;</a>");
  } else {
    $p->set_var("prev_week_link","&lt;&lt;");
  }
  $p->set_var("small_calendar_this",$phpgw->calendar->mini_calendar($thisday,$thismonth,$thisyear,"day.php"));

  $week_id = lang(strftime("%B",$first["raw"]))." ".$first["day"];
  if($first["month"] <> $last["month"] && $first["year"] <> $last["year"]) $week_id .= ", ".$first["year"];
  $week_id .= " - ";
  if($first["month"] <> $last["month"]) $week_id .= lang(strftime("%B",$last["raw"]))." ";
  $week_id .= $last["day"].", ".$last["year"];

  $p->set_var("week_identifier",$week_id);
  $p->set_var("username",$phpgw->common->grab_owner_name($owner));

  if (!$friendly) {
    $p->set_var("next_week_link","<a href=\"".$phpgw->link("week.php","year=".$next["year"]."&month=".$next["month"]."&day=".$next["day"])."\">&gt;&gt;</a>");
  } else {
    $p->set_var("next_week_link","&gt;&gt;");
  }
  $p->set_var("small_calendar_next",$phpgw->calendar->mini_calendar($thisday,$nextmonth["month"],$nextmonth["year"],"day.php"));
  $p->set_var("week_display",$phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true,$owner));

  if (!$friendly) {
    $param = 'year='.$thisyear.'&month='.$thismonth.'&friendly=1&filter='.$filter;
    $p->set_var("print","<a href=\"".$phpgw->link("",$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	   . "status = '" . lang("Generate printer-friendly version"). "'\">[". lang("Printer Friendly") . "]</A>");
    $p->parse("out","week_t");
    $p->pparse("out","week_t");
  } else {
    $p->set_var("print","");
    $p->parse("out","week_t");
    $p->pparse("out","week_t");
  }
  $phpgw->common->phpgw_footer();
?>
