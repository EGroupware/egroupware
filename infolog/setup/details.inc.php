<?php
//	include('../version.inc.php');
	$phpgw_info['setup']['info']['name'] = 'info';
	$phpgw_info['setup']['info']['version'] = "0.1";
	$phpgw_info['setup']['info']['app_order'] = 1;
	$phpgw_info['setup']['info']['tables'] = "info";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$phpgw_info['setup']['info']['hooks'] = $hooks_string;
?>