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
  $show_maxlog = 30;
?>
  <br>
  <table border="0" align="center" width="75%">
   <tr>
    <td bgcolor="<?php echo $phpgw_info["theme"][th_bg]; ?>" align="center" colspan="5">
      <?php echo lang_admin("Last x logins",$show_maxlog); ?>
    </td>
   </tr>
   <tr bgcolor="<?php echo $phpgw_info["theme"][th_bg]; ?>">
    <td><?php echo lang_admin("LoginID"); ?></td>
    <td><?php echo lang_admin("IP"); ?></td>
    <td><?php echo lang_common("Login"); ?></td>
    <td><?php echo lang_common("Logout"); ?></td>
    <td><?php echo lang_common("Total"); ?></td>
   </tr>
   <?php

  $phpgw->db->query("select loginid,ip,li,lo from access_log order by li desc "
	         . "limit $show_maxlog");
  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    // In case there was a problem creating there session. eg, bad login time
    // I still want it to be printed here.  This will alert the admin there
    // is a problem.
    if ($phpgw->db->f("li") && $phpgw->db->f("lo")) {
       $total = $phpgw->db->f("lo") - $phpgw->db->f("li");
       if ($total > 86400 && $total > 172800)
          $total = gmdate("z \d\a\y\s - G:i:s",$total);
       else if ($total > 172800)
          $total = gmdate("z \d\a\y - G:i:s",$total);
       else
          $total = gmdate("G:i:s",$total);
    } else
       $total = "&nbsp;";

    if ($phpgw->db->f("li"))
       $li = $phpgw->preferences->show_date($phpgw->db->f("li"));
    else
       $li = "&nbsp;";

    if ($phpgw->db->f("lo"))
       $lo = $phpgw->preferences->show_date($phpgw->db->f("lo"));
    else
       $lo = "&nbsp;";

    echo "<tr bgcolor=$tr_color><td>" . $phpgw->db->f("loginid") . "</td><td>"
       . $phpgw->db->f("ip") . "</td><td>$li</td><td>$lo</td><td>$total</td></tr>";
  }

  $phpgw->db->query("select count(*) from access_log");
  $phpgw->db->next_record();
  $total = $phpgw->db->f(0);

  $phpgw->db->query("select count(*) from access_log where lo!='0'");
  $phpgw->db->next_record();
  $loggedout = $phpgw->db->f(0);

  $percent = round((10000 * ($loggedout / $total)) / 100);

  echo "<tr bgcolor=" . $theme["bg_color"] . "><td colspan=5 align=left>"
     . lang_admin("Total records") . ": $total</td></tr>";

  echo "<tr bgcolor=" . $theme["bg_color"] . "><td colspan=5 align=left>"
     . lang_admin("Percent of users that logged out") . ": $percent%</td></tr></table>";

  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

