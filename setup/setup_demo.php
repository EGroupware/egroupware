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

$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => "home",
		'noapi'      => True
	);
include('./inc/functions.inc.php');
include('../header.inc.php');

	// Authorize the user to use setup app and load the database
	// Does not return unless user is authorized
if (!$phpgw_setup->auth('Config'))
{
	Header('Location: index.php');
	exit;
}

if (!$submit)
{
	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
			'T_head' => 'head.tpl',
			'T_footer' => 'footer.tpl',
			'T_alert_msg' => 'msg_alert_msg.tpl',
			'T_login_main' => 'login_main.tpl',
			'T_login_stage_header' => 'login_stage_header.tpl',
			'T_setup_demo' => 'setup_demo.tpl'
		));
	$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
	$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');

	$phpgw_setup->show_header(lang('Demo Server Setup'));

	$setup_tpl->set_var('action_url','setup_demo.php');
	$setup_tpl->set_var('description',lang('This will create 1 admin account and 3 demo accounts<br>The username/passwords are: demo/guest, demo2/guest and demo3/guest.<br><b>!!!THIS WILL DELETE ALL EXISTING ACCOUNTS!!!</b><br>'));
	$setup_tpl->set_var('detailadmin',lang('Details for Admin account'));
	$setup_tpl->set_var('adminusername',lang('Admin username'));
	$setup_tpl->set_var('adminfirstname',lang('Admin first name'));
	$setup_tpl->set_var('adminlastname',lang('Admin last name'));
	$setup_tpl->set_var('adminpassword',lang('Admin password'));
	$setup_tpl->set_var('adminpassword2',lang('Re-enter password'));
	$setup_tpl->set_var('create_demo_accounts',lang('Create demo accounts'));

	$setup_tpl->set_var('lang_submit',lang('submit'));
	$setup_tpl->pparse('out','T_setup_demo');
	$phpgw_setup->show_footer();
}
else
{
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
	$phpgw_setup->db->transaction_begin();

		/* First clear out existing tables */
	$phpgw_setup->db->query("delete from phpgw_accounts");
	$phpgw_setup->db->query("delete from phpgw_preferences");
	$phpgw_setup->db->query("delete from phpgw_acl");
	$defaultprefs = 'a:5:{s:6:"common";a:10:{s:9:"maxmatchs";s:2:"15";s:12:"template_set";s:8:"verdilak";s:5:"theme";s:6:"purple";s:13:"navbar_format";s:5:"icons";s:9:"tz_offset";N;s:10:"dateformat";s:5:"m/d/Y";s:10:"timeformat";s:2:"12";s:4:"lang";s:2:"en";s:11:"default_app";N;s:8:"currency";s:1:"$";}s:11:"addressbook";a:1:{s:0:"";s:4:"True";}:s:8:"calendar";a:4:{s:13:"workdaystarts";s:1:"7";s:11:"workdayends";s:2:"15";s:13:"weekdaystarts";s:6:"Monday";s:15:"defaultcalendar";s:9:"month.php";}}';

	$sql = "insert into phpgw_accounts";
	$sql .= "(account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
	$sql .= "values ('Default', 'g', '".md5($passwd)."', 'Default', 'Group', ".time().", 'A')";
	$phpgw_setup->db->query($sql);
	$defaultgroupid = $phpgw_setup->db->get_last_insert_id('phpgw_accounts', 'account_id');

	$sql = "insert into phpgw_accounts";
	$sql .= "(account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
	$sql .= "values ('Admins', 'g', '".md5($passwd)."', 'Admin', 'Group', ".time().", 'A')";
	$phpgw_setup->db->query($sql);
	$admingroupid = $phpgw_setup->db->get_last_insert_id('phpgw_accounts', 'account_id');

		/* Creation of the demo accounts is now optional - the checkbox is on by default. */
	if ($create_demo)
	{
			/* Create records for demo accounts */
		$sql = "insert into phpgw_accounts";
		$sql .= "(account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
		$sql .= "values ('demo', 'u', '084e0343a0486ff05530df6c705c8bb4', 'Demo', 'Account', ".time().", 'A')";
		$phpgw_setup->db->query($sql);
		$accountid = $phpgw_setup->db->get_last_insert_id('phpgw_accounts', 'account_id');
		$phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 0)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', ".$accountid.", 1)");

		$sql = "insert into phpgw_accounts";
		$sql .= "(account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
		$sql .= "values ('demo2', 'u', '084e0343a0486ff05530df6c705c8bb4', 'Demo2', 'Account', ".time().", 'A')";
		$phpgw_setup->db->query($sql);
		$accountid = $phpgw_setup->db->get_last_insert_id('phpgw_accounts', 'account_id');
		$phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 0)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_account, acl_location, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', ".$accountid.", 1)");

		$sql = "insert into phpgw_accounts";
		$sql .= "(account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
		$sql .= "values ('demo3', 'u', '084e0343a0486ff05530df6c705c8bb4', 'Demo3', 'Account', ".time().", 'A')";
		$phpgw_setup->db->query($sql);
		$accountid = $phpgw_setup->db->get_last_insert_id('phpgw_accounts', 'account_id');
		$phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 0)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', ".$accountid.", 1)");
		$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', ".$accountid.", 1)");
	}

		/* Create records for administrator account */
	$sql = "insert into phpgw_accounts";
	$sql .= "(account_lid, account_type, account_pwd, account_firstname, account_lastname, account_lastpwd_change, account_status)";
	$sql .= "values ('$username', 'u', '".md5($passwd)."', '$fname', '$lname', ".time().", 'A')";
	$phpgw_setup->db->query($sql);
	$accountid = $phpgw_setup->db->get_last_insert_id('phpgw_accounts', 'account_id');
	$phpgw_setup->db->query("insert into phpgw_preferences (preference_owner, preference_value) values ('$accountid', '$defaultprefs')");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '".$defaultgroupid."', $accountid, 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('phpgw_group', '".$admingroupid."', $accountid, 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights)values('preferences', 'changepassword', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('admin', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('addressbook', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('filemanager', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('calendar', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('email', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('notes', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('nntp', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('todo', 'run', ".$accountid.", 1)");
	$phpgw_setup->db->query("insert into phpgw_acl (acl_appname, acl_location, acl_account, acl_rights) values('manual', 'run', ".$accountid.", 1)");

	$phpgw_setup->db->query("update phpgw_accounts set account_expires='-1'",__LINE__,__FILE__);
	$phpgw_setup->db->transaction_commit();

	Header("Location: index.php");
	exit;
}
?>
