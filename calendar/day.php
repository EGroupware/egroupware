<?php php_track_vars?>
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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True, "enable_nextmatchs_class" => True, "enable_template_class" => True);

  if (isset($friendly) && $friendly){
     $phpgw_info["flags"]["noheader"] = True;
     $phpgw_info["flags"]["nonavbar"] = True;
     $phpgw_info["flags"]["nocalendarheader"] = True;
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

  $view = "day";

  $now	= $phpgw->calendar->splitdate(mktime (2, 0, 0, $thismonth, $thisday, $thisyear));

  $phpgw->template->set_file(array("day" => "day.tpl"));

  $phpgw->template->set_block("day");

  if ($friendly) {
    $phpgw->template->set_var("printer_friendly","<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">");
  } else {
    $phpgw->template->set_var("printer_friendly","");
  }

  $phpgw->template->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);

  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  $phpgw->template->set_var("date",lang(strftime("%B",$m))." ".$thisday.", ".$thisyear);
  $phpgw->template->set_var("username",$phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]));
  $phpgw->template->set_var("daily_events",$phpgw->calendar->print_day_at_a_glance($now));
  $phpgw->template->set_var("small_calendar",$phpgw->calendar->pretty_small_calendar($now["day"],$now["month"],$now["year"],"day.php"));

  if (!$friendly) {
    $param = "year=".$now["year"]."&month=".$now["month"]."&day=".$now["day"]."&friendly=1";
    $phpgw->template->set_var("print","<a href=\"".$phpgw->link($PHP_SELF,$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang("Generate printer-friendly version")."'\">[".lang("Printer Friendly")."]</A>");
    $phpgw->template->parse("out","day");
    $phpgw->template->pparse("out","day");
    $phpgw->common->phpgw_footer();
  } else {
    $phpgw->template->set_var("print","");
    $phpgw->template->parse("out","day");
    $phpgw->template->pparse("out","day");
  }
?>
