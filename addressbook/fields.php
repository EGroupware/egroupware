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

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'addressbook',
		'enable_nextmatchs_class' => True
	);

	include('../header.inc.php');

	$GLOBALS['phpgw']->template->set_file(array(
		'field_list_t' => 'listfields.tpl',
		'field_list'   => 'listfields.tpl'
	));
	$GLOBALS['phpgw']->template->set_block('field_list_t','field_list','list');

	$field  = $HTTP_POST_VARS['field'];
	$start  = $HTTP_POST_VARS['start'];
	$query  = $HTTP_POST_VARS['query'];
	$sort   = $HTTP_POST_VARS['sort'];
	$order  = $HTTP_POST_VARS['order'];
	$filter = $HTTP_POST_VARS['filter'];

	$common_hidden_vars =
		  '<input type="hidden" name="sort"   value="' . $sort   . '">' . "\n"
		. '<input type="hidden" name="order"  value="' . $order  . '">' . "\n"
		. '<input type="hidden" name="query"  value="' . $query  . '">' . "\n"
		. '<input type="hidden" name="start"  value="' . $start  . '">' . "\n"
		. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

	$GLOBALS['phpgw']->template->set_var('lang_action',lang('Custom Fields'));
	$GLOBALS['phpgw']->template->set_var('add_action',$GLOBALS['phpgw']->link('/addressbook/addfield.php'));
	$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
	$GLOBALS['phpgw']->template->set_var('title_fields',lang('addressbook').' - '.lang('Custom Fields'));
	$GLOBALS['phpgw']->template->set_var('lang_search',lang('Search'));
	$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/addressbook/fields.php'));
	$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
	$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/admin/index.php'));

	if (!$start)
	{
		$start = 0;
	}

	if (!$sort)
	{
		$sort = 'ASC';
	}

	$fields = read_custom_fields($start,$limit,$query,$sort,$order);
	$total_records = count($fields);

	$GLOBALS['phpgw']->template->set_var('left',$GLOBALS['phpgw']->nextmatchs->left('/addressbook/fields.php',$start,$total_records));
	$GLOBALS['phpgw']->template->set_var('right',$GLOBALS['phpgw']->nextmatchs->right('/addressbook/fields.php',$start,$total_records));

	$GLOBALS['phpgw']->template->set_var('lang_showing',$GLOBALS['phpgw']->nextmatchs->show_hits($total_records,$start));

	$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
	$GLOBALS['phpgw']->template->set_var('sort_field',$GLOBALS['phpgw']->nextmatchs->show_sort_order($sort,'name',$order,'/addressbook/fields.php',lang('Name')));
	$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
	$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));

	for ($i=0;$i<count($fields);$i++)
	{
		$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
		$GLOBALS['phpgw']->template->set_var(tr_color,$tr_color);

		$field = $fields[$i]['name'];

		$GLOBALS['phpgw']->template->set_var('cfield',$field);

		$GLOBALS['phpgw']->template->set_var('edit',$GLOBALS['phpgw']->link('/addressbook/editfield.php',"field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$GLOBALS['phpgw']->template->set_var('lang_edit_entry',lang('Edit'));

		$GLOBALS['phpgw']->template->set_var('delete',$GLOBALS['phpgw']->link('/addressbook/deletefield.php',"field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$GLOBALS['phpgw']->template->set_var('lang_delete_entry',lang('Delete'));
		$GLOBALS['phpgw']->template->parse('list','field_list',True);
	}

	$GLOBALS['phpgw']->template->parse('out','field_list_t',True);
	$GLOBALS['phpgw']->template->p('out');

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
