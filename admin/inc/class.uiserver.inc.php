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
			$this->bo = createobject('admin.boserver',True);
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');

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

		function formatted_list($name,$list,$id='',$default=False,$java=False)
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
			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_file(array('server_list_t' => 'listservers.tpl'));
			$GLOBALS['phpgw']->template->set_block('server_list_t','server_list','list');

			$GLOBALS['phpgw']->template->set_var('lang_action',lang('Server List'));
			$GLOBALS['phpgw']->template->set_var('add_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.edit'));
			$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
			$GLOBALS['phpgw']->template->set_var('title_servers',lang('Peer Servers'));
			$GLOBALS['phpgw']->template->set_var('lang_search',lang('Search'));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.list_servers'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/admin/index.php'));

			if(!$this->start)
			{
				$this->start = 0;
			}

			if($GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'] &&
				$GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'] > 0)
			{
				$this->limit = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}
			else
			{
				$this->limit = 15;
			}

			$this->save_sessiondata();
			$servers = $this->bo->list_servers();

			$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->total,'menuaction=admin.uiserver.list_servers');
			$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->total,'menuaction=admin.uiserver.list_servers');
			$GLOBALS['phpgw']->template->set_var('left',$left);
			$GLOBALS['phpgw']->template->set_var('right',$right);

			$GLOBALS['phpgw']->template->set_var('lang_showing',$this->nextmatchs->show_hits($this->bo->total,$this->start));
			$GLOBALS['phpgw']->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);

			$GLOBALS['phpgw']->template->set_var('sort_name',
				$this->nextmatchs->show_sort_order($this->sort,'server_name',$this->order,'/index.php',lang('Name'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['phpgw']->template->set_var('sort_url',
				$this->nextmatchs->show_sort_order($this->sort,'server_url',$this->order,'/index.php',lang('URL'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['phpgw']->template->set_var('sort_mode',
				$this->nextmatchs->show_sort_order($this->sort,'server_mode',$this->order,'/index.php',lang('Mode'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['phpgw']->template->set_var('sort_security',
				$this->nextmatchs->show_sort_order($this->sort,'server_security',$this->order,'/index.php',lang('Security'),'&menuaction=admin.uiserver.list_servers'));
			$GLOBALS['phpgw']->template->set_var('lang_default',lang('Default'));
			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));

			while(list($key,$server) = @each($servers))
			{
				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
				$GLOBALS['phpgw']->template->set_var('tr_color',$tr_color);
				$server_id = $server['server_id'];

				$GLOBALS['phpgw']->template->set_var(array(
					'server_name' => $GLOBALS['phpgw']->strip_html($server['server_name']),
					'server_url'  => $GLOBALS['phpgw']->strip_html($server['server_url']),
					'server_security' => $server['server_security'] ? strtoupper($server['server_security']) : lang('none'),
					'server_mode' => strtoupper($server['server_mode'])
				));

				$GLOBALS['phpgw']->template->set_var('edit',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.edit&server_id=' . $server_id));
				$GLOBALS['phpgw']->template->set_var('lang_edit_entry',lang('Edit'));

				$GLOBALS['phpgw']->template->set_var('delete',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.delete&server_id=' . $server_id));
				$GLOBALS['phpgw']->template->set_var('lang_delete_entry',lang('Delete'));
				$GLOBALS['phpgw']->template->parse('list','server_list',True);
			}

			$GLOBALS['phpgw']->template->parse('out','server_list_t',True);
			$GLOBALS['phpgw']->template->p('out');
		}

		/* This function handles add or edit */
		function edit()
		{
			$is = CreateObject('phpgwapi.interserver');

			$GLOBALS['phpgw']->template->set_file(array('form' => 'server_form.tpl'));
			$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
			$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

			$server = $this->bo->read($GLOBALS['HTTP_GET_VARS']['server_id']);

			if ($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$errorcount = 0;

				$tmp = $is->name2id($GLOBALS['HTTP_POST_VARS']['server_name']);
				
				if($tmp && $GLOBALS['HTTP_POST_VARS']['server_id'])
				{
					$error[$errorcount++] = lang('That server name has been used already !');
				}

				if (!$GLOBALS['HTTP_POST_VARS']['server_name'])
				{
					$error[$errorcount++] = lang('Please enter a name for that server !');
				}

				if (!$error)
				{
					$server_info = array(
						'server_name' => addslashes($GLOBALS['HTTP_POST_VARS']['server_name']),
						'server_url'  => addslashes($GLOBALS['HTTP_POST_VARS']['server_url']),
						'trust_level' => intval($GLOBALS['HTTP_POST_VARS']['trust_level']),
						'trust_rel'   => intval($GLOBALS['HTTP_POST_VARS']['trust_rel']),
						'username'    => addslashes($GLOBALS['HTTP_POST_VARS']['server_username']),
						'password'    => $GLOBALS['HTTP_POST_VARS']['server_password'] ? $GLOBALS['HTTP_POST_VARS']['server_password'] : $server['password'],
						'server_mode' => addslashes($GLOBALS['HTTP_POST_VARS']['server_mode']),
						'server_security' => addslashes($GLOBALS['HTTP_POST_VARS']['server_security']),
						'admin_name'  => addslashes($GLOBALS['HTTP_POST_VARS']['admin_name']),
						'admin_email' => addslashes($GLOBALS['HTTP_POST_VARS']['admin_email'])
					);
					if($GLOBALS['HTTP_GET_VARS']['server_id'])
					{
						$server_info['server_id'] = $GLOBALS['HTTP_GET_VARS']['server_id'];
					}
					$newid = $this->bo->edit($server_info);
					$server = $this->bo->read($newid ? $newid : $server_info['server_id']);
				}
			}

			if ($errorcount)
			{
				$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
			}
			if (($GLOBALS['HTTP_POST_VARS']['submit']) && (!$error) && (!$errorcount))
			{
				if($GLOBALS['HTTP_GET_VARS']['server_id'])
				{
					$GLOBALS['phpgw']->template->set_var('message',lang('Server x has been updated',$GLOBALS['HTTP_POST_VARS']['server_name']));
				}
				else
				{
					$GLOBALS['phpgw']->template->set_var('message',lang('Server %1 has been added',$GLOBALS['HTTP_POST_VARS']['server_name']));
				}
			}
			if ((!$GLOBALS['HTTP_POST_VARS']['submit']) && (!$error) && (!$errorcount))
			{
				$GLOBALS['phpgw']->template->set_var('message','');
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$GLOBALS['phpgw']->template->set_var('title_servers',$GLOBALS['HTTP_GET_VARS']['server_id'] ? lang('Edit Peer Server') : lang('Add Peer Server'));
			$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.edit&server_id=' . $GLOBALS['HTTP_GET_VARS']['server_id']));
			$GLOBALS['phpgw']->template->set_var('deleteurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.delete&server_id=' . $GLOBALS['HTTP_GET_VARS']['server_id']));
			$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.list_servers'));

			$GLOBALS['phpgw']->template->set_var('lang_name',lang('Server name'));
			$GLOBALS['phpgw']->template->set_var('lang_url',lang('Server URL'));
			$GLOBALS['phpgw']->template->set_var('lang_trust',lang('Trust Level'));
			$GLOBALS['phpgw']->template->set_var('lang_relationship',lang('Trust Relationship'));
			$GLOBALS['phpgw']->template->set_var('lang_username',lang('Server Username'));
			$GLOBALS['phpgw']->template->set_var('lang_password',lang('Server Password'));
			$GLOBALS['phpgw']->template->set_var('lang_mode',lang('Server Type(mode)'));
			$GLOBALS['phpgw']->template->set_var('lang_security',lang('Security'));
			$GLOBALS['phpgw']->template->set_var('lang_admin_name',lang('Admin Name'));
			$GLOBALS['phpgw']->template->set_var('lang_admin_email',lang('Admin Email'));
			$GLOBALS['phpgw']->template->set_var('lang_edit',lang('Edit'));
			$GLOBALS['phpgw']->template->set_var('lang_default',lang('Default'));
			$GLOBALS['phpgw']->template->set_var('lang_reset',lang('Clear Form'));
			$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));
			$GLOBALS['phpgw']->template->set_var('lang_delete',lang('Delete'));

			$GLOBALS['phpgw']->template->set_var('server_name',$server['server_name']);
			$GLOBALS['phpgw']->template->set_var('server_url',$server['server_url']);
			$GLOBALS['phpgw']->template->set_var('server_username',$server['username']);
			$GLOBALS['phpgw']->template->set_var('server_mode',$this->formatted_list('server_mode',$is->server_modes,$server['server_mode']));
			$GLOBALS['phpgw']->template->set_var('server_security',$this->formatted_list('server_security',$is->security_types,$server['server_security']));
			$GLOBALS['phpgw']->template->set_var('ssl_note', function_exists('curl_init') ? '&nbsp;' : lang('Note: SSL available only if PHP is compiled with curl support'));
			$GLOBALS['phpgw']->template->set_var('pass_note',lang('(Stored password will not be shown here)'));
			$GLOBALS['phpgw']->template->set_var('trust_level',$this->formatted_list('trust_level',$is->trust_levels,$server['trust_level']));
			$GLOBALS['phpgw']->template->set_var('trust_relationship',$this->formatted_list('trust_rel',$is->trust_relationships,$server['trust_rel'],True));
			$GLOBALS['phpgw']->template->set_var('admin_name',$GLOBALS['phpgw']->strip_html($server['admin_name']));
			$GLOBALS['phpgw']->template->set_var('admin_email',$GLOBALS['phpgw']->strip_html($server['admin_email']));
			$GLOBALS['phpgw']->template->set_var('server_id',$GLOBALS['HTTP_GET_VARS']['server_id']);

			$GLOBALS['phpgw']->template->set_var('edithandle','');
			$GLOBALS['phpgw']->template->set_var('addhandle','');

			$GLOBALS['phpgw']->template->pparse('out','form');
			$GLOBALS['phpgw']->template->pparse('edithandle','edit');
		}

		function delete()
		{
			$server_id = $GLOBALS['HTTP_POST_VARS']['server_id'] ? $GLOBALS['HTTP_POST_VARS']['server_id'] : $GLOBALS['HTTP_GET_VARS']['server_id'];
			if ($GLOBALS['HTTP_POST_VARS']['confirm'])
			{
				$this->bo->delete($server_id);
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.list_servers'));
			}
			else
			{
				$GLOBALS['phpgw']->common->phpgw_header();

				$GLOBALS['phpgw']->template->set_file(array('server_delete' => 'delete_common.tpl'));

				$nolink = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.list_servers');

				$yeslink = '<form method="POST" name="yesbutton" action="' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiserver.delete') . '">'
					. '<input type="hidden" name="server_id" value="' . $server_id . '">'
					. '<input type="hidden" name="confirm" value="True">'
					. '<input type="submit" name="yesbutton" value=Yes>'
					. '</form><script>document.yesbutton.yesbutton.focus()</script>';

				$GLOBALS['phpgw']->template->set_var('messages',lang('Are you sure you want to delete this server?'));
				$GLOBALS['phpgw']->template->set_var('no','<a href="' . $nolink . '">' . lang('No') . '</a>');
				$GLOBALS['phpgw']->template->set_var('yes',$yeslink);
				$GLOBALS['phpgw']->template->pparse('out','server_delete');
			}
		}
	}
?>
