<?php
	$setup_info['calendar']['name']    = 'calendar';
	$setup_info['calendar']['title']   = 'Calendar';
	$setup_info['calendar']['version'] = '0.9.11';
	$setup_info['calendar']['app_order'] = 3;

	$setup_info['calendar']['tables'][] = 'phpgw_cal';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_holidays';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_repeats';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_user';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['calendar']['hooks'][] = 'preferences';

	/* Dependencies for this app to work */
	$setup_info['calendar']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.10', '0.9.11' , '0.9.12', '0.9.13')
	);

?>
