<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['acl_manager']['admin']['site_config_access'] = array(
		'name' => 'Deny to site configuration',
		'rights' => array(
			'List config settings'   => 1,
			'Change config settings' => 2
		)
	);

	$GLOBALS['acl_manager']['admin']['account_access'] = array(
		'name' => 'Deny access to user accounts',
		'rights' => array(
			'Account list'    => 1,
			'Search accounts' => 2,
			'Add account'     => 4,
			'View account'    => 8,
			'Edit account'    => 16,
			'Delete account'  => 32
		)
	);

	$GLOBALS['acl_manager']['admin']['group_access'] = array(
		'name' => 'Deny access to groups',
		'rights' => array(
			'Group list'    => 1,
			'Search groups' => 2,
			'Add group'     => 4,
			'View group'    => 8,
			'Edit group'    => 16,
			'Delete group'  => 32
		)
	);

	$GLOBALS['acl_manager']['admin']['peer_server_access'] = array(
		'name' => 'Deny access to peer servers',
		'rights' => array(
			'Peer server list'    => 1,
			'Search peer servers' => 2,
			'Add peer server'     => 4,
			'View peer server'    => 8,
			'Edit peer server'    => 16,
			'Delete peer server'  => 32
		)
	);

	$GLOBALS['acl_manager']['admin']['applications_access'] = array(
		'name' => 'Deny access to applications',
		'rights' => array(
			'Applications list' => 1,
			'Add application'   => 2,
			'Edit application'  => 4,
			'Delete application'  => 8
		)
	);

	$GLOBALS['acl_manager']['admin']['global_categories_access'] = array(
		'name' => 'Deny access to global categories',
		'rights' => array(
			'Categories list'   => 1,
			'Search categories' => 2,
			'Add category'      => 4,
			'View category'     => 8,
			'Edit category'     => 16,
			'Delete category'   => 32
		)
	);

	$GLOBALS['acl_manager']['admin']['mainscreen_message_access'] = array(
		'name' => 'Deny access to mainscreen message',
		'rights' => array(
			'Main screen message' => 1,
			'Login message'       => 2
		)
	);

	$GLOBALS['acl_manager']['admin']['current_sessions_access'] = array(
		'name' => 'Deny access to current sessions',
		'rights' => array(
			'List current sessions'   => 1,
			'Show current action'     => 2,
			'Show session IP address' => 4,
			'Kill session'            => 8
		)
	);
