<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
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

  $phpgw_info["flags"]["currentapp"] = "addressbook";
  $phpgw_info["flags"]["enable_addressbook_class"] = True;
  include("../header.inc.php");
  
  if (! $ab_id) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/"));
  }

  if ($confirm != "true") {
     $phpgw->db->query("select ab_owner from addressbook where ab_id='$ab_id'");
     $phpgw->db->next_record();

     if ($phpgw->db->f("ab_owner") != $phpgw_info["user"]["account_id"])
        Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/"));

     ?>
        <body bgcolor=FFFFFF aLink=0000EE link=0000EE vlink=0000EE>
        <center><?php echo lang("Are you sure you want to delete this entry ?"); ?><center>
        <br><center><a href="<?php 
          echo $phpgw->link("view.php","&ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start&query=$query");
          ?>"><?php echo lang("NO"); ?></a> &nbsp; &nbsp; &nbsp; &nbsp;
        <a href="<?php echo $phpgw->link("delete.php","ab_id=$ab_id&confirm=true&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"); 
            ?>"><?php echo lang("YES"); ?></a><center>
     <?php

     //$phpgw->common->phpgw_exit();
  } else {

     $phpgw->db->query("delete from addressbook where ab_owner='" . $phpgw_info["user"]["account_id"]
		             . "' and ab_id='$ab_id'");
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]. "/addressbook/",
	    "cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
  }
?>

