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

  if ($friendly) {
     include($phpgw_info["server"]["core_include_root"]."/footer.inc.php");
     exit;
  }

?>

<BR CLEAR="all">
<HR CLEAR="all">
<FONT SIZE="-1">
<TABLE BORDER=0 WIDTH=100% CELLPADDING=0 CELLSPACING=0>
<FORM ACTION="<?php echo $phpgw->link("index.php"); ?>" method="post" name="SelectMonth">

<TR><TD VALIGN="top" WIDTH=33%><FONT SIZE="-1">
<B><?php echo lang_calendar("Month"); ?>:</B>

<SELECT NAME="date" ONCHANGE="document.SelectMonth.submit()">
<?php
  if ($thisyear && $thismonth) {
     $m = $thismonth;
     $y = $thisyear;
  } else {
     $m = date("m");
     $y = date("Y");
  }
  $d_time = mktime(0,0,0,$m,1,$y);
  $thisdate = date("Ymd", $d_time);
  $y--;
  for ($i = 0; $i < 25; $i++) {
    $m++;
    if ($m > 12) {
       $m = 1;
       $y++;
    }
    $d = mktime(0,0,0,$m,1,$y);
    echo "<OPTION VALUE=\"" . date("Ymd", $d) . "\"";
    if (date("Ymd", $d) == $thisdate)
      echo " SELECTED";
    echo ">" . lang_common(date("F", $d)) . strftime(" %Y", $d) . "</option>\n";
  }
?>
</SELECT>

<NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang_calendar("Go!"); ?>"></NOSCRIPT>
</FONT></TD>
</FORM>

<FORM ACTION="<?php echo $phpgw->link("week.php"); ?>" method="post" name="SelectWeek">

<TD VALIGN="top" align="center" WIDTH=33%><FONT SIZE="-1">
<B><?php echo lang_calendar("Week"); ?>:</B>

<SELECT NAME="date" ONCHANGE="document.SelectWeek.submit()">
<?php
  if ($thisyear && $thismonth) {
     $m = $thismonth;
     $y = $thisyear;
  } else {
     $m = date("m");
     $y = date("Y");
  }
  if ($thisday) {
     $d = $thisday;
  } else {
     $d = date ("d");
  }
  $d_time = mktime(0,0,0,$m,$d,$y);
  $thisdate = date("Ymd", $d_time);
  $wday = date("w", $d_time);
  $sun = mktime(0,0,0,$m,$d - $wday, $y);
  for ($i = -7; $i <= 7; $i++) {
    $tsun = $sun + (3600 * 24 * 7 * $i);
    $tsat = $tsun + (3600 * 24 * 6);
    echo "<OPTION VALUE=\"" . date("Ymd", $tsun) . "\"";
    if (date("Ymd", $tsun) <= $thisdate &&
       date("Ymd", $tsat) >= $thisdate)
       echo " SELECTED";
    echo ">" . lang_common(date("F",$tsun)) . strftime(" %d", $tsun) . "-"
	     . lang_common(date("F",$tsat)) . strftime(" %d", $tsat);
    echo "</option>\n";
  }
?>
</SELECT>

<NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang_calendar("Go!"); ?>"></NOSCRIPT>
</FONT></TD>
</FORM>

<FONT SIZE="-1">

<FORM ACTION="<?php echo $phpgw->link("year.php"); ?>" method="post" name="SelectYear">

<TD VALIGN="top" align="right" WIDTH=33%><FONT SIZE="-1">
<B><?php echo lang_calendar("Year"); ?>:</B>

<SELECT NAME="year" ONCHANGE="document.SelectYear.submit()">
<?php
  if ($thisyear) {
    $y = $thisyear;
  } else {
    $y = date("Y");
  }
  for ($i = ($y - 3); $i < ($y + 3); $i++) {
    echo "<OPTION VALUE=\"" . $i . "\"";
    if ($i == $y)
       echo " SELECTED";
    echo ">" . $i . "</option>\n";
  }
?>
</SELECT>

<NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang_calendar("Go!"); ?>"></NOSCRIPT>
</FONT></TD>
</FORM>

</TR>
</TABLE>
