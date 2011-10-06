<?php
	/**************************************************************************\
	* eGroupWare - account administration                                      *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	class uiaccounts
	{
		//(regis) maybe some of them should be deleted?
		var $public_functions = array(
			'list_groups'		=> True,
			'list_users'		=> True,
			'add_group'			=> True,
			'add_user'			=> True,
			'delete_group'		=> True,
			'delete_user'		=> True,
			'edit_user'			=> True,
			'edit_user_hook'	=> True,
			'edit_group'		=> True,
			'view_user'			=> True,
			'edit_group_hook' => True,
			'edit_view_user_hook' => True,
			'group_manager'		=> True,
			'set_group_managers' => True
		);

		var $bo;
		var $nextmatchs;
		var $apps_with_acl = array(
			'todo'        => True,
			'calendar'    => True,
			'projects'    => True,
			'infolog'     => True,
			'filemanager' => array(
				'menuaction' => 'filemanager.filemanager_ui.file',
				'path' => '/home/$account_lid',
				'tabs' => 'eacl',
				'popup' => '495x400',
			),
			'tts'         => True,
			'bookmarks'   => True,
			'img'         => True,
			'phpbrain'    => True,
			'projectmanager' => True,
			'timesheet'   => True
		);

		function uiaccounts()
		{
			$this->bo =& CreateObject('admin.boaccounts');
			$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');
			@set_time_limit(300);
			/* Moved from bo class */
			if (get_magic_quotes_gpc())		// deal with magic_quotes_gpc On
			{
				$_POST = $this->array_stripslashes($_POST);
			}
			foreach($GLOBALS['egw']->hooks->process('group_acl','',true) as $app => $data)
			{
				if ($data) $this->apps_with_acl[$app] = $data;
			}
		}

		function row_action($action,$type,$account_id)
		{
			return '<a href="'.$GLOBALS['egw']->link('/index.php',Array(
				'menuaction' => 'admin.uiaccounts.'.$action.'_'.$type,
				'account_id' => $account_id
			)).'"> '.lang($action).' </a>';
		}

		function list_groups()
		{
			$query_types = array(
				'all' => 'all fields',
				'lid' => 'LoginID',
				'start' => 'start with',
				'exact' => 'exact'
			);

			if ($GLOBALS['egw']->acl->check('group_access',1,'admin'))
			{
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/admin/index.php'));
			}

			$GLOBALS['cd'] = ($_GET['cd']?$_GET['cd']:0);

			if(isset($_REQUEST['query']))
			{
				// limit query to limit characters
				//if(preg_match('/^[a-z_0-9]+$/i',$_REQUEST['query']))
					$GLOBALS['query'] = $_REQUEST['query'];
			}

			if(isset($_POST['start']))
			{
				$start = (int)$_POST['start'];
			}
			else
			{
				$start = 0;
			}
			switch($_REQUEST['order'])
			{
				case 'account_lid':
					$order = $_REQUEST['order'];
					break;
				default:
					$order = 'account_lid';
					break;
			}

			switch($_REQUEST['sort'])
			{
				case 'ASC':
				case 'DESC':
					$sort = $_REQUEST['sort'];
					break;
				default:
					$sort = 'ASC';
					break;
			}

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['admin']['title'].' - '.
				lang('User groups');
			$GLOBALS['egw']->common->egw_header();

			$p =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$p->set_file(
				array(
					'groups'   => 'groups.tpl'
				)
			);
			$p->set_block('groups','list','list');
			$p->set_block('groups','row','row');
			$p->set_block('groups','row_empty','row_empty');
			$p->set_block('list','letter_search','letter_search_cells');

			$search_param = array(
				'type' => 'groups',
				'start' => $start,
				'sort' => $sort,
				'order' => $order,
				'query_type' => $_REQUEST['query_type']
			);
			//_debug_array($search_param);
			if (!$GLOBALS['egw']->acl->check('account_access',2,'admin'))
			{
				$search_param['query'] = $GLOBALS['query'];
			}
			$account_info = $GLOBALS['egw']->accounts->search($search_param);
			$total = $GLOBALS['egw']->accounts->total;

			$link_data = array(
				'menuaction' => 'admin.uiaccounts.list_groups',
				//'group_id'   => $_REQUEST['group_id'],
				'query_type' => $_REQUEST['query_type'],
				'query'      => $GLOBALS['query'],
			);

			$var = Array(
				'left_next_matchs'  => $this->nextmatchs->left('/index.php',$start,$total,$link_data),
				'right_next_matchs' => $this->nextmatchs->right('/index.php',$start,$total,$link_data),
				'lang_groups' => lang('%1 - %2 of %3 user groups',$start+1,$start+count($account_info),$total),
				'sort_name'     => $this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('name'),$link_data),
				'header_edit'   => lang('Edit'),
				'header_delete' => lang('Delete'),
				'lang_search'  => lang('search') // KL 20061128 Text fr den Suchbutton hinzugefeugt
			);
			$p->set_var($var);

			if (!count($account_info) || !$total)
			{
				$p->set_var('message',lang('No matches found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				if (! $GLOBALS['egw']->acl->check('group_access',8,'admin'))
				{
					$can_view = True;
				}

				if (! $GLOBALS['egw']->acl->check('group_access',16,'admin'))
				{
					$can_edit = True;
				}

				if (! $GLOBALS['egw']->acl->check('group_access',32,'admin'))
				{
					$can_delete = True;
				}

				foreach($account_info as $account)
				{
					$var = Array(
						'class'       => $this->nextmatchs->alternate_row_color('', True),
						'group_name'  => (!$account['account_lid']?'&nbsp;':$account['account_lid']),
						'delete_link' => $this->row_action('delete','group',$account['account_id'])
					);
					$p->set_var($var);

					if ($can_edit)
					{
						$p->set_var('edit_link',$this->row_action('edit','group',$account['account_id']));
					}
					else
					{
						$p->set_var('edit_link','&nbsp;');
					}

					if ($can_delete)
					{
						$p->set_var('delete_link',$this->row_action('delete','group',$account['account_id']));
					}
					else
					{
						$p->set_var('delete_link','&nbsp;');
					}

					$p->fp('rows','row',True);
				}
			}

			$link_data += array(
				'order'      => $order,
				'sort'       => $sort
			);
			$p->set_var(array(
				'query' => html::htmlspecialchars($GLOBALS['query']),
				'query_type' => is_array($query_types) ? html::select('query_type',$_REQUEST['query_type'],$query_types) : '',
				//'lang_group' => lang('group'),
				//'group' => $uiaccountsel->selection('group_id','admin_uiaccount_listusers_group_id',$_REQUEST['group_id'],'groups',0,False,'','this.form.submit();',lang('all')),
				'accounts_url' => $GLOBALS['egw']->link('/index.php',$link_data),
			));
			$letters = lang('alphabet');
			$letters = explode(',',substr($letters,-1) != '*' ? $letters : 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z');
			$link_data['query_type'] = 'start';
			foreach($letters as $letter)
			{
				$link_data['query'] = $letter;
				$p->set_var(array(
					'letter' => $letter,
					'link'   => $GLOBALS['egw']->link('/index.php',$link_data),
					'class'  => $GLOBALS['query'] == $letter && $_REQUEST['query_type'] == 'start' ? 'letter_box_active' : 'letter_box',
				));
				$p->fp('letter_search_cells','letter_search',True);
			}
			unset($link_data['query']);
			unset($link_data['query_type']);
			$p->set_var(array(
				'letter' => lang('all'),
				'link'   => $GLOBALS['egw']->link('/index.php',$link_data),
				'class'  => $_REQUEST['query_type'] != 'start' || !in_array($GLOBALS['query'],$letters) ? 'letter_box_active' : 'letter_box',
			));
			$p->fp('letter_search_cells','letter_search',True);

			$var = Array(
				'new_action'    => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.add_group'),
				'search_action' => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups')
			);
			$p->set_var($var);

			if (! $GLOBALS['egw']->acl->check('group_access',4,'admin'))
			{
				$p->set_var('input_add','<input type="submit" value="' . lang('Add') . '">');
			}

			if (! $GLOBALS['egw']->acl->check('group_access',2,'admin'))
			{
				$p->set_var('input_search',lang('Search') . '&nbsp;<input name="query" value="'.htmlspecialchars(stripslashes($GLOBALS['query'])).'">');
			}

			$p->pfp('out','list');
		}

		function list_users($param_cd='')
		{
			if ($GLOBALS['egw']->acl->check('account_access',1,'admin'))
			{
				$GLOBALS['egw']->redirect_link('/admin/index.php');
			}
			if($param_cd)
			{
				$cd = $param_cd;
			}
			if(isset($_REQUEST['query']))
			{
				$GLOBALS['query'] = $_REQUEST['query'];
			}
			if(isset($_REQUEST['start']))
			{
				$start = (int)$_REQUEST['start'];
			}
			else
			{
				$start = 0;
			}

			switch($_REQUEST['order'])
			{
				case 'account_lastname':
				case 'account_firstname':
				case 'account_lid':
				case 'account_email':
					$order = $_REQUEST['order'];
					break;
				default:
					$order = 'account_lid';
					break;
			}

			switch($_REQUEST['sort'])
			{
				case 'ASC':
				case 'DESC':
					$sort = $_REQUEST['sort'];
					break;
				default:
					$sort = 'ASC';
					break;
			}

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['admin']['title'].' - '.
				lang('User accounts');
			$GLOBALS['egw']->common->egw_header();

			$p =& CreateObject('phpgwapi.Template',EGW_APP_TPL);

			$p->set_file(
				Array(
					'list' => 'accounts.tpl'
				)
			);
			$p->set_block('list','row','rows');
			$p->set_block('list','row_empty','no_rows');
			$p->set_block('list','letter_search','letter_search_cells');

			$search_param = array(
				'type' => (int)$_REQUEST['group_id'] ? $_REQUEST['group_id'] : 'accounts',
				'start' => $start,
				'sort' => $sort,
				'order' => $order,
				'query_type' => $_REQUEST['query_type'],
			);
			if (!$GLOBALS['egw']->acl->check('account_access',2,'admin'))
			{
				$search_param['query'] = $GLOBALS['query'];
			}
			$account_info = $GLOBALS['egw']->accounts->search($search_param);
			$total = $GLOBALS['egw']->accounts->total;

			$link_data = array(
				'menuaction' => 'admin.uiaccounts.list_users',
				'group_id'   => $_REQUEST['group_id'],
				'query_type' => $_REQUEST['query_type'],
				'query'      => $GLOBALS['query'],
			);
			$uiaccountsel =& CreateObject('phpgwapi.uiaccountsel');
			$p->set_var(array(
				'left_next_matchs'   => $this->nextmatchs->left('/index.php',$start,$total,$link_data),
				'lang_showing' => ($_REQUEST['group_id'] ? $GLOBALS['egw']->common->grab_owner_name($_REQUEST['group_id']).': ' : '').
					($GLOBALS['query'] ? lang("Search %1 '%2'",lang($uiaccountsel->query_types[$_REQUEST['query_type']]),
					html::htmlspecialchars($GLOBALS['query'])).': ' : '')
					.$this->nextmatchs->show_hits($total,$start),
				'right_next_matchs'  => $this->nextmatchs->right('/index.php',$start,$total,$link_data),
				'lang_loginid'       => $this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('LoginID'),$link_data),
				'lang_lastname'      => $this->nextmatchs->show_sort_order($sort,'account_lastname',$order,'/index.php',lang('last name'),$link_data),
				'lang_firstname'     => $this->nextmatchs->show_sort_order($sort,'account_firstname',$order,'/index.php',lang('first name'),$link_data),
				'lang_email'         => $this->nextmatchs->show_sort_order($sort,'account_email',$order,'/index.php',lang('email'),$link_data),
				'lang_account_active'   => lang('Account active')."<br>".lang('Created')."<br>".lang('Modified'),
				'lang_edit'    => lang('edit'),
				'lang_delete'  => lang('delete'),
				'lang_view'    => lang('view'),
				'lang_search'  => lang('search')
			));
			$link_data += array(
				'order'      => $order,
				'sort'       => $sort,
			);
			$p->set_var(array(
				'query' => html::htmlspecialchars($GLOBALS['query']),
				'query_type' => is_array($uiaccountsel->query_types) ? html::select('query_type',$_REQUEST['query_type'],$uiaccountsel->query_types) : '',
				'lang_group' => lang('group'),
				'group' => $uiaccountsel->selection('group_id','admin_uiaccount_listusers_group_id',$_REQUEST['group_id'],'groups',0,False,'','this.form.submit();',lang('all')),
				'accounts_url' => $GLOBALS['egw']->link('/index.php',$link_data),
			));
			$letters = lang('alphabet');
			$letters = explode(',',substr($letters,-1) != '*' ? $letters : 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z');
			$link_data['query_type'] = 'start';
			foreach($letters as $letter)
			{
				$link_data['query'] = $letter;
				$p->set_var(array(
					'letter' => $letter,
					'link'   => $GLOBALS['egw']->link('/index.php',$link_data),
					'class'  => $GLOBALS['query'] == $letter && $_REQUEST['query_type'] == 'start' ? 'letter_box_active' : 'letter_box',
				));
				$p->fp('letter_search_cells','letter_search',True);
			}
			unset($link_data['query']);
			unset($link_data['query_type']);
			$p->set_var(array(
				'letter' => lang('all'),
				'link'   => $GLOBALS['egw']->link('/index.php',$link_data),
				'class'  => $_REQUEST['query_type'] != 'start' || !in_array($GLOBALS['query'],$letters) ? 'letter_box_active' : 'letter_box',
			));
			$p->fp('letter_search_cells','letter_search',True);

			if (! $GLOBALS['egw']->acl->check('account_access',4,'admin'))
			{
				$p->set_var('new_action',$GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.add_user'));
				$p->set_var('input_add','<input type="submit" value="' . lang('Add') . '">');
			}

			if (!count($account_info) || !$total)
			{
				$p->set_var('message',lang('No matches found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				if (! $GLOBALS['egw']->acl->check('account_access',8,'admin'))
				{
					$can_view = True;
				}

				if (! $GLOBALS['egw']->acl->check('account_access',16,'admin'))
				{
					$can_edit = True;
				}

				if (! $GLOBALS['egw']->acl->check('account_access',32,'admin'))
				{
					$can_delete = True;
				}

				foreach($account_info as $account)
				{
					$p->set_var('class',$this->nextmatchs->alternate_row_color('',True));
					if ($account['account_status']=='A')
					{
						$account['account_status'] = lang('Enabled');
					}
					else
					{
						$account['account_status'] = '<font color="red">' . lang('Disabled') . '</font>';
					}
					if (isset($account['account_created']))
						$account['account_status'].= '<br>'.$GLOBALS['egw']->common->show_date($account['account_created'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']);
					if (isset($account['account_modified']))
						$account['account_status'].= '<br>'.$GLOBALS['egw']->common->show_date($account['account_modified'],$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']);


					$p->set_var($account);

					if ($can_edit)
					{
						$p->set_var('row_edit',$this->row_action('edit','user',$account['account_id']));
					}
					else
					{
						$p->set_var('row_edit','&nbsp;');
					}

					if ($can_delete)
					{
						$p->set_var('row_delete',($GLOBALS['egw_info']['user']['userid'] != $account['account_lid']?$this->row_action('delete','user',$account['account_id']):'&nbsp'));
					}
					else
					{
						$p->set_var('row_delete','&nbsp;');
					}

					if ($can_view)
					{
						$p->set_var('row_view',$this->row_action('view','user',$account['account_id']));
					}
					else
					{
						$p->set_var('row_view','&nbsp;');
					}
					$p->parse('rows','row',True);
				}
			}		// End else
			$p->pfp('out','list');
		}

		function add_group()
		{
			if ($GLOBALS['egw']->acl->check('group_access',4,'admin'))
			{
				$this->list_groups();
				return False;
			}

			if($_POST['edit'])
			{
				$group_permissions = ($_POST['account_apps']?$_POST['account_apps']:Array());
				$account_apps = Array();
				foreach($group_permissions as $key => $value)
				{
					if($value)
					{
						$account_apps[$key] = True;
					}
				}
				@reset($account_apps);

				$group_info = Array(
					'account_id'   => ($_POST['account_id']?(int)$_POST['account_id']:0),
					'account_name' => ($_POST['account_name']?$_POST['account_name']:''),
					'account_user' => $_POST['account_user'],
					'account_apps' => $account_apps,
					'account_email' => $_POST['account_email']
				);
				$errors = $this->bo->add_group($group_info);
				if(is_array($errors))
				{
					$this->create_edit_group($group_info,$errors);
					$GLOBALS['egw']->common->egw_exit();
				}
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			}
			else
			{
				$group_info = Array(
					'account_id'   => $_GET['account_id'],
					'account_name' => '',
					'account_user' => Array(),
					'account_apps' => Array()
				);
				$this->create_edit_group($group_info);
			}
		}

		function add_user()
		{
			if ($GLOBALS['egw']->acl->check('account_access',4,'admin'))
			{
				$this->list_users();
				return;
			}

			if($_POST['submit'])
			{
				if(!($email = $_POST['account_email']))
				{
					$email = $GLOBALS['egw']->common->email_address($_POST['account_firstname'],$_POST['account_lastname'],$_POST['account_lid']);
				}
				$userData = array(
					'account_type'          => 'u',
					'account_lid'           => $_POST['account_lid'],
					'account_firstname'     => $_POST['account_firstname'],
					'account_lastname'      => $_POST['account_lastname'],
					'account_passwd'        => $_POST['account_passwd'],
					'status'                => ($_POST['account_status'] ? 'A' : ''),
					'account_status'        => ($_POST['account_status'] ? 'A' : ''),
					'old_loginid'           => ($_GET['old_loginid']?rawurldecode($_GET['old_loginid']):''),
					'account_id'            => ($_GET['account_id']?$_GET['account_id']:0),
					'account_primary_group' => $_POST['account_primary_group'],
					'account_passwd_2'      => $_POST['account_passwd_2'],
					'account_groups'        => $_POST['account_groups'],
					'anonymous'             => $_POST['anonymous'],
					'changepassword'        => $_POST['changepassword'],
					'mustchangepassword'        => $_POST['mustchangepassword'],
					'account_permissions'   => $_POST['account_permissions'],
					'homedirectory'         => $_POST['homedirectory'],
					'loginshell'            => $_POST['loginshell'],
					'account_expires_never' => $_POST['never_expires'],
					'account_email'         => $email
					/* 'file_space' => $_POST['account_file_space_number'] . "-" . $_POST['account_file_space_type'] */
				);
				if ($userData['mustchangpassword']) $userData['account_lastpwd_change']=0;
				/* when does the account expire */
				if ($_POST['expires'] !== '' && !$_POST['never_expires'])
				{
					$jscal =& CreateObject('phpgwapi.jscalendar',False);
					$userData += $jscal->input2date($_POST['expires'],False,'account_expires_day','account_expires_month','account_expires_year');
				}

				$errors = $this->bo->add_user($userData);
				if(is_array($errors))
				{
					$this->create_edit_user(0,$userData,$errors);
					$GLOBALS['egw']->common->egw_exit();
				}
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_users'));
			}
			else
			{
				$this->create_edit_user(0);
			}
		}

		function delete_group()
		{
			if ($_POST['no'] || $_POST['yes'] || !@isset($_GET['account_id']) || !@$_GET['account_id'] || $GLOBALS['egw']->acl->check('group_access',32,'admin'))
			{
				if ($_POST['yes'])
				{
					$this->bo->delete_group($_POST['account_id']);
				}
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			}

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);

			$GLOBALS['egw']->common->egw_header();

			$p =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$p->set_file(
				Array(
					'body' => 'delete_common.tpl',
					'message_row' => 'message_row.tpl',
					'form_button' => 'form_button_script.tpl'
				)
			);

			$p->set_var('message_display',lang('Are you sure you want to delete this group ?'));
			$p->parse('messages','message_row');

			if(($old_group_list = $GLOBALS['egw']->accounts->memberships((int)$_GET['account_id'],true)))
			{
				$group_name = $GLOBALS['egw']->accounts->id2name($_GET['account_id']);

				$p->set_var('message_display','<br>');
				$p->parse('messages','message_row',True);

				$user_list = '';
				while (list(,$id) = each($old_group_list))
				{
					$user_list .= '<a href="' . $GLOBALS['egw']->link('/index.php',
						Array(
							'menuaction' => 'admin.uiaccounts.edit_user',
							'account_id' => $id
						)
					) . '">' . $GLOBALS['egw']->common->grab_owner_name($id) . '</a><br>';
				}
				$p->set_var('message_display',$user_list);
				$p->parse('messages','message_row',True);

				$p->set_var('message_display',lang("Sorry, the above users are still a member of the group %1",$group_name)
					. '.<br>' . lang('They must be removed before you can continue'). '.<br>' . lang('Remove all users from this group').'?');
				$p->parse('messages','message_row',True);
			}

			$var = Array(
				'form_action' => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.delete_group'),
				'hidden_vars' => '<input type="hidden" name="account_id" value="'.$_GET['account_id'].'">',
				'yes'         => lang('Yes'),
				'no'          => lang('No')
			);
			$p->set_var($var);
/*
			$p->parse('yes','form_button');

			$var = Array(
				'submit_button' => lang('Submit'),
				'action_url_button'     => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'action_text_button'    => ' '.lang('No'),
				'action_confirm_button' => '',
				'action_extra_field'    => ''
			);
			$p->set_var($var);
			$p->parse('no','form_button');
*/
			$p->pparse('phpgw_body','body');
		}

		function delete_user()
		{
			if ($GLOBALS['egw']->acl->check('account_access',32,'admin') || $GLOBALS['egw_info']['user']['account_id'] == $_GET['account_id'] ||
				$_POST['cancel'])
			{
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_users'));
			}
			if($_POST['delete_account'])
			{
				$this->bo->delete_user($_POST['account_id'],$_POST['new_owner']);
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_users'));
			}

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw']->common->egw_header();

			$t =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$t->set_file(
				Array(
					'form' => 'delete_account.tpl'
				)
			);
			$var = Array(
				'form_action' => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.delete_user'),
				'account_id'  => $_GET['account_id']
			);

			// the account can have special chars/white spaces, if it is a ldap dn
			$account_id = rawurlencode($_GET['account_id']);

			$var['lang_new_owner'] = lang('Who would you like to transfer ALL records owned by the deleted user to?');
			$accountsel = new uiaccountsel();
			$var['new_owner_select'] = $accountsel->selection('new_owner','new_owner',array(''),'accounts',0,$account_id,'size="15"','',lang('Delete all records'));
			$var['cancel'] = lang('cancel');
			$var['delete'] = lang('delete');
			$t->set_var($var);
			$t->pparse('out','form');
		}

		 // (regis) why only for users, it works with groups as well so I add it
		 // I use it on the workflow app to add monitoring rights for some users
		 // and we could have history of connexions for members groups.
		function edit_group_hook() // (regis) why only for users, it works with groups as well so I add it
		{
			if ($_GET['account_id'] && 	// can't set it on add
					!$GLOBALS['egw']->acl->check('account_access',64,'admin'))	// no rights to set ACL-rights
			{
				$GLOBALS['menuData'][] = array(
					'description' => 'ACL Rights',
					'url'         => '/index.php',
					'extradata'   => 'menuaction=admin.uiaclmanager.list_apps'
				);
			}
		}

		function edit_group($cd='',$account_id='')
		{
			if ($GLOBALS['egw']->acl->check('group_access',16,'admin'))
			{
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			}

			if($_POST['edit'])
			{
				$group_permissions = ($_POST['account_apps']?$_POST['account_apps']:Array());
				$account_apps = Array();
				foreach($group_permissions as $key => $value)
				{
					if($value)
					{
						$account_apps[$key] = True;
					}
				}
				@reset($account_apps);

				$group_info = Array(
					'account_id'   => ($_POST['account_id']?(int)$_POST['account_id']:0),
					'account_name' => ($_POST['account_name']?$_POST['account_name']:''),
					'account_user' => $_POST['account_user'],
					'account_apps' => $account_apps,
					'account_email' => $_POST['account_email']
				);
				$errors = $this->bo->edit_group($group_info);
				if(is_array($errors))
				{
					$this->create_edit_group($group_info,$errors);
					$GLOBALS['egw']->common->egw_exit();
				}
				$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			}
			else
			{
				$cdid = $cd;
				settype($cd,'integer');
				$cd = ($_GET['cd']?$_GET['cd']:(int)$cdid);

				$accountid = $account_id;
				settype($account_id,'integer');
				$account_id = ($_GET['account_id'] ? $_GET['account_id'] : (int)$accountid);

				// todo
				// not needed if i use the same file for new groups too
				if (! $account_id)
				{
					$this->list_groups();
				}
				else
				{
					$group_info = Array(
						'account_id'   => (int)$_GET['account_id'],
						'account_name' => $GLOBALS['egw']->accounts->id2name($_GET['account_id']),
						'account_user' => $GLOBALS['egw']->accounts->members($_GET['account_id']),
						'account_apps' => $this->bo->load_group_apps($_GET['account_id'])
					);

					$this->create_edit_group($group_info);
				}
			}
		}

		function edit_view_user_hook()
		{
			if (!$GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))	// no rights to view
			{
				$GLOBALS['menuData'][] = array(
					'description' => 'Login History',
					'url'         => '/index.php',
					'extradata'   => 'menuaction=admin.admin_accesslog.index'
				);
			}
			// not sure if this realy belongs here, or only in edit_user
			if ($_GET['account_id'] && 	// can't set it on add
				!$GLOBALS['egw']->acl->check('account_access',64,'admin'))	// no rights to set ACL-rights
			{
				$GLOBALS['menuData'][] = array(
					'description' => 'ACL Rights',
					'url'         => '/index.php',
					'extradata'   => 'menuaction=admin.uiaclmanager.list_apps'
				);
			}

			// NDEE210804
			// added for different way of handling ldap entries inside account manager
			// we show this only, if accounts are stored in ldap
/* just doublicated EMailAdmin functionality
			if ($GLOBALS['egw_info']['server']['account_repository'] == "ldap")
			{
				$GLOBALS['menuData'][] = array(
				'description'   => 'LDAP-MGR',
				'url'           => '/index.php',
				'extradata'     => 'menuaction=admin.uildap_mgr.editUserData'
				);
			}
*/
			//NDEE
		}

		function edit_user($cd='',$account_id='')
		{
			if($GLOBALS['egw']->acl->check('account_access',16,'admin'))
			{
				$this->list_users();
				return False;
			}

			if($_POST['submit'])
			{
				if(!($email = $_POST['account_email']))
				{
					$email = $GLOBALS['egw']->common->email_address($_POST['account_firstname'],$_POST['account_lastname'],$_POST['account_lid']);
				}
				$userData = array(
					'account_lid'           => $_POST['account_lid'],
					'account_firstname'     => $_POST['account_firstname'],
					'account_lastname'      => $_POST['account_lastname'],
					'account_passwd'        => $_POST['account_passwd'],
					'account_status'        => ($_POST['account_status'] ? 'A' : ''),
					'old_loginid'           => ($_GET['old_loginid']?rawurldecode($_GET['old_loginid']):''),
					'account_id'            => ($_GET['account_id']?$_GET['account_id']:0),
					'account_passwd_2'      => $_POST['account_passwd_2'],
					'account_groups'        => $_POST['account_groups'],
					'account_primary_group'	=> $_POST['account_primary_group'],
					'anonymous'             => $_POST['anonymous'],
					'changepassword'        => $_POST['changepassword'],
					'mustchangepassword'        => $_POST['mustchangepassword'],
					'account_permissions'   => $_POST['account_permissions'],
					'homedirectory'         => $_POST['homedirectory'],
					'loginshell'            => $_POST['loginshell'],
					'account_expires_never' => $_POST['never_expires'],
					'account_email'         => $email,
					/* 'file_space' => $_POST['account_file_space_number'] . "-" . $_POST['account_file_space_type'] */
				);
				if ($userData['mustchangepassword'])
				{
					$userData['account_lastpwd_change']=0;
				}
				else
				{
					$accountid = $account_id;
					settype($account_id,'integer');
					$account_id = (int)($_GET['account_id'] ? $_GET['account_id'] : $accountid);

					//echo '<br>#'.$account_id.'#<br>';
					$prevVal =  $GLOBALS['egw']->accounts->id2name($account_id,'account_lastpwd_change');
					//echo '<br>#'.$prevVal.'#<br>'; // previous Value was forced password change by admin
					if (isset($prevVal) && $prevVal==0) $userData['account_lastpwd_change']=egw_time::to('now','ts');
				}
				if($userData['account_primary_group'] && (!isset($userData['account_groups']) || !in_array($userData['account_primary_group'],$userData['account_groups'])))
				{
					$userData['account_groups'][] = (int)$userData['account_primary_group'];
				}
				if($_POST['expires'] !== '' && !$_POST['never_expires'])
				{
					$jscal =& CreateObject('phpgwapi.jscalendar',False);
					$userData += $jscal->input2date($_POST['expires'],False,'account_expires_day','account_expires_month','account_expires_year');
				}
				$errors = $this->bo->edit_user($userData);

				if(!@is_array($errors))
				{
					// check if would create a menu
					// if we do, we can't return to the users list, because
					// there are also some other plugins
					if(!ExecMethod('admin.uimenuclass.createHTMLCode','edit_user'))
					{
						$GLOBALS['egw']->redirect_link('/index.php',array(	// without redirect changes happen only in the next page-view!
							'menuaction' => 'admin.uiaccounts.list_users'
						));
					}
					else
					{
						if($userData['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
						{
							$GLOBALS['egw']->redirect_link('/index.php',array(	// without redirect changes happen only in the next page-view!
								'menuaction' => 'admin.uiaccounts.edit_user',
								'account_id' => $_GET['account_id']
							));
						}
						$this->create_edit_user($userData['account_id']);
					}
				}
				else
				{
					$this->create_edit_user($userData['account_id'],$userData,$errors);
				}
			}
			else
			{
				$cdid = $cd;
				settype($cd,'integer');
				$cd = ($_GET['cd']?$_GET['cd']:(int)$cdid);

				$accountid = $account_id;
				settype($account_id,'integer');
				$account_id = (int)($_GET['account_id'] ? $_GET['account_id'] : $accountid);

				// todo
				// not needed if i use the same file for new users too
				if(!$account_id)
				{
					$this->list_users();
					return False;
				}
				else
				{
					$this->create_edit_user($account_id);
				}
			}
		}

		function view_user()
		{
			if ($GLOBALS['egw']->acl->check('account_access',8,'admin') || ! $_GET['account_id'])
			{
				$this->list_users();
				return False;
			}
			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw']->common->egw_header();

			$t =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$t->set_unknowns('remove');
			$t->set_file(
				Array(
					'account' => 'account_form.tpl'
				)
			);
			$t->set_block('account','form','form');
			$t->set_block('account','form_logininfo');
			$t->set_block('account','link_row');

			$var = Array(
				'tr_color1'    => $GLOBALS['egw_info']['theme']['row_on'],
				'tr_color2'    => $GLOBALS['egw_info']['theme']['row_off'],
				'lang_action'  => lang('View user account'),
				'lang_loginid' => lang('LoginID'),
				'lang_account_active'   => lang('Account active'),
				'lang_lastname'      => lang('Last Name'),
				'lang_groups'        => lang('Groups'),
				'lang_anonymous'     => lang('Anonymous user (not shown in list sessions)'),
				'lang_changepassword'=> lang('Can change password'),
				'lang_mustchangepassword'=> lang('Must change password upon next login'),
				'lang_firstname'     => lang('First Name'),
				'lang_lastlogin'     => lang('Last login'),
				'lang_lastloginfrom' => lang('Last login from'),
				'lang_expires' => lang('Expires'),
				'lang_app' => lang('application'),
				'lang_acl' => lang('enabled'),
			);

			$t->parse('password_fields','form_logininfo',True);

			$account =& CreateObject('phpgwapi.accounts',(int)$_GET['account_id'],'u');
			$userData = $account->read_repository();

			$var['account_lid']       = $userData['account_lid'];
			$var['account_firstname'] = $userData['firstname'];
			$var['account_lastname']  = $userData['lastname'];

			$acl =& CreateObject('phpgwapi.acl',(int)$_GET['account_id']);
			$var['anonymous']         = $acl->check('anonymous',1,'phpgwapi') ? '&nbsp;&nbsp;X' : '&nbsp;';
			$var['changepassword']    = !$acl->check('nopasswordchange',1,'preferences') ? '&nbsp;&nbsp;X' : '&nbsp;';
			if (!isset($auth)) $auth =& CreateObject('phpgwapi.auth');
			$accLPWDC = $auth->getLastPwdChange($userData['account_lid']);
			if ($accLPWC !== false) $userData['account_lastpwd_change'] = $accLPWDC;
			$var['mustchangepassword']= (isset($userData['account_lastpwd_change']) && ((is_string($userData['account_lastpwd_change']) && $userData['account_lastpwd_change']==="0")||(is_int($userData['account_lastpwd_change']) && $userData['account_lastpwd_change']===0)) ? '&nbsp;&nbsp;X' : '&nbsp;');
			unset($acl);

			if ($userData['status'])
			{
				$var['account_status'] = lang('Enabled');
			}
			else
			{
				$var['account_status'] = '<b>' . lang('Disabled') . '</b>';
			}
			if (isset($userData['account_created'])) $var['account_status'].= '<br>'.lang('Created').': '.$GLOBALS['egw']->common->show_date($userData['account_created']);
			if (isset($userData['account_modified'])) $var['account_status'].= '<br>'.lang('Modified').': '.$GLOBALS['egw']->common->show_date($userData['account_modified']);


			// Last login time
			if ($userData['lastlogin'])
			{
				$var['account_lastlogin'] = $GLOBALS['egw']->common->show_date($userData['lastlogin']);
			}
			else
			{
				$var['account_lastlogin'] = lang('Never');
			}

			// Last login IP
			if ($userData['lastloginfrom'])
			{
				$var['account_lastloginfrom'] = $userData['lastloginfrom'];
			}
			else
			{
				$var['account_lastloginfrom'] = lang('Never');
			}

			// Account expires
			if ($userData['expires'] != -1)
			{
				$var['input_expires'] = $GLOBALS['egw']->common->show_date($userData['expires']);
			}
			else
			{
				$var['input_expires'] = lang('Never');
			}

			// Find out which groups they are members of
			$usergroups = $account->membership((int)$_GET['account_id']);
			if(!@is_array($usergroups))
			{
				$var['groups_select'] = lang('None');
			}
			else
			{
				while (list(,$group) = each($usergroups))
				{
					$group_names[] = $group['account_name'];
				}
				$var['groups_select'] = implode(', ',$group_names);
			}

			$account_lastlogin      = $userData['account_lastlogin'];
			$account_lastloginfrom  = $userData['account_lastloginfrom'];
			$account_status         = $userData['account_status'];

			// create list of available app
			$i = 0;

			$availableApps = $GLOBALS['egw_info']['apps'];
			foreach($availableApps as $app => $data)
			{
				if (!$data['enabled'] || !$data['status'] || $data['status'] == 3)
				{
					unset($availableApps[$app]);	// do NOT show disabled apps, or our API (status = 3)
				}
			}
			uasort($availableApps,create_function('$a,$b','return strcasecmp($a["title"],$b["title"]);'));

			foreach($availableApps as $app => $data)
			{
				$perm_display[] = array(
					'appName' => $app,
					'title'   => $data['title'],
				);
			}

			// create apps output
			$apps =& CreateObject('phpgwapi.applications',(int)$_GET['account_id']);
			$db_perms = $apps->read_account_specific();

			@reset($db_perms);

			for ($i=0;$i<count($perm_display);$i++)
			{
				if ($perm_display[$i]['title'])
				{
					$part1 = sprintf("<td>%s</td><td>%s</td>",$perm_display[$i]['title'],($_userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?'&nbsp;&nbsp;X':'&nbsp'));
				}

				$i++;

				if ($perm_display[$i]['title'])
				{
					$part2 = sprintf("<td>%s</td><td>%s</td>",$perm_display[$i]['title'],($_userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?'&nbsp;&nbsp;X':'&nbsp'));
				}
				else
				{
					$part2 = '<td colspan="2">&nbsp;</td>';
				}

				$appRightsOutput .= sprintf("<tr class=\"%s\">$part1$part2</tr>\n",$this->nextmatchs->alternate_row_color('',true));
			}

			$var['permissions_list'] = $appRightsOutput;

			// create the menu on the left, if needed
//			$menuClass =& CreateObject('admin.uimenuclass');
			// This is now using ExecMethod()
			$var['rows'] = ExecMethod('admin.uimenuclass.createHTMLCode','view_user');
			$t->set_var($var);
			$t->pfp('out','form');
		}

		function group_manager($cd='',$account_id='')
		{
			if ($GLOBALS['egw']->acl->check('group_access',16,'admin'))
			{
				$this->list_groups();
				return False;
			}

			$cdid = $cd;
			settype($cd,'integer');
			$cd = ($_GET['cd']?$_GET['cd']:(int)$cdid);

			$accountid = $account_id;
			settype($account_id,'integer');
			$account_id = (int)($_GET['account_id'] ? $_GET['account_id'] : $accountid);

			// todo
			// not needed if i use the same file for new groups too
			if (! $account_id)
			{
				$this->list_groups();
			}
			else
			{
				$group_info = Array(
					'account_id'   => (int)$_GET['account_id'],
					'account_name' => $GLOBALS['egw']->accounts->id2name($_GET['account_id']),
					'account_user' => $GLOBALS['egw']->accounts->member($_GET['account_id']),
					'account_managers' => $this->bo->load_group_managers($_GET['account_id'])
				);

				$this->edit_group_managers($group_info);
			}
		}

		function create_edit_group($group_info,$_errors='')
		{
			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw']->common->egw_header();

			$p =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$p->set_file(Array('edit' => 'group_form.tpl'));
			$p->set_block('edit','select');
			$p->set_block('edit','popwin');
//fix from Maanus 280105
			$accounts =& CreateObject('phpgwapi.accounts',$group_info['account_id'],'g');

			$p->set_var('accounts',$GLOBALS['egw']->uiaccountsel->selection('account_user[]','admin_uiaccounts_user',$group_info['account_user'],'accounts',min(3+count($group_info['account_user']),10),false,'style="width: 300px;"'));

			$var = Array(
				'form_action'       => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.'.($group_info['account_id']?'edit':'add').'_group'),
				'hidden_vars'       => '<input type="hidden" name="account_id" value="' . $group_info['account_id'] . '">',
				'lang_group_name'   => lang('group name'),
				'group_name_value'  => $group_info['account_name'],
				'lang_include_user' => lang('Select users for inclusion'),
				'error'             => (!$_errors?'':'<center>'.$GLOBALS['egw']->common->error_list($_errors).'</center>'),
				'lang_permissions'  => lang('Permissions this group has')
			);
			$p->set_var($var);

			$group_repository = $accounts->read_repository();
			if (!$group_repository['file_space'])
			{
				$group_repository['file_space'] = $GLOBALS['egw_info']['server']['vfs_default_account_size_number'] . "-" . $GLOBALS['egw_info']['server']['vfs_default_account_size_type'];
			}
	/*
			$file_space_array = explode ('-', $group_repository['file_space']);
			$account_file_space_types = array ('gb', 'mb', 'kb', 'b');
			while (list ($num, $type) = each ($account_file_space_types))
			{
				$account_file_space_select .= '<option value="'.$type.'"'.($type==$file_space_array[1]?' selected':'').'>'.strtoupper ($type).'</option>'."\n";
			}
			$p->set_var ('lang_file_space', lang('File space'));
			$p->set_var ('account_file_space', '<input type=text name="account_file_space_number" value="'.trim($file_space_array[0]).'" size="7">');
			$p->set_var ('account_file_space_select','<select name="account_file_space_type">'."\n".$account_file_space_select.'</select>'."\n");
	*/

			if ($group_repository['mailAllowed'])
			{
				$p->set_var(array(
					'lang_email' => lang('Email'),
					'email' => html::input('account_email',$group_repository['account_email'],'',' style="width: 100%;"'),
				));
			}
			$availableApps = $GLOBALS['egw_info']['apps'];
			foreach($availableApps as $app => $data)
			{
				if (!$data['enabled'] || !$data['status'] || $data['status'] == 3 || $app == 'home')
				{
					unset($availableApps[$app]);	// do NOT show disabled apps, or our API (status = 3)
				}
			}
			uasort($availableApps,create_function('$a,$b','return strcasecmp($a["title"],$b["title"]);'));

			foreach ($availableApps as $app => $data)
			{
				$perm_display[] = Array(
					$app,
					$data['title']
				);
			}
			unset($app); unset($data);

			$perm_html = '<td width="35%">'.lang('Application').'</td><td width="15%">'.lang('enabled').' / '.lang('ACL').'</td>';
			$perm_html = '<tr class="th">'.
				$perm_html.$perm_html."</tr>\n";

			$tr_color = $GLOBALS['egw_info']['theme']['row_off'];
			for ($i=0;$i < count($perm_display);$i++)
			{
				$app = $perm_display[$i][0];
				if(!($i & 1))
				{
					$tr_class = $this->nextmatchs->alternate_row_color('',True);
					$perm_html .= '<tr class="'.$tr_class.'">';
				}
				$acl_action = self::_acl_action($app,$group_info['account_id'],$group_info['account_name'],$options);

				$perm_html .= '<td>' . $perm_display[$i][1] . '</td>'
					. '<td><input type="checkbox" name="account_apps['
					. $perm_display[$i][0] . ']" value="True"'.($group_info['account_apps'][$app]?' checked':'').'> '
					. ($acl_action?'<a href="'.$acl_action.'"'.$options
					. '><img src="'.$GLOBALS['egw']->common->image('phpgwapi','edit').'" border="0" hspace="3" align="absmiddle" title="'
					. lang('Grant Access').': '.lang("edit group ACL's").'"></a>':'&nbsp;').'</td>'.($i & 1?'</tr>':'')."\n";
			}
			if($i & 1)
			{
				$perm_html .= '<td colspan="4">&nbsp;</td></tr>';
			}

			$var = Array(
				'permissions_list'   => $perm_html,
				'lang_submit_button' => lang('submit changes')
			);
			$p->set_var($var);

			// create the menu on the left, if needed
			$p->set_var('rows',ExecMethod('admin.uimenuclass.createHTMLCode','group_manager'));

			$p->set_var('select','');
			$p->set_var('popwin','');
			$p->pfp('out','edit');
		}

		private function _acl_action($app,$account_id,$account_lid,&$options)
		{
			$options = '';
			if (!($acl_action = $this->apps_with_acl[$app]) || !$account_id)
			{
				return false;
			}
			if ($acl_action === true)
			{
				$acl_action = array(
					'menuaction' => 'preferences.uiaclprefs.index',
					'acl_app' => '$app',
					'owner'   => '$account_id',
				);
			}
			$replacements = array(
				'$app' => $app,
				'$account_id' => $account_id,
				'$account_lid' => $account_lid,
			);
			foreach($acl_action as $name => &$value)
			{
				$value = str_replace(array_keys($replacements),array_values($replacements),$value);
			}
			if ($acl_action['popup'])
			{
				list($w,$h) = explode('x',$acl_action['popup']);
				$options = ' onclick="window.open(this,this.target,\'width='.(int)$w.',height='.(int)$h.',location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\'); return false;"';
				unset($acl_action['popup']);
			}
			return $GLOBALS['egw']->link('/index.php',$acl_action);
		}

		function create_edit_user($_account_id,$_userData='',$_errors='')
		{
			//_debug_array($_userData);
			$GLOBALS['egw_info']['flags']['include_xajax'] = true;

			$jscal =& CreateObject('phpgwapi.jscalendar');

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);

			$GLOBALS['egw']->common->egw_header();

			$t =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$t->set_unknowns('remove');

			if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'] && ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap'))
			{
				$t->set_file(array('account' => 'account_form_ldap.tpl'));
			}
			else
			{
				$t->set_file(array('account' => 'account_form.tpl'));
			}
			$t->set_block('account','form','form');
			$t->set_block('account','form_passwordinfo','form_passwordinfo');
			$t->set_block('account','form_buttons_','form_buttons_');
			$t->set_block('account','link_row','link_row');

			$theme = $GLOBALS['egw_info']['user']['preferences']['common']['theme'];
			$t->set_var('icon_create_edit', '<img src="'. $GLOBALS['egw_info']['server']['webserver_url'] .'/admin/templates/'.$theme.'/images/useradm.gif">');

			//print_debug('Type : '.gettype($_userData).'<br>_userData(size) = "'.$_userData.'"('.strlen($_userData).')');
			if (is_array($_userData))
			{
				$userData = Array();
				$userData=$_userData;
//				$userData['firstname'] = $userData['account_firstname'];
//				$userData['lastname']  = $userData['account_lastname'];
				@reset($userData['account_groups']);
				while (list($key, $value) = @each($userData['account_groups']))
				{
					$userGroups[$key]['account_id'] = $value;
				}

				$account =& CreateObject('phpgwapi.accounts');
				$allGroups = $account->get_list('groups');
			}
			elseif(is_string($_userData) && $_userData=='')
			{
				if($_account_id)
				{
					$account =& CreateObject('phpgwapi.accounts',(int)$_account_id,'u');
					$userData = $account->read_repository();
					$userGroups = $account->membership($_account_id);
					$acl =& CreateObject('phpgwapi.acl',$_account_id);
					$acl->read_repository();
					$userData['anonymous'] = $acl->check('anonymous',1,'phpgwapi');
					$userData['changepassword'] = !$acl->check('nopasswordchange',1,'preferences');
					if (!isset($auth)) $auth =& CreateObject('phpgwapi.auth');
					$accLPWDC = $auth->getLastPwdChange($userData['account_lid']);
					if ($accLPWC !== false) $userData['account_lastpwd_change'] = $accLPWDC;
					$userData['mustchangepassword'] = (isset($userData['account_lastpwd_change']) && ((is_string($userData['account_lastpwd_change']) && $userData['account_lastpwd_change']==="0")||(is_int($userData['account_lastpwd_change']) && $userData['account_lastpwd_change']===0))?true:false);
					unset($acl);
				}
				else
				{
					$account =& CreateObject('phpgwapi.accounts');
					$userData = Array();
					$userData['status'] = 'A';
					$userGroups = Array();
					$userData['anonymous'] = False;
					$userData['changepassword'] = (bool)$GLOBALS['egw_info']['server']['change_pwd_every_x_days'];
					$userData['mustchangepassword'] = false;
				}
				$allGroups = $account->get_list('groups');
			}
			$page_params['menuaction'] = 'admin.uiaccounts.'.($_account_id?'edit':'add').'_user';
			if($_account_id)
			{
				$page_params['account_id']  = $_account_id;
				$page_params['old_loginid'] = rawurlencode($userData['account_lid']);
			}

			$var = Array(
				'form_action'    		=> $GLOBALS['egw']->link('/index.php',$page_params),
				'error_messages' 		=> (!$_errors?'':'<center>'.$GLOBALS['egw']->common->error_list($_errors).'</center>'),
				'th_bg'          		=> $GLOBALS['egw_info']['theme']['th_bg'],
				'tr_color1'      		=> $GLOBALS['egw_info']['theme']['row_on'],
				'tr_color2'      		=> $GLOBALS['egw_info']['theme']['row_off'],
				'lang_action'    		=> ($_account_id?lang('Edit user account'):lang('Add new account')),
				'lang_loginid'   		=> lang('LoginID'),
				'lang_account_active' 	=> lang('Account active'),
				'lang_email'     		=> lang('email'),
				'lang_password'  		=> lang('Password'),
				'lang_reenter_password' => lang('Re-Enter Password'),
				'lang_lastname'  		=> lang('Last Name'),
				'lang_groups'    		=> lang('Groups'),
				'lang_primary_group'    => lang('primary Group'),
				'lang_expires'   		=> lang('Expires'),
				'lang_firstname' 		=> lang('First Name'),
				'lang_anonymous' 		=> lang('Anonymous User (not shown in list sessions)'),
				'lang_changepassword' 	=> lang('Can change password'),
				'lang_mustchangepassword'=> lang('Must change password upon next login'),
				'lang_button'    		=> ($_account_id?lang('Save'):lang('Add')),
				'lang_passwds_unequal'  => lang('The two passwords are not the same'),
			/* 'lang_file_space' 		=> lang('File Space') */
			);
			$t->set_var($var);
			$t->parse('form_buttons','form_buttons_',True);

			if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'])
			{
				$lang_homedir = lang('home directory');
				$lang_shell = lang('login shell');
				$homedirectory = '<input name="homedirectory" id="homedirectory" value="'. ($_account_id?$userData['homedirectory']:$GLOBALS['egw_info']['server']['ldap_account_home'].$account_lid).'">';
				$loginshell = '<input name="loginshell" value="'
					. ($_account_id?$userData['loginshell']:$GLOBALS['egw_info']['server']['ldap_account_shell'])
					. '">';
			}
			else
			{
				$lang_homedir = '';
				$lang_shell = '';
				$homedirectory = '';
				$loginshell = '';
			}
			$account_file_space = '';
		/*
			if (!$userData['file_space'])
			{
				$userData['file_space'] = $GLOBALS['egw_info']['server']['vfs_default_account_size_number'] . "-" . $GLOBALS['egw_info']['server']['vfs_default_account_size_type'];
			}
			$file_space_array = explode ('-', $userData['file_space']);
			$account_file_space_number = $file_space_array[0];
			$account_file_space_type = $file_space_array[1];
			$account_file_space_type_selected[$account_file_space_type] = ' selected';

			$account_file_space = '<input type=text name="account_file_space_number" value="' . trim($account_file_space_number) . '" size="7">';
			$account_file_space_select ='<select name="account_file_space_type">';
			$account_file_space_types = array ('gb', 'mb', 'kb', 'b');
			while (list ($num, $type) = each ($account_file_space_types))
			{
				$account_file_space_select .= '<option value="'.$type.'"' . $account_file_space_type_selected[$type] . '>' . strtoupper ($type) . '</option>';
			}
			$account_file_space_select .= '</select>';

			$var = Array(
				'lang_file_space'    => 'File space',
				'account_file_space' => $account_file_space,
				'account_file_space_select' => $account_file_space_select
			);
			$t->set_var($var);
		*/
			$accountPrefix = '';
			if(isset($GLOBALS['egw_info']['server']['account_prefix']))
			{
				$accountPrefix = $GLOBALS['egw_info']['server']['account_prefix'];
				if (preg_match ("/^$accountPrefix(.*)/i", $userData['account_lid'], $matches))
				{
					$userData['account_lid'] = $matches[1];
				}
			}
			$var = Array(
				'input_expires' 	=> $jscal->input('expires',$userData['expires']<0?'':($userData['expires']?$userData['expires']:time()+(60*60*24*7))),
				'lang_never'    	=> lang('Never'),
				'account_lid'   	=> $accountPrefix.'<input id="account" onchange="check_account_email(this.id);" name="account_lid" maxlength="64" value="' . $userData['account_lid'] . '">',
				'lang_homedir'  	=> $lang_homedir,
				'lang_shell'    	=> $lang_shell,
				'homedirectory' 	=> $homedirectory,
				'loginshell'    	=> $loginshell,
				'anonymous'     	=> '<input type="checkbox" name="anonymous" value="1"'.($userData['anonymous'] ? ' checked' : '').'>',
				'changepassword'	=> '<input type="checkbox" name="changepassword" value="1"'.($userData['changepassword'] ? ' checked' : '').'>',
				'mustchangepassword'    => '<input type="checkbox" name="mustchangepassword" value="1"'.($userData['mustchangepassword'] ? ' checked' : '').'>',
				'account_status'    => '<input type="checkbox" name="account_status" value="A"'.($userData['status']?' checked':'').'>',
				'account_firstname' => '<input id="firstname" onchange="check_account_email(this.id);" name="account_firstname" maxlength="50" value="' . $userData['firstname'] . '">',
				'account_lastname'  => '<input id="lastname" onchange="check_account_email(this.id);" name="account_lastname" maxlength="50" value="' . $userData['lastname'] . '">',
				'account_email'     => '<input id="email" onchange="email_set=0; check_account_email(this.id);" name="account_email" size="32" maxlength="100" value="' . $userData['email'] . '">',
				'account_passwd'    => $userData['account_passwd'],
				'account_passwd_2'  => $userData['account_passwd_2'],
				'account_file_space' => $account_file_space,
				'account_id'        => (int) $userData['account_id']
			);
            if (isset($userData['account_created'])) $var['account_status'].= '<br>'.lang('Created').': '.$GLOBALS['egw']->common->show_date($userData['account_created']);
            if (isset($userData['account_modified'])) $var['account_status'].= '<br>'.lang('Modified').': '.$GLOBALS['egw']->common->show_date($userData['account_modified']);


			if($userData['expires'] == -1)
			{
				$var['never_expires'] = '<input type="checkbox" name="never_expires" value="True" checked>';
			}
			else
			{
				$var['never_expires'] = '<input type="checkbox" name="never_expires" value="True">';
			}

			$t->set_var($var);
			$t->parse('password_fields','form_passwordinfo',True);

			// set primary group to default, if there is no primary group set; this may fail, if no group "Default" exists
			if (!$userData['account_primary_group'])
			{
				$userData['account_primary_group'] = @$userGroups[0]['account_id'] ? @$userGroups[0]['account_id'] : $account->name2id('Default');
			}
			// prepare the allGroups array for use with the checkbox-multiselect use
			$allGroupsBuff=array();
			while (list($key,$value) = each($allGroups))
			{
				$allGroupsBuff[strtolower($value['account_lid'])]=$value;
			}
			// sort alphabetical
			ksort($allGroupsBuff);
			$allGroupsSorted=array();
			while (list($key,$value) = each($allGroupsBuff))
			{
				$allGroupsSorted[$value['account_id']]=$value['account_lid'];
				$primary_group_select .= '<option value="' . $value['account_id'] . '"';
				if ($value['account_id'] == $userData['account_primary_group'])
				{
					$primary_group_select .= ' selected="1"';
				}
				$primary_group_select .= '>' . $value['account_lid'] . '</option>'."\n";
			}
			//prepare the userGroups Array for use with the checkbox-multiselect use -> selarray
			$selarray=array();
			while (list($key,$value) = each($userGroups))
			{
				array_push($selarray,$value['account_id']);
			}
			$t->set_var('groups_select','<div id="groupselector">' .html::checkbox_multiselect('account_groups[]',$selarray,$allGroupsSorted,true,'',min(3+count($allGroupsSorted),10),' style="width: 300px; text-align:left" ').'</div>');

			/* create list of available apps */
			$apps =& CreateObject('phpgwapi.applications',$_account_id);
			$db_perms = $apps->read_account_specific();

			$availableApps = $GLOBALS['egw_info']['apps'];
			foreach($availableApps as $app => $data)
			{
				if (!$data['enabled'] || !$data['status'] || $data['status'] == 3 || $app == 'home')
				{
					unset($availableApps[$app]);	// do NOT show disabled apps, or our API (status = 3)
				}
			}
			uasort($availableApps,create_function('$a,$b','return strcasecmp($a["title"],$b["title"]);'));

			$appRightsOutput = '';
			$i = 0;
			foreach($availableApps as $app => $data)
			{
				$checked = (@$userData['account_permissions'][$app] || @$db_perms[$app]) && $_account_id ? ' checked="1"' : '';
				$acl_action = self::_acl_action($app,$_account_id,$userData['account_lid'],$options);
				$part[$i&1] = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s>',
					$data['title'],$app,$checked).
					($acl_action?'<a href="'.$acl_action.'"'.$options
					. '><img src="'.$GLOBALS['egw']->common->image('phpgwapi','edit').'" border="0" hspace="3" align="absmiddle" title="'
					. lang('Grant Access').'"></a>':'&nbsp;').'</td>';

				if ($i & 1)
				{
					$appRightsOutput .= sprintf('<tr class="%s">%s%s</tr>',$this->nextmatchs->alternate_row_color('',true), $part[0], $part[1]);
				}
				++$i;
			}
			if ($i & 1)
			{
				$part[1] = '<td colspan="3">&nbsp;</td>';
				$appRightsOutput .= sprintf('<tr class="%s">%s%s</tr>',$this->nextmatchs->alternate_row_color('',true), $part[0], $part[1]);
			}

			$var = Array(
				// KL 20061211 groups_select is already set
				//'groups_select'
				//=> '<div id="groupselector">' .
				//"\n".$groups_select. '</div>' . "\n",
				'primary_group_select'
				=> '<select
				name="account_primary_group">'."\n".$primary_group_select.'</
				select>'."\n",
				'permissions_list' => $appRightsOutput,
				'lang_app' => lang('application'),
				'lang_acl' => lang('enabled').' / '.lang('ACL'),
				);

/*
			$var = Array(
				'groups_select'
					=> '<select name="account_groups[]" multiple>'."\n".$groups_select.'</select>'."\n",
				'primary_group_select'
					=> '<select name="account_primary_group">'."\n".$primary_group_select.'</select>'."\n",
				'permissions_list'
					=> $appRightsOutput,
				'lang_app' => lang('application'),
				'lang_acl' => lang('enabled').' / '.lang('ACL'),
			);

*/
			$t->set_var($var);

			// create the menu on the left, if needed
//			$menuClass =& CreateObject('admin.uimenuclass');
			// This is now using ExecMethod()
			$GLOBALS['account_id'] = $_account_id;
			$t->set_var('rows',ExecMethod('admin.uimenuclass.createHTMLCode','edit_user'));

			echo $t->fp('out','form');
		}

		function ajax_check_account_email($first,$last,$account_lid,$account_id,$email,$id)
		{
			$response = new xajaxResponse();
			if (!$email)
			{
				$response->addAssign('email','value',$GLOBALS['egw']->common->email_address($first,$last,$account_lid));
			}
			$id_account_lid = (int) $GLOBALS['egw']->accounts->name2id($account_lid);
			if ($id == 'account' && $id_account_lid && $id_account_lid != (int) $account_id)
			{
				$response->addScript("alert('".addslashes(lang('That loginid has already been taken').': '.$account_lid)."'); document.getElementById('account').value='".
					($account_id ? $GLOBALS['egw']->accounts->id2name($account_id) : '')."'; document.getElementById('account').focus();");
			}
			if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'] &&
				($home = $GLOBALS['egw_info']['server']['ldap_account_home']) && $home != '/dev/null')
			{
				$response->addAssign('homedirectory','value',$home.'/'.$account_lid);
			}
			return $response->getXML();
		}

		function edit_group_managers($group_info,$_errors='')
		{
			if ($GLOBALS['egw']->acl->check('group_access',16,'admin'))
			{
				$this->list_groups();
				return False;
			}

			$accounts =& CreateObject('phpgwapi.accounts',$group_info['account_id'],'u');
			$account_list = $accounts->member($group_info['account_id']);
			$user_list = '';
			while (list($key,$entry) = each($account_list))
			{
				$user_list .= '<option value="' . $entry['account_id'] . '"'
					. $group_info['account_managers'][(int)$entry['account_id']] . '>'
					. $GLOBALS['egw']->common->grab_owner_name($entry['account_id'])
					. '</option>'."\n";
			}

			unset($GLOBALS['egw_info']['flags']['noheader']);
			unset($GLOBALS['egw_info']['flags']['nonavbar']);
			$GLOBALS['egw']->common->egw_header();

			$t =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
			$t->set_unknowns('remove');

			$t->set_file(
				Array(
					'manager' =>'group_manager.tpl'
				)
			);

			$t->set_block('manager','form','form');
			$t->set_block('manager','link_row','link_row');

			$var['th_bg'] = $GLOBALS['egw_info']['user']['theme']['th_bg'];
			$var['lang_group'] = lang('Group');
			$var['group_name'] = $group_info['account_name'];
			$var['tr_color1'] = $GLOBALS['egw_info']['user']['theme']['row_on'];
			$var['form_action'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.set_group_managers');
			$var['hidden'] = '<input type="hidden" name="account_id" value="'.$group_info['account_id'].'">';
			$var['lang_select_managers'] = lang('Select Group Managers');
			$var['group_members'] = '<select name="managers[]" size="'.(count($account_list)<5?count($account_list):5).'" multiple>'.$user_list.'</select>';
			$var['form_buttons'] = '<tr align="center"><td colspan="2"><input type="submit" name="submit" value="'.lang('Submit').'">&nbsp;&nbsp;'
				. '<input type="submit" name="cancel" value="'.lang('Cancel').'"><td></tr>';
			$t->set_var($var);

			// create the menu on the left, if needed
			$t->set_var('rows',ExecMethod('admin.uimenuclass.createHTMLCode','edit_group'));

			$t->pfp('out','form');
		}

		function set_group_managers()
		{
			if($GLOBALS['egw']->acl->check('group_access',16,'admin') || $_POST['cancel'])
			{
				$GLOBALS['egw']->redirect_link('/index.php','menuaction=admin.uiaccounts.list_groups');
				$GLOBALS['egw']->common->egw_exit();
			}
			elseif($_POST['submit'])
			{
				$acl =& CreateObject('phpgwapi.acl',(int)$_POST['account_id']);

				$users = $GLOBALS['egw']->accounts->member($_POST['account_id']);
				@reset($users);
				while($managers && list($key,$user) = each($users))
				{
					$acl->add_repository('phpgw_group',(int)$_POST['account_id'],$user['account_id'],1);
				}
				$managers = $_POST['managers'];
				@reset($managers);
				while($managers && list($key,$manager) = each($managers))
				{
					$acl->add_repository('phpgw_group',(int)$_POST['account_id'],$manager,(1 + EGW_ACL_GROUP_MANAGERS));
				}
			}
			$GLOBALS['egw']->redirect($GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			$GLOBALS['egw']->common->egw_exit();
		}

		/**
		 * applies stripslashes recursively on each element of an array
		 *
		 * @param array &$var
		 * @return array
		 */
		function array_stripslashes($var)
		{
			if(!is_array($var))
			{
				return stripslashes($var);
			}
			foreach($var as $key => $val)
			{
				$var[$key] = is_array($val) ? $this->array_stripslashes($val) : stripslashes($val);
			}
			return $var;
		}
	}
?>
