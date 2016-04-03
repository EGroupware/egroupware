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

	return $GLOBALS['setup_info']['api']['currentver'] = '16.1';
}