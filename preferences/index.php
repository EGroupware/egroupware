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

  //$phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);

  $phpgw_info["flags"]["currentapp"] = "preferences";
  include("../header.inc.php");

  // This func called by the includes to dump a row header
  function section_start($name="",$icon="") {
    global $phpgw,$phpgw_info;
    //echo "<TABLE WIDTH=\"75%\" BORDER=\"0\" CELLSPACING=\"0\" CELLPADDING=\"0\" BGCOLOR=\"".$phpgw_info["theme"]["navbar_bg"]."\">\n";
    echo "<TABLE WIDTH=\"75%\" BORDER=\"0\" CELLSPACING=\"0\" CELLPADDING=\"0\">\n";
    //echo "<TR BGCOLOR=\"".$phpgw_info["theme"]["navbar_bg"]."\">";
    echo "<TR>";
    if ($icon != "") {
      echo "<TD WIDTH='5%'><img src='".$icon."' ALT='[Icon]' align='middle'></TD>";
      echo "<TD><fontsize='+2'>".lang($name)."</font></TD>";
    } else {
      echo "<TD colspan='2'><font size='+2'>$name</font></TD>";
    } 
    echo "</TR>\n";
    echo "<TR><TD colspan='2'>\n";
  }
  function section_end() {
    echo "</TD></TR></TABLE>\n\n";
  }

  $order[] = "notes"; 
  $order[] = "email"; 
  $order[] = "preferences"; 
  $phpgw->common->hook("",$order);

  $phpgw->common->phpgw_footer();
?>
