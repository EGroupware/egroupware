<?php
	/**************************************************************************\
	* phpGroupWare - Account Administration                                    *
	* http://www.phpgroupware.org                                              *
	* Written by coreteam <phpgroupware-developers@gnu.org>                    *
	* -----------------------------------------------------                    *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	class boaccounts
	{
		var $so;
		var $public_functions = array
		(
			'add_group'				=> True,
			'delete_group'			=> True,
			'delete_user'			=> True,
			'edit_group'			=> True,
			'save_user'				=> True,
			'set_group_managers'	=> True
		);

		var $xml_functions = array();

		var $soap_functions = array
		(
			'add_user'	=> array
			(
				'in'	=> array('int','struct'),
				'out'	=> array()
			)
		);

		function boaccounts()
		{
			$this->so = createobject('admin.soaccounts');
		}

		function DONTlist_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if (is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'rpc_add_user' => array(
							'function'  => 'rpc_add_user',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Add a new account.')
						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

		function check_rights($action, $access = 'group_access')
		{
			switch($action)
			{
				case 'view':	$right = '8'; break;
				case 'add':		$right = '4'; break;
				case 'edit':	$right = '16'; break;
				case 'delete':	$right = '32'; break;
				case 'search':	$right = '2'; break;
			}

			if (!$GLOBALS['phpgw']->acl->check($access,$right,'admin'))
			{
				return True;
			}
			return False;
		}


		function add_group($values)
		{
			$temp_users = ($values['account_user']?$values['account_user']:Array());
			$account_user = Array();
			@reset($temp_users);
			while(list($key,$user_id) = each($temp_users))
			{
				$account_user[$user_id] = ' selected';
			}
			@reset($account_user);

			$group_permissions = ($values['account_apps']?$values['account_apps']:Array());
			$account_apps = Array();
			@reset($group_permissions);
			while(list($key,$value) = each($group_permissions))
			{
				if($value)
				{
					$account_apps[$key] = True;
				}
			}
			@reset($account_apps);

			$group_info = Array
			(
				'account_id'   => ($values['account_id']?intval($values['account_id']):0),
				'account_name' => ($values['account_name']?$values['account_name']:''),
				'account_user' => $account_user,
				'account_apps' => $account_apps
			);

			$GLOBALS['phpgw']->db->lock(array
			(
				'phpgw_accounts',
				'phpgw_nextid',
				'phpgw_preferences',
				'phpgw_sessions',
				'phpgw_acl',
				'phpgw_applications',
				'phpgw_app_sessions',
				'phpgw_hooks'
			));

			$group = CreateObject('phpgwapi.accounts',$group_info['account_id'],'g');
			$group->acct_type = 'g';
			$account_info = array
			(
				'account_type'      => 'g',
				'account_lid'       => $group_info['account_name'],
				'account_passwd'    => '',
				'account_firstname' => $group_info['account_name'],
				'account_lastname'  => 'Group',
				'account_status'    => 'A',
				'account_expires'   => -1
//				'account_file_space' => $account_file_space_number . "-" . $account_file_space_type,
			);
			$group->create($account_info);
			$group_info['account_id'] = $GLOBALS['phpgw']->accounts->name2id($group_info['account_name']);

			$apps = CreateObject('phpgwapi.applications',$group_info['account_id']);
			$apps->update_data(Array());
			reset($group_info['account_apps']);
			while(list($app,$value) = each($group_info['account_apps']))
			{
				$apps->add($app);
				$new_apps[] = $app;
			}
			$apps->save_repository();

			$acl = CreateObject('phpgwapi.acl',$group_info['account_id']);
			$acl->read_repository();

			@reset($group_info['account_user']);
			while(list($user_id,$dummy) = each($group_info['account_user']))
			{
				if(!$dummy)
				{
					continue;
				}
				$acl->add_repository('phpgw_group',$group_info['account_id'],$user_id,1);

				$docommit = False;
				$GLOBALS['pref'] = CreateObject('phpgwapi.preferences',$user_id);
				$t = $GLOBALS['pref']->read_repository();
				@reset($new_apps);
				while(list($app_key,$app_name) = each($new_apps))
				{
					if (!$t[($app_name=='admin'?'common':$app_name)])
					{
						$GLOBALS['phpgw']->hooks->single('add_def_pref', $app_name);
						$docommit = True;
					}
				}
				if ($docommit)
				{
					$GLOBALS['pref']->save_repository();
				}
			}

			$acl->save_repository();

			$basedir = $GLOBALS['phpgw_info']['server']['files_dir'] . SEP . 'groups' . SEP;
			$cd = 31;
			umask(000);
			if (! @mkdir ($basedir . $group_info['account_name'], 0707))
			{
				$cd = 37;
			}
			$GLOBALS['phpgw']->db->unlock();
		}

		function edit_group($values)
		{
			if ($GLOBALS['phpgw']->acl->check('group_access',16,'admin'))
			{
				$error[] = lang('no permission to create groups');
			}

			$temp_users = ($values['account_user']?$values['account_user']:Array());
			$account_user = Array();
			@reset($temp_users);
			while($temp_users && list($key,$user_id) = each($temp_users))
			{
				$account_user[$user_id] = ' selected';
			}
			@reset($account_user);

			$group_permissions = ($values['account_apps']?$values['account_apps']:Array());
			$account_apps = Array();
			@reset($group_permissions);
			while(list($key,$value) = each($group_permissions))
			{
				if($value)
				{
					$account_apps[$key] = True;
				}
			}
			@reset($account_apps);

			$group_info = Array(
				'account_id'   => ($values['account_id']?intval($values['account_id']):0),
				'account_name' => ($values['account_name']?$values['account_name']:''),
				'account_user' => $account_user,
				'account_apps' => $account_apps
			);

			// Lock tables
			$GLOBALS['phpgw']->db->lock(
				Array(
					'phpgw_accounts',
					'phpgw_preferences',
					'phpgw_config',
					'phpgw_applications',
					'phpgw_hooks',
					'phpgw_sessions',
					'phpgw_acl',
					'phpgw_app_sessions'
				)
			);

			$group = CreateObject('phpgwapi.accounts',$group_info['account_id'],'g');
			$old_group_info = $group->read_repository();

			// Set group apps
			$apps = CreateObject('phpgwapi.applications',$group_info['account_id']);
			$apps_before = $apps->read_account_specific();
			$apps->update_data(Array());
			$new_apps = Array();
			if(count($group_info['account_apps']))
			{
				reset($group_info['account_apps']);
				while(list($app,$value) = each($group_info['account_apps']))
				{
					$apps->add($app);
					if(!@$apps_before[$app] || @$apps_before == False)
					{
						$new_apps[] = $app;
					}
				}
			}
			$apps->save_repository();

			// Set new account_lid, if needed
			if($group_info['account_name'] && $old_group_info['account_lid'] <> $group_info['account_name'])
			{
				$group->data['account_lid'] = $group_info['account_name'];

				$basedir = $GLOBALS['phpgw_info']['server']['files_dir'] . SEP . 'groups' . SEP;
				if (! @rename($basedir . $old_group_info['account_lid'], $basedir . $group_info['account_name']))
				{
					$cd = 39;
				}
				else
				{
					$cd = 33;
				}
			}
			else
			{
				$cd = 33;
			}

			// Set group acl
			$acl = CreateObject('phpgwapi.acl',$group_info['account_id']);
//			$acl->read_repository();
			$old_group_list = $acl->get_ids_for_location($group_info['account_id'],1,'phpgw_group');
			@reset($old_group_list);
			while($old_group_list && list($key,$user_id) = each($old_group_list))
			{
				$acl->delete_repository('phpgw_group',$group_info['account_id'],$user_id);
				if(!$group_info['account_user'][$user_id])
				{
					// If the user is logged in, it will force a refresh of the session_info
					$GLOBALS['phpgw']->db->query("update phpgw_sessions set session_action='' "
						."where session_lid='" . $GLOBALS['phpgw']->accounts->id2name($user_id)
						. '@' . $GLOBALS['phpgw_info']['user']['domain'] . "'",__LINE__,__FILE__);
					$GLOBALS['phpgw']->session->delete_cache($user_id);
				}
			}

//			$acl->save_repository();
//			$acl->read_repository();

			@reset($group_info['account_user']);
			while(list($user_id,$dummy) = each($group_info['account_user']))
			{
				if(!$dummy)
				{
					continue;
				}
				$acl->add_repository('phpgw_group',$group_info['account_id'],$user_id,1);

				// If the user is logged in, it will force a refresh of the session_info
				$GLOBALS['phpgw']->db->query("update phpgw_sessions set session_action='' "
					."where session_lid='" . $GLOBALS['phpgw']->accounts->id2name($user_id)
					. '@' . $GLOBALS['phpgw_info']['user']['domain'] . "'",__LINE__,__FILE__);
					
				$GLOBALS['phpgw']->session->delete_cache($user_id);
				
				// The following sets any default preferences needed for new applications..
				// This is smart enough to know if previous preferences were selected, use them.
				$docommit = False;
				if($new_apps)
				{
					$GLOBALS['pref'] = CreateObject('phpgwapi.preferences',$user_id);
					$t = $GLOBALS['pref']->read_repository();
					@reset($new_apps);
					while(list($app_key,$app_name) = each($new_apps))
					{
						if (!$t[($app_name=='admin'?'common':$app_name)])
						{
							$GLOBALS['phpgw']->hooks->single('add_def_pref', $app_name);
							$docommit = True;
						}
					}
				}
				if ($docommit)
				{
					$GLOBALS['pref']->save_repository();
				}

				// This is down here so we are sure to catch the acl changes
				// for LDAP to update the memberuid attribute
				$group->save_repository();
			}

		/*
			// Update any other options here, since the above save_repository () depends
			// on a group having users
			$group->data['file_space'] = $GLOBALS['HTTP_POST_VARS']['account_file_space_number'] . "-" . $GLOBALS['HTTP_POST_VARS']['account_file_space_type'];
			$group->save_repository();
		*/

			$GLOBALS['phpgw']->db->unlock();
		}

		function save_user($values)
		{
			if (is_array($values))
			{
				if($values['expires_never'])
				{
					$values['expires'] = $values['account_expires'] = -1;
				}
				else
				{
					$values['expires'] = $values['account_expires'] = mktime(2,0,0,$values['account_expires_month'],$values['account_expires_day'],$values['account_expires_year']);
				}

				$userData = array
				(
					'account_type'          => 'u',
					'account_lid'           => $values['account_lid'],
					'account_firstname'     => $values['account_firstname'],
					'account_lastname'      => $values['account_lastname'],
					'account_passwd'        => $values['account_passwd'],
					'status'                => ($values['account_status'] ? 'A' : ''),
					'account_status'        => ($values['account_status'] ? 'A' : ''),
					'old_loginid'           => ($values['old_loginid']?rawurldecode($GLOBALS['HTTP_GET_VARS']['old_loginid']):''),
					'account_id'            => ($values['account_id']?$GLOBALS['HTTP_GET_VARS']['account_id']:0),
					'account_passwd_2'      => $values['account_passwd_2'],
					'account_groups'        => $values['account_groups'],
					'account_permissions'   => $values['account_permissions'],
					'homedirectory'         => $values['homedirectory'],
					'loginshell'            => $values['loginshell'],
					'account_expires_month' => $values['account_expires_month'],
					'account_expires_day'   => $values['account_expires_day'],
					'account_expires_year'  => $values['account_expires_year'],
					'account_expires_never'	=> $values['expires'],
								'expires'	=> $values['expires'],
						'account_expires'	=> $values['expires']
					/* 'file_space' => $GLOBALS['HTTP_POST_VARS']['account_file_space_number'] . "-" . $GLOBALS['HTTP_POST_VARS']['account_file_space_type'] */
				);

				if ($values['account_id'])
				{
					$userData['account_id'] = $values['account_id'];
					$this->update_user($userData);
					/* check if would create a menu
					// if we do, we can't return to the users list, because
					// there are also some other plugins
					if (!ExecMethod('admin.uimenuclass.createHTMLCode','edit_user'))
					{
					}*/
				}
				else
				{
					$account_id = $this->so->add_user($userData);
					$GLOBALS['hook_values']['account_lid']	= $userData['account_lid'];
					$GLOBALS['hook_values']['account_id']	= $account_id; //$userData['account_id'];
					$GLOBALS['hook_values']['new_passwd']	= $userData['account_passwd'];
					$GLOBALS['phpgw']->hooks->process('addaccount');
				}
			}
		}

		/* stores the userdata */
		function update_user($userData)
		{
			$account = CreateObject('phpgwapi.accounts',$userData['account_id'],'u');
			$account->update_data($userData);
			$account->save_repository();
			if ($userData['account_passwd'])
			{
				$auth = CreateObject('phpgwapi.auth');
				$auth->change_password($old_passwd,$userData['account_passwd'],$userData['account_id']);
				$GLOBALS['hook_values']['account_id'] = $userData['account_id'];
				$GLOBALS['hook_values']['old_passwd'] = $old_passwd;
				$GLOBALS['hook_values']['new_passwd'] = $userData['account_passwd'];
				$GLOBALS['phpgw']->hooks->process('changepassword');
			}

			$apps = CreateObject('phpgwapi.applications',array(intval($userData['account_id']),'u'));

			$apps->account_id = $userData['account_id'];
			if ($_userData['account_permissions'])
			{
				while($app = each($userData['account_permissions'])) 
				{
					if($app[1]) 
					{
						$apps->add($app[0]);
					}
				}
			}
			$apps->save_repository();

			$account = CreateObject('phpgwapi.accounts',$userData['account_id'],'u');
			$allGroups = $account->get_list('groups');

			if ($userData['account_groups'])
			{
				reset($userData['account_groups']);
				while (list($key,$value) = each($userData['account_groups']))
				{
					$newGroups[$value] = $value;
				}
			}

			$acl = CreateObject('phpgwapi.acl',$userData['account_id']);

			reset($allGroups);
			while (list($key,$groupData) = each($allGroups)) 
			{
				/* print "$key,". $groupData['account_id'] ."<br>";*/
				/* print "$key,". $userData['account_groups'][1] ."<br>"; */

				if ($newGroups[$groupData['account_id']]) 
				{
					$acl->add_repository('phpgw_group',$groupData['account_id'],$userData['account_id'],1);
				}
				else
				{
					$acl->delete_repository('phpgw_group',$groupData['account_id'],$userData['account_id']);
				}
			}
			$GLOBALS['phpgw']->session->delete_cache(intval($userData['account_id']));
		}

		function set_group_managers()
		{
			if($GLOBALS['phpgw']->acl->check('group_access',16,'admin') || $GLOBALS['HTTP_POST_VARS']['cancel'])
			{
				$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}
			elseif($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$acl = CreateObject('phpgwapi.acl',intval($GLOBALS['HTTP_POST_VARS']['account_id']));
				
				$users = $GLOBALS['phpgw']->accounts->member($GLOBALS['HTTP_POST_VARS']['account_id']);
				@reset($users);
				while($managers && list($key,$user) = each($users))
				{
					$acl->add_repository('phpgw_group',intval($GLOBALS['HTTP_POST_VARS']['account_id']),$user['account_id'],1);
				}
				$managers = $GLOBALS['HTTP_POST_VARS']['managers'];
				@reset($managers);
				while($managers && list($key,$manager) = each($managers))
				{
					$acl->add_repository('phpgw_group',intval($GLOBALS['HTTP_POST_VARS']['account_id']),$manager,(1 + PHPGW_ACL_GROUP_MANAGERS));
				}
			}
			$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_groups'));
			$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
			exit;
		}

		function validate_group($values)
		{
			$group = CreateObject('phpgwapi.accounts',$values['account_id'],'g');
			$group->read_repository();

			if (!$values['account_id'] && $GLOBALS['phpgw']->acl->check('group_access',4,'admin'))
			{
				$error[] = lang('no permission to add groups');
			}

			if(!$values['account_name'])
			{
				$error[] = lang('You must enter a group name.');
			}

			if($values['account_name'] != $group->id2name($values['account_id']))
			{
				if ($group->exists($values['account_name']))
				{
					$error[] = lang('Sorry, that group name has already been taken.');
				}
			}

		/*
			if (preg_match ("/\D/", $account_file_space_number))
			{
				$error[] = lang ('File space must be an integer');
			}
		*/
			if(is_array($error))
			{
				return $error;
			}
		}

		/* checks if the userdata are valid
		 returns FALSE if the data are correct
		 otherwise the error array
		*/
		function validate_user($values)
		{
			if (!$values['account_id'] && $GLOBALS['phpgw']->acl->check('account_access',4,'admin'))
			{
				$error[] = lang('no permission to add users');
			}

			/*
			if ($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap' && ! $values['allow_long_loginids'])
			{
				if (strlen($values['account_lid']) > 8) 
				{
					$error[] = lang('The loginid can not be more then 8 characters');
				}
			}
			*/

			if (!$values['account_lid'])
			{
				$error[] = lang('You must enter a loginid');
			}

			if ($values['old_loginid'] != $values['account_lid']) 
			{
				if ($GLOBALS['phpgw']->accounts->exists($values['account_lid']))
				{
					$error[] = lang('That loginid has already been taken');
				}
			}

			if ($values['account_passwd'] || $values['account_passwd_2']) 
			{
				if ($values['account_passwd'] != $values['account_passwd_2']) 
				{
					$error[] = lang('The two passwords are not the same');
				}

			/*	$temp_msgbox_data = $GLOBALS['phpgw_info']['flags']['msgbox_data'];
				unset($GLOBALS['phpgw_info']['flags']['msgbox_data']);
				if(!sanitize($_userData['account_passwd'],'password'))
				{
					reset($GLOBALS['phpgw_info']['flags']['msgbox_data']);
					while(list($key) = each($GLOBALS['phpgw_info']['flags']['msgbox_data']))
					{
						$error[$totalerrors] = lang($key);
						$totalerrors++;
					}
				}
				$GLOBALS['phpgw_info']['flags']['msgbox_data'] = $temp_msgbox_data;
				unset($temp_msgbox_data); */
			}

			if (!count($values['account_permissions']) && !count($values['account_groups'])) 
			{
				$error[] = lang('You must add at least 1 permission or group to this account');
			}

			if ($values['account_expires_month'] || $values['account_expires_day'] || $values['account_expires_year'] || $values['account_expires_never'])
			{
				if (! checkdate($values['account_expires_month'],$values['account_expires_day'],$values['account_expires_year']))
				{
					$error[] = lang('You have entered an invalid expiration date');
				}
			}

		/*
			$check_account_file_space = explode ('-', $_userData['file_space']);
			if (preg_match ("/\D/", $check_account_file_space[0]))
			{
				$error[$totalerrors] = lang ('File space must be an integer');
				$totalerrors++;
			}
		*/

			if (is_array($error))
			{
				return $error;
			}
		}


		function delete_group($account_id)
		{
			if ($GLOBALS['phpgw']->acl->check('group_access',32,'admin'))
			{
				return False;
			}

			$GLOBALS['phpgw']->db->lock(array
				(
					'phpgw_accounts',
					'phpgw_acl'
				)
			);

			$old_group_list = $GLOBALS['phpgw']->acl->get_ids_for_location($account_id,1,'phpgw_group');

			@reset($old_group_list);
			while($old_group_list && $id = each($old_group_list))
			{
				$GLOBALS['phpgw']->acl->delete_repository('phpgw_group',$account_id,intval($id[1]));
				$GLOBALS['phpgw']->session->delete_cache(intval($id[1]));
			}

			$GLOBALS['phpgw']->acl->delete_repository('%%','run',$account_id);

			if (! @rmdir($GLOBALS['phpgw_info']['server']['files_dir'].SEP.'groups'.SEP.$GLOBALS['phpgw']->accounts->id2name($account_id)))
			{
				$cd = 38;
			}
			else
			{
				$cd = 32;
			}

			$GLOBALS['phpgw']->accounts->delete($account_id);
			$GLOBALS['phpgw']->db->unlock();
		}

		function delete_user()
		{
			if (isset($GLOBALS['HTTP_POST_VARS']['cancel']) || $GLOBALS['phpgw']->acl->check('account_access',32,'admin'))
			{
				ExecMethod('admin.uiaccounts.list_users');
				return False;
			}
			elseif($GLOBALS['HTTP_POST_VARS']['delete_account'])
			{
				$accountid = $GLOBALS['HTTP_POST_VARS']['account_id'];
				settype($account_id,'integer');
				$account_id = get_account_id($accountid);
				$lid = $GLOBALS['phpgw']->accounts->id2name($account_id);
				$db = $GLOBALS['phpgw']->db;
				$db->query('SELECT app_name,app_order FROM phpgw_applications WHERE app_enabled!=0 ORDER BY app_order',__LINE__,__FILE__);
				if($db->num_rows())
				{
					while($db->next_record())
					{
						$appname = $db->f('app_name');

						if($appname <> 'admin')
						{
							$GLOBALS['phpgw']->hooks->single('deleteaccount', $appname);
						}
					}
				}

				$GLOBALS['phpgw']->hooks->single('deleteaccount','preferences');
				$GLOBALS['phpgw']->hooks->single('deleteaccount','admin');

				$GLOBALS['hook_values']['account_id'] = $account_id;
				$GLOBALS['phpgw']->hooks->process('deleteaccount');

				$basedir = $GLOBALS['phpgw_info']['server']['files_dir'] . SEP . 'users' . SEP;

				if (! @rmdir($basedir . $lid))
				{
					$cd = 34;
				}
				else
				{
					$cd = 29;
				}

				ExecMethod('admin.uiaccounts.list_users');
				return False;
			}
		}

		function load_group_users($account_id)
		{
			$temp_user = $GLOBALS['phpgw']->acl->get_ids_for_location($account_id,1,'phpgw_group');
			if(!$temp_user)
			{
				return Array();
			}
			else
			{
				$group_user = $temp_user;
			}
			$account_user = Array();
			while (list($key,$user) = each($group_user))
			{
				$account_user[$user] = ' selected';
			}
			@reset($account_user);
			return $account_user;
		}

		function load_group_managers($account_id)
		{
			$temp_user = $GLOBALS['phpgw']->acl->get_ids_for_location($account_id,PHPGW_ACL_GROUP_MANAGERS,'phpgw_group');
			if(!$temp_user)
			{
				return Array();
			}
			else
			{
				$group_user = $temp_user;
			}
			$account_user = Array();
			while (list($key,$user) = each($group_user))
			{
				$account_user[$user] = ' selected';
			}
			@reset($account_user);
			return $account_user;
		}

		function load_group_apps($account_id)
		{
			$apps = CreateObject('phpgwapi.applications',intval($account_id));
			$app_list = $apps->read_account_specific();
			$account_apps = Array();
			while(list($key,$app) = each($app_list))
			{
				$account_apps[$app['name']] = True;
			}
			@reset($account_apps);
			return $account_apps;
		}

		// xmlrpc functions

		function rpc_add_user($data)
		{
			exit;

			if (!$errors = $this->validate_user($data))
			{
				$result = $this->so->add_user($data);
			}
			else
			{
				$result = $errors;
			}
			return $result;
		}
	}
?>
