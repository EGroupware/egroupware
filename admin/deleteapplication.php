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

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "admin");

  if (! $app_name)
     Header("Location: " . $phpgw->link("applications.php"));

  include("../header.inc.php");

  if ($confirm) {
        $phpgw->db->query("delete from applications where app_name='$app_name'");

        Header("Location: " . $phpgw->link("applications.php"));
        $phpgw->common->phpgw_exit();
  }

  $phpgw->common->phpgw_header();
  echo parse_navbar();
  ?>
     <center>
      <table border=0 with=65%>
       <tr colspan=2>
        <td align=center>
         <?php echo lang("Are you sure you want to delete this application ?"); ?>
        <td>
       </tr>
       <tr>
         <td>
           <a href="<?php echo $phpgw->link("applications.php") . "\">" . lang("No") . "</a>"; ?>
         </td>
         <td>
           <a href="<?php echo $phpgw->link("deleteapplication.php","app_name=" . urlencode($app_name) . "&confirm=True") . "\">" . lang("Yes") . "</a>"; ?>
         </td>
       </tr>
      </table>
     </center>
     <?php
	$phpgw->common->phpgw_footer();
     ?>     
