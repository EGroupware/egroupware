<?php
  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home", "noapi" => True);
  include("../header.inc.php");
  include("./inc/functions.inc.php");

  $SetupDomain = "phpgroupware.org";
  loaddb();
//  $currentver = "drop";
  $currentver = "new";
  manage_tables();

  execute_script("create_tables");
?>