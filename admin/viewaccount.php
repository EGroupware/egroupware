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

  $phpgw_flags["currentapp"] = "admin";

  include("../header.inc.php");
  if (! $con)
     Header("Location: accounts.php?sessionid=" . $phpgw->session->id);

  $phpgw->db->query("select * from accounts where con='$con'");
  $phpgw->db->next_record();

  $db_perms = $phpgw->permissions->read_other($phpgw->db->f("loginid"));

  ?>
   <center>
   <p><table border=0 width=50%>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>">
     <td colspan="2">&nbsp;</td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_on"]; ?>">
     <td width="40%"><?php echo lang_admin("LoginID"); ?></td>
     <td width="60%"><?php echo $phpgw->db->f("loginid"); ?></td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
     <td width="40%"><?php echo lang_common("First Name"); ?></td>
     <td width="60%"><?php echo $phpgw->db->f("firstname"); ?></td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_on"]; ?>">
     <td width="40%"><?php echo lang_common("Last Name"); ?></td>
     <td width="60%"><?php echo $phpgw->db->f("lastname"); ?></td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
     <td width="40%"><?php echo lang_admin("permissions"); ?></td>
    <?php

      $i = 0;
      while ($permission = each($db_perms)) {
         if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
            $perm_display[$i] = lang_common($phpgw_info["apps"][$permission[0]]["title"]);
            $i++;
         }
      }

      echo "<td>" . implode(", ", $perm_display) . "</td></tr>";

      echo "<tr bgcolor=\"" . $phpgw_info["theme"]["row_on"] . "\"><td>"
	 . lang_admin("account active") . "</td> <td>";
      if ($phpgw->db->f("status") == "A")
         echo lang_common("yes");
      else
         echo "<b>" . lang_common("no") . "</b>";

    ?></td></tr>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
      <td>Groups: </td>
      <td><?php
            $user_groups = $phpgw->groups->read_names($phpgw->db->f("loginid"));
	     
	     for ($i=0;$i<count($user_groups); $i++) {
                echo $user_groups[$i][1];
                if (count($user_groups) !=0 && $i != count($user_groups)-1)
                   echo ", ";
             }
      ?>&nbsp;</td>
    </tr>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_on"]; ?>">
     <td>Last login</td><td> <?php 

    if (! $phpgw->db->f("lastlogin"))
       echo "Never";
    else
       echo $phpgw->preferences->show_date($phpgw->db->f("lastlogin"));

   ?></td></tr>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
      <td>Last login from</td>
      <td><?php echo $phpgw->db->f("lastloginfrom"); ?>&nbsp;</td>
    </tr>
    </table>
   </center>

<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

