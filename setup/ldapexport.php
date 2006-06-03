<?php
  /**************************************************************************\
  * eGroupWare - Setup                                                       *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noheader'   => True,
			'nonavbar'   => True,
			'currentapp' => 'home',
			'noapi'      => True
	));
	include('./inc/functions.inc.php');

	// Authorize the user to use setup app and load the database
	if (!$GLOBALS['egw_setup']->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	class egw
	{
		var $common;
		var $accounts;
		var $applications;
		var $db;
	}
	$egw =& new egw;
	$egw->common =& CreateObject('phpgwapi.common');

	$common =& $egw->common;
	$GLOBALS['egw_setup']->loaddb();
	$egw->db = clone($GLOBALS['egw_setup']->db);

	$tpl_root = $GLOBALS['egw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('setup.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'ldap'   => 'ldap.tpl',
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_alert_msg' => 'msg_alert_msg.tpl'
	));

	$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_name,config_value',array(
		"config_name LIKE 'ldap%'",
	),__LINE__,__FILE__);
	while ($GLOBALS['egw_setup']->db->next_record())
	{
		$GLOBALS['egw_info']['server'][$GLOBALS['egw_setup']->db->f('config_name')] = $GLOBALS['egw_setup']->db->f('config_value');
	}
	$GLOBALS['egw_info']['server']['account_repository'] = 'ldap';

	$egw->accounts     =& CreateObject('phpgwapi.accounts');
	$acct              =& $egw->accounts;

	// First, see if we can connect to the LDAP server, if not send `em back to config.php with an
	// error message.

	// connect to ldap server
	if(!$ldap = $common->ldapConnect())
	{
		$noldapconnection = True;
	}

	if($noldapconnection)
	{
		Header('Location: config.php?error=badldapconnection');
		exit;
	}

	// read all accounts & groups direct from SQL for export
	$group_info = $account_info = array();
	$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->accounts_table,'*',false,__LINE__,__FILE__);
	while(($row = $GLOBALS['egw_setup']->db->row(true)))
	{
		if ($row['account_type'] == 'u')	// account
		{
			$account_info[$row['account_id']] = $row;
		}
		else	// group
		{
			$row['account_id'] *= -1;	// group account_id is internally negative since 1.2
			$group_info[(string)$row['account_id']] = $row;
			
		}
	}

	if($_POST['cancel'])
	{
		Header('Location: ldap.php');
		exit;
	}
	$GLOBALS['egw_setup']->html->show_header(lang('LDAP Export'),False,'config',$GLOBALS['egw_setup']->ConfigDomain . '(' . $GLOBALS['egw_domain'][$GLOBALS['egw_setup']->ConfigDomain]['db_type'] . ')');
	
	if($_POST['submit'])
	{
		if($_POST['users'])
		{
			foreach($_POST['users'] as $accountid)
			{
				if (!isset($account_info[$accountid])) continue;

				$accounts =& CreateObject('phpgwapi.accounts',(int)$accountid);

				// check if user already exists in ldap
				if ($accounts->exists($accountid))
				{
					echo '<p>'.lang('%1 already exists in LDAP.',lang('User')." $accountid ({$account_info[$accountid]['account_lid']})")."</p>\n";
					continue;
				}
				$account_info[$accountid]['homedirectory'] = $GLOBALS['egw_info']['server']['ldap_account_home'] . '/' . $account_info[$accountid]['account_lid'];
				$account_info[$accountid]['loginshell'] = $GLOBALS['egw_info']['server']['ldap_account_shell'];

				if (!$accounts->create($account_info[$accountid]))
				{
					echo '<p>'.lang('Creation of %1 in LDAP failed !!!',lang('User')." $accountid ({$account_info[$accountid]['account_lid']})")."</p>\n";
					continue;
				}
				echo '<p>'.lang('%1 created in LDAP.',lang('User')." $accountid ({$account_info[$accountid]['account_lid']})")."</p>\n";
			}
		}
		if($_POST['ldapgroups'])
		{
			foreach($_POST['ldapgroups'] as $groupid)
			{
				if (!isset($group_info[$groupid])) continue;

				$groups =& CreateObject('phpgwapi.accounts',(int)$groupid);
				
				// check if group already exists in ldap
				if (!$groups->exists($groupid))
				{
					if (!$groups->create($group_info[$groupid]))
					{
						echo '<p>'.lang('Creation of %1 failed !!!',lang('Group')." $groupid ({$group_info[$groupid]['account_lid']})")."</p>\n";
						continue;
					}
					echo '<p>'.lang('%1 created in LDAP.',lang('Group')." $groupid ({$group_info[$groupid]['account_lid']})")."</p>\n";
				}
				else
				{
					echo '<p>'.lang('%1 already exists in LDAP.',lang('Group')." $groupid ({$group_info[$groupid]['account_lid']})")."</p>\n";

					if ($groups->id2name($groupid) != $group_info[$groupid]['account_lid'])
					{
						continue;	// different group under that gidnumber in ldap!
					}
				}
				// now saving / updating the memberships
				$groups->read_repository();
				if (!is_object($GLOBALS['egw']->acl))
				{
					$GLOBALS['egw']->acl =& CreateObject('phpgwapi.acl');
				}
				$groups->save_repository();
			}
		}
		$setup_complete = True;
	}


	if($error)
	{
		//echo '<br /><center><b>Error:</b> '.$error.'</center>';
		$GLOBALS['egw_setup']->html->show_alert_msg('Error',$error);
	}

	if($setup_complete)
	{
		echo '<br /><center>'.lang('Export has been completed!  You will need to set the user passwords manually.').'</center>';
		echo '<br /><center>'.lang('Click <a href="index.php">here</a> to return to setup.').'</center>';
		$GLOBALS['egw_setup']->html->show_footer();
		exit;
	}

	$setup_tpl->set_block('ldap','header','header');
	$setup_tpl->set_block('ldap','user_list','user_list');
	$setup_tpl->set_block('ldap','admin_list','admin_list');
	$setup_tpl->set_block('ldap','group_list','group_list');
	$setup_tpl->set_block('ldap','app_list','app_list');
	$setup_tpl->set_block('ldap','submit','submit');
	$setup_tpl->set_block('ldap','footer','footer');

	foreach($account_info as $account)
	{
		$user_list .= '<option value="' . $account['account_id'] . '" selected="1">'
			. $common->display_fullname($account['account_lid'],$account['account_firstname'],$account['account_lastname'])
			. '</option>';
	}

	foreach($group_info as $group)
	{
		$group_list .= '<option value="' . $group['account_id'] . '" selected="1">'
			. $group['account_lid']
			. '</option>';
	}

	$setup_tpl->set_var('action_url','ldapexport.php');
	$setup_tpl->set_var('users',$user_list);
	$setup_tpl->set_var('admins',$admin_list);
	$setup_tpl->set_var('ldapgroups',$group_list);
	$setup_tpl->set_var('s_apps',$app_list);

	$setup_tpl->set_var('ldap_import',lang('LDAP export users'));
	$setup_tpl->set_var('description',lang("This section will help you export users and groups from eGroupWare's account tables into your LDAP tree").'.');
	$setup_tpl->set_var('select_users',lang('Select which user(s) will be exported'));
	$setup_tpl->set_var('select_groups',lang('Select which group(s) will be exported (group membership will be maintained)'));
	$setup_tpl->set_var('form_submit','export');
	$setup_tpl->set_var('cancel',lang('Cancel'));

	$setup_tpl->pfp('out','header');
	if($account_info)
	{
		$setup_tpl->pfp('out','user_list');
	}
	if($group_info)
	{
		$setup_tpl->pfp('out','group_list');
	}
	$setup_tpl->pfp('out','submit');
	$setup_tpl->pfp('out','footer');

	$GLOBALS['egw_setup']->html->show_footer();
