<?php
	/**************************************************************************\
	* phpGroupWare - Addressbook hook_help                                     *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	include(PHPGW_SERVER_ROOT.'/'.'addressbook'.'/setup/setup.inc.php');

	$GLOBALS['phpgw']->help->set_params(array('app_name'		=> 'addressbook',
												'title'			=> lang('addressbook'),
												'app_version'	=> $setup_info['addressbook']['version']));

	$GLOBALS['phpgw']->help->data[] = array
	(
		'text'					=> lang('owerview'),
		'link'					=> $GLOBALS['phpgw']->help->check_help_file('overview.php'),
		'lang_link_statustext'	=> lang('owerview')
	);

	$GLOBALS['phpgw']->help->data[] = array
	(
		'text'					=> lang('list'),
		'link'					=> $GLOBALS['phpgw']->help->check_help_file('list.php'),
		'lang_link_statustext'	=> lang('list')
	);

	$GLOBALS['phpgw']->help->data[] = array
	(
		'text'					=> lang('add'),
		'link'					=> $GLOBALS['phpgw']->help->check_help_file('add.php'),
		'lang_link_statustext'	=> lang('add')
	);

	$GLOBALS['phpgw']->help->draw();
?>
