<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts from 14.1 onwards
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Include older eGroupWare update support */
include('tables_update_0_9_9.inc.php');
include('tables_update_0_9_10.inc.php');
include('tables_update_0_9_12.inc.php');
include('tables_update_0_9_14.inc.php');
include('tables_update_1_0.inc.php');
include('tables_update_1_2.inc.php');
include('tables_update_1_4.inc.php');
include('tables_update_1_6.inc.php');
include('tables_update_1_8.inc.php');

function phpgwapi_upgrade14_1()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_sharing',array(
		'fd' => array(
			'share_id' => array('type' => 'auto','nullable' => False,'comment' => 'auto-id'),
			'share_token' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'secure token'),
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
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.1.900';
}

/**
 * Bump version to 14.2
 *
 * @return string
 */
function phpgwapi_upgrade14_1_900()
{
	// Create anonymous user for sharing of files
	$GLOBALS['egw_setup']->add_account('NoGroup', 'No', 'Rights', false, false);
	$anonymous = $GLOBALS['egw_setup']->add_account('anonymous', 'SiteMgr', 'User', 'anonymous', 'NoGroup');
	$GLOBALS['egw_setup']->add_acl('phpgwapi', 'anonymous', $anonymous);

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2';
}
function phpgwapi_upgrade14_2()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_accounts','account_description',array(
		'type' => 'varchar',
		'precision' => '255',
		'comment' => 'group description'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.001';
}

function phpgwapi_upgrade15_0_001()
{
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.001';
}

function phpgwapi_upgrade14_2_001()
{
	$GLOBALS['run-from-upgrade14_2_001'] = true;	// flag no need to run 14.2.025 update

	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_config','config_app',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_config','config_name',array(
		'type' => 'ascii',
		'precision' => '32',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.002';
}


function phpgwapi_upgrade14_2_002()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_name',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_tables',array(
		'type' => 'varchar',
		'precision' => '8192',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.003';
}


function phpgwapi_upgrade14_2_003()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_tables',array(
		'type' => 'ascii',
		'precision' => '8192',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_version',array(
		'type' => 'ascii',
		'precision' => '20',
		'nullable' => False,
		'default' => '0.0'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_icon',array(
		'type' => 'ascii',
		'precision' => '32'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_icon_app',array(
		'type' => 'ascii',
		'precision' => '16'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_applications','app_index',array(
		'type' => 'ascii',
		'precision' => '64'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.004';
}


function phpgwapi_upgrade14_2_004()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_acl','acl_appname',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_acl','acl_location',array(
		'type' => 'ascii',
		'meta' => 'account',
		'precision' => '16',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_acl','acl_id',array(
		'type' => 'auto',
		'nullable' => False
	));*/

	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_acl',array(
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
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.005';
}


function phpgwapi_upgrade14_2_005()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_accounts','account_lastloginfrom',array(
		'type' => 'ascii',
		'precision' => '48',
		'comment' => 'ip'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.006';
}


function phpgwapi_upgrade14_2_006()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_preferences','preference_app',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_preferences','preference_id',array(
		'type' => 'auto',
		'nullable' => False
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_preferences',array(
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
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.007';
}


function phpgwapi_upgrade14_2_007()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_access_log','ip',array(
		'type' => 'ascii',
		'precision' => '48',
		'nullable' => False,
		'comment' => 'ip of user'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_access_log','session_action',array(
		'type' => 'ascii',
		'precision' => '64',
		'comment' => 'menuaction or path of last user action'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_access_log','session_php',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False,
		'comment' => 'php session-id or error-message'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_access_log','user_agent',array(
		'type' => 'ascii',
		'precision' => '255',
		'comment' => 'User-agent of browser/device'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.008';
}


function phpgwapi_upgrade14_2_008()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_hooks','hook_appname',array(
		'type' => 'ascii',
		'precision' => '16'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_hooks','hook_location',array(
		'type' => 'ascii',
		'precision' => '32'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_hooks','hook_filename',array(
		'type' => 'ascii',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.009';
}


function phpgwapi_upgrade14_2_009()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_languages','lang_id',array(
		'type' => 'ascii',
		'precision' => '5',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.010';
}


function phpgwapi_upgrade14_2_010()
{
	// delete since 11.x no longer used messages
	$GLOBALS['egw_setup']->oProc->query("DELETE FROM egw_lang WHERE app_name NOT IN ('loginscreen','mainscreen','custom')", __LINE__, __FILE__);

	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_lang','lang',array(
		'type' => 'ascii',
		'precision' => '5',
		'nullable' => False,
		'default' => ''
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_lang','app_name',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False,
		'default' => 'common'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_lang','message_id',array(
		'type' => 'ascii',
		'precision' => '128',
		'nullable' => False,
		'default' => ''
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_lang','content',array(
		'type' => 'varchar',
		'precision' => '8192'
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_lang',array(
		'fd' => array(
			'lang' => array('type' => 'ascii','precision' => '5','nullable' => False,'default' => ''),
			'app_name' => array('type' => 'ascii','precision' => '16','nullable' => False,'default' => 'common'),
			'message_id' => array('type' => 'ascii','precision' => '128','nullable' => False,'default' => ''),
			'content' => array('type' => 'varchar','precision' => '8192'),
			'lang_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('lang_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array(array('lang','app_name','message_id'))
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.011';
}


function phpgwapi_upgrade14_2_011()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_nextid','appname',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.012';
}


function phpgwapi_upgrade14_2_012()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_categories','cat_owner',array(
		'type' => 'ascii',
		'meta' => 'account-commasep',
		'precision' => '255',
		'nullable' => False,
		'default' => '0'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_categories','cat_access',array(
		'type' => 'ascii',
		'precision' => '7'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_categories','cat_appname',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_categories','cat_data',array(
		'type' => 'varchar',
		'precision' => '8192'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.013';
}


function phpgwapi_upgrade14_2_013()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_history_log','history_appname',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_history_log','history_status',array(
		'type' => 'varchar',
		'precision' => '32',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.014';
}


function phpgwapi_upgrade14_2_014()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_async','async_id',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_async','async_times',array(
		'type' => 'ascii',
		'precision' => '255',
		'nullable' => False,
		'comment' => 'serialized array with values for keys hour,min,day,month,year'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_async','async_method',array(
		'type' => 'ascii',
		'precision' => '80',
		'nullable' => False,
		'comment' => 'app.class.method class::method to execute'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_async','async_data',array(
		'type' => 'ascii',
		'precision' => '8192',
		'nullable' => False,
		'comment' => 'serialized array with data to pass to method'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_async','async_auto_id',array(
		'type' => 'auto',
		'nullable' => False
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_async',array(
		'fd' => array(
			'async_id' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'async_next' => array('type' => 'int','meta' => 'timestamp','precision' => '4','nullable' => False,'comment' => 'timestamp of next run'),
			'async_times' => array('type' => 'ascii','precision' => '255','nullable' => False,'comment' => 'serialized array with values for keys hour,min,day,month,year'),
			'async_method' => array('type' => 'ascii','precision' => '80','nullable' => False,'comment' => 'app.class.method class::method to execute'),
			'async_data' => array('type' => 'ascii','precision' => '8192','nullable' => False,'comment' => 'serialized array with data to pass to method'),
			'async_account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'default' => '0','comment' => 'creator of job'),
			'async_auto_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('async_auto_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('async_id')
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.015';
}


function phpgwapi_upgrade14_2_015()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_api_content_history','sync_appname',array(
		'type' => 'ascii',
		'precision' => '32',
		'nullable' => False,
		'comment' => 'not just app-names!'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_api_content_history','sync_contentid',array(
		'type' => 'ascii',
		'precision' => '48',
		'nullable' => False,
		'comment' => 'eworkflow uses 36-char uuids'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.016';
}


function phpgwapi_upgrade14_2_016()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_links','link_app1',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_links','link_id1',array(
		'type' => 'ascii',
		'meta' => array(
			"link_app1='home-accounts'" => 'account'
		),
		'precision' => '64',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_links','link_app2',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_links','link_id2',array(
		'type' => 'ascii',
		'meta' => array(
			"link_app2='home-accounts'" => 'account'
		),
		'precision' => '64',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.017';
}


function phpgwapi_upgrade14_2_017()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','cat_id',array(
		'type' => 'ascii',
		'meta' => 'category',
		'precision' => '255',
		'comment' => 'Category(s)'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_freebusy_uri',array(
		'type' => 'ascii',
		'precision' => '128',
		'comment' => 'freebusy-url for calendar of the contact'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_calendar_uri',array(
		'type' => 'ascii',
		'precision' => '128',
		'comment' => 'url for users calendar - currently not used'
	));
	// only shorten note to varchar(8194), if it does NOT contain longer input and it can be stored as varchar
	$max_note_length = $GLOBALS['egw']->db->query('SELECT MAX(CHAR_LENGTH(contact_note)) FROM egw_addressbook')->fetchColumn();
	// returns NULL, if there are no rows!
	if ((int)$max_note_length <= 8192 && $GLOBALS['egw_setup']->oProc->max_varchar_length >= 8192)
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_note',array(
			'type' => 'varchar',
			'precision' => '8192',
			'comment' => 'notes field'
		));
	}
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_geo',array(
		'type' => 'ascii',
		'precision' => '32',
		'comment' => 'currently not used'
	));
	// only shorten pubkey to varchar(16384), if it does NOT contain longer input and it can be stored as varchar
	$max_pubkey_length = $GLOBALS['egw']->db->query('SELECT MAX(LENGTH(contact_pubkey)) FROM egw_addressbook')->fetchColumn();
	// returns NULL, if there are no rows!
	if ((int)$max_pubkey_length <= 16384 && $GLOBALS['egw_setup']->oProc->max_varchar_length >= 16384)
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_pubkey',array(
			'type' => 'ascii',
			'precision' => '16384',
			'comment' => 'public key'
		));
	}
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_uid',array(
		'type' => 'ascii',
		'precision' => '128',
		'comment' => 'unique id of the contact'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','adr_one_countrycode',array(
		'type' => 'ascii',
		'precision' => '2',
		'comment' => 'countrycode (business)'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','adr_two_countrycode',array(
		'type' => 'ascii',
		'precision' => '2',
		'comment' => 'countrycode (private)'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','carddav_name',array(
		'type' => 'ascii',
		'precision' => '128',
		'comment' => 'name part of CardDAV URL, if specified by client'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.018';
}


function phpgwapi_upgrade14_2_018()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook_extra','contact_name',array(
		'type' => 'varchar',
		'meta' => 'cfname',
		'precision' => '64',
		'nullable' => False,
		'comment' => 'custom-field name'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook_extra','contact_value',array(
		'type' => 'varchar',
		'meta' => 'cfvalue',
		'precision' => '16384',
		'comment' => 'custom-field value'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_addressbook_extra','contact_extra_id',array(
		'type' => 'auto',
		'nullable' => False
	));*/
	// only shorten cf value to varchar(8194), if it does NOT contain longer input and it can be stored as varchar
	$max_value_length = $GLOBALS['egw']->db->query('SELECT MAX(CHAR_LENGTH(contact_value)) FROM egw_addressbook_extra')->fetchColumn();
	// returns NULL, if there are no rows!
	$new_value_length = 16384;
	if ((int)$max_value_length > $new_value_length && $GLOBALS['egw_setup']->oProc->max_varchar_length >= $new_value_length)
	{
		$new_value_length = $max_value_length;
	}
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_addressbook_extra',array(
		'fd' => array(
			'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'contact_owner' => array('type' => 'int','meta' => 'account','precision' => '8'),
			'contact_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '64','nullable' => False,'comment' => 'custom-field name'),
			'contact_value' => array('type' => 'varchar','meta' => 'cfvalue','precision' => $new_value_length,'comment' => 'custom-field value'),
			'contact_extra_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('contact_extra_id'),
		'fk' => array(),
		'ix' => array('contact_name',array('contact_id','contact_name')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.019';
}


function phpgwapi_upgrade14_2_019()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook_lists','list_uid',array(
		'type' => 'ascii',
		'precision' => '128'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook_lists','list_carddav_name',array(
		'type' => 'ascii',
		'precision' => '128'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.020';
}


function phpgwapi_upgrade14_2_020()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs','fs_mime',array(
		'type' => 'ascii',
		'precision' => '96',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.021';
}


function phpgwapi_upgrade14_2_021()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_locks','lock_token',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_locks','lock_id',array(
		'type' => 'auto',
		'nullable' => False
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_locks',array(
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
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.022';
}


function phpgwapi_upgrade14_2_022()
{
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs_props','prop_namespace',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs_props','prop_name',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs_props','prop_value',array(
		'type' => 'ascii',
		'precision' => '16384'
	));*/
	/* done by RefreshTable() anyway
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_sqlfs_props','prop_id',array(
		'type' => 'auto',
		'nullable' => False
	));*/
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_sqlfs_props',array(
		'fd' => array(
			'fs_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'prop_namespace' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'prop_name' => array('type' => 'ascii','precision' => '64','nullable' => False),
			'prop_value' => array('type' => 'ascii','precision' => '16384'),
			'prop_id' => array('type' => 'auto','nullable' => False)
		),
		'pk' => array('prop_id'),
		'fk' => array(),
		'ix' => array(array('fs_id','prop_namespace','prop_name')),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.023';
}


function phpgwapi_upgrade14_2_023()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_customfields','cf_app',array(
		'type' => 'ascii',
		'precision' => '16',
		'nullable' => False,
		'comment' => 'app-name cf belongs too'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_customfields','cf_private',array(
		'type' => 'ascii',
		'meta' => 'account-commasep',
		'precision' => '2048',
		'comment' => 'comma-separated account_id'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.024';
}


function phpgwapi_upgrade14_2_024()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sharing','share_token',array(
		'type' => 'ascii',
		'precision' => '64',
		'nullable' => False,
		'comment' => 'secure token'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] =
		empty($GLOBALS['run-from-upgrade14_2_001']) ? '14.2.026' : '14.2.025';
}


/**
 * Fix wrongly converted columns back to utf-8 and change message_id to ascii
 *
 * @return string
 */
function phpgwapi_upgrade14_2_025()
{
	// cat_data contains arbitrary user input eg. in tracker canned responses
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_categories','cat_data',array(
		'type' => 'varchar',
		'precision' => '8192'
	));
	// note is utf-8
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_note',array(
		'type' => 'varchar',
		'precision' => '8192',
		'comment' => 'notes field'
	));
	// message_id is in english and in an index --> ascii
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_lang','message_id',array(
		'type' => 'ascii',
		'precision' => '128',
		'nullable' => False,
		'default' => ''
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.2.026';
}

/**
 * Shorten index on egw_sqlfs.fs_name to improve performance
 *
 * @return string
 */
function phpgwapi_upgrade14_2_026()
{
	$GLOBALS['egw_setup']->oProc->DropIndex('egw_sqlfs', array('fs_dir','fs_active','fs_name'));
	$GLOBALS['egw_setup']->oProc->CreateIndex('egw_sqlfs', array('fs_dir','fs_active','fs_name(16)'));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3';
}

/**
 * Change history_status back to varchar, as it contains custom-field names, which can be non-ascii
 *
 * @return string
 */
function phpgwapi_upgrade14_3()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_history_log','history_status',array(
		'type' => 'varchar',
		'precision' => '32',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.001';
}

/**
 * Change egw_sqlfs_props.prop_value back to varchar, as it contains user-data eg. comment, which can be non-ascii
 *
 * @return string
 */
function phpgwapi_upgrade14_3_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sqlfs_props','prop_value',array(
		'type' => 'varchar',
		'precision' => '16384'
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.002';
}

/**
 * Fix old php-serialized favorites to use new format with name, group and state attributes
 * Change still php-serialized values in column egw_preferences.preference_value to json-encoding
 *
 * @return string
 */
function phpgwapi_upgrade14_3_002()
{
	$GLOBALS['run-from-upgrade14_3_002'] = true;

	preferences::change_preference(null, '/^favorite_/', function($name, $value, $owner)
	{
		if (is_string($value) && $value[0] == 'a' && $value[1] == ':' && ($state = php_safe_unserialize($value)))
		{
			$value = array(
				'name'  => substr($name, 9),	// skip "favorite_"
				'group' => !($owner > 0),
				'state' => $state,
			);
		}
		return $value;
	});

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.003';
}

/**
 * Check and if necessary fix indexes, they have been completly lost on PostgreSQL with previous updates
 *
 * @return string
 */
function phpgwapi_upgrade14_3_003()
{
	$GLOBALS['run-from-upgrade14_3_003'] = true;

	if ($GLOBALS['egw_setup']->db->Type == 'pgsql')
	{
		$GLOBALS['egw_setup']->oProc->CheckCreateIndexes();
	}
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.004';
}

/**
 * Updates on the way to 15.1
 */

/**
 * Drop egw_api_content_history table used by no longer supported SyncML
 *
 * @return string
 */
function phpgwapi_upgrade14_3_004()
{
	$GLOBALS['egw_setup']->oProc->DropTable('egw_api_content_history');

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.900';
}

/**
 * Run 14.3.002 upgrade for everyone who was already on 14.3.900
 */
function phpgwapi_upgrade14_3_900()
{
	if (empty($GLOBALS['run-from-upgrade14_3_002']))
	{
		phpgwapi_upgrade14_3_002();
	}
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.901';
}

/**
 * Run 14.3.003 upgrade for everyone who was already on 14.3.900
 */
function phpgwapi_upgrade14_3_901()
{
	if (empty($GLOBALS['run-from-upgrade14_3_003']))
	{
		phpgwapi_upgrade14_3_003();
	}
	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.902';
}

/**
 * Change egw_addressbook.contact_pubkey to 16k as an ascii-armored 4096 bit PGP key is ~12k
 *
 * @return type
 */
function phpgwapi_upgrade14_3_902()
{
	// only shorten pubkey to varchar(16384), if it does NOT contain longer input and it can be stored as varchar
	$max_pubkey_length = $GLOBALS['egw']->db->query('SELECT MAX(LENGTH(contact_pubkey)) FROM egw_addressbook')->fetchColumn();
	// returns NULL, if there are no rows!
	if ((int)$max_pubkey_length <= 16384 && $GLOBALS['egw_setup']->oProc->max_varchar_length >= 16384)
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_addressbook','contact_pubkey',array(
			'type' => 'ascii',
			'precision' => '16384',
			'comment' => 'public key'
		));
	}

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.3.903';
}
