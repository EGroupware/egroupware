<?php
	/**************************************************************************\
	* phpGroupWare - infolog                                                   *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['infolog']['name']      = 'infolog';
	$setup_info['infolog']['title']     = 'Info Log';
	$setup_info['infolog']['version']   = '0.9.11';
	$setup_info['infolog']['app_order'] = 20;
	$setup_info['infolog']['tables']    = array('phpgw_infolog');
	$setup_info['infolog']['enable']    = 1;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['infolog']['hooks'][] = 'preferences';
	$setup_info['infolog']['hooks'][] = 'admin';
	$setup_info['infolog']['hooks'][] = 'about';
	$setup_info['infolog']['hooks'][] = 'addressbook_view';
	$setup_info['infolog']['hooks'][] = 'projects_view';

	/* Dependacies for this app to work */
	$setup_info['infolog']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13', '0.9.14','0.9.15')
	);
?>
