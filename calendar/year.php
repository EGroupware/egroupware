<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($friendly) {
     $phpgw_flags["noheader"] = True;
  }

  $phpgw_flags["currentapp"] = "calendar";
  include("../header.inc.php");

  if ($friendly) {
     echo "<body bgcolor=\"".$phpgw_info["theme"][bg_color]."\">";
  }
?>

<center>
<table border="0" cellspacing="4" cellpadding="4">
 <tr>
  <?php
    if (! $friendly)
       echo "<td align=\"left\"><A HREF=\"year.php?sessionid=" . $phpgw->session->id
	  . "&year=" . ($year - 1) . "\">&lt;&lt;</A>";
  ?>
  </td>
  </td>
  <td colspan="<?php echo ($friendly?"6":"4"); ?>" align="center">
   <font face=\"".$phpgw_info["theme"][font]."\" size="+1"><? echo $year; ?></font>
  </td>
  <?php
    if (! $friendly)
       echo "<td align=\"right\"><A HREF=\"year.php?sessionid=" . $phpgw->session->id
	  . "&year=" . ($year + 1) . "\">&gt;&gt;</A>";
  ?>
  </td>
 </tr>
 <tr>
  <td valign="top"><? display_small_month(1,$year,False); ?></td>
  <td valign="top"><? display_small_month(2,$year,False); ?></td>
  <td valign="top"><? display_small_month(3,$year,False); ?></td>
  <td valign="top"><? display_small_month(4,$year,False); ?></td>
  <td valign="top"><? display_small_month(5,$year,False); ?></td>
  <td valign="top"><? display_small_month(6,$year,False); ?></td>
 </tr>
 <tr>
  <td valign="top"><? display_small_month(7,$year,False); ?></td>
  <td valign="top"><? display_small_month(8,$year,False); ?></td>
  <td valign="top"><? display_small_month(9,$year,False); ?></td>
  <td valign="top"><? display_small_month(10,$year,False); ?></td>
  <td valign="top"><? display_small_month(11,$year,False); ?></td>
  <td valign="top"><? display_small_month(12,$year,False); ?></td>
 </tr>
</table>
</center>

<?php
  if (! $friendly) {
     echo "&nbsp;<A HREF=\"year.php?sessionid=" . $phpgw->session->id . "&friendly=1&"
	. "&year=$year\"TARGET=\"cal_printer_friendly\" onMouseOver=\"window."
	. "status = '" . lang_calendar("Generate printer-friendly version") . "'\">["
	. lang_calendar("Printer Friendly") . "]</A>";
  }
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");


