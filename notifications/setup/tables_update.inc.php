<?php
/**
 * EGroupware - Setup
 * http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage setup
 */

function notifications_upgrade0_5()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_notificationpopup','account_id',array(
		'type' => 'int',
		'precision' => '20',
		'nullable' => False
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '0.6';
}


function notifications_upgrade0_6()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.4';
}


function notifications_upgrade1_4()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.6';
}


function notifications_upgrade1_6()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.8';
}

function notifications_upgrade1_8()
{
	$GLOBALS['egw_setup']->oProc->DropColumn('egw_notificationpopup',array(
		'fd' => array(
			'account_id' => array('type' => 'int','precision' => '20','nullable' => False),
			'message' => array('type' => 'longtext')
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array('account_id'),
		'uc' => array()
	),'session_id');

	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.9.001';
}

/**
 * Empty notificaton table, as it can contain thousands of old entries, not delivered before
 */
function notifications_upgrade1_9_001()
{
	// empty notificationpopup table, as it can contain thousands of old entries, not delivered before
	$GLOBALS['egw_setup']->db->query('DELETE FROM egw_notificationpopup');

	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.9.002';
}

/**
 * Add primary key to easy identify notifications in ajax request, a automatic timestamp and table prefix
 */
function notifications_upgrade1_9_002()
{
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_notificationpopup',array(
		'fd' => array(
			'notify_id' => array('type' => 'auto','nullable' => False,'comment' => 'primary key'),
			'account_id' => array('type' => 'int','precision' => '20','nullable' => False,'comment' => 'user to notify'),
			'notify_message' => array('type' => 'text','comment' => 'notification message'),
			'notify_created' => array('type' => 'timestamp','default' => 'current_timestamp','comment' => 'creation time of notification')
		),
		'pk' => array('notify_id'),
		'fk' => array(),
		'ix' => array('account_id','notify_created'),
		'uc' => array()
	),array(
		'notify_message' => 'message',
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.9.003';
}


function notifications_upgrade1_9_003()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_notificationpopup','notify_type',array(
		'type' => 'varchar',
		'precision' => '32',
		'comment' => 'notification type'
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.9.004';
}


function notifications_upgrade1_9_004()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '14.1';
}

function notifications_upgrade14_1()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_notificationpopup','notify_message',array(
		'type' => 'varchar',
		'precision' => '16384',
		'comment' => 'notification message'
	));
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_notificationpopup','notify_type',array(
		'type' => 'ascii',
		'precision' => '32',
		'comment' => 'notification type'
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '14.3';
}

function notifications_upgrade14_3()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '16.1';
}

function notifications_upgrade16_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_notificationpopup','notify_status',array(
		'type' => 'varchar',
		'precision' => '32',
		'comment' => 'notification status'
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '17.1';
}

function notifications_upgrade17_1()
{
	$GLOBALS['egw_setup']->oProc->AddColumn('egw_notificationpopup','notify_data',array(
		'type' => 'varchar',
		'precision' => '4096',
		'comment' => 'notification actions'
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '17.1.001';
}

/**
 * Change notify_message column type from Varchar to Text, this field is a dynamic field.
 *
 * @return string
 */
function notifications_upgrade17_1_001()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_notificationpopup','notify_message',array(
		'type' => 'text',
		'comment' => 'notification message'
	));
	return $GLOBALS['setup_info']['notifications']['currentver'] = '17.1.002';
}

/**
 * Bump version to 19.1
 *
 * @return string
 */
function notifications_upgrade17_1_002()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '19.1';
}

/**
 * Bump version to 20.1
 *
 * @return string
 */
function notifications_upgrade19_1()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '20.1';
}

/**
 * Bump version to 21.1
 *
 * @return string
 */
function notifications_upgrade20_1()
{
	return $GLOBALS['setup_info']['notifications']['currentver'] = '21.1';
}

function notifications_upgrade21_1()
{
	$GLOBALS['egw_setup']->oProc->AlterColumn('egw_notificationpopup','notify_message',array(
		'type' => 'longtext',
		'comment' => 'notification message'
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '23.1';
}