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

  $d1 = strtolower(substr($phpgw_info["server"]["server_root"],0,3));
  if($d1 == "htt" || $d1 == "ftp" ) {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);

  function update_version_table($tableschanged = True){
    global $phpgw_info, $phpgw_setup;
    if ($tableschanged == True){$phpgw_info["setup"]["tableschanged"] = True;}
    $phpgw_setup->db->query("update phpgw_applications set app_version='".$phpgw_info["setup"]["currentver"]["phpgwapi"]."' where (app_name='admin' or app_name='filemanager' or app_name='addressbook' or app_name='todo' or app_name='calendar' or app_name='email' or app_name='nntp' or app_name='cron_apps' or app_name='notes')");
  }

  if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "drop"){
    include("./sql/".$phpgw_domain[$ConfigDomain]["db_type"]."_droptables.inc.php");
  }
  if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "new") {
    include("./sql/".$phpgw_domain[$ConfigDomain]["db_type"]."_newtables.inc.php");
    include("./sql/common_default_records.inc.php");
    $included = True;
    include(PHPGW_SERVER_ROOT . "/setup/lang.php");
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = "oldversion";
  }

  if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "oldversion") {
    $phpgw_info["setup"]["currentver"]["phpgwapi"] = $phpgw_info["setup"]["oldver"]["phpgwapi"];
    if ($phpgw_info["setup"]["currentver"]["phpgwapi"] == "7122000" || $phpgw_info["setup"]["currentver"]["phpgwapi"] == "8032000" || $phpgw_info["setup"]["currentver"]["phpgwapi"] == "8072000" || $phpgw_info["setup"]["currentver"]["phpgwapi"] == "8212000" || $phpgw_info["setup"]["currentver"]["phpgwapi"] == "9052000" || $phpgw_info["setup"]["currentver"]["phpgwapi"] == "9072000") {
      include("./sql/".$phpgw_domain[$ConfigDomain]["db_type"]."_upgrade_prebeta.inc.php");
    }
    include("./sql/".$phpgw_domain[$ConfigDomain]["db_type"]."_upgrade_beta.inc.php");
  }

/* Not yet implemented
  if (!$phpgw_info["setup"]["tableschanged"] == True){
    echo "  <tr bgcolor=\"e6e6e6\">\n";
    echo "    <td>No table changes were needed. The script only updated your version setting.</td>\n";
    echo "  </tr>\n";
  }
*/
?>