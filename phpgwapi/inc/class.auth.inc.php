<?php 
	if (empty($GLOBALS['phpgw_info']['server']['auth_type']))
	{
		$GLOBALS['phpgw_info']['server']['auth_type'] = 'sql';
	}
	include(PHPGW_API_INC.'/class.auth_'.$GLOBALS['phpgw_info']['server']['auth_type'].'.inc.php');
?>
