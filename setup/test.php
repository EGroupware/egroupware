<?php
  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");
  include("./inc/functions.inc.php");

  $SetupDomain = "phpgroupware.org";
  loaddb();
//  $currentver = "drop";
  $currentver = "new";
  $phpgw_setup->manage_tables();

  $phpgw_setup->execute_script("create_tables");
?>