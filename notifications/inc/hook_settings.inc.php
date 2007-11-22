<?php
/**
 * eGroupWare - Notification - Preferences
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Christian Binder <christian@jaytraxx.de>
 */

$notification_chains = array(	'disable' => lang('do not notify me at all'),
								'popup_only' => lang('eGroupware-Popup only'),
								'winpopup_only' => lang('Windows-Popup only'),
								'email_only' => lang('E-Mail only'),
								'popup_or_email' => lang('eGroupware-Popup first, if that fails notify me by E-Mail'),
								'winpopup_or_email' => lang('Windows-Popup first, if that fails notify me by E-Mail'),
								'popup_and_email' => lang('eGroupware-Popup and E-Mail'),
								'winpopup_and_email' => lang('Windows-Popup and E-Mail'),
								'egwpopup_and_winpopup' => lang('eGroupware-Poupup and Windows-Popup'),
								'all' => lang('all possible notification extensions'),
								);
$verbosity_values = array(	'low' => lang('low'),
							'medium' => lang('medium'),
							'high' => lang('high'),
							);
 
$GLOBALS['settings'] = array(
	'notification_chain' => array(
		'type'   => 'select',
		'label'  => 'Notify me by',
		'name'   => 'notification_chain',
		'values' => $notification_chains,
		'help'   => 'Choose a notification-chain. You will be notified over the chosen extensions.',
		'xmlrpc' => True,
		'admin'  => False
	),
	'egwpopup_verbosity' => array(
		'type'   => 'select',
		'label'  => 'eGroupware-Popup verbosity',
		'name'   => 'egwpopup_verbosity',
		'values' => $verbosity_values,
		'help'   => 'How verbose should the eGroupware-Popup behave if a notification is sent to the user:<br />'
					.'low: just display the notification bell in the topmenu - topmenu must be enabled !<br />'
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