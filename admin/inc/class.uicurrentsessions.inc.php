<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
	*  This file written by Joseph Engo <jengo@phpgroupware.org>               *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uicurrentsessions
	{
		var $template;
		var $bo;
		var $public_functions = array(
			'list_sessions' => True,
			'kill'          => True
		);

		function uicurrentsessions()
		{
			if ($GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			$this->template   =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$this->bo         =& CreateObject('admin.bocurrentsessions');
			$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');
		}

		function header()
		{
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();
		}

		function store_location($info)
		{
			$GLOBALS['egw']->session->appsession('currentsessions_session_data','admin',$info);
		}

		function list_sessions()
		{
			$info = $GLOBALS['egw']->session->appsession('currentsessions_session_data','admin');
			if (! is_array($info))
			{
				$info = array(
					'start' => 0,
					'sort'  => 'DESC',
					'order' => 'session_dla'
				);
				$this->store_location($info);
			}

			if (isset($_REQUEST['start']) || $_REQUEST['sort'] || $_REQUEST['order'])
			{
				if ($_REQUEST['start'] == 0 || $_REQUEST['start'] && $_REQUEST['start'] != $info['start'])
				{
					$info['start'] = $_REQUEST['start'];
				}

				if ($_REQUEST['sort'] && $_REQUEST['sort'] != $info['sort'])
				{
					$info['sort'] = $_REQUEST['sort'];
				}

				if ($_REQUEST['order'] && $_REQUEST['order'] != $info['order'])
				{
					$info['order'] = $_REQUEST['order'];
				}

				$this->store_location($info);
			}

			$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('List of current users');
			$this->header();

			$this->template->set_file('current','currentusers.tpl');
			$this->template->set_block('current','list','list');
			$this->template->set_block('current','row','row');

			$can_view_action = !$GLOBALS['egw']->acl->check('current_sessions_access',2,'admin');
			$can_view_ip     = !$GLOBALS['egw']->acl->check('current_sessions_access',4,'admin');
			$can_kill        = !$GLOBALS['egw']->acl->check('current_sessions_access',8,'admin');

			$total = $this->bo->total();

			$this->template->set_var('left_next_matchs',$this->nextmatchs->left('/admin/currentusers.php',$info['start'],$total));
			$this->template->set_var('right_next_matchs',$this->nextmatchs->right('/admin/currentusers.php',$info['start'],$total));
			$this->template->set_var('start_total',$this->nextmatchs->show_hits($total,$info['start']));

			$this->template->set_var('sort_loginid',$this->nextmatchs->show_sort_order($info['sort'],'session_lid',$info['order'],
				'/admin/currentusers.php',lang('LoginID')));
			$this->template->set_var('sort_ip',$this->nextmatchs->show_sort_order($info['sort'],'session_ip',$info['order'],
				'/admin/currentusers.php',lang('IP')));
			$this->template->set_var('sort_login_time',$this->nextmatchs->show_sort_order($info['sort'],'session_logintime',$info['order'],
				'/admin/currentusers.php',lang('Login Time')));
			$this->template->set_var('sort_action',$this->nextmatchs->show_sort_order($info['sort'],'session_action',$info['order'],
				'/admin/currentusers.php',lang('Action')));
			$this->template->set_var('sort_idle',$this->nextmatchs->show_sort_order($info['sort'],'session_dla',$info['order'],
				'/admin/currentusers.php',lang('idle')));
			$this->template->set_var('lang_kill',lang('Kill'));

			$values = $this->bo->list_sessions($info['start'],$info['order'],$info['sort']);

			while (list(,$value) = @each($values))
			{
				$this->nextmatchs->template_alternate_row_color($this->template);

				$this->template->set_var('row_loginid',$value['session_lid']);

				$this->template->set_var('row_ip',$can_view_ip?$value['session_ip']:'&nbsp;');

				$this->template->set_var('row_logintime',$value['session_logintime']);
				$this->template->set_var('row_idle',$value['session_idle']);

				if ($value['session_action'] && $can_view_action)
				{
					$this->template->set_var('row_action',$GLOBALS['egw']->strip_html($value['session_action']));
				}
				else
				{
					$this->template->set_var('row_action','&nbsp;');
				}

				if ($value['session_id'] != $GLOBALS['egw_info']['user']['sessionid'] && $can_kill)
				{
					$this->template->set_var('row_kill','<a href="' . $GLOBALS['egw']->link('/index.php','menuaction=admin.uicurrentsessions.kill&ksession='
						. $value['session_id'] . '&kill=true') . '">' . lang('Kill').'</a>');
				}
				else
				{
					$this->template->set_var('row_kill','&nbsp;');
				}

				$this->template->parse('rows','row',True);
			}

			$this->template->pfp('out','list');
		}

		function kill()
		{
			if ($GLOBALS['egw']->acl->check('current_sessions_access',8,'admin'))
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Kill session');
			$this->header();
			$this->template->set_file('form','kill_session.tpl');

			$this->template->set_var('lang_message',lang('Are you sure you want to kill this session ?'));
			$this->template->set_var('link_no','<a href="' . $GLOBALS['egw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">' . lang('No') . '</a>');
			$this->template->set_var('link_yes','<a href="' . $GLOBALS['egw']->link('/index.php','menuaction=admin.bocurrentsessions.kill&ksession=' . $_GET['ksession']) . '">' . lang('Yes') . '</a>');

			$this->template->pfp('out','form');
		}
	}
