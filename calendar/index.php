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

  $next = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth + 1,1,$thisyear));

  $prev = $phpgw->calendar->splitdate(mktime(2,0,0,$thismonth - 1,1,$thisyear));

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">";
  }
  $view = "month";
?>

<HEAD>
<STYLE TYPE="text/css">
<!-- <?php echo "$CCS_DEFS";?> -->

  .tablecell {
    width: 80px;
    height: 80px;
  }
</STYLE>
</HEAD>

<table border="0" width="100%">
<tr>
<?php
  echo "<td align=\"left\" valign=\"top\">";
  echo $phpgw->calendar->pretty_small_calendar($thisday,$prev["month"],$prev["year"],"day.php");
  echo "</td>";
?>

<td align="middle"><font size="+2" color="#000000"><B>
<?php
  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  echo lang(strftime("%B",$m)) . " " . $thisyear;
?>
</b></font>
<font color="#000000" size="+1" color="<?php echo $phpgw_info["theme"]["bg_text"]; ?>">
<br>
<?php
  echo $phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]);
?>
</font></td>
<?php
  echo "<td align=\"right\" valign=\"top\">";
  echo $phpgw->calendar->pretty_small_calendar($thisday,$next["month"],$next["year"],"day.php");
  echo "</td>";
?>
</tr>
</table>
<?php
  flush();
  echo $phpgw->calendar->display_large_month($thismonth,$thisyear,True,"edit_entry.php");
  flush();
  /* Pre-Load the repeated events for quckier access */
  $repeated_events = read_repeated_events();
?>
<p>
<p>

<?php
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
