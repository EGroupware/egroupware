<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['admin']['name']      = 'admin';
	$setup_info['admin']['version']   = '1.0.0';
	$setup_info['admin']['app_order'] = 1;
	$setup_info['admin']['tables']    = '';
	$setup_info['admin']['enable']    = 1;

	$setup_info['admin']['author'][] = array(
		'name'  => 'eGroupWare coreteam',
		'email' => 'egroupware-developers@lists.sourceforge.net'
	);

	$setup_info['admin']['maintainer'][] = array(
		'name'  => 'eGroupWare coreteam',
		'email' => 'egroupware-developers@lists.sourceforge.net',
		'url'   => 'www.egroupware.org'
	);

	$setup_info['admin']['license']  = 'GPL';
	$setup_info['admin']['description'] = 'eGroupWare administration application';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['admin']['hooks'] = array(
		'acl_manager',
		'add_def_pref',
		'admin',
		'after_navbar',
		'config',
		'deleteaccount',
		'view_user' => 'admin.uiaccounts.edit_view_user_hook',
		'edit_user' => 'admin.uiaccounts.edit_view_user_hook',
		'sidebox_menu'
	);

	/* Dependencies for this app to work */
	$setup_info['admin']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => Array('0.9.14','0.9.15','1.0.0')
	);
?>
