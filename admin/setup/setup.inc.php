<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['admin']['name']      = 'admin';
	$setup_info['admin']['title']     = 'Administration';
	$setup_info['admin']['version']   = '0.9.13.002';
	$setup_info['admin']['app_order'] = 1;
	$setup_info['admin']['tables']    = '';
	$setup_info['admin']['enable']    = 1;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['admin']['hooks'][] = 'acl_manager';
	$setup_info['admin']['hooks'][] = 'add_def_pref';
	$setup_info['admin']['hooks'][] = 'admin';
	$setup_info['admin']['hooks'][] = 'after_navbar';
	$setup_info['admin']['hooks'][] = 'config';
	$setup_info['admin']['hooks'][] = 'deleteaccount';
	$setup_info['admin']['hooks'][] = 'manual';
	$setup_info['admin']['hooks'][] = 'view_user';

	/* Dependencies for this app to work */
	$setup_info['admin']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13', '0.9.14', '0.9.15')
	);
?>
