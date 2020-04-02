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
 */

$phpgw_baseline = array(
	'egw_config' => array(
		'fd' => array(
			'config_app' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'config_name' => array('type' => 'ascii','precision' => '32','nullable' => False),
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
			'app_name' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'app_enabled' => array('type' => 'int','precision' => '4','nullable' => False),
			'app_order' => array('type' => 'int','precision' => '4','nullable' => False),
			'app_tables' => array('type' => 'ascii','precision' => '8192','nullable' => False),
			'app_version' => array('type' => 'ascii','precision' => '20','nullable' => False,'default' => '0.0'),
			'app_icon' => array('type' => 'ascii','precision' => '128'),
			'app_icon_app' => array('type' => 'ascii','precision' => '16'),
			'app_index' => array('type' => 'ascii','precision' => '128')
		),
		'pk' => array('app_id'),
		'fk' => array(),
		'ix' => array(array('app_enabled','app_order')),
		'uc' => array('app_name')
	),
	'egw_acl' => array(
		'fd' => array(
			'acl_appname' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'acl_location' => array('type' => 'ascii','meta' => 'account','precision' => '16','nullable' => False),
			'acl_account' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False),
			'acl_rights' => array('type' => 'int','precision' => '4'),
			'acl_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('acl_id'),
		'fk' => array(),
		'ix' => array('acl_account',array('acl_location','acl_account'),array('acl_appname','acl_account')),
		'uc' => array(array('acl_appname','acl_location','acl_account'))
	),
	'egw_accounts' => array(
		'fd' => array(
			'account_id' => array('type' => 'auto','meta' => 'account-abs','nullable' => False),
			'account_lid' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'account_pwd' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'account_lastlogin' => array('type' => 'int','precision' => '4'),
			'account_lastloginfrom' => array('type' => 'ascii','precision' => '48','comment' => 'ip'),
			'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
			'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
			'account_expires' => array('type' => 'int','precision' => '4'),
			'account_type' => array('type' => 'char','precision' => '1'),
			'account_primary_group' => array('type' => 'int','meta' => 'group','precision' => '4','nullable' => False,'default' => '0'),
			'account_description' => array('type' => 'varchar','precision' => '255','comment' => 'group description')
		),
		'pk' => array('account_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('account_lid')
	),
	'egw_preferences' => array(
		'fd' => array(
			'preference_owner' => array('type' => 'int','meta' => 'account-prefs','precision' => '4','nullable' => False),
			'preference_app' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'preference_value' => array('type' => 'text','nullable' => False),
			'preference_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('preference_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('preference_owner','preference_app'))
	),
	'egw_access_log' => array(
		'fd' => array(
			'sessionid' => array('type' => 'auto','nullable' => False,'comment' => 'primary key'),
			'loginid' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'username used to login'),
			'ip' => array('type' => 'ascii','precision' => '48','nullable' => False,'comment' => 'ip of user'),
			'li' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'TS if login'),
			'lo' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'TD of logout'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0','comment' => 'numerical account id'),
			'session_dla' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'TS of last user action'),
			'session_action' => array('type' => 'ascii','precision' => '64','comment' => 'menuaction or path of last user action'),
			'session_php' => array('type' => 'ascii','precision' => '64','nullable' => False,'comment' => 'php session-id or error-message'),
			'notification_heartbeat' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'TS of last notification request'),
			'user_agent' => array('type' => 'ascii','precision' => '255','comment' => 'User-agent of browser/device')
		),
		'pk' => array('sessionid'),
		'fk' => array(),
		'ix' => array('li','lo','session_dla','session_php','notification_heartbeat',array('account_id','ip','li'),array('account_id','loginid','li')),
		'uc' => array()
	),
	'egw_languages' => array(
		'fd' => array(
			'lang_id' => array('type' => 'ascii','precision' => '5','nullable' => False),
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
			'app_name' => array('type' => 'ascii','precision' => '16','nullable' => False,'default' => 'common'),
			'message_id' => array('type' => 'ascii','precision' => '128','nullable' => False,'default' => ''),
			'content' => array('type' => 'varchar','precision' => '8192'),
			'lang_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('lang_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('lang','app_name','message_id'))
	),
	'egw_categories' => array(
		'fd' => array(
			'cat_id' => array('type' => 'auto','meta' => 'category','precision' => '4','nullable' => False),
			'cat_main' => array('type' => 'int','meta' => 'category','precision' => '4','nullable' => False,'default' => '0'),
			'cat_parent' => array('type' => 'int','meta' => 'category','precision' => '4','nullable' => False,'default' => '0'),
			'cat_level' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '0'),
			'cat_owner' => array('type' => 'ascii','meta' => 'account-commasep','precision' => '255','nullable' => False,'default' => '0'),
			'cat_access' => array('type' => 'ascii','precision' => '7'),
			'cat_appname' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'cat_name' => array('type' => 'varchar','precision' => '150','nullable' => False),
			'cat_description' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cat_data' => array('type' => 'varchar','precision' => '8192'),
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
			'history_appname' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'history_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'history_status' => array('type' => 'varchar','precision' => '32','nullable' => False),
			'history_new_value' => array('type' => 'longtext','nullable' => False),
			'history_timestamp' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'history_old_value' => array('type' => 'longtext','nullable' => False),
			'sessionid' => array('type' => 'int','precision' => '4','comment' => 'primary key to egw_access_log'),
			'share_email' => array('type' => 'varchar','precision' => '4096','comment' => 'email addresses of share who made the change, comma seperated')
		),
		'pk' => array('history_id'),
		'fk' => array(),
		'ix' => array(array('history_appname','history_record_id','history_id')),
		'uc' => array()
	),
	'egw_async' => array(
		'fd' => array(
			'async_id' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'async_next' => array('type' => 'int','meta' => 'timestamp','precision' => '4','nullable' => False,'comment' => 'timestamp of next run'),
			'async_times' => array('type' => 'ascii','precision' => '255','nullable' => False,'comment' => 'serialized array with values for keys hour,min,day,month,year'),
			'async_method' => array('type' => 'ascii','precision' => '80','nullable' => False,'comment' => 'app.class.method class::method to execute'),
			'async_data' => array('type' => 'varchar','precision' => '8192','nullable' => False,'comment' => 'serialized array with data to pass to method'),
			'async_account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0','comment' => 'creator of job'),
			'async_auto_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('async_auto_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('async_id')
	),
	'egw_links' => array(
		'fd' => array(
			'link_id' => array('type' => 'auto','nullable' => False),
			'link_app1' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'link_id1' => array('type' => 'ascii','meta' => array("link_app1='api-accounts'" => 'account'),'precision' => '64','nullable' => False),
			'link_app2' => array('type' => 'ascii','precision' => '16','nullable' => False),
			'link_id2' => array('type' => 'ascii','meta' => array("link_app2='api-accounts'" => 'account'),'precision' => '64','nullable' => False),
			'link_remark' => array('type' => 'varchar','precision' => '100'),
			'link_lastmod' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False),
			'link_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False),
			'deleted' => array('type' => 'timestamp')
		),
		'pk' => array('link_id'),
		'fk' => array(),
		'ix' => array('deleted',array('link_app1','link_id1','link_lastmod'),array('link_app2','link_id2','link_lastmod'),array('link_app1','link_app2','link_id1','link_id2')),
		'uc' => array()
	),
	'egw_addressbook' => array(
		'fd' => array(
			'contact_id' => array('type' => 'auto','nullable' => False),
			'contact_tid' => array('type' => 'char','precision' => '1','default' => 'n'),
			'contact_owner' => array('type' => 'int','meta' => 'account','precision' => '8','nullable' => False,'comment' => 'account or group id of the adressbook'),
			'contact_private' => array('type' => 'int','precision' => '1','default' => '0','comment' => 'privat or personal'),
			'cat_id' => array('type' => 'ascii','meta' => 'category','precision' => '255','comment' => 'Category(s)'),
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
			'contact_freebusy_uri' => array('type' => 'ascii','precision' => '128','comment' => 'freebusy-url for calendar of the contact'),
			'contact_calendar_uri' => array('type' => 'ascii','precision' => '128','comment' => 'url for users calendar - currently not used'),
			'contact_note' => array('type' => 'varchar','precision' => '8192','comment' => 'notes field'),
			'contact_tz' => array('type' => 'varchar','precision' => '8','comment' => 'timezone difference'),
			'contact_geo' => array('type' => 'ascii','precision' => '32','comment' => 'currently not used'),
			'contact_pubkey' => array('type' => 'ascii','precision' => '16384','comment' => 'public key'),
			'contact_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of the creation'),
			'contact_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account id of the creator'),
			'contact_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'timestamp of the last modified'),
			'contact_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id of the last modified'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'account id'),
			'contact_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag of the changes'),
			'contact_uid' => array('type' => 'ascii','precision' => '255','comment' => 'unique id of the contact'),
			'adr_one_countrycode' => array('type' => 'ascii','precision' => '2','comment' => 'countrycode (business)'),
			'adr_two_countrycode' => array('type' => 'ascii','precision' => '2','comment' => 'countrycode (private)'),
			'carddav_name' => array('type' => 'ascii','precision' => '260','comment' => 'name part of CardDAV URL, if specified by client'),
			'contact_files' => array('type' => 'int','precision' => '1','default' => '0','comment' => '&1: photo, &2: pgp, &4: smime')
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
			'contact_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '64','nullable' => False,'comment' => 'custom-field name'),
			'contact_value' => array('type' => 'varchar','meta' => 'cfvalue','precision' => '16384','comment' => 'custom-field value'),
			'contact_extra_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('contact_extra_id'),
		'fk' => array(),
		'ix' => array('contact_name',array('contact_id','contact_name')),
		'uc' => array()
	),
	'egw_addressbook_lists' => array(
		'fd' => array(
			'list_id' => array('type' => 'auto','nullable' => False),
			'list_name' => array('type' => 'varchar','precision' => '80','nullable' => False),
			'list_owner' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False),
			'list_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8'),
			'list_creator' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'list_uid' => array('type' => 'ascii','precision' => '128'),
			'list_carddav_name' => array('type' => 'ascii','precision' => '128'),
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
			'fs_mime' => array('type' => 'ascii','precision' => '96','nullable' => False),
			'fs_size' => array('type' => 'int','precision' => '8','nullable' => False),
			'fs_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False),
			'fs_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'fs_active' => array('type' => 'bool','nullable' => False,'default' => 't'),
			'fs_content' => array('type' => 'blob'),
			'fs_link' => array('type' => 'varchar','precision' => '255')
		),
		'pk' => array('fs_id'),
		'fk' => array(),
		'ix' => array(array('fs_dir','fs_active','fs_name(16)')),
		'uc' => array()
	),
	'egw_locks' => array(
		'fd' => array(
			'lock_token' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'lock_path' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'lock_expires' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False),
			'lock_owner' => array('type' => 'varchar','precision' => '255'),
			'lock_recursive' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_write' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_exclusive' => array('type' => 'bool','nullable' => False,'default' => '0'),
			'lock_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','default' => '0'),
			'lock_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','default' => '0'),
			'lock_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('lock_id'),
		'fk' => array(),
		'ix' => array('lock_path','lock_expires'),
		'uc' => array('lock_token')
	),
	'egw_sqlfs_props' => array(
		'fd' => array(
			'fs_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'prop_namespace' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'prop_name' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'prop_value' => array('type' => 'varchar','precision' => '16384'),
			'prop_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('prop_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('fs_id','prop_namespace','prop_name'))
	),
	'egw_customfields' => array(
		'fd' => array(
			'cf_id' => array('type' => 'auto','nullable' => False),
			'cf_app' => array('type' => 'ascii','precision' => '16','nullable' => False,'comment' => 'app-name cf belongs too'),
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
			'cf_private' => array('type' => 'ascii','meta' => 'account-commasep','precision' => '2048','comment' => 'comma-separated account_id'),
			'cf_modifier' => array('type' => 'int','meta' => 'account','precision' => '4','comment' => 'last modifier'),
			'cf_modified' => array('type' => 'timestamp','default' => 'current_timestamp','comment' => 'last modification time'),
			'cf_tab' => array('type' => 'varchar','precision' => '64','comment' => 'tab customfield should be shown')
		),
		'pk' => array('cf_id'),
		'fk' => array(),
		'ix' => array(array('cf_app','cf_order')),
		'uc' => array(array('cf_app','cf_name'))
	),
	'egw_sharing' => array(
		'fd' => array(
			'share_id' => array('type' => 'auto','nullable' => False,'comment' => 'auto-id'),
			'share_token' => array('type' => 'ascii','precision' => '64','nullable' => False,'comment' => 'secure token'),
			'share_path' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'path to share'),
			'share_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'owner of share'),
			'share_expires' => array('type' => 'date','comment' => 'expire date of share'),
			'share_writable' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=readable, 1=writable'),
			'share_with' => array('type' => 'varchar','precision' => '4096','comment' => 'email addresses, comma seperated'),
			'share_passwd' => array('type' => 'varchar','precision' => '128','comment' => 'optional password-hash'),
			'share_created' => array('type' => 'timestamp','nullable' => False,'comment' => 'creation date'),
			'share_last_accessed' => array('type' => 'timestamp','comment' => 'last access of share')
		),
		'pk' => array('share_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('share_token')
	),
	'egw_mailaccounts' => array(
		'fd' => array(
			'mail_id' => array('type' => 'auto','nullable' => False,'comment' => 'the id'),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False,'comment' => 'account id of the owner, can be user AND group'),
			'mail_type' => array('type' => 'int','precision' => '1','nullable' => False,'comment' => '0=active, 1=alias, 2=forward, 3=forwardOnly, 4=quota'),
			'mail_value' => array('type' => 'ascii','precision' => '128','nullable' => False,'comment' => 'the value (that should be) corresponding to the mail_type')
		),
		'pk' => array('mail_id'),
		'fk' => array(),
		'ix' => array('mail_value',array('account_id','mail_type')),
		'uc' => array()
	),
	'egw_ea_accounts' => array(
		'fd' => array(
			'acc_id' => array('type' => 'auto','nullable' => False),
			'acc_name' => array('type' => 'varchar','precision' => '80','comment' => 'description'),
			'ident_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'standard identity'),
			'acc_imap_host' => array('type' => 'ascii','precision' => '128','nullable' => False,'comment' => 'imap hostname'),
			'acc_imap_ssl' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_imap_port' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '143','comment' => 'imap port'),
			'acc_sieve_enabled' => array('type' => 'bool','default' => '0','comment' => 'sieve enabled'),
			'acc_sieve_host' => array('type' => 'ascii','precision' => '128','comment' => 'sieve host, default imap_host'),
			'acc_sieve_port' => array('type' => 'int','precision' => '4','default' => '4190'),
			'acc_folder_sent' => array('type' => 'varchar','precision' => '128','comment' => 'sent folder'),
			'acc_folder_trash' => array('type' => 'varchar','precision' => '128','comment' => 'trash folder'),
			'acc_folder_draft' => array('type' => 'varchar','precision' => '128','comment' => 'draft folder'),
			'acc_folder_template' => array('type' => 'varchar','precision' => '128','comment' => 'template folder'),
			'acc_folder_archive' => array('type' => 'varchar','precision' => '128','comment' => 'archive folder'),
			'acc_smtp_host' => array('type' => 'varchar','precision' => '128','comment' => 'smtp hostname'),
			'acc_smtp_ssl' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_smtp_port' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '25','comment' => 'smtp port'),
			'acc_smtp_type' => array('type' => 'ascii','precision' => '32','default' => 'emailadmin_smtp','comment' => 'smtp class to use'),
			'acc_imap_type' => array('type' => 'ascii','precision' => '32','default' => 'emailadmin_imap','comment' => 'imap class to use'),
			'acc_imap_logintype' => array('type' => 'ascii','precision' => '20','comment' => 'standard, vmailmgr, admin, uidNumber'),
			'acc_domain' => array('type' => 'varchar','precision' => '100','comment' => 'domain name'),
			'acc_user_editable' => array('type' => 'bool','nullable' => False,'default' => '1','comment' => '0=no, 1=yes'),
			'acc_sieve_ssl' => array('type' => 'int','precision' => '1','default' => '1','comment' => '0=none, 1=starttls, 2=tls, 3=ssl, &8=validate certificate'),
			'acc_modified' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
			'acc_modifier' => array('type' => 'int','meta' => 'user','precision' => '4'),
			'acc_smtp_auth_session' => array('type' => 'bool','comment' => '0=no, 1=yes, use username/pw from current user'),
			'acc_folder_junk' => array('type' => 'varchar','precision' => '128','comment' => 'junk folder'),
			'acc_imap_default_quota' => array('type' => 'int','precision' => '4','comment' => 'default quota, if no user specific one set'),
			'acc_imap_timeout' => array('type' => 'int','precision' => '2','comment' => 'timeout for imap connection'),
			'acc_user_forward' => array('type' => 'bool','default' => '0','comment' => 'allow user to define forwards'),
			'acc_further_identities' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '1','comment' => '0=no, 1=yes, 2=only matching aliases'),
			'acc_folder_ham' => array('type' => 'varchar','precision' => '128','comment' => 'ham folder'),
			'acc_spam_api' => array('type' => 'varchar','precision' => '128','comment' => 'SpamTitan API URL')
		),
		'pk' => array('acc_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_ea_credentials' => array(
		'fd' => array(
			'cred_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'into egw_ea_accounts'),
			'cred_type' => array('type' => 'int','precision' => '1','nullable' => False,'comment' => '&1=imap, &2=smtp, &4=admin'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'account_id or 0=all'),
			'cred_username' => array('type' => 'varchar','precision' => '80','nullable' => False,'comment' => 'username'),
			'cred_password' => array('type' => 'varchar','precision' => '16384','comment' => 'password encrypted'),
			'cred_pw_enc' => array('type' => 'int','precision' => '1','default' => '0','comment' => '0=not, 1=user pw, 2=system')
		),
		'pk' => array('cred_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('acc_id','account_id','cred_type'))
	),
	'egw_ea_identities' => array(
		'fd' => array(
			'ident_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'for which account'),
			'ident_realname' => array('type' => 'varchar','precision' => '128','nullable' => False,'comment' => 'real name'),
			'ident_email' => array('type' => 'varchar','precision' => '128','comment' => 'email address'),
			'ident_org' => array('type' => 'varchar','precision' => '128','comment' => 'organisation'),
			'ident_signature' => array('type' => 'text','comment' => 'signature text'),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False,'default' => '0','comment' => '0=all users of give mail account'),
			'ident_name' => array('type' => 'varchar','precision' => '128','comment' => 'name of identity to display')
		),
		'pk' => array('ident_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_ea_valid' => array(
		'fd' => array(
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'account_id' => array('type' => 'int','meta' => 'account','precision' => '4','nullable' => False)
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array(array('account_id','acc_id')),
		'uc' => array(array('acc_id','account_id'))
	),
	'egw_ea_notifications' => array(
		'fd' => array(
			'notif_id' => array('type' => 'auto','nullable' => False),
			'acc_id' => array('type' => 'int','precision' => '4','nullable' => False,'comment' => 'mail account'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'user account'),
			'notif_folder' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'folder name')
		),
		'pk' => array('notif_id'),
		'fk' => array(),
		'ix' => array(array('account_id','acc_id')),
		'uc' => array()
	)
);
