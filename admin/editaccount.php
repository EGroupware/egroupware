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
  if (! $account_id)
     Header("Location: " . $phpgw->link("accounts.php"));

  function change_owner($app,$table,$field,$new,$old)
  {
    global $phpgw, $phpgw_info;

    if ($phpgw_info["apps"][$app]["enabled"] || ! $app) {
       $phpgw->db->query("update $table set $field='$new' where $field='$old'");
    }
  }

  if ($submit) {
    $phpgw->db->query("select account_lid from accounts where account_id=$account_id");
    $phpgw->db->next_record();
    $lid = $phpgw->db->f("account_lid");

    if ($n_passwd || $n_passwd_2) {
      if ($n_passwd != $n_passwd_2){
        $error .= lang("The two passwords are not the same");
      }
      if (! $n_passwd){
        $error .= lang("You must enter a password");
      }
    } 

    if ($lid != $n_loginid) {
      $phpgw->db->query("select account_lid from accounts where account_lid='$n_loginid'");
      if ($phpgw->db->num_rows() != 0) {
        $error .= "<br>" . lang("That loginid has already been taken");
      }
    }

    if (count($new_permissions) == 0){
      $error .= "<br>" . lang("You must add at least 1 permission to this account");
    }
    if (! $error) {
      $phpgw->db->lock(array('accounts','preferences','sessions'));
	    if ($n_passwd) {
        $phpgw->db->query("update accounts set account_pwd='" . md5($n_passwd) . "', "
		              . "account_lastpwd_change='" . time() . "' where account_lid='" . "$lid'");
        $phpgw->db->query("update sessions set passwd='" . addslashes($n_passwd)
                        . "' where loginid='$lid'");
      }
      while ($permission = each($new_permissions)) {
        if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
          $phpgw->accounts->add_app($permission[0]);
        }
      }
      //$phpgw->permissions->add("hr");

      if ($new_permissions["anonymous"] && ! $new_permissions["admin"]){
	      $phpgw->accounts->add_app("anonymous");
	    }
      if (! $n_account_status){
        $n_account_status = "L";
      }
      $cd = 27;

      // If they changed there loginid, we need to change the owner in ALL
      // tables to reflect on the new one
      if ($lid != $n_loginid) {
        change_owner("","preferences","owner",$n_loginid,$lid);
        change_owner("addressbook","addressbook","owner",$n_loginid,$lid);
        change_owner("todo","todo","owner",$n_loginid,$lid);
        change_owner("","accounts","loginid",$n_loginid,$lid);
        change_owner("","sessions","loginid",$n_loginid,$lid);
        change_owner("calendar","webcal_entry","cal_create_by",$n_loginid,$lid);
        change_owner("calendar","webcal_entry_user","cal_login",$n_loginid,$lid);

        if ($lid <> $n_loginid) {
          $sep = $phpgw->common->filesystem_separator();
	
  	      $basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep
		        . "users" . $sep;

   	      if (! @rename($basedir . $lid, $basedir . $n_loginid)) {
	          $cd = 35;
          }
        }
      }

      $phpgw->db->query("update accounts set account_firstname='" . addslashes($n_firstname) . "',"
			       . " account_lastname='" . addslashes($n_lastname) . "', account_permissions='"
	  		       . $phpgw->accounts->add_app("",True) . "', account_status='"
			       . "$n_account_status', account_groups='"
  			       . $phpgw->accounts->array_to_string("none",$n_groups)
			       . "' where account_lid='$n_loginid'");

        $phpgw->db->unlock();
        Header("Location: " . $phpgw->link("accounts.php", "cd=$cd"));
        exit;
    }		// if ! $error
  }		// if $submit

  $phpgw->common->header();
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
		            if ($user_groups[$i][0] == $phpgw->db->f("group_id")){
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

/*
          echo "<tr><td>" . lang("Anonymous user") . "</td><td><input type=\""
	     . "checkbox\" name=\"new_permissions[anonymous]\" value=\"True\"";
	     if ($db_perms["anonymous"] || $new_permissions[anonymous])
		    echo " checked";
	      echo "></td>";

          echo "<td>" . lang("Manager") . "</td><td><input type=\""
	     . "checkbox\" name=\"new_permissions[manager]\" value=\"True\"";
	     if ($db_perms["manager"] || $new_permissions[manager])
		    echo " checked";
	     echo "></td></tr>";
*/
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
<?php include($phpgw_info["server"]["api_dir"] . "/footer.inc.php"); ?>
