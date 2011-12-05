/**
 * eGroupWare - Notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpoup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Christian Binder <christian@jaytraxx.de>
 * @version $Id$
 */

var notifymessages = {};
var EGW_BROWSER_NOTIFY_ALLOWED = 0;

function egwpopup_init(_i) {
	window.setTimeout("egwpopup_refresh(" + _i + ");", 1000);
}

function egwpopup_setTimeout(_i) {
	window.setTimeout("egwpopup_refresh(" + _i + ");", _i*1000);
}
function egwpopup_refresh(_i) {
	var request = xajax_doXMLHTTP("notifications.notifications_ajax.get_notifications", check_browser_notify());
	request.request.error = function(_xmlhttp,_err){if(console) {console.log(request);console.log(_err)}};
	egwpopup_setTimeout(_i);
}

/**
 * Check to see if browser supports / allows desktop notifications
 */
function check_browser_notify() {
	return window.webkitNotifications && window.webkitNotifications.checkPermission() == EGW_BROWSER_NOTIFY_ALLOWED;
}

function egwpopup_display() {
	var egwpopup;
	var egwpopup_message;
	var Browserwidth;
	var Browserheight;
	var egwpopup_ok_button;
	egwpopup = document.getElementById("egwpopup");
	egwpopup_message = document.getElementById("egwpopup_message");
	egwpopup_ok_button = document.getElementById("egwpopup_ok_button");
	egwpopup.style.display = "block";
	egwpopup.style.position = "absolute";
	egwpopup.style.width = "500px";
	Browserwidth = (window.innerWidth || document.body.clientWidth || 640)
	Browserheight = (window.innerHeight || document.body.clientHeight || 480)
	egwpopup.style.left = (Browserwidth/2 - 250) + "px";
	egwpopup.style.top = (Browserheight/4) + "px";
	egwpopup_message.style.maxHeight = (Browserheight/2) + "px";
	for(var show in notifymessages) break;
	egwpopup_message.innerHTML = notifymessages[show];
	var num = 0;
	for(var id in notifymessages) ++num;
	if(num-1 > 0 ) {
		egwpopup_ok_button.value = "OK (" + (num-1) + ")";
	} else {
		egwpopup_ok_button.value = "OK";
	}
	if(window.webkitNotifications && window.webkitNotifications.checkPermission() != EGW_BROWSER_NOTIFY_ALLOWED &&
		jQuery('#desktop_perms').length == 0)
	{
		var label = 'Desktop notifications';
		try {
			if(egw) label = egw.lang(label);
		} catch(err) {}
		var desktop_button = jQuery('<button id="desktop_perms">' + label + '</button>')
			.click(function() {
				window.webkitNotifications.requestPermission(); 
				jQuery(this).hide();
			});
		desktop_button.appendTo(jQuery(egwpopup_ok_button).parent());
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

function egwpopup_button_ok() {
	var egwpopup;
	var egwpopup_message;
	egwpopup = document.getElementById("egwpopup");
	egwpopup_message = document.getElementById("egwpopup_message");
	egwpopup_message.scrollTop = 0;

	for(var confirmed in notifymessages) break;
	xajax_doXMLHTTP("notifications.notifications_ajax.confirm_message", confirmed);
	delete notifymessages[confirmed];
	
	for(var id in notifymessages) break;
	if (id == undefined) {
		egwpopup.style.display = "none";
		egwpopup_message.innerHTML = "";
		notificationbell_switch("inactive");
	} else {	
		egwpopup_display();
	}
}

// Close and mark all as read
function egwpopup_button_close() {
	var ids = new Array();
	for(var id in notifymessages) {
		ids.push(id);
	}
	xajax_doXMLHTTP("notifications.notifications_ajax.confirm_message", ids);

	notifymessages = {};
	var egwpopup = document.getElementById("egwpopup");
	var egwpopup_message = document.getElementById("egwpopup_message");
	egwpopup.style.display = "none";
	egwpopup_message.innerHTML = "";
	notificationbell_switch("inactive");
}

function append_notification_message(_id, _message, _browser_notify) {

	if(!check_browser_notify() || typeof notifymessages[_id] != 'undefined')
	{
		notifymessages[_id] = _message;
		return;
	}
	// Prevent the same thing popping up multiple times
	notifymessages[_id] = _message;

	// Notification API
	if(_browser_notify)
	{
		var notice = webkitNotifications.createHTMLNotification(_browser_notify);
		notice.ondisplay = function() {
			// Confirm when user gets to see it - no close needed
			// Wait a bit to let it load first, or it might not be there when requested.  
			window.setTimeout( function() {
				xajax_doXMLHTTP("notifications.notifications_ajax.confirm_message", _id);
			}, 2000);
		};
		notice.show();
	}
}
