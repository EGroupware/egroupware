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
		'currentapp' => 'admin',
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	$phpgw->template->set_file(array(
		'list' => 'accesslog.tpl',
		'row'  => 'accesslog_row.tpl'
	));

	$show_maxlog = 30;

	$phpgw->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$phpgw->template->set_var('lang_last_x_logins',lang('Last x logins',$show_maxlog));

	$phpgw->template->set_var('lang_loginid',lang('LoginID'));
	$phpgw->template->set_var('lang_ip',lang('IP'));
	$phpgw->template->set_var('lang_login',lang('Login'));
	$phpgw->template->set_var('lang_logout',lang('Logout'));
	$phpgw->template->set_var('lang_total',lang('Total'));

	$phpgw->db->query("select loginid,ip,li,lo from phpgw_access_log order by li desc "
				. "limit $show_maxlog");
	while ($phpgw->db->next_record())
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
		$phpgw->template->set_var('tr_color',$tr_color);

		if ($phpgw->db->f('li') && $phpgw->db->f('lo'))
		{
			$total = ($phpgw->db->f('lo') - $phpgw->db->f('li'));
			if ($total > 86400 && $total > 172800)
			{
				$total = gmdate('z \d\a\y\s - G:i:s',$total);
			}
			else if ($total > 172800)
			{
				$total = gmdate('z \d\a\y - G:i:s',$total);
			}
			else
			{
				$total = gmdate('G:i:s',$total);
			}
		}
		else
		{
			$total = '&nbsp;';
		}

		if ($phpgw->db->f('li'))
		{
			$li = $phpgw->common->show_date($phpgw->db->f('li'));
		}
		else
		{
			$li = '&nbsp;';
		}
	
		if ($phpgw->db->f('lo') != '')
		{
			$lo = $phpgw->common->show_date($phpgw->db->f('lo'));
		}
		else
		{
			$lo = '&nbsp;';
		}
	
		if (ereg('@',$phpgw->db->f('loginid')))
		{
			$t = split('@',$phpgw->db->f('loginid'));
			$loginid = $t[0];
		}
		else
		{
			$loginid = $phpgw->db->f('loginid');
		}

		$phpgw->template->set_var('row_loginid',$loginid);
		$phpgw->template->set_var('row_ip',$phpgw->db->f('ip'));
		$phpgw->template->set_var('row_li',$li);
		$phpgw->template->set_var('row_lo',$lo);
		$phpgw->template->set_var('row_total',$total);

		$phpgw->template->parse('rows','row',True);
	}

	$phpgw->db->query("select count(*) from phpgw_access_log");
	$phpgw->db->next_record();
	$total = $phpgw->db->f(0);

	$phpgw->db->query("select count(*) from phpgw_access_log where lo!=''");
	$phpgw->db->next_record();
	$loggedout = $phpgw->db->f(0);

	$percent = round((10000 * ($loggedout / $total)) / 100);

	$phpgw->template->set_var('bg_color',$phpgw_info['themes']['bg_color']);
	$phpgw->template->set_var('footer_total',lang('Total records') . ': ' . $total);
	$phpgw->template->set_var('lang_percent',lang('Percent of users that logged out') . ': ' . $percent . '%');

	$phpgw->template->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>