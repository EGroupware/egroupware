<?php
	$setup_info['phpwebhosting']['name']    = 'phpwebhosting';
	$setup_info['phpwebhosting']['title']   = 'PHPWebHosting';
	$setup_info['phpwebhosting']['version'] = '0.9.13.001';
	$setup_info['phpwebhosting']['app_order'] = 10;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['phpwebhosting']['hooks'][] = 'preferences';

	/* Dependencies for this app to work */
	$setup_info['phpwebhosting']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.10', '0.9.11' , '0.9.12')
	);

?>
