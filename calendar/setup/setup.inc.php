<?php
	/**************************************************************************\
	* phpGroupWare - Calendar                                                  *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['calendar']['name']    = 'calendar';
	$setup_info['calendar']['title']   = 'Calendar';
	$setup_info['calendar']['version'] = '0.9.13.007';
	$setup_info['calendar']['app_order'] = 3;
	$setup_info['calendar']['enable']  = 1;

	$setup_info['calendar']['author'] = 'Mark Peters';
	$setup_info['calendar']['license']  = 'GPL';
	$setup_info['calendar']['description'] =
		'Powerful calendar with meeting request system and ACL security.';
	$setup_info['calendar']['maintainer'] = 'Mark Peters';
	$setup_info['calendar']['maintainer_email'] = 'skeeter@phpgroupware.org';

	$setup_info['calendar']['tables'][] = 'phpgw_cal';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_holidays';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_repeats';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_user';
	$setup_info['calendar']['tables'][] = 'phpgw_cal_alarm';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['calendar']['hooks'] = array
	(
		'add_def_prefs',
		'admin',
		'deleteaccount',
		'email',
		'home',
		'home_day',
		'home_month',
		'home_week',
		'home_year',
		'manual',
		'preferences',
		'about'
	);

	/* Dependencies for this app to work */
	$setup_info['calendar']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13', '0.9.14','0.9.15')
	);
?>
