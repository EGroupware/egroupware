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
       $thisyear  = intval(substr($date, 0, 4));
       $thismonth = intval(substr($date, 4, 2));
       $thisday   = intval(substr($date, 6, 2));
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

  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));

  $p->set_file(array("index_t" => "index.tpl"));

  $p->set_block("index_t","index");

  if ($friendly) {
    $p->set_var("printer_friendly","<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">");
  } else {
    $p->set_var("printer_friendly","");
  }

  $p->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);

  $p->set_var("small_calendar_prev",$phpgw->calendar->mini_calendar(1,$prev["month"],$prev["year"],"day.php"));

  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  $p->set_var("month_identifier",lang(strftime("%B",$m)) . " " . $thisyear);
  $p->set_var("username",$phpgw->common->grab_owner_name($owner));
  $p->set_var("small_calendar_next",$phpgw->calendar->mini_calendar(1,$next["month"],$next["year"],"day.php"));
  flush();
  $p->set_var("large_month",$phpgw->calendar->display_large_month($thismonth,$thisyear,True,$owner));
  if (!$friendly) {
    $param = 'year='.$thisyear.'&month='.$thismonth.'&friendly=1&filter='.$filter;
    $p->set_var("print","<a href=\"".$phpgw->link("",$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	   . "status = '" . lang("Generate printer-friendly version"). "'\">[". lang("Printer Friendly") . "]</a>");
    $p->parse("out","index_t");
    $p->pparse("out","index_t");
  } else {
    $p->set_var("print","");
    $p->parse("out","index_t");
    $p->pparse("out","index_t");
  }
  $phpgw->common->phpgw_footer();
?>
