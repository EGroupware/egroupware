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

	$app_id = $GLOBALS['phpgw']->applications->name2id('addressbook');

	$GLOBALS['phpgw']->help->set_params(array('app_id'	=> $app_id,
											'app_name'	=> 'addressbook',
												'title'	=> lang('addressbook')));

	$GLOBALS['phpgw']->help->data[] = array
	(
		'text'					=> lang('owerview'),
		'link'					=> $GLOBALS['phpgw']->help->check_help_file('addressbook.php'),
		'lang_link_statustext'	=> lang('owerview')
	);

	$GLOBALS['phpgw']->help->draw();
?>
