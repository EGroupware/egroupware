<?php
/**
 * EGroupware - Notifications
 *
 * serves the hook "after_navbar" to create the notificationwindow
 *
 * @abstract notificatonwindow is an empty and non displayed 1px div which gets rezised
 * and populated if a notification is about to be displayed.
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpoup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

use EGroupware\Api;
if ($GLOBALS['egw_info']['user']['apps']['notifications'])
{
	$notification_config = Api\Config::read('notifications');
	Api\Translation::add_app('notifications');
	$langRequire = array (
		'app'	=> 'notifications',
		'lang'	=> Api\Translation::$userlang,
		'etag'	=> Api\Translation::etag('notifications', Api\Translation::$userlang)
	);
	$popup_poll_interval = empty($notification_config['popup_poll_interval']) ? 60 : $notification_config['popup_poll_interval'];
	// Independent of whether a real push connection is otherwise available: neither Dovecot's
	// nor JMAP's mail-server push currently triggers notification_check_mailbox()'s own
	// notify_folders check (only polling does - see doc/ai/projects/push-fallback-longpoll.md's
	// "Mail notification-check polling" follow-up note), so the client (notifications/js/app.ts)
	// needs to know whether to keep a slow keep-alive poll running even once egw.pushAvailable()
	// is true. 180s matches notification_check_mailbox()'s own internal 3-minute rate limit - no
	// point polling more often than the check itself can ever actually run.
	$mail_check_interval = !empty($GLOBALS['egw_info']['user']['apps']['mail']) &&
		class_exists('mail_hooks') && mail_hooks::needsNotificationCheckPolling() ? 180 : '';
	// notifications/js/app.ts's own bundle is registered by hook_framework_header.inc.php instead
	// of here - that hook fires BEFORE Api\Framework\Ajax::header()'s _get_header() call resolves
	// get_script_links() into the page, whereas 'after_navbar' fires AFTER it (found live: an
	// includeJS() call here was simply too late to ever reach the rendered page - see that file's
	// comment). This element only carries config values app.ts's constructor reads via
	// getElementById() - not a <script> tag itself, so it's not subject to CSP's script-src either.
	echo '<div id="notifications_script_id" style="display:none" data-poll-interval="'.$popup_poll_interval.
		'" data-mail-check-interval="'.$mail_check_interval.
		'" data-langRequire="'. htmlspecialchars(json_encode($langRequire)).'"></div>';
	echo '
		<div id="egwpopup" style="display: none; z-index: 999;">
			<div id="egwpopup_header">'.lang('Notifications').
			'<span class="button_right_toggle"></span><span class="egwpopup_seenall" title="'. lang('mark all as read').'"></span>'.
			'<span class="egwpopup_deleteall" title="'.lang('delete all messages').'"></span></div>
			<div id="egwpopup_list"></div>
		</div>
	';
	unset($notification_config);
}
