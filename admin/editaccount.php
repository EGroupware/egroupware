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
		'noheader'          => True,
		'nonavbar'          => True,
		'currentapp'        => 'admin',
		'parent_page'       => 'accounts.php',
		'enable_sbox_class' => True
	);
 
	include('../header.inc.php');

	// creates the html for the user data
	function createPageBody($_account_id,$_userData='',$_errors='')
	{
		global $phpgw, $phpgw_info, $t;

		$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
		$t->set_unknowns('remove');

		if ($phpgw_info['server']['ldap_extra_attributes'] && ($phpgw_info['server']['account_repository'] == 'ldap'))
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
			$account = CreateObject('phpgwapi.accounts',$_account_id);
			$userData = $account->read_repository();
			$userGroups = $account->membership($_account_id);
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

		$error_messages = '';
		if ($_errors) 
		{
			$error_messages = '<center>' . $phpgw->common->error_list($_errors) . '</center>';
		}

		$var = Array(
			'form_action'		=> $phpgw->link('/admin/editaccount.php','account_id='.$_account_id.'&old_loginid='.rawurlencode($userData['account_lid'])),
			'error_messages'	=> $error_messages,
			'th_bg'			=> $phpgw_info['theme']['th_bg'],
			'tr_color1'		=> $phpgw_info['theme']['row_on'],
			'tr_color2'		=> $phpgw_info['theme']['row_off'],
			'lang_action'		=> lang('Edit user account'),
			'lang_loginid'		=> lang('LoginID'),
			'lang_account_active'	=> lang('Account active'),
			'lang_password'	=> lang('Password'),
			'lang_reenter_password'	=> lang('Re-Enter Password'),
			'lang_lastname'	=> lang('Last Name'),
			'lang_groups'		=> lang('Groups'),
			'lang_expires'		=> lang('Expires'),
			'lang_firstname'	=> lang('First Name'),
			'lang_button'		=> lang('Save')
			/* 'lang_file_space'	=> lang('File Space') */
		);
		$t->set_var($var);
		$t->parse('form_buttons','form_buttons_',True);

		if ($phpgw_info['server']['ldap_extra_attributes']) {
			$lang_homedir = lang('home directory');
			$lang_shell = lang('login shell');
			$homedirectory = '<input name="homedirectory" value="' . $userData['homedirectory']. '">';
			$loginshell = '<input name="loginshell" value="' . $userData['loginshell']. '">';
		}
		else
		{
			$lang_homedir = '';
			$lang_shell = '';
			$homedirectory = '';
			$loginshell = '';
		}

		$_y = $phpgw->sbox->getyears('account_expires_year',$userData['account_expires_year'],date('Y'),date('Y')+10);
		$_m = $phpgw->sbox->getmonthtext('account_expires_month',$userData['account_expires_month']);
		$_d = $phpgw->sbox->getdays('account_expires_day',$userData['account_expires_day']);

		/*
		if (!$userData['file_space'])
		{
			$userData['file_space'] = $phpgw_info['server']['vfs_default_account_size_number'] . "-" . $phpgw_info['server']['vfs_default_account_size_type'];
		}
		$file_space_array = explode ("-", $userData['file_space']);
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

		$t->set_var ('lang_file_space', "File space");
		$t->set_var ('account_file_space', $account_file_space);
		$t->set_var ('account_file_space_select', $account_file_space_select);
		*/

		$var = Array(
			'input_expires'	=> $phpgw->common->dateformatorder($_y,$_m,$_d,True),
			'account_lid'	=> '<input name="account_lid" value="' . $userData['account_lid'] . '">',
			'lang_homedir'	=> $lang_homedir,
			'lang_shell'	=> $lang_shell,
			'homedirectory'	=> $homedirectory,
			'loginshell'	=> $loginshell,
			'account_passwd'	=> $account_passwd,
			'account_passwd_2'	=> $account_passwd_2,
			'account_file_space'	=> $account_file_space
		);
		$t->set_var($var);
		$t->parse('password_fields','form_passwordinfo',True);

		if ($userData['status']) 
		{
			$account_status = '<input type="checkbox" name="account_status" value="A" checked>';
		}
		else
		{
			$account_status = '<input type="checkbox" name="account_status" value="A">';
		}

		$allAccounts;
		$userGroups;

		$groups_select = '<select name="account_groups[]" multiple>';
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
			$groups_select .= '>' . $value['account_lid'] . '</option>';
		}
		$groups_select .= '</select>';

		/* create list of available apps */
		$i = 0;

		$apps = CreateObject('phpgwapi.applications',$_account_id);
		$db_perms = $apps->read_account_specific();

		@reset($phpgw_info['apps']);
		$availableApps = $phpgw_info['apps'];
		@asort($availableApps);
		@reset($availableApps);
		while ($application = each($availableApps)) 
		{
			if ($application[1]['enabled'] && $application[1]['status'] != 3) 
			{
				$perm_display[$i]['appName']        = $application[0];
				$perm_display[$i]['translatedName'] = $application[1]['title'];
				$i++;
			}
		}

		/* create apps output */
		@reset($db_perms);
		for ($i=0;$i<count($perm_display);$i++) 
		{
			$checked = '';
			if ($userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]) 
			{
				$checked = ' checked';
			}

			if ($perm_display[$i]['translatedName'])
			{
				$part1 = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s></td>',
					lang($perm_display[$i]['translatedName']),
					$perm_display[$i]['appName'],
					$checked);
			}

			$i++;			

			$checked = '';
			if ($userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']])
			{
				$checked = ' checked';
			}

			if ($perm_display[$i]['translatedName'])
			{
				$part2 = sprintf('<td>%s</td><td><input type="checkbox" name="account_permissions[%s]" value="True"%s></td>',
					lang($perm_display[$i]['translatedName']),
					$perm_display[$i]['appName'],
					$checked);
			}
			else
			{
				$part2 = '<td colspan="2">&nbsp;</td>';
			}
			
			$appRightsOutput .= sprintf('<tr bgcolor="%s">%s%s</tr>',$phpgw_info['theme']['row_on'], $part1, $part2);
		}

		$var = Array(
			'account_status'		=> $account_status,
			'account_firstname'	=> '<input name="account_firstname" value="' . $userData['firstname'] . '">',
			'account_lastname'	=> '<input name="account_lastname" value="' . $userData['lastname'] . '">',
			'groups_select'		=> $groups_select,
			'permissions_list'	=> $appRightsOutput
		);
		$t->set_var($var);
		
		$phpgw->common->hook('edit_account');

		echo $t->fp('out','form');
	}

	/* stores the userdata */
	function saveUserData($_userData)
	{
		global $phpgw;

		$account = CreateObject('phpgwapi.accounts',$_userData['account_id']);
		$account->update_data($_userData);
		$account->save_repository();
		if ($_userData['account_passwd'])
		{
			$auth = CreateObject('phpgwapi.auth');
			$auth->change_password($old_passwd, $_userData['account_passwd'], $_userData['account_id']);
		}

		$apps = CreateObject('phpgwapi.applications',array(intval($_userData['account_id']),'u'));

		$apps->account_type = 'u';
		$apps->account_id = $_userData['account_id'];
		$apps->account_apps = Array(Array());
		if ($_userData['account_permissions'])
		{
			while($app = each($_userData['account_permissions'])) 
			{
				if($app[1]) 
				{
					$apps->add($app[0]);
					if(!$apps_before[$app[0]]) 
					{
						$apps_after[] = $app[0];
					}
				}
			}
		}
		$apps->save_repository();

		$account = CreateObject('phpgwapi.accounts');
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
		$phpgw->session->delete_cache(intval($_userData['account_id']));
	}

	/* checks if the userdata are valid
	 returns FALSE if the data are correct
	 otherwise the error array
	*/
	function userDataInvalid(&$_userData)
	{
		global $phpgw,$phpgw_info;

		$totalerrors = 0;

		if ($phpgw_info['server']['account_repository'] == 'ldap' && ! $allow_long_loginids)
		{
			if (strlen($_userData['account_lid']) > 8) 
			{
				$error[$totalerrors] = lang('The loginid can not be more then 8 characters');
				$totalerrors++;
			}
		}

		if ($_userData['old_loginid'] != $_userData['account_lid']) 
		{
			if ($phpgw->accounts->exists($_userData['account_lid']))
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
			}
		}
		else
		{
			$_userData['expires'] = -1;
		}

		/*
		$check_account_file_space = explode ("-", $_userData['file_space']);
		if (preg_match ("/\D/", $check_account_file_space[0]))
		{
			$error[$totalerrors++] = lang ('File space must be an integer');
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

 	function section_item($pref_link='',$pref_text='', $bgcolor)
	{
		global $phpgw, $phpgw_info, $t;

		$t->set_var('row_link',$pref_link);
		$t->set_var('row_text',$pref_text);
		$t->set_var('tr_color',$bgcolor);
		$t->parse('rows','link_row',True);
	}

	// $file must be in the follow format:
	// $file = Array(
	//		'Login History' => array('/index.php','menuaction=admin.uiaccess_history.list')
	// );
	// This allows extra data to be sent along
	function display_section($appname,$title,$file)
	{
		global $phpgw, $phpgw_info, $account_id;

		$i = 0;
		$color[1] = $phpgw_info['theme']['row_off'];
		$color[0] = $phpgw_info['theme']['row_on'];
		while(list($text,$_url) = each($file))
		{
			list($url,$extra_data) = $_url;
			if ($extra_data)
			{
				$link = $phpgw->link($url,'account_id=' . $account_id . '&' . $extra_data);
			}
			else
			{
				$link = $phpgw->link($url,'account_id=' . $account_id);
			}
			section_item($link,lang($text),$color[$i%2]);
			$i++;
		}
	}

	// todo
	// not needed if i use the same file for new users too
	if (! $account_id)
	{
		Header('Location: ' . $phpgw->link('/admin/accounts.php'));
	}

	if ($submit)
	{
		$userData = array(
			'account_lid'           => $account_lid,
			'firstname'             => $account_firstname,
			'lastname'              => $account_lastname,
			'account_passwd'        => $account_passwd,
			'status'                => $account_status,
			'old_loginid'           => rawurldecode($old_loginid),
			'account_id'            => $account_id,
			'account_passwd_2'      => $account_passwd_2,
			'account_groups'        => $account_groups,
			'account_permissions'   => $account_permissions,
			'homedirectory'         => $homedirectory,
			'loginshell'            => $loginshell,
			'account_expires_month' => $account_expires_month,
			'account_expires_day'   => $account_expires_day,
			'account_expires_year'  => $account_expires_year
			/* 'file_space'	=> $account_file_space_number . "-" . $account_file_space_type */
		);

		if (!$errors = userDataInvalid($userData))
		{ 
			saveUserData($userData);
			Header('Location: ' . $phpgw->link('/admin/accounts.php', 'cd='.$cd));
			$phpgw->common->phpgw_exit();
		}
		else
		{
			$phpgw->common->phpgw_header();
			echo parse_navbar();

			createPageBody($userData['account_id'],$userData,$errors);

			$phpgw->common->phpgw_footer();
		}
	}
	else
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		createPageBody($account_id);

		$phpgw->common->phpgw_footer();
	}	
	return;

	/////////////////////////////////////////////////////////////////////////////////////////
	//
	//			the old code
	//
	/////////////////////////////////////////////////////////////////////////////////////////

	// The following sets any default preferences needed for new applications..
	// This is smart enough to know if previous preferences were selected, use them.

	$pref = CreateObject('phpgwapi.preferences',intval($account_id));
	$t = $pref->get_preferences();

	$docommit = False;
	$after_apps = explode(':',$apps_after);
	for ($i=1;$i<count($after_apps) - 1;$i++)
	{
		if ($after_apps[$i]=='admin')
		{
			$check = 'common';
		}
		else
		{
			$check = $after_apps[$i];
		}

		if (! $t[$check])
		{
			$phpgw->common->hook_single('add_def_pref', $after_apps[$i]);
			$docommit = True;
		}
	}

	if ($docommit)
	{
		$pref->commit();
	}

	// start including other admin tools
	while ($app = each($apps_after))
	{
		$phpgw->common->hook_single('update_user_data', $app[0]);
	}

	$includedSomething = False;
	// start including other admin tools
	while($app = each($apps_after))
	{
		$phpgw->common->hook_single('show_user_data', $app[0]);
	}
?>
