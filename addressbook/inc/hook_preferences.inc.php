<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $ Id $ */
{
  echo "<p>\n";
  $imgfile = $phpgw->common->get_image_dir("addressbook")."/" . $appname .".gif";
  if (file_exists($imgfile)) {
    $imgpath = $phpgw->common->get_image_path("addressbook")."/" . $appname .".gif";
  } else {
    $imgfile = $phpgw->common->get_image_dir("addressbook")."/navbar.gif";
    if (file_exists($imgfile)) {
      $imgpath = $phpgw->common->get_image_path("addressbook")."/navbar.gif";
    } else {
      $imgpath = "";
    }
  }
  section_start("Address Book",$imgpath);

  $pg = $phpgw->link($phpgw_info["server"]["webserver_url"]."/".$appname."/preferences.php");
  echo "<a href=".$pg.">" . lang("Addressbook preferences") . "</a><br>";

  $pg = $phpgw->link($phpgw_info["server"]["webserver_url"]."/".$appname."/acl_preferences.php");
  echo "<a href=".$pg.">" . lang("Grant Addressbook Access") . "</a>";

  section_end(); 
}
?>
