<?php
	$setup_info['preferences']['name']      = 'preferences';
	$setup_info['preferences']['title']     = 'Preferences';
	$setup_info['preferences']['version']   = '0.9.11';
	$setup_info['preferences']['app_order'] = 1;
	$setup_info['preferences']['tables']    = '';

	/* The hooks this app includes, needed for hooks registration */
	//$setup_info['admin']['hooks'][] = 'preferences';
	//$setup_info['admin']['hooks'][] = 'admin';

	/* Dependacies for this app to work */
	$setup_info['preferences']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.10', '0.9.11' , '0.9.12')
	);
?>
