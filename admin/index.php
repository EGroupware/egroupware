<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * Modified by Stephen Brown <steve@dataclarity.net>                        *
  *  to distribute admin across the application directories                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info = array();
  $phpgw_info["flags"]["currentapp"] = "admin";
  include("../header.inc.php");
  check_code($cd);

  // This func called by the includes to dump a row header
  function section_start($name="",$icon="") {
    global $phpgw,$phpgw_info;
    echo "<TABLE WIDTH=\"75%\" BORDER=\"0\" CELLSPACING=\"0\" CELLPADDING=\"0\">\n";
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

  // We only want to list applications that are enabled, plus the common stuff
  // (if they can get to the admin page, the admin app is enabled, hence it is shown)

  $phpgw->db->query("select app_name from applications where app_enabled = 1 order by app_title");
  
  // Stuff it in an array in the off chance the admin includes need the db
  while ($phpgw->db->next_record() ) {
    $apps[] = $phpgw->db->f("app_name");
  }

  for( $i =0; $i < sizeof($apps); $i++) {
    $appname = $apps[$i];
    $f = $phpgw_info["server"]["server_root"] . "/" . $appname . "/inc/hook_admin.inc.php";
    if (file_exists($f)) {
      include($f);
      echo "<p>\n";
    }
  }

if ( $SHOW_INFO > 0 ) {
  echo "<p><a href=\"".$phpgw->link($PHP_SELF, "SHOW_INFO=0")."\">Hide PHP Information</a>";
  echo "<hr>\n";
  phpinfo();
  echo "<hr>\n";
}
else {
  echo "<p><a href=\"".$phpgw->link($PHP_SELF, "SHOW_INFO=1")."\">PHP Information</a>";
}
  $phpgw->common->phpgw_footer();
?>
