<?
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");

  $phpgw_info["server"]["api_dir"] = $phpgw_info["server"]["include_root"]."/phpgwapi";
  
  /* Database setup */
  switch($phpgw_info["server"]["db_type"]){
    case "postgresql":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_pgsql.inc.php");
      break;
    case "oracle":
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_oracle.inc.php");
      break;      
    default:
      include($phpgw_info["server"]["api_dir"] . "/phpgw_db_mysql.inc.php");
  }

  $db	            = new db;
  $db->Host	    = $phpgw_info["server"]["db_host"];
  $db->Type	    = $phpgw_info["server"]["db_type"];
  $db->Database   = $phpgw_info["server"]["db_name"];
  $db->User	    = $phpgw_info["server"]["db_user"];
  $db->Password   = $phpgw_info["server"]["db_pass"];
//  $db->Halt_On_Error = "report";
  $db->Halt_On_Error = "no";

  $db->query("select * from config");
  if ($db->num_rows() == 0){
    $db->query("select * from accounts");
    if ($db->num_rows() == 0){
      echo "You appear to be running a new install of phpGroupWare, so the tables will be created for you.<br>\n";
      include ("droptables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("createtables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("default_records.inc.php");
      include ("lang_records.inc.php");
      echo "If you did not recieve any errors, your tables have been created.<br>\n";
      echo "<a href=\"config.php\">Click here</a> to configure the environment.<br>\n"; 
    }else{
      echo "You appear to be running a pre-beta version of phpGroupWare<br>\n";
      //echo "We are not providing an upgrade path at this time, please backup your tables and drop them, so that this script can recreate them.<br>\n";

      /* This is a temporary evil section */
      echo "This script is going to take the evil action of dropping your existing table and re-creating them in the new format.<br>\n";
      include ("droptables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("createtables_".$phpgw_info["server"]["db_type"].".inc.php");
      include ("default_records.inc.php");
      include ("lang_records.inc.php");
      echo "If you did not recieve any errors, your tables have been created.<br>\n";
      echo "<a href=\"config.php\">Click here</a> to configure the environment.<br>\n"; 
    }
  }else{
    echo "Your database seems to be current.<br>\n"; 
    echo "<a href=\"config.php\">Click here</a> to configure the environment.<br>\n"; 
  }

?>