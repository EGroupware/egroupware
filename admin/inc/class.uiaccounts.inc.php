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
		var $public_functions = array
		(
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
			'edit_view_user_hook' => True,
			'group_manager'		=> True,
			'accounts_popup'	=> True
		);

		var $bo;
		var $nextmatchs;

		function uiaccounts()
		{
			$this->bo = createobject('admin.boaccounts');
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');
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
				$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/admin/index.php'));
			}

			$query = (isset($_POST['query'])?$_POST['query']:'');

			$GLOBALS['cd'] = ($_GET['cd']?$_GET['cd']:0);
			
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

			$url = $GLOBALS['phpgw']->link('/index.php');

			$var = Array(
				'th_bg'             => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'left_next_matchs'  => $this->nextmatchs->left('/index.php',$start,$total,'menuaction=admin.uiaccounts.list_groups'),
				'right_next_matchs' => $this->nextmatchs->right('/admin/groups.php',$start,$total,'menuaction=admin.uiaccounts.list_groups'),
				'lang_groups'   => lang('user groups'),
				'sort_name'     => $this->nextmatchs->show_sort_order($sort,'account_lid',$order,'/index.php',lang('name'),'menuaction=admin.uiaccounts.list_groups'),
				'header_edit'   => lang('Edit'),
				'header_delete' => lang('Delete')
			);
			$p->set_var($var);

			if (!count($account_info) || !$total)
			{
				$p->set_var('message',lang('No matches found'));
				$p->parse('rows','row_empty',True);
			}
			else
			{
				if (! $GLOBALS['phpgw']->acl->check('group_access',8,'admin'))
				{
					$can_view = True;
				}

				if (! $GLOBALS['phpgw']->acl->check('group_access',16,'admin'))
				{
					$can_edit = True;
				}

				if (! $GLOBALS['phpgw']->acl->check('group_access',32,'admin'))
				{
					$can_delete = True;
				}

				while (list($null,$account) = each($account_info))
				{
					$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
					$var = Array(
						'tr_color'    => $tr_color,
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
			$var = Array(
				'new_action'    => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.add_group'),
				'search_action' => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups')
			);
			$p->set_var($var);

			if (! $GLOBALS['phpgw']->acl->check('group_access',4,'admin'))
			{
				$p->set_var('input_add','<input type="submit" value="' . lang('Add') . '">');
			}

			if (! $GLOBALS['phpgw']->acl->check('group_access',2,'admin'))
			{
				$p->set_var('input_search',lang('Search') . '&nbsp;<input name="query">');
			}

			$p->pfp('out','list');
		}

		function list_users($param_cd='')
		{
			if ($GLOBALS['phpgw']->acl->check('account_access',1,'admin'))
			{
				$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/admin/index.php'));
			}

			if($param_cd)
			{
				$cd = $param_cd;
			}
			
			if(isset($_POST['query']))
			{
				$GLOBALS['query'] = $_POST['query'];
			}
			
			if(isset($_POST['start']))
			{
				$start = intval($_POST['start']);
			}
			else
			{
				$start = 0;
			}

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
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);

			$p->set_file(
				Array(
					'accounts' => 'accounts.tpl'
				)
			);
			$p->set_block('accounts','list','list');
			$p->set_block('accounts','row','row');
			$p->set_block('accounts','row_empty','row_empty');

			if ($GLOBALS['phpgw']->acl->check('account_access',2,'admin'))
			{
				$account_info = $GLOBALS['phpgw']->accounts->get_list('accounts',$start,$sort,$order,$GLOBALS['query']);
				$total = $GLOBALS['phpgw']->accounts->total;
			}
			else
			{
				$account_info = $GLOBALS['phpgw']->accounts->get_list('accounts',$start,$sort,$order,$GLOBALS['query']);
				$total = $GLOBALS['phpgw']->accounts->total;
			}
			
			$url = $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users');

			$var = Array(
				'bg_color' => $GLOBALS['phpgw_info']['theme']['bg_color'],
				'th_bg'    => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'left_next_matchs'   => $this->nextmatchs->left($url,$start,$total,'menuaction=admin.uiaccounts.list_users'),
				'lang_user_accounts' => lang('%1 - %2 of %3 user accounts',$start+1,$start+count($account_info),$total),
				'right_next_matchs'  => $this->nextmatchs->right($url,$start,$total,'menuaction=admin.uiaccounts.list_users'),
				'lang_loginid'       => $this->nextmatchs->show_sort_order($sort,'account_lid',$order,$url,lang('LoginID')),
				'lang_lastname'      => $this->nextmatchs->show_sort_order($sort,'account_lastname',$order,$url,lang('last name')),
				'lang_firstname'     => $this->nextmatchs->show_sort_order($sort,'account_firstname',$order,$url,lang('first name')),
				'lang_edit'    => lang('edit'),
				'lang_delete'  => lang('delete'),
				'lang_view'    => lang('view'),
				'accounts_url' => $url,
				'lang_search'  => lang('search')
			);
			$p->set_var($var);
			
			if (! $GLOBALS['phpgw']->acl->check('account_access',4,'admin'))
			{
				$p->set_var('new_action',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.add_user'));
				$p->set_var('input_add','<input type="submit" value="' . lang('Add') . '">');
			}

			if (! $GLOBALS['phpgw']->acl->check('account_access',2,'admin'))
			{
				$p->set_var('input_search',lang('Search') . '&nbsp;<input type="text" name="query" value='.$GLOBALS['query'].'>');
			}

			if (!count($account_info) || !$total)
			{
				$p->set_var('message',lang('No matches found'));
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
			if ($GLOBALS['phpgw']->acl->check('group_access',4,'admin'))
			{
				$this->list_groups();
				return False;
			}

			$group_info = Array(
				'account_id'   => $_GET['account_id'],
				'account_name' => '',
				'account_user' => Array(),
				'account_apps' => Array()
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
			if ($_POST['no'] || $_POST['yes'] || !@isset($_GET['account_id']) || !@$_GET['account_id'] || $GLOBALS['phpgw']->acl->check('group_access',32,'admin'))
			{
				if ($_POST['yes'])
				{
					$this->bo->delete_group();
				}
				$this->list_groups();
				return False;
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$p->set_file(
				Array(
					'body' => 'delete_common.tpl',
					'message_row' => 'message_row.tpl',
					'form_button' => 'form_button_script.tpl'
				)
			);

			$p->set_var('message_display',lang('Are you sure you want to delete this group ?'));
			$p->parse('messages','message_row');

			$old_group_list = $GLOBALS['phpgw']->acl->get_ids_for_location(intval($_GET['account_id']),1,'phpgw_group');

			if($old_group_list)
			{
				$group_name = $GLOBALS['phpgw']->accounts->id2name($_GET['account_id']);

				$p->set_var('message_display','<br>');
				$p->parse('messages','message_row',True);

				$user_list = '';
				while (list(,$id) = each($old_group_list))
				{
					$user_list .= '<a href="' . $GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction' => 'admin.uiaccounts.edit_user',
							'account_id' => $id
						)
					) . '">' . $GLOBALS['phpgw']->common->grab_owner_name($id) . '</a><br>';
				}
				$p->set_var('message_display',$user_list);
				$p->parse('messages','message_row',True);

				$p->set_var('message_display',lang("Sorry, the above users are still a member of the group %1",$group_name)
					. '.<br>' . lang('They must be removed before you can continue'). '.<br>' . lang('Remove all users from this group').'?');
				$p->parse('messages','message_row',True);
			}

			$var = Array(
				'form_action' => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.delete_group'),
				'hidden_vars' => '<input type="hidden" name="account_id" value="'.$_GET['account_id'].'">',
				'yes'         => lang('Yes'),
				'no'          => lang('No')
			);
			$p->set_var($var);
/*
			$p->parse('yes','form_button');


			$var = Array(
				'submit_button' => lang('Submit'),
				'action_url_button'     => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'),
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

		function edit_group($cd='',$account_id='')
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
					'account_user' => $this->bo->load_group_users($_GET['account_id']),
					'account_apps' => $this->bo->load_group_apps($_GET['account_id'])
				);

				$this->create_edit_group($group_info);
			}
		}

		function edit_view_user_hook()
		{
			if (!$GLOBALS['phpgw']->acl->check('current_sessions_access',1,'admin'))	// no rights to view
			{
				$GLOBALS['menuData'][] = array(
					'description' => 'Login History',
					'url'         => '/index.php',
					'extradata'   => 'menuaction=admin.uiaccess_history.list_history'
				);
			}
			// not sure if this realy belongs here, or only in edit_user
			if ($_GET['account_id'] && 	// can't set it on add
			    !$GLOBALS['phpgw']->acl->check('account_access',64,'admin'))	// no rights to set ACL-rights
			{
				$GLOBALS['menuData'][] = array(
					'description' => 'ACL Rights',
					'url'         => '/index.php',
					'extradata'   => 'menuaction=admin.uiaclmanager.list_apps'
				);
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
			$cd = ($_GET['cd']?$_GET['cd']:intval($cdid));

			$accountid = $account_id;
			settype($account_id,'integer');
			$account_id = intval($_GET['account_id'] ? $_GET['account_id'] : $accountid);
			
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
				'th_bg'        => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'tr_color1'    => $GLOBALS['phpgw_info']['theme']['row_on'],
				'tr_color2'    => $GLOBALS['phpgw_info']['theme']['row_off'],
				'lang_action'  => lang('View user account'),
				'lang_loginid' => lang('LoginID'),
				'lang_account_active'   => lang('Account active'),
				'lang_lastname'      => lang('Last Name'),
				'lang_groups'        => lang('Groups'),
				'lang_anonymous'     => lang('Anonymous user (not shown in list sessions)'),
				'lang_changepassword'=> lang('Can change password'),
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

			$acl = CreateObject('phpgwapi.acl',intval($_GET['account_id']));
			$var['anonymous']         = $acl->check('anonymous',1,'phpgwapi') ? '&nbsp;&nbsp;X' : '&nbsp;';
			$var['changepassword']    = $acl->check('changepassword',0xFFFF,'preferences') ? '&nbsp;&nbsp;X' : '&nbsp;';
			unset($acl);

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
				$var['groups_select'] = implode(', ',$group_names);
			}

			$account_lastlogin      = $userData['account_lastlogin'];
			$account_lastloginfrom  = $userData['account_lastloginfrom'];
			$account_status         = $userData['account_status'];

			// create list of available app
			$i = 0;
		
			$availableApps = $GLOBALS['phpgw_info']['apps'];
			@asort($availableApps);
			@reset($availableApps);
			foreach($availableApps as $app => $data) 
			{
				if ($data['enabled'] && $data['status'] != 2) 
				{
					$perm_display[$i]['appName'] = $app;
					$perm_display[$i]['title']   = $data['title'];
					$i++;
				}
			}

			// create apps output
			$apps = CreateObject('phpgwapi.applications',intval($_GET['account_id']));
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

				$appRightsOutput .= sprintf("<tr bgcolor=\"%s\">$part1$part2</tr>\n",$GLOBALS['phpgw_info']['theme']['row_on']);
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

		function accounts_popup()
		{
			$GLOBALS['phpgw']->accounts->accounts_popup('admin');
		}

		function create_edit_group($group_info,$_errors='')
		{
			// Maybe we should list this in setup/setup.inc.php and put it into the
			// phpgw_applications table ? (jengo)
			$apps_with_acl = array(
				'addressbook' => True,
				'todo'        => True,
				'calendar'    => True,
				'notes'       => True,
				'projects'    => True,
				'phonelog'    => True,
				'infolog'     => True,
				'filemanager' => True,
				'tts'         => True,
				'bookmarks'   => True,
				'img'         => True,
				'netsaint'    => True,
				'inv'         => True
			);

			$sbox = createobject('phpgwapi.sbox');

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
			$p->set_file(Array('edit' => 'group_form.tpl'));
			$p->set_block('edit','select');
			$p->set_block('edit','popwin');

			$accounts = CreateObject('phpgwapi.accounts',$group_info['account_id'],'u');

			if ($GLOBALS['phpgw_info']['user']['preferences']['common']['account_selection'] == 'popup')
			{
				$p->set_var('accounts_link',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.accounts_popup'));
				$p->set_var('lang_open_popup',lang('open popup window'));

				while(is_array($group_info['account_user']) && list($ac_id,) = each($group_info['account_user']))
				{
					$ac_name = $GLOBALS['phpgw']->accounts->get_account_data($ac_id);

					$user_list .= '<option value="' . $ac_id . '" selected>'
								. $GLOBALS['phpgw']->common->display_fullname($ac_name[$ac_id]['lid'],$ac_name[$ac_id]['firstname'],$ac_name[$ac_id]['lastname'])
								. '</option>'."\n";
				}
				$account_num = count($group_info['account_user']);
				$p->set_var('select_size',($account_num < 25?$account_num:25));
				$p->set_var('user_list',$user_list);
				$p->fp('accounts','popwin',True);
			}
			else
			{
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
				$p->set_var('select_size',($account_num < 7?$account_num:7));
				$p->set_var('user_list',$user_list);
				$p->fp('accounts','select',True);
			}

			$var = Array(
				'form_action'       => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.boaccounts.'.($group_info['account_id']?'edit':'add').'_group'),
				'hidden_vars'       => '<input type="hidden" name="account_id" value="' . $group_info['account_id'] . '">',
				'lang_group_name'   => lang('group name'),
				'group_name_value'  => $group_info['account_name'],
				'lang_include_user' => lang('Select users for inclusion'),
				'error'             => (!$_errors?'':'<center>'.$GLOBALS['phpgw']->common->error_list($_errors).'</center>'),
				'lang_permissions'  => lang('Permissions this group has')
			);
			$p->set_var($var);

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
					$perm_display[] = Array(
						$permission[0],
						$permission[1]['title']
					);
				}
			}

			$perm_html = '<td>'.lang('Application').'</td><td>&nbsp;</td><td>'.lang('ACL').'</td>';
			$perm_html = '<tr bgcolor="'.$GLOBALS['phpgw_info']['theme']['th_bg'].'">'.
				$perm_html.$perm_html."</tr>\n";
			
			$tr_color = $GLOBALS['phpgw_info']['theme']['row_off'];
			for ($i=0;$i < count($perm_display);$i++)
			{
				$app = $perm_display[$i][0];
				if(!($i & 1))
				{
					$tr_color = $this->nextmatchs->alternate_row_color();
					$perm_html .= '<tr bgcolor="'.$tr_color.'">';
				}
				$perm_html .= '<td width="40%">' . $perm_display[$i][1] . '</td>'
					. '<td width="5%"><input type="checkbox" name="account_apps['
					. $perm_display[$i][0] . ']" value="True"'.($group_info['account_apps'][$app]?' checked':'').'></td><td width="5%" align="center">'
					. ($apps_with_acl[$app] && $group_info['account_id']?'<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$app.'&owner='.$group_info['account_id'])
					. '" target="_blank"><img src="'.$GLOBALS['phpgw']->common->image('admin','dot').'" border="0" hspace="3" align="absmiddle" alt="'
					. lang('Grant Access').'"></a>':'&nbsp;').'</td>'.($i & 1?'</tr>':'')."\n";
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

		function create_edit_user($_account_id,$_userData='',$_errors='')
		{
			$sbox = createobject('phpgwapi.sbox');
			$jscal = CreateObject('phpgwapi.jscalendar');

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
				$userData['account_firstname']	= $userData['firstname'];
				$userData['account_lastname']	= $userData['lastname'];
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
					$acl = CreateObject('phpgwapi.acl',$_account_id);
					$acl->read_repository();
					$userData['anonymous'] = $acl->check('anonymous',1,'phpgwapi');
					$userData['changepassword'] = $acl->check('changepassword',0xFFFF,'preferences');
					unset($acl);
				}
				else
				{
					$account = CreateObject('phpgwapi.accounts');
					$userData = Array();
					$userData['status'] = 'A';
					$userGroups = Array();
					$userData['anonymous'] = False;
					$userData['changepassword'] = True;
				}
				$allGroups = $account->get_list('groups');
			}
			$page_params['menuaction'] = 'admin.boaccounts.'.($_account_id?'edit':'add').'_user';
			if($_account_id)
			{
				$page_params['account_id']  = $_account_id;
				$page_params['old_loginid'] = rawurlencode($userData['account_lid']);
			}

			$var = Array(
				'form_action'    => $GLOBALS['phpgw']->link('/index.php',$page_params),
				'error_messages' => (!$_errors?'':'<center>'.$GLOBALS['phpgw']->common->error_list($_errors).'</center>'),
				'th_bg'          => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'tr_color1'      => $GLOBALS['phpgw_info']['theme']['row_on'],
				'tr_color2'      => $GLOBALS['phpgw_info']['theme']['row_off'],
				'lang_action'    => ($_account_id?lang('Edit user account'):lang('Add new account')),
				'lang_loginid'   => lang('LoginID'),
				'lang_account_active' => lang('Account active'),
				'lang_password'  => lang('Password'),
				'lang_reenter_password' => lang('Re-Enter Password'),
				'lang_lastname'  => lang('Last Name'),
				'lang_groups'    => lang('Groups'),
				'lang_primary_group'    => lang('primary Group'),
				'lang_expires'   => lang('Expires'),
				'lang_firstname' => lang('First Name'),
				'lang_anonymous' => lang('Anonymous User (not shown in list sessions)'),
				'lang_changepassword' => lang('Can change password'),
				'lang_button'    => ($_account_id?lang('Save'):lang('Add'))
			/* 'lang_file_space' => lang('File Space') */
			);
			$t->set_var($var);
			$t->parse('form_buttons','form_buttons_',True);

			if ($GLOBALS['phpgw_info']['server']['ldap_extra_attributes']) {
				$lang_homedir = lang('home directory');
				$lang_shell = lang('login shell');
				$homedirectory = '<input name="homedirectory" value="'
					. ($_account_id?$userData['homedirectory']:$GLOBALS['phpgw_info']['server']['ldap_account_home'].$account_lid)
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
				'input_expires' => $jscal->input('expires',$userData['expires']<0?'':($userData['expires']?$userData['expires']:time()+(60*60*24*7))),
				'lang_never'    => lang('Never'),
				'account_lid'   => '<input name="account_lid" value="' . $userData['account_lid'] . '">',
				'lang_homedir'  => $lang_homedir,
				'lang_shell'    => $lang_shell,
				'homedirectory' => $homedirectory,
				'loginshell'    => $loginshell,
				'anonymous'     => '<input type="checkbox" name="anonymous" value="1"'.($userData['anonymous'] ? ' checked' : '').'>',
				'changepassword'=> '<input type="checkbox" name="changepassword" value="1"'.($userData['changepassword'] ? ' checked' : '').'>',
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

			$groups_select		= '';
			$primary_group_select	= '';
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

			if (!$userData['account_primary_group'])
			{
				$userData['account_primary_group'] = @$userGroups[0]['account_id'] ? @$userGroups[0]['account_id'] : $account->name2id('Default');
			}
			foreach($allGroups as $key => $value)
			{
#				print "<br>$key =>";
#				_debug_array($userGroups);
				$primary_group_select .= '<option value="' . $value['account_id'] . '"';
				#print $value['account_id'].''.$value['account_primary_group']
				if ($value['account_id'] == $userData['account_primary_group'])
				{
					$primary_group_select .= ' selected';
				}
				$primary_group_select .= '>' . $value['account_lid'] . '</option>'."\n";
			}

			/* create list of available apps */
			$apps = CreateObject('phpgwapi.applications',$_account_id);
			$db_perms = $apps->read_account_specific();

			$availableApps = $GLOBALS['phpgw_info']['apps'];
			uasort($availableApps,create_function('$a,$b','return strcasecmp($a["title"],$b["title"]);'));

			$appRightsOutput = '';
			$i = 0;
			foreach($availableApps as $app => $data)
			{
				if (!$data['enabled'] || $data['status'] == 3)
				{
					continue;
				}
				$checked = (@$userData['account_permissions'][$app] || @$db_perms[$app]) && $_account_id ? ' checked' : '';
				$part[$i&1] = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s></td>',
					$data['title'],$app,$checked);

				if ($i & 1)
				{
					$appRightsOutput .= sprintf('<tr bgcolor="%s">%s%s</tr>',$this->nextmatchs->alternate_row_color(), $part[0], $part[1]);
				}
				++$i;
			}
			if ($i & 1)
			{
				$part[1] = '<td colspan="2">&nbsp;</td>';
				$appRightsOutput .= sprintf('<tr bgcolor="%s">%s%s</tr>',$this->nextmatchs->alternate_row_color(), $part[0], $part[1]);
			}

			$var = Array(
				'groups_select'	
					=> '<select name="account_groups[]" multiple>'."\n".$groups_select.'</select>'."\n",
				'primary_group_select'    
					=> '<select name="account_primary_group">'."\n".$primary_group_select.'</select>'."\n",
				'permissions_list' 
					=> $appRightsOutput
			);
			$t->set_var($var);

			// create the menu on the left, if needed
//			$menuClass = CreateObject('admin.uimenuclass');
			// This is now using ExecMethod()
			$GLOBALS['account_id'] = $_account_id;
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
