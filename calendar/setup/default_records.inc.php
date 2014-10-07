<?php
/**
 * eGroupWare - Calendar setup
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// enable auto-loading of holidays from localhost by default
foreach(array(
	'auto_load_holidays' => 'True',
	'holidays_url_path'  => 'localhost',
) as $name => $value)
{
	$oProc->insert($GLOBALS['egw_setup']->config_table,array(
		'config_value' => $value,
	),array(
		'config_app' => 'phpgwapi',
		'config_name' => $name,
	),__FILE__,__LINE__);
}

// import timezone data from sqlite database
try
{
	calendar_timezones::import_sqlite();
	calendar_timezones::import_tz_aliases();
}
// catch missing or broken sqlite support and use timezones.db_backup to install timezones
catch (egw_exception_wrong_userinput $e)	// all other exceptions are fatal
{
	$db_backup = new db_backup();
	$db_backup->restore($db_backup->fopen_backup(EGW_SERVER_ROOT.'/calendar/setup/timezones.db_backup', true), true, '', false);
}