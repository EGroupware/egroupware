<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  function update_version_table($tableschanged = True){
    global $currentver, $phpgw_info, $db, $tablechanges;
    if ($tableschanged == True){$tablechanges = True;}
    $db->query("update applications set app_version='".$currentver."' where (app_name='admin' or app_name='filemanager' or app_name='addressbook' or app_name='todo' or app_name='calendar' or app_name='email' or app_name='nntp' or app_name='cron_apps' or app_name='notes')");
  }

  if ($currentver == "drop"){
    include("./sql/".$phpgw_domain[$SetupDomain]["db_type"]."_droptables.inc.php");
    $currentver = "new";
  }
  if ($currentver == "new") {
    include("./sql/".$phpgw_domain[$SetupDomain]["db_type"]."_newtables.inc.php");
    include("./sql/common_default_records.inc.php");
    $included = True;
    include($phpgw_info["server"]["server_root"] . "/setup/lang.php");
  }
  echo "<table border=\"0\" align=\"center\">\n";
  echo "  <tr bgcolor=\"486591\">\n";
  echo "    <td colspan=\"2\"><font color=\"fefefe\">&nbsp;<b>Table Changes</b></font></td>\n";
  echo "  </tr>\n";
  
  if ($currentver == "7122000" || $currentver == "8032000" || $currentver == "8072000" || $currentver == "8212000" || $currentver == "9052000" || $currentver == "9072000") {
    include("./sql/".$phpgw_domain[$SetupDomain]["db_type"]."_upgrade_prebeta.inc.php");
  }
  
  include("./sql/".$phpgw_domain[$SetupDomain]["db_type"]."_upgrade_beta.inc.php");
  
  if (!$tablechanges == True){
    echo "  <tr bgcolor=\"e6e6e6\">\n";
    echo "    <td>No table changes were needed. The script only updated your version setting.</td>\n";
    echo "  </tr>\n";
  }
  echo "</table>\n";
?>
