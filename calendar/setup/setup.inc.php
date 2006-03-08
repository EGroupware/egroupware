<?php
	/**************************************************************************\
	* eGroupWare - Calendar                                                    *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['calendar']['name']    = 'calendar';
	$setup_info['calendar']['version'] = '1.2';
	$setup_info['calendar']['app_order'] = 3;
	$setup_info['calendar']['enable']  = 1;

	$setup_info['calendar']['license']  = 'GPL';
	$setup_info['calendar']['description'] =
		'Powerful group calendar with meeting request system and ACL security.';
	$setup_info['calendar']['note'] = 
		'The calendar has been completly rewritten for eGroupWare 1.2.';
	$setup_info['calendar']['author'] = $setup_info['calendar']['maintainer'] = array(
		'name'  => 'Ralf Becker',
		'email' => 'RalfBecker@outdoor-training.de'
	);

	$setup_info['calendar']['tables'][] = 'egw_cal';
	$setup_info['calendar']['tables'][] = 'egw_cal_holidays';
	$setup_info['calendar']['tables'][] = 'egw_cal_repeats';
	$setup_info['calendar']['tables'][] = 'egw_cal_user';
	$setup_info['calendar']['tables'][] = 'egw_cal_extra';
	$setup_info['calendar']['tables'][] = 'egw_cal_dates';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['calendar']['hooks'][] = 'add_def_prefs';
	$setup_info['calendar']['hooks'][] = 'admin';
	$setup_info['calendar']['hooks'][] = 'deleteaccount';
	$setup_info['calendar']['hooks'][] = 'home';
	$setup_info['calendar']['hooks'][] = 'preferences';
	$setup_info['calendar']['hooks'][] = 'settings';
	$setup_info['calendar']['hooks']['sidebox_menu'] = 'calendar.uical.sidebox_menu';
	$setup_info['calendar']['hooks']['search_link'] = 'calendar.bocal.search_link';

	/* Dependencies for this app to work */
	$setup_info['calendar']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('1.0.0','1.0.1','1.2','1.3')
	);
	$setup_info['calendar']['depends'][] = array(
		 'appname' => 'etemplate',
		 'versions' => Array('1.0.0','1.0.1','1.2','1.3')
	);
















