<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");

  if ($order)
      $ordermethod = "order by $order $sort";
   else
      $ordermethod = "order by app_title asc";

  if (! $sort)
     $sort = "desc";

  echo '<p><table border="0" width="45%" align="center"><tr bgcolor="'
     . $phpgw_info["theme"][bg_color] . '">'
     . '<td align="center" colspan=4><b>' . lang("Installed applications") . '</b></td></tr>'
     . '<tr><td colspan=4>&nbsp;</td></tr>';

  echo "<tr bgcolor=" . $phpgw_info["theme"]["th_bg"] . "><td>"
     . $phpgw->nextmatchs->show_sort_order($sort,"app_title",$order,"applications.php",lang("title")) . "</td><td>"
     . lang("Edit") . "</td> <td> " . lang("Delete") . " </td> <td> "
     . lang("Enabled") . " </td> <td></tr>";

  $phpgw->db->query("select * from applications $ordermethod");

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $name = $phpgw->db->f("app_title");

    if (! $phpgw->db->f("app_title")) $name = $phpgw->db->f("app_name");
    if (! $name)                      $name = "&nbsp;";

    echo "<tr bgcolor=$tr_color><td>$name</td><td width=5%><a href=\""
       . $phpgw->link("editapplication.php","app_name=" . urlencode($phpgw->db->f("app_name")))
       . "\"> " . lang("Edit") . " </a></td>";

    echo "<td width=5%><a href=\"" . $phpgw->link("deleteapplication.php",
         "app_name=" . urlencode($phpgw->db->f("app_name"))) . "\"> " . lang("Delete")
       . " </a></td>";

    echo  "<td width=5%>";
    if ($phpgw->db->f("app_enabled") == 1) {
       echo lang("Yes");
    } else {
       echo "<b>" . lang("No") . "</b>";
    }
    echo "</td></tr>\n";
  }

  echo "</form></table><form method=POST action=\"".$phpgw->link("newapplication.php")."\">"
     . "<table border=0 width=45% align=center><tr><td align=left><input type=\"submit\" "
     . "value=\"" . lang("Add") . "\"></td></tr></table></form>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
