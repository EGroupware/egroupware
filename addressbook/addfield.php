<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
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

	$t = new Template(PHPGW_APP_TPL);
	$t->set_file(array('form' => 'field_form.tpl'));
	$t->set_block('form','add','addhandle');
	$t->set_block('form','edit','edithandle');

	if ($submit) {
		$errorcount = 0;

		if (!$field_name)
		{
			$error[$errorcount++] = lang('Please enter a name for that field !');
		}

		$fields = read_custom_fields($start,$limit,$field_name);
		if ($fields[0]['name'])
		{
			$error[$errorcount++] = lang('That field name has been used already !');
		}

		if (! $error)
		{
			$field_name = addslashes($field_name);
			save_custom_field($field,$field_name);
		}
	}

	if ($errorcount) { $t->set_var('message',$phpgw->common->error_list($error)); }
	if (($submit) && (! $error) && (! $errorcount)) { $t->set_var('message',lang('Field x has been added !', $field_name)); }
	if ((! $submit) && (! $error) && (! $errorcount)) { $t->set_var('message',''); }

	$t->set_var('title_fields',lang('Add'). ' ' . lang('Custom Field'));
	$t->set_var('actionurl',$phpgw->link('/addressbook/addfield.php'));
	$t->set_var('doneurl',$phpgw->link('/addressbook/fields.php'));
	$t->set_var('hidden_vars','<input type="hidden" name="field" value="' . $field . '">');

	$t->set_var('lang_name',lang('Field name'));

	$t->set_var('lang_add',lang('Add'));
	$t->set_var('lang_reset',lang('Clear Form'));
	$t->set_var('lang_done',lang('Done'));

	$t->set_var('field_name',$field_name);

	$t->set_var('edithandle','');
	$t->set_var('addhandle','');
	$t->pparse('out','form');
	$t->pparse('addhandle','add');

	$phpgw->common->phpgw_footer();
?>
