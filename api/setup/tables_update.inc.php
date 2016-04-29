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

function api_upgrade16_1()
{
        $GLOBALS['egw_setup']->oProc->AddColumn('egw_ea_accounts','acc_folder_archive', array(
		'type' => 'varchar',
		'precision' => '128',
		'comment' => 'archive folder'
	));

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1.001';
}
