<?php
/**
 * eGroupWare - Setup
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
	$GLOBALS['egw_setup']->oProc->RefreshTable('egw_notificationpopup',array(
		'fd' => array(
			'account_id' => array('type' => 'int','precision' => '20','nullable' => False),
			'message' => array('type' => 'longtext')
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array('account_id'),
		'uc' => array()
	));

	return $GLOBALS['setup_info']['notifications']['currentver'] = '1.9.001';
}

