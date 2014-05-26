/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

"use strict";

/*egw:uses
	egw_core;
*/

/**
 * Methods to display a success or error message and the app-header
 *
 * @augments Class
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('message', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	_app;	// not used, but required by function signature
	var message_timer;
	var error_reg_exp;
	var on_click_remove_installed = false;
	var a_href_reg = /<a href="([^"]+)">([^<]+)<\/a>/img;

	// Register an 'error' plugin, displaying using the message system
	this.registerJSONPlugin(function(type, res, req) {
		if (typeof res.data == 'string')
		{
			egw.message(res.data,'error');
			return true;
		}
		throw 'Invalid parameters';
	}, null, 'error');

	/**
	 * Decode html entities so they can be added via .text(_str), eg. html_entity_decode('&amp;') === '&'
	 *
	 * @param {string} _str
	 * @returns {string}
	 */
	function html_entity_decode(_str)
	{
		return _str && _str.indexOf('&') != -1 ? jQuery('<span>'+_str+'</span>').text() : _str;
	}

	return {
		/**
		 * Display an error or regular message
		 *
		 * Alle messages but type "success" are displayed 'til next message or user clicks on it.
		 *
		 * @param {string} _msg message to show or empty to remove previous message
		 * @param {string} _type 'help', 'info', 'error', 'warning' or 'success' (default)
		 */
		message: function(_msg, _type)
		{
			var framework = _wnd.framework;
			var jQuery = _wnd.jQuery;

			if (_msg && typeof _type == 'undefined')
			{
				if (typeof error_reg_exp == 'undefined') error_reg_exp = new RegExp('(error|'+egw.lang('error')+')', 'i');

				_type = _msg.match(error_reg_exp) ? 'error' : 'success';
			}

			// if we are NOT in a popup and have a framwork --> let it deal with it
			if (!this.is_popup() && typeof framework != 'undefined')
			{
				// currently not using framework, but top windows message
				//framework.setMessage.call(framework, _msg, _type);
				if (_wnd !== _wnd.top)
				{
					egw(_wnd.top).message(_msg, _type);
					return;
				}
			}
			// handle message display for non-framework templates, eg. idots or jerryr
			if (message_timer)
			{
				_wnd.clearTimeout(message_timer);
				message_timer = null;
			}
			var parent = jQuery('div#divAppboxHeader');
			// popup has no app-header (idots) or it is hidden by onlyPrint class (jdots) --> use body
			if (!parent.length || parent.hasClass('onlyPrint'))
			{
				parent = jQuery('body');
			}
			jQuery('div#egw_message').remove();

			if (_msg)	// empty _msg just removes pervious message
			{
				if (!on_click_remove_installed)
				{
					// install handler to remove message on click
					jQuery('body').on('click', 'div#egw_message', function(e) {
						jQuery('div#egw_message').remove();
					});
					on_click_remove_installed = true;
				}
				// replace br-tags with newlines
				_msg = _msg.replace(/<br\s?\/?>\n?/i, "\n");

				var msg_div = jQuery(_wnd.document.createElement('div'))
					.attr('id','egw_message')
					.text(_msg)
					.addClass(_type+'_message')
					.css('position', 'absolute');
				parent.prepend(msg_div);

				// replace simple a href (NO other attribute, to gard agains XSS!)
				var matches = a_href_reg.exec(_msg);
				if (matches)
				{
					var parts = _msg.split(matches[0]);
					msg_div.text(parts[0]);
					msg_div.append(jQuery(_wnd.document.createElement('a'))
						.attr('href', html_entity_decode(matches[1]))
						.text(matches[2]));
					msg_div.append(jQuery(_wnd.document.createElement('span')).text(parts[1]));
				}
				if (_type == 'success')	// clear message again after some time, if no error
				{
					message_timer = _wnd.setTimeout(function() {
						jQuery('div#egw_message').remove();
					}, 5000);
				}
			}
		},

		/**
		 * Are we running in a popup
		 *
		 * @returns {boolean} true: popup, false: main window
		 */
		is_popup: function ()
		{
			var popup = false;
			try {
				if (_wnd.opener && typeof _wnd.opener.top.egw == 'function')
				{
					popup = true;
				}
			}
			catch(e) {
				// ignore SecurityError exception if opener is different security context / cross-origin
			}
			return popup;
		},

		/**
		 * Active app independent if we are using a framed template-set or not
		 *
		 * @returns {string}
		 */
		app_name: function()
		{
			return !this.is_popup() && _wnd.framework && _wnd.framework.activeApp ? _wnd.framework.activeApp.appName : _wnd.egw_appName;
		},

		/**
		 * Update app-header and website-title
		 *
		 * @param {string} _header
		 * @param {string} _app Application name, if not for the current app
		 */
		app_header: function(_header,_app)
		{
			// not for popups and only for framed templates
			if (!this.is_popup() && _wnd.framework && _wnd.framework.setWebsiteTitle)
			{
				var app = _app || this.app_name();
				var title = _wnd.document.title.replace(/[.*]$/, '['+_header+']');

				_wnd.framework.setWebsiteTitle.call(_wnd.framework, app, title, _header);
				return;
			}
			_wnd.jQuery('div#divAppboxHeader').text(_header);

			_wnd.document.title = _wnd.document.title.replace(/[.*]$/, '['+_header+']');
		},

		/**
		 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
		 *
		 * Default implementation here only reloads window with it's current url with an added msg=_msg attached
		 *
		 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
		 * @param {string} _app application name
		 * @param {(string|number)} _id id of entry to refresh or null
		 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
		 * - update: request just modified data from given rows.  Sorting is not considered,
		 *		so if the sort field is changed, the row will not be moved.
		 * - edit: rows changed, but sorting may be affected.  Requires full reload.
		 * - delete: just delete the given rows clientside (no server interaction neccessary)
		 * - add: requires full reload for proper sorting
		 * @param {string} _targetapp which app's window should be refreshed, default current
		 * @param {(string|RegExp)} _replace regular expression to replace in url
		 * @param {string} _with
		 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
		 * @param {object|null} _links app => array of ids of linked entries
		 * or null, if not triggered on server-side, which adds that info
		 */
	   refresh: function(_msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type, _links)
	   {
			// Log for debugging purposes
			this.debug("log", "egw_refresh(%s, %s, %s, %o, %s, %s)", _msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type);

			var win = typeof _targetapp != 'undefined' ? _wnd.egw_appWindow(_targetapp) : _wnd;

			this.message(_msg, _msg_type);

			// notify app observers: if observer for _app itself returns false, no regular refresh will take place
			// app's own observer can replace current app_refresh functionality
			var no_regular_refresh = false;
			for(var app in _wnd.egw.window.app)	// run observers in main window (eg. not iframe, which might be opener!)
			{
				var app_obj = _wnd.egw.window.app[app];
				if (typeof app_obj.observer == 'function' &&
					app_obj.observer(_msg, _app, _id, _type, _msg_type, _links) === false && app === _app)
				{
					no_regular_refresh = true;
				}
			}
			if (no_regular_refresh) return;

			// if we have a framework template, let it deal with refresh, unless it returns a DOMwindow for us to refresh
			if (win.framework && win.framework.refresh &&
				!(win = win.framework.refresh(_msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type)))
			{
				return;
			}

			// if window registered an app_refresh method or overwritten app_refresh, just call it
			if(typeof win.app_refresh == "function" && typeof win.app_refresh.registered == "undefined" ||
				typeof win.app_refresh != "undefined" && win.app_refresh.registered(_app))
			{
				win.app_refresh(_msg, _app, _id, _type);
				return;
			}

			// etemplate2 specific to avoid reloading whole page
			if(typeof etemplate2 != "undefined" && etemplate2.app_refresh)
			{
				var refresh_done = etemplate2.app_refresh(_msg, _app, _id, _type);

				// Refresh target or current app too
				if ((_targetapp || this.app_name()) != _app)
				{
					refresh_done = etemplate2.app_refresh(_msg, _targetapp || this.app_name()) || refresh_done;
				}
				//In case that we have etemplate2 ready but it's empty and refresh is not done
				if (refresh_done) return;
			}

			// fallback refresh by reloading window
			var href = win.location.href;

			if (typeof _replace != 'undefined')
			{
				href = href.replace(typeof _replace == 'string' ? new RegExp(_replace) : _replace, typeof _with != 'undefined' ? _with : '');
			}

			if (href.indexOf('msg=') != -1)
			{
				href = href.replace(/msg=[^&]*/,'msg='+encodeURIComponent(_msg));
			}
			else if (_msg)
			{
				href += (href.indexOf('?') != -1 ? '&' : '?') + 'msg=' + encodeURIComponent(_msg);
			}
			//alert('egw_refresh() about to call '+href);
			win.location.href = href;
	   }
	};
});
