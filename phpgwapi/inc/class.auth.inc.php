<?php 
  if (empty($phpgw_info['server']['auth_type'])){$phpgw_info['server']['auth_type'] = 'sql';}
  include(PHPGW_API_INC.'/class.auth_'.$phpgw_info['server']['auth_type'].'.inc.php');
?>
