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
  include($phpgw_info["server"]["server_root"] . "/admin/inc/accounts_"
        . $phpgw_info["server"]["auth_type"] . ".inc.php");
  
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
     $cd = account_delete($account_id);

     Header("Location: " . $phpgw->link("accounts.php","cd=$cd"));
  }
?>
