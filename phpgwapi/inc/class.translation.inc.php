<?php 
	if (empty($GLOBALS['phpgw_info']['server']['translation_system']))
	{
		$GLOBALS['phpgw_info']['server']['translation_system'] = 'sql';
	}
	include(PHPGW_API_INC.'/class.translation_' . $GLOBALS['phpgw_info']['server']['translation_system'].'.inc.php'); 
?>
