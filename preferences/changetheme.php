<?php
  /**************************************************************************\
  * phpGroupWare - preferences                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_flags = array("noheader" => True, "nonavbar" => True);

  $phpgw_flags["currentapp"] = "preferences";
  include("../header.inc.php");

  $dh = opendir($phpgw_info["server"]["server_root"] . "/themes");
  while ($file = readdir($dh)) {
    if ($file != "." && $file != ".." && $file != "CVS") {
       $installed_themes[] = substr($file,0,strpos($file,"."));
    }
  }

  if ($phpgw_info["user"]["permissions"]["anonymous"]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"));
     exit;
  }

  if (!isset($submit) && !$submit) {
     $phpgw->common->header();
     $phpgw->common->navbar();

   ?>
     <br><?php echo lang_pref("your current theme is: x",$phpgw_info["user"]["preferences"]["theme"]); ?>
     <br><?php echo lang_pref("please, select a new theme").":"; ?>
     <br>

<?php
     for ($i=0; $i < count($installed_themes); $i++)
       echo "<br><a href=\"" . $phpgw->link("changetheme.php","submit=true&ntheme="
	  . $installed_themes[$i]) . "\">" . $installed_themes[$i] . "</a>\n";
  } else {
    $theme = $ntheme;
    $phpgw->preferences->update($phpgw->session->loginid,"theme");

    // This way the theme is changed right away.
    Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
	 . "/preferences/"));
  }
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

