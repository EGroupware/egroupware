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

  if ($friendly) {
     $phpgw_info["flags"]["noheader"] = True;
  }

  $phpgw_info["flags"]["currentapp"] = "calendar";
  include("../header.inc.php");
  if (strlen($date) > 0) {
     $thisyear = substr($date, 0, 4);
     $thismonth = substr($date, 4, 2);
     $thisday = substr($date, 6, 2);
  } else {
     if ($day == 0)
        $thisday = date("d");
     else
        $thisday = $day;
     if ($month == 0)
        $thismonth = date("m");
     else
        $thismonth = $month;
     if ($year == 0)
        $thisyear = date("Y");
     else
        $thisyear = $year;
  }

  $next = mktime(2,0,0,$thismonth + 1,1,$thisyear);
  $nextyear = date("Y", $next);
  $nextmonth = date("m", $next);
  $nextdate = date("Ymd");

  $prev = mktime(2,0,0,$thismonth - 1,1,$thisyear);
  $prevyear = date("Y",$prev);
  $prevmonth = date("m",$prev);
  $prevdate = date("Ymd");

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"][bg_color]."\">";
     $view = "month";
  }
?>

<HEAD>
<STYLE TYPE="text/css">
<?php echo "$CCS_DEFS";?>

  .tablecell {
    width: 80px;
    height: 80px;
  }
</STYLE>
</HEAD>

<table border="0" width="100%">
<tr>
<?php
  if (! $friendly) {
     echo "<td align=\"left\">";
     display_small_month($prevmonth,$prevyear,True);
  }
?>

<td align="middle"><font size="+2" color="#000000"><B>
<?php
  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  print lang(strftime("%B",$m)) . " " . $thisyear;
?>
</b></font>
<font color="#000000" size="+1">
<br>
<?php
  echo $phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]);
?>
</font></td>
<?php
  if (! $friendly) {
     echo "<td align=\"right\">";
     display_small_month($nextmonth,$nextyear,True);
  }
?>
</tr>
</table>

<table width="100%" border="0" bordercolor="#FFFFFF" cellspacing="2" cellpadding="2">

<tr>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Sun"); ?></font></th>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Mon"); ?></font></th>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Tue"); ?></font></th>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Wed"); ?></font></th>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Thu"); ?></font></th>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Fri"); ?></font></th>
<th width="14%" bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><font color="<?php echo $phpgw_info["theme"]["th_text"]; ?>"><?php echo lang("Sat"); ?></font></th>
</tr>

<?php
  /* Pre-Load the repeated events for quckier access */
  $repeated_events = read_repeated_events();

  // We add 2 hours on to the time so that the switch to DST doesn't
  // throw us off.  So, all our dates are 2AM for that day.
  // $sun = get_sunday_before($thisyear,$thismonth,1) + 7200;
  $sun = get_sunday_before($thisyear,$thismonth,1) + 7200;
  // generate values for first day and last day of month
  $monthstart = mktime(2,0,0,$thismonth,1,$thisyear);
  $monthend = mktime(2,0,0,$thismonth + 1,0,$thisyear);

  // debugging
  //echo "<P>sun = "	    . date("D, m-d-Y", $sun)	    . "<BR>";
  //echo "<P>monthstart = " . date("D, m-d-Y", $monthstart) . "<BR>";
  //echo "<P>monthend = "   . date("D, m-d-Y", $monthend)   . "<BR>";

  $today = mktime(2,0,0,date("m"),date("d"),date("Y"));

  for ($i = $sun; date("Ymd",$i) <= date("Ymd",$monthend); $i += (24 * 3600 * 7) ) {
    $cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);

    echo "<tr>\n";
    for ($j = 0; $j < 7; $j++) {
      $date = $i + ($j * 24 * 3600);
      if (date("Ymd",$date) >= date("Ymd",$monthstart) &&
         date("Ymd",$date) <= date("Ymd",$monthend)) {
         echo "<td valign=\"top\" width=\"75\" height=\"75\"";
         if (date("Ymd",$date) == date("Ymd",$today)) {
            echo " bgcolor=\"".$phpgw_info["theme"]["cal_today"]."\">";
         } else {
            echo " bgcolor=\"$cellcolor\">";
         }

         print_date_entries($date,$friendly,$phpgw_info["user"]["sessionid"]);

         $thirsday=$i+24*3600*4;
         if ($phpgw_info["user"]["preferences"]["weekdaystarts"] == "Sunday" && $j == 0) {
            echo "<font size=\"-2\"><a href=\"".$phpgw->link("week.php","date=".date("Ymd",$date))."\">week " .(int)((date("z",$thirsday)+7)/7) . "</a></font>";
         }
         if ($phpgw_info["user"]["preferences"]["weekdaystarts"] == "Monday" && $j == 1) {
            echo "<font size=\"-2\"><a href=\"".$phpgw->link("week.php","date=" . date("Ymd",$date)) . "\">week " . (int)((date("z",$thirsday)+7)/7) . "</a></font>";
         }

         echo "</td>\n";
      } else {
         echo "<td></td>\n";
      }
    }
    print "</tr>\n";
  }

?>

</table>
<p>
<p>

<?php
  if (! $friendly) {
     $param = "";
     if ($thisyear)
        $param .= "year=$thisyear&month=$thismonth&";

     $param .= "friendly=1";
     echo "<a href=\"".$phpgw->link($PHP_SELF,$param)."\" target=\"cal_printer_friendly\" onMouseOver=\"window.status='"
	  .lang("Generate printer-friendly version")."'\">[" . lang("Printer Friendly") . "]</a>";
     $phpgw->common->phpgw_footer();
  }
?>
