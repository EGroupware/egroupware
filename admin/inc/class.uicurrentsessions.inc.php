<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
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
			$this->template   = createobject('phpgwapi.Template',PHPGW_APP_TPL);
			$this->bo         = createobject('admin.bocurrentsessions');
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');
		}

		function header()
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
		}

		function store_location($info)
		{
			$GLOBALS['phpgw']->session->appsession('currentsessions_session_data','admin',$info);
		}

		function list_sessions()
		{
			$info = $GLOBALS['phpgw']->session->appsession('currentsessions_session_data','admin');
			if (! is_array($info))
			{
				$info = array(
					'start' => 0,
					'sort'  => 'asc',
					'order' => 'session_dla'
				);
				$this->store_location($info);
			}

			if ($GLOBALS['start'] || $GLOBALS['sort'] || $GLOBALS['order'])
			{
				if ($GLOBALS['start'] == 0 || $GLOBALS['start'] && $GLOBALS['start'] != $info['start'])
				{
					$info['start'] = $GLOBALS['start'];
				}

				if ($GLOBALS['sort'] && $GLOBALS['sort'] != $info['sort'])
				{
					$info['sort'] = $GLOBALS['sort'];
				}

				if ($GLOBALS['order'] && $GLOBALS['order'] != $info['order'])
				{
					$info['order'] = $GLOBALS['order'];
				}

				$this->store_location($info);
			}

			$this->header();

			$this->template->set_file('current','currentusers.tpl');
			$this->template->set_block('current','list','list');
			$this->template->set_block('current','row','row');

			if (! $GLOBALS['phpgw']->acl->check('current_sessions_access',4,'admin'))
			{
				$can_view_ip = True;
			}

			if (! $GLOBALS['phpgw']->acl->check('current_sessions_access',2,'admin'))
			{
				$can_view_action = True;
			}

			$total = $this->bo->total();

			$this->template->set_var('lang_current_users',lang('List of current users'));
			$this->template->set_var('bg_color',$GLOBALS['phpgw_info']['theme']['bg_color']);
			$this->template->set_var('left_next_matchs',$this->nextmatchs->left('/admin/currentusers.php',$info['start'],$total));
			$this->template->set_var('right_next_matchs',$this->nextmatchs->right('/admin/currentusers.php',$info['start'],$total));
			$this->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);

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

			while (list(,$value) = each($values))
			{
				$this->nextmatchs->template_alternate_row_color(&$this->template);

				$this->template->set_var('row_loginid',$value['session_lid']);

				if ($can_view_ip)
				{
					$this->template->set_var('row_ip',$value['session_ip']);
				}
				else
				{
					$this->template->set_var('row_ip','&nbsp; -- &nbsp;');
				}

				$this->template->set_var('row_logintime',$value['session_logintime']);
				$this->template->set_var('row_idle',$value['session_idle']);

				if ($value['session_action'] && $can_view_action)
				{
					$this->template->set_var('row_action',$GLOBALS['phpgw']->strip_html($value['session_action']));
				}
				elseif(! $can_view_action)
				{
					$this->template->set_var('row_action','&nbsp; -- &nbsp;');
				}
				else
				{
					$this->template->set_var('row_action','&nbsp;');
				}

				if ($value['session_id'] != $GLOBALS['phpgw_info']['user']['sessionid'] && ! $GLOBALS['phpgw']->acl->check('current_sessions_access',8,'admin'))
				{
					$this->template->set_var('row_kill','<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.kill&ksession='
						. $value['session_id'] . '&kill=true') . '">' . lang('Kill').'</a>');
				}
				else
				{
					$this->template->set_var('row_kill','&nbsp;');
				}

				$this->template->parse('rows','row',True);
			}

			$this->template->pparse('out','list');
		}

		function kill()
		{
			if ($GLOBALS['phpgw']->acl->check('current_sessions_access',8,'admin'))
			{
				$this->list_sessions();
				return False;
			}

			$this->header();
			$this->template->set_file('form','kill_session.tpl');

			$this->template->set_var('lang_title',lang('Kill session'));
			$this->template->set_var('lang_message',lang('Are you sure you want to kill this session ?'));
			$this->template->set_var('link_no','<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions') . '">' . lang('No') . '</a>');
			$this->template->set_var('link_yes','<a href="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.bocurrentsessions.kill&ksession=' . $GLOBALS['HTTP_GET_VARS']['ksession']) . '">' . lang('Yes') . '</a>');

			$this->template->pfp('out','form');
		}
	}
