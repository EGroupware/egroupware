<?php
  /**************************************************************************\
  * phpGroupWare - Address Book                                              *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info['flags'] = array(
		'currentapp'  => 'addressbook', 
		'noheader'    => True, 
		'nonavbar'    => True, 
		'noappheader' => True,
		'noappfooter' => True,
		'enable_config_class'     => True,
		'enable_nextmatchs_class' => True
	);

	include('../header.inc.php');

	$phpgw->config->read_repository();

	if ($submit)
	{
		if ($usecountrylist)
		{
			$phpgw->config->config_data['countrylist'] = True;
		}
		else
		{
			unset($phpgw->config->config_data['countrylist']);
		}

		$phpgw->config->save_repository(True);
		Header('Location: ' . $phpgw->link('/admin/index.php'));
	}

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$t->set_file(array('admin' => 'admin.tpl'));

	$t->set_var('action_url',$phpgw->link('/addressbook/admin.php'));

	$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
	$t->set_var('tr_color',$tr_color);

	$t->set_var('lang_countrylist',lang('Use Country List'));
	if ($phpgw->config->config_data['countrylist'])
	{
		$t->set_var('countrylist',' checked');
	}
	else
	{
		$t->set_var('countrylist','');
	}

	$t->set_var('lang_admin',lang('Addressbook').' '.lang('Admin'));
	$t->set_var('lang_submit',lang('submit'));

	$t->pparse('out','admin');
	$phpgw->common->phpgw_footer();
?>
