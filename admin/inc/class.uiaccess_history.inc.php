<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
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
			if ($GLOBALS['egw']->acl->check('access_log_access',1,'admin'))
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			
			$this->bo         =& CreateObject('admin.boaccess_history');
			$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');
			$this->template   = $GLOBALS['egw']->template;
			$this->template->set_file(
				Array(
					'accesslog' => 'accesslog.tpl'
				)
			);
			$this->template->set_block('accesslog','list');
			$this->template->set_block('accesslog','row');
			$this->template->set_block('accesslog','row_empty');
		}

		function list_history()
		{
			$account_id = get_var('account_id',array('GET','POST'));
			$start = get_var('start',array('POST'),0);
			$sort = get_var('sort',array('POST'),0);
			$order = get_var('order',array('POST'),0);
			
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('View access log');
			if(!@is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['egw']->js->validate_file('jscode','openwindow','admin');
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();

			$total_records = $this->bo->total($account_id);

			$var = Array(
				'th_bg'       => $GLOBALS['egw_info']['theme']['th_bg'],
				'nextmatchs_left'  => $this->nextmatchs->left('/index.php',$start,$total_records,'&menuaction=admin.uiaccess_history.list_history&account_id=' . $account_id),
				'nextmatchs_right' => $this->nextmatchs->right('/index.php',$start,$total_records,'&menuaction=admin.uiaccess_history.list_history&account_id=' . $account_id),
				'showing'          => $this->nextmatchs->show_hits($total_records,$start),
				'lang_loginid'     => lang('LoginID'),
				'lang_ip'     => lang('IP'),
				'lang_login'  => lang('Login'),
				'lang_logout' => lang('Logout'),
				'lang_total'  => lang('Total')
			);

			if ($account_id)
			{
				$var['link_return_to_view_account'] = '<a href="' . $GLOBALS['egw']->link('/index.php',
					Array(
						'menuaction' => 'admin.uiaccounts.view',
						'account_id' => $account_id
					)
				) . '">' . lang('Return to view account') . '</a>';
				$var['lang_last_x_logins'] = lang('Last %1 logins for %2',$total_records,$GLOBALS['egw']->common->grab_owner_name($account_id));
			}
			else
			{
				$var['lang_last_x_logins'] = lang('Last %1 logins',$total_records);
			}

			$this->template->set_var($var);

			$records = $this->bo->list_history($account_id,$start,$order,$sort);
			while (is_array($records) && list(,$record) = each($records))
			{
				$this->nextmatchs->template_alternate_row_color($this->template);

				$var = array(
					'row_loginid' => $record['loginid'],
					'row_ip'      => $record['ip'],
					'row_li'      => $record['li'],
					'row_lo'      => $record['account_id'] ? $record['lo'] : '<b>'.lang($record['sessionid']).'</b>',
					'row_total'   => ($record['lo']?$record['total']:'&nbsp;')
				);
				$this->template->set_var($var);
				$this->template->fp('rows_access','row',True);
			}

			if (! $total_records && $account_id)
			{
				$this->nextmatchs->template_alternate_row_color($this->template);
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

			$var = Array(
				'bg_color'     => $GLOBALS['egw_info']['themes']['bg_color'],
				'footer_total' => lang('Total records') . ': ' . $total_records
			);
			if ($account_id)
			{
				$var['lang_percent'] = lang('Percent this user has logged out') . ': ' . $percent . '%';
			}
			else
			{
				$var['lang_percent'] = lang('Percent of users that logged out') . ': ' . $percent . '%';
			}

			// create the menu on the left, if needed
			$menuClass =& CreateObject('admin.uimenuclass');
			$var['rows'] = $menuClass->createHTMLCode('view_account');

			$this->template->set_var($var);
			$this->template->pfp('out','list');
		}
	}
