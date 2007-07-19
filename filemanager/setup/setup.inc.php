<?php
	/**************************************************************************\
	* eGroupWare - Filemanager                                                 *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['filemanager']['name']    = 'filemanager';
	$setup_info['filemanager']['title']   = 'Filemanager';
	$setup_info['filemanager']['version'] = '1.5';
	$setup_info['filemanager']['app_order'] = 6;
	$setup_info['filemanager']['enable']  = 1;
	$setup_info['filemanager']['tables']  =array('egw_vfs');

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['filemanager']['hooks']['preferences'] = 'filemanager.admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['filemanager']['hooks']['deleteaccount'] = 'filemanager.admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['filemanager']['hooks'][] = 'settings';
	$setup_info['filemanager']['hooks']['sidebox_menu'] = 'filemanager.admin_prefs_sidebox_hooks.all_hooks';

	
	/* Dependencies for this app to work */
	$setup_info['filemanager']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => array('1.3','1.4','1.5')
	);

	// installation checks for filemanager
	$setup_info['filemanager']['check_install'] = array(
		'' => array(
			'func' => 'pear_check',
			'from' => 'Filemanager',
		),
		'HTTP_WebDAV_Server' => array(
			'func' => 'pear_check',
			'from' => 'Filemanager',
		),
	);
