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

  if (! $app_name) {
     Header("Location: " . $phpgw->link("/admin/applications.php"));
  }
  include("../header.inc.php");
  $phpgw->template->set_file(array("body" => "delete_common.tpl"));

  if ($confirm) {
        $phpgw->db->query("delete from phpgw_applications where app_name='$app_name'",__LINE__,__FILE__);

        Header("Location: " . $phpgw->link("/admin/applications.php"));
        $phpgw->common->phpgw_exit();
  }

  $phpgw->common->phpgw_header();
  echo parse_navbar();

  $phpgw->template->set_var("message",lang("Are you sure you want to delete this application ?"));
  $phpgw->template->set_var("no",'<a href="' . $phpgw->link("/admin/applications.php")
                               . '">' . lang("No") . '</a>');
  $phpgw->template->set_var("yes",'<a href="' . $phpgw->link("/admin/deleteapplication.php","app_name=" . urlencode($app_name) . "&confirm=True") . '">' . lang("Yes") . '</a>');
  $phpgw->template->pparse("out","body");

  $phpgw->common->phpgw_footer();
?>
