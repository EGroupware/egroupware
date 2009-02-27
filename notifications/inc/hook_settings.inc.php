<?php
/**
 * eGroupWare - Notifications - Preferences
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 */

$notifications = new notifications();
$available_chains = $notifications->get_available_chains('human');

$verbosity_values = array(
	'low' 		=> lang('low'),
	'medium' 	=> lang('medium'),
	'high' 		=> lang('high'),
);
 
$GLOBALS['settings'] = array(
	'notification_chain' => array(
		'type'   => 'select',
		'label'  => 'Notify me by',
		'name'   => 'notification_chain',
		'values' => $available_chains,
		'help'   => 'Choose a notification-chain. You will be notified over the backends included in the chain.<br />'
					.'Note: If a notification-chain is marked as "disabled", your Administrator does not allow one or'
					.' more of the backends included in the chain and notifications falls back to "E-Mail" while notifying you.',
		'xmlrpc' => True,
		'admin'  => False
	),
	'egwpopup_verbosity' => array(
		'type'   => 'select',
		'label'  => 'eGroupware-Popup verbosity',
		'name'   => 'egwpopup_verbosity',
		'values' => $verbosity_values,
		'help'   => 'How verbose should the eGroupware-Popup behave if a notification is sent to the user:<br />'
					.'low: just display the notification bell in the topmenu - topmenu must be enabled!<br />'
					.'medium: bring notification window to front<br />'
					.'high: bring notification window to front and let the browser do something to announce itself',
		'xmlrpc' => True,
		'admin'  => False
	),
	'external_mailclient' => array(
		'type'   => 'check',
		'label'  => 'Optimize E-Mails for external mail client',
		'name'   => 'external_mailclient',
		'help'   => 'If set, embedded links get rendered special for external clients',
		'xmlrpc' => True,
		'admin'  => False
	),
);
?>