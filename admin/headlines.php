<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");
  echo "<p><center>" . lang_admin("Headline Sites") . "<br><table border=0 width=65%>"
     . "<tr bgcolor=" . $theme["th_bg"] . "><td>" . lang_admin("Site") . "</td>"
     . "<td> " . lang_common("Edit") . " </td> <td> " . lang_common("Delete") . " </td> <td> "
     . lang_common("View") . " </td></tr>";
  $phpgw->db->query("select con,display from news_site order by "
	         . "display");

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    $display  = $phpgw->db->f("display");

    if (! $display)
       $display  = '&nbsp;';

    echo "<tr bgcolor=$tr_color><td>$display</td>"
       . "<td width=5%><a href=\"".$phpgw->link("editheadline.php",
         "con=".$phpgw->db->f("con"))."\"> ".lang_common("Edit")." </a></td>";

    echo "<td width=5%><a href=\"".$phpgw->link("deleteheadline.php",
         "con=".$phpgw->db->f("con"))."\"> ".lang_common("Delete")." </a></td>";
    echo  "<td width=5%><a href=\"".$phpgw->link("viewheadline.php",
         "con=".$phpgw->db->f("con"))."\"> ".lang_common("View")." </a> </td></tr>\n";
  }
  echo "<form method=POST action=\"".$phpgw->link("newheadline.php")."\">"
     . "<tr><td colspan=\"5\"><input type=\"submit\" value=\"".lang_common("Add")
     . "\"></td></tr></form></table></center>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
