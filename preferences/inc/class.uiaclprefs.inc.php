<?php
	/**************************************************************************\
	* phpGroupWare - Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uiaclprefs
	{
		var $acl;
		var $template;

		var $public_functions = array('index' => True);

		function uiaclprefs()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
		}

		function index()
		{
			$acl_app = get_var('acl_app',Array('GET','POST'));
			$owner   = $GLOBALS['owner'];

			if (! $acl_app)
			{
				$acl_app            = 'preferences';
				$acl_app_not_passed = True;
			}

			$GLOBALS['phpgw_info']['flags']['currentapp'] = $acl_app;

			if ($acl_app_not_passed)
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'F-BadmenuactionVariable, failed to pass acl_app.',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}
			}

			if ($GLOBALS['phpgw_info']['server']['deny_user_grants_access'])
			{
				echo '<center><b>' . lang('Access not permitted') . '</b></center>';
				$GLOBALS['phpgw']->common->phpgw_exit(True);
			}

			/*
			if(isset($save_my_owner) && $GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				$owner = $save_my_owner;
				unset($save_my_owner);
			}
			elseif(@isset($save_my_owner))
			{
				echo '<center>'.lang('You do not have permission to set ACL\'s in this mode!').'</center>';
				$GLOBALS['phpgw']->common->phpgw_footer();
			}
			*/

			if((!isset($owner) || empty($owner)) || !$GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				$owner = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$acct = CreateObject('phpgwapi.accounts',$owner);
			$groups = $acct->get_list('groups');
			$users = $acct->get_list('accounts');
			$owner_name = $acct->id2name($owner);		// get owner name for title
			if($is_group = $acct->get_type($owner) == 'g')
			{
				$owner_name = lang('Group').' ('.$owner_name.')';
			}
			unset($acct);
			$this->acl = CreateObject('phpgwapi.acl',intval($owner));
			$this->acl->read_repository();

			if(get_var('submit',Array('POST')))
			{
				$processed = get_var('processed',Array('POST'));
				$to_remove = unserialize(urldecode($processed));

				for($i=0;$i<count($to_remove);$i++)
				{
					$this->acl->delete($GLOBALS['phpgw_info']['flags']['currentapp'],$to_remove[$i]);
				}

				/* Group records */
				$group_variable = get_var('g_'.$GLOBALS['phpgw_info']['flags']['currentapp'],Array('POST'));

				if (!$group_variable)
				{
					$group_variable = array();
				}
				@reset($group_variable);
				$totalacl = array();
				while(list($rowinfo,$perm) = each($group_variable))
				{
					list($group_id,$rights) = split('_',$rowinfo);
					$totalacl[$group_id] += $rights;
				}
				@reset($totalacl);
				while(list($group_id,$rights) = @each($totalacl))
				{
					if($is_group)
					{
						/* Don't allow group-grants to grant private */
						$rights &= ~PHPGW_ACL_PRIVATE;
					}

					$this->acl->add($GLOBALS['phpgw_info']['flags']['currentapp'],$group_id,$rights);
				}

				/* User records */
				$user_variable = get_var('u_'.$GLOBALS['phpgw_info']['flags']['currentapp'],Array('POST'));

				if(!$user_variable)
				{
					$user_variable = array();
				}
				@reset($user_variable);
				$totalacl = array();
				while(list($rowinfo,$perm) = each($user_variable))
				{
					list($user_id,$rights) = split('_',$rowinfo);
					$totalacl[$user_id] += $rights;
				}
				@reset($totalacl);
				while(list($user_id,$rights) = @each($totalacl))
				{
					if($is_group)
					{
						/* Don't allow group-grants to grant private */
						$rights &= ~ PHPGW_ACL_PRIVATE;
					}

					$this->acl->add($GLOBALS['phpgw_info']['flags']['currentapp'],$user_id,$rights);
				}
				$this->acl->save_repository();
			}

			$processed = Array();

			$total = 0;

			if(!isset($start))
			{
				$start = 0;
			}

			if(!$start)
			{
				$s_groups = 0;
				$s_users = 0;
			}

			if(!isset($s_groups))
			{
				$s_groups = 0;
			}

			if(!isset($s_users))
			{
				$s_users = 0;
			}

			if(!isset($query))
			{
				$query = "";
			}

			if(!isset($maxm))
			{
				$maxm = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}

			if(!isset($totalentries))
			{
				$totalentries = count($groups) + count($users);
				if($totalentries < $maxm)
				{
					$maxm = $totalentries;
				}
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$this->template = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir($acl_app));
			$templates = Array (
				'preferences' => 'preference_acl.tpl',
				'row_colspan' => 'preference_colspan.tpl',
				'acl_row'     => 'preference_acl_row.tpl'
			);

			$this->template->set_file($templates);

			if ($submit)
			{
				$this->template->set_var('errors',lang('ACL grants have been updated'));
			}

			$common_hidden_vars =
				'     <input type="hidden" name="s_groups" value="'.$s_groups.'">'."\n"
				. '     <input type="hidden" name="s_users" value="'.$s_users.'">'."\n"
				. '     <input type="hidden" name="maxm" value="'.$maxm.'">'."\n"
				. '     <input type="hidden" name="totalentries" value="'.$totalentries.'">'."\n"
				. '     <input type="hidden" name="start" value="'.$start.'">'."\n"
				. '     <input type="hidden" name="query" value="'.$query.'">'."\n"
				. '     <input type="hidden" name="owner" value="'.$owner.'">'."\n"
				. '     <input type="hidden" name="acl_app" value="'.$acl_app.'">'."\n";

			$var = Array(
				'errors'      => '',
				'title'       => '<p><b>'.lang($GLOBALS['phpgw_info']['flags']['currentapp'].' preferences').' - '.lang('acl').': '.$owner_name.'</b><hr><p>',
				'action_url'  => $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=' . $acl_app),
				'bg_color'    => $GLOBALS['phpgw_info']['theme']['th_bg'],
				'submit_lang' => lang('submit'),
				'common_hidden_vars_form' => $common_hidden_vars
			);

			$this->template->set_var($var);

			if(isset($query_result) && $query_result)
			{
				$common_hidden_vars .= '<input type="hidden" name="query_result" value="'.$query_result.'">'."\n";
			}

			$this->template->set_var('common_hidden_vars',$common_hidden_vars);

			$var = Array(
				'read_lang'   => lang('Read'),
				'add_lang'    => lang('Add'),
				'edit_lang'   => lang('Edit'),
				'delete_lang' => lang('Delete')
			);

			$this->template->set_var($var);
			$this->template->set_var('private_lang',lang('Private'));

			if(intval($s_groups) <> count($groups))
			{
				$this->template->set_var('string',lang('Groups'));
				$this->template->parse('row','row_colspan',True);

				reset($groups);
				for($k=0;$k<count($groups);$k++)
				{
					$group = $groups[$k];
					$go = True;

					if($query)
					{
						if(!strpos(' '.$group['account_lid'].' ',$query))
						{
							$go = False;
						}
					}

					if($go)
					{
						$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
						$this->display_row($tr_color,'g_',$group['account_id'],$group['account_lid'],$is_group);
						$s_groups++;
						$processed[] = $group['account_id'];
						$total++;
						if($total == $maxm)
						{
							break;
						}
					}
				}
			}

			if($total <> $maxm)
			{
				if($users)
				{
					$this->template->set_var('string',ucfirst(lang('Users')));
					$this->template->parse('row','row_colspan',True);
					$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
					for($k=$s_users;$k<$totalentries || $k==count($users);$k++)
					{
						$user = $users[$k];
						//echo '<br>acctid: '.$user['account_id'];
						if ($user['account_id'])
						{
							$go = True;
						}
						else
						{
							$go = False;
						}
						if($query)
						{
							$name = ' '.$user['account_firstname'].' '.$user['account_lastname'].' '.$user['account_lid'].' ';
							if(!strpos($name,$query))
							{
								$go = False;
							}
						}

						if($go && $user['account_id'] != $owner)	// Need to be $owner not $GLOBALS['phpgw_info']['user']['account_id']
						{
							// or the admin can't get special grants from a group
							$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
							$this->display_row($tr_color,'u_',$user['account_id'],$GLOBALS['phpgw']->common->display_fullname($user['account_lid'],$user['account_firstname'],$user['account_lastname']),$is_group);
							$s_users++;
							$processed[] = $user['account_id'];
							$total++;
							if($total == $maxm)
							{
								break;
							}
						}
					}
				}
			}

			$extra_parms = 'menuaction=preferences.uiaclprefs.index'
				. '&acl_app=' . $acl_app
				. '&s_users='.$s_users.'&s_groups='.$s_groups
				. '&maxm=' . $maxm . '&totalentries=' . $totalentries
				. '&total=' . ($start + $total) . '&owner='.$owner;

			$var = Array(
				'nml'          => $GLOBALS['phpgw']->nextmatchs->left('/index.php',$start,$totalentries,$extra_parms),
				'nmr'          => $GLOBALS['phpgw']->nextmatchs->right('/index.php',$start,$totalentries,$extra_parms),
				'search_value' => (isset($query) && $query?$query:''),
				'search'       => lang('search'),
				'processed'    => urlencode(serialize($processed))
			);

			$this->template->set_var($var);

			$this->template->pfp('out','preferences');
		}

		function check_acl($label,$id,$acl,$rights,$right,$is_group=False)
		{
			$this->template->set_var($acl,$label.$GLOBALS['phpgw_info']['flags']['currentapp'].'['.$id.'_'.$right.']');
			$rights_set = (($rights & $right)?' checked':'');
			if ($is_group)
			{
				// This is so you can't select it in the GUI
				$rights_set .= ' disabled';
			}
			$this->template->set_var($acl.'_selected',$rights_set);
		}

		function display_row($bg_color,$label,$id,$name,$is_group)
		{
			$this->template->set_var('row_color',$bg_color);
			$this->template->set_var('user',$name);
			$rights = $this->acl->get_rights($id,$GLOBALS['phpgw_info']['flags']['currentapp']);
			// vv This is new
			$grantors = $this->acl->get_ids_for_location($id,$rights,$GLOBALS['phpgw_info']['flags']['currentapp']);
			$is_group_set = False;
			while(@$grantors && list($key,$grantor) = each($grantors))
			{
				if($GLOBALS['phpgw']->accounts->get_type($grantor) == 'g')
				{
					$is_group_set = True;
				}
			}
			// ^^ This is new

			$this->check_acl($label,$id,'read',$rights,PHPGW_ACL_READ,($is_group_set && ($rights & PHPGW_ACL_READ) && !$is_group?$is_group_set:False));
			$this->check_acl($label,$id,'add',$rights,PHPGW_ACL_ADD,($is_group_set && ($rights & PHPGW_ACL_ADD && !$is_group)?$is_group_set:False));
			$this->check_acl($label,$id,'edit',$rights,PHPGW_ACL_EDIT,($is_group_set && ($rights & PHPGW_ACL_EDIT && !$is_group)?$is_group_set:False));
			$this->check_acl($label,$id,'delete',$rights,PHPGW_ACL_DELETE,($is_group_set && ($rights & PHPGW_ACL_DELETE && !$is_group)?$is_group_set:False));
			$this->check_acl($label,$id,'private',$rights,PHPGW_ACL_PRIVATE,$is_group);

			$this->template->parse('row','acl_row',True);
		}
	}
?>
