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

  $view = "day";

  if (strlen($date) > 0) {
     $thisyear  = substr($date,0,4);
     $thismonth = substr($date,4,2);
     $thisday   = substr($date,6,2);
  } else {
     if ($month == 0) {
        $thismonth = date("m");
     } else {
        $thismonth = $month;
     }

     if ($year == 0) {
        $thisyear = date("Y");
     } else {
        $thisyear = $year;
     }

     if ($day == 0) {
        $thisday = date("d");
     } else {
        $thisday = $day;
     }
  }

  $now		 = mktime (2, 0, 0, $thismonth, $thisday, $thisyear);

  $next		 = mktime(2, 0, 0, $thismonth, $thisday + 1, $thisyear);
  $nextyear	 = date("Y", $next);
  $nextmonth	 = date("m", $next);
  $nextday	 = date("d", $next);
  $month_ago	 = date("Ymd", mktime(2, 0, 0,$thismonth - 1,$thisday,$thisyear));

  $prev		 = mktime(2, 0, 0, $thismonth, $day - 1, $thisyear);
  $prevyear	 = date("Y", $prev);
  $prevmonth	 = date("m", $prev);
  $prevday	 = date("d", $prev);
  $month_ahead	 = date("Ymd", mktime(2,0,0,$thismonth + 1,$thisday,$thisyear));

  /* Pre-Load the repeated events for quckier access */
  $repeated_events = read_repeated_events();

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"][bg_color]."\">";
  }

?>
<TABLE BORDER=0 WIDTH=100%>
<TR><TD VALIGN="top" WIDTH=70%"><TR><TD>
<TABLE BORDER=0 WIDTH=100%>
<TR>
<TD ALIGN="middle"><FONT SIZE="+2" COLOR="<?php echo $H2COLOR;?>"><B>

<?php echo "$month_names[$thismonth], $thisday $thisyear"; ?>

</B></FONT>
<FONT SIZE="+1" COLOR="<?php echo $H2COLOR;?>">
<br>
<?php
  echo $phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]);
?>
</FONT>
</TD>
</TR>
</TABLE>

<TABLE BORDER="0" WIDTH="100%" CELLSPACING="0" CELLPADDING="0">
 <TR>
  <TD BGCOLOR="<?php echo $TABLEBG?>">
   <TABLE BORDER="0" WIDTH="100%" CELLSPACING="1" CELLPADDING="2" BORDER="0">
    <?php print_day_at_a_glance($now, $friendly); ?>
  </td>
 </TR>

</TABLE>
</TD></TR></TABLE>
</TD>
<TD VALIGN="top">
<?php
  if (! $friendly) {
     ?>
      <DIV ALIGN="right">
      <TABLE BORDER="0" CELLSPACING="0" CELLPADDING="0">
      <TR><TD BGCOLOR="<?php echo $TABLEBG?>">
      <TABLE BORDER="0" WIDTH="100%" CELLSPACING="1" CELLPADDING="2" BORDER="0">
       <TR><TH COLSPAN="7" BGCOLOR="<?php echo $THBG?>"><FONT SIZE="+4" COLOR="<?php echo $THFG?>"><?php echo $thisday?></FONT></TH></TR>
       <TR>
       <TD ALIGN="left" BGCOLOR="<?php echo $THBG?>"><A HREF="<?php echo $phpgw->link("day.php","date=$month_ago"); ?>" CLASS="monthlink">&lt;</A></TD>
       <TH COLSPAN="5" BGCOLOR="<?php echo $THBG?>"><FONT COLOR="<?php echo $THFG?>"><?php echo $month_names[$thismonth] . " $thisyear"; ?></FONT></TH>
       <TD ALIGN="right" BGCOLOR="<?php echo $THBG?>"><A HREF="<?php echo $phpgw->link("day.php","date=$month_ahead") ?>" CLASS="monthlink">&gt;</A></TD>
      </TR>
<?php
  echo "<TR>";
  if ($WEEK_START == 0)
     echo "<TD BGCOLOR=\"$TODAYCELLBG\"><FONT SIZE=\"-2\">" .

substr($weekday_names[0], 0, 2) . "</TD>";
for ($i = 1; $i < 7; $i++) {
    echo "<TD BGCOLOR=\"$TODAYCELLBG\"><FONT SIZE=\"-2\">" .
    substr($weekday_names[$i], 0, 2) . "</TD>";
}
if ($WEEK_START == 1)
   echo "<TD BGCOLOR=\"$TODAYCELLBG\"><FONT SIZE=\"-2\">" .
        substr($weekday_names[0], 0, 2) . "</TD>";
echo "</TR>\n";

// generate values for first day and last day of month
$monthstart = mktime(2, 0, 0, $thismonth, 1, $thisyear);
$monthend   = mktime(2, 0, 0, $thismonth + 1, 0, $thisyear);

if ($WEEK_START == "1")
   $wkstart = get_monday_before($thisyear, $thismonth, 1) + 7200;
else
   $wkstart = get_sunday_before($thisyear, $thismonth, 1) + 7200;

$wkend = $wkstart + (3600 * 24 * 7);

for ($i = $wkstart; date("Ymd", $i) <= date("Ymd", $monthend); $i += (24 * 3600 * 7)) {
    for ($i = $wkstart; date("Ymd", $i) <= date("Ymd", $monthend); $i += (24 * 3600 * 7)) {
        echo "<TR ALIGN=\"center\">\n";
        for ($j = 0; $j < 7; $j++) {
            $date = $i + ($j * 24 * 3600);
            if (date("Ymd", $date) >= date("Ymd", $monthstart) && date("Ymd", $date) <= date("Ymd", $monthend)) {
               if (date("Ymd", $date) == date("Ymd", $now))
                 echo "<TD BGCOLOR=\"$TODAYCELLBG\">";
               else
                 echo "<TD BGCOLOR=\"$TODAYCELLBG\">";
               echo "<FONT SIZE=\"-2\">";
               echo "<A HREF=\"".$phpgw->link("day.php","date=".date("Ymd",$date));
               echo "\" CLASS=\"monthlink\">" .
               date("d", $date) . "</A></FONT></TD>\n";
            } else {
               print "<TD BGCOLOR=\"$TODAYCELLBG\">&nbsp;</TD>\n";
            }
       }
       echo "</TR>\n";
   }
}
?>

</TABLE>
</TD></TR></TABLE>
</DIV>
<?php } ?>
</TD></TR></TABLE>

<?php
  if (! $friendly) {
     echo "<p><A HREF=\"".$phpgw->link("day.php");
     if ($thisyear)
        echo "&year=$thisyear&month=$thismonth&day=$thisday";

     ?>&friendly=1" TARGET="cal_printer_friendly" onMouseOver="window.status = '<?php echo lang("Generate printer-friendly version") . "'\">[" . lang("Printer Friendly"); ?>]</A>

<?php 
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
   } 
?>
