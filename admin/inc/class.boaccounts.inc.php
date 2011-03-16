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

	class boaccounts
	{
		var $so;
		var $public_functions = array(
			'add_group'    => True,
			'add_user'     => True,
			'delete_group' => True,
			'delete_user'  => True,
			'edit_group'   => True,
			'edit_user'    => True
		);

		var $xml_functions = array();

		var $soap_functions = array(
			'add_user' => array(
				'in'  => array('int', 'struct'),
				'out' => array()
			)
		);

		function boaccounts()
		{
			$this->so =& CreateObject('admin.soaccounts');
		}

		function delete_group($account_id='')
		{
			if(!$account_id || $GLOBALS['egw']->acl->check('group_access',32,'admin'))
			{
				return False;
			}

			$account_id = (int)$account_id;

			// delete all acl (and memberships) of group
			$GLOBALS['egw']->acl->delete_account($account_id);

			// make this information also available in the hook
			$lid = $GLOBALS['egw']->accounts->id2name($account_id);

			$GLOBALS['egw']->hooks->process($GLOBALS['hook_values'] = array(
				'account_id'  => $account_id,
				'account_name' => $lid,
				'location'    => 'deletegroup'
			),False,True);  // called for every app now, not only enabled ones)

			$GLOBALS['egw']->accounts->delete($account_id);

			return True;
		}

		function delete_user($account_id='',$new_owner='')
		{
			if(!$account_id || $GLOBALS['egw']->acl->check('account_access',32,'admin'))
			{
				return False;
			}

			$accountid = (int)$account_id;
			$account_id = get_account_id($accountid);
			// make this information also available in the hook
			$lid = $GLOBALS['egw']->accounts->id2name($account_id);

			$GLOBALS['hook_values'] = array(
				'account_id'  => $account_id,
				'account_lid' => $lid,
				'new_owner'   => (int)$new_owner,
				'location'    => 'deleteaccount'
			);
			// first all other apps, then preferences and admin
			foreach(array_merge(array_diff(array_keys($GLOBALS['egw_info']['apps']),array('preferences','admin')),array('preferences','admin')) as $app)
			{
				$GLOBALS['egw']->hooks->single($GLOBALS['hook_values'],$app);
			}
			return True;
		}

		function add_group($group_info)
		{
			if($GLOBALS['egw']->acl->check('group_access',4,'admin'))
			{
				return False;
			}

			$errors = $this->validate_group($group_info);
			if(count($errors))
			{
				return $errors;
			}

			$group =& CreateObject('phpgwapi.accounts',$group_info['account_id'],'g');
			$group->acct_type = 'g';
			$account_info = array(
				'account_type'      => 'g',
				'account_lid'       => $group_info['account_name'],
				'account_passwd'    => '',
				'account_firstname' => $group_info['account_name'],
				'account_lastname'  => 'Group',
				'account_status'    => 'A',
				'account_expires'   => -1,
//				'account_file_space' => $account_file_space_number . "-" . $account_file_space_type,
				'account_email'     => $group_info['account_email'],
				'account_members'   => $group_info['account_user']
			);
			$group_info['account_id'] = $group->create($account_info);

			// do the following only if we got an id - the create succeeded
			if($group_info['account_id'])
			{
				$group->set_members($group_info['account_user'],$group_info['account_id']);

				$apps =& CreateObject('phpgwapi.applications',$group_info['account_id']);
				$apps->update_data(Array());
				reset($group_info['account_apps']);
				while(list($app,$value) = each($group_info['account_apps']))
				{
					$apps->add($app);
					$new_apps[] = $app;
				}
				$apps->save_repository();

				$GLOBALS['hook_values'] = $group_info;
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'addgroup'
				),False,True);  // called for every app now, not only enabled ones)

				return True;
			}

			return False;
		}

		function add_user($userData)
		{
			if($GLOBALS['egw']->acl->check('account_access',4,'admin'))
			{
				return False;
			}

			$accountPrefix = '';
			if(isset($GLOBALS['egw_info']['server']['account_prefix']))
			{
				$accountPrefix = $GLOBALS['egw_info']['server']['account_prefix'];
			}
			if($accountPrefix)
			{
				$userData['account_lid'] = $accountPrefix . $userData['account_lid'];
			}

			// add the primary group, to the users other groups, if not already added
			if(is_array($userData['account_groups']))
			{
				if(!in_array($userData['account_primary_group'],$userData['account_groups']))
				{
					$userData['account_groups'][] = (int)$userData['account_primary_group'];
				}
			}
			else
			{
				$userData['account_groups'] = array((int)$userData['account_primary_group']);
			}

			// do we have all needed data??
			if(!($errors = $this->validate_user($userData)) &&
				($userData['account_id'] = $account_id = $this->so->add_user($userData)))	// no error in the creation
			{
				if($userData['anonymous'])
				{
					$GLOBALS['egw']->acl->add_repository('phpgwapi','anonymous',$account_id,1);
				}
				else
				{
					$GLOBALS['egw']->acl->delete_repository('phpgwapi','anonymous',$account_id);
				}
				// make this information for the hooks available
				$GLOBALS['hook_values'] = $userData + array('new_passwd' => $userData['account_passwd']);
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'addaccount'
				),False,True);	// called for every app now, not only enabled ones

				return True;
			}
			else
			{
				return $errors;
			}
			return False;
		}

		function edit_group($group_info)
		{
			if($GLOBALS['egw']->acl->check('group_access',16,'admin'))
			{
				return False;
			}

			$errors = $this->validate_group($group_info);
			if(count($errors))
			{
				return $errors;
			}

			$group =& CreateObject('phpgwapi.accounts',$group_info['account_id'],'g');
			$old_group_info = $group->read_repository();

			// Set group apps
			$apps =& CreateObject('phpgwapi.applications',$group_info['account_id']);
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

			$group->set_members($group_info['account_user'],$group_info['account_id']);

			$GLOBALS['hook_values'] = $group_info;
			$GLOBALS['hook_values']['old_name'] = $group->id2name($group_info['account_id']);

			// This is down here so we are sure to catch the acl changes
			// for LDAP to update the memberuid attribute
			$group->data['account_email'] = $group_info['account_email'];
			$group->data['account_lid']   = $group_info['account_name'];
			$group->save_repository();

			$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
				'location' => 'editgroup'
			),False,True);  // called for every app now, not only enabled ones)

			return True;
		}

		function edit_user($userData)
		{
			if($GLOBALS['egw']->acl->check('account_access',16,'admin'))
			{
				return False;
			}
			//error_log(array2string($userData));
			$accountPrefix = '';
			if(isset($GLOBALS['egw_info']['server']['account_prefix']))
			{
				$accountPrefix = $GLOBALS['egw_info']['server']['account_prefix'];
			}
			if($accountPrefix)
			{
				$userData['account_lid'] = $accountPrefix . $userData['account_lid'];
			}

			$errors = $this->validate_user($userData);
			if(@is_array($errors))
			{
				return $errors;
			}
			else
			{
				$this->save_user($userData);
				$GLOBALS['hook_values'] = $userData;
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'editaccount'
				),False,True);	// called for every app now, not only enabled ones)

				return True;
			}
			return True;
		}

		function validate_group($group_info)
		{
			$errors = Array();

			$group =& CreateObject('phpgwapi.accounts',$group_info['account_id'],'g');
			$group->read_repository();

			if(!$group_info['account_name'])
			{
				$errors[] = lang('You must enter a group name.');
			}
			/* For LDAP */
			if(!$group_info['account_user'])
			{
				$errors[] = lang('You must select at least one group member.');
			}

			if($group_info['account_name'] != $group->id2name($group_info['account_id']))
			{
				if($group->exists($group_info['account_name']))
				{
					$errors[] = lang('Sorry, that group name has already been taken.');
				}
			}

		/*
			if(preg_match("/\D/", $account_file_space_number))
			{
				$errors[] = lang('File space must be an integer');
			}
		*/
			if(count($errors))
			{
				return $errors;
			}
		}

		/* checks if the userdata are valid
		 returns FALSE if the data are correct
		 otherwise the error array
		*/
		function validate_user(&$_userData)
		{
			$totalerrors = 0;

			if($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
				(!$_userData['account_lastname'] && !$_userData['lastname']))
			{
				$error[$totalerrors] = lang('You must enter a lastname');
				$totalerrors++;
			}

			if(!$_userData['account_lid'])
			{
				$error[$totalerrors] = lang('You must enter a loginid');
				$totalerrors++;
			}

			if(!in_array($_userData['account_primary_group'],$_userData['account_groups']))
			{
				$error[$totalerrors] = lang('The groups must include the primary group');
				$totalerrors++;
			}
			// Check if an account already exists as system user, and if it does deny creation
			// (increase the totalerrors counter and the message thereof)
			if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
				!$GLOBALS['egw_info']['server']['ldap_allow_systemusernames'] &&
				function_exists('posix_getpwnam') && posix_getpwnam($_userData['account_lid']))
			{
				$error[$totalerrors] = lang('There already is a system-user with this name. User\'s should not have the same name as a systemuser');
				$totalerrors++;
			}
			if($_userData['old_loginid'] != $_userData['account_lid'])
			{
				if($GLOBALS['egw']->accounts->exists($_userData['account_lid']))
				{
					if($GLOBALS['egw']->accounts->exists($_userData['account_lid']) && $GLOBALS['egw']->accounts->get_type($_userData['account_lid'])=='g')
					{
						$error[$totalerrors] = lang('There already is a group with this name. Userid\'s can not have the same name as a groupid');
					}
					else
					{
						$error[$totalerrors] = lang('That loginid has already been taken');
					}
					$totalerrors++;
				}
			}

			if($_userData['account_passwd'] || $_userData['account_passwd_2'])
			{
				if($_userData['account_passwd'] != $_userData['account_passwd_2'])
				{
					$error[$totalerrors] = lang('The two passwords are not the same');
					$totalerrors++;
				}
			}

			if(!count($_userData['account_permissions']) && !count($_userData['account_groups']))
			{
				$error[$totalerrors] = lang('You must add at least 1 permission or group to this account');
				$totalerrors++;
			}

			if($_userData['account_expires_month'] || $_userData['account_expires_day'] || $_userData['account_expires_year'] || $_userData['account_expires_never'])
			{
				if($_userData['account_expires_never'])
				{
					$_userData['expires'] = -1;
					$_userData['account_expires'] = $_userData['expires'];
				}
				else
				{
					if(! checkdate($_userData['account_expires_month'],$_userData['account_expires_day'],$_userData['account_expires_year']))
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
			}
			else
			{
				$_userData['expires'] = -1;
				$_userData['account_expires'] = $_userData['expires'];
			}

		/*
			$check_account_file_space = explode('-', $_userData['file_space']);
			if(preg_match("/\D/", $check_account_file_space[0]))
			{
				$error[$totalerrors] = lang('File space must be an integer');
				$totalerrors++;
			}
		*/

			if($totalerrors == 0)
			{
				return False;
			}
			else
			{
				return $error;
			}
		}

		/* stores the userdata */
		function save_user($_userData)
		{
			//error_log(__METHOD__.array2string($_userData));
			//error_log(__METHOD__.array2string($old_passwd));
			$account =& CreateObject('phpgwapi.accounts',$_userData['account_id'],'u');
			$account->update_data($_userData);
			$account->save_repository();

			$account->set_memberships($_userData['account_groups'],$_userData['account_id']);

			if($_userData['account_passwd'])
			{
				$auth =& CreateObject('phpgwapi.auth');
				$auth->change_password($old_passwd, $_userData['account_passwd'], $_userData['account_id']);
				$GLOBALS['hook_values']['account_id'] = $_userData['account_id'];
				$GLOBALS['hook_values']['old_passwd'] = $old_passwd;
				$GLOBALS['hook_values']['new_passwd'] = $_userData['account_passwd'];

				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'changepassword'
				),False,True);	// called for every app now, not only enabled ones)
				if ($_userData['account_lastpwd_change']==0)
				{
					// change password sets the shadow_timestamp/account_lastpwd_change timestamp
					// so we need to reset that to 0 as Admin required the change of password upon next login
					unset($_userData['account_passwd']);
					$this->save_user($_userData);
					// maybe we should call that with NULL for 2nd Parameter as we are doing an admin action.
					if (method_exists($auth,'setLastPwdChange')) $auth->setLastPwdChange($_userData['account_id'], $_userData['account_passwd'], $_userData['account_lastpwd_change']);
				}
			}

			$apps =& CreateObject('phpgwapi.applications',(int)$_userData['account_id']);
			if($_userData['account_permissions'])
			{
				foreach($_userData['account_permissions'] as $app => $enabled)
				{
					if($enabled)
					{
						$apps->add($app);
					}
				}
			}
			$apps->save_repository();

			$acl =& CreateObject('phpgwapi.acl',$_userData['account_id']);
			if($_userData['anonymous'])
			{
				$acl->add_repository('phpgwapi','anonymous',$_userData['account_id'],1);
			}
			else
			{
				$acl->delete_repository('phpgwapi','anonymous',$_userData['account_id']);
			}
			if(!$_userData['changepassword'])
			{
				$GLOBALS['egw']->acl->add_repository('preferences','nopasswordchange',$_userData['account_id'],1);
			}
			else
			{
				$GLOBALS['egw']->acl->delete_repository('preferences','nopasswordchange',$_userData['account_id']);
			}
			$GLOBALS['egw']->session->delete_cache((int)$_userData['account_id']);
		}

		function load_group_managers($account_id)
		{
			$temp_user = $GLOBALS['egw']->acl->get_ids_for_location($account_id,EGW_ACL_GROUP_MANAGERS,'phpgw_group');
			if(!$temp_user)
			{
				return Array();
			}
			else
			{
				$group_user = $temp_user;
			}
			$account_user = Array();
			while(list($key,$user) = each($group_user))
			{
				$account_user[$user] = ' selected';
			}
			@reset($account_user);
			return $account_user;
		}

		function load_group_apps($account_id)
		{
			$apps =& CreateObject('phpgwapi.applications',(int)$account_id);
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

			if(!$errors = $this->validate_user($data))
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
