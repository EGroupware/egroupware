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
		'currentapp' => 'home',
		'noapi'      => True
	);
	include('./inc/functions.inc.php');

	// Authorize the user to use setup app and load the database
	// Does not return unless user is authorized
	if(!$GLOBALS['phpgw_setup']->auth('Config') || get_var('cancel',Array('POST')))
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

	if(!get_var('submit',Array('POST')))
	{
		$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
		$setup_tpl = CreateObject('setup.Template',$tpl_root);
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

		$GLOBALS['phpgw_setup']->html->show_header(lang('Demo Server Setup'));

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
		$GLOBALS['phpgw_setup']->html->show_footer();
	}
	else
	{
		/* Posted admin data */
		$passwd   = get_var('passwd',Array('POST'));
		$passwd2  = get_var('passwd2',Array('POST'));
		$username = get_var('username',Array('POST'));
		$fname    = get_var('fname',Array('POST'));
		$lname    = get_var('lname',Array('POST'));

		if($passwd != $passwd2)
		{
			echo lang('Passwords did not match, please re-enter') . '.';
			exit;
		}
		if(!$username)
		{
			echo lang('You must enter a username for the admin') . '.';
			exit;
		}

		$GLOBALS['phpgw_setup']->loaddb();
		/* Load up some configured values */
		$GLOBALS['phpgw_setup']->db->query("SELECT config_name,config_value FROM phpgw_config WHERE config_name LIKE 'ldap%' OR config_name='account_repository'",__LINE__,__FILE__);
		while ($GLOBALS['phpgw_setup']->db->next_record())
		{
			$config[$GLOBALS['phpgw_setup']->db->f('config_name')] = $GLOBALS['phpgw_setup']->db->f('config_value');
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
		$GLOBALS['phpgw']->db       = $GLOBALS['phpgw_setup']->db;
		$GLOBALS['phpgw']->common   = CreateObject('phpgwapi.common');
		$GLOBALS['phpgw']->accounts = CreateObject('phpgwapi.accounts');
		if(($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap') &&
			!$GLOBALS['phpgw']->accounts->ds)
		{
			printf("<b>Error: Error connecting to LDAP server %s!</b><br>",$GLOBALS['phpgw_info']['server']['ldap_host']);
			exit;
		}

		/* Begin transaction for acl, etc */
		$GLOBALS['phpgw_setup']->db->transaction_begin();

		/* Now, clear out existing tables */
		$GLOBALS['phpgw_setup']->db->query('DELETE FROM phpgw_accounts');
		$GLOBALS['phpgw_setup']->db->query('DELETE FROM phpgw_preferences');
		$GLOBALS['phpgw_setup']->db->query('DELETE FROM phpgw_acl');

		$defaultprefs = 'a:3:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";s:0:"";s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";s:0:"";s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';

		/* Create the demo groups */
		$defaultgroupid = add_account('Default','Default','Group',$passwd,'g');
		$admingroupid   = add_account('Admins','Admin', 'Group',$passwd,'g');

		/* Group perms for the default group */
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('addressbook','run'," . $defaultgroupid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('filemanager','run'," . $defaultgroupid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('calendar','run'," . $defaultgroupid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('email','run'," . $defaultgroupid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('notes','run'," . $defaultgroupid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('todo','run'," . $defaultgroupid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('manual','run'," . $defaultgroupid . ", 1)");

		/* Creation of the demo accounts is optional - the checkbox is on by default. */
		if(get_var('create_demo',Array('POST')))
		{
			/* Create records for demo accounts */
			$accountid = add_account('demo','Demo','Account','guest');

			/* User permissions based on group membership with additional user perms for the messenger and infolog apps */
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_preferences(preference_owner,preference_value) VALUES('" . $accountid . "','" . $defaultprefs . "')");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('preferences','changepassword', " . $accountid . ",0)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('phpgw_group', '" . $defaultgroupid."'," . $accountid . ",1)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('messenger','run'," . $accountid . ", 1)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('infolog','run'," . $accountid . ", 1)");

			$accountid = add_account('demo2','Demo2','Account','guest');

			/* User permissions based solely on group membership */
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_preferences (preference_owner, preference_value) VALUES ('$accountid', '$defaultprefs')");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('preferences','changepassword', ".$accountid.", 0)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('phpgw_group','" . $defaultgroupid . "'," . $accountid . ",1)");

			$accountid = add_account('demo3','Demo3','Account','guest');

			/* User-specific perms, no group membership */
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_preferences(preference_owner,preference_value) VALUES('" . $accountid . "','" . $defaultprefs . "')");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('preferences','changepassword', " . $accountid . ",0)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('addressbook','run', " . $accountid . ", 1)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('calendar','run', " . $accountid . ", 1)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('notes','run', " . $accountid . ", 1)");
			$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('todo','run', " . $accountid . ", 1)");
		}

		/* Create records for administrator account */
		$accountid = add_account($username,$fname,$lname,$passwd);

		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_preferences (preference_owner, preference_value) VALUES('" . $accountid . "','" . $defaultprefs . "')");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('phpgw_group','" . $defaultgroupid."'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('phpgw_group','" . $admingroupid."'," . $accountid . ",1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('preferences','changepassword', " . $accountid . ",1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('admin','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('addressbook','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('filemanager','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('calendar','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('email','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('notes','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('nntp','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('todo','run'," . $accountid . ", 1)");
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('manual','run'," . $accountid . ", 1)");

		$GLOBALS['phpgw_setup']->db->transaction_commit();

		Header('Location: index.php');
		exit;
	}
?>
