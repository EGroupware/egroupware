<?php
	$setup_info['calendar']['name'] = 'Calendar';
	$setup_info['calendar']['version'] = '0.9.11';
	$setup_info['calendar']['app_order'] = 3;
	$setup_info['calendar']['tables'] = array('phpgw_cal','phpgw_cal_holidays','phpgw_cal_repeats','phpgw_cal_user');
	$hooks = Array();
	$hooks_string = implode (',', $hooks);
	$setup_info['calendar']['hooks'] = $hooks_string;
?>
