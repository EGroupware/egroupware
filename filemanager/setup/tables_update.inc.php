<?php
/**
 * EGroupware - Filemanager - setup
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * Some dummy updates to ease update from before 16.1
 */
function filemanager_upgrade1_6()
{
	return $GLOBALS['setup_info']['filemanager']['currentver'] = '16.1';
}
function filemanager_upgrade1_8()
{
	return $GLOBALS['setup_info']['filemanager']['currentver'] = '16.1';
}
function filemanager_upgrade1_9_001()
{
	return $GLOBALS['setup_info']['filemanager']['currentver'] = '16.1';
}
function filemanager_upgrade14_1()
{
	return $GLOBALS['setup_info']['filemanager']['currentver'] = '16.1';
}

/**
 * Creating colaboration tables
 *
 * @return string
 */
function filemanager_upgrade16_1()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_collab_member', array(
		'fd' => array(
			'collab_member_id' => array('type' => 'auto','nullable' => False, 'comment' => 'Unique per user and session'),
			'collab_es_id' => array('type' => 'varchar','precision' => '64','nullable' => False, 'comment' => 'Related editing session id'),
			'collab_uid' => array('type' => 'varchar','precision' => '64'),
			'collab_color' => array('type' => 'varchar','precision' => '32'),
			'collab_is_active' => array('type' => 'int','precision' => '2', 'default'=>'0','nullable' => False),
			'collab_is_guest' => array('type' => 'int','precision' => '2','default' => '0','nullable' => False),
			'collab_token' => array('type' => 'varchar','precision' => '32'),
			'collab_status' => array('type' => 'int','precision' => '2','default' => '1','nullable' => False)
		),
		'pk' => array('collab_member_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	$GLOBALS['egw_setup']->oProc->CreateTable('egw_collab_op', array(
		'fd' => array(
			'collab_seq' => array('type' => 'auto','nullable' => False, 'comment' => 'Sequence number'),
			'collab_es_id' => array('type' => 'varchar','precision' => '64','nullable' => False, 'comment' => 'Editing session id'),
			'collab_member' => array('type' => 'int','precision' => '4','default' => '1','nullable' => False, 'comment' => 'User and time specific'),
			'collab_optype' => array('type' => 'varchar','precision' => '64', 'comment' => 'Operation type'),
			'collab_opspec' => array('type' => 'longtext', 'comment' => 'json-string')
		),
		'pk' => array('collab_seq'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	$GLOBALS['egw_setup']->oProc->CreateTable('egw_collab_session', array(
		'fd' => array(
			'collab_es_id' => array('type' => 'varchar','precision' => '64','nullable' => False, 'comment' => 'Editing session id'),
			'collab_genesis_url' => array('type' => 'varchar','precision' => '512', 'comment' => 'Relative to owner documents storage /template.odt'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False, 'comment' => 'user who created the session'),
			'collab_last_save' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'timestamp of the last save')
		),
		'pk' => array('collab_es_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['filemanager']['currentver'] = '16.2';
}

function filemanager_upgrade16_2()
{
	return $GLOBALS['setup_info']['filemanager']['currentver'] = '17.1';
}
