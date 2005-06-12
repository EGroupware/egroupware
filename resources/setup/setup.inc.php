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
	$setup_info['resources']['version']	= '0.0.1.017';
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
		
	$setup_info['resources']['hooks']['preferences']	= 'resources.resources_hooks.admin_prefs_sidebox';
	$setup_info['resources']['hooks']['admin']		= 'resources.resources_hooks.admin_prefs_sidebox';
	$setup_info['resources']['hooks']['sidebox_menu']	= 'resources.resources_hooks.admin_prefs_sidebox';
	$setup_info['resources']['hooks']['search_link']	= 'resources.resources_hooks.search_link';
	$setup_info['resources']['hooks']['calendar_resources']	= 'resources.resources_hooks.calendar_resources';
//	$setup_info['resources']['hooks'][]	= 'home';
//	$setup_info['resources']['hooks'][]	= 'settings';

	$setup_info['resources']['depends'][]	= array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('1.0.1')
	);
	$setup_info['resources']['depends'][]	= array( // cause eTemplates is not in the api yet
		 'appname' => 'etemplate',
		 'versions' => Array('1.0.0')
	);
	$setup_info['resources']['depends'][]	= array( // cause the link class is not in the api yet
		 'appname' => 'infolog',
		 'versions' => Array('1.0.0')
	);
	$setup_info['resources']['depends'][]	= array( // cause of vfs psuedoprotocol is not fully in the api yet
		 'appname' => 'filemanager',
		 'versions' => Array('1.0.0')
	);
	$setup_info['resources']['depends'][]	= array( // cause of the manual needs wiki and it's not in the api yet
		 'appname' => 'wiki',
		 'versions' => Array('1.0.0')
	);











