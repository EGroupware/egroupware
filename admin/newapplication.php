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

  if ($submit) {
     $phpgw_flags = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");
  if (! $submit) {
     ?>
       <p>
       <?php echo lang_admin("Add new application"); ?><hr><p>
       <form action="newapplication.php">
        <?php echo $phpgw->session->hidden_var(); ?>

        <table border="0" width="35%" align="center">
         <tr>
          <td><?php echo lang_admin("application name"); ?></td>
          <td><input name="n_app_name"></td>
         </tr>

         <tr>
          <td><?php echo lang_admin("application title"); ?></td>
          <td><input name="n_app_title"></td>
         </tr>

         <tr>
          <td><?php echo lang_admin("enabled"); ?></td>
          <td><input type="checkbox" name="n_app_enabled" value="1"></td>
         </tr>

         <tr>
          <td colspan="2" align="center"><input type="submit" name="submit" value="<?php echo lang_common("Add"); ?>"></td>
         </tr>
	</table>
       </form>
     <?php
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

  } else {
     $phpgw->db->query("insert into applications (app_name,app_title,app_enabled) values ('"
			 . addslashes($n_app_name) . "','" . addslashes($n_app_title) . "','"
			 . "$n_app_enabled')");

     Header("Location: " . $phpgw->link("applications.php"));
  }

