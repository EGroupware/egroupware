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

  if ($confirm) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");
  // Make sure they are not attempting to delete there own account.
  // If they are, they should not reach this point anyway.
  if ($phpgw_info["user"]["con"] == $con) {
     Header("Location: " . $phpgw->link("accounts.php"));
     exit;
  }

  if (($con) && (! $confirm)) {
     ?>
     <center>
      <table border=0 with=65%>
       <tr colspan=2>
        <td align=center>
         <?php echo lang_admin("Are you sure you want to delete this account ?"); ?>
        <td>
       </tr>
       <tr colspan=2>
        <td align=center>
         <?php echo lang_admin("All records and account information will be lost!"); ?>
        </td>
       </tr>
       <tr>
         <td>
           <a href="<?php echo $phpgw->link("accounts.php") . "\">" . lang_common("No"); ?></a>
         </td>
         <td>
           <a href="<?php echo $phpgw->link("deleteaccount.php","con=$con&confirm=true") . "\">" . lang_common("Yes"); ?></a>
         </td>
       </tr>
      </table>
     </center>
     <?
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
  }

  if ($confirm) {
     $phpgw->db->query("select loginid from accounts where con='$con'");
     $phpgw->db->next_record();
     $lid = $phpgw->db->f(0);

     $phpgw->db->query("select cal_id from webcal_entry where cal_create_by='$lid'");
     while ($phpgw->db->next_record()) {
       $cal_id[$i] = $phpgw->db->f("cal_id");
       echo "<br>" . $phpgw->db->f("cal_id");
       $i++;
     }

     $table_locks = array('preferences','todo','addressbook','accounts','users_headlines',
                          'webcal_entry','webcal_entry_user','webcal_entry_repeats',
                          'webcal_entry_groups');
     $phpgw->db->lock($table_locks);

     for ($i=0; $i<count($cal_id); $i++) {
        $phpgw->db->query("delete from webcal_entry_repeats where cal_id='$cal_id[$i]'");
        $phpgw->db->query("delete from webcal_entry_groups where cal_id='$cal_id[$i]'");
     }

     $phpgw->db->query("delete from webcal_entry where cal_create_by='$lid'");
     $phpgw->db->query("delete from webcal_entry_user where cal_login='$lid'");

     $phpgw->db->query("delete from preferences where owner='$lid'");
     $phpgw->db->query("delete from todo where owner='$lid'");
     $phpgw->db->query("delete from addressbook where owner='$lid'");
     $phpgw->db->query("delete from accounts where loginid='$lid'");
     $phpgw->db->query("delete from users_headlines where owner='$lid'");
     //$phpgw->db->query("delete from profiles where owner='$lid'");

     $phpgw->db->unlock();

     $sep = $phpgw->common->filesystem_separator();

     $basedir = $phpgw_info["server"]["server_root"] . $sep . "filemanager" . $sep . "users"
              . $sep;

     if (! @rmdir($basedir . $lid)) {
        $cd = 34;
     } else {
        $cd = 29;
     }

     Header("Location: " . $phpgw->link("accounts.php","cd=$cd"));
  }
?>