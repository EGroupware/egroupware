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

	$phpgw_info['flags'] = array(
		'currentapp'        => 'admin',
		'noheader'          => True,
		'nonavbar'          => True,
		'parent_page'       => 'accounts.php',
		'enable_sbox_class' => True
	);
	include('../header.inc.php');

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

	if ($submit)
	{
		$totalerrors = 0;

		if ($phpgw_info['server']['account_repository'] == 'ldap' && ! $allow_long_loginids)
		{
			if (strlen($n_loginid) > 8)
			{
				$error[$totalerrors++] = lang('The loginid can not be more then 8 characters');
			}
		}

		if (! $account_lid)
		{
			$error[$totalerrors++] = lang('You must enter a loginid');
		}

		if (! $account_passwd)
		{
			$error[$totalerrors++] = lang('You must enter a password');
		}

		if ($account_passwd == $account_lid)
		{
			$error[$totalerrors++] = lang('The login and password can not be the same');
		}

		if ($account_passwd != $account_passwd_2)
		{
			$error[$totalerrors++] = lang('The two passwords are not the same');
		}

		if (!count($account_permissions) && !count($account_groups))
		{
			$error[$totalerrors++] = lang('You must add at least 1 permission or group to this account');
		}

		if ($phpgw->accounts->exists($account_lid))
		{
			$error[$totalerrors++] = lang('That loginid has already been taken');
		}

		if ($account_expires_month || $account_expires_day || $account_expires_year)
		{
			if (! checkdate($account_expires_month,$account_expires_day,$account_expires_year))
			{
				$error[] = lang('You have entered an invalid expiration date');
			}
			else
			{
				$account_expires = mktime(2,0,0,$account_expires_month,$account_expires_day,$account_expires_year);
			}
		}
		else
		{
			$account_expires = -1;
		}

		if (! $error)
		{
			$phpgw->db->lock(array(
				'phpgw_accounts',
				'phpgw_nextid',
				'phpgw_preferences',
				'phpgw_sessions',
				'phpgw_acl',
				'phpgw_applications'
			));

			$account_info = array(
				'account_type'      => 'u',
				'account_lid'       => $account_lid,
				'account_passwd'    => $account_passwd,
				'account_firstname' => $account_firstname,
				'account_lastname'  => $account_lastname,
				'account_status'    => $account_status,
				'account_expires'   => $account_expires
			);
			$phpgw->accounts->create($account_info);

			$account_id = $phpgw->accounts->name2id($account_lid);

			$apps = CreateObject('phpgwapi.applications',array($account_id,'u'));
			$apps->read_installed_apps();

			// Read Group Apps
			if ($account_groups)
			{
				$apps->account_type = 'g';
				reset($account_groups);
				while($groups = each($account_groups))
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
			$apps->account_id = $account_id;
			$apps->account_apps = Array(Array());

			if ($account_permissions)
			{
				@reset($account_permissions);
				while ($app = each($account_permissions))
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

			// This is not the correct way to do it ... Fix me
			$phpgw->db->query("insert into phpgw_acl values ('preferences','changepassword','$account_id','1')",__LINE__,__FILE__);

			// Assign user to groups
			if ($account_groups)
			{
				for ($i=0;$i<count($account_groups);$i++)
				{
					$phpgw->acl->add_repository('phpgw_group',$account_groups[$i],$account_id,1);
				}
			}

			if ($apps_after)
			{
				$pref = CreateObject('phpgwapi.preferences',$account_id);
				$phpgw->common->hook_single('add_def_pref','admin');
				while ($apps = each($apps_after))
				{
					if (strcasecmp ($apps[0], 'admin') != 0)
					{
						$phpgw->common->hook_single('add_def_pref', $apps[1]);
					}
				}
				$pref->save_repository(False);
			}

			$apps->account_apps = Array(Array());
			$apps_after = Array(Array());

			$phpgw->db->unlock();

/*
			// start inlcuding other admin tools
			while($app = each($apps_after))
			{
				$phpgw->common->hook_single('add_user_data', $value);
			}
*/
			Header('Location: ' . $phpgw->link('/admin/accounts.php','cd='.$cd));
			$phpgw->common->phpgw_exit();
		}
	}
	else
	{
		$account_status = 'A';
	}

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_unknowns('remove');

	if ($phpgw_info["server"]["ldap_extra_attributes"] && $phpgw_info['server']['account_repository'] == 'ldap')
	{
		$p->set_file(array('account' => 'account_form_ldap.tpl'));
	}
	else
	{
		$p->set_file(array('account' => 'account_form.tpl'));
	}
	$p->set_block('account','form','form');
	$p->set_block('account','form_passwordinfo','form_passwordinfo');
	$p->set_block('account','form_buttons_','form_buttons_');

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$p->set_var('lang_action',lang('Add new account'));

	if ($totalerrors)
	{
		$p->set_var('error_messages','<center>' . $phpgw->common->error_list($error) . '</center>');
	}

	$p->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$p->set_var('tr_color1',$phpgw_info['theme']['row_on']);
	$p->set_var('tr_color2',$phpgw_info['theme']['row_off']);

	$p->set_var('form_action',$phpgw->link('/admin/newaccount.php'));
	$p->set_var('lang_loginid',lang('LoginID'));

	if ($account_status) 
	{
		$p->set_var('account_status','<input type="checkbox" name="account_status" value="A" checked>');
	}
	else
	{
		$p->set_var('account_status','<input type="checkbox" name="account_status" value="A">');
	}

	$p->set_var('account_lid','<input name="account_lid" value="' . $account_lid . '">');

	$p->set_var('lang_account_active',lang('Account active'));

	$p->set_var('lang_password',lang('Password'));
	$p->set_var('account_passwd',$account_passwd);

	if ($phpgw_info["server"]["ldap_extra_attributes"]) {
		$p->set_var("lang_homedir",lang("home directory"));
		$p->set_var("lang_shell",lang(" login shell"));
		$p->set_var("homedirectory",'<input name="homedirectory" value="' . $phpgw_info["server"]["ldap_account_home"].SEP.$account_lid . '">');
		$p->set_var("loginshell",'<input name="loginshell" value="' . $phpgw_info["server"]["ldap_account_shell"] . '">');
	}

	$p->set_var('lang_reenter_password',lang('Re-Enter Password'));
	$p->set_var('account_passwd_2',$account_passwd_2);
	$p->parse('password_fields','form_passwordinfo',True);

	$p->set_var('lang_firstname',lang('First Name'));
	$p->set_var('account_firstname','<input name="account_firstname" value="' . $account_firstname . '">');

	$p->set_var('lang_lastname',lang('Last Name'));
	$p->set_var('account_lastname','<input name="account_lastname" value="' . $account_lastname . '">');

	$p->set_var('lang_groups',lang('Groups'));

	$p->set_var('lang_expires',lang('Expires'));
	$_y = $phpgw->sbox->getyears('account_expires_year',$account_expires_year,date('Y'),date('Y')+10);
	$_m = $phpgw->sbox->getmonthtext('account_expires_month',$account_expires_month);
	$_d = $phpgw->sbox->getdays('account_expires_day',$account_expires_day);
	$p->set_var('input_expires',$phpgw->common->dateformatorder($_y,$_m,$_d,True));

	$p->parse('form_buttons','form_buttons_',True);

	/* groups list */
	$groups_select = '<select name="account_groups[]" multiple>';

	$groups = $phpgw->accounts->get_list('groups');

	while (list(,$group) = each($groups))
	{
		$groups_select .= '<option value="' . $group['account_id'] . '"';
		while (list(,$ags) = @each($account_groups))
		{
			if ($group['account_id'] == $ags)
			{
				$groups_select .= ' selected';
			}
		}
		@reset($account_groups);

		$groups_select .= '>' . $group['account_lid'] . '</option>';
		$groups_select .= "\n";
	}
	$groups_select .= '</select>';
	$p->set_var('groups_select',$groups_select);
	/* end groups list */

	$i = 0;
	$phpgw->applications->read_installed_apps();
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

	// The $i<200 is only used for a brake
	for ($i=0;$i<200;)
	{
		if (! $perm_display[$i][1])
		{
			break;
		}

		$perms_html .= '<tr bgcolor="' . $phpgw_info['theme']['row_on'] . '"><td>' . lang($perm_display[$i][1]) . '</td>'
			. '<td><input type="checkbox" name="account_permissions['
			. $perm_display[$i][0] . ']" value="True"';

		if ($account_permissions[$perm_display[$i][0]])
		{
			$perms_html .= ' checked';
		}
		$perms_html .= '></td>';

		$i++;

		if ($i == count($perm_display) && is_odd(count($perm_display)))
		{
			$perms_html .= '<td colspan="2">&nbsp;</td></tr>';
		}
 
		if (! $perm_display[$i][1])
		{
			break;
		}
 
		$perms_html .= '<td>' . lang($perm_display[$i][1]) . '</td>'
			. '<td><input type="checkbox" name="account_permissions['
			. $perm_display[$i][0] . ']" value="True"';

		if ($account_permissions[$perm_display[$i][0]])
		{
			$perms_html .= ' checked';
		}
		$perms_html .= '></td></tr>';

		$i++;
	}
	$p->set_var('permissions_list',$perms_html);

	$includedSomething = False;

	// Skeeter: I don't see this as a player, if creating new accounts...
	// start inlcuding other admin tools
	//  while(list($key,$value) = each($phpgw_info["user"]["app_perms"]))
	//  {
	// check if we have something included, when not ne need to set
	// {gui_hooks} to ""
	//  	if ($phpgw->common->hook_single("show_newuser_data", $value)) $includedSomething="true";
	//  }

	$p->set_var('lang_button',Lang('Add'));
	$p->pfp('out','form');

	$phpgw->common->phpgw_footer();
?>
