<?
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * The file written by Joseph Engo <jengo@phpgroupware.org>                 *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id $ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");
  include($phpgw_info["server"]["include_root"]."/globalconfig.inc.php");

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

  echo "db server: ".$phpgw_info["server"]["db_host"];


?>