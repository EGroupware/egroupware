<?php
	include('../version.inc.php');
	$phpgw_info['setup']['addressbook']['name'] = 'Addressbook';
	$phpgw_info['setup']['addressbook']['version'] = $phpgw_info['server']['versions']['addressbook'];
	$phpgw_info['setup']['addressbook']['app_order'] = 7;
	$phpgw_info['setup']['addressbook']['tables'] = "";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$phpgw_info['setup']['addressbook']['hooks'] = $hooks_string;
?>