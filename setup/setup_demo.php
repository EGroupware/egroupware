<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	// Little file to setup a demo install

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => "home",
		'noapi'      => True
	);
	include('./inc/functions.inc.php');
	include('../header.inc.php');

	// Authorize the user to use setup app and load the database
	// Does not return unless user is authorized
	if (!$phpgw_setup->auth('Config') || $HTTP_POST_VARS['cancel'])
	{
		Header('Location: index.php');
		exit;
	}

	function add_account($username,$first,$last,$passwd,$type='u')
	{
		$account_info = array(
			'account_type'      => $type,
			'account_lid'       => $username,
			'account_passwd'    => $passwd,
			'account_firstname' => $first,
			'account_lastname'  => $last,
			'account_status'    => 'A',
			'account_expires'   => -1
		);
		$GLOBALS['phpgw']->accounts->create($account_info);

		return $GLOBALS['phpgw']->accounts->name2id($username);
	}

	if (!$HTTP_POST_VARS['submit'])
	{
		$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
		$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
		$setup_tpl->set_file(array(
			'T_head'       => 'head.tpl',
			'T_footer'     => 'footer.tpl',
			'T_alert_msg'  => 'msg_alert_msg.tpl',
			'T_login_main' => 'login_main.tpl',
			'T_login_stage_header' => 'login_stage_header.tpl',
			'T_setup_demo' => 'setup_demo.tpl'
		));
		$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
		$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');

		$phpgw_setup->show_header(lang('Demo Server Setup'));

		$setup_tpl->set_var('action_url','setup_demo.php');
		$setup_tpl->set_var('description',lang('This will create 1 admin account and 3 demo accounts<br>The username/passwords are: demo/guest, demo2/guest and demo3/guest.<br><b>!!!THIS WILL DELETE ALL EXISTING ACCOUNTS!!!</b><br>')
			. ' '. lang('(account deletion in SQL only)'
		));
		$setup_tpl->set_var('detailadmin',lang('Details for Admin account'));
		$setup_tpl->set_var('adminusername',lang('Admin username'));
		$setup_tpl->set_var('adminfirstname',lang('Admin first name'));
		$setup_tpl->set_var('adminlastname',lang('Admin last name'));
		$setup_tpl->set_var('adminpassword',lang('Admin password'));
		$setup_tpl->set_var('adminpassword2',lang('Re-enter password'));
		$setup_tpl->set_var('create_demo_accounts',lang('Create demo accounts'));

		$setup_tpl->set_var('lang_submit',lang('submit'));
		$setup_tpl->set_var('lang_cancel',lang('cancel'));
		$setup_tpl->pparse('out','T_setup_demo');
		$phpgw_setup->show_footer();
	}
	else
	{
		/* Posted admin data */
		$passwd   = $HTTP_POST_VARS['passwd'];
		$passwd2  = $HTTP_POST_VARS['passwd2'];
		$username = $HTTP_POST_VARS['username'];
		$fname    = $HTTP_POST_VARS['fname'];
		$lname    = $HTTP_POST_VARS['lname'];

		if ($passwd != $passwd2)
		{
			echo lang('Passwords did not match, please re-enter') . '.';
			exit;
		}
		if (!$username)
		{
			echo lang('You must enter a username for the admin') . '.';
			exit;
		}

		$phpgw_setup->loaddb();
		/* Load up some configured values */
		$phpgw_setup->db->query("SELECT config_name,config_value FROM phpgw_config WHERE config_name LIKE 'ldap%' OR config_name='account_repository'",__LINE__,__FILE__);
		while ($phpgw_setup->db->next_record())
		{
			$config[$phpgw_setup->db->f('config_name')] = $phpgw_setup->db->f('config_value');
		}
		$GLOBALS['phpgw_info']['server']['ldap_host']          = $config['ldap_host'];
		$GLOBALS['phpgw_info']['server']['ldap_context']       = $config['ldap_context'];
		$GLOBALS['phpgw_info']['server']['ldap_group_context'] = $config['ldap_group_context'];
		$GLOBALS['phpgw_info']['server']['ldap_root_dn']       = $config['ldap_root_dn'];
		$GLOBALS['phpgw_info']['server']['ldap_root_pw']       = $config['ldap_root_pw'];
		$GLOBALS['phpgw_info']['server']['ldap_extra_attributes'] = $config['ldap_extra_attributes'];
		$GLOBALS['phpgw_info']['server']['ldap_account_home']  = $config['ldap_account_home'];
		$GLOBALS['phpgw_info']['server']['ldap_account_shell'] = $config['ldap_account_shell'];
		$GLOBALS['phpgw_info']['server']['ldap_encryption_type'] = $config['ldap_encryption_type'];
		$GLOBALS['phpgw_info']['server']['account_repository'] = $config['account_repository'];
		unset($config);

		/* Create dummy class, then accounts object */
		class phpgw
		{
			var $db;
			var $common;
			var $accounts;
		}
		$GLOBALS['phpgw'] = new phpgw;
		$GLOBALS['phpgw']->db       = $phpgw_setup->db;
		$GLOBALS['phpgw']->common   = CreateObject('phpgwapi.common');
		$GLOBALS['phpgw']->accounts = CreateObject('phpgwapi.accounts');
		if(($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap') &&
			!$GLOBALS['phpgw']->accounts->ds)
		{
			printf("<b>Error: Error connecting to LDAP server %s!</b><br>",$GLOBALS['phpgw_info']['server']['ldap_host']);
			exit;
		}

		/* Begin transaction for acl, etc */
		$phpgw_setup->db->transaction_begin();

		/* Now, clear out existing tables */
		$phpgw_setup->db->query('DELETE FROM phpgw_accounts');
		$phpgw_setup->db->query('DELETE FROM phpgw_preferences');
		$phpgw_setup->db->query('DELETE FROM phpgw_acl');

		$defaultprefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';

		/* Create the demo groups */
		$defaultgroupid = add_account('Default','Default','Group',$passwd,'g');
		$admingroupid   = add_account('Admins','Admin', 'Group',$passwd,'g');

		/* Creation of the demo accounts is now optional - the checkbox is on by default. */
		if ($HTTP_POST_VARS['create_demo'])
		{
			/* Create records for demo accounts */
			$accountid = add_account('demo','Demo','Account','guest');

			$phpgw_setup->db->query("INSERT INTO phpgw_preferences (preference_owner, preference_value) VALUES ('$accountid', '$defaultprefs')");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('preferences', 'changepassword', ".$accountid.", 0)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('addressbook', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('filemanager', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('calendar', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('email', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('notes', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('todo', 'run', ".$accountid.", 1)");

			$accountid = add_account('demo2','Demo2','Account','guest');

			$phpgw_setup->db->query("INSERT INTO phpgw_preferences (preference_owner, preference_value) VALUES ('$accountid', '$defaultprefs')");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('preferences', 'changepassword', ".$accountid.", 0)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_account, acl_location, acl_rights) VALUES ('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('addressbook', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('filemanager', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('calendar', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('email', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('notes', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('todo', 'run', ".$accountid.", 1)");

			$accountid = add_account('demo3','Demo3','Account','guest');

			$phpgw_setup->db->query("INSERT INTO phpgw_preferences (preference_owner, preference_value) VALUES ('$accountid', '$defaultprefs')");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('preferences', 'changepassword', ".$accountid.", 0)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('addressbook', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('filemanager', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('calendar', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('email', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('notes', 'run', ".$accountid.", 1)");
			$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('todo', 'run', ".$accountid.", 1)");
		}

		/* Create records for administrator account */
		$accountid = add_account($username,$fname,$lname,$passwd);

		$phpgw_setup->db->query("INSERT INTO phpgw_preferences (preference_owner, preference_value) VALUES ('$accountid', '$defaultprefs')");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('phpgw_group', '".$admingroupid."', $accountid, 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('preferences', 'changepassword', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('admin', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('addressbook', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('filemanager', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('calendar', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('email', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('notes', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('nntp', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('todo', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("INSERT INTO phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) VALUES ('manual', 'run', ".$accountid.", 1)");

		$phpgw_setup->db->transaction_commit();

		Header('Location: index.php');
		exit;
	}
?>
