<?php
	/***************************************************************************\
	* phpGroupWare - Categories                                                 *
	* http://www.phpgroupware.org                                               *
	* Written by Bettina Gille [ceb@phpgroupware.org]                           *
	* -----------------------------------------------                           *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	$phpgw_info['flags'] = array('currentapp' => $cats_app,
								'enable_nextmatchs_class' => True);

	if (! $appheader)
	{
		$phpgw_info['flags']['noappheader'] = True;
	}

	if (! $appfooter)
	{
		$phpgw_info['flags']['noappfooter'] = True;
	}

	include('../header.inc.php');

	$t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('preferences'));
	$t->set_file(array('cat_list_t' => 'listcats.tpl',
						'cat_list'   => 'listcats.tpl'));
	$t->set_block('cat_list_t','cat_list','list');

	$c = CreateObject('phpgwapi.categories');
	$c->app_name = $cats_app;

	$hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
				. '<input type="hidden" name="order" value="' . $order . '">' . "\n"
				. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
				. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
				. '<input type="hidden" name="cats_app" value="' . $cats_app . '">' . "\n"
				. '<input type="hidden" name="extra" value="' . $extra . '">' . "\n"
				. '<input type="hidden" name="global_cats" value="' . $global_cats . '">' . "\n"
				. '<input type="hidden" name="cats_level" value="' . $cats_level . '">' . "\n"
				. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

	$t->set_var('font',$phpgw_info['theme']['font']);
	$t->set_var('user_name',$phpgw_info['user']['fullname']);
	$t->set_var('hidden_vars',$hidden_vars);
	$t->set_var('title_categories',lang('categories for'));
	$t->set_var('lang_action',lang('Category list'));
	$t->set_var('add_action',$phpgw->link('/preferences/addcategory.php','cats_app=' . $cats_app . '&extra=' . $extra . '&cats_level=' . $cats_level
										. '&global_cats=' . $global_cats));
	$t->set_var('lang_add',lang('Add'));
	$t->set_var('lang_search',lang('Search'));
	$t->set_var('actionurl',$phpgw->link('/preferences/categories.php','cats_app=' . $cats_app . '&extra=' . $extra . '&cats_level=' . $cats_level
										. '&global_cats=' . $global_cats));
	$t->set_var('lang_done',lang('Done'));
	$t->set_var('doneurl',$phpgw->link('/preferences/index.php'));

	if (! $start) { $start = 0; }

	if ($global_cats)
	{
		$categories = $c->return_sorted_array($start,True,$query,$sort,$order,True);
	}
	else
	{
		$categories = $c->return_sorted_array($start,True,$query,$sort,$order);
	}

//--------------------------------- nextmatch --------------------------------------------

	$left = $phpgw->nextmatchs->left('/preferences/categories.php',$start,$c->total_records,'&cats_app=' . $cats_app . '&extra=' . $extra
									. '&cats_level=' . $cats_level . '&global_cats=' . $global_cats);
	$right = $phpgw->nextmatchs->right('/preferences/categories.php',$start,$c->total_records,'&cats_app=' . $cats_app . '&extra=' . $extra
									. '&cats_level=' . $cats_level . '&global_cats=' . $global_cats);
	$t->set_var('left',$left);
	$t->set_var('right',$right);

	$t->set_var('lang_showing',$phpgw->nextmatchs->show_hits($c->total_records,$start));

// ------------------------------ end nextmatch ------------------------------------------

//------------------- list header variable template-declarations ------------------------- 

	$t->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$t->set_var('sort_name',$phpgw->nextmatchs->show_sort_order($sort,'cat_name',$order,'/preferences/categories.php',lang('Name'),'&cats_app=' . $cats_app
																. '&extra=' . $extra . '&cats_level=' . $cats_level . '&global_cats=' . $global_cats));
	$t->set_var('sort_description',$phpgw->nextmatchs->show_sort_order($sort,'cat_description',$order,'/preferences/categories.php',lang('Description'),'&cats_app=' . $cats_app
																. '&extra=' . $extra . '&cats_level=' . $cats_level . '&global_cats=' . $global_cats));
	if ($extra)
	{
		$t->set_var('sort_data','<td bgcolor="' . $phpgw_info['theme']['th_bg'] . '"><font face="' . $phpgw_info['theme']['font'] . '">'
			. $phpgw->nextmatchs->show_sort_order($sort,'cat_data',$order,'/preferences/categories.php',lang($extra),'&cats_app=' . $cats_app . '&extra=' . $extra
																				. '&cats_level=' . $cats_level . '&global_cats=' . $global_cats) . '</td>');
	}
	else
	{
		$t->set_var('sort_data','');
	}

	$t->set_var('lang_app',lang($cats_app));
	$t->set_var('lang_sub',lang('Add sub'));
	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang('Delete'));

// -------------------------- end header declaration --------------------------------------

	for ($i=0;$i<count($categories);$i++)
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$t->set_var('tr_color',$tr_color);
		$cat_id = $categories[$i]['id'];
		$owner = $categories[$i]['owner'];
		$level = $categories[$i]['level'];

		if ($categories[$i]['app_name'] == 'phpgw')
		{
			$appendix = '&lt;' . lang('Global') . '&gt;';
		}
		else
		{
			$appendix = '';
		}

		if ($level > 0)
		{
			$space = '&nbsp;&nbsp;';
			$spaceset = str_repeat($space,$level);
			$name = $spaceset .$phpgw->strip_html($categories[$i]['name']) . $appendix;
		}

		$descr = $phpgw->strip_html($categories[$i]['description']);
		if (! $descr) { $descr  = '&nbsp;'; }

		if ($extra)
		{
			$data = $categories[$i]['data'];
			if (! $data) { $data  = '&nbsp;'; }
			$t->set_var('td_data','<td><font face=' . $phpgw_info['theme']['font'] . '>' . $data . '</font></td>');
		}
		else
		{
			$t->set_var('td_data','');
		}

		if ($level == 0)
		{
			$name = '<font color="FF0000"><b>' . $phpgw->strip_html($categories[$i]['name']) . '</b></font>' . $appendix;
			$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
			$data = '<font color="FF0000"><b>' . $data . '</b></font>';
		}

//-------------------------- template declaration for list records ---------------------------

		$t->set_var(array('name' => $name,
						'descr' => $descr));

		$t->set_var('app_url',$phpgw->link('/' . $phpgw_info['flags']['currentapp'] . '/index.php','cat_id=' . $cat_id));

		if ($cats_level || ($level == 0))
		{
			if ($categories[$i]['owner'] == $phpgw_info['user']['account_id'] || $categories[$i]['app_name'] == 'phpgw')
			{
				$t->set_var('add_sub',$phpgw->link('/preferences/addcategory.php','cat_parent=' . $cat_id . '&cats_app=' . $cats_app . '&extra=' . $extra
											. '&cats_level=' . $cats_level . '&global_cats=' . $global_cats));
				$t->set_var('lang_sub_entry',lang('Add sub'));
			}
		}
		else
		{
			$t->set_var('add_sub','');
			$t->set_var('lang_sub_entry','&nbsp;');
		}

		if ($categories[$i]['owner'] == $phpgw_info['user']['account_id'] && $categories[$i]['app_name'] != 'phpgw')
		{
			$t->set_var('edit',$phpgw->link('/preferences/editcategory.php','cat_id=' . $cat_id . '&cats_app=' . $cats_app . '&extra=' . $extra
											. '&cats_level=' . $cats_level . '&global_cats=' . $global_cats));
			$t->set_var('lang_edit_entry',lang('Edit'));
		}
		else
		{
			$t->set_var('edit','');
			$t->set_var('lang_edit_entry','&nbsp;');
		}

		if ($categories[$i]['owner'] == $phpgw_info['user']['account_id'] && $categories[$i]['app_name'] != 'phpgw')
		{
			$t->set_var('delete',$phpgw->link('/preferences/deletecategory.php','cat_id=' . $cat_id . '&cats_app=' . $cats_app . '&extra=' . $extra
											. '&cats_level=' . $cats_level . '&global_cats=' . $global_cats));
			$t->set_var('lang_delete_entry',lang('Delete'));
		}
		else
		{
			$t->set_var('delete','');
			$t->set_var('lang_delete_entry','&nbsp;');
		}

		$t->parse('list','cat_list',True);
	}

// ---------------------------- end record declaration -----------------------------------------

	$t->parse('out','cat_list_t',True);
	$t->p('out');

	$phpgw->common->phpgw_footer();
?>
