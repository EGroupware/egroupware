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
     $phpgw->common->phpgw_footer();
     $phpgw->common->phpgw_exit();
  }
?>
       <BR CLEAR="all">
       <HR CLEAR="all">
       <FONT SIZE="-1">

       <TABLE BORDER=0 WIDTH=100% CELLPADDING=0 CELLSPACING=0>
        <FORM ACTION="<?php echo $phpgw->link("index.php"); ?>" method="post" name="SelectMonth">
        <TR>
         <TD VALIGN="top" WIDTH=33%><FONT SIZE="-1">
          <B><?php echo lang("Month"); ?>:</B>
          <SELECT NAME="date" ONCHANGE="document.SelectMonth.submit()">
           <?php
             if ($phpgw->calendar->tempyear && $phpgw->calendar->tempmonth) {
                $m = $phpgw->calendar->tempmonth;
                $y = $phpgw->calendar->tempyear;
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
                 echo'"<option value="' . date("Ymd", $d) . '"';
                 if (date("Ymd", $d) == $thisdate)
                    echo " SELECTED";
                 echo ">" . lang(date("F", $d)) . strftime(" %Y", $d) . "</option>\n";
            }
          ?>
         </SELECT>
         <NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang("Go!"); ?>"></NOSCRIPT></FONT>
        </TD>
      </FORM>
      <FORM ACTION="<?php echo $phpgw->link("week.php"); ?>" method="post" name="SelectWeek">
      <TD VALIGN="top" align="center" WIDTH=33%><FONT SIZE="-1"><B><?php echo lang("Week"); ?>:</B>

      <SELECT NAME="date" ONCHANGE="document.SelectWeek.submit()">
       <?php
         if ($phpgw->calendar->tempyear && $phpgw->calendar->tempmonth) {
            $m = $phpgw->calendar->tempmonth;
            $y = $phpgw->calendar->tempyear;
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
         $sun = $phpgw->calendar->get_weekday_start($y,$m,$d);
         for ($i = -7; $i <= 7; $i++) {
             $tsun = $sun + (3600 * 24 * 7 * $i);
             $tsat = $tsun + (3600 * 24 * 6);
             echo "<OPTION VALUE=\"" . $phpgw->common->show_date($tsun,"Ymd") . "\"";
             if ($phpgw->common->show_date($tsun,"Ymd") <= $thisdate && $phpgw->common->show_date($tsat,"Ymd") >= $thisdate)
                echo " SELECTED";
             echo ">" . lang($phpgw->common->show_date($tsun,"F")) . " " . $phpgw->common->show_date($tsun,"d") . "-"
	            . lang($phpgw->common->show_date($tsat,"F")) . " " . $phpgw->common->show_date($tsat,"d");
            echo "</option>\n";
        }
      ?>
     </SELECT>

     <NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang("Go!"); ?>"></NOSCRIPT></FONT>
    </TD>
   </FORM>

<FONT SIZE="-1">

<FORM ACTION="<?php echo $phpgw->link("year.php"); ?>" method="post" name="SelectYear">

<TD VALIGN="top" align="right" WIDTH=33%><FONT SIZE="-1">
<B><?php echo lang("Year"); ?>:</B>

<SELECT NAME="year" ONCHANGE="document.SelectYear.submit()">
<?php
  if ($phpgw->calendar->tempyear) {
    $y = $phpgw->calendar->tempyear;
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

<NOSCRIPT><INPUT TYPE="submit" VALUE="<?php echo lang("Go!"); ?>"></NOSCRIPT>
</FONT></TD>
</FORM>

</TR>
</TABLE>
