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

	$phpgw_info["flags"] = array(
		'currentapp' => 'addressbook',
		'enable_nextmatchs_class' => True);

	include('../header.inc.php');

	$t = new Template(PHPGW_APP_TPL);
	$t->set_file(array(
		'field_list_t' => 'listfields.tpl',
		'field_list'   => 'listfields.tpl'));
	$t->set_block('field_list_t','field_list','list');

	$common_hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
		. "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
		. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
		. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
		. "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n";

	$t->set_var('lang_action',lang('Custom Fields'));
	$t->set_var('add_action',$phpgw->link('/addressbook/addfield.php'));
	$t->set_var('lang_add',lang('Add'));
	$t->set_var('title_fields',lang('addressbook').' - '.lang('Custom Fields'));
	$t->set_var('lang_search',lang('Search'));
	$t->set_var('actionurl',$phpgw->link('/addressbook/fields.php'));
	$t->set_var('lang_done',lang('Done'));
	$t->set_var('doneurl',$phpgw->link('/admin/index.php'));

	if (!$start) { $start = 0; }

	if($phpgw_info["user"]["preferences"]["common"]["maxmatchs"] && $phpgw_info["user"]["preferences"]["common"]["maxmatchs"] > 0)
	{
		$limit = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
	}
	else
	{
		$limit = 15;
	}

	if (!$sort) { $sort = "ASC"; }

	$fields = read_custom_fields($start,$limit,$query,$sort,$order);
	$total_records = count($fields);

//--------------------------------- nextmatch --------------------------------------------

	$left = $phpgw->nextmatchs->left('/addressbook/fields.php',$start,$total_records);
	$right = $phpgw->nextmatchs->right('/addressbook/fields.php',$start,$total_records);
	$t->set_var('left',$left);
	$t->set_var('right',$right);

	if ($total_records > $limit)
	{
		$t->set_var('lang_showing',lang("showing x - x of x",($start + 1),($start + $limit),$total_records));
	}
	else
	{
		$t->set_var('lang_showing',lang("showing x",$total_records));
	}

// ------------------------------ end nextmatch ------------------------------------------

//------------------- list header variable template-declarations ------------------------- 

	$t->set_var('th_bg',$phpgw_info["theme"][th_bg]);
	$t->set_var('sort_field',$phpgw->nextmatchs->show_sort_order($sort,'name',$order,'/addressbook/fields.php',lang('Name')));
	$t->set_var('lang_edit',lang('Edit'));
	$t->set_var('lang_delete',lang('Delete'));

// -------------------------- end header declaration --------------------------------------

	for ($i=0;$i<count($fields);$i++)
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$t->set_var(tr_color,$tr_color);

		$field = $fields[$i]['name'];

//-------------------------- template declaration for list records ---------------------------

		$t->set_var('cfield',$field);

		$t->set_var('edit',$phpgw->link('/addressbook/editfield.php',"field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$t->set_var('lang_edit_entry',lang('Edit'));

		$t->set_var('delete',$phpgw->link('/addressbook/deletefield.php',"field=$field&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$t->set_var('lang_delete_entry',lang('Delete'));
		$t->parse('list','field_list',True);
	}
// ---------------------------- end record declaration -----------------------------------------

	$t->parse('out','field_list_t',True);
	$t->p('out');

	$phpgw->common->phpgw_footer();
?>
