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
  $imgfile = $phpgw->common->get_image_dir("calendar")."/" . $appname .".gif";
  if (file_exists($imgfile)) {
    $imgpath = $phpgw->common->get_image_path("calendar")."/" . $appname .".gif";
  } else {
    $imgfile = $phpgw->common->get_image_dir("calendar")."/navbar.gif";
    if (file_exists($imgfile)) {
      $imgpath = $phpgw->common->get_image_path("calendar")."/navbar.gif";
    } else {
      $imgpath = "";
    }
  }

  section_start("Calendar",$imgpath);

  $pg = $phpgw->link($phpgw_info["server"]["webserver_url"]."/calendar/preferences.php");
  echo "<a href=".$pg.">" . lang("Calendar preferences") . "</a>";

  section_end(); 
}
?>
