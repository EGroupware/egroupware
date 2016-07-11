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
		).sendRequest(true);
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
		return egw.checkNotification();
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
		egwpopup_message.innerHTML = notifymessages[show]['message'];

		// Activate links
		jQuery('div[data-id],div[data-url]', egwpopup_message).on('click',
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
		request.sendRequest(true);
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
		request.sendRequest(true);
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
			notifymessages[_id] = {message:_message};
			return;
		}

		var data = this.getData(_message);
		// Prevent the same thing popping up multiple times
		notifymessages[_id] = {message:_message, data: data};
		// Notification API
		if(_browser_notify)
		{
			egw.notification(data.title, {
				tag: data.app+":"+_id,
				body: data.message,
				icon: data.icon,
				onclose:function(e){
					// notification id
					var id = this.tag.split(":");
					// confirm the message
					var request = egw.json("notifications.notifications_ajax.confirm_message", [id[1]]);
					request.sendRequest();
				},
				onclick:function(e){
					// notification id
					var id = this.tag.split(":");

					// get the right data from messages object
					var notify = notifymessages[id[1]];

					if (!notifymessages[id[1]]) this.close();

					if (notify && notify.data && notify.data.id)
					{
						egw.open(notify.data.id, notify.data.app);
					}
					else if (notify && notify.data)
					{
						egw.open_link(notify.data.url,'_blank',notify.data.popup);
					}

					var request = egw.json("notifications.notifications_ajax.confirm_message", [id[1]]);
					request.sendRequest();
					delete notifymessages[id[1]];
					this.close();
				}
			});
		}
	};

	/**
	 * Extract useful data out of HTML message
	 *
	 * @param {type} _message
	 * @returns {notificationajaxpopup_L15.notifications.prototype.getData.data}
	 */
	notifications.prototype.getData = function (_message) {
		var dom = jQuery(document.createElement('div')).html(_message);;
		var link = dom.find('div[data-id],div[data-url]');
		var data = {
			message: dom.text(),
			title: link.text(),
			icon: link.find('img').attr('src')
		};
		jQuery.extend(data,link.data());
		return typeof data == 'object'? data: {};
	};

	var lab = egw_LAB || $LAB;
	var self = notifications;
	lab.wait(function(){
		if (typeof window.app == 'undefined') window.app = {};
		window.app.notifications = new self();
	});
})();
