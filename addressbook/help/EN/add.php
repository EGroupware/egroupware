<?php
	/**************************************************************************\
	* phpGroupWare - User manual                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = Array
	(
		'headonly'		=> True,
		'currentapp'	=> 'addressbook'
	);

	include('../../../header.inc.php');
	$GLOBALS['phpgw']->help = CreateObject('phpgwapi.help_helper');
	$GLOBALS['phpgw']->help->set_params(array('app_name'	=> 'addressbook',
												'title'		=> lang('add'),
												'controls'	=> array('app_intro'	=> 'overview.php',
																			'up'	=> 'overview.php')));

	$values['add'] = array
	(
		'intro'	=> 'Click on the add button, a form page will be presented with the following fields:',
		'lang_lastname'			=> 'Last name',
		'lang_firstname'		=> 'First name',
		'lang_email'			=> 'E-mail',
		'lang_homephone'		=> 'Home phone',
		'lang_workphone'		=> 'Work phone',
		'lang_mobile'			=> 'Mobile',
		'lang_street'			=> 'Street',
		'lang_city'				=> 'City',
		'lang_state'			=> 'State',
		'lang_zip'				=> 'ZIP code',
		'lang_access'			=> 'Access',
		'lang_group_settings'	=> 'Group settings',
		'lang_notes'			=> 'Notes',
		'lang_company'			=> 'Company name',
		'lang_fax'				=> 'Fax',
		'lang_pager'			=> 'Pager',
		'lang_othernumber'		=> 'Other number',
		'lang_birthday'			=> 'Birthday',
		'end'					=> 'Simply fill in the fields, and click OK.',
		'access_descr'			=> 'Access can be restricted to private, overriding acl preferences settings.
									From preferences, you can grant access to users to the be able to view, 
									edit, and even delete your entries.'
	);

	$GLOBALS['phpgw']->help->xdraw($values);
	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',$GLOBALS['phpgw']->help->output);
?>
