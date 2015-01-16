<?php
/**
 * EGroupware - Home - Setup
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package home
 * @subpackage setup
 * @copyright (c) 2014 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function home_upgrade14_1()
{
	// Previously all portlets were together in a sub-array, for defaults they
	// need to be moved to top level
	$preferences = array();
	foreach($GLOBALS['egw_setup']->db->select('egw_preferences','preference_owner,preference_app,preference_value',array(
		'preference_app' => 'home',
	),__LINE__,__FILE__) as $row)
	{
		$preferences[] = $row;
	}
	foreach($preferences as $row)
	{
		// The following replacement is required for PostgreSQL to work
		$app = trim($row['preference_app']);

		// Move portlets into top level, not a sub-array
		if ($row['preference_value'][0] != 'a' && $row['preference_value'][1] != ':')
		{
			$values = json_decode($row['preference_value'], true);
		}
		else
		{
			// Too old, skip it
			continue;
		}
		if($values['portlets'] && is_array($values['portlets']))
		{
			foreach($values['portlets'] as $id => $settings)
			{
				$values["portlet_$id"] = $settings;
			}
			unset($values['portlets']);
		}
		$GLOBALS['egw_setup']->db->insert(
			'egw_preferences',array(
				'preference_value' => json_encode($values),
			),array(
				'preference_owner' => $row['preference_owner'],
				'preference_app'   => $app,
			),__LINE__,__FILE__
		);
	}

	return $GLOBALS['setup_info']['home']['currentver'] = '14.1.001';
}

