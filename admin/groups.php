<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	$phpgw_info['flags'] = array(
		'currentapp' => 'admin',
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	function account_total($query)
	{
		global $phpgw;

		if ($query) {
			$querymethod = " AND account_firstname LIKE '%$query%' OR account_lastname LIKE "
				. "'%$query%' OR account_lid LIKE '%$query%' ";
		}
 
		$phpgw->db->query("SELECT COUNT(*) FROM phpgw_accounts WHERE account_type='g'".$querymethod,__LINE__,__FILE__);
		$phpgw->db->next_record();

		return $phpgw->db->f(0);
	}

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_file(array(
		'groups'   => 'groups.tpl'
	));
	$p->set_block('groups','list','list');
	$p->set_block('groups','row','row');
	$p->set_block('groups','row_empty','row_empty');

	$total = account_total($query);
 
	$p->set_var('th_bg',$phpgw_info['theme']['th_bg']);

	$p->set_var('left_next_matchs',$phpgw->nextmatchs->left('/admin/groups.php',$start,$total));
	$p->set_var('right_next_matchs',$phpgw->nextmatchs->right('/admin/groups.php',$start,$total));
	$p->set_var('lang_groups',lang('user groups'));

	$p->set_var('sort_name',$phpgw->nextmatchs->show_sort_order($sort,"account_lid",$order,"/admin/groups.php",lang("name")));
	$p->set_var('header_edit',lang('Edit'));
	$p->set_var('header_delete',lang('Delete'));

	$account_info = $phpgw->accounts->get_list('groups',$start,$sort, $order, $query, $total);

	if (! count($account_info))
	{
		$p->set_var('message',lang('No matchs found'));
		$p->parse('rows','empty_row',True);
	}
	else
	{
		while (list($null,$account) = each($account_info))
		{
			$group_id   = $account['account_id'];
			$group_name = $account['account_lid'];

			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			$p->set_var("tr_color",$tr_color);

			if (! $group_name) { $group_name = '&nbsp;'; }

			$p->set_var('group_name',$group_name); 
			$p->set_var('edit_link','<a href="' . $phpgw->link('/admin/editgroup.php','group_id=' . $group_id) . '"> ' . lang('Edit') . ' </a>');
			$p->set_var('delete_link','<a href="' . $phpgw->link('/admin/deletegroup.php','group_id=' . $group_id) . '"> ' . lang('Delete') . ' </a>');
			$p->parse('rows','row',True);

		}
	}

	$p->set_var('new_action',$phpgw->link('/admin/newgroup.php'));
	$p->set_var('lang_add',lang('add'));

	$p->set_var('search_action',$phpgw->link('/admin/groups.php'));
	$p->set_var('lang_search',lang('search'));

	$p->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>
