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
  echo "<p><center>" . lang_admin("user accounts") . "<br><table border=0 width=65%>"
     . "<tr bgcolor=" . $theme["th_bg"] . "><td>" . lang_common("Last name") . "</td><td>"
     . lang_common("First name") . "</td><td> " . lang_common("Edit") . " </td> <td> "
     . lang_common("Delete") . " </td> <td> " . lang_common("View") . " </td></tr>\n";

  $phpgw->db->query("select con,firstname,lastname,loginid from accounts order by "
	      . "lastname, firstname");

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    $lastname  = $phpgw->db->f("lastname");
    $firstname = $phpgw->db->f("firstname");

    if (! $lastname)  $lastname  = '&nbsp;';
    if (! $firstname) $firstname = '&nbsp;';

    echo "<tr bgcolor=$tr_color><td>$lastname</td><td>$firstname</td>"
       . "<td width=5%><a href=\"" . $phpgw->link("editaccount.php",
         "con=" . $phpgw->db->f("con")) . "\"> " . lang_common("Edit") . " </a></td>";

    if ($phpgw->session->loginid != $phpgw->db->f("loginid"))
       echo "<td width=5%><a href=\"" . $phpgw->link("deleteaccount.php",
            "con=" . $phpgw->db->f("con")) . "\"> " . lang_common("Delete") . " </a></td>";
    else
       echo "<td width=5%>&nbsp;</td>";
    echo  "<td width=5%><a href=\"" . $phpgw->link("viewaccount.php",
          "con=" . $phpgw->db->f("con")) . "\"> " . lang_common("View") . " </a> </td></tr>\n";
  }
  echo "\n<form method=POST action=\"newaccount.php\">"
     . $phpgw->session->hidden_var()
     . "<tr><td colspan=5><input type=\"submit\" value=\"" . lang_common("Add")
     . "\"></td></tr></form></table></center>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
