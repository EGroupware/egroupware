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

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "preferences");

  include("../header.inc.php");
  
  if ($theme) {
     $phpgw->preferences->change("common","theme");
     $phpgw->preferences->commit();
     if ($phpgw_info["server"]["useframes"] != "never") {
        Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php","forward=/preferences/changetheme.php&cd=yes"));
        Header("Window-Target: _top");
     } else {
        Header("Location: " . $phpgw->link("changetheme.php"));
     }
     $phpgw->common->phpgw_exit();
  }

  $dh = opendir($phpgw_info["server"]["server_root"] . "/phpgwapi/themes");
  while ($file = readdir($dh)) {
    if (eregi("\.theme$", $file)) {
       $installed_themes[] = substr($file,0,strpos($file,"."));
    }
  }

  $phpgw->common->phpgw_header();
  echo parse_navbar();

  echo "<br>" . lang("your current theme is: x",$phpgw_info["user"]["preferences"]["theme"]);
  echo "<br>" . lang("please, select a new theme") . ":";
  echo "<br>";

  for ($i=0; $i<count($installed_themes); $i++) {
     echo '<br><a href="' . $phpgw->link("changetheme.php","theme="
     	. $installed_themes[$i]) . '">' . $installed_themes[$i] . '</a>';
  
     if ($phpgw_info["server"]["useframes"] != "never") {     
//        echo '<br><a href="' . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php","ntheme="
//        	. $installed_themes[$i] . "&forward=" . urlencode("preferences/index.php")) . '" target="_new">' . $installed_themes[$i] . '</a>';
     }
  }

  $phpgw->common->phpgw_footer();
?>
