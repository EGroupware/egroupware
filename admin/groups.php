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

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");
  echo "<p><center>" . lang_admin("User groups") . "<br><table border=0 width=35%>"
     . "<tr bgcolor=" . $theme["th_bg"] . "><td>" . lang_common("Name") . "</td>"
     . "<td> " . lang_common("Edit") . " </td> <td> " . lang_common("Delete")
     . " </td> </tr>";

  $phpgw->db->query("select * from groups order by group_name");
  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    $group_name = $phpgw->db->f("group_name");

    if (! $group_name)  $group_name  = '&nbsp;';

    echo "<tr bgcolor=$tr_color><td>$group_name</td>"
       . "<td width=5%><a href=\"" . $phpgw->link("editgroup.php","group_id=" . $phpgw->db->f("group_id"))
       . "\"> " . lang_common("Edit") . " </a></td>" . "<td width=5%><a href=\""
       . $phpgw->link("deletegroup.php","group_id=" . $phpgw->db->f("group_id"))
       . "\"> " . lang_common("Delete") . " </a></td>";
  }
  echo "<form method=POST action=\"newgroup.php\">"
     . $phpgw->session->hidden_var()
     . "<tr><td colspan=5><input type=\"submit\" value=\"" . lang_common("Add") . "\"></td></tr>"
     . "</form></table></center>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
