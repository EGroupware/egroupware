<?php
	/***************************************************************************\
	* phpGroupWare - Categories													*
	* http://www.phpgroupware.org												*
	* Written by Bettina Gille [ceb@phpgroupware.org]							*
	* -----------------------------------------------							*
	* This program is free software; you can redistribute it and/or modify it	*
	* under the terms of the GNU General Public License as published by the		*
	* Free Software Foundation; either version 2 of the License, or (at your	*
	* option) any later version.												*
	\***************************************************************************/
	/* $Id$ */

	if (! $cat_id)
	{
		Header('Location: ' . $phpgw->link('/preferences/categories.php','sort=' . $sort . '&order=' . $order . '&query=' . $query . '&start=' . $start
							. '&filter=' . $filter . '&cats_app=' . $cats_app . '&extra=' . $extra . '&cats_level=' . $cats_level . '&global_cats=' . $global_cats));
	}

	$phpgw_info['flags']['currentapp'] = $cats_app;
	$phpgw_info['flags']['noappheader'] = True;
	$phpgw_info['flags']['noappfooter'] = True;

	include('../header.inc.php');

	$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
				. '<input type="hidden" name="order" value="' . $order . '">' . "\n"
				. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
				. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
				. '<input type="hidden" name="cats_app" value="' . $cats_app . '">' . "\n"
				. '<input type="hidden" name="cat_id" value="' . $cat_id . '">' . "\n"
				. '<input type="hidden" name="extra" value="' . $extra . '">' . "\n"
				. '<input type="hidden" name="global_cats" value="' . $global_cats . '">' . "\n"
				. '<input type="hidden" name="cats_level" value="' . $cats_level . '">' . "\n"
				. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

	$t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('preferences'));
	$t->set_file(array('form' => 'category_form.tpl'));
	$t->set_block('form','add','addhandle');
	$t->set_block('form','edit','edithandle');

	$c = CreateObject('phpgwapi.categories');
	$c->app_name = $cats_app;

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

		if ($cat_main && $cat_parent)
		{
			$main = $c->id2name($cat_parent,'main');
			if ($main != $cat_main)
			{
				$error[$errorcount++] = lang('You have selected an invalid main category');
			}
		}

		$cat_name = addslashes($cat_name);
		$cat_description = addslashes($cat_description);
		if ($access)
		{
			$cat_access = 'private';
		}
		else
		{
			$cat_access = 'public';
		}

		if (! $error)
		{
			$c->edit($cat_id,$cat_parent,$cat_name,$cat_description,$cat_data,$cat_access,$cat_main);
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

	$t->set_var('lang_main',lang('Main category'));
	$t->set_var('lang_new_main',lang('New main category'));

	if ($global_cats)
	{
		$t->set_var('main_category_list',$c->formated_list('select','mains',$cats[0]['main'],True));
	}
	else
	{
		$t->set_var('main_category_list',$c->formated_list('select','mains',$cats[0]['main']));
	}

	if ($cats_level) 
	{
		if ($global_cats)
		{
			$category_list = $c->formated_list('select','all',$cats[0]['parent'],True);
		}
		else
		{
			$category_list = $c->formated_list('select','all',$cats[0]['parent']);
		}

		$t->set_var('category_select','<select name="cat_parent"><option value="">' . lang('Choose the parent category') . '</option>' . $category_list .'</select>');
		$t->set_var('lang_parent',lang('Parent category'));
	}
	else
	{
		$t->set_var('lang_parent','');
		$t->set_var('category_select','');
	}

	$t->set_var('font',$phpgw_info['theme']['font']);
	$t->set_var('user_name',$phpgw_info['user']['fullname']);
	$t->set_var('title_categories',lang('Edit x category for',lang($cats_app)));
	$t->set_var('doneurl',$phpgw->link('/preferences/categories.php'));
	$t->set_var('actionurl',$phpgw->link('/preferences/editcategory.php'));
	$t->set_var('deleteurl',$phpgw->link('/preferences/deletecategory.php'));
	$t->set_var('hidden_vars',$hidden_vars);
	$t->set_var('lang_name',lang('Name'));
	$t->set_var('lang_descr',lang('Description'));
	$t->set_var('lang_access',lang('Private'));

	if ($cats[0]['access']=='private')
	{
		$t->set_var('access', '<input type="checkbox" name="access" value="True" checked>');
	}
	else
	{
		$t->set_var('access', '<input type="checkbox" name="access" value="True"');
	}

	$cat_id = $cats[0]['id'];

	$t->set_var('cat_name',$phpgw->strip_html($cats[0]['name']));
	$t->set_var('cat_description',$phpgw->strip_html($cats[0]['description']));

	if ($extra)
	{
		$t->set_var('td_data','<input name="cat_data" size="50" value="' . $cats[0]['data'] . '">');
		$t->set_var('lang_data',lang($extra));
	}
	else
	{
		$t->set_var('td_data','');
		$t->set_var('lang_data','');
	}

	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang('Delete'));
	$t->set_var('lang_done',lang('Done'));
	$t->set_var('edithandle','');
	$t->set_var('addhandle','');
	$t->pparse('out','form');
	$t->pparse('edithandle','edit');
	$phpgw->common->phpgw_footer();
?>
