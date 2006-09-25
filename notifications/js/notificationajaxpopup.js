/**
 * eGroupWare - Notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpoup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

function notificationwindow_init() {
	window.setTimeout("notificationwindow_refresh();", 1000);
}

function notificationwindow_setTimeout() {
	window.setTimeout("notificationwindow_refresh();", 10000);
}
function notificationwindow_refresh() {
	xajax_doXMLHTTP("notifications.notification_popup.ajax_get_notifications");
	notificationwindow_setTimeout();
}

function notificationwindow_display() {
	var notificationwindow;
	notificationwindow = document.getElementById("notificationwindow");
	notificationwindow.style.display = "inline";
	notificationwindow.style.position = "absolute";
	notificationwindow.style.width = "500px";
	notificationwindow.style.left = screen.availWidth/2 - 250 + "px";
	notificationwindow.style.top = screen.availHeight/4 + "px";
	notificationwindow.style.height = "100%";
}

function notificationwindow_button_ok() {
	var notificationwindow;
	var notificationwindow_message;
	notificationwindow = document.getElementById("notificationwindow");
	notificationwindow_message = document.getElementById("notificationwindow_message");
	notificationwindow.style.display = "none";
	notificationwindow_message.innerHTML = "";
}