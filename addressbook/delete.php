<?php
/**************************************************************************\
* phpGroupWare - addressbook                                               *
* http://www.phpgroupware.org                                              *
* Written by Joseph Engo <jengo@phpgroupware.org>                          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'addressbook'
	);

	include('../header.inc.php');

	if (! $ab_id)
	{
		Header('Location: ' . $phpgw->link('/addressbook/index.php'));
	}

	$contacts = CreateObject('phpgwapi.contacts');
	$fields = $contacts->read_single_entry($ab_id,array('owner' => 'owner'));
	//$record_owner = $fields[0]['owner'];

	if (! $contacts->check_perms($contacts->grants[$fields[0]['owner']],PHPGW_ACL_DELETE) && $fields[0]['owner'] != $phpgw_info['user']['account_id'])
	{
		Header('Location: '
			. $phpgw->link('/addressbook/index.php',"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$phpgw->common->phpgw_exit();
	}

	$t = new Template(PHPGW_APP_TPL);
	$t->set_file(array('delete' => 'delete.tpl'));

	if ($confirm != 'true')
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		$t->set_var('lang_sure',lang('Are you sure you want to delete this entry ?'));
		$t->set_var('no_link',$phpgw->link('/addressbook/index.php',
			"ab_id=$ab_id&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$t->set_var('lang_no',lang('NO'));
		$t->set_var('yes_link',$phpgw->link('/addressbook/delete.php',
			"ab_id=$ab_id&confirm=true&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
		$t->set_var('lang_yes',lang('YES'));
		$t->pparse('out','delete');

		$phpgw->common->phpgw_footer(); 
	}
	else
	{
		$contacts->account_id = $phpgw_info['user']['account_id'];
		$contacts->delete($ab_id);

		@Header('Location: ' . $phpgw->link('/addressbook/index.php',
			"cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query&cat_id=$cat_id"));
	}
?>
