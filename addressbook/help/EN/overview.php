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
												'title'		=> lang('addressbook') . ' - ' . lang('overview'),
												'controls'	=> array('down' => 'list.php')));

	$values['overview']	= array
	(
		'intro'				=> 'A searchable address book for keeping contact information of business associates or friends and family, to keep various levels of contact information and a search function to find people you need quickly. Integration with other applications in the phpGroupWare suite.',
		'prefs_settings'	=> 'Preferences settings:<br>When you enter the adressbock the first time it shows on the top the message *Please set your preferences for this application!*. This means you still have to adapt the application for your special needs. Each applications preferences section can be found within the preferences application. For further informations please see the section preferences.'
	);

	$GLOBALS['phpgw']->help->xdraw($values);
	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',$GLOBALS['phpgw']->help->output);
?>
