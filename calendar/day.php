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

  $phpgw_info["flags"] = array("currentapp" => "calendar", "enable_calendar_class" => True, "enable_nextmatchs_class" => True);
  include("../header.inc.php");

  $view = "day";

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

  $now	= $phpgw->calendar->splitdate(mktime (2, 0, 0, $thismonth, $thisday, $thisyear));

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"]["bg_color"]."\">";
  }

?>
<table border="0" width="100%">
 <tr>
  <td valign="top" width="70%">
   <tr>
    <td>
     <table border="0" width=100%>
      <tr>
       <td align="middle">
        <font size="+2" color="<?php echo $phpgw_info["theme"]["bg_text"]; ?>">
         <b>
<?php
  $m = mktime(2,0,0,$thismonth,1,$thisyear);
  echo lang(strftime("%B",$m)) . " " .$thisday . ", " . $thisyear;
?>
         </b>
        </font>
        <font size="+1" color="<?php echo $phpgw_info["theme"]["bg_text"]; ?>"><br>
<?php
  echo $phpgw->common->display_fullname($phpgw_info["user"]["userid"],$phpgw_info["user"]["firstname"],$phpgw_info["user"]["lastname"]);
?>
        </font>
       </td>
      </tr>
     </table>
     <table border="0" width="100%" cellspacing="0" cellpadding="0">
      <tr>
       <td bgcolor="<?php echo $phpgw_info["theme"]["bg_text"]; ?>">
        <table border="0" width="100%" cellspacing="1" cellpadding="2" border="0">
           <?php echo $phpgw->calendar->print_day_at_a_glance($now); ?>
          </td>
         </tr>
        </table>
       </td>
      </tr>
    </table>
   </td>
 <td valign="top" align="right">
<?php echo $phpgw->calendar->pretty_small_calendar($now["day"],$now["month"],$now["year"],"day.php"); ?>
  </td>
 </tr>
</table>
<?php
  if (! $friendly) {
     $param = "";
//     if ($thisyear)
        $param .= "year=".$now["year"]."&month=".$now["month"]."&day=".$now["day"]."&";

     $param .= "friendly=1\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	. "status = '" . lang("Generate printer-friendly version"). "'";
     echo "<a href=\"".$phpgw->link($PHP_SELF,$param)."\">";
     echo "[". lang("Printer Friendly") . "]</A>";
     $phpgw->common->phpgw_footer();
  }
?>
