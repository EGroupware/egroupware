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

  $phpgw_info = array();
  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "admin");
  include("../header.inc.php");
  include($phpgw_info["server"]["server_root"] . "/admin/inc/accounts_"
        . $phpgw_info["server"]["account_repository"] . ".inc.php");

  if (! $account_id) {
     Header("Location: " . $phpgw->link("accounts.php"));
  }

  if ($submit) {
     $totalerrors = 0;

     if ($phpgw_info["server"]["account_repository"] == "ldap") {
        if (strlen($n_loginid) > 8) {
           $error[$totalerrors++] = lang("The loginid can not be more then 8 characters");
        }
     }
    
     if ($old_loginid != $n_loginid) {
        if (account_exsists($n_loginid)) {
           $error[$totalerrors++] = lang("That loginid has already been taken");
        }
//        $c_loginid = $n_loginid;
//        $n_loginid = $old_loginid;
     }
  
     if ($n_passwd || $n_passwd_2) {
        if ($n_passwd != $n_passwd_2) {
           $error[$totalerrors++] = lang("The two passwords are not the same");
        }
        if (! $n_passwd){
           $error[$totalerrors++] = lang("You must enter a password");
        }
     }

     if (count($new_permissions) == 0){
        $error[$totalerrors++] = "<br>" . lang("You must add at least 1 permission to this account");
     }
     
     if (! $totalerrors) {
        $cd = account_edit(array("loginid"   => $n_loginid,   "permissions"    => $new_permissions,
                                 "firstname" => $n_firstname, "lastname"       => $n_lastname,
                        	     "passwd"    => $n_passwd,    "account_status" => $n_account_status,
                                 "old_loginid" => $old_loginid, "account_id"     => rawurldecode($account_id),
                                 "groups"    => $phpgw->accounts->groups_array_to_string($n_groups)));
     }

     Header("Location: " . $phpgw->link("accounts.php", "cd=$cd"));
     exit;
  }		// if $submit

  $phpgw->common->phpgw_header();
  $phpgw->common->navbar();
  
  $userData = $phpgw->accounts->read_userData($account_id);

  $db_perms = $phpgw->accounts->read_apps($userData["account_lid"]);

?>
    <form method="POST" action="<?php echo $phpgw->link("editaccount.php"); ?>">
      <input type="hidden" name="account_id" value="<? 
	if ($phpgw_info["server"]["account_repository"] == "ldap")
	{
		echo rawurlencode($userData["account_dn"]);
	}
	else
	{
		echo $userData["account_id"]; 
	}?>">
      <input type="hidden" name="old_loginid" value="<? echo $userData["account_lid"]; ?>">
<?php
  if ($error) {
    echo "<center>" . $phpgw->common->error_list($error) . "</center>";
  }
?>
      <center>
       <table border=0 width=65%>
        <tr>
         <td><?php echo lang("LoginID"); ?></td>
         <td><input name="n_loginid" value="<? echo $userData["account_lid"]; ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang("First Name"); ?></td>
         <td><input name="n_firstname" value="<?echo $userData["firstname"]; ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang("Last Name"); ?></td>
         <td><input name="n_lastname" value="<? echo $userData["lastname"]; ?>"></td>
        </tr>
        <tr>
           <td><?php echo lang("Groups"); ?></td>
           <td><select name="n_groups[]" multiple size="5">
<?php
            $user_groups = $phpgw->accounts->read_group_names($userData["account_lid"]);

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
           <td>
           	<input type="checkbox" name="n_account_status" value="A"
           		<?php if ($userData["status"] == "A") { echo " checked"; } ?> 
           	>
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
