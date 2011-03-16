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
	$egwdir = dir(EGW_INCLUDE_ROOT);
	while (false !== ($appdir = $egwdir->read())) {
		$defdir = EGW_INCLUDE_ROOT. "/$appdir/setup/";
		if ( !is_dir( $defdir ) ) continue;

		// Set as default definition for the app, if there is no site default yet
		if(!$GLOBALS['egw']->preferences->default[$appdir]['nextmatch-export-definition']) {
			$bo = new importexport_definitions_bo(array('name' => "export-$appdir*"));
			$definitions = $bo->get_definitions();
			if($definitions[0]) {
				$definition = $bo->read($definitions[0]);
				$GLOBALS['egw']->preferences->add($appdir, 'nextmatch-export-definition', $definition['name'], 'default');
			}
		}
	}
	$GLOBALS['egw']->preferences->save_repository(true, 'default');
	return $GLOBALS['setup_info']['importexport']['currentver'] = '1.9.002';
}
