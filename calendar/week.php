<?php_track_vars?>
<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
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
//     $phpgw_info["flags"]["nocalendarheader"] = True;
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
  unset($owner);

  if(!isset($owner) || !$owner) {
    $id = $phpgw_info["user"]["userid"];
    $fn = $phpgw_info["user"]["firstname"];
    $ln = $phpgw_info["user"]["lastname"];
    $owner = 0;
  } else {
    $phpgw->db->query("SELECT account_lid,account_firstname,account_lastname FROM accounts WHERE account_id=$owner");
    $phpgw->db->next_record();
    $id = $phpgw->db->f("account_lid");
    $fn = $phpgw->db->f("account_firstname");
    $ln = $phpgw->db->f("account_lastname");
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
  $first = $phpgw->calendar->splitdate($phpgw->calendar->get_sunday_before($thisyear, $thismonth, $thisday) + $start);
  $last = $phpgw->calendar->splitdate($first["raw"] + 518400);
  $phpgw->template->set_file(array("week_t" => "week.tpl"));

  $phpgw->template->set_block("week_t","week");

  if ($friendly) {
    $phpgw->template->set_var("printer_friendly","<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">");
  } else {
    $phpgw->template->set_var("printer_friendly","");
  }

  $phpgw->template->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);

  $phpgw->template->set_var("small_calendar_prev",$phpgw->calendar->pretty_small_calendar($thisday,$prevmonth["month"],$prevmonth["year"],"day.php"));
  if (!$friendly) {
    $phpgw->template->set_var("prev_week_link","<a href=\"".$phpgw->link("week.php","year=".$prev["year"]."&month=".$prev["month"]."&day=".$prev["day"])."\">&lt;&lt;</a>");
  } else {
    $phpgw->template->set_var("prev_week_link","&lt;&lt;");
  }
  $phpgw->template->set_var("small_calendar_this",$phpgw->calendar->pretty_small_calendar($thisday,$thismonth,$thisyear,"day.php"));

  $week_id = lang(strftime("%B",$first["raw"]))." ".$first["day"];
  if($first["month"] <> $last["month"] && $first["year"] <> $last["year"]) $week_id .= ", ".$first["year"];
  $week_id .= " - ";
  if($first["month"] <> $last["month"]) $week_id .= lang(strftime("%B",$last["raw"]))." ";
  $week_id .= $last["day"].", ".$last["year"];

  $phpgw->template->set_var("week_identifier",$week_id);
  $phpgw->template->set_var("username",$phpgw->common->display_fullname($id,$fn,$ln));

  if (!$friendly) {
    $phpgw->template->set_var("next_week_link","<a href=\"".$phpgw->link("week.php","year=".$next["year"]."&month=".$next["month"]."&day=".$next["day"])."\">&gt;&gt;</a>");
  } else {
    $phpgw->template->set_var("next_week_link","&gt;&gt;");
  }
  $phpgw->template->set_var("small_calendar_next",$phpgw->calendar->pretty_small_calendar($thisday,$nextmonth["month"],$nextmonth["year"],"day.php"));
  $phpgw->template->set_var("week_display",$phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true,$owner));

  if (!$friendly) {
    $param = "year=".$thisyear."&month=".$thismonth."&day=".$thisday."&friendly=1&filter=".$filter;
    $phpgw->template->set_var("print","<a href=\"".$phpgw->link($PHP_SELF,$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	   . "status = '" . lang("Generate printer-friendly version"). "'\">[". lang("Printer Friendly") . "]</A>");
    $phpgw->template->parse("out","week_t");
    $phpgw->template->pparse("out","week_t");
    $phpgw->common->phpgw_footer();
  } else {
    $phpgw->template->set_var("print","");
    $phpgw->template->parse("out","week_t");
    $phpgw->template->pparse("out","week_t");
  }
?>
