<?php
	$setup_info['admin']['name']      = 'admin';
	$setup_info['admin']['title']     = 'Administration';
	$setup_info['admin']['version']   = '0.9.11';
	$setup_info['admin']['app_order'] = 1;
	$setup_info['admin']['tables']    = '';
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['admin']['hooks'] = $hooks_string;
?>
