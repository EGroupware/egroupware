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

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);

  $phpgw_info["flags"]["currentapp"] = "admin";
  $phpgw_info["flags"]["disable_message_class"] = True;
  $phpgw_info["flags"]["disable_send_class"] = True;
  include("../header.inc.php");
  include($phpgw_info["server"]["server_root"] . "/admin/inc/accounts_"
        . $phpgw_info["server"]["auth_type"] . ".inc.php");

  if (! $account_id) {
     Header("Location: " . $phpgw->link("accounts.php"));
  }

  if ($submit) {
     if ($old_loginid != $n_loginid) {
        if (account_exsists($n_loginid)) {
           $error .= "<br>" . lang("That loginid has already been taken");
        }
        $c_loginid = $n_loginid;
        $n_loginid = $old_loginid;
     }
  
     if ($n_passwd || $n_passwd_2) {
        if ($n_passwd != $n_passwd_2) {
           $error .= lang("The two passwords are not the same");
        }
        if (! $n_passwd){
           $error .= lang("You must enter a password");
        }
     }

     if (count($new_permissions) == 0){
        $error .= "<br>" . lang("You must add at least 1 permission to this account");
     }
     
     if (! $error) {  
        $cd = account_edit(array("loginid"   => $n_loginid,   "permissions"    => $new_permissions,
        				         "firstname" => $n_firstname, "lastname"       => $n_lastname,
        				         "passwd"    => $n_passwd,    "account_status" => $account_status,
        				         "c_loginid" => $c_loginid,
        				         "groups"    => $phpgw->accounts->groups_array_to_string($n_groups)));
     }

     Header("Location: " . $phpgw->link("accounts.php", "cd=$cd"));
     exit;
  }		// if $submit

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();
  
  $phpgw->db->query("select account_lid from accounts where account_id=$account_id");
  $phpgw->db->next_record();
  $db_perms = $phpgw->accounts->read_apps($phpgw->db->f("account_lid"));

  $phpgw->db->query("select * from accounts where account_id=$account_id");
  $phpgw->db->next_record();
  $account_status = $phpgw->db->f("account_status");
?>
    <form method="POST" action="<?php echo $phpgw->link("editaccount.php"); ?>">
      <input type="hidden" name="account_id" value="<? echo $account_id; ?>">
      <input type="hidden" name="old_loginid" value="<? echo $phpgw->db->f("account_lid"); ?>">
<?php
  if ($error) {
    echo "<center>" . lang("Error") . ":$error</center>";
  }
?>
      <center>
       <table border=0 width=65%>
        <tr>
         <td><?php echo lang("LoginID"); ?></td>
         <td><input name="n_loginid" value="<? echo $phpgw->db->f("account_lid"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang("First Name"); ?></td>
         <td><input name="n_firstname" value="<?echo $phpgw->db->f("account_firstname"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang("Last Name"); ?></td>
         <td><input name="n_lastname" value="<? echo $phpgw->db->f("account_lastname"); ?>"></td>
        </tr>
        <tr>
           <td><?php echo lang("Groups"); ?></td>
           <td><select name="n_groups[]" multiple size="5">
<?php
            $user_groups = $phpgw->accounts->read_group_names($phpgw->db->f("account_lid"));

            $phpgw->db->query("select * from groups");
            while ($phpgw->db->next_record()) {
              echo "<option value=\"" . $phpgw->db->f("group_id") . "\"";
              for ($i=0; $i<count($user_groups); $i++) {
		if ($user_groups[$i][0] == $phpgw->db->f("group_id")) {
                  echo " selected";
                }
	      }
	      echo ">" . $phpgw->db->f("group_name") . "</option>\n";
            }
?>
            </select>
          </tr>
<?php
            $i = 0;
            while ($permission = each($phpgw_info["apps"])) {
              if ($permission[1]["enabled"]) {
                $perm_display[$i][0] = $permission[0];
                $perm_display[$i][1] = $permission[1]["title"];
                $i++;
              }
	       }

            for ($i=0;$i<200;) {		// The $i<200 is only used for a brake
              if (! $perm_display[$i][1]) break;
              echo '<tr><td>' . lang($perm_display[$i][1]) . '</td>'
                . '<td><input type="checkbox" name="new_permissions['
		            . $perm_display[$i][0] . ']" value="True"';
              if ($new_permissions[$perm_display[$i][0]] || $db_perms[$perm_display[$i][0]]) {
                 echo " checked";
              }
              echo "></td>";
              $i++;
              if (! $perm_display[$i][1]) break;
              echo '<td>' . lang($perm_display[$i][1]) . '</td>'
                . '<td><input type="checkbox" name="new_permissions['
		            . $perm_display[$i][0] . ']" value="True"';
              if ($new_permissions[$perm_display[$i][0]] || $db_perms[$perm_display[$i][0]]) {
                echo " checked";
              }
	 	          echo "></td></tr>";
              $i++;
            }
?>
          <tr>
           <td><?php echo lang("Account active"); ?></td>
           <td><input type="checkbox" name="n_account_status" value="A" <?php if ($account_status == "A") { echo " checked"; } ?>>
          </td>
          </tr>
          <tr>
           <td><?php echo lang("New password [ Leave blank for no change ]"); ?></td>
           <td><input type=password name="n_passwd"></td>
          </tr>
          <tr>
           <td><?php echo lang("Re-enter password"); ?></td>
           <td><input type=password name="n_passwd_2"></td>
          </tr>
          <tr>
          <td colspan=2><input type="submit" name="submit" value="<?php echo lang("submit"); ?>"></td>
          </tr>
         </table>
        </center>
       </form>
<?php
  account_close();
  $phpgw->common->phpgw_footer();
?>
