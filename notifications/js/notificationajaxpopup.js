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
		var $egwpopup,$egwpopup_list,$message,$mark,$delete,$delete_all,$mark_all;
		$egwpopup_list = jQuery("#egwpopup_list");

		for(var show in notifymessages)
		{
			var message_id = 'egwpopup_message_'+show;
			if (jQuery('#'+message_id,$egwpopup_list).length > 0)
			{
				this.update_message_status(show, notifymessages[show]['status']);
				continue;
			}
			$message = jQuery(document.createElement('div'))
					.addClass('egwpopup_message')
					.attr('id', message_id);
			$message[0].innerHTML = notifymessages[show]['message'];
			$delete = jQuery(document.createElement('span'))
					.addClass('egwpopup_expand')
					.attr('title',egw.lang('expand/collapse notification'))
					.click(jQuery.proxy(this.button_expand, this,[$message]))
					.prependTo($message);
			$delete = jQuery(document.createElement('span'))
					.addClass('egwpopup_delete')
					.attr('title',egw.lang('delete this message'))
					.click(jQuery.proxy(this.button_delete, this,[$message]))
					.prependTo($message);
			$mark = jQuery(document.createElement('span'))
					.addClass('egwpopup_mark')
					.prependTo($message);
			if (notifymessages[show]['status'] != 'SEEN')
			{

				$mark.click(jQuery.proxy(this.message_seen, this,[$message]))
					.attr('title',egw.lang('mark as read'));
			}
			// Activate links
			jQuery('div[data-id],div[data-url]', $message).on('click',
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
			if (notifymessages[show]['data'] && notifymessages[show]['data']['actions'])
			{
				var $actions_container = jQuery(document.createElement('div')).addClass('egwpopup_actions_container');
				for(var action in notifymessages[show].data.actions)
				{
					var func = new Function(notifymessages[show].data.actions[action].onExecute);
					jQuery(document.createElement('button'))
							.addClass('et2_button')
							.css({'background-image':'url('+egw.image(notifymessages[show].data.actions[action].icon,notifymessages[show].data.app)+')'})
							.text(notifymessages[show].data.actions[action].caption)
							.click(jQuery.proxy(func,this))
							.prependTo($actions_container);
				}
				$actions_container.prependTo($message);
			}
			$egwpopup_list.append($message);
			// bind click handler after the message container is attached
			$message.click(jQuery.proxy(this.clickOnMessage, this,[$message]));
			this.update_message_status(show, notifymessages[show]['status']);
		}
		this.counterUpdate();
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

	notifications.prototype.clickOnMessage = function (_node, _event){
		_event.stopPropagation();
		this.message_seen(_node, _event);
		if (_node[0][0] !=_event.target) return;
		var egwpopup_message = _node[0];
		var id = egwpopup_message[0].id.replace(/egwpopup_message_/ig,'');
		if (notifymessages[id]['data'])
		{
			if (notifymessages[id]['data']['id'])
			{
				egw.open(notifymessages[id]['data']['id'], notifymessages[id]['data']['app']);
			}
			else
			{
				egw.open_link(notifymessages[id]['data']['url'],'_blank',notifymessages[id]['data']['popup']);
			}
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
	notifications.prototype.message_seen = function(_node, _event) {
		_event.stopPropagation();
		var egwpopup_message = _node[0];
		var id = egwpopup_message[0].id.replace(/egwpopup_message_/ig,'');
		if (notifymessages[id]['status'] !='SEEN')
		{
			var request = egw.json("notifications.notifications_ajax.update_status", [id, "SEEN"]);
			request.sendRequest(true);
			this.update_message_status(id, "SEEN");
		}
	};

	notifications.prototype.mark_all_seen = function()
	{
		if (!notifymessages || Object.keys(notifymessages).length == 0) return false;
		egw.json("notifications.notifications_ajax.update_status", [Object.keys(notifymessages), "SEEN"]).sendRequest(true);
		for (var id in notifymessages)
		{
			this.update_message_status(id, "SEEN");
		}
	};

	notifications.prototype.update_message_status = function (_id, _status)
	{
		var $egwpopup_message = jQuery('#egwpopup_message_'+_id);
		notifymessages[_id]['status'] = _status;
		if ($egwpopup_message.length>0)
		{
			switch (_status)
			{
				case 'SEEN':
					$egwpopup_message.addClass('egwpopup_message_seen');
					break;
				case 'UNSEEN':
				case 'DISPLAYED':
					$egwpopup_message.removeClass('egwpopup_message_seen');
					break;
			}
		}
		this.counterUpdate();
	};

	notifications.prototype.delete_all = function () {
		if (!notifymessages || Object.entries(notifymessages).length == 0) return false;
		egw.json("notifications.notifications_ajax.delete_message", [Object.keys(notifymessages)]).sendRequest(true);
		delete(notifymessages);
		jQuery("#egwpopup_list").empty();
		this.counterUpdate();
	};

	/**
	 * Callback for close button: close and mark all as read
	 */
	notifications.prototype.button_delete = function(_node, _event) {
		_event.stopPropagation();
		var egwpopup_message = _node[0];
		var id = egwpopup_message[0].id.replace(/egwpopup_message_/ig,'');
		var request = egw.json("notifications.notifications_ajax.delete_message", [id]);
		request.sendRequest(true);
		delete (notifymessages[id]);
		egwpopup_message.hide();
		this.bell("inactive");
		this.counterUpdate();
	};

	/**
	 * Callback for expand button: Expand notification
	 */
	notifications.prototype.button_expand = function(_node, _event) {
		_event.stopPropagation();
		var egwpopup_message = _node[0];
        egwpopup_message.toggleClass('notification_expanded');
	};

	/**
	 * Add message to internal display-queue
	 *
	 * @param _id
	 * @param _message
	 * @param _browser_notify
	 */
	notifications.prototype.append = function(_id, _message, _browser_notify, _status) {
		if(!this.check_browser_notify() || typeof notifymessages[_id] != 'undefined')
		{
			notifymessages[_id] = {message:_message, status:_status};
			return;
		}

		var data = this.getData(_message);
		// Prevent the same thing popping up multiple times
		notifymessages[_id] = {message:_message, data: data, status: _status};
		// Notification API
		if(_browser_notify && !_status)
		{
			egw.notification(data.title, {
				tag: data.app+":"+_id,
				body: data.message,
				icon: data.icon,
				onclose:function(e){
					// notification id
					var id = this.tag.split(":");
					// delete the message
					var request = egw.json("notifications.notifications_ajax.update_status", [id[1], 'DISPLAYED']);
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
		var actions = dom.find('div[data-actions]');
		var data = {
			message: dom.text(),
			title: link.text(),
			icon: link.find('img').attr('src')
		};
		jQuery.extend(data,link.data());
		if (actions.data()) jQuery.extend(data,actions.data());
		return typeof data == 'object'? data: {};
	};

	/**
	 * toggle notifications container
	 */
	notifications.prototype.toggle = function ()
	{
		// Remove popup_note as soon as message list is toggled
		jQuery('.popup_note', '#egwpopup_fw_notifications').remove();

		var $egwpopup = jQuery('#egwpopup');
		if ($egwpopup.length>0) $egwpopup.slideToggle('fast');
	};

	/**
	 * Set new state of notifications counter
	 */
	notifications.prototype.counterUpdate = function ()
	{
		var $egwpopup_fw_notifications = jQuery('#egwpopup_fw_notifications');
		var $popup_note = jQuery(document.createElement('div')).addClass('popup_note');
		var counter = 0;
		for (var id in notifymessages)
		{
			if (notifymessages[id]['status'] != 'SEEN') counter++;
		}
		if (counter > 0)
		{
			$egwpopup_fw_notifications.addClass('egwpopup_notify');
			$egwpopup_fw_notifications.text(counter);
			$egwpopup_fw_notifications.append($popup_note);
			$popup_note.text(egw.lang('You have '+counter+' unread notifications'));
			setTimeout(function (){$popup_note.remove();}, 5000);
		}
		else
		{
			$egwpopup_fw_notifications.text(0);
			$egwpopup_fw_notifications.removeClass('egwpopup_notify');
		}
	};

	var lab = egw_LAB || $LAB;
	var self = notifications;
	lab.wait(function(){
		if (typeof window.app == 'undefined') window.app = {};
		window.app.notifications = new self();
		// toggle notifications bar
		jQuery('.egwpopup_toggle').click(function(){window.app.notifications.toggle();});
		jQuery('#egwpopup_fw_notifications').click(function(){window.app.notifications.toggle();});
		jQuery(".egwpopup_deleteall", '#egwpopup').click(function(){
			et2_dialog.show_dialog( function(_button){
					if (_button == 2) window.app.notifications.delete_all();
				}, 
				egw.lang('Are you sure you want to delete all notifications?'), 
				egw.lang('Delete notifications'), 
				null, et2_dialog.BUTTON_YES_NO, et2_dialog.WARNING_MESSAGE, undefined, egw
			);
		});
		jQuery(".egwpopup_seenall", '#egwpopup').click(function(){window.app.notifications.mark_all_seen()});
	});
})();
