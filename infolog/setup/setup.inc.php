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
	$setup_info['infolog']['version']   = '0.9.15.005';
	$setup_info['infolog']['app_order'] = 4;
	$setup_info['infolog']['tables']    = array('phpgw_infolog','phpgw_links');
	$setup_info['infolog']['enable']    = 1;

	$setup_info['infolog']['author'] = 
 	$setup_info['infolog']['maintainer'] = array(
		'name'  => 'Ralf Becker',
		'email' => 'ralfbecker@outdoor-training.de'
	);
	$setup_info['infolog']['license']  = 'GPL';
	$setup_info['infolog']['description'] =
		'<b>CRM</b> (customer-relation-management) type app using Addressbook providing 
		Todo List, Notes and Phonelog. <b>InfoLog</b> is orininaly based on phpGroupWare\'s 
		ToDo-List and has the features of all 3 mentioned applications plus fully working ACL 
		(including Add+Private attributes, add for to addreplys/subtasks).<p>
		Responsibility for a task (ToDo) or a phonecall can be <b>delegated</b> to an other 
		user. All entries can be linked to addressbook entries, projects and/or calendar events.
		This allows you to <b>log all activity of a contact</b>/address or project. 
		The entries may be viewed or added from InfoLog direct or from within
		the contact/address, project or calendar view.<p>
		Other documents / files can be linked to InfoLog entries and are store in the VFS
		(phpGroupWare\'s virtual file system). An extension of the VFS allows to symlink
		the files to a fileserver, instead of placeing a copy in the VFS 
		(<i>need to be configured in the admin-section</i>).
		It is planed to include emails and faxes into InfoLog in the future.';
	$setup_info['infolog']['note'] =
		'Their is a <b>CSV import filter</b> (in the admin-section) to import existing data.
		It allows to interactivly assign fields, customize the values with regular 
		expressions and direct calls to php-functions (e.g. to link the phone calls 
		(again) to the addressbook entrys).<p>
		<b>More information</b> about InfoLog and the current development-status can be found on the 
		<a href="http://www.phpgroupware.org/wiki/InfoLog" target="_blank">InfoLog page in our Wiki</a>.';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['infolog']['hooks'][] = 'preferences';
	$setup_info['infolog']['hooks'][] = 'settings';
	$setup_info['infolog']['hooks'][] = 'admin';
	$setup_info['infolog']['hooks'][] = 'deleteaccount';
	$setup_info['infolog']['hooks'][] = 'about';
	$setup_info['infolog']['hooks'][] = 'home';
	$setup_info['infolog']['hooks']['addressbook_view'] = 'infolog.uiinfolog.hook_view';
	$setup_info['infolog']['hooks']['projects_view']    = 'infolog.uiinfolog.hook_view';
	$setup_info['infolog']['hooks']['calendar_view']    = 'infolog.uiinfolog.hook_view';
	$setup_info['infolog']['hooks']['infolog']          = 'infolog.uiinfolog.hook_view';

	/* Dependencies for this app to work */
	$setup_info['infolog']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('0.9.13','0.9.14','0.9.15','0.9.16')
	);
	$setup_info['infolog']['depends'][] = array(
		 'appname' => 'etemplate',
		 'versions' => Array('0.9.15','0.9.16')
	);
?>
