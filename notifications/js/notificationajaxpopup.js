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

	/**
	 * time range label today
	 * @type Number
	 */
	const TIME_LABEL_TODAY = 0;

	/**
	 * time range label yesterday
	 * @type Number
	 */
	const TIME_LABEL_YESTERDAY = 1;

	/**
	 * time range label this month
	 * @type Number
	 */
	const TIME_LABEL_THIS_MONTH = 2;

	/**
	 * time range label last month
	 * @type Number
	 */
	const TIME_LABEL_LAST_MONTH = 3;

	/**
	 * Heigh priorority action for notifing about an entry.
	 * Action: It pops up the entry once
	 * @type Number
	 */
	const EGW_PR_NOTIFY_HEIGH = 1;

	/**
	 * Medium priority for notifing about an entry
	 * Action: Not defined
	 * @type Number
	 */
	const EGW_PR_NOTIFY_MEDIUM = 2;

	/**
	 * Low priority for notifing about an entry
	 * Action: Not defined
	 * @type Number
	 */
	const EGW_PR_NOTIFY_LOW = 3;

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
	 * This function gets created time and current time then finds out if
	 * the time range of the event.
	 *
	 * @param {type} _created
	 * @param {type} _current
	 *
	 * @returns {int} returns type of range
	 */
	notifications.prototype.getTimeLabel = function (_created, _current) {
		var created = typeof _created == 'string'? new Date(_created): _created;
		var current = new Date(_current.date);
		var time_diff = (current - created) / 1000;
		var result = '';
		if (time_diff < current.getHours() * 3600)
		{
			result = TIME_LABEL_TODAY;
		}
		else if ((time_diff > current.getHours() * 3600) &&
				(time_diff < (current.getHours() * 3600 + 86400)))
		{
			result = TIME_LABEL_YESTERDAY;
		}
		else if (current.getFullYear() == created.getFullYear() &&
				(current.getMonth() - created.getMonth()) == 0 &&
				time_diff > (current.getHours() * 3600 + 86400))
		{
			result = TIME_LABEL_THIS_MONTH;
		}
		else if (current.getFullYear() == created.getFullYear() &&
				(current.getMonth() - created.getMonth()) == 1)
		{
			result = TIME_LABEL_LAST_MONTH;
		}

		return result;
	};

	/**
	 * Display notifications window
	 */
	notifications.prototype.display = function() {
		// list container
		var $egwpopup_list = jQuery("#egwpopup_list");

		// Preserve already poped notifications
		var poped = [];
		$egwpopup_list.find('.egwpopup_expanded').each(function(index, item){
			// store messages ids of poped messages
			poped.push(item.id.replace('egwpopup_message_', ''));
		});
		// clear the list
		$egwpopup_list.empty();
		// define time label deviders
		var $today = jQuery(document.createElement('div'))
					.addClass('egwpopup_time_label')
					.text(egw.lang('today'))
			, $yesterday = jQuery(document.createElement('div'))
					.addClass('egwpopup_time_label')
					.text(egw.lang('yesterday'))
			, $this_month = jQuery(document.createElement('div'))
					.addClass('egwpopup_time_label')
					.text(egw.lang('this month'))
			, $last_month = jQuery(document.createElement('div'))
					.addClass('egwpopup_time_label')
					.text(egw.lang('last month'));
		// reverse indexes to get the latest messages at the top
		var indexes = Object.keys(notifymessages).reverse()
		for(var index in indexes)
		{
			var id = indexes[index];
			var $message, $mark, $delete, $inner_container, $nav_prev, $nav_next,
				$more_info, $top_toolbar, $open_entry, $date, $collapse;
			var message_id = 'egwpopup_message_'+id;
			var time_label = this.getTimeLabel(notifymessages[id]['created'], notifymessages[id]['current']);
			if (jQuery('#'+message_id,$egwpopup_list).length > 0)
			{
				this.update_message_status(id, notifymessages[id]['status']);
				continue;
			}
			// set the time labels on
			switch (time_label)
			{
				case TIME_LABEL_TODAY:
					if (!$egwpopup_list.has($today).length) $today.appendTo($egwpopup_list);
					break;
				case TIME_LABEL_YESTERDAY:
					if (!$egwpopup_list.has($yesterday).length) $yesterday.appendTo($egwpopup_list);
					break;
				case TIME_LABEL_THIS_MONTH:
					if (!$egwpopup_list.has($this_month).length) $this_month.appendTo($egwpopup_list);
					break;
				case TIME_LABEL_LAST_MONTH:
					if (!$egwpopup_list.has($last_month).length) $last_month.appendTo($egwpopup_list);
					break;
			}

			// message container
			$message = jQuery(document.createElement('div'))
					.addClass('egwpopup_message')
					.attr({
						id:message_id,
						'data-entryid': notifymessages[id]['data']['id'],
						'data-appname': notifymessages[id]['data']['app'],
			});
			// wrapper for message content
			$inner_container =  jQuery(document.createElement('div'))
					.addClass('egwpopup_message_inner_container')
					.appendTo($message);
			$inner_container[0].innerHTML = notifymessages[id]['message'];
			if (notifymessages[id]['children'])
			{
				for (var c in notifymessages[id]['children'])
				{
					$inner_container[0].innerHTML += notifymessages[id]['children'][c]['message'];
				}
			}
			$more_info = jQuery(document.createElement('div'))
					.addClass('egwpopup_message_more_info')
					.text(egw.lang('More info')+'...')
					.appendTo($message);
			// top toolbar NODE
			$top_toolbar = jQuery(document.createElement('div'))
					.addClass('egwpopup_message_top_toolbar');
			// Date indicator NODE
			$date = jQuery(document.createElement('span'))
					.addClass('egwpopup_message_date')
					.prependTo($top_toolbar)
					.text(notifymessages[id]['created']);
			if (notifymessages[id]['data']['id'] || notifymessages[id]['data']['url']) {
				// OPEN entry button
				$open_entry = jQuery(document.createElement('span'))
						.addClass('egwpopup_message_open')
						.attr('title',egw.lang('open notified entry'))
						.click(jQuery.proxy(this.open_entry, this,[$message]))
						.prependTo($top_toolbar);
			}
			// Previous button
			$nav_prev = jQuery(document.createElement('span'))
					.addClass('egwpopup_nav_prev')
					.attr('title',egw.lang('previous'))
					.click(jQuery.proxy(this.nav_button, this,[$message, "prev"]))
					.prependTo($top_toolbar);
			// Next button
			$nav_next = jQuery(document.createElement('span'))
					.addClass('egwpopup_nav_next')
					.attr('title',egw.lang('next'))
					.click(jQuery.proxy(this.nav_button, this,[$message, "next"]))
					.prependTo($top_toolbar);
			// Delete button
			$delete = jQuery(document.createElement('span'))
					.addClass('egwpopup_delete')
					.attr('title',egw.lang('delete this message'))
					.click(jQuery.proxy(this.button_delete, this,[$message]))
					.prependTo($top_toolbar);
			// Mark as read button
			$mark = jQuery(document.createElement('span'))
					.addClass('egwpopup_mark')
					.prependTo($top_toolbar);
			// Collapse button (close icon)
			$collapse = jQuery(document.createElement('span'))
					.addClass('egwpopup_collapse')
					.click(jQuery.proxy(this.collapseMessage, this, [$message]))
					.prependTo($top_toolbar);

			if (notifymessages[id]['status'] != 'SEEN')
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
			if (notifymessages[id]['data'] && notifymessages[id]['data']['actions'] && notifymessages[id]['data']['actions'].length > 0)
			{
				var $actions_container = jQuery(document.createElement('div')).addClass('egwpopup_actions_container');
				for(var action in notifymessages[id].data.actions)
				{
					var func = new Function(notifymessages[id].data.actions[action].onExecute);
					jQuery(document.createElement('button'))
							.addClass('et2_button')
							.css({'background-image':'url('+egw.image(notifymessages[id].data.actions[action].icon,notifymessages[id].data.app)+')'})
							.text(notifymessages[id].data.actions[action].caption)
							.click(jQuery.proxy(func,this))
							.prependTo($actions_container);
				}
				$actions_container.appendTo($message);
			}
			$top_toolbar.prependTo($message);
			$egwpopup_list.append($message);
			// bind click handler after the message container is attached
			$message.click(jQuery.proxy(this.clickOnMessage, this,[$message]));
			this.update_message_status(id, notifymessages[id]['status']);
			if (notifymessages[id]['extra_data']
					&& !notifymessages[id]['status']
					&& notifymessages[id]['extra_data']['egw_pr_notify'])
			{
				switch (notifymessages[id]['extra_data']['egw_pr_notify'])
				{
					case EGW_PR_NOTIFY_HEIGH:
						poped.push(id);
						this.toggle(true);
						break;
					case EGW_PR_NOTIFY_MEDIUM:
					case EGW_PR_NOTIFY_LOW:
						//Could be define with all sort of stuffs
				}
			}
		}

		if (poped.length > 0)
		{
			for (var key in poped)
			{
				// pop again those where opened before refresh
				$egwpopup_list.find('#egwpopup_message_'+poped[key]).trigger('click');
			}
		}
		this.counterUpdate();
	};

	/**
	 * Opens the relavant entry from clicked message
	 *
	 * @param {jquery object} _node
	 * @param {object} _event
	 */
	notifications.prototype.open_entry = function (_node, _event){
		_event.stopPropagation();
		this.message_seen(_node, _event);
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
	 * Reposition the expanded message back in the list & removes the clone node
	 * @param {jquery object} _node
	 */
	notifications.prototype.collapseMessage = function (_node, _event){
		_event.stopPropagation();
		var cloned = _node[0].prev();
		if (cloned.length > 0 && cloned[0].id == _node[0].attr('id')+'_expanded')
			cloned.remove();
		_node[0].removeClass('egwpopup_expanded');
		_node[0].css('z-index', 0);
		this.checkNavButtonStatus();
		egw.loading_prompt('popup_notifications', false);
	};

	/**
	 * Expand a clicked message into bigger view
	 * @param {jquery object} _node
	 * @param {object} _event
	 *
	 * @return undefined
	 */
	notifications.prototype.clickOnMessage = function (_node, _event){
		// Do not run the click handler if it's been already expanded
		if (_node[0].hasClass('egwpopup_expanded') || jQuery(_event.target).hasClass('et2_button')) return;
		this.message_seen(_node, _event);
		var $node = jQuery(_node[0][0].cloneNode());
		if ($node)
		{
			$node.attr('id', _node[0].attr('id')+'_expanded')
					.addClass('egwpopup_message_clone')
					.insertBefore(_node[0]);
		}
		var zindex = jQuery('.egwpopup_expanded').length;
		_node[0].addClass('egwpopup_expanded').css('z-index', zindex++);
		this.checkNavButtonStatus();
		if (jQuery('#egwpopup').is(':visible') && !egwIsMobile()) egw.loading_prompt('popup_notifications', true);
	};

	notifications.prototype.nav_button = function (_params, _event){
		var $expanded = jQuery('.egwpopup_expanded');
		var $messages = jQuery('.egwpopup_message').not('.egwpopup_message_clone');
		var self = this;
		var current = 0;
		$messages.each(function(i, j){if (j.id == _params[0][0].id) current = i;});
		$expanded.each(function(index, item){
			self.collapseMessage([jQuery(item)], _event);
		});
		if (_params[1] == "prev")
		{
			$messages[current-1].click();
		}
		else
		{
			$messages[current+1].click();
		}
	}

	notifications.prototype.checkNavButtonStatus = function (){
		var top = 0;
		var $expanded = jQuery('.egwpopup_expanded');
		var $messages = jQuery('.egwpopup_message').not('.egwpopup_message_clone');
		$expanded.removeClass('egwpopup_nav_disable');
		$expanded.each(function(index, item){
			if (item.style.getPropertyValue('z-index') > $expanded[top].style.getPropertyValue('z-index'))
			{
				top = index;
			}
		});
		var $topNode = jQuery($expanded[top]);
		if ($topNode[top] == $messages[0]) $topNode.find('.egwpopup_nav_prev').addClass('egwpopup_nav_disable');
		if ($topNode[top] == $messages[$messages.length-1]) $topNode.find('.egwpopup_nav_next').addClass('egwpopup_nav_disable');
	}

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
		var ids = [id];
		if (notifymessages[id]['children'])
		{
			ids = ids.concat(Object.keys(notifymessages[id]['children']));
		}
		if (notifymessages[id]['status'] !='SEEN')
		{
			var request = egw.json("notifications.notifications_ajax.update_status", [ids, "SEEN"]);
			request.sendRequest(true);
			this.update_message_status(id, "SEEN");
		}
	};

	notifications.prototype.mark_all_seen = function()
	{
		if (!notifymessages || Object.keys(notifymessages).length == 0) return false;
		var ids = Object.keys(notifymessages);
		ids = ids.concat(this.findAllChildrenIds());
		egw.json("notifications.notifications_ajax.update_status", [ids, "SEEN"]).sendRequest(true);
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
		var ids = Object.keys(notifymessages);
		ids = ids.concat(this.findAllChildrenIds());
		egw.json("notifications.notifications_ajax.delete_message", [ids]).sendRequest(true);
		notifymessages = {};
		jQuery("#egwpopup_list").empty();
		this.counterUpdate();
		egw.loading_prompt('popup_notifications', false);
	};

	/**
	 * Callback for close button: close and mark all as read
	 */
	notifications.prototype.button_delete = function(_node, _event) {
		_event.stopPropagation();
		var egwpopup_message = _node[0];
		var id = egwpopup_message[0].id.replace(/egwpopup_message_/ig,'');
		var ids = [id];
		if (notifymessages[id]['children'])
		{
			ids = ids.concat(Object.keys(notifymessages[id]['children']));
		}
		var request = egw.json("notifications.notifications_ajax.delete_message", [ids]);
		request.sendRequest(true);
		var nextNode = egwpopup_message.next();
		var keepLoadingPrompt = false;
		delete (notifymessages[id]);
		if (nextNode.length > 0 && nextNode[0].id.match(/egwpopup_message_/ig) && egwpopup_message.hasClass('egwpopup_expanded'))
		{
			nextNode.trigger('click');
			keepLoadingPrompt = true;
		}
		// try to close the dialog if expanded before hidding it
		this.collapseMessage(_node, _event);
		if (keepLoadingPrompt && !egwIsMobile()) egw.loading_prompt('popup_notifications', true);
		egwpopup_message.remove();
		this.bell("inactive");
		this.counterUpdate();

	};

	/**
	 * Find all children ids from notifications
	 * @returns {Array} returns ids of all children from all parents
	 */
	notifications.prototype.findAllChildrenIds = function ()
	{
		var map = [];
		for (var i in notifymessages)
		{
			map = map.concat(this.findChildrenIds(notifymessages[i]));
		}
		return map;
	};

	/**
	 * Find children of a parent
	 * @param {object} _notification parent object
	 * @returns {Array} return ids of children
	 */
	notifications.prototype.findChildrenIds = function (_notification)
	{
		if(_notification.children)
		{
			return Object.keys(_notification.children);
		}
		return [];
	};

	/**
	 * Finds potential parent
	 *
	 * @param {type} _id
	 * @param {type} _app
	 * @returns {int} return id of notification
	 */
	notifications.prototype.findParent = function (_id, _app)
	{
		if (!_id && !_app) return null;
		for(var i in notifymessages)
		{
			if (notifymessages[i]['data']['id'] == _id && notifymessages[i]['data']['app'] == _app)
			{
				return i;
			}
		}
	};

	/**
	 * Add message to internal display-queue
	 *
	 * @param _rowData
	 * @param _browser_notify
	 */
	notifications.prototype.append = function(_rawData, _browser_notify) {

		var hasUnseen = [];
		notifymessages = {};
		for (var i=0; i < _rawData.length; i++)
		{
			var data = this.getData(_rawData[i]['message'], _rawData[i]['extra_data']);
			var parent;
			if ((parent = this.findParent(data['id'], data['app']))
					&& typeof _rawData[i]['extra_data']['egw_pr_notify'] == 'undefined')
			{
				if (parent == _rawData[i]['id']) continue;
				if (!notifymessages[parent]['children']) notifymessages[parent] = jQuery.extend(notifymessages[parent], {children:{}});
				notifymessages[parent]['children'][_rawData[i]['id']] = {
					message:_rawData[i]['message'],
					data: data,
					status: _rawData[i]['status'],
					created: _rawData[i]['created'],
					current: _rawData[i]['current'],
					extra_data: _rawData[i]['extra_data']
				};
				if (_rawData[i]['actions'] && _rawData[i]['actions'].length > 0) notifymessages[parent]['children'][_rawData[i]['id']]['data']['actions'] = _rawData[i]['actions'];
				continue;
			}

			// Prevent the same thing popping up multiple times
			notifymessages[_rawData[i]['id']] = {
				message:_rawData[i]['message'],
				data: data,
				status: _rawData[i]['status'],
				created: _rawData[i]['created'],
				current: _rawData[i]['current'],
				extra_data: _rawData[i]['extra_data']
			};
			if (_rawData[i]['actions'] && _rawData[i]['actions'].length > 0) notifymessages[_rawData[i]['id']]['data']['actions'] = _rawData[i]['actions'];
			// Notification API
			if(_browser_notify && !_rawData[i]['status'])
			{
				egw.notification(data.title, {
					tag: data.app+":"+_rawData[i]['id'],
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
			if (!_rawData[i]['status'])
			{
				egw.json("notifications.notifications_ajax.update_status", [_rawData[i]['id'], 'DISPLAYED']);
				hasUnseen.push(_rawData[i]['id']);
			}

		}
		switch(egw.preference('egwpopup_verbosity', 'notifications'))
		{
			case 'low':
				this.bell('active');
				break;
			case 'high':
				if (hasUnseen.length > 0)
				{
					alert(egw.lang('EGroupware has notifications for you'));
					egw.json("notifications.notifications_ajax.update_status", [hasUnseen, 'DISPLAYED']).sendRequest();
				}
				this.display();
				break;
			case 'medium':
				this.display();

		}
	};

	/**
	 * Extract useful data out of HTML message
	 *
	 * @param {type} _message
	 * @returns {notificationajaxpopup_L15.notifications.prototype.getData.data}
	 */
	notifications.prototype.getData = function (_message, _extra_data) {
		var dom = jQuery(document.createElement('div')).html(_message);
		var extra_data = _extra_data || {};
		var link = dom.find('div[data-id],div[data-url]');
		var data = {
			message: dom.text(),
			title: link.text(),
			icon: link.find('img').attr('src')
		};
		jQuery.extend(data,link.data(), extra_data);
		return typeof data == 'object'? data: {};
	};

	/**
	 * toggle notifications container
	 * @param boolean _stat true keeps the popup on
	 */
	notifications.prototype.toggle = function (_stat)
	{
		var $egwpopup = jQuery('#egwpopup');
		var $body = jQuery('body');
		var $counter = jQuery('#egwpopup_fw_notifications');

		if (!$egwpopup.is(":visible"))
		{
			$body.on('click', function(e){
				if (!$counter.is(e.target) && e.originalEvent.target.className !== 'popup_note' &&
						!$egwpopup.is(e.target) && $egwpopup.has(e.target).length == 0)
				{
					jQuery(this).off(e);
					$egwpopup.toggle('slide');
					egw.loading_prompt('popup_notifications', false);
				}
			});
			egw.loading_prompt('popup_notifications', jQuery("#egwpopup_list").find('.egwpopup_expanded').length>0);
		}
		else
		{
			egw.loading_prompt('popup_notifications', false);
			if (_stat) return;
			$body.off('click');
		}
		// Remove popup_note as soon as message list is toggled
		jQuery('.popup_note', '#egwpopup_fw_notifications').remove();

		if ($egwpopup.length>0) $egwpopup.toggle('slide');
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
			$popup_note.text(egw.lang('You have %1 unread notifications', counter));
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
		var langRequire = jQuery('#notifications_script_id').attr('data-langRequire');
		egw.langRequire(window, [JSON.parse(langRequire)], function()
		{
			var $egwpopup_fw = jQuery('#egwpopup_fw_notifications');
			switch (egw.preference('notification_chain','notifications'))
			{
				case 'popup_only':
				case 'popup_and_email':
				case 'popup_or_email':
				case 'all':
					break;
				default:
					$egwpopup_fw.hide();
					return;
			}
			if (typeof window.app == 'undefined') window.app = {};

			window.onbeforeunload = function()
			{
				if (typeof egw.killAliveNotifications =='function') egw.killAliveNotifications();
			};
			window.app.notifications = new self();
			// toggle notifications bar
			jQuery('.button_right_toggle', '#egwpopup').click(function(){window.app.notifications.toggle();});
			$egwpopup_fw.click(function(){window.app.notifications.toggle();});
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
		}, this);
	});
})();
