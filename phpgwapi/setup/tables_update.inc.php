<?php
	/**************************************************************************\
	* phpGroupWare - Setup                                                     *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$
	// $Source$

	/* Include older phpGroupWare update support */
	include('tables_update_0_9_9.inc.php');
	include('tables_update_0_9_10.inc.php');
	include('tables_update_0_9_12.inc.php');

	/* This is since the last release */
	$test[] = '0.9.12';
	function phpgwapi_upgrade0_9_12()
	{
		global $setup_info,$phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.13.001';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.001';
	function phpgwapi_upgrade0_9_13_001()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_categories','cat_access', array('type' => 'varchar', 'precision' => 7));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.002';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.002';
	function phpgwapi_upgrade0_9_13_002()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_accounts','account_file_space', array ('type' => 'varchar', 'precision' => 25));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.003';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.003';
	function phpgwapi_upgrade0_9_13_003()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_access_log','sessionid',array('type' => 'char', 'precision' => 32));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.004';
		return $setup_info['phpgwapi']['currentver'];
	}

	class phpgw
	{
		var $common;
		var $accounts;
		var $applications;
		var $db;
	}
	$test[] = '0.9.13.004';
	function phpgwapi_upgrade0_9_13_004()
	{
		global $setup_info, $phpgw_setup, $phpgw_info, $phpgw;

		$phpgw_setup->oProc->AddColumn('phpgw_access_log','account_id',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

		$phpgw = new phpgw;
		$phpgw->common = CreateObject('phpgwapi.common');

		$phpgw_setup->oProc->query("SELECT config_name,config_value FROM phpgw_config WHERE config_name LIKE 'ldap%' OR config_name='account_repository'",__LINE__,__FILE__);
		while ($phpgw_setup->oProc->next_record())
		{
			$config[$phpgw_setup->oProc->f('config_name')] = $phpgw_setup->oProc->f('config_value');
		}
		$phpgw_info['server']['ldap_host']          = $config['ldap_host'];
		$phpgw_info['server']['ldap_context']       = $config['ldap_context'];
		$phpgw_info['server']['ldap_group_context'] = $config['ldap_group_context'];
		$phpgw_info['server']['ldap_root_dn']       = $config['ldap_root_dn'];
		$phpgw_info['server']['ldap_root_pw']       = $config['ldap_root_pw'];
		$phpgw_info['server']['account_repository'] = $config['account_repository'];

		$accounts = CreateObject('phpgwapi.accounts');
		$accounts->db = $phpgw_setup->db;

		$phpgw_setup->oProc->query("select * from phpgw_access_log");
		while ($phpgw_setup->oProc->next_record())
		{
			$lid         = explode('@',$phpgw_setup->oProc->f('loginid'));
			$account_lid = $lid[0];
			$account_id = $accounts->name2id($account_lid);

			$phpgw_setup->db->query("update phpgw_access_log set account_id='" . $account_id
				. "' where sessionid='" . $phpgw_setup->oProc->f('sessionid') . "'");
		}

		$setup_info['phpgwapi']['currentver'] = '0.9.13.005';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.005';
	function phpgwapi_upgrade0_9_13_005()
	{
		global $setup_info, $phpgw_setup;

		$newtbldef = array(
			'fd' => array(
				'account_id' => array('type' => 'auto', 'nullable' => false),
				'account_lid' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'account_pwd' => array('type' => 'varchar', 'precision' => 32, 'nullable' => false),
				'account_firstname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastname' => array('type' => 'varchar', 'precision' => 50),
				'account_permissions' => array('type' => 'text'),
				'account_groups' => array('type' => 'varchar', 'precision' => 30),
				'account_lastlogin' => array('type' => 'int', 'precision' => 4),
				'account_lastloginfrom' => array('type' => 'varchar', 'precision' => 255),
				'account_lastpwd_change' => array('type' => 'int', 'precision' => 4),
				'account_status' => array('type' => 'char', 'precision' => 1, 'nullable' => false, 'default' => 'A'),
				'account_expires' => array('type' => 'int', 'precision' => 4),
				'account_type' => array('type' => 'char', 'precision' => 1, 'nullable' => true)
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		);

		$phpgw_setup->oProc->DropColumn('phpgw_accounts',$newtbldef,'account_file_space');

		$setup_info['phpgwapi']['currentver'] = '0.9.13.006';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.006';
	function phpgwapi_upgrade0_9_13_006()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_log', array(
				'fd' => array(
					'log_id' 	=> array('type' => 'auto',      'precision' => 4,  'nullable' => False),
					'log_date' 	=> array('type' => 'timestamp', 'nullable' => False),
					'log_user' 	=> array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'log_app' 	=> array('type' => 'varchar',   'precision' => 50, 'nullable' => False),
					'log_severity' 	=> array('type' => 'char',  'precision' => 1,  'nullable' => False)
				),
				'pk' => array('log_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$phpgw_setup->oProc->CreateTable(
			'phpgw_log_msg', array(
				'fd' => array(
					'log_msg_log_id' 	=> array('type' => 'auto',      'precision' => 4,  'nullable' => False),
					'log_msg_seq_no'	=> array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'log_msg_date'		=> array('type' => 'timestamp',	'nullable' => False),
					'log_msg_tx_fid'	=> array('type' => 'varchar',   'precision' => 4,  'nullable' => True),
					'log_msg_tx_id'		=> array('type' => 'varchar',   'precision' => 4,  'nullable' => True),
					'log_msg_severity'	=> array('type' => 'char',      'precision' => 1,  'nullable' => False),
					'log_msg_code' 		=> array('type' => 'varchar',   'precision' => 30, 'nullable' => False),
					'log_msg_msg' 		=> array('type' => 'text', 'nullable' => False),
					'log_msg_parms'		=> array('type' => 'text', 'nullable' => False)
			 	),
				'pk' => array('log_msg_log_id', 'log_msg_seq_no'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.13.007';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.007';
	function phpgwapi_upgrade0_9_13_007()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_log_msg','log_msg_log_id',array('type' => 'int', 'precision' => 4, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.008';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.008';
	function phpgwapi_upgrade0_9_13_008()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_log_msg','log_msg_file',array('type' => 'varchar', 'precision' => 255, 'nullable'=> False));
		$phpgw_setup->oProc->AddColumn('phpgw_log_msg','log_msg_line',array('type' => 'int', 'precision' => 4, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.009';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.009';
	function phpgwapi_upgrade0_9_13_009()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_interserv', array(
				'fd' => array(
					'server_id'   => array('type' => 'auto', 'nullable' => False),
					'server_name' => array('type' => 'varchar', 'precision' => 64,  'nullable' => True),
					'server_host' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'server_url'  => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'trust_level' => array('type' => 'int',     'precision' => 4),
					'trust_rel'   => array('type' => 'int',     'precision' => 4),
					'username'    => array('type' => 'varchar', 'precision' => 64,  'nullable' => True),
					'password'    => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'admin_name'  => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'admin_email' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'server_mode' => array('type' => 'varchar', 'precision' => 16,  'nullable' => False, 'default' => 'xmlrpc'),
					'server_security' => array('type' => 'varchar', 'precision' => 16,'nullable' => True)
				),
				'pk' => array('server_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.13.010';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.010';
	function phpgwapi_upgrade0_9_13_010()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_sessions','session_lid',array('type' => 'varchar', 'precision' => 255, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.011';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.011';
	function phpgwapi_upgrade0_9_13_011()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_vfs', array(
				'fd' => array(
					'file_id' => array('type' => 'auto','nullable' => False),
					'owner_id' => array('type' => 'int', 'precision' => 4,'nullable' => False),
					'createdby_id' => array('type' => 'int', 'precision' => 4,'nullable' => True),
					'modifiedby_id' => array('type' => 'int', 'precision' => 4,'nullable' => True),
					'created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
					'modified' => array('type' => 'date','nullable' => True),
					'size' => array('type' => 'int', 'precision' => 4,'nullable' => True),
					'mime_type' => array('type' => 'varchar', 'precision' => 150,'nullable' => True),
					'deleteable' => array('type' => 'char', 'precision' => 1,'nullable' => True,'default' => 'Y'),
					'comment' => array('type' => 'text','nullable' => True),
					'app' => array('type' => 'varchar', 'precision' => 25,'nullable' => True),
					'directory' => array('type' => 'text','nullable' => True),
					'name' => array('type' => 'text','nullable' => False),
					'link_directory' => array('type' => 'text','nullable' => True),
					'link_name' => array('type' => 'text','nullable' => True),
					'version' => array('type' => 'varchar', 'precision' => 30,'nullable' => False,'default' => '0.0.0.0')
				),
				'pk' => array('file_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);
		$setup_info['phpgwapi']['currentver'] = '0.9.13.012';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.012';
	function phpgwapi_upgrade0_9_13_012()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_applications', 'app_tables', array('type' => 'text'));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.013';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.013';
	function phpgwapi_upgrade0_9_13_013()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_history_log', array(
				'fd' => array(
					'history_id'        => array('type' => 'auto',      'precision' => 4,  'nullable' => False),
					'history_record_id' => array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'history_appname'   => array('type' => 'varchar',   'precision' => 64, 'nullable' => False),
					'history_owner'     => array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'history_status'    => array('type' => 'char',      'precision' => 2,  'nullable' => False),
					'history_new_value' => array('type' => 'text',      'nullable' => False),
					'history_timestamp' => array('type' => 'timestamp', 'nullable' => False, 'default' => 'current_timestamp')

				),
				'pk' => array('history_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.014';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.014';
	function phpgwapi_upgrade0_9_13_014()
	{
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_applications SET app_order=100 WHERE app_order IS NULL");
		$GLOBALS['phpgw_setup']->oProc->query("SELECT * FROM phpgw_applications");
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$app_name[]	= $GLOBALS['phpgw_setup']->oProc->f('app_name');
			$app_title[]	= $GLOBALS['phpgw_setup']->oProc->f('app_title');
			$app_enabled[]	= $GLOBALS['phpgw_setup']->oProc->f('app_enabled');
			$app_order[]	= $GLOBALS['phpgw_setup']->oProc->f('app_order');
			$app_tables[]	= $GLOBALS['phpgw_setup']->oProc->f('app_tables');
			$app_version[]	= $GLOBALS['phpgw_setup']->oProc->f('app_version');
		}

		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_applications');

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_applications', array(
				'fd' => array(
					'app_id' => array('type' => 'auto', 'precision' => 4, 'nullable' => false),
					'app_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
					'app_title' => array('type' => 'varchar', 'precision' => 50),
					'app_enabled' => array('type' => 'int', 'precision' => 4),
					'app_order' => array('type' => 'int', 'precision' => 4),
					'app_tables' => array('type' => 'varchar', 'precision' => 255),
					'app_version' => array('type' => 'varchar', 'precision' => 20, 'nullable' => false, 'default' => '0.0')
				),
				'pk' => array('app_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('app_name')
			)
		);

		$rec_count = count($app_name);
		for($rec_loop=0;$rec_loop<$rec_count;$rec_loop++)
		{
			$GLOBALS['phpgw_setup']->oProc->query('INSERT INTO phpgw_applications(app_id,app_name,app_title,app_enabled,app_order,app_tables,app_version) '
				. 'VALUES('.($rec_loop + 1).",'".$app_name[$rec_loop]."','".$app_title[$rec_loop]."',".$app_enabled[$rec_loop].','.$app_order[$rec_loop].",'".$app_tables[$rec_loop]."','".$app_version[$rec_loop]."')");
		}

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.015';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.015';
	function phpgwapi_upgrade0_9_13_015()
	{
		/* Skip this for mysql 3.22.X in php4 at least */
		if(phpversion() >= '4.0.5' && @$GLOBALS['phpgw_setup']->db->Type == 'mysql')
		{
			$_ver_str = @mysql_get_server_info();
			$_ver_arr = explode(".",$_ver_str);
			$_ver = $_ver_arr[1];
			if(intval($_ver) < 23)
			{
				$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.016';
				return $GLOBALS['setup_info']['phpgwapi']['currentver'];
			}
		}

		$GLOBALS['phpgw_setup']->oProc->AlterColumn(
			'lang',
			'message_id',
			array(
				'type' => 'varchar',
				'precision' => 255,
				'nullable' => false,
				'default' => ''
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.016';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.016';
	function phpgwapi_upgrade0_9_13_016()
	{
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','acl_manager','hook_acl_manager.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','add_def_pref','hook_add_def_pref.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','after_navbar','hook_after_navbar.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','deleteaccount','hook_deleteaccount.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','config','hook_config.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','manual','hook_manual.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','view_user','hook_view_user.inc.php')");

		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','admin_deleteaccount','hook_admin_deleteaccount.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','config','hook_config.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','manual','hook_manual.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','preferences','hook_preferences.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','settings','hook_settings.inc.php')");

		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','about','hook_about.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','add_def_pref','hook_add_def_pref.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','config_validate','hook_config_validate.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','deleteaccount','hook_deleteaccount.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','home','hook_home.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','manual','hook_manual.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','notifywindow','hook_notifywindow.inc.php')");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.017';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.017';
	function phpgwapi_upgrade0_9_13_017()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_history_log','history_old_value',array('type' => 'text','nullable' => False));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.018';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.018';
	function phpgwapi_upgrade0_9_13_018()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.000';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.000';
	function phpgwapi_upgrade0_9_14_000()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.001';
	function phpgwapi_upgrade0_9_14_001()
	{
		// Fix bug from update script in 0.9.11.004/5:
		// column config_app was added to table phpgw_config (which places it as last column),
		// but in the tables_current.inc.php it was added as first column.
		// When setup / schemaproc wants to do the AlterColum it recreates the table for pgSql,
		// as pgSql could not change the column-type. This recreation is can not be based on 
		// tables_current, but on running tables_baseline throught all update-scripts.
		// Which gives at the end two different versions of the table on new or updated installs.
		// I fix it now in the (wrong) order of the tables_current, as some apps might depend on!
		
		$confs = array();
		$GLOBALS['phpgw_setup']->oProc->query("SELECT * FROM phpgw_config");
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$confs[] = array(
				'config_app' => $GLOBALS['phpgw_setup']->oProc->f('config_app'),
				'config_name' => $GLOBALS['phpgw_setup']->oProc->f('config_name'),
				'config_value' => $GLOBALS['phpgw_setup']->oProc->f('config_value')
			);
		}
		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_config');
		
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_config',array(
			'fd' => array(
				'config_app' => array('type' => 'varchar', 'precision' => 50),
				'config_name' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'config_value' => array('type' => 'text')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('config_name')
		));
		
		foreach($confs as $conf)
		{
			$GLOBALS['phpgw_setup']->oProc->query(
				"INSERT INTO phpgw_config (config_app,config_name,config_value) VALUES ('".
				$conf['config_app']."','".$conf['config_name']."','".$conf['config_value']."')");
		}
		
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE languages SET available='Yes' WHERE lang_id='cs'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
	
	$test[] = '0.9.14.002';
	function phpgwapi_upgrade0_9_14_002()
	{
		// 0.9.14.5xx are the development-versions of the 0.9.16 release (based on the 0.9.14 api)
		// as 0.9.15.xxx are already used in HEAD
		
		// this is the 0.9.15.003 update, needed for the new filemanager and vfs-classes in the api
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => True));

		// this is the 0.9.15.004 update, needed for the polish translations
		$GLOBALS['phpgw_setup']->db->query("UPDATE languages set available='Yes' WHERE lang_id='pl'");
		
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.500';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.003';
	function phpgwapi_upgrade0_9_14_003()
	{
		// 0.9.14.5xx are the development-versions of the 0.9.16 release (based on the 0.9.14 api)
		// as 0.9.15.xxx are already used in HEAD
		
		// this is the 0.9.15.003 update, needed for the new filemanager and vfs-classes in the api
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => True));

		// this is the 0.9.15.004 update, needed for the polish translations
		$GLOBALS['phpgw_setup']->db->query("UPDATE languages set available='Yes' WHERE lang_id='pl'");
		
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.500';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.004';
	function phpgwapi_upgrade0_9_14_004()
	{
		// 0.9.14.5xx are the development-versions of the 0.9.16 release (based on the 0.9.14 api)
		// as 0.9.15.xxx are already used in HEAD
		
		// this is the 0.9.15.003 update, needed for the new filemanager and vfs-classes in the api
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => True));

		// this is the 0.9.15.004 update, needed for the polish translations
		$GLOBALS['phpgw_setup']->db->query("UPDATE languages set available='Yes' WHERE lang_id='pl'");
		
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.500';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.005';
	function phpgwapi_upgrade0_9_14_005()
	{
		// 0.9.14.5xx are the development-versions of the 0.9.16 release (based on the 0.9.14 api)
		// as 0.9.15.xxx are already used in HEAD
		
		// this is the 0.9.15.003 update, needed for the new filemanager and vfs-classes in the api
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => True));

		// this is the 0.9.15.004 update, needed for the polish translations
		$GLOBALS['phpgw_setup']->db->query("UPDATE languages set available='Yes' WHERE lang_id='pl'");
		
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.500';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.006';
	function phpgwapi_upgrade0_9_14_006()
	{
		// 0.9.14.5xx are the development-versions of the 0.9.16 release (based on the 0.9.14 api)
		// as 0.9.15.xxx are already used in HEAD
		
		// this is the 0.9.15.003 update, needed for the new filemanager and vfs-classes in the api
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => True));

		// this is the 0.9.15.004 update, needed for the polish translations
		$GLOBALS['phpgw_setup']->db->query("UPDATE languages set available='Yes' WHERE lang_id='pl'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.500';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.500';
	function phpgwapi_upgrade0_9_14_500()
	{
		// this is the 0.9.15.001 update
		$GLOBALS['phpgw_setup']->oProc->RenameTable('lang','phpgw_lang');
		$GLOBALS['phpgw_setup']->oProc->RenameTable('languages','phpgw_languages');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.501';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.501';
	function phpgwapi_upgrade0_9_14_501()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_async',array(
			'fd' => array(
				'id' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'next' => array('type' => 'int','precision' => '4','nullable' => False),
				'times' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'method' => array('type' => 'varchar','precision' => '80','nullable' => False),
				'data' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_applications',array(
			'fd' => array(
				'app_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'app_name' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'app_enabled' => array('type' => 'int','precision' => '4'),
				'app_order' => array('type' => 'int','precision' => '4'),
				'app_tables' => array('type' => 'text'),
				'app_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => '0.0')
			),
			'pk' => array('app_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('app_name')
		),'app_title');
		
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.502';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.14.502';
	function phpgwapi_upgrade0_9_14_502()
	{
		$GLOBALS['phpgw_setup']->oProc->RenameTable('phpgw_preferences','old_preferences');

		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_preferences',array(
			'fd' => array(
				'preference_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'preference_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'preference_value' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('preference_owner','preference_app'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));
		$db2 = $GLOBALS['phpgw_setup']->db;	// we need a 2. result-set
		$GLOBALS['phpgw_setup']->oProc->query("SELECT * FROM old_preferences");
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$owner = intval($GLOBALS['phpgw_setup']->oProc->f('preference_owner'));
			$prefs = unserialize($GLOBALS['phpgw_setup']->oProc->f('preference_value'));
			
			if (is_array($prefs))
			{
				foreach ($prefs as $app => $pref)
				{
					if (!empty($app) && count($pref))
					{
						$app = addslashes($app);
						$pref = serialize($pref);
						$db2->query("INSERT INTO phpgw_preferences".
							" (preference_owner,preference_app,preference_value)".
							" VALUES ($owner,'$app','$pref')");
					}
				}
			}
		}
		$GLOBALS['phpgw_setup']->oProc->DropTable('old_preferences');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.503';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.503';
	function phpgwapi_upgrade0_9_14_503()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_addressbook','last_mod',array(
				'type' => 'int',
				'precision' => '4',
				'nullable' => False
			));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.504';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.504';
	function phpgwapi_upgrade0_9_14_504()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_categories','last_mod',array(
				'type' => 'int',
				'precision' => '4',
				'nullable' => False
			));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.505';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.14.505';
	function phpgwapi_upgrade0_9_14_505()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_access_log','lo',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => True,
			'default' => '0'
		));


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.506';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.14.506';
	function phpgwapi_upgrade0_9_14_506()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_vfs','content',array(
			'type' => 'text',
			'nullable' => True
		));


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.507';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.14.507';
	function phpgwapi_upgrade0_9_14_507()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_async','account_id',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => False,
			'default' => '0'
		));


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.508';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.14.508';
	function phpgwapi_upgrade0_9_14_508()
	{
		// update to 0.9.10pre3 droped the columns account_permissions and account_groups
		// unfortunally they are still in the tables_current of 0.9.14.508
		// so it depends on having a new or an updated install, if one have them or not
		// we now check if they are there and drop them if thats the case

		$GLOBALS['phpgw_setup']->oProc->m_oTranslator->_GetColumns($GLOBALS['phpgw_setup']->oProc,'phpgw_accounts',$columns);
		$columns = explode(',',$columns);
		if (in_array('account_permissions',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_accounts',array(
				'fd' => array(
					'account_id' => array('type' => 'auto','nullable' => False),
					'account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'account_pwd' => array('type' => 'varchar','precision' => '32','nullable' => False),
					'account_firstname' => array('type' => 'varchar','precision' => '50'),
					'account_lastname' => array('type' => 'varchar','precision' => '50'),
					'account_groups' => array('type' => 'varchar','precision' => '30'),
					'account_lastlogin' => array('type' => 'int','precision' => '4'),
					'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
					'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
					'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
					'account_expires' => array('type' => 'int','precision' => '4'),
					'account_type' => array('type' => 'char','precision' => '1','nullable' => True)
				),
				'pk' => array('account_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('account_lid')
			),'account_permissions');
		}
		if (in_array('account_groups',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_accounts',array(
				'fd' => array(
					'account_id' => array('type' => 'auto','nullable' => False),
					'account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'account_pwd' => array('type' => 'varchar','precision' => '32','nullable' => False),
					'account_firstname' => array('type' => 'varchar','precision' => '50'),
					'account_lastname' => array('type' => 'varchar','precision' => '50'),
					'account_lastlogin' => array('type' => 'int','precision' => '4'),
					'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
					'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
					'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
					'account_expires' => array('type' => 'int','precision' => '4'),
					'account_type' => array('type' => 'char','precision' => '1','nullable' => True)
				),
				'pk' => array('account_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('account_lid')
			),'account_groups');
		}

		// we add the person_id from the .16RC1, if its not already there
		if (!in_array('person_id',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_accounts','person_id',array(
				'type' => 'int',
				'precision' => '4',
				'nullable' => True
			));
		}
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_accounts','account_primary_group',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => False,
			'default' => '0'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.002';
	function phpgwapi_upgrade0_9_99_002()
	{
		// needed for the chinese(simplified) translations
		$GLOBALS['phpgw_setup']->db->query("UPDATE phpgw_languages SET lang_name='Chinese(simplified)',available='Yes' WHERE lang_id='zh'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.003';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	/*
	 * Updates from phpGroupWare .16 branch
	 */

	$test[] = '0.9.14.509';
	function phpgwapi_upgrade0_9_14_509()
	{
		// this is the phpGW .16RC1 with the new contacts tables
		// we need to drop them here to not run into problems later on, if we install them
		foreach(array(
			'phpgw_contact',
			'phpgw_contact_person',
			'phpgw_contact_org',
			'phpgw_contact_org_person',
			'phpgw_contact_addr',
			'phpgw_contact_note',
			'phpgw_contact_others',
			'phpgw_contact_comm',
			'phpgw_contact_comm_descr',
			'phpgw_contact_comm_type',
			'phpgw_contact_types',
			'phpgw_contact_addr_type',
			'phpgw_contact_note_type'
		) as $table)
		{
			$GLOBALS['phpgw_setup']->oProc->DropTable($table);
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.508';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	/*
	 * Updates / downgrades from phpGroupWare HEAD branch
	 */

	$test[] = '0.9.15.013';
	function phpgwapi_upgrade0_9_15_013()
	{
		// is db-compatible to 0.9.14.507
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.507';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.014';
	function phpgwapi_upgrade0_9_15_014()
	{
		// is db-compatible to 0.9.14.508
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.508';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}



	$test[] = '0.9.99.003';
	function phpgwapi_upgrade0_9_99_003()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_id',array(
			'type' => 'auto'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lid',array(
			'type' => 'varchar',
			'precision' => '25'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_firstname',array(
			'type' => 'varchar',
			'precision' => '50'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastname',array(
			'type' => 'varchar',
			'precision' => '50'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastlogin',array(
			'type' => 'int',
			'precision' => '4'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastloginfrom',array(
			'type' => 'varchar',
			'precision' => '255'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastpwd_change',array(
			'type' => 'int',
			'precision' => '4'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_expires',array(
			'type' => 'int',
			'precision' => '4'
		));


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.004';
	function phpgwapi_upgrade0_9_99_004()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_app_sessions','content',array(
			'type' => 'longtext'
		));


		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.005';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?>
