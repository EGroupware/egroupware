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

  if (! $account_id) {
     $phpgw_info["flags"] = array("nonavbar" => True, "noheader" => True);
  }
  $phpgw_info["flags"]["currentapp"] = "admin";

  include("../header.inc.php");
  include($phpgw_info["server"]["server_root"] . "/admin/inc/accounts_"
        . $phpgw_info["server"]["auth_type"] . ".inc.php");

  if (! $account_id) {
     Header("Location: " . $phpgw->link("accounts.php"));
  }

  $phpgw->db->query("select account_lid from accounts where account_id='$account_id'");
  $phpgw->db->next_record();
  $loginid = $phpgw->db->f("account_lid");
  
  $db_perms = $phpgw->accounts->read_apps($loginid);
  $account_info = account_view($loginid);

  $phpgw->db->query("select account_lastlogin,account_lastloginfrom,account_status from accounts "
  				. "where account_id='$account_id'");
  $phpgw->db->next_record();
  
  $account_lastlogin      = $phpgw->db->f("account_lastlogin");
  $account_lastloginfrom  = $phpgw->db->f("account_lastloginfrom");
  $account_status	     = $phpgw->db->f("account_status");

  ?>
   <center>
   <p><table border=0 width=50%>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>">
     <td colspan="2">&nbsp;</td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_on"]; ?>">
     <td width="40%"><?php echo lang("LoginID"); ?></td>
     <td width="60%"><?php echo $loginid; ?></td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
     <td width="40%"><?php echo lang("First Name"); ?></td>
     <td width="60%"><?php echo $account_info["account_firstname"]; ?>&nbsp;</td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_on"]; ?>">
     <td width="40%"><?php echo lang("Last Name"); ?></td>
     <td width="60%"><?php echo $account_info["account_lastname"]; ?>&nbsp;</td>
    </tr>

    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
     <td width="40%"><?php echo lang("account_permissions"); ?>&nbsp;</td>
    <?php

      $i = 0;
      while ($permission = each($db_perms)) {
         if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
            $perm_display[$i] = lang($phpgw_info["apps"][$permission[0]]["title"]);
            $i++;
         }
      }

      echo "<td>" . implode(", ", $perm_display) . "</td></tr>";

      echo "<tr bgcolor=\"" . $phpgw_info["theme"]["row_on"] . "\"><td>"
	 . lang("account active") . "</td> <td>";
      if ($phpgw->db->f("account_status") == "A")
         echo lang("yes");
      else
         echo "<b>" . lang("no") . "</b>";

    ?></td></tr>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
      <td>Groups: </td>
      <td><?php
            $user_groups = $phpgw->accounts->read_group_names($phpgw->db->f("account_lid"));
	     
	     for ($i=0;$i<count($user_groups); $i++) {
                echo $user_groups[$i][1];
                if (count($user_groups) !=0 && $i != count($user_groups)-1)
                   echo ", ";
             }
      ?>&nbsp;</td>
    </tr>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_on"]; ?>">
     <td>Last login</td><td> <?php 

    if (! $account_lastlogin)
       echo "Never";
    else
       echo $phpgw->common->show_date($account_lastlogin);

   ?></td></tr>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["row_off"]; ?>">
      <td>Last login from</td>
      <td><?php echo $account_lastloginfrom; ?>&nbsp;</td>
    </tr>
    </table>
   </center>

<?php
  $phpgw->common->phpgw_footer();
?>
