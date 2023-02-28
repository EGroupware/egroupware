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
 */

function home_upgrade1_0_0()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '14.1';
}

function home_upgrade1_2()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '14.1';
}

function home_upgrade1_4()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '14.1';
}

function home_upgrade1_8()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '14.1';
}

function home_upgrade1_9()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '14.1';
}

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

/**
 * Update to 16.1: rename "home-accounts" to "api-accounts" and give everyone updating home run-rights
 *
 * @return string
 */
function home_upgrade14_1_001()
{
	// rename "home-accounts" to "api-accounts"
	foreach(array('link_app1', 'link_app2') as $col)
	{
		$GLOBALS['egw_setup']->db->update('egw_links', array(
				$col => 'api-accounts',
			),
			array(
				$col => 'home-accounts',
			), __LINE__, __FILE__);
	}

	// give Default group run-rights for home, as it is no longer implicit
	$GLOBALS['egw_setup']->setup_account_object();
	if (($defaultgroup = $GLOBALS['egw_setup']->accounts->name2id('Default', 'account_lid', 'g')))
	{
		$GLOBALS['egw_setup']->add_acl('home', 'run', $defaultgroup);
	}

	return $GLOBALS['setup_info']['home']['currentver'] = '16.1';
}

function home_upgrade16_1()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '17.1';
}

/**
 * Bump version to 19.1
 *
 * @return string
 */
function home_upgrade17_1()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '19.1';
}

/**
 * Bump version to 20.1
 *
 * @return string
 */
function home_upgrade19_1()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '20.1';
}

/**
 * Bump version to 21.1
 *
 * @return string
 */
function home_upgrade20_1()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '21.1';
}

/**
 * Bump version to 23.1
 *
 * @return string
 */
function home_upgrade21_1()
{
	return $GLOBALS['setup_info']['home']['currentver'] = '23.1';
}