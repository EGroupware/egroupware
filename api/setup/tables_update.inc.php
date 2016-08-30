<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts from 16.1 onwards
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Remove rests of EMailAdmin or install 14.1 tables for update from before 14.1
 *
 * 14.3.907 is the version set by setup, if api is not installed in 16.1 upgrade
 *
 * @return string
 */
function api_upgrade14_3_907()
{
	// check if EMailAdmin tables are there and create them if not
	$tables = $GLOBALS['egw_setup']->db->table_names(true);
	$phpgw_baseline = array();
	include (__DIR__.'/tables_current.inc.php');
	foreach($phpgw_baseline as $table => $definition)
	{
		if (!in_array($table, $tables))
		{
			$GLOBALS['egw_setup']->oProc->CreateTable($table, $definition);
		}
	}

	// uninstall no longer existing EMailAdmin
	if (in_array('egw_emailadmin', $tables))
	{
		$GLOBALS['egw_setup']->oProc->DropTable('egw_emailadmin');
	}
	$GLOBALS['egw_setup']->deregister_app('emailadmin');

	// uninstall obsolete FelamiMail tables, if still around
	$done = 0;
	foreach(array_intersect($tables, array('egw_felamimail_accounts', 'egw_felamimail_displayfilter', 'egw_felamimail_signatures')) as $table)
	{
		$GLOBALS['egw_setup']->oProc->DropTable($table);

		if (!$done++) $GLOBALS['egw_setup']->deregister_app('felamimail');
	}

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1';
}

/**
 * Add archive folder to mail accounts
 *
 * @return string
 */
function api_upgrade16_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_folder_archive', array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'archive folder'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.001';
}

/**
 * Fix home-accounts in egw_customfields and egw_links to api-accounts
 *
 * @return string
 */
function api_upgrade16_1_001()
{
	foreach(array(
		'cf_type' => 'egw_customfields',
		'link_app1' => 'egw_links',
		'link_app2' => 'egw_links',
	) as $col => $table)
	{
		$GLOBALS['egw_setup']->db->query("UPDATE $table SET $col='api-accounts' WHERE $col='home-accounts'", __LINE__, __FILE__);
	}
	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.002';
}

use EGroupware\Api\Vfs;

/**
 * Create /templates and subdirectories, if they dont exist
 *
 * They are create as part of the installation for new installations and allways exist in EPL.
 * If they dont exist, you can not save the preferences of the concerned applications, unless
 * you either manually create the directory or remove the path from the default preferences.
 *
 * @return string
 */
function api_upgrade16_1_002()
{
	$admins = $GLOBALS['egw_setup']->add_account('Admins','Admin','Group',False,False);

	Vfs::$is_root = true;
	foreach(array('','addressbook', 'calendar', 'infolog', 'tracker', 'timesheet', 'projectmanager', 'filemanager') as $app)
	{
		if ($app && !file_exists(EGW_SERVER_ROOT.'/'.$app)) continue;

		// create directory and set permissions: Admins writable and other readable
		$dir = '/templates'.($app ? '/'.$app : '');
		if (Vfs::file_exists($dir)) continue;

		Vfs::mkdir($dir, 075, STREAM_MKDIR_RECURSIVE);
		Vfs::chgrp($dir, abs($admins));
		Vfs::chmod($dir, 075);
	}
	Vfs::$is_root = false;

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.003';
}
