<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	if (! $account_id)
	{
		$phpgw_info['flags'] = array(
			'nonavbar' => True,
			'noheader' => True
		);
	}

	$phpgw_info['flags']['enable_nextmatchs_class'] = True;
	$phpgw_info['flags']['currentapp']  = 'admin';
	$phpgw_info['flags']['parent_page'] = 'accounts.php';

	include('../header.inc.php');

	if (! $account_id)
	{
		Header('Location: ' . $phpgw->link('/admin/accounts.php'));
	}

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$t->set_unknowns('remove');
	$t->set_file(array('account' => 'account_form.tpl'));
	$t->set_block('account','form','form');
	$t->set_block('account','form_logininfo');
	$t->set_block('account','link_row');

	$t->set_var('th_bg',$phpgw_info['theme']['th_bg']);
	$t->set_var('tr_color1',$phpgw_info['theme']['row_on']);
	$t->set_var('tr_color2',$phpgw_info['theme']['row_off']);
	$t->set_var('lang_action',lang('View user account'));
	$t->set_var('lang_loginid',lang('LoginID'));
	$t->set_var('lang_account_active',lang('Account active'));
	$t->set_var('lang_password',lang('Password'));
	$t->set_var('lang_reenter_password',lang('Re-Enter Password'));
	$t->set_var('lang_lastname',lang('Last Name'));
	$t->set_var('lang_groups',lang('Groups'));
	$t->set_var('lang_firstname',lang('First Name'));
	$t->set_var('lang_lastlogin',lang('Last login'));
	$t->set_var('lang_lastloginfrom',lang('Last login from'));
	$t->set_var('lang_expires',lang('Expires'));

	$account = CreateObject('phpgwapi.accounts',$account_id);
	$userData = $account->read_repository();

	$t->set_var('account_lid',$userData['account_lid']);
	$t->set_var('account_firstname',$userData['firstname']);
	$t->set_var('account_lastname',$userData['lastname']);

	// Account status
	if ($userData['status'])
	{
		$t->set_var('account_status',lang('Enabled'));
	}
	else
	{
		$t->set_var('account_status','<b>' . lang('Disabled') . '</b>');
	}

	// Last login time
	if ($userData['lastlogin'])
	{
		$t->set_var('account_lastlogin',$phpgw->common->show_date($userData['lastlogin']));
	}
	else
	{
		$t->set_var('account_lastlogin',lang('Never'));
	}

	// Last login IP
	if ($userData['lastloginfrom'])
	{
		$t->set_var('account_lastloginfrom',$userData['lastloginfrom']);
	}
	else
	{
		$t->set_var('account_lastloginfrom',lang('Never'));
	}
	$t->parse('password_fields','form_logininfo',True);

	// Account expires
	if ($userData['expires'] != -1)
	{
		$t->set_var('input_expires',$phpgw->common->show_date($userData['expires']));
	}
	else
	{
		$t->set_var('input_expires',lang('Never'));
	}

	// Find out which groups they are members of
	$usergroups = $account->membership(intval($account_id));
	if (gettype($usergroups) != 'array')
	{
		$t->set_var('groups_select',lang('None'));
	}
	else
	{
		while (list(,$group) = each($usergroups))
		{
			$group_names[] = $group['account_name'];
		}
		$t->set_var('groups_select',implode(',',$group_names));
	}


	$loginid = $userData["account_lid"];
	$account_lastlogin      = $userData["account_lastlogin"];
	$account_lastloginfrom  = $userData["account_lastloginfrom"];
	$account_status	     = $userData["account_status"];


	// create list of available app
	$i = 0;
		
	$availableApps = $phpgw_info['apps'];
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
	$apps = CreateObject('phpgwapi.applications',intval($account_id));
	$db_perms = $apps->read_account_specific();

	@reset($db_perms);

	for ($i=0;$i<=count($perm_display);$i++)
	{
		$checked = '';
		if ($_userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]) 
		{
			$checked = '&nbsp;&nbsp;X';
		}
		else
		{
			$checked = '&nbsp;';
		}
			
		if ($perm_display[$i]['translatedName'])
		{
			$part1 = sprintf("<td>%s</td><td>%s</td>",lang($perm_display[$i]['translatedName']),$checked);
		}

		$i++;			
		
		if ($_userData['account_permissions'][$perm_display[$i]['appName']] || $db_perms[$perm_display[$i]['appName']]) 
		{
			$checked = '&nbsp;&nbsp;X';
		}
		else
		{
			$checked = '&nbsp;';
		}
			
		if ($perm_display[$i]['translatedName'])
		{
			$part2 = sprintf("<td>%s</td><td>%s</td>",lang($perm_display[$i]['translatedName']),$checked);
		}
		else
		{
			$part2 = '<td colspan="2">&nbsp;</td>';
		}
			
		$appRightsOutput .= sprintf("<tr bgcolor=\"%s\">$part1$part2</tr>\n",$phpgw_info["theme"]["row_on"]);
	}

	$t->set_var('permissions_list',$appRightsOutput);

	// create the menu on the left, if needed
	$menuClass = CreateObject('admin.uimenuclass');
	$t->set_var('rows',$menuClass->createHTMLCode('view_account'));
	
	$t->pfp('out','form');
	$phpgw->common->phpgw_footer();
?>
