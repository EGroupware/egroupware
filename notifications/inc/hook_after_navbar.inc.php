<?php
/**
 * eGroupWare - Notifications
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
 * @todo check if user wants notifications via egw popup!
 */
$GLOBALS['egw']->translation->add_app('notifications');
if (!$GLOBALS['egw_info']['user']['preferences']['notifications']['disable_ajaxpopup']) {
	echo '<script src="'. $GLOBALS['egw_info']['server']['webserver_url']. '/notifications/js/notificationajaxpopup.js'. '" type="text/javascript"></script>';
	echo '<script type="text/javascript">notificationwindow_init()</script>';
	echo '
		<div id="notificationwindow" style="display: none; z-index: 999;">
			<div id="divAppboxHeader">'. lang('Notification'). '</div>
				<div id="divAppbox">
				<div id="notificationwindow_message"</div>
				<center><input type="submit" value="'. lang('ok'). '" onClick="notificationwindow_button_ok();"></center>
			</div>
		</div>
	';
}