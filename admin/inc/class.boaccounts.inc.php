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

	class boaccounts
	{
		var $public_functions = array(
			'add_user'	=> True,
			'delete_user'	=> True,
			'edit_user'	=> True
		);

		var $so;

		function boaccounts()
		{
			$this->so = createobject('admin.soaccounts');
		}

		function account_total($query)
		{
			return $this->so->account_total($query);
		}

		function delete_user()
		{
			if(isset($GLOBALS['HTTP_POST_VARS']['cancel']))
			{
				Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users'));
				$GLOBALS['phpgw']->common->phpgw_exit();

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
							$GLOBALS['phpgw']->common->hook_single('deleteaccount', $appname);
						}
					}
				}
		
				$GLOBALS['phpgw']->common->hook_single('deleteaccount','preferences');
				$GLOBALS['phpgw']->common->hook_single('deleteaccount','admin');
		
				$basedir = $GLOBALS['phpgw_info']['server']['files_dir'] . SEP . 'users' . SEP;

				if (! @rmdir($basedir . $lid))
				{
					$cd = 34;
				}
				else
				{
					$cd = 29;
				}

				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> 'admin.uiaccounts.list_users',
							'cd'		=> $cd
						)
					)
				);
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
		}

		function add_user()
		{
			if ($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$userData = array(
					'account_type'				=> 'u',
					'account_lid'           => $GLOBALS['HTTP_POST_VARS']['account_lid'],
					'account_firstname'     => $GLOBALS['HTTP_POST_VARS']['account_firstname'],
					'account_lastname'      => $GLOBALS['HTTP_POST_VARS']['account_lastname'],
					'account_passwd'        => $GLOBALS['HTTP_POST_VARS']['account_passwd'],
					'account_status'        => ($GLOBALS['HTTP_POST_VARS']['account_status']?$GLOBALS['HTTP_POST_VARS']['account_status']:''),
					'old_loginid'           => ($GLOBALS['HTTP_GET_VARS']['old_loginid']?rawurldecode($GLOBALS['HTTP_GET_VARS']['old_loginid']):''),
					'account_id'            => ($GLOBALS['HTTP_GET_VARS']['account_id']?$GLOBALS['HTTP_GET_VARS']['account_id']:0),
					'account_passwd_2'      => $GLOBALS['HTTP_POST_VARS']['account_passwd_2'],
					'account_groups'        => $GLOBALS['HTTP_POST_VARS']['account_groups'],
					'account_permissions'   => $GLOBALS['HTTP_POST_VARS']['account_permissions'],
					'homedirectory'         => $GLOBALS['HTTP_POST_VARS']['homedirectory'],
					'loginshell'            => $GLOBALS['HTTP_POST_VARS']['loginshell'],
					'account_expires_month' => $GLOBALS['HTTP_POST_VARS']['account_expires_month'],
					'account_expires_day'   => $GLOBALS['HTTP_POST_VARS']['account_expires_day'],
					'account_expires_year'  => $GLOBALS['HTTP_POST_VARS']['account_expires_year']
					/* 'file_space'	=> $GLOBALS['HTTP_POST_VARS']['account_file_space_number'] . "-" . $GLOBALS['HTTP_POST_VARS']['account_file_space_type'] */
				);

				if (!$errors = $this->validate_user($userData))
				{
					$userData['account_expires'] = $userData['expires'];
					$GLOBALS['phpgw']->db->lock(
						Array(
							'phpgw_accounts',
							'phpgw_nextid',
							'phpgw_preferences',
							'phpgw_sessions',
							'phpgw_acl',
							'phpgw_applications'
						)
					);

					$GLOBALS['phpgw']->accounts->create($userData);

					$userData['account_id'] = $GLOBALS['phpgw']->accounts->name2id($userData['account_lid']);

					$apps = CreateObject('phpgwapi.applications',array($userData['account_id'],'u'));
					$apps->read_installed_apps();

					// Read Group Apps
					if ($GLOBALS['HTTP_POST_VARS']['account_groups'])
					{
						$apps->account_type = 'g';
						reset($GLOBALS['HTTP_POST_VARS']['account_groups']);
						while($groups = each($GLOBALS['HTTP_POST_VARS']['account_groups']))
						{
							$apps->account_id = $groups[0];
							$old_app_groups = $apps->read_account_specific();
							@reset($old_app_groups);
							while($old_group_app = each($old_app_groups))
							{
								if (!$apps_after[$old_group_app[0]])
								{
									$apps_after[$old_group_app[0]] = $old_app_groups[$old_group_app[0]];
								}
							}
						}
					}

					$apps->account_type = 'u';
					$apps->account_id = $userData['account_id'];
					$apps->account_apps = Array(Array());

					if ($userData['account_permissions'])
					{
						@reset($userData['account_permissions']);
						while ($app = each($userData['account_permissions']))
						{
							if ($app[1])
							{
								$apps->add($app[0]);
								if (!$apps_after[$app[0]])
								{
									$apps_after[] = $app[0];
								}
							}
						}
					}
					$apps->save_repository();

					$GLOBALS['phpgw']->acl->add_repository('preferences','changepassword',$userData['account_id'],1);

					// Assign user to groups
					if ($userData['account_groups'])
					{
						$c_acct_groups = count($userData['account_groups']);
						for ($i=0;$i<$c_acct_groups;$i++)
						{
							$GLOBALS['phpgw']->acl->add_repository('phpgw_group',$userData['account_groups'][$i],$userData['account_id'],1);
						}
					}

					if ($apps_after)
					{
						$GLOBALS['pref'] = CreateObject('phpgwapi.preferences',$userData['account_id']);
						$GLOBALS['phpgw']->common->hook_single('add_def_pref','admin');
						while ($apps = each($apps_after))
						{
							if (strcasecmp ($apps[0], 'admin') != 0)
							{
								$GLOBALS['phpgw']->common->hook_single('add_def_pref', $apps[1]);
							}
						}
						$GLOBALS['pref']->save_repository(False);
					}

					$apps->account_apps = Array(Array());
					$apps_after = Array(Array());

					$GLOBALS['phpgw']->db->unlock();

/*
					// start inlcuding other admin tools
					while($app = each($apps_after))
					{
						$GLOBALS['phpgw']->common->hook_single('add_user_data', $value);
					}
*/
					Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> 'admin.uiaccounts.list_users',
								'cd'	=> $cd
							)
						)
					);
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
				else
				{
					$ui = createobject('admin.uiaccounts');
					$ui->create_edit_user($userData['account_id'],$userData,$errors);
				}
			}
			else
			{
				Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiaccounts.list_users'));
				$GLOBALS['phpgw']->common->phpgw_exit();				
			}
		}

		function edit_user()
		{
			if ($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				$userData = array(
					'account_lid'           => $GLOBALS['HTTP_POST_VARS']['account_lid'],
					'firstname'             => $GLOBALS['HTTP_POST_VARS']['account_firstname'],
					'lastname'              => $GLOBALS['HTTP_POST_VARS']['account_lastname'],
					'account_passwd'        => $GLOBALS['HTTP_POST_VARS']['account_passwd'],
					'status'                => $GLOBALS['HTTP_POST_VARS']['account_status'],
					'old_loginid'           => ($GLOBALS['HTTP_GET_VARS']['old_loginid']?rawurldecode($GLOBALS['HTTP_GET_VARS']['old_loginid']):''),
					'account_id'            => ($GLOBALS['HTTP_GET_VARS']['account_id']?$GLOBALS['HTTP_GET_VARS']['account_id']:0),
					'account_passwd_2'      => $GLOBALS['HTTP_POST_VARS']['account_passwd_2'],
					'account_groups'        => $GLOBALS['HTTP_POST_VARS']['account_groups'],
					'account_permissions'   => $GLOBALS['HTTP_POST_VARS']['account_permissions'],
					'homedirectory'         => $GLOBALS['HTTP_POST_VARS']['homedirectory'],
					'loginshell'            => $GLOBALS['HTTP_POST_VARS']['loginshell'],
					'account_expires_month' => $GLOBALS['HTTP_POST_VARS']['account_expires_month'],
					'account_expires_day'   => $GLOBALS['HTTP_POST_VARS']['account_expires_day'],
					'account_expires_year'  => $GLOBALS['HTTP_POST_VARS']['account_expires_year']
					/* 'file_space'	=> $GLOBALS['HTTP_POST_VARS']['account_file_space_number'] . "-" . $GLOBALS['HTTP_POST_VARS']['account_file_space_type'] */
				);

				if (!$errors = $this->validate_user($userData))
				{
					$this->save_user($userData);
					// check if would create a menu
					// if we do, we can't return to the users list, because
					// there are also some other plugins
					$menuClass = CreateObject('admin.uimenuclass');
					if (!$menuClass->createHTMLCode('edit_user'))
					{
						Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',
								Array(
									'menuaction'	=> 'admin.uiaccounts.list_users',
									'cd'		=> $cd
								)
							)
						);
						$GLOBALS['phpgw']->common->phpgw_exit();
					}
					else
					{
						$linkdata = Array(
							'menuaction'	=> 'admin.uiaccounts.edit_user',
							'cd'	=> $cd,
							'account_id'	=> $GLOBALS['HTTP_GET_VARS']['account_id']
						);
						Header('Location: ' . $GLOBALS['phpgw']->link('/index.php', $linkdata));

						$GLOBALS['phpgw']->common->phpgw_exit();
					}
				}
				else
				{
					$ui = createobject('admin.uiaccounts');
					$ui->create_edit_user($userData['account_id'],$userData,$errors);
				}
			}
		}

		/* checks if the userdata are valid
		 returns FALSE if the data are correct
		 otherwise the error array
		*/
		function validate_user(&$_userData)
		{
			$totalerrors = 0;

			if ($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap' && ! $allow_long_loginids)
			{
				if (strlen($_userData['account_lid']) > 8) 
				{
					$error[$totalerrors] = lang('The loginid can not be more then 8 characters');
					$totalerrors++;
				}
			}
			
			if (!$_userData['account_lid'])
			{
				$error[$totalerrors] = lang('You must enter a loginid');
				$totalerrors++;
			}

			if ($_userData['old_loginid'] != $_userData['account_lid']) 
			{
				if ($GLOBALS['phpgw']->accounts->exists($_userData['account_lid']))
				{
					$error[$totalerrors] = lang('That loginid has already been taken');
					$totalerrors++;
				}
			}

			if ($_userData['account_passwd'] || $_userData['account_passwd_2']) 
			{
				if ($_userData['account_passwd'] != $_userData['account_passwd_2']) 
				{
					$error[$totalerrors] = lang('The two passwords are not the same');
					$totalerrors++;
				}
			}

			if (!count($_userData['account_permissions']) && !count($_userData['account_groups'])) 
			{
				$error[$totalerrors] = lang('You must add at least 1 permission or group to this account');
				$totalerrors++;
			}

			if ($_userData['account_expires_month'] || $_userData['account_expires_day'] || $_userData['account_expires_year'])
			{
				if (! checkdate($_userData['account_expires_month'],$_userData['account_expires_day'],$_userData['account_expires_year']))
				{
					$error[$totalerrors] = lang('You have entered an invalid expiration date');
					$totalerrors++;
				}
				else
				{
					$_userData['expires'] = mktime(2,0,0,$_userData['account_expires_month'],$_userData['account_expires_day'],$_userData['account_expires_year']);
					$_userData['account_expires'] = $_userData['expires'];
				}
			}
			else
			{
				$_userData['expires'] = -1;
				$_userData['account_expires'] = $_userData['expires'];
			}

		/*
			$check_account_file_space = explode ('-', $_userData['file_space']);
			if (preg_match ("/\D/", $check_account_file_space[0]))
			{
				$error[$totalerrors] = lang ('File space must be an integer');
				$totalerrors++;
			}
		*/

			if ($totalerrors == 0)
			{
				return FALSE;
			}
			else
			{
				return $error;
			}
		}
		
		/* stores the userdata */
		function save_user($_userData)
		{
			$account = CreateObject('phpgwapi.accounts',$_userData['account_id']);
			$account->update_data($_userData);
			$account->save_repository();
			if ($_userData['account_passwd'])
			{
				$auth = CreateObject('phpgwapi.auth');
				$auth->change_password($old_passwd, $_userData['account_passwd'], $_userData['account_id']);
			}

			$apps = CreateObject('phpgwapi.applications',array(intval($_userData['account_id']),'u'));

			$apps->account_id = $_userData['account_id'];
			if ($_userData['account_permissions'])
			{				
				while($app = each($_userData['account_permissions'])) 
				{
					if($app[1]) 
					{
						$apps->add($app[0]);
					}
				}
			}
			$apps->save_repository();

			$account = CreateObject('phpgwapi.accounts',$_userData['account_id']);
			$allGroups = $account->get_list('groups');

			if ($_userData['account_groups']) {
				reset($_userData['account_groups']);
				while (list($key,$value) = each($_userData['account_groups'])) {
					$newGroups[$value] = $value;
				}
			}

			$acl = CreateObject('phpgwapi.acl',$_userData['account_id']);

			reset($allGroups);
			while (list($key,$groupData) = each($allGroups)) 
			{
				/* print "$key,". $groupData['account_id'] ."<br>";*/
				/* print "$key,". $_userData['account_groups'][1] ."<br>"; */

				if ($newGroups[$groupData['account_id']]) 
				{
					$acl->add_repository('phpgw_group',$groupData['account_id'],$_userData['account_id'],1);
				}
				else
				{
					$acl->delete_repository('phpgw_group',$groupData['account_id'],$_userData['account_id']);
				}
			}
			$GLOBALS['phpgw']->session->delete_cache(intval($_userData['account_id']));
		}
	}
?>
