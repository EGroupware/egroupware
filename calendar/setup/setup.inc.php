<?php
	$setup_info['calendar']['name']    = 'calendar';
	$setup_info['calendar']['title']   = 'Calendar';
	$setup_info['calendar']['version'] = '0.9.11';
	$setup_info['calendar']['app_order'] = 3;

	$setup_info['calendar']['tables'][] = 'phpgw_cal';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_holidays';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_repeats';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_user';
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['calendar']['hooks'] = $hooks_string;
?>
