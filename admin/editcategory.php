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

	if (! $cat_id)
	{
		Header('Location: ' . $phpgw->link('/admin/categories.php','sort=' . $sort . '&order=' . $order . '&query=' . $query
										. '&start=' . $start . '&filter=' . $filter));
	}

	$phpgw_info['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$t->set_file(array('form' => 'category_form.tpl'));
	$t->set_block('form','add','addhandle');
	$t->set_block('form','edit','edithandle');

	$c = CreateObject('phpgwapi.categories');
	$c->app_name = 'phpgw';

	$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
				. '<input type="hidden" name="order" value="' . $order . '">' . "\n"
				. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
				. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
				. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n"
				. '<input type="hidden" name="cat_id" value="' . $cat_id . '">' . "\n";

	if ($new_parent)
	{
		$cat_parent = $new_parent;
	}

	if ($submit)
	{
		$errorcount = 0;

		if (!$cat_name)
		{
			$error[$errorcount++] = lang('Please enter a name');
		}

		if (!$error)
		{
			if (!$cat_parent)
			{
				$exists = $c->exists('appandmains',$cat_name,$cat_id);
			}
			else
			{
				$exists = $c->exists('appandsubs',$cat_name,$cat_id);
			}

			if ($exists == True)
			{
				$error[$errorcount++] = lang('That name has been used already');
			}
		}

		if (! $error)
		{
			$c->edit(array
			(
				'access'	=> 'public',
				'parent'	=> $cat_parent,
				'descr'		=> $cat_description,
				'name'		=> $cat_name,
				'id'		=> $cat_id
			));
		}
	}

	if ($errorcount)
	{
		$t->set_var('message',$phpgw->common->error_list($error));
	}

	if (($submit) && (! $error) && (! $errorcount))
	{
		$t->set_var('message',lang('Category x has been updated !',$cat_name));
	}

	if ((! $submit) && (! $error) && (! $errorcount))
	{
		$t->set_var('message','');
	}

	$cats = $c->return_single($cat_id);

	$t->set_var('title_categories',lang('Edit global category'));
	$t->set_var('lang_parent',lang('Parent category'));
	$t->set_var('lang_none',lang('None'));
	$t->set_var('actionurl',$phpgw->link('/admin/editcategory.php'));
	$t->set_var('deleteurl',$phpgw->link('/admin/deletecategory.php','cat_id=' . $cat_id . '&start=' . $start . '&query=' . $query . '&sort=' . $sort
									. '&order=' . $order . '&filter=' . $filter));
	$t->set_var('doneurl',$phpgw->link('/admin/categories.php','start=' . $start . '&query=' . $query . '&sort=' . $sort . '&order=' . $order . '&filter=' . $filter));
	$t->set_var('hidden_vars',$hidden_vars);
	$t->set_var('lang_name',lang('Name'));
	$t->set_var('lang_descr',lang('Description'));
	$t->set_var('lang_done',lang('Done'));
	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang('Delete'));

	$t->set_var('cat_name',$phpgw->strip_html($cats[0]['name']));
	$t->set_var('cat_description',$phpgw->strip_html($cats[0]['description']));
	$t->set_var('category_list',$c->formated_list('select','all',$cats[0]['parent']));

	$t->set_var('edithandle','');
	$t->set_var('addhandle','');
	$t->pparse('out','form');
	$t->pparse('edithandle','edit');

	$phpgw->common->phpgw_footer();
?>
