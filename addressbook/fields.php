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

	$phpgw_info['flags'] = array(
		'currentapp' => 'addressbook',
		'enable_nextmatchs_class' => True
	);

	include('../header.inc.php');

	$phpgw->template->set_file(array(
		'field_list_t' => 'listfields.tpl',
		'field_list'   => 'listfields.tpl'
	));
	$phpgw->template->set_block('field_list_t','field_list','list');

	$common_hidden_vars =
		  '<input type="hidden" name="sort"   value="' . $sort   . '">' . "\n"
		. '<input type="hidden" name="order"  value="' . $order  . '">' . "\n"
		. '<input type="hidden" name="query"  value="' . $query  . '">' . "\n"
		. '<input type="hidden" name="start"  value="' . $start  . '">' . "\n"
		. '<input type="hidden" name="filter" value="' . $filter . '">' . "\n";

	$phpgw->template->set_var('lang_action',lang('Custom Fields'));
	$phpgw->template->set_var('add_action',$phpgw->link('/addressbook/addfield.php'));
	$phpgw->template->set_var('lang_add',lang('Add'));
	$phpgw->template->set_var('title_fields',lang('addressbook').' - '.lang('Custom Fields'));
	$phpgw->template->set_var('lang_search',lang('Search'));
	$phpgw->template->set_var('actionurl',$phpgw->link('/addressbook/fields.php'));
	$phpgw->template->set_var('lang_done',lang('Done'));
	$phpgw->template->set_var('doneurl',$phpgw->link('/admin/index.php'));

	if (!$start) { $start = 0; }

	if (!$sort) { $sort = 'ASC'; }

	$fields = read_custom_fields($start,$limit,$query,$sort,$order);
	$total_records = count($fields);

	$phpgw->template->set_var('left',$phpgw->nextmatchs->left('/addressbook/fields.php',$start,$total_records));
	$phpgw->template->set_var('right',$phpgw->nextmatchs->right('/addressbook/fields.php',$start,$total_records));

	$phpgw->template->set_var('lang_showing',$phpgw->nextmatchs->show_hits($total_records,$start));

	$phpgw->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$phpgw->template->set_var('sort_field',$phpgw->nextmatchs->show_sort_order($sort,'name',$order,'/addressbook/fields.php',lang('Name')));
	$phpgw->template->set_var('lang_edit',lang('Edit'));
	$phpgw->template->set_var('lang_delete',lang('Delete'));

	for ($i=0;$i<count($fields);$i++)
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$phpgw->template->set_var(tr_color,$tr_color);

		$field = $fields[$i]['name'];

		$phpgw->template->set_var('cfield',$field);

		$phpgw->template->set_var('edit',$phpgw->link('/addressbook/editfield.php',"field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$phpgw->template->set_var('lang_edit_entry',lang('Edit'));

		$phpgw->template->set_var('delete',$phpgw->link('/addressbook/deletefield.php',"field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$phpgw->template->set_var('lang_delete_entry',lang('Delete'));
		$phpgw->template->parse('list','field_list',True);
	}

	$phpgw->template->parse('out','field_list_t',True);
	$phpgw->template->p('out');

	$phpgw->common->phpgw_footer();
?>
