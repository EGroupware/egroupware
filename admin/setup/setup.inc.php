<?php
	$setup_info['admin']['name']      = 'admin';
	$setup_info['admin']['title']     = 'Administration';
	$setup_info['admin']['version']   = '0.9.11';
	$setup_info['admin']['app_order'] = 1;
	$setup_info['admin']['tables']    = '';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['admin']['hooks'][] = 'preferences';
	$setup_info['admin']['hooks'][] = 'admin';

	/* Dependacies for this app to work */
	$setup_info['admin']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.10', '0.9.11' , '0.9.12')
	);
?>
