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
  if (!$friendly){
    $phpgw_info["flags"]["noheader"]="True";
  }

  $phpgw_info["flags"]["currentapp"] = "calendar";
  include("../header.inc.php");
  
  if (! $friendly) {
     $phpgw->common->phpgw_header();
  } else
     echo "<body bgcolor=\"".$phpgw_info["theme"][bg_color]."\">";

  $view = "week";
?>

<STYLE TYPE="text/css">
  .tablecell {
    width: 80px;
    height: 80px;
  }
</STYLE>
</HEAD>

<?php
  if (strlen($date) > 0) {
     $thisyear  = substr($date, 0, 4);
     $thismonth = substr($date, 4, 2);
     $thisday   = substr($date, 6, 2);
  } else {
     if ($month == 0)
        $thismonth = date("m");
     else
        $thismonth = $month;

     if ($year == 0)
        $thisyear = date("Y");
     else
        $thisyear = $year;

     if ($day == 0)
        $thisday = date("d");
     else
        $thisday = $day;
  }

  $next = mktime(2, 0, 0, $thismonth, $thisday + 7, $thisyear);
  $nextyear = date("Y", $next);
  $nextmonth = date("m", $next);
  $nextday = date("d", $next);

  $prev = mktime(2, 0, 0, $thismonth, $thisday - 7, $thisyear);
  $prevyear = date("Y", $prev);
  $prevmonth = date("m", $prev);
  $prevday = date("d", $prev);

  // We add 2 hours on to the time so that the switch to DST doesn't
  // throw us off.  So, all our dates are 2AM for that day.
  $sun = get_sunday_before($thisyear, $thismonth, $thisday) + 7200;
  $sat = $sun + (3600 * 24 * 7);
?>

<TABLE BORDER=0 WIDTH=100%>
<TR>
<?php if (! $friendly) { ?>
<TD ALIGN="left"><A HREF="week.php?sessionid=<?php echo $phpgw_info["user"]["sessionid"]; ?>&year=<?php echo $prevyear;?>&month=<?php echo $prevmonth;?>&day=<?php echo $prevday;?>">&lt;&lt;</A></TD>
<?php } ?>
<TD ALIGN="middle"><FONT SIZE="+2" COLOR="<?php echo $H2COLOR;?>"><B>
<?php
  if (date("m", $sun) == date("m", $sat)) {
     echo strftime("%b %d", $sun) . " - " . strftime("%d, %Y", $sat);
  } else {
     if (date("Y", $sun) == date("Y", $sat)) {
        echo strftime("%b %d", $sun) . " - " .
        strftime("%b %d, %Y", $sat);
     } else {
        echo strftime("%b %d, %Y", $sun) . " - " .
        strftime("%b %d, %Y", $sat);
     }
  }
?>
</B></FONT>
<FONT SIZE="+1" COLOR="<?php echo $H2COLOR;?>">
<?php
  $phpgw->db->query("SELECT account_lastname, account_firstname FROM accounts WHERE account_lid='"
	      . $phpgw_info["user"]["account_lid"]. "'");
  echo "<BR>\n";
  if ($phpgw->db->next_record()) {
     if (strlen($phpgw->db->f(0)) || strlen($phpgw->db->f(1))) {
        if (strlen($phpgw->db->f(1)))
           echo $phpgw->db->f(1) . " ";
           if (strlen($phpgw->db->f(0)))
              echo $phpgw->db->f(0) . " ";
     } else
       echo $user;
     }
?>
</FONT>
</TD>
<?php 
  if (! $friendly) {
     echo "<TD ALIGN=\"right\"><A HREF=\"" . $phpgw->link("week.php","&year=$nextyear&month=$nextmonth&day=$nextday")
        . "\">&gt;&gt;</A></TD>";
  }
?>
</TR>
</TABLE>

<TABLE WIDTH=100% BORDER=0 bordercolor=FFFFFF cellspacing=2 cellpadding=2>

<TR>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Sun"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Mon"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Tue"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Wed"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Thu"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Fri"); ?></FONT></TH>
<TH WIDTH=14% BGCOLOR="<?php echo $phpgw_info["theme"]["th_bg"]; ?>"><FONT COLOR="#000000"><?php echo lang("Sat"); ?></FONT></TH>
</TR>

<TR>
<?php
  // Pre-Load the repeated events
  $repeated_events = read_repeated_events($phpgw_info["user"]["userid"]);

  $today = mktime(2,0,0,date("m"), date("d"), date("Y"));
  for ($j = 0; $j < 7; $j++) {
    $date = $sun + ($j * 24 * 3600);
    $CELLBG = $phpgw->nextmatchs->alternate_row_color($CELLBG);

    echo "<TD VALIGN=\"top\" WIDTH=75 HEIGHT=75 ID=\"tablecell\"";
    if (date("Ymd", $date) == date("Ymd", $today))
       echo "BGCOLOR=\"".$phpgw_info["theme"][cal_today]."\">";
    else
       echo "BGCOLOR=\"$CELLBG\">";

    print_date_entries($date,$hide_icons,$phpgw_info["user"]["sessionid"]);

    //$date = $i + ($j * 24 * 3600);
/*    $thirsday=$j+24*3600*4;
    if ($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] == "Sunday" && $j == 0) {
       echo '<font size=-2><a href="' . $phpgw->link("week.php","date=" . date("Ymd",$date)) . '">week ' .(int)((date("z",$thirsday)+7)/7) . '</a></font>';
    }
    if ($phpgw_info["user"]["preferences"]["calendar"]["weekdaystarts"] == "Monday" && $j == 1) {
       echo '<font size=-2><a href="' . $phpgw->link("week.php","date=" . date("Ymd",$date)) . '">week ' . (int)((date("z",$thirsday)+7)/7) . '</a></font>';
    }*/
    
    echo "</TD>\n";
  }

?>
</TR>

</TABLE>

<?php
  if ($thisyear) {
     $yeartext = "year=$thisyear&month=$thismonth&day=$thisday";
  }

  if (! $friendly) {
     echo "<P>&nbsp;<A HREF=\"" . $phpgw->link("week.php","$yeartext&friendly=1");
     ?>" TARGET="cal_printer_friendly" onMouseOver="window.status = '<?php echo lang("Generate printer-friendly version"); ?>'">[<?php echo lang("Printer Friendly"); ?>]</A>
     <?php
  }

  $phpgw->common->phpgw_footer();
?>
