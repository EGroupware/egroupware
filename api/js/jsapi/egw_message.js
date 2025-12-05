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

/*egw:uses
	egw_core;
*/
import './egw_core.js';
import './egw_json.js';	// for registerJSONPlugin

/**
 * Methods to display a success or error message and the app-header
 *
 * @augments Class
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('message', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	"use strict";

	_app;	// not used, but required by function signature
	var error_reg_exp;
	var a_href_reg = /<a href="([^"]+)">([^<]+)<\/a>/img;
	var new_line_reg = /<\/?(p|br)\s*\/?>\n?/ig;
	// keeps alive messages stored
	var alive_messages = [];
	// Register an 'error' plugin, displaying using the message system
	window.setTimeout(() =>
	{
		egw(_wnd).registerJSONPlugin(function (type, res, req)
		{
			if (typeof res.data == 'string')
			{
				egw.message(res.data, 'error');
				return true;
			}
			throw 'Invalid parameters';
		}, null, 'error');
	}, 0);

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
		 * All messages, but type "success", are displayed 'til next message or user clicks on it.
		 *
		 * @param {string} _msg message to show or empty to remove previous message
		 * @param {string} _type 'help', 'info', 'error', 'warning' or 'success' (default)
		 * @param {string} _discardID unique string id (appname:id) in order to register
		 * the message as discardable. If no appname given, the id will be prefixed with
		 * current app. The discardID will be stored in local storage.
		 *
		 * @return {object} returns an object containing data and methods related to the message
		 */
		message: function(_msg, _type, _discardID)
		{
			let message = null;
			// if we are NOT in a popup then call the message on top window
			if (!this.is_popup() && _wnd !== egw.top)
			{
				return egw(egw.top).message(_msg, _type);
			}
			if (egw_getFramework() && typeof egw_getFramework().message == 'function' && _msg && typeof _msg == "string" && _msg.trim())
			{
				message = framework.message(_msg, _type, null, true, _discardID, _wnd);
			}
			// Add popup message styling
			if (message && (!egw_getFramework() || !_wnd.document.body.contains(framework)))
			{
				return message.then(m =>
				{
					m.toast();
					setTimeout(() =>
					{
						_wnd.document.body.querySelector('.sl-toast-stack')?.classList.add('isPopup');
					}, 0);
				});
			}
			return message;
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
				if (_wnd.opener && _wnd.opener != _wnd && typeof _wnd.opener.top.egw == 'function')
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
				// Ignore
				return;
			}
			if (_wnd.document.querySelector('div#divAppboxHeader'))
			{
				_wnd.document.querySelector('div#divAppboxHeader').textContent = _header;
			}

			_wnd.document.title = _wnd.document.title.replace(/[.*]$/, '['+_header+']');
		},

		/**
		 * Loading prompt is for building a loading animation and show it to user
		 * while a request is under progress.
		 *
		 * @param {string} _id a unique id to be able to distinguish loading-prompts
		 * @param {boolean} _stat true to show the loading and false to remove it
		 * @param {string} _msg a message to show while loading
		 * @param {string|jQuery _node} _node DOM selector id or jquery DOM object, default is body
		 * @param {string} _mode	defines the animation mode, default mode is spinner
		 *	animation modes:
		 *		- spinner: a sphere with a spinning bar inside
		 *		- horizental: a horizental bar
		 *
		 * @returns {jquery dom object|null} returns jQuery DOM object or null in case of hiding
		 */
		loading_prompt: function(_id,_stat,_msg,_node, _mode)
		{
			var $container = '';
			var jQuery = _wnd.jQuery;

			var id = _id? 'egw-loadin-prompt_'+_id: 'egw-loading-prompt_1';
			var mode = _mode || 'spinner';
			if (_stat)
			{
				var $node = _node? jQuery(_node): jQuery('body');

				var $container = jQuery(_wnd.document.createElement('div'))
						.attr('id', id)
						.addClass('egw-loading-prompt-container ui-front');

				var $text = jQuery(_wnd.document.createElement('span'))
						.addClass('egw-loading-prompt-'+mode+'-msg')
						.text(_msg)
						.appendTo($container);
				var $animator = jQuery(_wnd.document.createElement('div'))
						.addClass('egw-loading-prompt-'+mode+'-animator')
						.appendTo($container);
				if (!_wnd.document.getElementById(id)) $container.insertBefore($node);
				return $container;
			}
			else
			{
				$container = jQuery(_wnd.document.getElementById(id));
				if ($container.length > 0) $container.remove();
				return null;
			}
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
		 * - update-in-place: update row, but do NOT move it, or refresh if uid does not exist
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
			this.debug("log", "egw_refresh(%s, %s, %s, %o, %s, %s)", _msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type, _links);

			var win = _targetapp ? _wnd.egw_appWindow(_targetapp) : _wnd;

			this.message(_msg, _msg_type);

			if(typeof _links == "undefined")
			{
				_links = [];
			}

			// notify app observers: if observer for _app itself returns false, no regular refresh will take place
			// app's own observer can replace current app_refresh functionality
			var no_regular_refresh = false;
			for(var app_obj of _wnd.egw.window.EgwApp)	// run observers in main window (eg. not iframe, which might be opener!)
			{
				if (typeof app_obj.observer == 'function' &&
					app_obj.observer(_msg, _app, _id, _type, _msg_type, _links) === false && app_obj.appname === _app)
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
			if(typeof win.etemplate2 != "undefined" && win.etemplate2.app_refresh)
			{
				var refresh_done = win.etemplate2.app_refresh(_msg, _app, _id, _type);

				// Refresh target or current app too
				if ((_targetapp || this.app_name()) != _app)
				{
					refresh_done = win.etemplate2.app_refresh(_msg, _targetapp || this.app_name()) || refresh_done;
				}
				//In case that we have etemplate2 ready but it's empty and refresh is not done
				if (refresh_done) return;
			}

			// fallback refresh by reloading window
			var href = win.location.href;

			if (typeof _replace != 'undefined')
			{
				href = href.replace(typeof _replace == 'string' ? new RegExp(_replace) : _replace, (typeof _with != 'undefined' && _with != null) ? _with : '');
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
	   },


		/**
		 * Handle a push notification about entry changes from the websocket
		 *
		 * @param  pushData
		 * @param {string} pushData.app application name
		 * @param {(string|number)} pushData.id id of entry to refresh or null
		 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
		 * - update: request just modified data from given rows.  Sorting is not considered,
		 *		so if the sort field is changed, the row will not be moved.
		 * - edit: rows changed, but sorting may be affected.  Requires full reload.
		 * - delete: just delete the given rows clientside (no server interaction neccessary)
		 * - add: requires full reload for proper sorting
		 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
		 * @param {number} pushData.account_id User that caused the notification
		 */
		push: function(pushData)
		{
			// Log for debugging purposes
			this.debug("log", "push(%o)", pushData);

			if (typeof pushData == "undefined")
			{
				this.debug('warn', "Push sent nothing");
				return;
			}

			// notify app observers
			for (var app_obj of _wnd.egw.window.EgwApp)	// run observers in main window (eg. not iframe, which might be opener!)
			{
				if (typeof app_obj.push == 'function')
				{
					app_obj.push(pushData);
				}
			}

			// call the global registered push callbacks
			this.registerPush(pushData);
		}
	};

});