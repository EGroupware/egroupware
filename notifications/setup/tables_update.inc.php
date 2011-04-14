<?php
/**
 * EGroupware - Setup
 * http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage setup
 * @version $Id$
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

