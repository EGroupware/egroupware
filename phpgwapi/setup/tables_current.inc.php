<?php
/**
 * EGroupware - API Setup
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
			'acl_location' => array('type' => 'varchar','meta' => 'account','precision' => '255','nullable' => False),
			'acl_account' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False),
			'acl_rights' => array('type' => 'int','precision' => '4')
		),
		'pk' => array('acl_appname','acl_location','acl_account'),
		'fk' => array(),
		'ix' => array('acl_account',array('acl_location','acl_account'),array('acl_appname','acl_account')),
		'uc' => array()
	),
	'egw_accounts' => array(
		'fd' => array(
			'account_id' => array('type' => 'auto','meta' => 'account-abs','nullable' => False),
			'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'account_pwd' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'account_lastlogin' => array('type' => 'int','precision' => '4'),
			'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
			'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
			'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
			'account_expires' => array('type' => 'int','precision' => '4'),
			'account_type' => array('type' => 'char','precision' => '1'),
			'account_primary_group' => array('type' => 'int','meta' => 'group','precision' => '4','nullable' => False,'default' => '0')
		),
		'pk' => array('account_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('account_lid')
	),
	'egw_preferences' => array(
		'fd' => array(
			'preference_owner' => array('type' => 'int','meta' => 'account-prefs','precision' => '4','nullable' => False),
			'preference_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'preference_value' => array('type' => 'text','nullable' => False)
		),
		'pk' => array('preference_owner','preference_app'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_access_log' => array(
		'fd' => array(
			'sessionid' => array('type' => 'auto','nullable' => False,'comment' => 'primary key'),
			'loginid' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'username used to login'),
			'ip' => array('type' => 'varchar','precision' => '40','nullable' => False,'comment' => 'ip of user'),
			'li' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'TS if login'),
			'lo' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'TD of logout'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0','comment' => 'numerical account id'),
			'session_dla' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'TS of last user action'),
			'session_action' => array('type' => 'varchar','precision' => '64','comment' => 'menuaction or path of last user action'),
			'session_php' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'php session-id or error-message'),
			'notification_heartbeat' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'TS of last notification request'),
			'user_agent' => array('type' => 'varchar','precision' => '255','comment' => 'User-agent of browser/device')
		),
		'pk' => array('sessionid'),
		'fk' => array(),
		'ix' => array('li','lo','session_dla','session_php','notification_heartbeat',array('account_id','ip','li'),array('account_id','loginid','li')),
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
			'cat_id' => array('type' => 'auto','meta' => 'category','precision' => '4','nullable' => False),
			'cat_main' => array('type' => 'int','meta' => 'category','precision' => '4','nullable' => False,'default' => '0'),
			'cat_parent' => array('type' => 'int','meta' => 'category','precision' => '4','nullable' => False,'default' => '0'),
			'cat_level' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '0'),
			'cat_owner' => array('type' => 'varchar','meta' => 'account-commasep','precision' => '255','nullable' => False,'default' => '0'),
			'cat_access' => array('type' => 'varchar','precision' => '7'),
			'cat_appname' => array('type' => 'varchar','precision' => '50','nullable' => False),
			'cat_name' => array('type' => 'varchar','precision' => '150','nullable' => False),
			'cat_description' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cat_data' => array('type' => 'text'),
			'last_mod' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False)
		),
		'pk' => array('cat_id'),
		'fk' => array(),
		'ix' => array(array('cat_appname','cat_owner','cat_parent','cat_level')),
		'uc' => array()
	),
	'egw_history_log' => array(
		'fd' => array(
			'history_id' => array('type' => 'auto','precision' => '4','nullable' => False),
			'history_record_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'history_appname' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'history_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'history_status' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'history_new_value' => array('type' => 'text','nullable' => False),
			'history_timestamp' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'history_old_value' => array('type' => 'text','nullable' => False),
			'sessionid' => array('type' => 'int','precision' => '4','comment' => 'primary key to egw_access_log')
		),
		'pk' => array('history_id'),
		'fk' => array(),
		'ix' => array(array('history_appname','history_record_id','history_id')),
		'uc' => array()
	),
	'egw_async' => array(
		'fd' => array(
			'async_id' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'async_next' => array('type' => 'int','meta' => 'timestamp','precision' => '4','nullable' => False,'comment' => 'timestamp of next run'),
			'async_times' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'serialized array with values for keys hour,min,day,month,year'),
			'async_method' => array('type' => 'varchar','precision' => '80','nullable' => False,'comment' => 'app.class.method class::method to execute'),
			'async_data' => array('type' => 'text','nullable' => False,'comment' => 'serialized array with data to pass to method'),
			'async_account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0','comment' => 'creator of job')
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
			'sync_changedby' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False)
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
			'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False,'meta' => array("link_app1='home-accounts'" => 'account')),
			'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
			'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False,'meta' => array("link_app2='home-accounts'" => 'account')),
			'link_remark' => array('type' => 'varchar','precision' => '100'),
			'link_lastmod' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False),
			'link_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False),
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
			'contact_owner' => array('type' => 'int','meta' => 'account','precision' => '8','nullable' => False,'comment' => 'account or group id of the adressbook'),
			'contact_private' => array('type' => 'int','precision' => '1','default' => '0','comment' => 'privat or personal'),
			'cat_id' => array('type' => 'varchar','meta' => 'category','precision' => '255','comment' => 'Category(s)'),
			'n_family' => array('type' => 'varchar','precision' => '64','comment' => 'Family name'),
			'n_given' => array('type' => 'varchar','precision' => '64','comment' => 'Given Name'),
			'n_middle' => array('type' => 'varchar','precision' => '64'),
			'n_prefix' => array('type' => 'varchar','precision' => '64','comment' => 'Prefix'),
			'n_suffix' => array('type' => 'varchar','precision' => '64','comment' => 'Suffix'),
			'n_fn' => array('type' => 'varchar','precision' => '128','comment' => 'Full name'),
			'n_fileas' => array('type' => 'varchar','precision' => '255','comment' => 'sort as'),
			'contact_bday' => array('type' => 'varchar','precision' => '12','comment' => 'Birtday'),
			'org_name' => array('type' => 'varchar','precision' => '128','comment' => 'Organisation'),
			'org_unit' => array('type' => 'varchar','precision' => '64','comment' => 'Department'),
			'contact_title' => array('type' => 'varchar','precision' => '64','comment' => 'jobtittle'),
			'contact_role' => array('type' => 'varchar','precision' => '64','comment' => 'role'),
			'contact_assistent' => array('type' => 'varchar','precision' => '64','comment' => 'Name of the Assistent (for phone number)'),
			'contact_room' => array('type' => 'varchar','precision' => '64','comment' => 'room'),
			'adr_one_street' => array('type' => 'varchar','precision' => '64','comment' => 'street (business)'),
			'adr_one_street2' => array('type' => 'varchar','precision' => '64','comment' => 'street (business) - 2. line'),
			'adr_one_locality' => array('type' => 'varchar','precision' => '64','comment' => 'city (business)'),
			'adr_one_region' => array('type' => 'varchar','precision' => '64','comment' => 'region (business)'),
			'adr_one_postalcode' => array('type' => 'varchar','precision' => '64','comment' => 'postalcode (business)'),
			'adr_one_countryname' => array('type' => 'varchar','precision' => '64','comment' => 'countryname (business)'),
			'contact_label' => array('type' => 'text','comment' => 'currently not used'),
			'adr_two_street' => array('type' => 'varchar','precision' => '64','comment' => 'street (private)'),
			'adr_two_street2' => array('type' => 'varchar','precision' => '64','comment' => 'street (private) - 2. line'),
			'adr_two_locality' => array('type' => 'varchar','precision' => '64','comment' => 'city (private)'),
			'adr_two_region' => array('type' => 'varchar','precision' => '64','comment' => 'region (private)'),
			'adr_two_postalcode' => array('type' => 'varchar','precision' => '64','comment' => 'postalcode (private)'),
			'adr_two_countryname' => array('type' => 'varchar','precision' => '64','comment' => 'countryname (private)'),
			'tel_work' => array('type' => 'varchar','precision' => '40','comment' => 'phone-number (business)'),
			'tel_cell' => array('type' => 'varchar','precision' => '40','comment' => 'mobil phone (business)'),
			'tel_fax' => array('type' => 'varchar','precision' => '40','comment' => 'fax-number (business)'),
			'tel_assistent' => array('type' => 'varchar','precision' => '40','comment' => 'phone-number assistent'),
			'tel_car' => array('type' => 'varchar','precision' => '40'),
			'tel_pager' => array('type' => 'varchar','precision' => '40','comment' => 'pager'),
			'tel_home' => array('type' => 'varchar','precision' => '40','comment' => 'phone-number (private)'),
			'tel_fax_home' => array('type' => 'varchar','precision' => '40','comment' => 'fax-number (private)'),
			'tel_cell_private' => array('type' => 'varchar','precision' => '40','comment' => 'mobil phone (private)'),
			'tel_other' => array('type' => 'varchar','precision' => '40','comment' => 'other phone'),
			'tel_prefer' => array('type' => 'varchar','precision' => '32','comment' => 'prefered phone-number'),
			'contact_email' => array('type' => 'varchar','precision' => '128','comment' => 'email address (business)'),
			'contact_email_home' => array('type' => 'varchar','precision' => '128','comment' => 'email address (private)'),
			'contact_url' => array('type' => 'varchar','precision' => '128','comment' => 'website (business)'),
			'contact_url_home' => array('type' => 'varchar','precision' => '128','comment' => 'website (private)'),
			'contact_freebusy_uri' => array('type' => 'varchar','precision' => '128','comment' => 'freebusy-url for calendar of the contact'),
			'contact_calendar_uri' => array('type' => 'varchar','precision' => '128','comment' => 'url for users calendar - currently not used'),
			'contact_note' => array('type' => 'text','comment' => 'notes field'),
			'contact_tz' => array('type' => 'varchar','precision' => '8','comment' => 'timezone difference'),
			'contact_geo' => array('type' => 'varchar','precision' => '32','comment' => 'currently not used'),
			'contact_pubkey' => array('type' => 'text','comment' => 'public key'),
			'contact_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of the creation'),
			'contact_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account id of the creator'),
			'contact_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'timestamp of the last modified'),
			'contact_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id of the last modified'),
			'contact_jpegphoto' => array('type' => 'blob','comment' => 'photo of the contact (attachment)'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id'),
			'contact_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag of the changes'),
			'contact_uid' => array('type' => 'varchar','precision' => '255','comment' => 'unique id of the contact'),
			'adr_one_countrycode' => array('type' => 'varchar','precision' => '2','comment' => 'countrycode (business)'),
			'adr_two_countrycode' => array('type' => 'varchar','precision' => '2','comment' => 'countrycode (private)'),
			'carddav_name' => array('type' => 'varchar','precision' => '200','comment' => 'name part of CardDAV URL, if specified by client')
		),
		'pk' => array('contact_id'),
		'fk' => array(),
		'ix' => array('contact_owner','cat_id','n_fileas','contact_modified','contact_uid','carddav_name',array('n_family','n_given'),array('n_given','n_family'),array('org_name','n_family','n_given')),
		'uc' => array('account_id')
	),
	'egw_addressbook_extra' => array(
		'fd' => array(
			'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'contact_owner' => array('type' => 'int','meta' => 'account','precision' => '8'),
			'contact_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '255','nullable' => False),
			'contact_value' => array('type' => 'text','meta' => 'cfvalue')
		),
		'pk' => array('contact_id','contact_name'),
		'fk' => array(),
		'ix' => array('contact_name'),
		'uc' => array()
	),
	'egw_addressbook_lists' => array(
		'fd' => array(
			'list_id' => array('type' => 'auto','nullable' => False),
			'list_name' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'list_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False),
			'list_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'list_creator' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'list_uid' => array('type' => 'varchar','precision' => '255'),
			'list_carddav_name' => array('type' => 'varchar','precision' => '64'),
			'list_etag' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
			'list_modified' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'list_modifier' => array('type' => 'int','meta' => 'user','precision' => '4')
		),
		'pk' => array('list_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('list_uid','list_carddav_name',array('list_owner','list_name'))
	),
	'egw_addressbook2list' => array(
		'fd' => array(
			'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'list_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'list_added' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'list_added_by' => array('type' => 'int','meta' => 'user','precision' => '4')
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
			'fs_uid' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0'),
			'fs_gid' => array('type' => 'int','meta' => 'group-abs','precision' => '4','nullable' => False,'default' => '0'),
			'fs_created' => array('type' => 'timestamp','precision' => '8','nullable' => False),
			'fs_modified' => array('type' => 'timestamp','precision' => '8','nullable' => False),
			'fs_mime' => array('type' => 'varchar','precision' => '96','nullable' => False),
			'fs_size' => array('type' => 'int','precision' => '8','nullable' => False),
			'fs_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'fs_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
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
			'si_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False)
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
			'cat_id' => array('type' => 'int','meta' => 'category','precision' => '4','nullable' => False),
			'ce_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False)
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
			'lock_expires' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False),
			'lock_owner' => array('type' => 'varchar','precision' => '255'),
			'lock_recursive' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_write' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_exclusive' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','default' => '0'),
			'lock_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','default' => '0')
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
	),
	'egw_customfields' => array(
		'fd' => array(
			'cf_id' => array('type' => 'auto','nullable' => False),
			'cf_app' => array('type' => 'varchar','precision' => '50','nullable' => False,'comment' => 'app-name cf belongs too'),
			'cf_name' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'internal name'),
			'cf_label' => array('type' => 'varchar','precision' => '128','comment' => 'label to display'),
			'cf_type' => array('type' => 'varchar','precision' => '64','nullable' => False,'default' => 'text','comment' => 'type of field'),
			'cf_type2' => array('type' => 'varchar','precision' => '2048','comment' => 'comma-separated subtypes of app, cf is valid for'),
			'cf_help' => array('type' => 'varchar','precision' => '256','comment' => 'helptext'),
			'cf_values' => array('type' => 'varchar','precision' => '8096','comment' => 'json object with value label pairs'),
			'cf_len' => array('type' => 'int','precision' => '2','comment' => 'length or columns of field'),
			'cf_rows' => array('type' => 'int','precision' => '2','comment' => 'rows of field'),
			'cf_order' => array('type' => 'int','precision' => '2','comment' => 'order to display fields'),
			'cf_needed' => array('type' => 'bool','default' => '0','comment' => 'field is required'),
			'cf_private' => array('type' => 'varchar','meta' => 'account-commasep','precision' => '2048','comment' => 'comma-separated account_id'),
			'cf_modifier' => array('type' => 'int','meta' => 'account','precision' => '4','comment' => 'last modifier'),
			'cf_modified' => array('type' => 'timestamp','default' => 'current_timestamp','comment' => 'last modification time'),
			'cf_tab' => array('type' => 'varchar','precision' => '64','comment' => 'tab customfield should be shown')
		),
		'pk' => array('cf_id'),
		'fk' => array(),
		'ix' => array(array('cf_app','cf_order')),
		'uc' => array(array('cf_app','cf_name'))
	)
);
