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

  /* $Id$ */

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

	$test[] = '0.9.13.004';
	function phpgwapi_upgrade0_9_13_004()
	{
		global $setup_info, $phpgw_setup, $phpgw_info, $phpgw;

		$phpgw_setup->oProc->AddColumn('phpgw_access_log','account_id',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

		class phpgw
		{
			var $common;
			var $accounts;
			var $applications;
			var $db;
		}
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
?>
