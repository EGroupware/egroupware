/**
 * EGroupware clientside API: opening of windows, popups or application entries
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/*egw:uses
	egw_core;
	egw_links;
*/

/**
 * @augments Class
 * @param {object} _egw
 * @param {DOMwindow} _wnd
 */
egw.extend('open', egw.MODULE_WND_LOCAL, function(_egw, _wnd)
{
	"use strict";

	/**
	 * Magic handling for mailto: uris using mail application.
	 *
	 * We check for open compose windows and add the address in as specified in
	 * the URL.  If there are no open compose windows, a new one is opened.  If
	 * there are more than one open compose window, we prompt for which one to
	 * use.
	 *
	 * The user must have set the 'Open EMail addresses in external mail program' preference
	 * to No, otherwise the browser will handle it.
	 *
	 * @param {String} uri
	 */
	function mailto(uri)
	{
		// Parse uri into a map
		var match = [], index;
		var mailto = uri.match(/^mailto:([^?]+)/) || [];
		var hashes = uri.slice(uri.indexOf('?') + 1).split('&');
		for(var i = 0; i < hashes.length; i++)
		{
			index = hashes[i].split('=');
			match.push(index[0]);
			match[index[0]] = index[1];
		}

		var content = {
			to: mailto[1] || [],
			cc: match['cc']	|| [],
			bcc: match['bcc'] || []
		};

		// Get open compose windows
		var compose = egw.getOpenWindows("mail", /(^compose_)||(^mail.compose)/);
		if(compose.length == 0)
		{
			// No compose windows, might be no mail app.js
			// We really want to use mail_compose() here
			egw.open('','mail','add',{'preset[mailto]': uri},'compose__','mail');
		}
		if(compose.length == 1)
		{
			try {
				var popup = egw.open_link('',compose[0],'100x100','mail');
				popup.app.mail.setCompose(compose[0], content);
			} catch(e) {
				// Looks like a leftover window that wasn't removed from the list
				egw.debug("warn", e.message);
				popup.close();
				egw.windowClosed("mail",popup);
				window.setTimeout(function() {
					egw.open_link(uri);
					console.debug("Trying again with ", uri);
				}, 500);
			}
		}
		else if(compose.length > 1)
		{
			// Need to prompt
			var prompt = $j(document.createElement('ul'));
			for(var i = 0; i < compose.length; i++)
			{
				var w = window.open('',compose[i],'100x100');
				if(w.closed) continue;
				w.blur();
				var title = w.document.title || egw.lang("compose");
				$j("<li data-window = '" + compose[i] + "'>"+ title + "</li>")
					.click(function() {
						var w = egw.open_link('',$j(this).attr("data-window"),'100x100','mail');
						w.app.mail.setCompose(w.name, content);
						prompt.dialog("close");
					})
					.appendTo(prompt);
			}
			_wnd.setTimeout(function() {
				this.focus();
			}, 200);
			var _buttons = {};
			_buttons[egw.lang("cancel")] = function() {
				$j(this).dialog("close");
			};
			prompt.dialog({
				buttons: _buttons
			});
		}
	}

	return {
		/**
		 * View an EGroupware entry: opens a popup of correct size or redirects window.location to requested url
		 *
		 * Examples:
		 * - egw.open(123,'infolog') or egw.open('infolog:123') opens popup to edit or view (if no edit rights) infolog entry 123
		 * - egw.open('infolog:123','timesheet','add') opens popup to add new timesheet linked to infolog entry 123
		 * - egw.open(123,'addressbook','view') opens addressbook view for entry 123 (showing linked infologs)
		 * - egw.open('','addressbook','view_list',{ search: 'Becker' }) opens list of addresses containing 'Becker'
		 *
		 * @param {string}|int|object id_data either just the id or if app=="" "app:id" or object with all data
		 * 	to be able to open files you need to give: (mine-)type, path or id, app2 and id2 (path=/apps/app2/id2/id"
		 * @param {string} app app-name or empty (app is part of id)
		 * @param {string} type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
		 * @param {object|string} extra extra url parameters to append as object or string
		 * @param {string} target target of window to open
		 * @param {string} target_app target application to open in that tab
		 * @param {boolean} _check_popup_blocker TRUE check if browser pop-up blocker is on/off, FALSE no check
		 * - This option only makes sense to be enabled when the open_link requested without user interaction
		 * @memberOf egw
		 */
		open: function(id_data, app, type, extra, target, target_app, _check_popup_blocker)
		{
			// Log for debugging purposes - special log tag 'navigation' always
			// goes in user log, if user log is enabled
			egw.debug("navigation",
				"egw.open(id_data=%o, app=%s, type=%s, extra=%o, target=%s, target_app=%s)",
				id_data,app,type,extra,target,target_app
			);

			var id;
			if(typeof target === 'undefined')
			{
				target = '_blank';
			}
			if (!app)
			{
				if (typeof id_data != 'object')
				{
					var app_id = id_data.split(':',2);
					app = app_id[0];
					id = app_id[1];
				}
				else
				{
					app = id_data.app;
					id = id_data.id;
					if(typeof id_data.type != 'undefined')
					{
						type = id_data.type;
					}
				}
			}
			else if (app != 'file')
			{
				id = id_data;
				id_data = { 'id': id, 'app': app, 'extra': extra };
			}
			var url;
			var popup;
			var params;
			if (app == 'file')
			{
				url = this.mime_open(id_data);
				if (typeof url == 'object')
				{
			 		if(typeof url.mime_popup != 'undefined')
					{
						popup = url.mime_popup;
						delete url.mime_popup;
					}
			 		if(typeof url.mime_target != 'undefined')
					{
						target = url.mime_target;
						delete url.mime_target;
					}
					if (typeof url.url == 'string')
					{
						url = url.url;
					}
					else
					{
						params = url;
						url = '/index.php';
					}
				}
			}
			else
			{
				var app_registry = this.link_get_registry(app);

				if (!app || !app_registry)
				{
					alert('egw.open() app "'+app+'" NOT defined in link registry!');
					return;
				}
				if (typeof type == 'undefined') type = 'edit';
				if (type == 'edit' && typeof app_registry.edit == 'undefined') type = 'view';
				if (typeof app_registry[type] == 'undefined')
				{
					alert('egw.open() type "'+type+'" is NOT defined in link registry for app "'+app+'"!');
					return;
				}
				url = '/index.php';
				// Copy, not get a reference, or we'll change the registry
				params = jQuery.extend({},app_registry[type]);
				if (type == 'view' || type == 'edit')	// add id parameter for type view or edit
				{
					params[app_registry[type+'_id']] = id;
				}
				else if (type == 'add' && id)	// add add_app and app_id parameters, if given for add
				{
					var app_id = id.split(':',2);
					params[app_registry.add_app] = app_id[0];
					params[app_registry.add_id] = app_id[1];
				}

				if (typeof extra == 'string')
				{
					url += '?'+extra;
				}
				else if (typeof extra == 'object')
				{
					$j.extend(params, extra);
				}
				popup = app_registry[type+'_popup'];
			}
			return this.open_link(this.link(url, params), target, popup, target_app, _check_popup_blocker);
		},

		/**
		 * Open a link, which can be either a menuaction, a EGroupware relative url or a full url
		 *
		 * @param {string} _link menuaction, EGroupware relative url or a full url (incl. "mailto:" or "javascript:")
		 * @param {string} _target optional target / window name
		 * @param {string} _popup widthxheight, if a popup should be used
		 * @param {string} _target_app app-name for opener
		 * @param {boolean} _check_popup_blocker TRUE check if browser pop-up blocker is on/off, FALSE no check
		 * - This option only makes sense to be enabled when the open_link requested without user interaction
		 * @param {string} _mime_type if given, we check if any app has registered a mime-handler for that type and use it
		 */
		open_link: function(_link, _target, _popup, _target_app, _check_popup_blocker, _mime_type)
		{
			// Log for debugging purposes - don't use navigation here to avoid
			// flooding log with details already captured by egw.open()
			egw.debug("log",
				"egw.open_link(_link=%s, _target=%s, _popup=%s, _target_app=%s)",
				_link,_target,_popup,_target_app
			);
			//Check browser pop-up blocker
			if (_check_popup_blocker)
			{
				if (this._check_popupBlocker(_link, _target, _popup, _target_app)) return;
			}
			var url = _link;
			if (url.indexOf('javascript:') == 0)
			{
				eval(url.substr(11));
				return;
			}
			if (url.indexOf('mailto:') == 0)
			{
				return mailto(url);
			}
			// link is not necessary an url, it can also be a menuaction!
			if (url.indexOf('/') == -1 && url.split('.').length >= 3 &&
				!(url.indexOf('mailto:') == 0 || url.indexOf('/index.php') == 0 || url.indexOf('://') != -1))
			{
				url = "/index.php?menuaction="+url;
			}
			// append the url to the webserver url, if not already contained or empty
			if (url[0] == '/' && this.webserverUrl && this.webserverUrl != '/' && url.indexOf(this.webserverUrl+'/') != 0)
			{
				url = this.webserverUrl + url;
			}
			var mime_info = _mime_type ? this.get_mime_info(_mime_type) : undefined;
			if (mime_info && (mime_info.mime_url || mime_info.mime_data))
			{
				var data = {};
				for(var attr in mime_info)
				{
					switch(attr)
					{
						case 'mime_popup':
							_popup = mime_info.mime_popup;
							break;
						case 'mime_target':
							_target = mime_info.mime_target;
							break;
						case 'mime_type':
							data[mime_info.mime_type] = _mime_type;
							break;
						case 'mime_data':
							data[mime_info[attr]] = _link;
							break;
						case 'mime_url':
							data[mime_info[attr]] = url;
							break;
						default:
							data[attr] = mime_info[attr];
							break;
					}
				}
				url = egw.link('/index.php', data);
			}
			else if (mime_info)
			{
				if (mime_info.mime_popup) _popup = mime_info.mime_popup;
				if (mime_info.mime_target) _target = mime_info.mime_target;
			}
			if (_popup)
			{
				var w_h = _popup.split('x');
				var popup_window = this.openPopup(url, w_h[0], w_h[1], _target || '_blank', _target_app, true);

				// Remember which windows are open
				egw().storeWindow(_target_app, popup_window);

				return popup_window;
			}
			else if ((typeof _target == 'undefined' || _target == '_self' || typeof this.link_app_list()[_target] != "undefined"))
			{
				if(_target == '_self')
				{
					// '_self' isn't allowed, but we can handle it
					_target = undefined;
				}
				// Use framework's link handler, if present
				return this.link_handler(url,_target);
			}
			else
			{
				return _wnd.open(url, _target);
			}
		},

		/**
		 * Open a (centered) popup window with given size and url
		 *
		 * @param {string} _url
		 * @param {number} _width
		 * @param {number} _height
		 * @param {string} _windowName or "_blank"
		 * @param {string|boolean} _app app-name for framework to set correct opener or false for current app
		 * @param {boolean} _returnID true: return window, false: return undefined
		 * @param {string} _status "yes" or "no" to display status bar of popup
		 * @param {boolean} _skip_framework
		 * @returns {DOMWindow|undefined}
		 */
		openPopup: function(_url, _width, _height, _windowName, _app, _returnID, _status, _skip_framework)
		{
			// Log for debugging purposes
			egw.debug("navigation", "openPopup(%s, %s, %s, %o, %s, %s)",_url,_windowName,_width,_height,_status,_app);

			if (_height == 'availHeight') _height = this.availHeight();

			// if we have a framework and we use mobile template --> let framework deal with opening popups
			if (!_skip_framework && _wnd.framework)
			{
				return _wnd.framework.openPopup(_url, _width, _height, _windowName, _app, _returnID, _status, _wnd);
			}

			if (typeof(_app) == 'undefined') _app = false;
			if (typeof(_returnID) == 'undefined') _returnID = false;

			var $wnd = jQuery(_wnd.top);
			var positionLeft = ($wnd.outerWidth()/2)-(_width/2)+_wnd.screenX;
			var positionTop  = ($wnd.outerHeight()/2)-(_height/2)+_wnd.screenY;

			// IE fails, if name contains eg. a dash (-)
			if (navigator.userAgent.match(/msie/i)) _windowName = !_windowName ? '_blank' : _windowName.replace(/[^a-zA-Z0-9_]+/,'');

			var windowID = _wnd.open(_url, _windowName || '_blank', "width=" + _width + ",height=" + _height +
				",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
				",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);

			// inject egw object
			if (windowID) windowID.egw = _wnd.egw;

			// returning something, replaces whole window in FF, if used in link as "javascript:egw_openWindowCentered2()"
			if (_returnID !== false) return windowID;
		},

		/**
		 * Get available height of screen
		 */
		availHeight: function()
		{
			return screen.availHeight < screen.height ? screen.availHeight : screen.height - 100;
		},

		/**
		 * Use frameworks (framed template) link handler to open a url
		 *
		 * @param {string} _url
		 * @param {string} _target
		 */
		link_handler: function(_url, _target)
		{
			// if url is supposed to open in admin, use admins loader to open it in it's own iframe
			// (otherwise there's no tree and sidebox!)
			if (_target === 'admin' && !_url.match(/menuaction=admin\.admin_ui\.index/))
			{
				_url = _url.replace(/menuaction=([^&]+)/, 'menuaction=admin.admin_ui.index&load=$1');
			}
			if (_wnd.framework)
			{
				_wnd.framework.linkHandler(_url, _target);
			}
			else
			{
				_wnd.location.href = _url;
			}
		},

		/**
		 * Close current window / popup
		 */
		close: function()
		{
			if (_wnd.framework && typeof _wnd.framework.popup_close == "function")
			{
				_wnd.framework.popup_close(_wnd);
			}
			else
			{
				_wnd.close();
			}
		},

		/**
		 * Check if browser pop-up blocker is on/off
		 *
		 * @param {string} _link menuaction, EGroupware relative url or a full url (incl. "mailto:" or "javascript:")
		 * @param {string} _target optional target / window name
		 * @param {string} _popup widthxheight, if a popup should be used
		 * @param {string} _target_app app-name for opener
		 *
		 * @return boolean returns false if pop-up blocker is off
		 * - returns true if pop-up blocker is on,
		 * - and re-call the open_link with provided parameters, after user interaction.
		 */
		_check_popupBlocker: function(_link, _target, _popup, _target_app)
		{
			var popup = window.open("","",'top='+(screen.height/2)+',left='+(screen.width/2)+',width=1,height=1,menubar=no,resizable=yes,scrollbars=yes,status=no,toolbar=no,dependent=yes');

			if (!popup||popup == 'undefined'||popup == null)
			{
				et2_dialog.show_dialog(function(){
					window.egw.open_link(_link, _target, _popup, _target_app);
				},egw.lang("The browser popup blocker is on. Please click on OK button to see the pop-up.\n\nIf you would like to not see this message for the next time, allow your browser pop-up blocker to open popups from %1",window.location.hostname) ,
				"Popup Blocker Warning",{},et2_dialog.BUTTONS_OK,et2_dialog.WARNING_MESSAGE);
				return true;
			}
			else
			{
				popup.close();
				return false;
			}
		}
	};
});


// Add protocol handler as an option if mail handling is not forced so mail can handle mailto:
/* Not working consistantly yet
$j(function() {
try {
	if(egw.user('apps').mail && (egw.preference('force_mailto','addressbook')||true) != '0')
	{
		var params = egw.link_get_registry('mail','add');
		if(params)
		{
			params['preset[mailto]'] = ''; // %s, but egw.link will encode it
			navigator.registerProtocolHandler("mailto",egw.link('/index.php', params)+'%s', egw.lang('mail'));
		}
	}
} catch (e) {}
});
*/
