<?php
	/**************************************************************************\
	* phpGroupWare - Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['preferences']['name']      = 'preferences';
	$setup_info['preferences']['version']   = '0.9.13.002';
	$setup_info['preferences']['app_order'] = 1;
	$setup_info['preferences']['tables']    = '';
	$setup_info['preferences']['enable']    = 2;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['preferences']['hooks'][] = 'deleteaccount';
	$setup_info['preferences']['hooks'][] = 'config';
	$setup_info['preferences']['hooks'][] = 'manual';
	$setup_info['preferences']['hooks'][] = 'preferences';
	$setup_info['preferences']['hooks'][] = 'settings';

	/* Dependencies for this app to work */
	$setup_info['preferences']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13', '0.9.14','0.9.15')
	);
?>
