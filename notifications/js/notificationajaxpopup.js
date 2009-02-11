/**
 * eGroupWare - Notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpoup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

var notifymessages = new Array();

function notificationwindow_init() {
	window.setTimeout("notificationwindow_refresh();", 1000);
}

function notificationwindow_setTimeout() {
	window.setTimeout("notificationwindow_refresh();", 60000);
}
function notificationwindow_refresh() {
	xajax_doXMLHTTP("notifications.ajaxnotifications.check_mailbox");
	xajax_doXMLHTTP("notifications.ajaxnotifications.get_popup_notifications");
	notificationwindow_setTimeout();
}

function notificationwindow_display() {
	var notificationwindow;
	var notificationwindow_message;
	var Browserwidth;
	var Browserheight;
	var notificationwindow_ok_button;
	notificationwindow_ok_button = document.getElementById("notificationwindow_ok_button");
	notificationwindow = document.getElementById("notificationwindow");
	notificationwindow_message = document.getElementById("notificationwindow_message");
	notificationwindow.style.display = "inline";
	notificationwindow.style.position = "absolute";
	notificationwindow.style.width = "500px";
	Browserwidth = (window.innerWidth || document.body.clientWidth || 640)
	Browserheight = (window.innerHeight || document.body.clientHeight || 480)
	notificationwindow.style.left = (Browserwidth/2 - 250) + "px";
	notificationwindow.style.top = (Browserheight/4) + "px";
	notificationwindow.style.height = "100%";
	notificationwindow_message.innerHTML = notifymessages[0];
	if(notifymessages.length-1 > 0 ) {
		notificationwindow_ok_button.value = "OK (" + (notifymessages.length-1) + ")";
	} else {
		notificationwindow_ok_button.value = "OK";
	}

}

function notificationbell_switch(mode) {
	var notificationbell;
	notificationbell = document.getElementById("notificationbell");
	if(mode == "active") {
		notificationbell.style.display = "inline";
	} else {
		notificationbell.style.display = "none";
	}
}

function notificationwindow_button_ok() {
	var notificationwindow;
	var notificationwindow_message;
	notificationwindow = document.getElementById("notificationwindow");
	notificationwindow_message = document.getElementById("notificationwindow_message");
	notifymessages.shift();
	if(notifymessages.length > 0) {
		notificationwindow_display();
	} else {
		notificationwindow.style.display = "none";
		notificationwindow_message.innerHTML = "";
		notificationbell_switch("inactive");
	}
}

function append_notification_message(_message) {
	notifymessages.push(_message);
}