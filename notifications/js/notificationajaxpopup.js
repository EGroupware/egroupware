/**
 * EGroupware Notifications - clientside javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpoup
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Christian Binder <christian@jaytraxx.de>, Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

/**
 * Installs app.notifications used to poll notifications from server and display them
 */
(function()
{
	var notifymessages = {};
	var EGW_BROWSER_NOTIFY_ALLOWED = 0;

	/**
	 * Constructor inits polling and installs handlers, polling frequence is passed via data-poll-interval of script tag
	 */
	function notifications() {
		var notification_script = document.getElementById('notifications_script_id');
		var popup_poll_interval = notification_script && notification_script.getAttribute('data-poll-interval');
		this.setTimeout(popup_poll_interval || 60);
		jQuery('#egwpopup_ok_button').click(jQuery.proxy(this.button_ok, this));
		jQuery('#egwpopup_close_button').click(jQuery.proxy(this.button_close, this));
		jQuery('#notificationbell').click(jQuery.proxy(this.display, this));
		// query notifictions now
		this.get_notifications();
	};

	/**
	 * Poll server for new notifications
	 */
	notifications.prototype.get_notifications = function()
	{
		egw.json(
			"notifications.notifications_ajax.get_notifications",
			this.check_browser_notify()
		).sendRequest();
	};

	/**
	 * Poll server in given frequency via Ajax
	 * @param _i
	 */
	notifications.prototype.setTimeout = function(_i) {
		var self = this;
		window.setTimeout(function(){
			self.get_notifications();
			self.setTimeout(_i);
		}, _i*1000);
	};

	/**
	 * Check to see if browser supports / allows desktop notifications
	 */
	notifications.prototype.check_browser_notify = function() {
		return window.webkitNotifications && window.webkitNotifications.checkPermission() == EGW_BROWSER_NOTIFY_ALLOWED;
	};

	/**
	 * Display notifications window
	 */
	notifications.prototype.display = function() {
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
		Browserwidth = (window.innerWidth || document.body.clientWidth || 640);
		Browserheight = (window.innerHeight || document.body.clientHeight || 480);
		egwpopup.style.left = (Browserwidth/2 - 250) + "px";
		egwpopup.style.top = (Browserheight/4) + "px";
		egwpopup_message.style.maxHeight = (Browserheight/2) + "px";
		for(var show in notifymessages) break;
		egwpopup_message.innerHTML = notifymessages[show];

		// Activate links
		$j('div[data-id],div[data-url]', egwpopup_message).on('click',
			function() {
				if(this.dataset.id)
				{
					egw.open(this.dataset.id,this.dataset.app);
				}
				else
				{
					egw.open_link(this.dataset.url,'_blank',this.dataset.popup);
				}
			}
		).addClass('et2_link');
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
	};

	/**
	 * Display or hide notifcation-bell
	 *
	 * @param {string} mode "active"
	 */
	notifications.prototype.bell = function(mode) {
		var notificationbell;
		notificationbell = document.getElementById("notificationbell");
		if(mode == "active") {
			notificationbell.style.display = "inline";
		} else {
			notificationbell.style.display = "none";
		}
	};

	/**
	 * Callback for OK button: confirms message on server and hides display
	 */
	notifications.prototype.button_ok = function() {
		var egwpopup;
		var egwpopup_message;
		egwpopup = document.getElementById("egwpopup");
		egwpopup_message = document.getElementById("egwpopup_message");
		egwpopup_message.scrollTop = 0;

		for(var confirmed in notifymessages) break;
		var request = egw.json("notifications.notifications_ajax.confirm_message", [confirmed]);
		request.sendRequest();
		delete notifymessages[confirmed];

		for(var id in notifymessages) break;
		if (id == undefined) {
			egwpopup.style.display = "none";
			egwpopup_message.innerHTML = "";
			this.bell("inactive");
		} else {
			this.display();
		}
	};

	/**
	 * Callback for close button: close and mark all as read
	 */
	notifications.prototype.button_close = function() {
		var ids = new Array();
		for(var id in notifymessages) {
			ids.push(id);
		}
		var request = egw.json("notifications.notifications_ajax.confirm_message", [ids]);
		request.sendRequest();
		notifymessages = {};
		var egwpopup = document.getElementById("egwpopup");
		var egwpopup_message = document.getElementById("egwpopup_message");
		egwpopup.style.display = "none";
		egwpopup_message.innerHTML = "";
		this.bell("inactive");
	};

	/**
	 * Add message to internal display-queue
	 *
	 * @param _id
	 * @param _message
	 * @param _browser_notify
	 */
	notifications.prototype.append = function(_id, _message, _browser_notify) {
		if(!this.check_browser_notify() || typeof notifymessages[_id] != 'undefined')
		{
			notifymessages[_id] = _message;
			return;
		}
		// Prevent the same thing popping up multiple times
		notifymessages[_id] = _message;

		// Notification API
		if(_browser_notify)
		{
			var notice = null;
			if(webkitNotifications.createHTMLNotification)
			{
				notice = webkitNotifications.createHTMLNotification(_browser_notify);
			}
			else if (webkitNotifications.createNotification)
			{
				// Pull the subject of the messasge, if possible
				var message = /<b>(.*?)<\/b>/.exec(_message);
				if(message && message[1])
				{
					_message = message[1];
				}
				else
				{
					_message = _message.replace(/<(?:.|\n)*?>/gm, '');
				}
				notice = webkitNotifications.createNotification('', "Egroupware",_message);

				// When they click, bring up the popup for full info
				notice.onclick = function() {
					window.focus();
					window.app.notifications.display();
					this.close();
				};
			}
			if(notice)
			{
				notice.ondisplay = function() {
					// Confirm when user gets to see it - no close needed
					// Wait a bit to let it load first, or it might not be there when requested.
					window.setTimeout( function() {
						var request = egw.json("notifications.notifications_ajax.confirm_message", _id);
						request.sendRequest();
					}, 2000);
				};
				notice.show();
			}
		}
	};

	var lab = egw_LAB || $LAB;
	var self = notifications;
	lab.wait(function(){
		if (typeof window.app == 'undefined') window.app = {};
		window.app.notifications = new self();
	});
})();
