<?php
	/**************************************************************************\
	* phpGroupWare - Addressbook                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/* Basic information about this app */
	$setup_info['addressbook']['name']      = 'addressbook';
	$setup_info['addressbook']['version']   = '0.9.13.003';
	$setup_info['addressbook']['app_order'] = 4;
	$setup_info['addressbook']['enable']    = 1;

	$setup_info['addressbook']['author'][] = array
	(
		'name'	=> 'Joseph Engo',
		'email'	=> 'jengo@phpgroupware.org'
	);

	$setup_info['addressbook']['author'][] = array
	(
		'name'	=> 'Miles Lott',
		'email'	=> 'milos@speakeasy.net'
	);

	$setup_info['addressbook']['note']   = 'The phpgwapi manages contact data.  Addressbook manages servers for its remote capability';
	$setup_info['addressbook']['license']  = 'GPL';
	$setup_info['addressbook']['description'] =
		'Contact manager with Vcard support.<br>
		Addressbook is the phpgroupware default contact application. <br>
		It makes use of the phpgroupware contacts class to store and retrieve 
		contact information via SQL or LDAP.';

	$setup_info['addressbook']['maintainer'] = '';

	/* Tables */
	$setup_info['addressbook']['tables'][] = 'phpgw_addressbook_servers';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['addressbook']['hooks'] = array
	(
		'admin',
		'add_def_pref',
		'config_validate',
		'home',
		'manual',
		'deleteaccount',
		'notifywindow',
		'preferences',
		'help'
	);

	/* Dependencies for this app to work */
	$setup_info['addressbook']['depends'][] = array
	(
		'appname'  => 'phpgwapi',
		'versions' => Array('0.9.15', '0.9.16')
	);
?>
