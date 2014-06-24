<?php
/**
 * eTemplates - table update scripts
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package etemplate
 * @subpackage setup
 * @copyright (c) 200s-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function etemplate_upgrade0_9_13_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_data',array(
		'type' => 'text',
		'nullable' => True
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_size',array(
		'type' => 'char',
		'precision' => '128',
		'nullable' => True
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_style',array(
		'type' => 'text',
		'nullable' => True
	));
	$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_etemplate','et_modified',array(
		'type' => 'int',
		'precision' => '4',
		'default' => '0',
		'nullable' => False
	));

	$GLOBALS['setup_info']['etemplate']['currentver'] = '0.9.15.001';
	return $GLOBALS['setup_info']['etemplate']['currentver'];
}


function etemplate_upgrade0_9_15_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_name',array(
		'type' => 'varchar',
		'precision' => '80',
		'nullable' => False
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_template',array(
		'type' => 'varchar',
		'precision' => '20',
		'nullable' => False,
		'default' => ''
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_lang',array(
		'type' => 'varchar',
		'precision' => '5',
		'nullable' => False,
		'default' => ''
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_version',array(
		'type' => 'varchar',
		'precision' => '20',
		'nullable' => False,
		'default' => ''
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_etemplate','et_size',array(
		'type' => 'varchar',
		'precision' => '128',
		'nullable' => True
	));

	$GLOBALS['setup_info']['etemplate']['currentver'] = '0.9.15.002';
	return $GLOBALS['setup_info']['etemplate']['currentver'];
}


function etemplate_upgrade0_9_15_002()
{
	$GLOBALS['setup_info']['etemplate']['currentver'] = '1.0.0';
	return $GLOBALS['setup_info']['etemplate']['currentver'];
}


function etemplate_upgrade1_0_0()
{
	$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_etemplate','egw_etemplate');

	$GLOBALS['setup_info']['etemplate']['currentver'] = '1.2';
	return $GLOBALS['setup_info']['etemplate']['currentver'];
}


function etemplate_upgrade1_2()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_etemplate','et_modified',array(
		'type' => 'int',
		'precision' => '8',
		'nullable' => False,
		'default' => '0'
	));

	return $GLOBALS['setup_info']['etemplate']['currentver'] = '1.4';
}


function etemplate_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_etemplate','et_data',array(
		'type' => 'longtext',
		'nullable' => True,
	));

	return $GLOBALS['setup_info']['etemplate']['currentver'] = '1.5.001';
}


function etemplate_upgrade1_5_001()
{
	return $GLOBALS['setup_info']['etemplate']['currentver'] = '1.6';
}


function etemplate_upgrade1_6()
{
	return $GLOBALS['setup_info']['etemplate']['currentver'] = '1.8';
}


function etemplate_upgrade1_8()
{
	return $GLOBALS['setup_info']['etemplate']['currentver'] = '14.1';
}
