<?php
	include('../version.inc.php');
	$phpgw_info['setup']['admin']['name'] = 'Administration';
	$phpgw_info['setup']['admin']['version'] = $phpgw_info['server']['versions']['admin'];
	$phpgw_info['setup']['admin']['app_order'] = 1;
	$phpgw_info['setup']['admin']['tables'] = "";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$phpgw_info['setup']['admin']['hooks'] = $hooks_string;
?>