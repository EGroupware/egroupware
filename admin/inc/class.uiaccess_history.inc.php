<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uiaccess_history
	{
		var $template;
		var $public_functions = array(
				'list_history' => True
			);

		function uiaccess_history()
		{
			global $phpgw;

			$this->bo         = createobject('admin.boaccess_history');
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');
			$this->template   = $phpgw->template;
			$this->template->set_file(array(
				'accesslog' => 'accesslog.tpl'
			));
			$this->template->set_block('accesslog','list');
			$this->template->set_block('accesslog','row');
			$this->template->set_block('accesslog','row_empty');
		}

		function list_history()
		{
			global $account_id, $phpgw, $phpgw_info, $start, $sort, $order;

			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$total_records = $this->bo->total($account_id);
			$this->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);

			$this->template->set_var('nextmatchs_left',$this->nextmatchs->left('/index.php',$start,$total_records,'&menuaction=admin.uiaccess_history.list_history&account_id=' . $account_id));
			$this->template->set_var('nextmatchs_right',$this->nextmatchs->right('/index.php',$start,$total_records,'&menuaction=admin.uiaccess_history.list_history&account_id=' . $account_id));

			if ($account_id)
			{
				$this->template->set_var('link_return_to_view_account','<a href="' . $phpgw->link('/admin/viewaccount.php','account_id=' . $account_id) . '">' . lang('Return to view account') . '</a>');
				$fullname = $this->bo->grab_fullname($account_id);
				$this->template->set_var('lang_last_x_logins',lang('Last %1 logins for %2',$total_records,$fullname));
			}
			else
			{
				$this->template->set_var('lang_last_x_logins',lang('Last x logins',$total_records));
			}

			$this->template->set_var('showing',$this->nextmatchs->show_hits($total_records,$start));
			$this->template->set_var('lang_loginid',lang('LoginID'));
			$this->template->set_var('lang_ip',lang('IP'));
			$this->template->set_var('lang_login',lang('Login'));
			$this->template->set_var('lang_logout',lang('Logout'));
			$this->template->set_var('lang_total',lang('Total'));

			$records = $this->bo->list_history($account_id,$start,$order,$sort);
			while (is_array($records) && list(,$record) = each($records))
			{			
				$this->nextmatchs->template_alternate_row_color(&$this->template);

				$this->template->set_var('row_loginid',$record['loginid']);
				$this->template->set_var('row_ip',$record['ip']);
				$this->template->set_var('row_li',$record['li']);
				$this->template->set_var('row_lo',$record['lo']);
				$this->template->set_var('row_total',$record['total']);

				$this->template->fp('rows_access','row',True);
			}

			if (! $total_records && $account_id)
			{
				$this->nextmatchs->template_alternate_row_color(&$this->template);
				$this->template->set_var('row_message',lang('No login history exists for this user'));
				$this->template->fp('rows_access','row_empty',True);
			}

			$loggedout = $this->bo->return_logged_out($account_id);

			if ($total_records)
			{
				$percent = round((10000 * ($loggedout / $total_records)) / 100);
			}
			else
			{
				$percent = '0';
			}

			$this->template->set_var('bg_color',$phpgw_info['themes']['bg_color']);
			$this->template->set_var('footer_total',lang('Total records') . ': ' . $total_records);
			if ($account_id)
			{
				$this->template->set_var('lang_percent',lang('Percent this user has logged out') . ': ' . $percent . '%');
			}
			else
			{
				$this->template->set_var('lang_percent',lang('Percent of users that logged out') . ': ' . $percent . '%');
			}
			
			// create the menu on the left, if needed
			$menuClass = CreateObject('admin.uimenuclass');
			$this->template->set_var('rows',$menuClass->createHTMLCode('view_account'));

			$this->template->pfp('out','list');
		}
	}
