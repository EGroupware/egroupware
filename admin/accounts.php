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

  if (! $start)
     $start = 0;

  if ($order)
      $ordermethod = "order by $order $sort";
   else
      $ordermethod = "order by lastname,firstname,loginid asc";

  if (! $sort)
     $sort = "desc";

  if ($query) {
     $querymethod = " where firstname like '%$query%' OR lastname like '%$query%' OR loginid "
		  . "like '%$query%' ";
  }

  $phpgw->db->query("select count(*) from accounts $querymethod");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);

  echo '<p><table border="0" width="65%" align="center"><tr bgcolor="'
     . $phpgw_info["theme"][bg_color] . '">'
     . '<td align="left">' . $phpgw->nextmatchs->left("accounts.php",$start,$total)  . '</td>'
     . '<td align="center">' . lang_admin("user accounts") . '</td>'
     . '<td align="right">' . $phpgw->nextmatchs->right("accounts.php",$start,$total) . '</td>'
     . '</tr></table>';

  echo "<center><table border=0 width=65%>"
     . "<tr bgcolor=" . $phpgw_info["theme"]["th_bg"] . "><td>"
     . $phpgw->nextmatchs->show_sort_order($sort,"lastname",$order,"accounts.php",lang_common("last name")) . "</td><td>"
     . $phpgw->nextmatchs->show_sort_order($sort,"firstname",$order,"accounts.php",lang_common("first name")) . "</td><td>"
     . lang_common("Edit") . " </td> <td> " . lang_common("Delete") . " </td> <td> "
     . lang_common("View") . " </td></tr>\n";

  $phpgw->db->query("select con,firstname,lastname,loginid from accounts $querymethod "
	      . "$ordermethod limit $limit");

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
     . $phpgw->session->hidden_var() . "</table></center>"
     . "<table border=0 width=65% align=center><tr><td align=left><input type=\"submit\" "
     . "value=\"" . lang_common("Add") . "\"></form><form action=\"accounts.php\"></td>"
     . $phpgw->session->hidden_var() . "<td align=right>" . lang_common("search") . "&nbsp;"
     . "<input name=\"query\"></td></tr></form></table>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
