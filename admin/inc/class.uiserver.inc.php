<?php
	/**************************************************************************\
	* eGroupWare - phpgroupware Peer Servers                                   *
	* http://www.egroupware.org                                                *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uiserver
	{
		var $public_functions = array(
			'list_servers' => True,
			'edit'         => True,
			'add'          => True,
			'delete'       => True
		);

		var $start = 0;
		var $limit = 0;
		var $query = '';
		var $sort  = '';
		var $order = '';

		var $debug = False;

		var $bo = '';
		var $nextmatchs = '';

		function uiserver()
		{
			if ($GLOBALS['egw']->acl->check('peer_server_access',1,'admin'))
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			$this->acl_search = !$GLOBALS['egw']->acl->check('peer_server_access',2,'admin');
			$this->acl_add    = !$GLOBALS['egw']->acl->check('peer_server_access',4,'admin');
			$this->acl_view   = !$GLOBALS['egw']->acl->check('peer_server_access',8,'admin');
			$this->acl_edit   = !$GLOBALS['egw']->acl->check('peer_server_access',16,'admin');
			$this->acl_delete = !$GLOBALS['egw']->acl->check('peer_server_access',32,'admin');

			$this->bo =& CreateObject('admin.boserver',True);
			$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');

			$this->start = $this->bo->start;
			$this->limit = $this->bo->limit;
			$this->query = $this->bo->query;
			$this->sort  = $this->bo->sort;
			$this->order = $this->bo->order;
			if($this->debug) { $this->_debug_sqsof(); }
			/* _debug_array($this); */
		}

		function _debug_sqsof()
		{
			$data = array(
				'start' => $this->start,
				'limit' => $this->limit,
				'query' => $this->query,
				'sort'  => $this->sort,
				'order' => $this->order
			);
			echo '<br>UI:';
			_debug_array($data);
		}

		function save_sessiondata()
		{
			$data = array(
				'start' => $this->start,
				'limit' => $this->limit,
				'query' => $this->query,
				'sort'  => $this->sort,
				'order' => $this->order
			);
			$this->bo->save_sessiondata($data);
		}

		function formatted_list($name,$list,$id='',$default=False)
		{
			$select  = "\n" .'<select name="' . $name . '"' . ">\n";
			if($default)
			{
				$select .= '<option value="">' . lang('Please Select') . '</option>'."\n";
			}
			while (list($val,$key) = each($list))
			{
				$select .= '<option value="' . $key . '"';
				if ($key == $id && $id != '')
				{
					$select .= ' selected';
				}
				$select .= '>' . lang($val) . '</option>'."\n";
			}

			$select .= '</select>'."\n";

			return $select;
		}

		function list_servers()
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.lang('Peer Servers');
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_file(array('server_list_t' => 'listservers.tpl'));
			$GLOBALS['egw']->template->set_block('server_list_t','server_list','list');
			if (!$this->acl_search)
			{
				$GLOBALS['egw']->template->set_block('server_list_t','search','searchhandle');
			}
			if (!$this->acl_add)
			{
				$GLOBALS['egw']->template->set_block('server_list_t','add','addhandle');
			}

			$GLOBALS['egw']->template->set_var('lang_action',lang('Server List'));
			$GLOBALS['egw']->template->set_var('add_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.edit'));
			$GLOBALS['egw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['egw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.list_servers'));
			$GLOBALS['egw']->template->set_var('lang_done',lang('Cancel'));
			$GLOBALS['egw']->template->set_var('doneurl',$GLOBALS['egw']->link('/admin/index.php'));

			if(!$this->start)
			{
				$this->start = 0;
			}

			if($GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] &&
				$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] > 0)
			{
				$this->limit = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			}
			else
			{
				$this->limit = 15;
			}

			$this->save_sessiondata();
			$servers = $this->bo->list_servers();

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->total,'menuaction=admin.uiserver.list_servers');
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->total,'menuaction=admin.uiserver.list_servers');
			$GLOBALS['egw']->template->set_var('left',$left);
			$GLOBALS['egw']->template->set_var('right',$right);

			$GLOBALS['egw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->total,$this->start));
			$GLOBALS['egw']->template->set_var('th_bg',$GLOBALS['egw_info']['theme']['th_bg']);

			$GLOBALS['egw']->template->set_var('sort_name',
				$this->nextmatchs->show_sort_order($this->sort,'server_name',$this->order,'/index.php',lang('Name'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['egw']->template->set_var('sort_url',
				$this->nextmatchs->show_sort_order($this->sort,'server_url',$this->order,'/index.php',lang('URL'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['egw']->template->set_var('sort_mode',
				$this->nextmatchs->show_sort_order($this->sort,'server_mode',$this->order,'/index.php',lang('Mode'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['egw']->template->set_var('sort_security',
				$this->nextmatchs->show_sort_order($this->sort,'server_security',$this->order,'/index.php',lang('Security'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['egw']->template->set_var('lang_default',lang('Default'));
			$GLOBALS['egw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['egw']->template->set_var('lang_delete',lang('Delete'));

			while(list($key,$server) = @each($servers))
			{
				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['egw']->template->set_var('tr_color',$tr_color);
				$server_id = $server['server_id'];

				$GLOBALS['egw']->template->set_var(array(
					'server_name' => $GLOBALS['egw']->strip_html($server['server_name']),
					'server_url'  => $GLOBALS['egw']->strip_html($server['server_url']),
					'server_security' => $server['server_security'] ? strtoupper($server['server_security']) : lang('none'),
					'server_mode' => strtoupper($server['server_mode'])
				));

				$GLOBALS['egw']->template->set_var('edit','');
				$GLOBALS['egw']->template->set_var('delete','');
				if ($this->acl_edit)
				{
					$GLOBALS['egw']->template->set_var('edit','<a href="'.$GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.edit&server_id=' . $server_id).
						'">'.lang('Edit').'</a>');
				}
				if ($this->acl_delete)
				{
					$GLOBALS['egw']->template->set_var('delete','<a href="'.$GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.delete&server_id=' . $server_id).
						'">'.lang('Delete').'</a>');
				}
				$GLOBALS['egw']->template->parse('list','server_list',True);
			}

			$GLOBALS['egw']->template->parse('out','server_list_t',True);
			$GLOBALS['egw']->template->p('out');
		}

		/* This function handles add or edit */
		function edit()
		{
			if ($_POST['done'])
			{
				return $this->list_servers();
			}
			if ($_POST['delete'])
			{
				return $this->delete();
				
			}
			$server_id = get_var('server_id',array('POST','GET'));

			if (!$server_id && !$this->acl_add || $server_id && !$this->acl_edit)
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			$is =& CreateObject('phpgwapi.interserver');

			$GLOBALS['egw']->template->set_file(array('form' => 'server_form.tpl'));

			if (!$this->acl_delete || !$server_id)
			{
				$GLOBALS['egw']->template->set_block('form','delete','deletehandle');
				$GLOBALS['egw']->template->set_var('deletehandle','');
			}
			$server = $this->bo->read($server_id);

			if ($_POST['save'])
			{
				$errorcount = 0;

				$tmp = $is->name2id($_POST['server_name']);
				
				if($tmp && $server_id != $tmp)
				{
					$error[$errorcount++] = lang('That server name has been used already !');
				}

				if (!$_POST['server_name'])
				{
					$error[$errorcount++] = lang('Please enter a name for that server !');
				}

				if (!$error)
				{
					$server_info = array(
						'server_name' => addslashes($_POST['server_name']),
						'server_url'  => addslashes($_POST['server_url']),
						'trust_level' => (int)$_POST['trust_level'],
						'trust_rel'   => (int)$_POST['trust_rel'],
						'username'    => addslashes($_POST['server_username']),
						'password'    => $_POST['server_password'] ? $_POST['server_password'] : $server['password'],
						'server_mode' => addslashes($_POST['server_mode']),
						'server_security' => addslashes($_POST['server_security']),
						'admin_name'  => addslashes($_POST['admin_name']),
						'admin_email' => addslashes($_POST['admin_email'])
					);
					if($server_id)
					{
						$server_info['server_id'] = $server_id;
					}
					$newid = $this->bo->edit($server_info);
					$server = $this->bo->read($newid ? $newid : $server_info['server_id']);
				}
			}

			if ($errorcount)
			{
				$GLOBALS['egw']->template->set_var('message',$GLOBALS['egw']->common->error_list($error));
			}
			if (($_POST['save']) && (!$error) && (!$errorcount))
			{
				if($server_id)
				{
					$GLOBALS['egw']->template->set_var('message',lang('Server %1 has been updated',$_POST['server_name']));
				}
				else
				{
					$GLOBALS['egw']->template->set_var('message',lang('Server %1 has been added',$_POST['server_name']));
				}
			}
			if ((!$_POST['save']) && (!$error) && (!$errorcount))
			{
				$GLOBALS['egw']->template->set_var('message','');
			}

			$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin').' - '.($server_id ? lang('Edit Peer Server') : lang('Add Peer Server'));
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();

			$GLOBALS['egw']->template->set_var('actionurl',$GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.edit'));

			$GLOBALS['egw']->template->set_var('lang_name',lang('Server name'));
			$GLOBALS['egw']->template->set_var('lang_url',lang('Server URL'));
			$GLOBALS['egw']->template->set_var('lang_trust',lang('Trust Level'));
			$GLOBALS['egw']->template->set_var('lang_relationship',lang('Trust Relationship'));
			$GLOBALS['egw']->template->set_var('lang_username',lang('Server Username'));
			$GLOBALS['egw']->template->set_var('lang_password',lang('Server Password'));
			$GLOBALS['egw']->template->set_var('lang_mode',lang('Server Type(mode)'));
			$GLOBALS['egw']->template->set_var('lang_security',lang('Security'));
			$GLOBALS['egw']->template->set_var('lang_admin_name',lang('Admin Name'));
			$GLOBALS['egw']->template->set_var('lang_admin_email',lang('Admin Email'));
			$GLOBALS['egw']->template->set_var('lang_save',lang('Save'));
			$GLOBALS['egw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['egw']->template->set_var('lang_default',lang('Default'));
			$GLOBALS['egw']->template->set_var('lang_reset',lang('Clear Form'));
			$GLOBALS['egw']->template->set_var('lang_done',lang('Cancel'));
			$GLOBALS['egw']->template->set_var('lang_delete',lang('Delete'));

			$GLOBALS['egw']->template->set_var('server_name',$server['server_name']);
			$GLOBALS['egw']->template->set_var('server_url',$server['server_url']);
			$GLOBALS['egw']->template->set_var('server_username',$server['username']);
			$GLOBALS['egw']->template->set_var('server_mode',$this->formatted_list('server_mode',$is->server_modes,$server['server_mode']));
			$GLOBALS['egw']->template->set_var('server_security',$this->formatted_list('server_security',$is->security_types,$server['server_security']));
			$GLOBALS['egw']->template->set_var('ssl_note', function_exists('curl_init') ? '&nbsp;' : lang('Note: SSL available only if PHP is compiled with curl support'));
			$GLOBALS['egw']->template->set_var('pass_note',$server_id ? '<br>'.lang('(Stored password will not be shown here)') : '');
			$GLOBALS['egw']->template->set_var('trust_level',$this->formatted_list('trust_level',$is->trust_levels,$server['trust_level']));
			$GLOBALS['egw']->template->set_var('trust_relationship',$this->formatted_list('trust_rel',$is->trust_relationships,$server['trust_rel'],True));
			$GLOBALS['egw']->template->set_var('admin_name',$GLOBALS['egw']->strip_html($server['admin_name']));
			$GLOBALS['egw']->template->set_var('admin_email',$GLOBALS['egw']->strip_html($server['admin_email']));
			$GLOBALS['egw']->template->set_var('server_id',$server_id);

			$GLOBALS['egw']->template->set_var(array(
				'th'      => $GLOBALS['egw_info']['theme']['th_bg'],
				'row_on'  => $GLOBALS['egw_info']['theme']['row_on'],
				'row_off' => $GLOBALS['egw_info']['theme']['row_off']
			));
			$GLOBALS['egw']->template->pparse('phpgw_body','form');
		}

		function delete()
		{
			if (!$this->acl_delete)
			{
				$GLOBALS['egw']->redirect_link('/index.php');
			}
			$server_id = get_var('server_id',array('POST','GET'));
			if ($_POST['yes'] || $_POST['no'])
			{
				if ($_POST['yes'])
				{
					$this->bo->delete($server_id);
				}
				$GLOBALS['egw']->redirect_link('/index.php','menuaction=admin.uiserver.list_servers');
			}
			else
			{
				$GLOBALS['egw']->common->egw_header();
				echo parse_navbar();

				$GLOBALS['egw']->template->set_file(array('server_delete' => 'delete_common.tpl'));

				$GLOBALS['egw']->template->set_var(array(
					'form_action' => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.delete'),
					'hidden_vars' => '<input type="hidden" name="server_id" value="' . $server_id . '"><script>document.yesbutton.yesbutton.focus()</script>',
					'messages' => lang('Are you sure you want to delete this server?'),
					'no' => lang('No'),
					'yes' => lang('Yes'),
				));
				$GLOBALS['egw']->template->pparse('phpgw_body','server_delete');
			}
		}
	}
?>
