<?php
  /**************************************************************************\
  * phpGroupWare - Admin - Global categories                                 *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	$phpgw_info['flags'] = array('currentapp' => 'admin',
								'enable_nextmatchs_class' => True);

	include('../header.inc.php');

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);

	$t->set_file(array('cat_list_t' => 'listcats.tpl',
						'cat_list'   => 'listcats.tpl'));
	$t->set_block('cat_list_t','cat_list','list');

	$common_hidden_vars = '<input type="hidden" name="sort" value="' . $sort . '">' . "\n"
						. '<input type="hidden" name="order" value="' . $order . '">' . "\n"
						. '<input type="hidden" name="query" value="' . $query . '">' . "\n"
						. '<input type="hidden" name="start" value="' . $start . '">' . "\n"
						. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

	$t->set_var('lang_action',lang('Category list'));
	$t->set_var('add_action',$phpgw->link('/admin/addcategory.php'));
	$t->set_var('lang_add',lang('Add'));
	$t->set_var('title_categories',lang('Global categories'));
	$t->set_var('lang_search',lang('Search'));
	$t->set_var('actionurl',$phpgw->link('/admin/categories.php'));
	$t->set_var('lang_done',lang('Done'));
	$t->set_var('doneurl',$phpgw->link('/admin/index.php'));

	if (! $start) { $start = 0; }

	$c = CreateObject('phpgwapi.categories');
	$c->app_name = 'phpgw';
	$categories = $c->return_array('all',$start,True,$query,$sort,$order,True);

//--------------------------------- nextmatch --------------------------------------------

	$left = $phpgw->nextmatchs->left('/admin/categories.php',$start,$c->total_records);
	$right = $phpgw->nextmatchs->right('/admin/categories.php',$start,$c->total_records);
	$t->set_var('left',$left);
	$t->set_var('right',$right);

	$t->set_var('lang_showing',$phpgw->nextmatchs->show_hits($c->total_records,$start));

// ------------------------------ end nextmatch ------------------------------------------

//------------------- list header variable template-declarations ------------------------- 

	$t->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$t->set_var('sort_name',$phpgw->nextmatchs->show_sort_order($sort,'cat_name',$order,'/admin/categories.php',lang('Name')));
	$t->set_var('sort_description',$phpgw->nextmatchs->show_sort_order($sort,'cat_description',$order,'/admin/categories.php',lang('Description')));
	$t->set_var('lang_sub',lang('Add sub'));
	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang('Delete'));

// -------------------------- end header declaration --------------------------------------

	for ($i=0;$i<count($categories);$i++)
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$t->set_var(tr_color,$tr_color);

		$cat_id = $categories[$i]['id'];
		$level = $categories[$i]['level'];

		if ($level > 0)
		{
			$space = '&nbsp;&nbsp;';
			$spaceset = str_repeat($space,$level);
			$name = $spaceset . $phpgw->strip_html($categories[$i]['name']);
		}

		$descr = $phpgw->strip_html($categories[$i]['description']);
		if (!$descr) { $descr = '&nbsp;'; }

		if ($level == 0)
		{
			$name = '<font color="FF0000"><b>' . $phpgw->strip_html($categories[$i]['name']) . '</b></font>';
			$descr = '<font color="FF0000"><b>' . $descr . '</b></font>';
		}

//-------------------------- template declaration for list records ---------------------------

		$t->set_var(array('name' => $name,
						'descr' => $descr));

		$t->set_var('add_sub',$phpgw->link('/admin/addcategory.php','cat_parent=' . $cat_id . '&start=' . $start . '&query=' . $query . '&sort=' . $sort
							. '&order=' . $order . '&filter=' . $filter));
		$t->set_var('lang_sub_entry',lang('Add sub'));

		$t->set_var('edit',$phpgw->link('/admin/editcategory.php','cat_id=' . $cat_id . '&start=' . $start . '&query=' . $query . '&sort=' . $sort
										. '&order=' . $order . '&filter=' . $filter));
		$t->set_var('lang_edit_entry',lang('Edit'));

		$t->set_var('delete',$phpgw->link('/admin/deletecategory.php','cat_id=' . $cat_id . '&start=' . $start . '&query=' . $query . '&sort=' . $sort . '&order='
										. $order . '&filter=' . $filter));
		$t->set_var('lang_delete_entry',lang('Delete'));

		$t->parse('list','cat_list',True);
	}

// ---------------------------- end record declaration -----------------------------------------

	$t->parse('out','cat_list_t',True);
	$t->p('out');

	$phpgw->common->phpgw_footer();
?>
