<?php
/**
 * EGroupware - Notifications
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
			'notify_id' => array('type' => 'auto','nullable' => False,'comment' => 'primary key'),
			'account_id' => array('type' => 'int','meta' => 'user','precision' => '20','nullable' => False,'comment' => 'user to notify'),
			'notify_message' => array('type' => 'longtext','comment' => 'notification message'),
			'notify_created' => array('type' => 'timestamp','meta' => 'timestamp','default' => 'current_timestamp','comment' => 'creation time of notification'),
			'notify_type' => array('type' => 'ascii','precision' => '32','comment' => 'notification type'),
			'notify_status' => array('type' => 'varchar','precision' => '32','comment' => 'notification status'),
			'notify_data' => array('type' => 'varchar','precision' => '4096','comment' => 'notification data')
		),
		'pk' => array('notify_id'),
		'fk' => array(),
		'ix' => array('account_id','notify_created'),
		'uc' => array()
	)
);
