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
	$setup_info['infolog']['version']   = '0.9.15.003';
	$setup_info['infolog']['app_order'] = 20;
	$setup_info['infolog']['tables']    = array('phpgw_infolog','phpgw_links');
	$setup_info['infolog']['enable']    = 1;

	$setup_info['infolog']['author'] = 'Ralf Becker';
	$setup_info['infolog']['license']  = 'GPL';
	$setup_info['infolog']['description'] =
		'CRM type app using Addressbook providing Todo List, Notes and Phonelog.';
	$setup_info['infolog']['maintainer'] = 'Ralf Becker';
	$setup_info['infolog']['maintainer_email'] = 'ralfbecker@outdoor-training.de';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['infolog']['hooks'][] = 'preferences';
	$setup_info['infolog']['hooks'][] = 'admin';
	$setup_info['infolog']['hooks'][] = 'about';
	$setup_info['infolog']['hooks'][] = 'home';
	$setup_info['infolog']['hooks'][] = 'addressbook_view';
	$setup_info['infolog']['hooks'][] = 'projects_view';
	$setup_info['infolog']['hooks'][] = 'calendar_view';

	/* Dependencies for this app to work */
	$setup_info['infolog']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13', '0.9.14','0.9.15')
	);
	$setup_info['infolog']['depends'][] = array(
		 'appname' => 'etemplate',
		 'versions' => Array('0.9.13', '0.9.14','0.9.15')
	);
?>
