<?php
	$setup_info['calendar']['name'] = 'Calendar';
	$setup_info['calendar']['version'] = '0.9.11';
	$setup_info['calendar']['app_order'] = 3;
	$setup_info['calendar']['tables'] = "";
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['calendar']['hooks'] = $hooks_string;
?>