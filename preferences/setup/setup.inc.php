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

	$setup_info['admin']['author'][] = array
	(
		'name'	=> 'phpGroupWare coreteam',
		'email' => 'phpgroupware-developers@gnu.org'
	);

	$setup_info['admin']['maintainer'][]  = array
	(
		'name'	=> 'Joseph Engo',
		'email'	=> 'jengo@phpgroupware.org'
	);

	$setup_info['admin']['maintainer'][]  = array
	(
		'name'	=> 'Marc A. Peters',
		'email'	=> 'skeeter@phpgroupware.org'
	);

	$setup_info['admin']['maintainer'][]	= array
	(
		'name'	=> 'Bettina Gille',
		'email'	=> 'ceb@phpgroupware.org'
	);

	$setup_info['admin']['maintainer'][]  = array
	(
		'name'	=> 'Dan Kuykendall',
		'email'	=> 'seek3r@phpgroupware.org'
	);

	$setup_info['admin']['license']  = 'GPL';
	$setup_info['admin']['description'] = 'phpGroupWare preferences application';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['preferences']['hooks'][] = 'deleteaccount';
	$setup_info['preferences']['hooks'][] = 'config';
	$setup_info['preferences']['hooks'][] = 'manual';
	$setup_info['preferences']['hooks'][] = 'preferences';
	$setup_info['preferences']['hooks'][] = 'settings';

	/* Dependencies for this app to work */
	$setup_info['preferences']['depends'][] = array
	(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.15')
	);
?>
