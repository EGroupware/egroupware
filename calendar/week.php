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
  if (isset($friendly) && $friendly){
     $phpgw_info["flags"]["noheader"] = True;
  } else {
     $friendly = 0;
  }

  $phpgw_info["flags"]["currentapp"] = "calendar";
  include("../header.inc.php");

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

  $next = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth,$thisday + 7,$thisyear));
  $prev = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth,$thisday - 7,$thisyear));

  $nextmonth = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth + 1,1,$thisyear));
  $prevmonth = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth - 1,1,$thisyear));

  $sun = $phpgw->calendar->splitdate($phpgw->calendar->get_sunday_before($thisyear, $thismonth, $thisday) + 7200);
  $sat = $phpgw->calendar->splitdate($sun["raw"] + 604800);

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">";
     $view = "week";
  }
?>

<head>
<style type="text/css">
  .tablecell {
    width: 80px;
    height: 80px;
  }
</style>
</head>
<table border=0 width=100%>
<tr>
<?php
  echo "<td align=\"left\" valign=\"top\">";
  echo $phpgw->calendar->pretty_small_calendar($thisday,$prevmonth["month"],$prevmonth["year"],"day.php");
  echo "</td>";
  echo "<td align=\"left\">";
  if (!$friendly)
    echo "<a href=\"".$phpgw->link("week.php","year=".$prev["year"]."&month=".$prev["month"]."&day=".$prev["day"])."\">";
  echo "&lt;&lt;";
  if(!friendly) echo "</a>";
  echo "</td>";
  echo "<td align=\"center\" valign=\"top\">";
  echo $phpgw->calendar->pretty_small_calendar($thisday,$thismonth,$thisyear,"day.php");
?>
<font size="+2" color="<?php echo $phpgw_info["theme"]["bg_text"]; ?>"><b>
<?php
  echo lang(strftime("%B",$sun["raw"]))." ".$sun["day"];
  if($sun["month"] <> $sat["month"] && $sun["year"] <> $sat["year"]) echo ", ".$sun["year"];
  echo " - ";
  if($sun["month"] <> $sat["month"])  echo lang(strftime("%B",$sat["raw"]))." ";
  echo $sat["day"].", ".$sat["year"];
?>
</b></font>
<font size="+1" color="<?php echo $phpgw_info["theme"]["bg_text"]; ?>">
<br>
<?php
  echo $phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]);
?>
</font></td>
<?php
  echo "<td align=\"right\">";
  if (!$friendly)
    echo "<a href=\"".$phpgw->link("week.php","year=".$next["year"]."&month=".$next["month"]."&day=".$next["day"])."\">";
  echo "&gt;&gt;";
  if(!friendly) echo "</a>";
  echo "</td>";
  echo "<td align=\"right\" valign=\"top\">";
  echo $phpgw->calendar->pretty_small_calendar($thisday,$nextmonth["month"],$nextmonth["year"],"day.php");
  echo "</td>";
?>
</tr>
</table>
<?php 
  echo $phpgw->calendar->display_large_week($thisday,$thismonth,$thisyear,true);

  if (!$friendly) {
     $param = "";
     if ($thisyear)
        $param .= "year=$thisyear&month=$thismonth&";

     $param .= "friendly=1";
     echo "<a href=\"".$phpgw->link($PHP_SELF,$param)."\" target=\"cal_printer_friendly\" onMouseOver=\"window.status='"
	  .lang("Generate printer-friendly version")."'\">[" . lang("Printer Friendly") . "]</a>";
     $phpgw->common->phpgw_footer();
  }
?>
