<?php
	/**************************************************************************\
	* eGroupWare - resources                                                   *
	* http://www.egroupware.org                                                *
	*                                                                          *
	* Written by Cornelius Weiss [egw@von-und-zu-weiss.de]                     *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$*/
	
	$setup_info['resources']['name']	= 'resources';
	$setup_info['resources']['title']	= 'resources';
	$setup_info['resources']['version']	= '0.0.1.015';
	$setup_info['resources']['app_order']	= 1;
	$setup_info['resources']['tables']	= array('egw_resources');
	$setup_info['resources']['enable']	= 1;

	$setup_info['resources']['author']	= 'Cornelius Weiss'; 
	$setup_info['resources']['license']	= 'GPL'; 
	$setup_info['resources']['description'] = 'Resource management system';
	$setup_info['resources']['note']	= 'This includes resource booking';
	$setup_info['resources']['maintainer']	= array( 
		'name' => 'Cornelius Weiss', 
		'email' => 'egw@von-und-zu-weiss.de' 
		); 
		
	$setup_info['resources']['hooks']['preferences']	= 'resources.admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['resources']['hooks']['admin']		= 'resources.admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['resources']['hooks']['sidebox_menu']	= 'resources.admin_prefs_sidebox_hooks.all_hooks';
// 	$setup_info['resources']['hooks'][]	= 'admin';
//	$setup_info['resources']['hooks'][]	= 'home';
//	$setup_info['resources']['hooks'][]	= 'sidebox_menu';
//	$setup_info['resources']['hooks'][]	= 'settings';
//	$setup_info['resources']['hooks'][]	= 'preferences'

	$setup_info['resources']['depends'][]	= array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('1.0.1')
	);
	$setup_info['resources']['depends'][]	= array(
		 'appname' => 'etemplate',
		 'versions' => Array('1.0.0')
	);








