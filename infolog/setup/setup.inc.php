<?php
	$setup_info['infolog']['name'] = 'Info Log';
	$setup_info['infolog']['version'] = '0.9.11';
	$setup_info['infolog']['app_order'] = 1;
	$setup_info['infolog']['tables'] = 'phpgw_infolog';
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['infolog']['hooks'] = $hooks_string;
?>
