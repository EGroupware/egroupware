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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True, "enable_nextmatchs_class" => True);
  if (isset($friendly) && $friendly){
     $phpgw_info["flags"]["noheader"] = True;
     $phpgw_info["flags"]["nonavbar"] = True;
     $phpgw_info["flags"]["nocalendarheader"] = True;
  } else {
     $friendly = 0;
  }

  include("../header.inc.php");

  $next = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth + 1,1,$thisyear));

  $prev = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth - 1,1,$thisyear));

  $view = "month";

  $phpgw->template->set_file(array("index" => "index.tpl"));

  $phpgw->template->set_block("index");

  if ($friendly) {
    $phpgw->template->set_var("printer_friendly","<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">");
  } else {
    $phpgw->template->set_var("printer_friendly","");
  }

  $phpgw->template->set_var("bg_text",$phpgw_info["theme"]["bg_text"]);

  $phpgw->template->set_var("small_calendar_prev",$phpgw->calendar->pretty_small_calendar($thisday,$prev["month"],$prev["year"],"day.php"));

  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  $phpgw->template->set_var("month_identifier",lang(strftime("%B",$m)) . " " . $thisyear);
  $phpgw->template->set_var("username",$phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]));
  $phpgw->template->set_var("small_calendar_next",$phpgw->calendar->pretty_small_calendar($thisday,$next["month"],$next["year"],"day.php"));
  $phpgw->template->set_var("large_month",$phpgw->calendar->display_large_month($thismonth,$thisyear,True,"edit_entry.php"));
  if (!$friendly) {
    $param = "year=".$now["year"]."&month=".$now["month"]."&friendly=1";
    $phpgw->template->set_var("print","<a href=\"".$phpgw->link($PHP_SELF,$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	   . "status = '" . lang("Generate printer-friendly version"). "'\">[". lang("Printer Friendly") . "]</a>");
    $phpgw->template->parse("out","index");
    $phpgw->template->pparse("out","index");
    $phpgw->common->phpgw_footer();
  } else {
    $phpgw->template->set_var("print","");
    $phpgw->template->parse("out","index");
    $phpgw->template->pparse("out","index");
  }
?>
