<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	$phpgw_info["flags"]["currentapp"] = 'addressbook';
	include('../header.inc.php');

	if(!$phpgw->acl->check('run',1,'admin'))
	{
		echo lang('access not permitted');
		$phpgw->common->phpgw_footer();
		$phpgw->common->phpgw_exit();

	}

	if (!$field) {
		Header('Location: ' . $phpgw->link('/addressbook/fields.php',"sort=$sort&query=$query&start=$start"));
	}

	$t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('addressbook'));
	$t->set_file(array('form' => 'field_form.tpl'));
	$t->set_block('form','add','addhandle');
	$t->set_block('form','edit','edithandle');

	$hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
		. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
		. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
		. "<input type=\"hidden\" name=\"field\" value=\"$field\">\n";

	if ($submit) {
		$errorcount = 0;
		if (!$field_name) { $error[$errorcount++] = lang('Please enter a name for that field!'); }

		$field_name   = addslashes($field_name);

		if (! $error)
		{
			save_custom_field($field,$field_name);
		}
	}

	if ($errorcount) { $t->set_var('message',$phpgw->common->error_list($error)); }
	if (($submit) && (! $error) && (! $errorcount)) { $t->set_var('message',lang("Field '$field_name' has been updated !")); }
	if ((! $submit) && (! $error) && (! $errorcount)) { $t->set_var('message',''); }

	if ($submit)
	{
		$field = $field_name;
	}
	else
	{
		$fields = read_custom_fields($start,$limit,$field);
		$field  = $phpgw->strip_html($fields[0]['name']);
	}

	$t->set_var('title_fields',lang('Edit Field'));
	$t->set_var('actionurl',$phpgw->link('/addressbook/editfield.php'));
	$t->set_var('deleteurl',$phpgw->link('/addressbook/deletefield.php',"field=$field&start=$start&query=$query&sort=$sort"));
	$t->set_var('doneurl',$phpgw->link('/addressbook/fields.php',"start=$start&query=$query&sort=$sort"));

	$t->set_var('hidden_vars',$hidden_vars);
	$t->set_var('lang_name',lang('Field name'));

	$t->set_var('lang_done',lang('Done'));
	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang('Delete'));

	$t->set_var('field_name',$field);

	$t->set_var('edithandle','');
	$t->set_var('addhandle','');

	$t->pparse('out','form');
	$t->pparse('edithandle','edit');

	$phpgw->common->phpgw_footer();
?>
