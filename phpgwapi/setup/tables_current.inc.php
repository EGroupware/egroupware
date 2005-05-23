<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id$
	// $Source$

	$phpgw_baseline = array(
		'phpgw_config' => array(
			'fd' => array(
				'config_app' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'config_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'config_value' => array('type' => 'text')
			),
			'pk' => array('config_app','config_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_applications' => array(
			'fd' => array(
				'app_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'app_name' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'app_enabled' => array('type' => 'int','precision' => '4','nullable' => False),
				'app_order' => array('type' => 'int','precision' => '4','nullable' => False),
				'app_tables' => array('type' => 'text','nullable' => False),
				'app_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => '0.0')
			),
			'pk' => array('app_id'),
			'fk' => array(),
			'ix' => array(array('app_enabled','app_order')),
			'uc' => array('app_name')
		),
		'phpgw_acl' => array(
			'fd' => array(
				'acl_appname' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'acl_location' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'acl_account' => array('type' => 'int','precision' => '4','nullable' => False),
				'acl_rights' => array('type' => 'int','precision' => '4')
			),
			'pk' => array('acl_appname','acl_location','acl_account'),
			'fk' => array(),
			'ix' => array('acl_account',array('acl_location','acl_account'),array('acl_appname','acl_account')),
			'uc' => array()
		),
		'phpgw_accounts' => array(
			'fd' => array(
				'account_id' => array('type' => 'auto','nullable' => False),
				'account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'account_pwd' => array('type' => 'varchar','precision' => '100','nullable' => False),
				'account_firstname' => array('type' => 'varchar','precision' => '50'),
				'account_lastname' => array('type' => 'varchar','precision' => '50'),
				'account_lastlogin' => array('type' => 'int','precision' => '4'),
				'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
				'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
				'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
				'account_expires' => array('type' => 'int','precision' => '4'),
				'account_type' => array('type' => 'char','precision' => '1'),
				'person_id' => array('type' => 'int','precision' => '4'),
				'account_primary_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'account_email' => array('type' => 'varchar','precision' => '100')
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		),
		'phpgw_preferences' => array(
			'fd' => array(
				'preference_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'preference_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'preference_value' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('preference_owner','preference_app'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_sessions' => array(
			'fd' => array(
				'session_id' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'session_lid' => array('type' => 'varchar','precision' => '128'),
				'session_ip' => array('type' => 'varchar','precision' => '32'),
				'session_logintime' => array('type' => 'int','precision' => '4'),
				'session_dla' => array('type' => 'int','precision' => '4'),
				'session_action' => array('type' => 'varchar','precision' => '255'),
				'session_flags' => array('type' => 'char','precision' => '2')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(array('session_flags','session_dla')),
			'uc' => array('session_id')
		),
		'phpgw_app_sessions' => array(
			'fd' => array(
				'sessionid' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'loginid' => array('type' => 'int','precision' => '4','nullable' => False),
				'app' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'location' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'content' => array('type' => 'longtext'),
				'session_dla' => array('type' => 'int','precision' => '4')
			),
			'pk' => array('sessionid','loginid','app','location'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_access_log' => array(
			'fd' => array(
				'sessionid' => array('type' => 'char','precision' => '32','nullable' => False),
				'loginid' => array('type' => 'varchar','precision' => '30','nullable' => False),
				'ip' => array('type' => 'varchar','precision' => '30','nullable' => False),
				'li' => array('type' => 'int','precision' => '4','nullable' => False),
				'lo' => array('type' => 'int','precision' => '4','nullable' => True,'default' => '0'),
				'account_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_hooks' => array(
			'fd' => array(
				'hook_id' => array('type' => 'auto','nullable' => False),
				'hook_appname' => array('type' => 'varchar','precision' => '255'),
				'hook_location' => array('type' => 'varchar','precision' => '255'),
				'hook_filename' => array('type' => 'varchar','precision' => '255')
			),
			'pk' => array('hook_id'),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		),
		'phpgw_languages' => array(
			'fd' => array(
				'lang_id' => array('type' => 'varchar','precision' => '5','nullable' => False),
				'lang_name' => array('type' => 'varchar','precision' => '50','nullable' => False)
			),
			'pk' => array('lang_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_lang' => array(
			'fd' => array(
				'lang' => array('type' => 'varchar','precision' => '5','nullable' => False,'default' => ''),
				'app_name' => array('type' => 'varchar','precision' => '100','nullable' => False,'default' => 'common'),
				'message_id' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => ''),
				'content' => array('type' => 'text')
			),
			'pk' => array('lang','app_name','message_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_nextid' => array(
			'fd' => array(
				'id' => array('type' => 'int','precision' => '4','nullable' => True),
				'appname' => array('type' => 'varchar','precision' => '25','nullable' => False)
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('appname')
		),
		'phpgw_categories' => array(
			'fd' => array(
				'cat_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'cat_main' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cat_parent' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cat_level' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '0'),
				'cat_owner' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cat_access' => array('type' => 'varchar','precision' => '7'),
				'cat_appname' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'cat_name' => array('type' => 'varchar','precision' => '150','nullable' => False),
				'cat_description' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'cat_data' => array('type' => 'text'),
				'last_mod' => array('type' => 'int','precision' => '8','nullable' => False)
			),
			'pk' => array('cat_id'),
			'fk' => array(),
			'ix' => array(array('cat_appname','cat_owner','cat_parent','cat_level')),
			'uc' => array()
		),
		'phpgw_addressbook' => array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'lid' => array('type' => 'varchar','precision' => '32'),
				'tid' => array('type' => 'char','precision' => '1'),
				'owner' => array('type' => 'int','precision' => '8'),
				'access' => array('type' => 'varchar','precision' => '7'),
				'cat_id' => array('type' => 'varchar','precision' => '32'),
				'fn' => array('type' => 'varchar','precision' => '64'),
				'n_family' => array('type' => 'varchar','precision' => '64'),
				'n_given' => array('type' => 'varchar','precision' => '64'),
				'n_middle' => array('type' => 'varchar','precision' => '64'),
				'n_prefix' => array('type' => 'varchar','precision' => '64'),
				'n_suffix' => array('type' => 'varchar','precision' => '64'),
				'sound' => array('type' => 'varchar','precision' => '64'),
				'bday' => array('type' => 'varchar','precision' => '32'),
				'note' => array('type' => 'text'),
				'tz' => array('type' => 'varchar','precision' => '8'),
				'geo' => array('type' => 'varchar','precision' => '32'),
				'url' => array('type' => 'varchar','precision' => '128'),
				'pubkey' => array('type' => 'text'),
				'org_name' => array('type' => 'varchar','precision' => '64'),
				'org_unit' => array('type' => 'varchar','precision' => '64'),
				'title' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street' => array('type' => 'varchar','precision' => '64'),
				'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_one_region' => array('type' => 'varchar','precision' => '64'),
				'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
				'adr_one_type' => array('type' => 'varchar','precision' => '32'),
				'label' => array('type' => 'text'),
				'adr_two_street' => array('type' => 'varchar','precision' => '64'),
				'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_two_region' => array('type' => 'varchar','precision' => '64'),
				'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
				'adr_two_type' => array('type' => 'varchar','precision' => '32'),
				'tel_work' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_home' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_voice' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_fax' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_msg' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_cell' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_pager' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_bbs' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_modem' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_car' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_isdn' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_video' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_prefer' => array('type' => 'varchar','precision' => '32'),
				'email' => array('type' => 'varchar','precision' => '64'),
				'email_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
				'email_home' => array('type' => 'varchar','precision' => '64'),
				'email_home_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
				'last_mod' => array('type' => 'int','precision' => '8','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(array('tid','owner','access','n_family','n_given','email'),array('tid','cat_id','owner','access','n_family','n_given','email')),
			'uc' => array()
		),
		'phpgw_addressbook_extra' => array(
			'fd' => array(
				'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'contact_owner' => array('type' => 'int','precision' => '8'),
				'contact_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'contact_value' => array('type' => 'text')
			),
			'pk' => array('contact_id','contact_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_log' => array(
			'fd' => array(
				'log_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'log_date' => array('type' => 'timestamp','nullable' => False),
				'log_user' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_app' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'log_severity' => array('type' => 'char','precision' => '1','nullable' => False)
			),
			'pk' => array('log_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_log_msg' => array(
			'fd' => array(
				'log_msg_log_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_msg_seq_no' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_msg_date' => array('type' => 'timestamp','nullable' => False),
				'log_msg_tx_fid' => array('type' => 'varchar','precision' => '4','nullable' => True),
				'log_msg_tx_id' => array('type' => 'varchar','precision' => '4','nullable' => True),
				'log_msg_severity' => array('type' => 'char','precision' => '1','nullable' => False),
				'log_msg_code' => array('type' => 'varchar','precision' => '30','nullable' => False),
				'log_msg_msg' => array('type' => 'text','nullable' => False),
				'log_msg_parms' => array('type' => 'text','nullable' => False),
				'log_msg_file' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'log_msg_line' => array('type' => 'int','precision' => '4','nullable' => False)
			),
			'pk' => array('log_msg_log_id','log_msg_seq_no'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_interserv' => array(
			'fd' => array(
				'server_id' => array('type' => 'auto','nullable' => False),
				'server_name' => array('type' => 'varchar','precision' => '64','nullable' => True),
				'server_host' => array('type' => 'varchar','precision' => '255','nullable' => True),
				'server_url' => array('type' => 'varchar','precision' => '255','nullable' => True),
				'trust_level' => array('type' => 'int','precision' => '4'),
				'trust_rel' => array('type' => 'int','precision' => '4'),
				'username' => array('type' => 'varchar','precision' => '64','nullable' => True),
				'password' => array('type' => 'varchar','precision' => '255','nullable' => True),
				'admin_name' => array('type' => 'varchar','precision' => '255','nullable' => True),
				'admin_email' => array('type' => 'varchar','precision' => '255','nullable' => True),
				'server_mode' => array('type' => 'varchar','precision' => '16','nullable' => False,'default' => 'xmlrpc'),
				'server_security' => array('type' => 'varchar','precision' => '16','nullable' => True)
			),
			'pk' => array('server_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_vfs' => array(
			'fd' => array(
				'vfs_file_id' => array('type' => 'auto','nullable' => False),
				'vfs_owner_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'vfs_createdby_id' => array('type' => 'int','precision' => '4'),
				'vfs_modifiedby_id' => array('type' => 'int','precision' => '4'),
				'vfs_created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
				'vfs_modified' => array('type' => 'date'),
				'vfs_size' => array('type' => 'int','precision' => '4'),
				'vfs_mime_type' => array('type' => 'varchar','precision' => '64'),
				'vfs_deleteable' => array('type' => 'char','precision' => '1','default' => 'Y'),
				'vfs_comment' => array('type' => 'varchar','precision' => '255'),
				'vfs_app' => array('type' => 'varchar','precision' => '25'),
				'vfs_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_name' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'vfs_link_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_link_name' => array('type' => 'varchar','precision' => '128'),
				'vfs_version' => array('type' => 'varchar','precision' => '30','nullable' => False,'default' => '0.0.0.0'),
				'vfs_content' => array('type' => 'text')
			),
			'pk' => array('vfs_file_id'),
			'fk' => array(),
			'ix' => array(array('vfs_directory','vfs_name','vfs_mime_type')),
			'uc' => array()
		),
		'phpgw_history_log' => array(
			'fd' => array(
				'history_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'history_record_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'history_appname' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'history_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'history_status' => array('type' => 'char','precision' => '2','nullable' => False),
				'history_new_value' => array('type' => 'text','nullable' => False),
				'history_timestamp' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
				'history_old_value' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('history_id'),
			'fk' => array(),
			'ix' => array(array('history_appname','history_record_id','history_status','history_timestamp')),
			'uc' => array()
		),
		'egw_async' => array(
			'fd' => array(
				'async_id' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'async_next' => array('type' => 'int','precision' => '4','nullable' => False),
				'async_times' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'async_method' => array('type' => 'varchar','precision' => '80','nullable' => False),
				'async_data' => array('type' => 'text','nullable' => False),
				'async_account_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
			),
			'pk' => array('async_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'egw_api_content_history' => array(
			'fd' => array(
				'sync_appname' => array('type' => 'varchar','precision' => '60','nullable' => False),
				'sync_contentid' => array('type' => 'varchar','precision' => '60','nullable' => False),
				'sync_added' => array('type' => 'timestamp'),
				'sync_modified' => array('type' => 'timestamp'),
				'sync_deleted' => array('type' => 'timestamp'),
				'sync_id' => array('type' => 'auto','nullable' => False),
				'sync_guid' => array('type' => 'varchar','precision' => '120','nullable' => False),
				'sync_changedby' => array('type' => 'int','precision' => '4','nullable' => False)
			),
			'pk' => array('sync_id'),
			'fk' => array(),
			'ix' => array('sync_added','sync_modified','sync_deleted','sync_guid','sync_changedby',array('sync_appname','sync_contentid')),
			'uc' => array()
		),
		/*********************************************************************\
		 *	                       VFS version 2                             *
		\*********************************************************************/
		'phpgw_vfs2_mimetypes' => array(
			'fd' => array(
				'mime_id' => array('type' => 'auto','nullable' => False),
				'extension' => array('type' => 'varchar', 'precision' => 10, 'nullable' => false),
				'mime' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
				'mime_magic' => array('type' => 'varchar', 'precision' => 255, 'nullable' => true),
				'friendly' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
				'image' => array('type' => 'blob'),
				'proper_id' => array('type' => 'varchar', 'precision' => 4)
			),
			'pk' => array('mime_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),

		'phpgw_vfs2_files' => array(
			'fd' => array(
				'file_id' => array('type' => 'auto','nullable' => False),
				'mime_id' => array('type' => 'int','precision' => 4),
				'owner_id' => array('type' => 'int','precision' => 4,'nullable' => False),
				'createdby_id' => array('type' => 'int','precision' => 4),
				'created' => array('type' => 'timestamp','default' => '1970-01-01 00:00:00', 'nullable' => False),
				'size' => array('type' => 'int','precision' => 8),
				'deleteable' => array('type' => 'char','precision' => 1,'default' => 'Y'),
				'comment' => array('type' => 'varchar','precision' => 255),
				'app' => array('type' => 'varchar','precision' => 25),
				'directory' => array('type' => 'varchar','precision' => 255),
				'name' => array('type' => 'varchar','precision' => 128,'nullable' => False),
				'link_directory' => array('type' => 'varchar','precision' => 255),
				'link_name' => array('type' => 'varchar','precision' => 128),
				'version' => array('type' => 'varchar','precision' => 30,'nullable' => False,'default' => '0.0.0.0'),
				'content' => array('type' => 'longtext'),
				'is_backup' => array('type' => 'varchar', 'precision' => 1, 'nullable' => False, 'default' => 'N'),
				'shared' => array('type' => 'varchar', 'precision' => 1, 'nullable' => False,'default' => 'N'),
				'proper_id' => array('type' => 'varchar', 'precision' => 45)
			),
			'pk' => array('file_id'),
			'fk' => array('mime_id' => array ('phpgw_vfs2_mimetypes' => 'mime_id')),
			'ix' => array(array('directory','name')),
			'uc' => array()
		),

		'phpgw_vfs2_customfields' => array(
			'fd' => array(
				'customfield_id' => array('type' => 'auto','nullable' => False),
				'customfield_name' => array('type' => 'varchar','precision' => 60,'nullable' => False),
				'customfield_description' => array('type' => 'varchar','precision' => 255,'nullable'=> True),
				'customfield_type' => array('type' => 'varchar','precision' => 20, 'nullable' => false),
				'customfield_precision' => array('type' => 'int', 'precision' => 4, 'nullable' => true),
				'customfield_active' => array('type' => 'varchar','precision' => 1,'nullable' => False,'default' => 'N')
			),
			'pk' => array('customfield_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_vfs2_quota' => array(
			'fd' => array(
				'account_id' => array('type' => 'int','precision' => 4,'nullable' => false),
				'quota' => array('type' => 'int','precision' => 4,'nullable' => false)
			),
			'pk' => array('account_id'),
			'fk' => array('account_id' => array('phpgw_accounts' => 'account_id')),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_vfs2_shares' => array(
			'fd' => array(
				'account_id' => array('type' => 'int','precision' => 4,'nullable' => false),
				'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
				'acl_rights' => array('type' => 'int','precision' => 4,'nullable' => false)
			),
			'pk' => array('account_id','file_id'),
			'fk' => array('account_id' => array('phpgw_accounts' => 'account_id'), 'file_id' => array('phpgw_vfs2_files' => 'file_id')),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_vfs2_versioning' => array(
			'fd' => array(
				'version_id' => array('type' => 'auto', 'nullable' => false),
				'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
				'operation' => array('type' => 'int','precision' => 4, 'nullable' => False),
				'modifiedby_id' => array('type' => 'int','precision' => 4,'nullable' => false),
				'modified' => array('type' => 'timestamp', 'nullable' => False ),
				'version' => array('type' => 'varchar', 'precision' => 30, 'nullable' => False ),
				'comment' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
				'backup_file_id' => array('type' => 'int','precision' => 4, 'nullable' => True),
				'backup_content' => array('type' => 'longtext', 'nullable' => True),
				'src' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
				'dest' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True)
			),
			'pk' => array('version_id'),
			'fk' => array('file_id' => array('phpgw_vfs2_files' => 'file_id')),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_vfs2_customfields_data' => array(
			'fd' => array(
				'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
				'customfield_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
				'data' => array('type' => 'longtext', 'nullable' => True)
			),
			'pk' => array('file_id','customfield_id'),
			'fk' => array('file_id' => array('phpgw_vfs2_files' => 'file_id'),'customfield_id' => array('phpgw_vfs2_customfields' => 'customfield_id')),
			'ix' => array(),
			'uc' => array()
		),
		'phpgw_vfs2_prefixes' => array(
			'fd' => array(
				'prefix_id' => array('type' => 'auto','nullable' => false),
				'prefix' => array('type' => 'varchar', 'precision' => 8, 'nullable' => false),
				'owner_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
				'prefix_description' => array('type' => 'varchar', 'precision' => 30, 'nullable' => True),
				'prefix_type' => array('type' => 'varchar', 'precision' => 1, 'nullable' => false, 'default' => 'p')
			),
			'pk' => array('prefix_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		),
		/*********************************************************************/
	);
