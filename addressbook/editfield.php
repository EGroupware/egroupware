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

	$GLOBALS['phpgw']_info['flags']['currentapp'] = 'addressbook';
	include('../header.inc.php');

	if(!$GLOBALS['phpgw']->acl->check('run',1,'admin'))
	{
		echo lang('access not permitted');
		$GLOBALS['phpgw']->common->phpgw_footer();
		$GLOBALS['phpgw']->common->phpgw_exit();

	}

	$field = $HTTP_POST_VARS['field'];
	$field_name = $HTTP_POST_VARS['field_name'];
	$start = $HTTP_POST_VARS['start'];
	$query = $HTTP_POST_VARS['query'];
	$sort  = $HTTP_POST_VARS['sort'];

	if (!$field)
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/addressbook/fields.php',"sort=$sort&query=$query&start=$start"));
	}

	$GLOBALS['phpgw']->template->set_file(array('form' => 'field_form.tpl'));
	$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
	$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

	$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
		. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
		. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
		. '<input type="hidden" name="field" value="' . $field . '">' . "\n";

	if ($HTTP_POST_VARS['submit'])
	{
		$errorcount = 0;
		if (!$field_name) { $error[$errorcount++] = lang('Please enter a name for that field!'); }

		$field_name = addslashes($field_name);

		if (! $error)
		{
			save_custom_field($field,$field_name);
		}
	}

	if ($errorcount)
	{
		$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
	}
	if (($submit) && (! $error) && (! $errorcount))
	{
		$GLOBALS['phpgw']->template->set_var('message',lang('Field x has been updated !', $field_name));
	}
	if ((! $submit) && (! $error) && (! $errorcount))
	{
		$GLOBALS['phpgw']->template->set_var('message','');
	}

	if ($submit)
	{
		$field = $field_name;
	}
	else
	{
		$fields = read_custom_fields($start,$limit,$field);
		$field  = $GLOBALS['phpgw']->strip_html($fields[0]['name']);
	}

	$GLOBALS['phpgw']->template->set_var('title_fields',lang('Edit Field'));
	$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/addressbook/editfield.php'));
	$GLOBALS['phpgw']->template->set_var('deleteurl',$GLOBALS['phpgw']->link('/addressbook/deletefield.php',"field=$field&start=$start&query=$query&sort=$sort"));
	$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/addressbook/fields.php',"start=$start&query=$query&sort=$sort"));

	$GLOBALS['phpgw']->template->set_var('hidden_vars',$hidden_vars);
	$GLOBALS['phpgw']->template->set_var('lang_name',lang('Field name'));

	$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
	$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
	$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));

	$GLOBALS['phpgw']->template->set_var('field_name',$field);

	$GLOBALS['phpgw']->template->set_var('edithandle','');
	$GLOBALS['phpgw']->template->set_var('addhandle','');

	$GLOBALS['phpgw']->template->pparse('out','form');
	$GLOBALS['phpgw']->template->pparse('edithandle','edit');

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
