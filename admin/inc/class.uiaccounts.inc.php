<?php
	/**************************************************************************\
	* phpGroupWare - account administration                                    *
	* http://www.phpgroupware.org                                              *
	* Written by coreteam <phpgroupware-developers@gnu.org>                    *
	* -----------------------------------------------------                    *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	class uiaccounts
	{
		var $public_functions = array
		(
			'list_groups'	=> True,
			'list_users'	=> True,
			'add_group'		=> True,
			'add_user'		=> True,
			'delete_group'	=> True,
			'delete_user'	=> True,
			'edit_user'		=> True,
			'edit_group'	=> True,
			'view_user'		=> True,
			'group_manager'	=> True
		);

		var $bo;
		var $nextmatchs;

		function uiaccounts()
		{
			$GLOBALS['phpgw_info']['flags']['xslt_app'] = True;

			$this->bo			= createobject('admin.boaccounts');
			$this->nextmatchs	= createobject('phpgwapi.nextmatchs');

			@set_time_limit(300);
		}

		function row_action($action,$type,$account_id)
		{
			return '<a href="'.$GLOBALS['phpgw']->link('/index.php',Array(
				'menuaction' => 'admin.uiaccounts.'.$action.'_'.$type,
				'account_id' => $account_id
			)).'"> '.lang($action).' </a>';
		}

		function list_groups()
		{
			if ($GLOBALS['phpgw']->acl->check('group_access',1,'admin'))
			{
				$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.mainscreen'));
			}

			$query = (isset($_POST['query'])?$_POST['query']:'');

			$GLOBALS['cd'] = ($_GET['cd']?$_GET['cd']:0);

			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('administration') . ': ' . lang('list groups');

			$GLOBALS['phpgw']->xslttpl->add_file(array('app_data','groups',
										$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'search_field',
										$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'nextmatchs'));

/* what should this be for??? this is the same call for both cases! can this be removed? [ceb] */

			if ($GLOBALS['phpgw']->acl->check('group_access',2,'admin'))
			{
				$account_info = $GLOBALS['phpgw']->accounts->get_list('groups',$start,$sort, $order, $query, $total);
				$total = $GLOBALS['phpgw']->accounts->total;
			}
			else
			{
				$account_info = $GLOBALS['phpgw']->accounts->get_list('groups',$start,$sort, $order, $query, $total);
				$total = $GLOBALS['phpgw']->accounts->total;
			}

			$group_header = array
			(
				'sort_name'				=> $this->nextmatchs->show_sort_order(array
											(
												'sort'	=> $sort,
												'var'	=> 'account_lid',
												'order'	=> $order,
												'extra'	=> 'menuaction=admin.uiaccounts.list_groups'
											)),
				'lang_name'				=> lang('name'),
				'lang_edit'				=> lang('edit'),
				'lang_delete'			=> lang('delete'),
				'lang_sort_statustext'	=> lang('sort the entries')
			);

			while (list($null,$account) = each($account_info))
			{
				$group_data[] = Array
				(
					'edit_url'					=> ($this->bo->check_rights('edit')?$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.edit_group&account_id=' . $account['account_id']):''),
					'lang_edit'					=> ($this->bo->check_rights('edit')?lang('edit'):''),
					'lang_edit_statustext'		=> ($this->bo->check_rights('edit')?lang('edit this group'):''),
					'group_name'				=> (!$account['account_lid']?'':$account['account_lid']),
					'delete_url'				=> ($this->bo->check_rights('delete')?$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.delete_group&account_id=' . $account['account_id']):''),
					'lang_delete_statustext'	=> ($this->bo->check_rights('delete')?lang('delete this group'):''),
					'lang_delete'				=> ($this->bo->check_rights('delete')?lang('delete'):'')
				);
			}

			$group_add = array
			(
				'lang_add'				=> lang('add'),
				'lang_add_statustext'	=> lang('add a group'),
				'add_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.edit_group'),
				'lang_done'				=> lang('done'),
				'lang_done_statustext'	=> lang('return to admin mainscreen'),
				'done_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.mainscreen'),
				'add_access'			=> ($this->bo->check_rights('add')?'yes':''),
			);

			$data = array
			(
				'start_record'					=> $start,
 				'record_limit'					=> $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'],
 				'num_records'					=> count($account_info),
 				'all_records'					=> $total,
				'nextmatchs_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'nextmatchs_img_path'			=> $GLOBALS['phpgw']->common->get_image_path('phpgwapi','default'),
				'select_url'					=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'lang_searchfield_statustext'	=> lang('Enter the search string. To show all entries, empty this field and press the SUBMIT button again'),
				'lang_searchbutton_statustext'	=> lang('Submit the search string'),
				'query'							=> $query,
				'lang_search'					=> lang('search'),
				'group_header'					=> $group_header,
				'group_data'					=> $group_data,
				'group_add'						=> $group_add,
				'search_access'					=> ($this->bo->check_rights('search')?'yes':'')
			);
			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('group_list' => $data));
		}

		function list_users($param_cd='')
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',1,'admin'))
			{
				$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.mainscreen'));
			}

			if($param_cd)
			{
				$cd = $param_cd;
			}

			$GLOBALS['query'] = (isset($GLOBALS['HTTP_POST_VARS']['query'])?$GLOBALS['HTTP_POST_VARS']['query']:'');
			$start = (isset($GLOBALS['HTTP_POST_VARS']['start'])?intval($GLOBALS['HTTP_POST_VARS']['start']):'');

			if(isset($_GET['order']))
			{
				$order = $_GET['order'];
			}
			else
			{
				$order = 'account_lid';
			}
			
			if(isset($_GET['sort']))
			{
				$sort = $_GET['sort'];
			}
			else
			{
				$sort = 'ASC';
			}

			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('administration') . ': ' . lang('list users');

			$GLOBALS['phpgw']->xslttpl->add_file(array('app_data','users',
										$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'search_field',
										$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'nextmatchs'));

/* the same like in groups... we really should remove this... :) [ceb] */

			if ($GLOBALS['phpgw']->acl->check('account_access',2,'admin'))
			{
				$account_info = $GLOBALS['phpgw']->accounts->get_list('accounts',$start,$sort,$order,$GLOBALS['query'],$total);
				$total = $GLOBALS['phpgw']->accounts->total;
			}
			else
			{
				$account_info = $GLOBALS['phpgw']->accounts->get_list('accounts',$start,$sort,$order,$GLOBALS['query'],$total);
				$total = $GLOBALS['phpgw']->accounts->total;
			}

			$user_header = array
			(
				'sort_lid'				=> $this->nextmatchs->show_sort_order(array
											(
												'sort'	=> $sort,
												'var'	=> 'account_lid',
												'order'	=> $order,
												'extra'	=> 'menuaction=admin.uiaccounts.list_users'
											)),
				'lang_lid'				=> lang('loginid'),
				'sort_lastname'			=> $this->nextmatchs->show_sort_order(array
											(
												'sort'	=> $sort,
												'var'	=> 'account_lastname',
												'order'	=> $order,
												'extra'	=> 'menuaction=admin.uiaccounts.list_users'
											)),
				'lang_lastname'				=> lang('Lastname'),
				'sort_firstname'			=> $this->nextmatchs->show_sort_order(array
											(
												'sort'	=> $sort,
												'var'	=> 'account_firstname',
												'order'	=> $order,
												'extra'	=> 'menuaction=admin.uiaccounts.list_users'
											)),
				'lang_firstname'			=> lang('firstname'),
				'lang_view'				=> lang('view'),
				'lang_edit'				=> lang('edit'),
				'lang_delete'			=> lang('delete'),
				'lang_sort_statustext'	=> lang('sort the entries')
			);

			while (list($null,$account) = each($account_info))
			{
				$user_data[] = Array
				(
					'view_url'					=> ($this->bo->check_rights('view','account_access')?$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.view_user&account_id=' . $account['account_id']):''),
					'lang_view'					=> ($this->bo->check_rights('view','account_access')?lang('view'):''),
					'lang_view_statustext'		=> ($this->bo->check_rights('view','account_access')?lang('view this user'):''),
					'edit_url'					=> ($this->bo->check_rights('edit','account_access')?$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.edit_user&account_id=' . $account['account_id']):''),
					'lang_edit'					=> ($this->bo->check_rights('edit','account_access')?lang('edit'):''),
					'lang_edit_statustext'		=> ($this->bo->check_rights('edit','account_access')?lang('edit this user'):''),
					'lid'						=> (!$account['account_lid']?'':$account['account_lid']),
					'firstname'					=> (!$account['account_firstname']?'':$account['account_firstname']),
					'lastname'					=> (!$account['account_lastname']?'':$account['account_lastname']),
					'delete_url'				=> ($this->bo->check_rights('delete','account_access')?$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.delete_user&account_id=' . $account['account_id']):''),
					'lang_delete_statustext'	=> ($this->bo->check_rights('delete','account_access')?lang('delete this user'):''),
					'lang_delete'				=> ($this->bo->check_rights('delete','account_access')?lang('delete'):'')
				);
			}

			$user_add = array
			(
				'lang_add'				=> lang('add'),
				'lang_add_statustext'	=> lang('add a user'),
				'add_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.edit_user'),
				'lang_done'				=> lang('done'),
				'lang_done_statustext'	=> lang('return to admin mainscreen'),
				'done_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uimainscreen.mainscreen'),
				'add_access'			=> ($this->bo->check_rights('add','account_access')?'yes':''),
			);

			$data = array
			(
				'start_record'					=> $start,
 				'record_limit'					=> $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'],
 				'num_records'					=> count($account_info),
 				'all_records'					=> $total,
				'nextmatchs_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users'),
				'nextmatchs_img_path'			=> $GLOBALS['phpgw']->common->get_image_path('phpgwapi','default'),
				'select_url'					=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users'),
				'lang_searchfield_statustext'	=> lang('Enter the search string. To show all entries, empty this field and press the SUBMIT button again'),
				'lang_searchbutton_statustext'	=> lang('Submit the search string'),
				'query'							=> $query,
				'lang_search'					=> lang('search'),
				'user_header'					=> $user_header,
				'user_data'						=> $user_data,
				'user_add'						=> $user_add,
				'search_access'					=> ($this->bo->check_rights('search','account_access')?'yes':'')
			);
			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('account_list' => $data));
		}

		function add_user()
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',4,'admin'))
			{
				$this->list_users();
			}
			else
			{
				$this->create_edit_user(0);
			}
		}

		function delete_group()
		{
			$account_id = get_var('account_id',array('POST','GET'));

			if ($GLOBALS['phpgw']->acl->check('group_access',32,'admin'))
			{
				$this->list_groups();
				return False;
			}

			if ($account_id && get_var('confirm',array('POST')))
			{
				$this->bo->delete_group($account_id);
				$this->list_groups();
				return False;
			}

			$GLOBALS['phpgw']->xslttpl->add_file(array('app_data',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'app_delete'));
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('administration') . ': ' . lang('delete group');

			$data = array
			(
				'delete_url'			=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.delete_group&account_id=' . $account_id),
				'done_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'lang_yes'				=> lang('Yes'),
				'lang_no'				=> lang('No'),
				'lang_yes_statustext'	=> lang('Delete the entry'),
				'lang_no_statustext'	=> lang('Back to the list'),
				'lang_error_msg'		=> lang('are you sure you want to delete this group ?')
			);

			$old_group_list = $GLOBALS['phpgw']->acl->get_ids_for_location(intval($account_id),1,'phpgw_group');

			if($old_group_list)
			{
				$group_name = $GLOBALS['phpgw']->accounts->id2name($account_id);

				$user_list = '';
				while (list(,$id) = each($old_group_list))
				{
					$data['user_list'][] = array
					(
						'user_url'					=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.edit_user&account_id=' . $id),
						'user_name'					=> $GLOBALS['phpgw']->common->grab_owner_name($id),
						'lang_user_url_statustext'	=> lang('edit user')
					);
				}

				$data['lang_confirm_msg']			= lang('the users bellow are still members of group %1',$group_name) . '. '
													. lang('they must be removed before you can continue');
				$data['lang_remove_user']			= lang('Remove all users from this group ?');
			}

			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('delete' => $data));
		}

		function delete_user()
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',32,'admin') || $GLOBALS['phpgw_info']['user']['account_id'] == $_GET['account_id'])
			{
				$this->list_users();
				return False;
			}
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$t->set_file(
				Array(
					'form' => 'delete_account.tpl'
				)
			);
			$var = Array(
				'form_action' => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.boaccounts.delete_user'),
				'account_id'  => $_GET['account_id']
			);

			// the account can have special chars/white spaces, if it is a ldap dn
			$account_id = rawurlencode($_GET['account_id']);

			// Find out who the new owner is of the deleted users records...
			$users = $GLOBALS['phpgw']->accounts->get_list('accounts');
			$c_users = count($users);
			$str = '';
			for($i=0;$i<$c_users;$i++)
			{
				$str .= '<option value='.$users[$i]['account_id'].'>'.$GLOBALS['phpgw']->common->display_fullname($users[$i]['account_lid'],$users[$i]['account_firstname'],$users[$i]['account_lastname']).'</option>'."\n";
			}
			$var['lang_new_owner'] = lang('Who would you like to transfer ALL records owned by the deleted user to?');
			$var['new_owner_select'] = '<select name="new_owner" size="5">'."\n".'<option value=0 selected>'.lang('Delete All Records').'</option>'."\n".$str.'</select>'."\n";
			$var['cancel'] = lang('cancel');
			$var['delete'] = lang('delete');
			$t->set_var($var);
			$t->pparse('out','form');
		}

		function edit_group()
		{
			$account_id 	= get_var('account_id',array('POST','GET'));
			$values			= get_var('values',array('POST'));
			$account_user	= get_var('account_user',array('POST'));
			$account_apps	= get_var('account_apps',array('POST'));

			if ($values['save'])
			{
				$error = $this->bo->validate_group($values);

				if (is_array($error))
				{

				}
				else
				{
					if (is_array($account_user))
					{
						$values['account_user'] = $account_user;
					}

					if (is_array($account_apps))
					{
						$values['account_apps'] = $account_apps;
					}

					if ($values['account_id'])
					{
						$this->bo->edit_group($values);
						$account_id = $values['account_id'];
					}
					else
					{
						$this->bo->add_group($values);
						Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
					}
				}
			}

			if (!$account_id && $GLOBALS['phpgw']->acl->check('group_access',4,'admin'))
			{
				$this->list_groups();
				return False;
			}

			if ($account_id && $GLOBALS['phpgw']->acl->check('group_access',16,'admin'))
			{
				$this->list_groups();
				return False;
			}

			$cdid = $cd;
			settype($cd,'integer');
			$cd = ($_GET['cd']?$_GET['cd']:intval($cdid));

			if ($account_id)
			{
				$group_info = Array
				(
					'account_name' => $GLOBALS['phpgw']->accounts->id2name($account_id),
					'account_user' => $this->bo->load_group_users($account_id),
					'account_apps' => $this->bo->load_group_apps($account_id)
				);
			}

			$apps_with_acl = Array
			(
				'addressbook'	=> True,
				'bookmarks'		=> True,
				'calendar'		=> True,
				'filemanager'	=> True,
				'img'			=> True,
				'infolog'		=> True,
				'inv'			=> True,
				'netsaint'		=> True,
				'notes'			=> True,
				'phonelog'		=> True,
				'phpwebhosting'	=> True,
				'projects'		=> True,
				'todo'			=> True,
				'tts'			=> True
			);

			$GLOBALS['phpgw']->xslttpl->add_file(array('app_data','groups'));
			$GLOBALS['phpgw_info']['flags']['app_header'] = lang('administration') . ': ' . ((intval($account_id) > 0)?lang('edit group'):lang('add group'));

			$accounts = CreateObject('phpgwapi.accounts',$account_id,'u');
			$account_list = $accounts->get_list('accounts');
			$account_num = count($account_list);

			$user_list = '';
			while (list($key,$entry) = each($account_list))
			{
				$user_list[] = array
				(
					'account_id'		=> $entry['account_id'],
					'account_name'		=> $GLOBALS['phpgw']->common->display_fullname($entry['account_lid'],$entry['account_firstname'],
																						$entry['account_lastname']),
 					'selected'			=> $group_info['account_user'][intval($entry['account_id'])]
				);
			}

			$group_repository = $accounts->read_repository();
			if (!$group_repository['file_space'])
			{
				$group_repository['file_space'] = $GLOBALS['phpgw_info']['server']['vfs_default_account_size_number'] . "-" . $GLOBALS['phpgw_info']['server']['vfs_default_account_size_type'];
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

			reset($GLOBALS['phpgw_info']['apps']);
			$sorted_apps = $GLOBALS['phpgw_info']['apps'];
			@asort($sorted_apps);
			@reset($sorted_apps);
			while ($permission = each($sorted_apps))
			{
				if ($permission[1]['enabled'] && $permission[1]['status'] != 3)
				{
					$perm_display[] = array
					(
						$permission[0],
						$permission[1]['title']
					);
				}
			}

			/*$perm_html = '<td>'.lang('Application').'</td><td>&nbsp;</td><td>'.lang('ACL').'</td>';
			$perm_html = '<tr class="th">'.$perm_html.$perm_html."</tr>\n";*/

			for ($i=0;$i < count($perm_display);$i++)
			{
				$app = $perm_display[$i][0];
				/*$perm_html .= '<td width="40%">' . $perm_display[$i][1] . '</td>'
					. '<td width="5%"><input type="checkbox" name="account_apps['
					. $perm_display[$i][0] . ']" value="True"'.($group_info['account_apps'][$app]?' checked':'').'></td><td width="5%" align="center">'
					. ($apps_with_acl[$app] && $group_info['account_id']?'<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$app.'&owner='.$group_info['account_id'])
					. '" target="_blank"><img src="'.$GLOBALS['phpgw']->common->image('admin','dot').'" border="0" hspace="3" align="absmiddle" alt="'
					. lang('Grant Access').'"></a>':'&nbsp;').'</td>'.($i & 1?'</tr>':'')."\n";*/

				$app_list[] = array
				(
					'app_name'		=> $perm_display[$i][1],
					'checkbox_name'	=> 'account_apps[' . $perm_display[$i][0] . ']',
					'checked'		=> ($group_info['account_apps'][$app]?'checked':''),
					'acl_url'		=> ($apps_with_acl[$app] && $account_id?$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$app.'&owner='.$account_id):''),
					'acl_img'		=> $GLOBALS['phpgw']->common->image('admin','dot'),
					'img_name'		=> lang('Grant Access')
				);
			}

			/*if($i & 1)
			{
				$perm_html .= '<td colspan="4">&nbsp;</td></tr>';
			}*/

			$link_data = array
			(
				'menuaction'	=> 'admin.uiaccounts.edit_group',
				'account_id'	=> $account_id
			);

			$data = array
			(
				'edit_url'				=> $GLOBALS['phpgw']->link('/index.php',$link_data),
				'done_url'				=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'account_id'			=> $group_info['account_id'],
				'lang_account_name'		=> lang('group name'),
				'value_account_name'	=> $group_info['account_name'],
				'lang_include_user'		=> lang('select users for inclusion'),
				'error'					=> (!$_errors?'':$GLOBALS['phpgw']->common->error_list($_errors)),
				'select_size'			=> ($account_num < 5?$account_num:5),
				'user_list'				=> $user_list,
				'lang_permissions'		=> lang('permissions this group has'),
				'lang_application'		=> lang('application'),
				'lang_acl'				=> lang('acl'),
				'lang_done'				=> lang('done'),
				'lang_save'				=> lang('save'),
				'app_list'				=> $app_list,
				'account_id'			=> $account_id
			);

			/* create the menu on the left, if needed
			$p->set_var('rows',ExecMethod('admin.uimenuclass.createHTMLCode','group_manager')); */

			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('group_edit' => $data));
		}

		function edit_user($cd='',$account_id='')
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',16,'admin'))
			{
				$this->list_users();
				return False;
			}

			$cdid = $cd;
			settype($cd,'integer');
			$cd = ($_GET['cd']?$_GET['cd']:intval($cdid));

			$accountid = $account_id;
			settype($account_id,'integer');
			$account_id = ($_GET['account_id']?$_GET['account_id']:intval($accountid));
			
			// todo
			// not needed if i use the same file for new users too
			if (! $account_id)
			{
				$this->list_users();
				return False;
			}
			else
			{
				$this->create_edit_user($account_id);
			}
		}

		function view_user()
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',8,'admin') || ! $_GET['account_id'])
			{
				$this->list_users();
				return False;
			}
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
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
				'lang_action'  => lang('View user account'),
				'lang_loginid' => lang('LoginID'),
				'lang_account_active'   => lang('Account active'),
				'lang_password'         => lang('Password'),
				'lang_reenter_password' => lang('Re-Enter Password'),
				'lang_lastname'      => lang('Last Name'),
				'lang_groups'        => lang('Groups'),
				'lang_firstname'     => lang('First Name'),
				'lang_lastlogin'     => lang('Last login'),
				'lang_lastloginfrom' => lang('Last login from'),
				'lang_expires' => lang('Expires')
			);

			$t->parse('password_fields','form_logininfo',True);

			$account = CreateObject('phpgwapi.accounts',intval($_GET['account_id']),'u');
			$userData = $account->read_repository();

			$var['account_lid']       = $userData['account_lid'];
			$var['account_firstname'] = $userData['firstname'];
			$var['account_lastname']  = $userData['lastname'];

			if ($userData['status'])
			{
				$var['account_status'] = lang('Enabled');
			}
			else
			{
				$var['account_status'] = '<b>' . lang('Disabled') . '</b>';
			}

			// Last login time
			if ($userData['lastlogin'])
			{
				$var['account_lastlogin'] = $GLOBALS['phpgw']->common->show_date($userData['lastlogin']);
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
				$var['input_expires'] = $GLOBALS['phpgw']->common->show_date($userData['expires']);
			}
			else
			{
				$var['input_expires'] = lang('Never');
			}

			// Find out which groups they are members of
			$usergroups = $account->membership(intval($_GET['account_id']));
			if (gettype($usergroups) != 'array')
			{
				$var['groups_select'] = lang('None');
			}
			else
			{
				while (list(,$group) = each($usergroups))
				{
					$group_names[] = $group['account_name'];
				}
				$var['groups_select'] = implode(',',$group_names);
			}

			$account_lastlogin      = $userData['account_lastlogin'];
			$account_lastloginfrom  = $userData['account_lastloginfrom'];
			$account_status         = $userData['account_status'];

			// create list of available app
			$i = 0;
		
			$availableApps = $GLOBALS['phpgw_info']['apps'];
			@asort($availableApps);
			@reset($availableApps);
			while ($application = each($availableApps)) 
			{
				if ($application[1]['enabled'] && $application[1]['status'] != 2) 
				{
					$perm_display[$i]['appName']        = $application[0];
					$perm_display[$i]['translatedName'] = $application[1]['title'];
					$i++;
				}
			}

			// create apps output
			$apps = CreateObject('phpgwapi.applications',intval($_GET['account_id']));
			$db_perms = $apps->read_account_specific();

			@reset($db_perms);

			for ($i=0;$i<=count($perm_display);$i++)
			{
				if ($perm_display[$i]['translatedName'])
				{
					$part1 = sprintf("<td>%s</td><td>%s</td>",lang($perm_display[$i]['translatedName']),($_userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?'&nbsp;&nbsp;X':'&nbsp'));
				}

				$i++;

				if ($perm_display[$i]['translatedName'])
				{
					$part2 = sprintf("<td>%s</td><td>%s</td>",lang($perm_display[$i]['translatedName']),($_userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?'&nbsp;&nbsp;X':'&nbsp'));
				}
				else
				{
					$part2 = '<td colspan="2">&nbsp;</td>';
				}

				$appRightsOutput .= "<tr class=\"row_on\">$part1$part2</tr>\n";
			}

			$var['permissions_list'] = $appRightsOutput;

			// create the menu on the left, if needed
//			$menuClass = CreateObject('admin.uimenuclass');
			// This is now using ExecMethod()
			$var['rows'] = ExecMethod('admin.uimenuclass.createHTMLCode','view_user');
			$t->set_var($var);
			$t->pfp('out','form');
		}

		function group_manager($cd='',$account_id='')
		{
			if ($GLOBALS['phpgw']->acl->check('group_access',16,'admin'))
			{
				$this->list_groups();
				return False;
			}

			$cdid = $cd;
			settype($cd,'integer');
			$cd = ($_GET['cd']?$_GET['cd']:intval($cdid));

			$accountid = $account_id;
			settype($account_id,'integer');
			$account_id = ($_GET['account_id']?$_GET['account_id']:intval($accountid));
			
			// todo
			// not needed if i use the same file for new groups too
			if (! $account_id)
			{
				$this->list_groups();
			}
			else
			{
				$group_info = Array(
					'account_id'   => intval($_GET['account_id']),
					'account_name' => $GLOBALS['phpgw']->accounts->id2name($_GET['account_id']),
					'account_user' => $GLOBALS['phpgw']->accounts->member($_GET['account_id']),
					'account_managers' => $this->bo->load_group_managers($_GET['account_id'])
				);

				$this->edit_group_managers($group_info);
			}
		}

		function create_edit_user($_account_id,$_userData='',$_errors='')
		{
			$sbox = createobject('phpgwapi.sbox');

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$t->set_unknowns('remove');

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes'] && ($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap'))
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

			print_debug('Type : '.gettype($_userData).'<br>_userData(size) = "'.$_userData.'"('.strlen($_userData).')');
			if (is_array($_userData))
			{
				$userData = Array();
				$userData=$_userData;
				@reset($userData['account_groups']);
				while (list($key, $value) = @each($userData['account_groups']))
				{
					$userGroups[$key]['account_id'] = $value;
				}

				$account = CreateObject('phpgwapi.accounts');
				$allGroups = $account->get_list('groups');
			}
			elseif(is_string($_userData) && $_userData=='')
			{
				if($_account_id)
				{
					$account = CreateObject('phpgwapi.accounts',intval($_account_id),'u');
					$userData = $account->read_repository();
					$userGroups = $account->membership($_account_id);
				}
				else
				{
					$account = CreateObject('phpgwapi.accounts');
					$userData = Array();
					$userData['status'] = 'A';
					$userGroups = Array();
				}
				$allGroups = $account->get_list('groups');

				if ($userData['expires'] == -1)
				{
					$userData['account_expires_month'] = 0;
					$userData['account_expires_day']   = 0;
					$userData['account_expires_year']  = 0;
				}
				else
				{
					/* Change this to be an admin/setup setting.  For now, default to expire one week from today. */
					$time_var = time() + (60*60*24*7);
					$userData['account_expires_month'] = date('m',$userData['expires'] > 0 ? $userData['expires'] : $time_var);
					$userData['account_expires_day']   = date('d',$userData['expires'] > 0 ? $userData['expires'] : $time_var);
					$userData['account_expires_year']  = date('Y',$userData['expires'] > 0 ? $userData['expires'] : $time_var);
				}
			}
			$page_params['menuaction'] = 'admin.boaccounts.'.($_account_id?'edit':'add').'_user';
			if($_account_id)
			{
				$page_params['account_id']  = $_account_id;
				$page_params['old_loginid'] = rawurlencode($userData['account_lid']);
			}

			$var = Array(
				'form_action'    => $GLOBALS['phpgw']->link('/index.php',$page_params),
				'cancel_action'  => $GLOBALS['phpgw']->link('/admin/index.php'),
				'error_messages' => (!$_errors?'':'<center>'.$GLOBALS['phpgw']->common->error_list($_errors).'</center>'),
				'lang_action'    => ($_account_id?lang('Edit user account'):lang('Add new account')),
				'lang_loginid'   => lang('LoginID'),
				'lang_account_active' => lang('Account active'),
				'lang_password'  => lang('Password'),
				'lang_reenter_password' => lang('Re-Enter Password'),
				'lang_lastname'  => lang('Last Name'),
				'lang_groups'    => lang('Groups'),
				'lang_expires'   => lang('Expires'),
				'lang_firstname' => lang('First Name'),
				'lang_button'    => ($_account_id?lang('Save'):lang('Add')),
				'lang_cancel'    => lang('Cancel')
			/* 'lang_file_space' => lang('File Space') */
			);
			$t->set_var($var);
			$t->parse('form_buttons','form_buttons_',True);

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes']) {
				$lang_homedir = lang('home directory');
				$lang_shell = lang('login shell');
				$homedirectory = '<input name="homedirectory" value="'
					. ($_account_id?$userData['homedirectory']:$GLOBALS['phpgw_info']['server']['ldap_account_home'].SEP.$account_lid)
					. '">';
				$loginshell = '<input name="loginshell" value="'
					. ($_account_id?$userData['loginshell']:$GLOBALS['phpgw_info']['server']['ldap_account_shell'])
					. '">';
			}
			else
			{
				$lang_homedir = '';
				$lang_shell = '';
				$homedirectory = '';
				$loginshell = '';
			}

			$_y = $sbox->getyears('account_expires_year',$userData['account_expires_year'],date('Y'),date('Y')+10);
			$_m = $sbox->getmonthtext('account_expires_month',$userData['account_expires_month']);
			$_d = $sbox->getdays('account_expires_day',$userData['account_expires_day']);

			$account_file_space = '';
		/*
			if (!$userData['file_space'])
			{
				$userData['file_space'] = $GLOBALS['phpgw_info']['server']['vfs_default_account_size_number'] . "-" . $GLOBALS['phpgw_info']['server']['vfs_default_account_size_type'];
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

			$var = Array(
				'input_expires' => $GLOBALS['phpgw']->common->dateformatorder($_y,$_m,$_d,True),
				'lang_never'    => lang('Never'),
				'account_lid'   => '<input name="account_lid" value="' . $userData['account_lid'] . '">',
				'lang_homedir'  => $lang_homedir,
				'lang_shell'    => $lang_shell,
				'homedirectory' => $homedirectory,
				'loginshell'    => $loginshell,
				'account_status'    => '<input type="checkbox" name="account_status" value="A"'.($userData['status']?' checked':'').'>',
				'account_firstname' => '<input name="account_firstname" value="' . $userData['firstname'] . '">',
				'account_lastname'  => '<input name="account_lastname" value="' . $userData['lastname'] . '">',
				'account_passwd'    => $account_passwd,
				'account_passwd_2'  => $account_passwd_2,
				'account_file_space' => $account_file_space
			);

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

//			$allAccounts;
//			$userGroups;

			$groups_select = '';
			reset($allGroups);
			while (list($key,$value) = each($allGroups)) 
			{
				$groups_select .= '<option value="' . $value['account_id'] . '"';
				for ($i=0; $i<count($userGroups); $i++) 
				{
					/* print "Los1:".$userData["account_id"].$userGroups[$i]['account_id']." : ".$value['account_id']."<br>"; */
					if (@$userGroups[$i]['account_id'] == $value['account_id']) 
					{
						$groups_select .= ' selected';
					}
				}
				$groups_select .= '>' . $value['account_lid'] . '</option>'."\n";
			}

			/* create list of available apps */
			$i = 0;

			$apps = CreateObject('phpgwapi.applications',$_account_id);
			$db_perms = $apps->read_account_specific();

			@reset($GLOBALS['phpgw_info']['apps']);
			$availableApps = $GLOBALS['phpgw_info']['apps'];
			@asort($availableApps);
			@reset($availableApps);
			while (list($key,$application) = each($availableApps)) 
			{
				if ($application['enabled'] && $application['status'] != 3) 
				{
					$perm_display[$i]['appName']        = $key;
					$perm_display[$i]['translatedName'] = $application['title'];
					$i++;
				}
			}

			/* create apps output */
			$appRightsOutput = '';
//			@reset($perm_display);
			for ($i=0;$i<count($perm_display);$i++) 
			{
				if ($perm_display[$i]['translatedName'])
				{
					$part1 = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s></td>',
						$perm_display[$i]['translatedName'],
						$perm_display[$i]['appName'],
						($userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?' checked':''));
				}

				$i++;

				if ($perm_display[$i]['translatedName'])
				{
					$part2 = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s></td>',
						$perm_display[$i]['translatedName'],
						$perm_display[$i]['appName'],
						($userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?' checked':''));
				}
				else
				{
					$part2 = '<td colspan="2">&nbsp;</td>';
				}

				$appRightsOutput .= '<tr class="'.($i&2?'row_off':'row_on').'">'.$part1.$part2.'</tr>';
			}

			$var = Array(
				'groups_select'    => '<select name="account_groups[]" multiple>'."\n".$groups_select.'</select>'."\n",
				'permissions_list' => $appRightsOutput
			);
			$t->set_var($var);

			// create the menu on the left, if needed
//			$menuClass = CreateObject('admin.uimenuclass');
			// This is now using ExecMethod()
			$t->set_var('rows',ExecMethod('admin.uimenuclass.createHTMLCode','edit_user'));

			echo $t->fp('out','form');
		}

		function edit_group_managers($group_info,$_errors='')
		{
			if ($GLOBALS['phpgw']->acl->check('group_access',16,'admin'))
			{
				$this->list_groups();
				return False;
			}

			$accounts = CreateObject('phpgwapi.accounts',$group_info['account_id'],'u');
			$account_list = $accounts->member($group_info['account_id']);
			$user_list = '';
			while (list($key,$entry) = each($account_list))
			{
				$user_list .= '<option value="' . $entry['account_id'] . '"'
					. $group_info['account_managers'][intval($entry['account_id'])] . '>'
					. $GLOBALS['phpgw']->common->grab_owner_name($entry['account_id'])
					. '</option>'."\n";
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$t->set_unknowns('remove');

			$t->set_file(
				Array(
					'manager'	=>'group_manager.tpl'
				)
			);

			$t->set_block('manager','form','form');
			$t->set_block('manager','link_row','link_row');

			$var['th_bg'] = $GLOBALS['phpgw_info']['user']['theme']['th_bg'];
			$var['lang_group'] = lang('Group');
			$var['group_name'] = $group_info['account_name'];
			$var['tr_color1'] = $GLOBALS['phpgw_info']['user']['theme']['row_on'];
			$var['form_action'] = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.boaccounts.set_group_managers');
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

	}
?>
