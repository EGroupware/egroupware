<?php
	/* Basic information about this app */
	$setup_info['addressbook']['name']      = 'addressbook';
	$setup_info['addressbook']['title']     = 'Addressbook';
	$setup_info['addressbook']['version']   = '0.9.11';
	$setup_info['addressbook']['app_order'] = 4;
	$setup_info['addressbook']['tables']    = '';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['addressbook']['hooks'][] = 'preferences';

	/* Dependacies for this app to work */
	$setup_info['addressbook']['depends'][] = array(
			 'appname' => 'phpgwapi',
			 'versions' => Array('0.9.10', '0.9.11' , '0.9.12')
		);
?>