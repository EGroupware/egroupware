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

	$phpgw_info['flags'] = array(
		'currentapp'              => 'admin',
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_file(array('current' => 'currentusers.tpl'));
	$p->set_block('current','list','list');
	$p->set_block('current','row','row');

	if (! $start)
	{
		$start = 0;
	}

	$limit = $phpgw->db->limit($start);
	$phpgw->db->query("select count(*) from phpgw_sessions where session_flags != 'A'",__LINE__,__FILE__);
	$phpgw->db->next_record();

	$total = $phpgw->db->f(0);

	$p->set_var('lang_current_users',lang('List of current users'));
	$p->set_var('bg_color',$phpgw_info['theme'][bg_color]);
	$p->set_var('left_next_matchs',$phpgw->nextmatchs->left('currentusers.php',$start,$total));
	$p->set_var('right_next_matchs',$phpgw->nextmatchs->right('currentusers.php',$start,$total));
	$p->set_var('th_bg',$phpgw_info['theme']['th_bg']);

	$p->set_var('sort_loginid',$phpgw->nextmatchs->show_sort_order($sort,'session_lid',$order,
		'/admin/currentusers.php',lang('LoginID')));
	$p->set_var('sort_ip',$phpgw->nextmatchs->show_sort_order($sort,'session_ip',$order,
		'/admin/currentusers.php',lang('IP')));
	$p->set_var('sort_login_time',$phpgw->nextmatchs->show_sort_order($sort,'session_logintime',$order,
		'/admin/currentusers.php',lang('Login Time')));
	$p->set_var('sort_action',$phpgw->nextmatchs->show_sort_order($sort,'session_action',$order,
		'/admin/currentusers.php',lang('Action')));
	$p->set_var('sort_idle',$phpgw->nextmatchs->show_sort_order($sort,'session_dla',$order,
		'/admin/currentusers.php',lang('idle')));
	$p->set_var('lang_kill',lang('Kill'));

	if ($order)
	{
		$ordermethod = "order by $order $sort";
	}
	else
	{
		$ordermethod = 'order by session_dla asc';
	}

	$phpgw->db->query("select * from phpgw_sessions where session_flags != 'A' $ordermethod " . $phpgw->db->limit($start),__LINE__,__FILE__);

	$i = 0;
	while ($phpgw->db->next_record())
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$p->set_var('tr_color',$tr_color);

		if (ereg('@',$phpgw->db->f('session_lid')))
		{
			$t = split('@',$phpgw->db->f('session_lid'));
			$loginid = $t[0];
		}
		else
		{
			$loginid = $phpgw->db->f('session_lid');
		}

		$p->set_var('row_loginid',$loginid);
		$p->set_var('row_ip',$phpgw->db->f('session_ip'));
		$p->set_var('row_logintime',$phpgw->common->show_date($phpgw->db->f('session_logintime')));
		if($phpgw->db->f('session_action'))
		{
			$p->set_var('row_action',$phpgw->strip_html($phpgw->db->f('session_action')));
		}
		else
		{
			 $p->set_var('row_action','&nbsp;');
		}
		$p->set_var('row_idle',gmdate('G:i:s',(time() - $phpgw->db->f('session_dla'))));

		if ($phpgw->db->f('session_id') != $phpgw_info['user']['sessionid'])
		{
			$p->set_var('row_kill','<a href="' . $phpgw->link('/admin/killsession.php','ksession='
				. $phpgw->db->f('session_id') . '&kill=true') . '">' . lang('Kill').'</a>');
		}
		else
		{
			$p->set_var('row_kill','&nbsp;');
		}

		$p->parse('rows','row',True);
	}

	$p->pparse('out','list');
	$phpgw->common->phpgw_footer();
?>
