<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	if ($submit)
	{
		$phpgw_flags = Array(
			'currentapp'	=> 'admin',
			'enable_nextmatchs_class'	=> True,
			'noheader'	=> True,
			'nonavbar'	=> True
		);
	}
	else
	{
		$phpgw_flags = Array(
			'currentapp'	=> 'admin',
			'enable_nextmatchs_class'	=> True
		);
	}
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

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

	function is_odd($n)
	{
		$ln = substr($n,-1);
		if ($ln == 1 || $ln == 3 || $ln == 5 || $ln == 7 || $ln == 9)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

	if (! $group_id)
	{
		Header('Location: ' . $phpgw->link('/admin/groups.php'));
	}

	if ($submit)
	{
		$group = CreateObject('phpgwapi.accounts',intval($group_id));
		$group->read_repository();
		$old_group_name = $group->id2name($group_id);

		if($n_group != $old_group_name)
		{
			if ($group->exists($n_group))
			{
				$error = lang('Sorry, that group name has already been taken.');
			}
		}

		/*
		if (preg_match ("/\D/", $account_file_space_number))
		{
			$error = lang ('File space must be an integer');
		}
		*/

		if (!$error)
		{
			// Lock tables
			$phpgw->db->lock(Array('phpgw_accounts','phpgw_preferences','phpgw_config','phpgw_applications','phpgw_hooks','phpgw_sessions','phpgw_acl'));

			// Set group apps
			$apps = CreateObject('phpgwapi.applications',intval($group_id));
			$apps_before = $apps->read_account_specific();
			$apps->update_data(Array());
			$new_apps = Array();
			if(isset($n_group_permissions))
			{
				reset($n_group_permissions);
				while($app = each($n_group_permissions))
				{
					if($app[1])
					{
						$apps->add($app[0]);
						if(!@$apps_before[$app[0]] || @$apps_before == False)
						{
							$new_apps[] = $app[0];
						}
					}
				}
			}
			$apps->save_repository();

			// Set new account_lid, if needed
			if($old_group_name <> $n_group)
			{
				$group->data['account_lid'] = $n_group;
			}

			// Set group acl
			$acl = CreateObject('phpgwapi.acl',$group_id);
			$acl->read_repository();
			$old_group_list = $acl->get_ids_for_location($group_id,1,'phpgw_group');
			@reset($old_group_list);
			while($old_group_list && $user_id = each($old_group_list))
			{
				$acl->delete_repository('phpgw_group',$group_id,$user_id[1]);
			}

			for ($i=0; $i<count($n_users);$i++)
			{
				$acl->add_repository('phpgw_group',$group_id,$n_users[$i],1);

				// If the user is logged in, it will force a refresh of the session_info
				$phpgw->db->query("update phpgw_sessions set session_action='' "
					."where session_lid='" . $phpgw->accounts->id2name(intval($n_users[$i])) . "@" . $phpgw_info["user"]["domain"] . "'",__LINE__,__FILE__);

				// The following sets any default preferences needed for new applications..
				// This is smart enough to know if previous preferences were selected, use them.
				$docommit = False;
				if($new_apps)
				{
					$pref = CreateObject('phpgwapi.preferences',intval($n_users[$i]));
					$t = $pref->read_repository();

					for ($j=1;$j<count($new_apps) - 1;$j++)
					{
						if($new_apps[$j]=='admin')
						{
							$check = 'common';
						}
						else
						{
							$check = $new_apps[$j];
						}
						if (!$t[$check])
						{
							$phpgw->common->hook_single('add_def_pref', $new_apps[$j]);
							$docommit = True;
						}
					}
				}
				if ($docommit)
				{
					$pref->save_repository();
				}

				// This is down here so we are sure to catch the acl changes
				// for LDAP to update the memberuid attribute
				$group->save_repository();
			}

			// Update any other options here, since the above save_repository () depends
			// on a group having users
			$group->data['file_space'] = $account_file_space_number . "-" . $account_file_space_type;
			$group->save_repository ();

			if ($old_group_name <> $n_group)
			{
				$basedir = $phpgw_info['server']['files_dir'] . SEP . 'groups' . SEP;
				if (! @rename($basedir . $old_group_name, $basedir . $n_group))
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

			$phpgw->db->unlock();

			Header('Location: ' . $phpgw->link('/admin/groups.php','cd='.$cd));
			$phpgw->common->phpgw_exit();
		}
	}

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_file(array('form' => 'group_form.tpl'));

	if ($error)
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();
		$p->set_var('error','<p><center>'.$error.'</center>');
	}
	else
	{
		$p->set_var('error','');
	}

	if ($submit)
	{
	//     $p->set_var('group_name_value',$n_group_name);

		for ($i=0; $i<count($n_users); $i++)
		{
			$selected_users[$n_user[$i]] = ' selected';
		}
	}
	else
	{
		$group_user = $phpgw->acl->get_ids_for_location($group_id,1,'phpgw_group');

		if (!$group_user) { $group_user = array(); }
		while ($user = each($group_user))
		{
			$selected_users[intval($user[1])] = ' selected';
		}

		$apps = CreateObject('phpgwapi.applications',intval($group_id));
		$db_perms = $apps->read_account_specific();
	}

	$p->set_var('form_action',$phpgw->link('/admin/editgroup.php'));
	$p->set_var('hidden_vars','<input type="hidden" name="group_id" value="' . $group_id . '">');

	$p->set_var('lang_group_name',lang('group name'));
	$p->set_var('group_name_value',$phpgw->accounts->id2name($group_id));

	$accounts = CreateObject('phpgwapi.accounts',$group_id);
	$account_list = $accounts->get_list('accounts');
	$account_num = count($account_list);

	if ($account_num < 5)
	{
		$p->set_var('select_size',$account_num);
	}
	else
	{
		$p->set_var('select_size','5');
	}

	$p->set_var('lang_include_user',lang('Select users for inclusion'));

	while (list($key,$entry) = each($account_list))
	{
		$user_list .= '<option value="' . $entry['account_id'] . '"'
			. $selected_users[intval($entry['account_id'])] . '>'
			. $phpgw->common->display_fullname(
				$entry['account_lid'],
				$entry['account_firstname'],
				$entry['account_lastname'])
			. '</option>'."\n";
	}
	$p->set_var('user_list',$user_list);

	$group_repository = $accounts->read_repository ();
	if (!$group_repository['file_space'])
	{
		$group_repository['file_space'] = $phpgw_info['server']['vfs_default_account_size_number'] . "-" . $phpgw_info['server']['vfs_default_account_size_type'];
	}
	/*
	$file_space_array = explode ("-", $group_repository['file_space']);
	$account_file_space_number = $file_space_array[0];
	$account_file_space_type = $file_space_array[1];
	$account_file_space_type_selected[$account_file_space_type] = "selected";

	$account_file_space = '
		<input type=text name="account_file_space_number" value="' . trim($account_file_space_number) . '" size="7">';
	$account_file_space_select ='<select name="account_file_space_type">';
	$account_file_space_types = array ("gb", "mb", "kb", "b");
	while (list ($num, $type) = each ($account_file_space_types))
	{
		$account_file_space_select .= "<option value=$type " . $account_file_space_type_selected[$type] . ">" . strtoupper ($type) . "</option>";
	}
	$account_file_space_select .= '</select>';

	$p->set_var ('lang_file_space', "File space");
	$p->set_var ('account_file_space', $account_file_space);
	$p->set_var ('account_file_space_select', $account_file_space_select);
	*/
	$p->set_var('lang_permissions',lang('Permissions this group has'));

	$i = 0;
	reset($phpgw_info['apps']);
	$sorted_apps = $phpgw_info['apps'];
	@asort($sorted_apps);
	@reset($sorted_apps);
	while ($permission = each($sorted_apps))
	{
		if ($permission[1]['enabled'] && $permission[1]['status'] != 3)
		{
			$perm_display[$i][0] = $permission[0];
			$perm_display[$i][1] = $permission[1]['title'];
			$i++;
		}
	}

	$perm_html = '';
	$tr_color = $phpgw_info['theme']['row_off'];
	for ($i=0;$perm_display[$i][1];$i++)
	{
		$app = $perm_display[$i][0];
		if(!($i & 1))
		{
			$tr_color = $phpgw->nextmatchs->alternate_row_color();
			$perm_html .= '<tr bgcolor="'.$tr_color.'">';
		}
		$perm_html .= '<td width="40%">' . lang($perm_display[$i][1]) . '</td>'
			. '<td width="5%"><input type="checkbox" name="n_group_permissions['
			. $perm_display[$i][0] . ']" value="True"';
		if ($n_group_permissions[$app] || $db_perms[$app])
		{
			$perm_html .= ' checked';
		}
		$perm_html .= '></td><td width="5%">';
		if($apps_with_acl[$app])
		{
			$perm_html .= '<a href="'.$phpgw->link('/preferences/acl_preferences.php','acl_app='.$app.'&owner='.$group_id).'" target="_blank"><img src="'.$phpgw->common->image('admin','dot.gif').'" border="0" hspace="3" align="absmiddle" alt="'.lang('Grant Access').'"></a>';
		}
		else
		{
			$perm_html .= '&nbsp;';
		}
		$perm_html .= '</td>';
		if($i & 1)
		{
			$perm_html .= '</tr>';
		}
	}
	if($i & 1)
	{
		$perm_html .= '<td colspan="4">&nbsp;</td></tr>';
	}

	$p->set_var('permissions_list',$perm_html);	
	$p->set_var('lang_submit_button',lang('submit changes'));
	$p->pfp('out','form');

	$phpgw->common->phpgw_footer();
?>
