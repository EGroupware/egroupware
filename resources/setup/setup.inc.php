<?php
	$setup_info['resources']['name']      = 'resources';
	$setup_info['resources']['title']     = 'Resource Management';
	$setup_info['resources']['version']   = '0.0.1.011';
	$setup_info['resources']['app_order'] = 1;
	$setup_info['resources']['tables']    = array('egw_resources');
	$setup_info['resources']['enable']    = 1;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['resources']['hooks'][] = 'admin';
//	$setup_info['resources']['hooks'][] = 'home';
//	$setup_info['resources']['hooks'][] = 'sidebox_menu';
//	$setup_info['resources']['hooks'][] = 'settings';
//	$setup_info['resources']['hooks'][] = 'preferences'

	/* Dependencies for this app to work */
	$setup_info['resources']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('1.0.0','1.0.1')
	);
	$setup_info['resources']['depends'][] = array(	// this is only necessary as long the etemplate-class is not in the api
		 'appname' => 'etemplate',
		 'versions' => Array('1.0.0')
	);




