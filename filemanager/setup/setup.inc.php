<?php
	$setup_info['phpwebhosting']['name']    = 'phpwebhosting';
	$setup_info['phpwebhosting']['title']   = 'PHPWebHosting';
	$setup_info['phpwebhosting']['version'] = '0.9.13.001';
	$setup_info['phpwebhosting']['app_order'] = 10;

	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['phpwebhosting']['hooks'] = $hooks_string;
?>
