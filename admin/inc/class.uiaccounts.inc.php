<?php
	/**************************************************************************\
	* phpGroupWare - account administration                                    *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uiaccounts
	{

		var $public_functions = array(
			'list_groups'	=> True,
			'list_users'	=> True,
			'add_group'	=> True,
			'add_user'	=> True,
			'delete_group'	=> True,
			'delete_user'	=> True,
			'edit_user'	=> True,
			'edit_group'	=> True,
			'view_user'	=> True
		);

		var $bo;
		var $nextmatchs;

		function uiaccounts()
		{
			$this->bo = createobject('admin.boaccounts');
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');
		}

		function row_action($action,$type,$account_id)
		{
			return '<a href="'.$GLOBALS['phpgw']->link('/index.php',Array(
					'menuaction'	=> 'admin.uiaccounts.'.$action.'_'.$type,
					'account_id'	=> $account_id
				)).'"> '.lang($action).' </a>';
		}

		function list_groups()
		{

			$GLOBALS['cd'] = ($GLOBALS['HTTP_GET_VARS']['cd']?$GLOBALS['HTTP_GET_VARS']['cd']:0);
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$p->set_file(
				array(
					'groups'   => 'groups.tpl'
				)
			);
			$p->set_block('groups','list','list');
			$p->set_block('groups','row','row');
			$p->set_block('groups','row_empty','row_empty');

			$total = $this->bo->account_total('g',$query);

			$url = $GLOBALS['phpgw']->link('/index.php');

		 	$var = Array(
		 		'th_bg'	=> $GLOBALS['phpgw_info']['theme']['th_bg'],
		 		'left_next_matchs'	=> $this->nextmatchs->left('/index.php',$start,$total,'menuaction=admin.uiaccounts.list_groups'),
		 		'right_next_matchs'	=> $this->nextmatchs->right('/admin/groups.php',$start,$total,'menuaction=admin.uiaccounts.list_groups'),
		 		'lang_groups'	=> lang('user groups'),
		 		'sort_name'		=> $this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('name'),'menuaction=admin.uiaccounts.list_groups'),
		 		'header_edit'	=> lang('Edit'),
		 		'header_delete'	=> lang('Delete')
		 	);
		 	$p->set_var($var);
 	
			$account_info = $GLOBALS['phpgw']->accounts->get_list('groups',$start,$sort, $order, $query, $total);

			if (!count($account_info))
			{
				$p->set_var('message',lang('No matchs found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				while (list($null,$account) = each($account_info))
				{
					$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
					$var = Array(
						'tr_color'	=> $tr_color,
						'group_name'	=> (!$account['account_lid']?'&nbsp;':$account['account_lid']),
						'edit_link'		=> $this->row_action('edit','group',$account['account_id']),
						'delete_link'	=> $this->row_action('delete','group',$account['account_id'])
					);
					$p->set_var($var);
					$p->parse('rows','row',True);

				}
			}
			$var = Array(
				'new_action'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.add_group'),
				'lang_add'		=> lang('add'),
				'search_action'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'lang_search'	=> lang('search')
			);
			$p->set_var($var);
			$p->pparse('out','list');
		}

		function list_users($param_cd='')
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',1,'admin'))
			{
				$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/admin/index.php'));
			}

			if(!$param_cd)
			{
				$cd = $param_cd;
			}
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);

			$p->set_file(
				Array(
					'accounts'   => 'accounts.tpl'
				)
			);
			$p->set_block('accounts','list','list');
			$p->set_block('accounts','row','row');
			$p->set_block('accounts','row_empty','row_empty');

			$total = $this->bo->account_total('u',$query);

			$url = $GLOBALS['phpgw']->link('/index.php');

			$var = Array(
				'bg_color'		=> $GLOBALS['phpgw_info']['theme']['bg_color'],
				'th_bg'			=> $GLOBALS['phpgw_info']['theme']['th_bg'],
				'left_next_matchs'	=> $this->nextmatchs->left($url,$start,$total,'menuaction=admin.uiaccounts.list_users'),
				'lang_user_accounts'	=> lang('user accounts'),
				'right_next_matchs'	=> $this->nextmatchs->right($url,$start,$total,'menuaction=admin.uiaccounts.list_users'),
				'lang_loginid'		=> $this->nextmatchs->show_sort_order($sort,'account_lid',$order,$url,lang('LoginID'),'menuaction=admin.uiaccounts.list_users'),
				'lang_lastname'		=> $this->nextmatchs->show_sort_order($sort,'account_lastname',$order,$url,lang('last name'),'menuaction=admin.uiaccounts.list_users'),
				'lang_firstname'	=> $this->nextmatchs->show_sort_order($sort,'account_firstname',$order,$url,lang('first name'),'menuaction=admin.uiaccounts.list_users'),
				'lang_edit'		=> lang('edit'),
				'lang_delete'		=> lang('delete'),
				'lang_view'		=> lang('view'),
				'actionurl'		=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.add_user'),
				'accounts_url'		=> $url,
				'lang_search'		=> lang('search')
			);
			$p->set_var($var);

			if (! $GLOBALS['phpgw']->acl->check('account_access',4,'admin'))
			{
				$p->set_var('input_add','<input type="submit" value="' . lang('Add') . '">');
			}

			if (! $GLOBALS['phpgw']->acl->check('account_access',2,'admin'))
			{
				$p->set_var('input_search',lang('Search') . '&nbsp;<input name="query">');
			}

			$account_info = $GLOBALS['phpgw']->accounts->get_list('accounts',$start,$sort,$order,$query);

			if (! count($account_info))
			{
				$p->set_var('message',lang('No matchs found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				if (! $GLOBALS['phpgw']->acl->check('account_access',8,'admin'))
				{
					$can_view = True;
				}

				if (! $GLOBALS['phpgw']->acl->check('account_access',16,'admin'))
				{
					$can_edit = True;
				}

				if (! $GLOBALS['phpgw']->acl->check('account_access',32,'admin'))
				{
					$can_delete = True;
				}

				while (list($null,$account) = each($account_info))
				{
					$this->nextmatchs->template_alternate_row_color($p);

					$var = array(
						'row_loginid'   => $account['account_lid'],
						'row_firstname' => (!$account['account_firstname']?'&nbsp':$account['account_firstname']),
						'row_lastname'  => (!$account['account_lastname']?'&nbsp':$account['account_lastname'])
					);
					$p->set_var($var);

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
						$p->set_var('row_delete',($GLOBALS['phpgw_info']['user']['userid'] != $account['account_lid']?$this->row_action('delete','user',$account['account_id']):'&nbsp'));
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
			$group_info = Array(
				'account_id'		=> $GLOBALS['HTTP_GET_VARS']['account_id'],
				'account_name'	=> '',
				'account_user'	=> Array(),
				'account_apps'	=> Array()
				);
			$this->create_edit_group($group_info);
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
			if (!@isset($GLOBALS['HTTP_GET_VARS']['account_id']) || !@$GLOBALS['HTTP_GET_VARS']['account_id'])
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$p->set_file(
				Array(
					'body' => 'delete_common.tpl',
					'message_row' => 'message_row.tpl',
					'form_button'		=>	'form_button_script.tpl'
				)
			);

			$p->set_var('message_display',lang('Are you sure you want to delete this group ?'));
			$p->parse('messages','message_row');

			$old_group_list = $GLOBALS['phpgw']->acl->get_ids_for_location(intval($GLOBALS['HTTP_GET_VARS']['account_id']),1,'phpgw_group');

			if($old_group_list)
			{
				$group_name = $GLOBALS['phpgw']->accounts->id2name($GLOBALS['HTTP_GET_VARS']['account_id']);

				$p->set_var('message_display','<br>');
				$p->parse('messages','message_row',True);

				$user_list = '';
				while (list(,$id) = each($old_group_list))
				{
					$user_list .= '<a href="' . $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> 'admin.uiaccounts.edit_user',
								'account_id'	=> $id
							)
						) . '">' . $GLOBALS['phpgw']->common->grab_owner_name($id) . '</a><br>';
				}
				$p->set_var('message_display',$user_list);
				$p->parse('messages','message_row',True);

				$p->set_var('message_display',lang("Sorry, the above users are still a member of the group x",$group_name)
					. '.<br>' . lang('They must be removed before you can continue'). '.<br>' . lang('Remove all users from this group').'?');
				$p->parse('messages','message_row',True);
			}

			$var = Array(
				'submit_button'		=> lang('Submit'),
				'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.boaccounts.delete_group'),
				'action_text_button'	=> lang('Yes'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> '<input type="hidden" name="account_id" value="'.$GLOBALS['HTTP_GET_VARS']['account_id'].'">'."\n"
			);
			$p->set_var($var);
			$p->parse('yes','form_button');


			$var = Array(
				'submit_button'		=> lang('Submit'),
				'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
				'action_text_button'	=> ' '.lang('No'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$p->parse('no','form_button');

			$p->pparse('out','body');
		}

		function delete_user()
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',32,'admin') || $GLOBALS['phpgw_info']['user']['account_id'] == $GLOBALS['HTTP_GET_VARS']['account_id'])
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
				'form_action'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.boaccounts.delete_user'),
				'account_id'	=> $GLOBALS['HTTP_GET_VARS']['account_id']
			);

			// the account can have special chars/white spaces, if it is a ldap dn
			$account_id = rawurlencode($GLOBALS['HTTP_GET_VARS']['account_id']);
		
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

		function edit_group($cd='',$account_id='')
		{
			$cdid = $cd;
			settype($cd,'integer');
			$cd = ($GLOBALS['HTTP_GET_VARS']['cd']?$GLOBALS['HTTP_GET_VARS']['cd']:intval($cdid));

			$accountid = $account_id;
			settype($account_id,'integer');
			$account_id = ($GLOBALS['HTTP_GET_VARS']['account_id']?$GLOBALS['HTTP_GET_VARS']['account_id']:intval($accountid));
			
			// todo
			// not needed if i use the same file for new users too
			if (!$account_id)
			{
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			}
			else
			{
				$group_info = Array(
					'account_id'		=> intval($GLOBALS['HTTP_GET_VARS']['account_id']),
					'account_name'	=> $GLOBALS['phpgw']->accounts->id2name($GLOBALS['HTTP_GET_VARS']['account_id']),
					'account_user'	=> $this->bo->load_group_users($GLOBALS['HTTP_GET_VARS']['account_id']),
					'account_apps'	=> $this->bo->load_group_apps($GLOBALS['HTTP_GET_VARS']['account_id'])
				);

				$this->create_edit_group($group_info);
			}	
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
			$cd = ($GLOBALS['HTTP_GET_VARS']['cd']?$GLOBALS['HTTP_GET_VARS']['cd']:intval($cdid));

			$accountid = $account_id;
			settype($account_id,'integer');
			$account_id = ($GLOBALS['HTTP_GET_VARS']['account_id']?$GLOBALS['HTTP_GET_VARS']['account_id']:intval($accountid));
			
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
			if ($GLOBALS['phpgw']->acl->check('account_access',8,'admin') || ! $GLOBALS['HTTP_GET_VARS']['account_id'])
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
				'th_bg'	=> $GLOBALS['phpgw_info']['theme']['th_bg'],
				'tr_color1'	=> $GLOBALS['phpgw_info']['theme']['row_on'],
				'tr_color2'	=> $GLOBALS['phpgw_info']['theme']['row_off'],
				'lang_action'	=> lang('View user account'),
				'lang_loginid'	=> lang('LoginID'),
				'lang_account_active'	=> lang('Account active'),
				'lang_password'	=> lang('Password'),
				'lang_reenter_password'	=> lang('Re-Enter Password'),
				'lang_lastname'	=> lang('Last Name'),
				'lang_groups'	=> lang('Groups'),
				'lang_firstname'	=> lang('First Name'),
				'lang_lastlogin'	=> lang('Last login'),
				'lang_lastloginfrom'	=> lang('Last login from'),
				'lang_expires'	=> lang('Expires')
			);

			$t->parse('password_fields','form_logininfo',True);

			$account = CreateObject('phpgwapi.accounts',intval($GLOBALS['HTTP_GET_VARS']['account_id']));
			$userData = $account->read_repository();

			$var['account_lid']		= $userData['account_lid'];
			$var['account_firstname']	= $userData['firstname'];
			$var['account_lastname']	= $userData['lastname'];

			if ($userData['status'])
			{
				$var['account_status']	= lang('Enabled');
			}
			else
			{
				$var['account_status']	= '<b>' . lang('Disabled') . '</b>';
			}

			// Last login time
			if ($userData['lastlogin'])
			{
				$var['account_lastlogin']	= $GLOBALS['phpgw']->common->show_date($userData['lastlogin']);
			}
			else
			{
				$var['account_lastlogin']	= lang('Never');
			}

			// Last login IP
			if ($userData['lastloginfrom'])
			{
				$var['account_lastloginfrom']	= $userData['lastloginfrom'];
			}
			else
			{
				$var['account_lastloginfrom'] = lang('Never');
			}

			// Account expires
			if ($userData['expires'] != -1)
			{
				$var['input_expires']	= $GLOBALS['phpgw']->common->show_date($userData['expires']);
			}
			else
			{
				$var['input_expires'] = lang('Never');
			}

			// Find out which groups they are members of
			$usergroups = $account->membership(intval($GLOBALS['HTTP_GET_VARS']['account_id']));
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
			$account_status	     = $userData['account_status'];

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
			$apps = CreateObject('phpgwapi.applications',intval($GLOBALS['HTTP_GET_VARS']['account_id']));
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
			
				$appRightsOutput .= sprintf("<tr bgcolor=\"%s\">$part1$part2</tr>\n",$GLOBALS['phpgw_info']['theme']['row_on']);
			}

			$var['permissions_list'] = $appRightsOutput;

			// create the menu on the left, if needed
			$menuClass = CreateObject('admin.uimenuclass');
			$var['rows'] = $menuClass->createHTMLCode('view_user');
			$t->set_var($var);
			$t->pfp('out','form');
		}

		function create_edit_group($group_info,$_errors='')
		{
			$apps_with_acl = Array(
				'addressbook'	=> True,
				'todo'		=> True,
				'calendar'	=> True,
				'notes'		=> True,
				'projects'	=> True,
				'phonelog'	=> True,
				'infolog'	=> True,
				'phpwebhosting'	=> True
			);

			$sbox = createobject('phpgwapi.sbox');

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$p->set_file(
				Array(
					'form' => 'group_form.tpl'
				)
			);

			$accounts = CreateObject('phpgwapi.accounts',$group_info['account_id']);
			$account_list = $accounts->get_list('accounts');
			$account_num = count($account_list);

			$user_list = '';
			while (list($key,$entry) = each($account_list))
			{
				$user_list .= '<option value="' . $entry['account_id'] . '"'
					. $group_info['account_user'][intval($entry['account_id'])] . '>'
					. $GLOBALS['phpgw']->common->display_fullname(
						$entry['account_lid'],
						$entry['account_firstname'],
						$entry['account_lastname'])
					. '</option>'."\n";
			}

			$var = Array(
				'form_action'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=admin.boaccounts.'.($group_info['account_id']?'edit':'add').'_group'),
				'hidden_vars'	=> '<input type="hidden" name="account_id" value="' . $group_info['account_id'] . '">',
				'lang_group_name'		=> lang('group name'),
				'group_name_value'	=> $group_info['account_name'],
				'lang_include_user'	=> lang('Select users for inclusion'),
				'error'	=> (!$_errors?'':'<center>'.$GLOBALS['phpgw']->common->error_list($_errors).'</center>'),
				'select_size'	=> ($account_num < 5?$account_num:5),
				'user_list'		=> $user_list,
				'lang_permissions'	=> lang('Permissions this group has')
			);
			$p->set_var($var);

			$group_repository = $accounts->read_repository ();
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
					$perm_display[] = Array(
						$permission[0],
						$permission[1]['title']
					);
				}
			}

			$perm_html = '';
			$tr_color = $GLOBALS['phpgw_info']['theme']['row_off'];
			for ($i=0;$perm_display[$i][1];$i++)
			{
				$app = $perm_display[$i][0];
				if(!($i & 1))
				{
					$tr_color = $this->nextmatchs->alternate_row_color();
					$perm_html .= '<tr bgcolor="'.$tr_color.'">';
				}
				$perm_html .= '<td width="40%">' . lang($perm_display[$i][1]) . '</td>'
					. '<td width="5%"><input type="checkbox" name="account_apps['
					. $perm_display[$i][0] . ']" value="True"'.($group_info['account_apps'][$app]?' checked':'').'></td><td width="5%">'
					.($apps_with_acl[$app] && $group_info['account_id']?'<a href="'.$GLOBALS['phpgw']->link('/preferences/acl_preferences.php','acl_app='.$app.'&owner='.$group_info['account_id'])
					.'" target="_blank"><img src="'.$GLOBALS['phpgw']->common->image('admin','dot.gif').'" border="0" hspace="3" align="absmiddle" alt="'
					.lang('Grant Access').'"></a>':'&nbsp;').'</td>'.($i & 1?'</tr>':'')."\n";
			}
			if($i & 1)
			{
				$perm_html .= '<td colspan="4">&nbsp;</td></tr>';
			}

			$var = Array(
				'permissions_list'	=> $perm_html,
				'lang_submit_button'	=> lang('submit changes')
			);
			$p->set_var($var);
			$p->pfp('out','form');
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
					$account = CreateObject('phpgwapi.accounts',intval($_account_id));
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
					$userData['account_expires_month'] = date('m',$userData['expires']);
					$userData['account_expires_day']   = date('d',$userData['expires']);
					$userData['account_expires_year']  = date('Y',$userData['expires']);
				}
			}
			$page_params['menuaction'] = 'admin.boaccounts.'.($_account_id?'edit':'add').'_user';
			if($_account_id)
			{
				$page_params['account_id']	= $_account_id;
				$page_params['old_loginid']	= rawurlencode($userData['account_lid']);
			}

			$var = Array(
				'form_action'		=> $GLOBALS['phpgw']->link('/index.php',$page_params),
				'error_messages'	=> (!$_errors?'':'<center>'.$GLOBALS['phpgw']->common->error_list($_errors).'</center>'),
				'th_bg'			=> $GLOBALS['phpgw_info']['theme']['th_bg'],
				'tr_color1'		=> $GLOBALS['phpgw_info']['theme']['row_on'],
				'tr_color2'		=> $GLOBALS['phpgw_info']['theme']['row_off'],
				'lang_action'		=> ($_account_id?lang('Edit user account'):lang('Add new account')),
				'lang_loginid'		=> lang('LoginID'),
				'lang_account_active'	=> lang('Account active'),
				'lang_password'	=> lang('Password'),
				'lang_reenter_password'	=> lang('Re-Enter Password'),
				'lang_lastname'	=> lang('Last Name'),
				'lang_groups'		=> lang('Groups'),
				'lang_expires'		=> lang('Expires'),
				'lang_firstname'	=> lang('First Name'),
				'lang_button'		=> ($_account_id?lang('Save'):lang('Add'))
			/* 'lang_file_space'	=> lang('File Space') */
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
				'lang_file_space'	=> 'File space',
				'account_file_space'	=> $account_file_space,
				'account_file_space_select'	=> $account_file_space_select
			);
			$t->set_var($var);
		*/

			$var = Array(
				'input_expires'	=> $GLOBALS['phpgw']->common->dateformatorder($_y,$_m,$_d,True),
				'account_lid'	=> '<input name="account_lid" value="' . $userData['account_lid'] . '">',
				'lang_homedir'	=> $lang_homedir,
				'lang_shell'	=> $lang_shell,
				'homedirectory'	=> $homedirectory,
				'loginshell'	=> $loginshell,
				'account_status'		=> '<input type="checkbox" name="account_status" value="A"'.($userData['status']?' checked':'').'>',
				'account_firstname'	=> '<input name="account_firstname" value="' . $userData['firstname'] . '">',
				'account_lastname'	=> '<input name="account_lastname" value="' . $userData['lastname'] . '">',
				'account_passwd'	=> $account_passwd,
				'account_passwd_2'	=> $account_passwd_2,
				'account_file_space'	=> $account_file_space
			);
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
					if ($userGroups[$i]['account_id'] == $value['account_id']) 
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
						lang($perm_display[$i]['translatedName']),
						$perm_display[$i]['appName'],
						($userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?' checked':''));
				}

				$i++;			

				if ($perm_display[$i]['translatedName'])
				{
					$part2 = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s></td>',
						lang($perm_display[$i]['translatedName']),
						$perm_display[$i]['appName'],
						($userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]?' checked':''));
				}
				else
				{
					$part2 = '<td colspan="2">&nbsp;</td>';
				}
			
				$appRightsOutput .= sprintf('<tr bgcolor="%s">%s%s</tr>',$GLOBALS['phpgw_info']['theme']['row_on'], $part1, $part2);
			}

			$var = Array(
				'groups_select'		=> '<select name="account_groups[]" multiple>'."\n".$groups_select.'</select>'."\n",
				'permissions_list'	=> $appRightsOutput
			);
			$t->set_var($var);

			// create the menu on the left, if needed		
			$menuClass = CreateObject('admin.uimenuclass');
			$t->set_var('rows',$menuClass->createHTMLCode('edit_user'));

			echo $t->fp('out','form');
		}
	}
?>
