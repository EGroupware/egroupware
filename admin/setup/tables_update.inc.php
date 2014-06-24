<?php
/**
 * EGroupware - Setup
 *
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @subpackage setup
 * @version $Id$
 */

function admin_upgrade1_2()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.4';
}


function admin_upgrade1_4()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_admin_queue',array(
		'fd' => array(
			'cmd_id' => array('type' => 'auto'),
			'cmd_uid' => array('type' => 'varchar','precision' => '255','nullable' => False),
			'cmd_creator' => array('type' => 'int','precision' => '4','nullable' => False),
			'cmd_creator_email' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'cmd_created' => array('type' => 'int','precision' => '8','nullable' => False),
			'cmd_type' => array('type' => 'varchar','precision' => '32','nullable' => False,'default' => 'admin_cmd'),
			'cmd_status' => array('type' => 'int','precision' => '1'),
			'cmd_scheduled' => array('type' => 'int','precision' => '8'),
			'cmd_modified' => array('type' => 'int','precision' => '8'),
			'cmd_modifier' => array('type' => 'int','precision' => '4'),
			'cmd_modifier_email' => array('type' => 'varchar','precision' => '128'),
			'cmd_error' => array('type' => 'varchar','precision' => '255'),
			'cmd_errno' => array('type' => 'int','precision' => '4'),
			'cmd_requested' => array('type' => 'int','precision' => '4'),
			'cmd_requested_email' => array('type' => 'varchar','precision' => '128'),
			'cmd_comment' => array('type' => 'varchar','precision' => '255'),
			'cmd_data' => array('type' => 'blob')
		),
		'pk' => array('cmd_id'),
		'fk' => array(),
		'ix' => array('cmd_status','cmd_scheduled'),
		'uc' => array('cmd_uid')
	));
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.5.001';
}


function admin_upgrade1_5_001()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_admin_remote',array(
		'fd' => array(
			'remote_id' => array('type' => 'auto'),
			'remote_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
			'remote_hash' => array('type' => 'varchar','precision' => '32','nullable' => False),
			'remote_url' => array('type' => 'varchar','precision' => '128','nullable' => False),
			'remote_domain' => array('type' => 'varchar','precision' => '64','nullable' => False)
		),
		'pk' => array('remote_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('remote_name')
	));

	return $GLOBALS['setup_info']['admin']['currentver'] = '1.5.002';
}


function admin_upgrade1_5_002()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_admin_queue','remote_id',array(
		'type' => 'int',
		'precision' => '4'
	));

	return $GLOBALS['setup_info']['admin']['currentver'] = '1.5.003';
}


function admin_upgrade1_5_003()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.6';
}


function admin_upgrade1_6()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.8';
}


/**
 * Change index page via setup.inc.php
 *
 * @return string
 */
function admin_upgrade1_8()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '1.9.001';
}


function admin_upgrade1_9_001()
{
	return $GLOBALS['setup_info']['admin']['currentver'] = '14.1';
}
