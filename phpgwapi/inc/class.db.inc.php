<?php 
  if (empty($phpgw_info["server"]["db_type"])){$phpgw_info["server"]["db_type"] = "mysql";}
  include(PHPGW_API_INC."/class.db_".$phpgw_info["server"]["db_type"].".inc.php"); 
?>