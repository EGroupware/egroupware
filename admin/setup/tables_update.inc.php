<?php
/**
 * eGroupWare - Setup
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
