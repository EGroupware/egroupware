<?php 
	if (empty($GLOBALS['phpgw_info']['server']['db_type']))
	{
		$GLOBALS['phpgw_info']['server']['db_type'] = 'mysql';
	}
	include(PHPGW_API_INC.'/class.db_'.$GLOBALS['phpgw_info']['server']['db_type'].'.inc.php'); 
?>
