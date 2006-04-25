<?php
	/**************************************************************************\
	* eGroupWare - Addressbook                                                 *
	* http://www.egroupware.org                                                *
	* ------------------------------------------------------------------------ *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/* Basic information about this app */
	$setup_info['addressbook']['name']      = 'addressbook';
	$setup_info['addressbook']['title']     = 'Addressbook';
	$setup_info['addressbook']['version']   = '1.3.001';
	$setup_info['addressbook']['app_order'] = 4;
	$setup_info['addressbook']['enable']    = 1;

	$setup_info['addressbook']['author'] = 'Ralf Becker, Cornelius Weiss, Lars Kneschke';
//	$setup_info['addressbook']['note']   = 'The phpgwapi manages contact data.  Addressbook manages servers for its remote capability.';
	$setup_info['addressbook']['license']  = 'GPL';
	$setup_info['addressbook']['description'] =
		'Contact manager with Vcard support.<br />
		 Always have your address book available for updates or look ups from anywhere. <br />
		 Share address book contact information with others. <br />
		 Link contacts to calendar events or InfoLog entires like phonecalls.<br /> 
		 Addressbook is the eGroupWare default contact application. <br />
		 It stores contact information via SQL or LDAP and provides contact services via the eGroupWare API.';

	$setup_info['addressbook']['maintainer'] = 'eGroupWare coreteam';
	$setup_info['addressbook']['maintainer_email'] = 'egroupware-developers@lists.sourceforge.net';

	$setup_info['addressbook']['tables'][]  = 'egw_addressbook';
	$setup_info['addressbook']['tables'][]  = 'egw_addressbook_extra';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['addressbook']['hooks']['admin'] = 'addressbook.contacts_admin_prefs.all_hooks';
	$setup_info['addressbook']['hooks']['preferences'] = 'addressbook.contacts_admin_prefs.all_hooks';
	$setup_info['addressbook']['hooks']['sidebox_menu'] = 'addressbook.contacts_admin_prefs.all_hooks';
	$setup_info['addressbook']['hooks']['settings'] = 'addressbook.contacts_admin_prefs.settings';
	$setup_info['addressbook']['hooks'][] = 'config_validate';
	$setup_info['addressbook']['hooks'][] = 'home';
	$setup_info['addressbook']['hooks']['editaccount'] = 'addressbook.bocontacts.editaccount';
	$setup_info['addressbook']['hooks']['deleteaccount'] = 'addressbook.bocontacts.deleteaccount';
	$setup_info['addressbook']['hooks'][] = 'notifywindow';
	$setup_info['addressbook']['hooks']['search_link'] = 'addressbook.bocontacts.search_link';
	$setup_info['addressbook']['hooks']['edit_user']    = 'addressbook.contacts_admin_prefs.edit_user';

	/* Dependencies for this app to work */
	$setup_info['addressbook']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => Array('1.0.0','1.0.1','1.2','1.3')
	);
	$setup_info['addressbook']['depends'][] = array(
		'appname' => 'etemplate',
		'versions' => Array('1.0.0','1.0.1','1.2','1.3')
	);

