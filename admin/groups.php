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

  if (! $start)
     $start = 0;

  if ($order)
      $ordermethod = "order by $order $sort";
   else
      $ordermethod = "order by group_name asc";

  if (! $sort)
     $sort = "asc";

  if ($query) {
     $querymethod = " where group_name like '%$query%'";
  }

  $phpgw->db->query("select count(*) from groups $querymethod");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);

  echo '<p><table border="0" width="45%" align="center"><tr bgcolor="'
     . $phpgw_info["theme"][bg_color] . '">'
     . '<td align="left">' . $phpgw->nextmatchs->left("groups.php",$start,$total)  . '</td>'
     . '<td align="center">' . lang("user groups") . '</td>'
     . '<td align="right">' . $phpgw->nextmatchs->right("groups.php",$start,$total) . '</td>'
     . '</tr></table>';

  echo "<table border=0 width=45% align=center>"
     . "<tr bgcolor=" . $phpgw_info["theme"]["th_bg"] . "><td>"
     . $phpgw->nextmatchs->show_sort_order($sort,"group_name",$order,"groups.php",
				 lang("name")) . "</td>"
     . "<td> " . lang("Edit") . " </td> <td> " . lang("Delete")
     . " </td> </tr>";

  $phpgw->db->query("select * from groups $querymethod $ordermethod limit $limit");
  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    $group_name = $phpgw->db->f("group_name");

    if (! $group_name)  $group_name  = '&nbsp;';

    echo "<tr bgcolor=$tr_color><td>$group_name</td>"
       . "<td width=5%><a href=\"" . $phpgw->link("editgroup.php","group_id=" . $phpgw->db->f("group_id"))
       . "\"> " . lang("Edit") . " </a></td>" . "<td width=5%><a href=\""
       . $phpgw->link("deletegroup.php","group_id=" . $phpgw->db->f("group_id"))
       . "\"> " . lang("Delete") . " </a></td>";
  }

  echo "\n<form method=POST action=\"".$phpgw->link("newgroup.php")."\">"
     . "</table></center>"
     . "<table border=0 width=45% align=center><tr><td align=left><input type=\"submit\" "
     . "value=\"" . lang("Add") . "\"></form><form action=\"".$phpgw->link("groups.php")."\"></td>"
     . "<td align=right>" . lang("search") . "&nbsp;"
     . "<input name=\"query\"></td></tr></form></table>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
