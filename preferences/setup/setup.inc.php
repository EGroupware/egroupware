<?php
	$setup_info['preferences']['name'] = 'Preferences';
	$setup_info['preferences']['version'] = '0.9.11';
	$setup_info['preferences']['app_order'] = 1;
	$setup_info['preferences']['tables'] = "";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['preferences']['hooks'] = $hooks_string;
?>