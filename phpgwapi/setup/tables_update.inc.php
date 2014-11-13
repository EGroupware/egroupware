<?php
/**
 * EGroupware - API Setup
 *
 * Update scripts from 14.1 onwards
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Include older eGroupWare update support */
include('tables_update_0_9_9.inc.php');
include('tables_update_0_9_10.inc.php');
include('tables_update_0_9_12.inc.php');
include('tables_update_0_9_14.inc.php');
include('tables_update_1_0.inc.php');
include('tables_update_1_2.inc.php');
include('tables_update_1_4.inc.php');
include('tables_update_1_6.inc.php');
include('tables_update_1_8.inc.php');

function phpgwapi_upgrade14_1()
{
	$GLOBALS['egw_setup']->oProc->CreateTable('egw_sharing',array(
		'fd' => array(
			'share_id' => array('type' => 'auto','nullable' => False,'comment' => 'auto-id'),
			'share_token' => array('type' => 'varchar','precision' => '64','nullable' => False,'comment' => 'secure token'),
			'share_path' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'path to share'),
			'share_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'owner of share'),
			'share_expires' => array('type' => 'date','comment' => 'expire date of share'),
			'share_writable' => array('type' => 'int','precision' => '1','nullable' => False,'default' => '0','comment' => '0=readable, 1=writable'),
			'share_with' => array('type' => 'varchar','precision' => '4096','comment' => 'email addresses, comma seperated'),
			'share_passwd' => array('type' => 'varchar','precision' => '128','comment' => 'optional password-hash'),
			'share_created' => array('type' => 'timestamp','nullable' => False,'comment' => 'creation date'),
			'share_last_accessed' => array('type' => 'timestamp','comment' => 'last access of share')
		),
		'pk' => array('share_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array('share_token')
	));

	return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '14.1.900';
}

