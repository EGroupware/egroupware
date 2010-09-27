<?php
/**
 * eGroupWare - API Setup
 *
 * Current DB schema
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_config' => array(
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
	'egw_applications' => array(
		'fd' => array(
			'app_id' => array('type' => 'auto','precision' => '4','nullable' => False),
			'app_name' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'app_enabled' => array('type' => 'int','precision' => '4','nullable' => False),
			'app_order' => array('type' => 'int','precision' => '4','nullable' => False),
			'app_tables' => array('type' => 'text','nullable' => False),
			'app_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => '0.0'),
			'app_icon' => array('type' => 'varchar','precision' => '32'),
			'app_icon_app' => array('type' => 'varchar','precision' => '25'),
			'app_index' => array('type' => 'varchar','precision' => '64')
		),
		'pk' => array('app_id'),
		'fk' => array(),
		'ix' => array(array('app_enabled','app_order')),
		'uc' => array('app_name')
	),
	'egw_acl' => array(
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
	'egw_accounts' => array(
		'fd' => array(
			'account_id' => array('type' => 'auto','nullable' => False),
			'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'account_pwd' => array('type' => 'varchar','precision' => '100','nullable' => False),
			'account_lastlogin' => array('type' => 'int','precision' => '4'),
			'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
			'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
			'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
			'account_expires' => array('type' => 'int','precision' => '4'),
			'account_type' => array('type' => 'char','precision' => '1'),
			'account_primary_group' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
		),
		'pk' => array('account_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('account_lid')
	),
	'egw_preferences' => array(
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
	'egw_sessions' => array(
		'fd' => array(
			'session_id' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'session_lid' => array('type' => 'varchar','precision' => '128'),
			'session_ip' => array('type' => 'varchar','precision' => '40'),
			'session_logintime' => array('type' => 'int','precision' => '8'),
			'session_dla' => array('type' => 'int','precision' => '8'),
			'session_action' => array('type' => 'varchar','precision' => '255'),
			'session_flags' => array('type' => 'char','precision' => '2')
		),
		'pk' => array('session_id'),
		'fk' => array(),
		'ix' => array(array('session_flags','session_dla')),
		'uc' => array()
	),
	'egw_app_sessions' => array(
		'fd' => array(
			'sessionid' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'loginid' => array('type' => 'int','precision' => '4','nullable' => False),
			'app' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'location' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'content' => array('type' => 'longtext'),
			'session_dla' => array('type' => 'int','precision' => '8')
		),
		'pk' => array('sessionid','loginid','app','location'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_access_log' => array(
		'fd' => array(
			'sessionid' => array('type' => 'char','precision' => '128','nullable' => False),
			'loginid' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'ip' => array('type' => 'varchar','precision' => '40','nullable' => False),
			'li' => array('type' => 'int','precision' => '4','nullable' => False),
			'lo' => array('type' => 'int','precision' => '4','default' => '0'),
			'account_id' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0')
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_hooks' => array(
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
	'egw_languages' => array(
		'fd' => array(
			'lang_id' => array('type' => 'varchar','precision' => '5','nullable' => False),
			'lang_name' => array('type' => 'varchar','precision' => '50','nullable' => False)
		),
		'pk' => array('lang_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_lang' => array(
		'fd' => array(
			'lang' => array('type' => 'varchar','precision' => '5','nullable' => False,'default' => ''),
			'app_name' => array('type' => 'varchar','precision' => '32','nullable' => False,'default' => 'common'),
			'message_id' => array('type' => 'varchar','precision' => '128','nullable' => False,'default' => ''),
			'content' => array('type' => 'text')
		),
		'pk' => array('lang','app_name','message_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_nextid' => array(
		'fd' => array(
			'id' => array('type' => 'int','precision' => '4','nullable' => True),
			'appname' => array('type' => 'varchar','precision' => '25','nullable' => False)
		),
		'pk' => array('appname'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_categories' => array(
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
	'egw_log' => array(
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
	'egw_log_msg' => array(
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
	'egw_interserv' => array(
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
			'vfs_created' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'vfs_modified' => array('type' => 'timestamp'),
			'vfs_size' => array('type' => 'int','precision' => '4'),
			'vfs_mime_type' => array('type' => 'varchar','precision' => '64'),
			'vfs_deleteable' => array('type' => 'char','precision' => '1','default' => 'Y'),
			'vfs_comment' => array('type' => 'varchar','precision' => '255'),
			'vfs_app' => array('type' => 'varchar','precision' => '25'),
			'vfs_directory' => array('type' => 'varchar','precision' => '233'),
			'vfs_name' => array('type' => 'varchar','precision' => '100','nullable' => False),
			'vfs_link_directory' => array('type' => 'varchar','precision' => '255'),
			'vfs_link_name' => array('type' => 'varchar','precision' => '128'),
			'vfs_version' => array('type' => 'varchar','precision' => '30','nullable' => False,'default' => '0.0.0.0'),
			'vfs_content' => array('type' => 'blob')
		),
		'pk' => array('vfs_file_id'),
		'fk' => array(),
		'ix' => array(array('vfs_directory','vfs_name')),
		'uc' => array()
	),
	'egw_history_log' => array(
		'fd' => array(
			'history_id' => array('type' => 'auto','precision' => '4','nullable' => False),
			'history_record_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'history_appname' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'history_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'history_status' => array('type' => 'varchar','precision' => '64','nullable' => False),
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
			'sync_changedby' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('sync_id'),
		'fk' => array(),
		'ix' => array('sync_added','sync_modified','sync_deleted','sync_changedby',array('sync_appname','sync_contentid')),
		'uc' => array()
	),
	'egw_links' => array(
		'fd' => array(
			'link_id' => array('type' => 'auto','nullable' => False),
			'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'link_remark' => array('type' => 'varchar','precision' => '100'),
			'link_lastmod' => array('type' => 'int','precision' => '8','nullable' => False),
			'link_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'deleted' => array('type' => 'timestamp')
		),
		'pk' => array('link_id'),
		'fk' => array(),
		'ix' => array('deleted',array('link_app1','link_id1','link_lastmod'),array('link_app2','link_id2','link_lastmod')),
		'uc' => array()
	),
	'egw_addressbook' => array(
		'fd' => array(
			'contact_id' => array('type' => 'auto','nullable' => False),
			'contact_tid' => array('type' => 'char','precision' => '1','default' => 'n'),
			'contact_owner' => array('type' => 'int','precision' => '8','nullable' => False),
			'contact_private' => array('type' => 'int','precision' => '1','default' => '0'),
			'cat_id' => array('type' => 'varchar','precision' => '255'),
			'n_family' => array('type' => 'varchar','precision' => '64'),
			'n_given' => array('type' => 'varchar','precision' => '64'),
			'n_middle' => array('type' => 'varchar','precision' => '64'),
			'n_prefix' => array('type' => 'varchar','precision' => '64'),
			'n_suffix' => array('type' => 'varchar','precision' => '64'),
			'n_fn' => array('type' => 'varchar','precision' => '128'),
			'n_fileas' => array('type' => 'varchar','precision' => '255'),
			'contact_bday' => array('type' => 'varchar','precision' => '12'),
			'org_name' => array('type' => 'varchar','precision' => '128'),
			'org_unit' => array('type' => 'varchar','precision' => '64'),
			'contact_title' => array('type' => 'varchar','precision' => '64'),
			'contact_role' => array('type' => 'varchar','precision' => '64'),
			'contact_assistent' => array('type' => 'varchar','precision' => '64'),
			'contact_room' => array('type' => 'varchar','precision' => '64'),
			'adr_one_street' => array('type' => 'varchar','precision' => '64'),
			'adr_one_street2' => array('type' => 'varchar','precision' => '64'),
			'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
			'adr_one_region' => array('type' => 'varchar','precision' => '64'),
			'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
			'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
			'contact_label' => array('type' => 'text'),
			'adr_two_street' => array('type' => 'varchar','precision' => '64'),
			'adr_two_street2' => array('type' => 'varchar','precision' => '64'),
			'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
			'adr_two_region' => array('type' => 'varchar','precision' => '64'),
			'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
			'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
			'tel_work' => array('type' => 'varchar','precision' => '40'),
			'tel_cell' => array('type' => 'varchar','precision' => '40'),
			'tel_fax' => array('type' => 'varchar','precision' => '40'),
			'tel_assistent' => array('type' => 'varchar','precision' => '40'),
			'tel_car' => array('type' => 'varchar','precision' => '40'),
			'tel_pager' => array('type' => 'varchar','precision' => '40'),
			'tel_home' => array('type' => 'varchar','precision' => '40'),
			'tel_fax_home' => array('type' => 'varchar','precision' => '40'),
			'tel_cell_private' => array('type' => 'varchar','precision' => '40'),
			'tel_other' => array('type' => 'varchar','precision' => '40'),
			'tel_prefer' => array('type' => 'varchar','precision' => '32'),
			'contact_email' => array('type' => 'varchar','precision' => '128'),
			'contact_email_home' => array('type' => 'varchar','precision' => '128'),
			'contact_url' => array('type' => 'varchar','precision' => '128'),
			'contact_url_home' => array('type' => 'varchar','precision' => '128'),
			'contact_freebusy_uri' => array('type' => 'varchar','precision' => '128'),
			'contact_calendar_uri' => array('type' => 'varchar','precision' => '128'),
			'contact_note' => array('type' => 'text'),
			'contact_tz' => array('type' => 'varchar','precision' => '8'),
			'contact_geo' => array('type' => 'varchar','precision' => '32'),
			'contact_pubkey' => array('type' => 'text'),
			'contact_created' => array('type' => 'int','precision' => '8'),
			'contact_creator' => array('type' => 'int','precision' => '4','nullable' => False),
			'contact_modified' => array('type' => 'int','precision' => '8','nullable' => False),
			'contact_modifier' => array('type' => 'int','precision' => '4'),
			'contact_jpegphoto' => array('type' => 'blob'),
			'account_id' => array('type' => 'int','precision' => '4'),
			'contact_etag' => array('type' => 'int','precision' => '4','default' => '0'),
			'contact_uid' => array('type' => 'varchar','precision' => '255'),
			'adr_one_countrycode' => array('type' => 'varchar','precision' => '2'),
			'adr_two_countrycode' => array('type' => 'varchar','precision' => '2')
		),
		'pk' => array('contact_id'),
		'fk' => array(),
		'ix' => array('contact_owner','cat_id','n_fileas','contact_uid',array('n_family','n_given'),array('n_given','n_family'),array('org_name','n_family','n_given')),
		'uc' => array('account_id')
	),
	'egw_addressbook_extra' => array(
		'fd' => array(
			'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'contact_owner' => array('type' => 'int','precision' => '8'),
			'contact_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'contact_value' => array('type' => 'text')
		),
		'pk' => array('contact_id','contact_name'),
		'fk' => array(),
		'ix' => array(array('contact_name','contact_value(32)')),
		'uc' => array()
	),
	'egw_addressbook_lists' => array(
		'fd' => array(
			'list_id' => array('type' => 'auto','nullable' => False),
			'list_name' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'list_owner' => array('type' => 'int','precision' => '4','nullable' => False),
			'list_created' => array('type' => 'int','precision' => '8'),
			'list_creator' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('list_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('list_owner','list_name'))
	),
	'egw_addressbook2list' => array(
		'fd' => array(
			'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'list_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'list_added' => array('type' => 'int','precision' => '8'),
			'list_added_by' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('contact_id','list_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_sqlfs' => array(
		'fd' => array(
			'fs_id' => array('type' => 'auto','nullable' => False),
			'fs_dir' => array('type' => 'int','precision' => '4','nullable' => False),
			'fs_name' => array('type' => 'varchar','precision' => '200','nullable' => False),
			'fs_mode' => array('type' => 'int','precision' => '2','nullable' => False),
			'fs_uid' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'fs_gid' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'fs_created' => array('type' => 'timestamp','precision' => '8','nullable' => False),
			'fs_modified' => array('type' => 'timestamp','precision' => '8','nullable' => False),
			'fs_mime' => array('type' => 'varchar','precision' => '96','nullable' => False),
			'fs_size' => array('type' => 'int','precision' => '8','nullable' => False),
			'fs_creator' => array('type' => 'int','precision' => '4','nullable' => False),
			'fs_modifier' => array('type' => 'int','precision' => '4'),
			'fs_active' => array('type' => 'bool','nullable' => False,'default' => 't'),
			'fs_content' => array('type' => 'blob'),
			'fs_link' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('fs_id'),
		'fk' => array(),
		'ix' => array(array('fs_dir','fs_active','fs_name')),
		'uc' => array()
	),
	'egw_index_keywords' => array(
		'fd' => array(
			'si_id' => array('type' => 'auto','nullable' => False),
			'si_keyword' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'si_ignore' => array('type' => 'bool')
		),
		'pk' => array('si_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('si_keyword')
	),
	'egw_index' => array(
		'fd' => array(
			'si_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'si_app_id' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'si_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'si_owner' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('si_app','si_app_id','si_id'),
		'fk' => array(),
		'ix' => array('si_id'),
		'uc' => array()
	),
	'egw_cat2entry' => array(
		'fd' => array(
			'ce_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'ce_app_id' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'cat_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'ce_owner' => array('type' => 'int','precision' => '4','nullable' => False)
		),
		'pk' => array('ce_app','ce_app_id','cat_id'),
		'fk' => array(),
		'ix' => array('cat_id'),
		'uc' => array()
	),
	'egw_locks' => array(
		'fd' => array(
			'lock_token' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'lock_path' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'lock_expires' => array('type' => 'int','precision' => '8','nullable' => False),
			'lock_owner' => array('type' => 'varchar','precision' => '255'),
			'lock_recursive' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_write' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_exclusive' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_created' => array('type' => 'int','precision' => '8','default' => '0'),
			'lock_modified' => array('type' => 'int','precision' => '8','default' => '0')
		),
		'pk' => array('lock_token'),
		'fk' => array(),
		'ix' => array('lock_path','lock_expires'),
		'uc' => array()
	),
	'egw_sqlfs_props' => array(
		'fd' => array(
			'fs_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'prop_namespace' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'prop_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'prop_value' => array('type' => 'text')
		),
		'pk' => array('fs_id','prop_namespace','prop_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	)
);
