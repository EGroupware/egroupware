<?php 
	if (empty($GLOBALS['phpgw_info']['server']['sessions_type']))
	{
		$GLOBALS['phpgw_info']['server']['sessions_type'] = 'db';
	}
	include(PHPGW_API_INC.'/class.sessions_'.$GLOBALS['phpgw_info']['server']['sessions_type'].'.inc.php');
?>
