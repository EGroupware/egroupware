<?php
  /**************************************************************************\
  * phpGroupWare - phpgroupware Peer Servers                                 *
  * http://www.phpgroupware.org                                              *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	$phpgw_info['flags'] = array(
		'currentapp' => 'admin',
		'enable_config_class' => True,
		'enable_nextmatchs_class' => True);

	include('../header.inc.php');

	$phpgw->template->set_file(array('server_list_t' => 'listservers.tpl'));
	$phpgw->template->set_block('server_list_t','server_list','list');

	$common_hidden_vars =
		  '<input type="hidden" name="sort"   value="' . $sort   . '"' . ">\n"
		. '<input type="hidden" name="order"  value="' . $order  . '"' . ">\n"
		. '<input type="hidden" name="query"  value="' . $query  . '"' . ">\n"
		. '<input type="hidden" name="start"  value="' . $start  . '"' . ">\n"
		. '<input type="hidden" name="filter" value="' . $filter . '"' . ">\n";

	$phpgw->template->set_var('lang_action',lang('Server List'));
	$phpgw->template->set_var('add_action',$phpgw->link('/admin/addserver.php'));
	$phpgw->template->set_var('lang_add',lang('Add'));
	$phpgw->template->set_var('title_servers',lang('Peer Servers'));
	$phpgw->template->set_var('lang_search',lang('Search'));
	$phpgw->template->set_var('actionurl',$phpgw->link('/admin/servers.php'));
	$phpgw->template->set_var('lang_done',lang('Done'));
	$phpgw->template->set_var('doneurl',$phpgw->link('/admin/index.php'));

	if(!$start)
	{
		$start = 0;
	}

	if($phpgw_info['user']['preferences']['common']['maxmatchs'] &&
		$phpgw_info['user']['preferences']['common']['maxmatchs'] > 0)
	{
		$limit = $phpgw_info['user']['preferences']['common']['maxmatchs'];
	}
	else
	{
		$limit = 15;
	}

	if(!$sort)
	{
		$sort = 'ASC';
	}

	if($order)
	{
		$ordermethod = "ORDER BY $order $sort ";
	}
	else
	{
		$ordermethod = " ORDER BY server_name ASC ";
	}

	if ($query)
	{
		$querymethod = " WHERE name like '%$query%' OR title like '%$query%'";
	}

	$is = CreateObject('phpgwapi.interserver');
	$servers = $is->get_list();

	$left  = $phpgw->nextmatchs->left('/admin/servers.php',$start,$total_records);
	$right = $phpgw->nextmatchs->right('/admin/servers.php',$start,$total_records);
	$phpgw->template->set_var('left',$left);
	$phpgw->template->set_var('right',$right);

	$phpgw->template->set_var('lang_showing',$phpgw->nextmatchs->show_hits($total_records,$start));

	$phpgw->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$phpgw->template->set_var('sort_name',$phpgw->nextmatchs->show_sort_order($sort,'server_name',$order,'/admin/servers.php',lang('Name')));
	$phpgw->template->set_var('sort_url', $phpgw->nextmatchs->show_sort_order($sort,'server_url',$order,'/admin/servers.php',lang('URL')));
	$phpgw->template->set_var('lang_default',lang('Default'));
	$phpgw->template->set_var('lang_edit',lang('Edit'));
	$phpgw->template->set_var('lang_delete',lang('Delete'));

	while(list($key,$server) = @each($servers))
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$phpgw->template->set_var('tr_color',$tr_color);
		$server_id = $server['server_id'];

		$phpgw->template->set_var(array(
			'server_name' => $phpgw->strip_html($server['server_name']),
			'server_url'  => $phpgw->strip_html($server['server_url'])
		));

		$phpgw->template->set_var('edit',$phpgw->link('/admin/editserver.php',"server_id=$server_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$phpgw->template->set_var('lang_edit_entry',lang('Edit'));

		$phpgw->template->set_var('delete',$phpgw->link('/admin/deleteserver.php',"server_id=$server_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
		$phpgw->template->set_var('lang_delete_entry',lang('Delete'));
		$phpgw->template->parse('list','server_list',True);
	}

	$phpgw->template->parse('out','server_list_t',True);
	$phpgw->template->p('out');

	$phpgw->common->phpgw_footer();
?>
