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

  if ($confirm || ! $account_id) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "admin";
  $phpgw_info["flags"]["disable_message_class"] = True;
  $phpgw_info["flags"]["disable_send_class"] = True;
  include("../header.inc.php");
  // Make sure they are not attempting to delete there own account.
  // If they are, they should not reach this point anyway.
  if ($phpgw_info["user"]["account_id"] == $account_id) {
     Header("Location: " . $phpgw->link("accounts.php"));
     exit;
  }

  if (($account_id) && (! $confirm)) {
     ?>
     <center>
      <table border=0 with=65%>
       <tr colspan=2>
        <td align=center>
         <?php echo lang("Are you sure you want to delete this account ?"); ?>
        <td>
       </tr>
       <tr colspan=2>
        <td align=center>
         <?php echo lang("All records and account information will be lost!"); ?>
        </td>
       </tr>
       <tr>
         <td>
           <a href="<?php echo $phpgw->link("accounts.php") . "\">" . lang("No"); ?></a>
         </td>
         <td>
           <a href="<?php echo $phpgw->link("deleteaccount.php","account_id=$account_id&confirm=true") . "\">" . lang("Yes"); ?></a>
         </td>
       </tr>
      </table>
     </center>
     <?php
     $phpgw->common->phpgw_footer();
  }

  if ($confirm) {
     $phpgw->db->query("select account_lid from accounts where account_id=$account_id");
     $phpgw->db->next_record();
     $lid = $phpgw->db->f(0);

     $i = 0;
     $phpgw->db->query("select cal_id from webcal_entry where cal_create_by='$lid'");
     while ($phpgw->db->next_record()) {
       $cal_id[$i] = $phpgw->db->f("cal_id");
       echo "<br>" . $phpgw->db->f("cal_id");
       $i++;
     }

     $table_locks = array('preferences','todo','addressbook','accounts',
                          'webcal_entry','webcal_entry_user','webcal_entry_repeats',
                          'webcal_entry_groups');
     $phpgw->db->lock($table_locks);

     for ($i=0; $i<count($cal_id); $i++) {
        $phpgw->db->query("delete from webcal_entry_repeats where cal_id='$cal_id[$i]'");
        $phpgw->db->query("delete from webcal_entry_groups where cal_id='$cal_id[$i]'");
     }

     $phpgw->db->query("delete from webcal_entry where cal_create_by='$lid'");
     $phpgw->db->query("delete from webcal_entry_user where cal_login='$lid'");

     $phpgw->db->query("delete from todo where todo_owner='$lid'");
     $phpgw->db->query("delete from addressbook where ab_owner='$lid'");
     $phpgw->db->query("delete from accounts where account_lid='$lid'");
     //$phpgw->db->query("delete from users_headlines where owner='$lid'");
     //$phpgw->db->query("delete from profiles where owner='$lid'");
     
     $phpgw->common->preferences_delete("all",$lid);

     $phpgw->db->unlock();

     $sep = $phpgw->common->filesystem_separator();

     //$basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep . "users"
     //         . $sep;
          $basedir = $phpgw_info["server"]["files_dir"] . $sep . "users" . $sep;

//echo "<h1> rmdir:".$basedir . $lid."</h1>\n";
     if (! @rmdir($basedir . $lid)) {
        $cd = 34;
     } else {
        $cd = 29;
     }

     Header("Location: " . $phpgw->link("accounts.php","cd=$cd"));
  }
?>
