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

  $phpgw_info["flags"]["disable_message_class"] = True;
  $phpgw_info["flags"]["disable_send_class"] = True;
  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");
  if ($submit) {
     if (! $n_loginid)
        $error = "<br>" . lang("You must enter a loginid");

     if (! $n_passwd)
        $error .= "<br>" . lang("You must enter a password");

     if ($n_passwd == $n_loginid)
        $error = "<br>" . lang("The login and password can not be the same");

     if ($n_passwd != $n_passwd_2)
        $error .= "<br>" . lang("The two passwords are not the same");

     if (count($new_permissions) == 0)
        $error .= "<br>" . lang("You must add at least 1 permission to this account");

     $phpgw->db->query("select loginid from accounts where loginid='$n_loginid'");
     $phpgw->db->next_record();
     if ($phpgw->db->f("loginid"))
        $error .= "<br>" . lang("That loginid has already been taken");

     if (! $error) {
        $phpgw->db->lock(array("accounts","preferences"));

        $phpgw->common->preferences_add($n_loginid,"maxmatchs","15");
        $phpgw->common->preferences_add($n_loginid,"theme","default");
        $phpgw->common->preferences_add($n_loginid,"tz_offset","0");
        $phpgw->common->preferences_add($n_loginid,"dateformat","m/d/Y");
        $phpgw->common->preferences_add($n_loginid,"timeformat","12");
        $phpgw->common->preferences_add($n_loginid,"lang","en");
        $phpgw->common->preferences_add($n_loginid,"addressbook_view_company","True");
        $phpgw->common->preferences_add($n_loginid,"addressbook_view_lastname","True");
        $phpgw->common->preferences_add($n_loginid,"addressbook_view_firstname","True");

        // Even if they don't have access to the calendar, we will add these.
        // Its better then the calendar being all messed up, they will be deleted
        // the next time the update there preferences.
        $phpgw->common->preferences_add($n_loginid,"weekstarts","Monday");
        $phpgw->common->preferences_add($n_loginid,"workdaystarts","9");
        $phpgw->common->preferences_add($n_loginid,"workdayends","17");

        while ($permission = each($new_permissions)) {
          if ($phpgw_info["apps"][$permission[0]]["enabled"]) {
             $phpgw->accounts->add_app($permission[0]);
          }
        }
        //$phpgw->permissions->add("hr");

        if ($n_anonymous && ! $n_admin)
	   $phpgwpermissions->add("anonymous");

          $sql = "insert into accounts (loginid,passwd,firstname,lastname,"
	       . "permissions,groups,status,lastpasswd_change) values ('$n_loginid'"
	       . ",'" . md5($n_passwd) . "','" . addslashes($n_firstname) . "','"
	       . addslashes($n_lastname) . "','" . $phpgw->accounts->add_app("",True)
	       . "','" . $phpgw->accounts->array_to_string("none",$n_groups) . "','A',0)";

          $phpgw->db->query($sql);
          $phpgw->db->unlock();

          $sep = $phpgw->common->filesystem_separator();

          $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

          if (! @mkdir($basedir . $n_loginid, 0707)) {
             $cd = 36;
          } else {
             $cd = 28;
          }

          Header("Location: " . $phpgw->link("accounts.php","cd=$cd"));
          exit;
     }
  }

     $phpgw->common->header();
     $phpgw->common->navbar();
     ?>
       <form method="POST" action="<?php echo $phpgw->link("newaccount.php"); ?>">
       <?php
         if ($error) {
            echo "<center>" . lang("Error") . ":$error</center>";
         }
       ?>
        <center>
         <table border=0 width=65%>
           <tr>
             <td><?php echo lang("LoginID"); ?></td>
             <td><input name="n_loginid" value="<?php echo $n_loginid; ?>"></td>
           </tr>
           <tr>
             <td><?php echo lang("Password"); ?></td>
             <td><input type="password" name="n_passwd" value="<?php echo $n_passwd; ?>"></td>
           </tr>
           <tr>
             <td><?php echo lang("Re-Enter Password"); ?></td>
             <td><input type="password" name="n_passwd_2" value="<?php echo $n_passwd_2; ?>"></td>
           </tr>
           <tr>
             <td><?php echo lang("First Name"); ?></td>
             <td><input name="n_firstname" value="<?php echo $n_firstname; ?>"></td>
           </tr>
           <tr>
             <td><?php echo lang("Last Name"); ?></td>
             <td><input name="n_lastname" value="<?php echo $n_lastname; ?>"></td>
           </tr>
           <tr>
             <td><?php echo lang("Groups"); ?></td>
             <td><select name="n_groups[]" multiple><?php
                   $phpgw->db->query("select * from groups");
                   while ($phpgw->db->next_record()) {
                     echo "<option value=\"" . $phpgw->db->f("group_id") . "\"";
                     if ($n_groups[$phpgw->db->f("group_id")]) {
                        echo " selected";
                     }
			 echo ">" . $phpgw->db->f("group_name") . "</option>";
                   }
                 ?>
                 </select></td>
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
                if ($new_permissions[$perm_display[$i][0]]) {
                   echo " checked";
                }
                echo "></td>";

                $i++;

                if (! $perm_display[$i][1]) break;

                echo '<td>' . lang($perm_display[$i][1]) . '</td>'
                   . '<td><input type="checkbox" name="new_permissions['
		   . $perm_display[$i][0] . ']" value="True"';
                if ($new_permissions[$perm_display[$i][0]]) {
                   echo " checked";
                }
	 	echo "></td></tr>";

                $i++;
             }

/*
           // Just until we can get thing the $phpgw_info["apps"] then figured out
	   echo "<tr><td>" . lang("Anonymous user") . "</td> <td><input type=\""
	      . "checkbox\" name=\"new_permissions[anonymous]\" value=\"Y\"></td>";

           echo "<td>" . lang("Manager") . "</td> <td><input type=\""
	      . "checkbox\" name=\"new_permissions[manager]\" value=\"Y\"></td></tr>";
*/	      

           ?>
           <tr>
             <td colspan=2>
              <input type="submit" name="submit" value="<?php echo lang("submit"); ?>">
             </td>
           </tr>
         </table>
        </center>
       </form>
     <?php
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
     ?>
