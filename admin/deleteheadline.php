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
     $phpgw_flags = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");

  function remove_account_data($query,$t)
  {
    global $phpgw->db;
    $phpgw->db->query("delete from $t where $query");
  }
  
  if (($con) && (! $confirm)) {
?>
     <center>
      <table border=0 with=65%>
       <tr colspan=2>
        <td align=center>
         <?php echo lang_admin("Are you sure you want to delete this news site ?"); ?>
        <td>
       </tr>
       <tr colspan=2>
        <td align=center>
         <?php echo lang_admin("All records and account information will be lost!"); ?>
        </td>
       </tr>
       <tr>
         <td>
           <a href="<?php echo $phpgw->link("headlines.php") . "\">" . lang_common("No"); ?></a>
         </td>
         <td>
           <a href="<?php echo $phpgw->link("deleteheadline.php","con=$con&confirm=true") . "\">" . lang_common("Yes"); ?></a>
         </td>
       </tr>
      </table>
     </center>
<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
  }
  else {
   $table_locks = array('news_site','news_headlines','users_headlines');
   $phpgw->db->lock($table_locks);

   remove_account_data("con=$con","news_site");
   remove_account_data("site=$con","news_headlines");
   remove_account_data("site=$con","users_headlines");
   $phpgw->db->unlock();

   Header("Location: " . $phpgw->link("headlines.php","cd=29"));
  }
