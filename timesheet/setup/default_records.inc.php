<?php
/**
 * TimeSheet - Default records
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package timesheet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

foreach(array(
	'history'     => 'history',
) as $name => $value)
{
	$GLOBALS['egw_setup']->db->insert(
		$GLOBALS['egw_setup']->config_table,
		array(
			'config_app' => 'timesheet',
			'config_name' => $name,
			'config_value' => $value,
		),array(
			'config_app' => 'timesheet',
			'config_name' => $name,
		),__LINE__,__FILE__
	);
}

