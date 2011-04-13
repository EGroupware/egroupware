<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_notificationpopup' => array(
		'fd' => array(
			'account_id' => array('type' => 'int','precision' => '20','nullable' => False),
			'message' => array('type' => 'longtext')
		),
		'pk' => array(),
		'fk' => array(),
		'ix' => array('account_id'),
		'uc' => array()
	)
);
