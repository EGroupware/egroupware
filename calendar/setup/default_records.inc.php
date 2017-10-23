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

// Start with at a month, gets updated later
// Without this, recurrences fail until it's set
EGroupware\Api\Config::save_value('horizont', time()+31*24*60*60,'calendar');

// import timezone data
calendar_timezones::import_zones();
calendar_timezones::import_tz_aliases();
