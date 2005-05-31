<?php 
	if (empty($GLOBALS['egw_info']['server']['translation_system']))
	{
		$GLOBALS['egw_info']['server']['translation_system'] = 'sql';
	}
	include(EGW_API_INC.'/class.translation_' . $GLOBALS['egw_info']['server']['translation_system'].'.inc.php'); 
?>
