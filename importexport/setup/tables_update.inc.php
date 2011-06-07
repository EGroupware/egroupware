<?php
/**
 * EGroupware - Setup
 *
 * @link http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @subpackage setup
 * @version $Id: class.db_tools.inc.php 21408 2006-04-21 10:31:06Z nelius_weiss $
 */

function importexport_upgrade0_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_importexport_definitions','description',array(
		'type' => 'varchar',
		'precision' => '255'
	));

	return $GLOBALS['setup_info']['importexport']['currentver'] = '0.003';
}


function importexport_upgrade0_003()
{
	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.4';
}


function importexport_upgrade1_4()
{
	$sql = 'UPDATE egw_importexport_definitions SET plugin = CONCAT(application, "_", plugin)';

	$GLOBALS['egw_setup']->db->query($sql, __LINE__, __FILE__);
	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.7.001';
}


function importexport_upgrade1_7_001()
{
	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.8';
}

function importexport_upgrade1_8()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_importexport_definitions','definition_id',array(
		'type' => 'auto',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_importexport_definitions','modified',array(
		'type' => 'timestamp'
	));

	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.9.001';
}

function importexport_upgrade1_9_001()
{
	// Not needed - did it wrong
	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.9.002';
}

function importexport_upgrade1_9_002()
{
	$sql = 'UPDATE egw_importexport_definitions SET allowed_users = '.
		$GLOBALS['egw_setup']->db->concat("','", 'allowed_users', "','");
	$GLOBALS['egw_setup']->oProc->query($sql, __LINE__, __FILE__);

	// give Default and Admins group rights for ImportExport
	foreach(array('Default' => 'Default','Admins' => 'Admin') as $account_lid => $name)
	{
		$account_id = $GLOBALS['egw_setup']->add_account($account_lid,$name,'Group',False,False);
		$GLOBALS['egw_setup']->add_acl('importexport','run',$account_id);
	}

	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.9.003';
}
