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

  if(!isset($owner)) { $owner = $phpgw_info["user"]["account_id"]; } 
//  unset($owner);

  $next = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth + 1,1,$thisyear));

  $prev = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth - 1,1,$thisyear));

  $view = "month";

  $phpgw->template->set_file(array("index_t" => "index.tpl"));

  $phpgw->template->set_block("index_t","index");

  if ($friendly) {
    $phpgw->template->set_var("printer_friendly","<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">");
  } else {
    $phpgw->template->set_var("printer_friendly","");
  }

  $phpgw->template->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);

  $phpgw->template->set_var("small_calendar_prev",$phpgw->calendar->mini_calendar($thisday,$prev["month"],$prev["year"],"day.php"));

  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  $phpgw->template->set_var("month_identifier",lang(strftime("%B",$m)) . " " . $thisyear);
  $phpgw->template->set_var("username",$phpgw->common->grab_owner_name($owner));
  $phpgw->template->set_var("small_calendar_next",$phpgw->calendar->mini_calendar($thisday,$next["month"],$next["year"],"day.php"));
  $phpgw->template->set_var("large_month",$phpgw->calendar->display_large_month($thismonth,$thisyear,True,$owner));
  if (!$friendly) {
    $param = 'year='.$thisyear.'&month='.$thismonth.'&friendly=1&filter='.$filter;
    $phpgw->template->set_var("print","<a href=\"".$phpgw->link("",$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	   . "status = '" . lang("Generate printer-friendly version"). "'\">[". lang("Printer Friendly") . "]</a>");
    $phpgw->template->parse("out","index_t");
    $phpgw->template->pparse("out","index_t");
  } else {
    $phpgw->template->set_var("print","");
    $phpgw->template->parse("out","index_t");
    $phpgw->template->pparse("out","index_t");
  }
  $phpgw->common->phpgw_footer();
?>
