<?php
	include('../version.inc.php');
	$phpgw_info['setup']['preferences']['name'] = 'Preferences';
	$phpgw_info['setup']['preferences']['version'] = $phpgw_info['server']['versions']['preferences'];
	$phpgw_info['setup']['preferences']['app_order'] = 1;
	$phpgw_info['setup']['preferences']['tables'] = "";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$phpgw_info['setup']['preferences']['hooks'] = $hooks_string;
?>