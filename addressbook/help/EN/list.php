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
												'title'		=> lang('addressbook') . ' - ' . lang('list'),
												'controls'	=> array('up'	=> 'overview.php',
																	'down'	=> 'add.php')));
	$values['list']	= array
	(
		'list_img'	=> $GLOBALS['phpgw']->common->image('addressbook','help_list'),
		'item_1'	=> 'Category select box. The category filter shows the items sorted by category.',
		'item_2'	=> 'The double arrow moves you to the first page, the single arrow moves you to the previous page.',
		'item_3'	=> 'Type in a word and hit the search for a specific name. For example you cant remember Bobs e-mail address, you type *Bob* into the search box and it will display all entries with the name Bob. You can search entries of your adressbook. The search function is not case sensetive, it searches for upper and lower cases at once.',
		'item_4'	=> 'This pull down menu allows you to choose which entries you would like to view: private, only yours or all records. Hit the filter button to display your selection.',
		'item_5'	=> 'Use the filter button to activate your selection of filters (see #4).',
		'item_6'	=> 'The double arrow moves you to the last page, the single arrow moves you to the next page.',
		'h_data'	=> 'Data records. The visible fields you have choosen in the preferences section. Examples:',
		'item_7'	=> 'Persons full name. By clicking on *Full Name* it will sort the list by the entries first name.',
		'item_8'	=> 'Persons birthday. By clicking on *Birthday* it will sort the list by the entries birthday.',
		'item_9'	=> 'Persons work e-mail address. By clicking on "Business Email" it will sort the list by the entries Business e-mail. In the example above, if you click on the e-mail address, joe@work.com, it will open a compose window to joe an e-mail.',
		'item_10'	=> 'Persons home e-mail address. By clicking on "Home Email" it will sort the list by the entries home e-mail. In the example above, if you click on the e-mail address, joe@home.com, it will open a compose window to joe an e-mail.',
		'item_11'	=> 'View: Allows you to view all of the information that was entered. ie: phone #, address, work #, etc...',
		'item_12'	=> 'VCard: Creates a VCard.',
		'item_13'	=> 'Edit: Allows you to edit all the information contained in that persons entry. It is only possible to edit an entry if you are the owner or have the rights to do so.',
		'item_14'	=> 'Owner: The person who owns this record.',
		'item_15'	=> 'Add: Creates a new entry to add a new person.',
		'item_16'	=> 'Add VCard: Imports a new VCard into your address book.',
		'item_17'	=> 'Import Contacts: A way to import a previous address book, ie. Netscape ldif format, IE csv files...',
		'item_18'	=> 'Export Contacts: Lets you export your address book to a text file.'
	);

	$GLOBALS['phpgw']->help->xdraw($values);
	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',$GLOBALS['phpgw']->help->output);
?>
