<?php 
  if (empty($phpgw_info["server"]["translation_system"])){$phpgw_info["server"]["translation_system"] = "sql";}
  include(PHPGW_API_INC."/class.translation_".$phpgw_info["server"]["translation_system"].".inc.php"); 
?>