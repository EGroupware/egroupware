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
 * @version $Id$
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

	// import i/e defintions
	if (extension_loaded('dom'))
	{
		require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.importexport_definitions_bo.inc.php');

		// This sets up $GLOBALS['egw']->accounts and $GLOBALS['egw']->db
		$GLOBALS['egw_setup']->setup_account_object();

		// step through every source code intstalled app
		$egwdir = dir(EGW_INCLUDE_ROOT);
		while (false !== ($appdir = $egwdir->read())) {
			$defdir = EGW_INCLUDE_ROOT. "/$appdir/setup/";
			if ( !is_dir( $defdir ) ) continue;

			// step through each file in defdir of app
			$d = dir($defdir);
			while (false !== ($entry = $d->read())) {
				$file = $defdir. '/'. $entry;
				list( $filename, $extension) = explode('.',$entry);
				if ( $extension != 'xml' ) continue;
				importexport_definitions_bo::import( $file );
			}
		}
	}
	// give Default and Admins group rights for ImportExport
	foreach(array('Default' => 'Default','Admins' => 'Admin') as $account_lid => $name)
	{
		$account_id = $GLOBALS['egw_setup']->add_account($account_lid,$name,'Group',False,False);
		$GLOBALS['egw_setup']->add_acl('importexport','run',$account_id);
	}

	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.9.003';
}
