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

	$phpgw_baseline = array(
		'phpgw_config' => array(
			'fd' => array(
				'config_app' => array('type' => 'varchar', 'precision' => 50),
				'config_name' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'config_value' => array('type' => 'varchar', 'precision' => 100)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('config_name')
		),
		'phpgw_applications' => array(
			'fd' => array(
				'app_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'app_title' => array('type' => 'varchar', 'precision' => 50),
				'app_enabled' => array('type' => 'int', 'precision' => 4),
				'app_order' => array('type' => 'int', 'precision' => 4),
				'app_tables' => array('type' => 'varchar', 'precision' => 255),
				'app_version' => array('type' => 'varchar', 'precision' => 20, 'nullable' => false, 'default' => '0.0')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('app_name')
		),
		'phpgw_acl' => array(
			'fd' => array(
				'acl_appname' => array('type' => 'varchar', 'precision' => 50),
				'acl_location' => array('type' => 'varchar', 'precision' => 255),
				'acl_account' => array('type' => 'int', 'precision' => 4),
				'acl_rights' => array('type' => 'int', 'precision' => 4)
			),
			'pk' => array(),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		),
		'phpgw_accounts' => array(
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
		),
		'phpgw_preferences' => array(
			'fd' => array(
				'preference_owner' => array('type' => 'varchar', 'precision' => 20, 'nullable' => false),
				'preference_value' => array('type' => 'text')
			),
			'pk' => array('preference_owner'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_sessions' => array(
			'fd' => array(
				'session_id' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'session_lid' => array('type' => 'varchar', 'precision' => 20),
				'session_ip' => array('type' => 'varchar', 'precision' => 255),
				'session_logintime' => array('type' => 'int', 'precision' => 4),
				'session_dla' => array('type' => 'int', 'precision' => 4),
				'session_action' => array('type' => 'varchar', 'precision' => 255),
				'session_flags' => array('type' => 'char', 'precision' => 2)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('session_id')
		),
		'phpgw_app_sessions' => array(
			'fd' => array(
				'sessionid' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'loginid' => array('type' => 'varchar', 'precision' => 20),
				'location' => array('type' => 'varchar', 'precision' => 255),
				'app' => array('type' => 'varchar', 'precision' => 20),
				'content' => array('type' => 'text'),
				'session_dla' => array('type' => 'int', 'precision' => 4)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_access_log' => array(
			'fd' => array(
				'sessionid' => array('type' => 'char', 'precision' => 32),
				'loginid' => array('type' => 'varchar', 'precision' => 30),
				'ip' => array('type' => 'varchar', 'precision' => 30),
				'li' => array('type' => 'int', 'precision' => 4),
				'lo' => array('type' => 'varchar', 'precision' => 255),
				'account_id' => array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_hooks' => array(
			'fd' => array(
				'hook_id' => array('type' => 'auto', 'nullable' => false),
				'hook_appname' => array('type' => 'varchar', 'precision' => 255),
				'hook_location' => array('type' => 'varchar', 'precision' => 255),
				'hook_filename' => array('type' => 'varchar', 'precision' => 255)
			),
			'pk' => array('hook_id'),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		),
		'languages' => array(
			'fd' => array(
				'lang_id' => array('type' => 'varchar', 'precision' => 2, 'nullable' => false),
				'lang_name' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
				'available' => array('type' => 'char', 'precision' => 3, 'nullable' => false, 'default' => 'No')
			),
			'pk' => array('lang_id'),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		),
		'lang' => array(
			'fd' => array(
				'message_id' => array('type' => 'varchar', 'precision' => 150, 'nullable' => false, 'default' => ''),
				'app_name' => array('type' => 'varchar', 'precision' => 100, 'nullable' => false, 'default' => 'common'),
				'lang' => array('type' => 'varchar', 'precision' => 5, 'nullable' => false, 'default' => ''),
				'content' => array('type' => 'text')
			),
			'pk' => array('message_id', 'app_name', 'lang'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_nextid' => array(
			'fd' => array(
				'id' => array('type' => 'int', 'precision' => 4, 'nullable' => true),
				'appname' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('appname')
		),
		'phpgw_categories' => array(
			'fd' => array(
				'cat_id' => array('type' => 'auto', 'precision' => 4, 'nullable' => false),
				'cat_main' => array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
				'cat_parent' => array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
				'cat_level' => array('type' => 'int', 'precision' => 2, 'default' => 0, 'nullable' => false),
				'cat_owner' => array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
				'cat_access' => array('type' => 'varchar', 'precision' => 7),
				'cat_appname' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
				'cat_name' => array('type' => 'varchar', 'precision' => 150, 'nullable' => false),
				'cat_description' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'cat_data' => array('type' => 'text')
			),
			'pk' => array('cat_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_addressbook' => array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'lid' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'tid' => array('type' => 'char', 'precision' => 1,'nullable' => True),
				'owner' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'access' => array('type' => 'varchar', 'precision' => 7,'nullable' => True),
				'cat_id' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'fn' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_family' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_given' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_middle' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_prefix' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'n_suffix' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'sound' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'bday' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'note' => array('type' => 'text','nullable' => True),
				'tz' => array('type' => 'varchar', 'precision' => 8,'nullable' => True),
				'geo' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'url' => array('type' => 'varchar', 'precision' => 128,'nullable' => True),
				'pubkey' => array('type' => 'text','nullable' => True),
				'org_name' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'org_unit' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'title' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_street' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_locality' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_region' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_postalcode' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_countryname' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_one_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'label' => array('type' => 'text','nullable' => True),
				'adr_two_street' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_locality' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_region' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_postalcode' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_countryname' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'adr_two_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'tel_work' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_home' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_voice' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_fax' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_msg' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_cell' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_pager' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_bbs' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_modem' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_car' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_isdn' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_video' => array('type' => 'varchar', 'precision' => 40,'nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_prefer' => array('type' => 'varchar', 'precision' => 32,'nullable' => True),
				'email' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'email_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => False,'default' => 'INTERNET'),
				'email_home' => array('type' => 'varchar', 'precision' => 64,'nullable' => True),
				'email_home_type' => array('type' => 'varchar', 'precision' => 32,'nullable' => False,'default' => 'INTERNET')
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_addressbook_extra' => array(
			'fd' => array(
				'contact_id' => array('type' => 'int', 'precision' => 4, 'nullable' => True),
				'contact_owner' => array('type' => 'int', 'precision' => 8,'nullable' => True),
				'contact_name' => array('type' => 'varchar', 'precision' => 255,'nullable' => True),
				'contact_value' => array('type' => 'text','nullable' => True)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_log' => array(
			'fd' => array(
				'log_id'       => array('type' => 'auto', 'precision' => 4, 'nullable' => False),
				'log_date'     => array('type' => 'timestamp', 'nullable' => False),
				'log_user'     => array('type' => 'int', 'precision' => 4, 'nullable' => False),
				'log_app'      => array('type' => 'varchar', 'precision' => 50, 'nullable' => False),
				'log_severity' => array('type' => 'char', 'precision' => 1, 'nullable' => False)
			    ),
			'pk' => array('log_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_log_msg' => array(
			'fd' => array(
				'log_msg_log_id'   => array('type' => 'int', 'precision' => 4, 'nullable' => False),
				'log_msg_seq_no'   => array('type' => 'int', 'precision' => 4, 'nullable' => False),
				'log_msg_date'     => array('type' => 'timestamp', 'nullable' => False),
				'log_msg_tx_fid'   => array('type' => 'varchar', 'precision' => 4, 'nullable' => True),
				'log_msg_tx_id'    => array('type' => 'varchar', 'precision' => 4, 'nullable' => True),
				'log_msg_severity' => array('type' => 'char', 'precision' => 1, 'nullable' => False),
				'log_msg_code'     => array('type' => 'varchar', 'precision' => 30, 'nullable' => False),
				'log_msg_msg'      => array('type' => 'text', 'nullable' => False),
				'log_msg_parms'    => array('type' => 'text', 'nullable' => False),
				'log_msg_file'     => array('type' => 'varchar', 'precision' => 255, 'nullable' => False),
				'log_msg_line'     => array('type' => 'int', 'precision' => 4, 'nullable' => False)
			    ),
			'pk' => array('log_msg_log_id', 'log_msg_seq_no'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_interserv' => array(
			'fd' => array(
				'server_id'   => array('type' => 'auto'),
				'server_name' => array('type' => 'varchar', 'precision' => 64,  'nullable' => True),
				'server_host' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
				'server_url'  => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
				'trust_level' => array('type' => 'int',     'precision' => 4,   'nullable' => True),
				'trust_rel'   => array('type' => 'int',     'precision' => 4,   'nullable' => True),
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
?>
