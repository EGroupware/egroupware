/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core';
import './egw_json';	// for registerJSONPlugin

export interface MessageModule
{
	/**
	 * Display an error or regular message
	 *
	 * Alle messages but type "success" are displayed 'til next message or user clicks on it.
	 *
	 * @param _msg message to show or empty to remove previous message
	 * @param _type 'help', 'info', 'error', 'warning' or 'success' (default)
	 * @param _discardID unique string id (appname:id) in order to register
	 * the message as discardable. If no appname given, the id will be prefixed with
	 * current app. The discardID will be stored in local storage.
	 *
	 * @returns returns an object containing data and methods related to the message
	 */
	message(_msg : string, _type? : "help" | "info" | "error" | "warning" | "success", _discardID? : string) : any;

	/**
	 * Are we running in a popup
	 *
	 * @returns true: popup, false: main window
	 */
	is_popup() : boolean;

	/**
	 * Active app independent if we are using a framed template-set or not
	 */
	app_name() : string;

	/**
	 * Update app-header and website-title
	 *
	 * @param _header
	 * @param _app Application name, if not for the current app
	 */
	app_header(_header : string, _app? : string) : void;

	/**
	 * Loading prompt is for building a loading animation and show it to user
	 * while a request is under progress.
	 *
	 * @param _id a unique id to be able to distinguish loading-prompts
	 * @param _stat true to show the loading and false to remove it
	 * @param _msg a message to show while loading
	 * @param _node DOM selector id or jquery DOM object, default is body
	 * @param _mode	defines the animation mode, default mode is spinner
	 *	animation modes:
	 *		- spinner: a sphere with a spinning bar inside (default)
	 *		- horizental: a horizental bar
	 *
	 * @returns returns jQuery DOM object or null in case of hiding
	 */
	loading_prompt(_id : string, _stat : boolean, _msg? : string, _node? : string|JQuery|HTMLElement, _mode? : "spinner"|"horizontal") : HTMLElement|null;

	/**
	 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
	 *
	 * Default implementation here only reloads window with it's current url with an added msg=_msg attached
	 *
	 * @param _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param _app application name
	 * @param _id id of entry to refresh or null
	 * @param _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - update-in-place: update row, but do NOT move it, or refresh if uid does not exist
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param _targetapp which app's window should be refreshed, default current
	 * @param _replace regular expression to replace in url
	 * @param _with
	 * @param _msg_type 'error', 'warning' or 'success' (default)
	 * @param _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 */
	refresh(_msg : string, _app : string, _id? : string|number, _type? : "update"|"edit"|"delete"|"add"|null,
			_targetapp? : string, _replace? : string|RegExp, _with? : string, _msg_type? : "error"|"warning"|"success", _links? : object) : void;

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param pushData one or multiple push-objects
	 * @param pushData.app application name
	 * @param pushData.id id of entry to refresh or null
	 * @param pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param pushData.account_id User that caused the notification
	 */
	push(pushData : object|object[]) : void;
}

declare global
{
	interface IegwWndLocal extends MessageModule
	{
	}
}

// Dead in the original .js too (declared, never referenced) - preserved
// as-is rather than pruned, not this modernization pass's job to clean up.
let error_reg_exp;
const a_href_reg = /<a href="([^"]+)">([^<]+)<\/a>/img;
const new_line_reg = /<\/?(p|br)\s*\/?>\n?/ig;
const alive_messages = [];

/**
 * Decode html entities so they can be added via .text(_str), eg. html_entity_decode('&amp;') === '&'
 *
 * Dead code (unused in the original .js too) - left untouched, including
 * its jQuery usage, since there's no behavior to preserve or observe.
 */
function html_entity_decode(_str : string) : string
{
	return _str && _str.indexOf('&') != -1 ? (<any>jQuery)('<span>'+_str+'</span>').text() : _str;
}

/**
 * Methods to display a success or error message and the app-header
 */
class Message implements MessageModule
{
	#wnd : Window;

	constructor(_wnd : Window)
	{
		this.#wnd = _wnd;

		// Register an 'error' plugin, displaying using the message system
		window.setTimeout(() =>
		{
			egw(this.#wnd).registerJSONPlugin(function (type, res, req) : boolean
			{
				if (typeof res.data == 'string')
				{
					egw.message(res.data, 'error');
					return true;
				}
				throw 'Invalid parameters';
			}, null, 'error');
		}, 0);
	}

	/**
	 * Display an error or regular message
	 *
	 * All messages, but type "success", are displayed 'til next message or user clicks on it.
	 *
	 * Needs this instance's own #wnd plus dynamic `this.is_popup()` dispatch
	 * (to whichever egw(app,wnd) instance the caller used) - self-capture.
	 *
	 * @param _msg message to show or empty to remove previous message
	 * @param _type 'help', 'info', 'error', 'warning' or 'success' (default)
	 * @param _discardID unique string id (appname:id) in order to register
	 * the message as discardable. If no appname given, the id will be prefixed with
	 * current app. The discardID will be stored in local storage.
	 *
	 * @return returns an object containing data and methods related to the message
	 */
	message = ((self : Message) => function(this : any, _msg : string, _type? : "help" | "info" | "error" | "warning" | "success", _discardID? : string) : any
	{
		let message : any = null;
		// if we are NOT in a popup then call the message on top window
		if (!this.is_popup() && self.#wnd !== egw.top)
		{
			return egw(egw.top).message(_msg, _type);
		}
		if ((<any>window).egw_getFramework() && typeof (<any>window).egw_getFramework().message == 'function' && _msg && typeof _msg == "string" && _msg.trim())
		{
			message = (<any>window).framework.message(_msg, _type, null, true, _discardID, self.#wnd);
		}
		// Add popup message styling
		if (message && (!(<any>window).egw_getFramework() || !self.#wnd.document.body.contains((<any>window).framework)))
		{
			return message.then(m =>
			{
				m.toast();
				setTimeout(() =>
				{
					self.#wnd.document.body.querySelector('.sl-toast-stack')?.classList.add('isPopup');
				}, 0);
			});
		}
		else if (!message)
		{
			// No framework fallback
			if (!_msg)
			{
				self.#wnd.document.querySelector('egw-message')?.remove();
				return;
			}
			const alert : any = Object.assign(self.#wnd.document.createElement("egw-message"), {message: _msg, type: _type});
			alert.addEventListener("sl-hide", (e) =>
			{
				delete this._messages[(e.target).dataset.hash ?? ""];
			});
			self.#wnd.document.body.append(alert);
			message = alert.updateComplete.then(() =>
			{
				alert.toast();
				return alert;
			});
		}
		return message;
	})(this);

	/**
	 * Are we running in a popup
	 *
	 * @returns true: popup, false: main window
	 */
	is_popup = () : boolean =>
	{
		var popup = false;
		try {
			if (this.#wnd.opener && this.#wnd.opener != this.#wnd && typeof (<any>this.#wnd.opener).top.egw == 'function')
			{
				popup = true;
			}
		}
		catch(e) {
			// ignore SecurityError exception if opener is different security context / cross-origin
		}
		return popup;
	}

	/**
	 * Active app independent if we are using a framed template-set or not
	 *
	 * Needs this instance's own #wnd plus dynamic `this.is_popup()` dispatch -
	 * self-capture.
	 */
	app_name = ((self : Message) => function(this : any) : string
	{
		return !this.is_popup() && (<any>self.#wnd).framework && (<any>self.#wnd).framework.activeApp ? (<any>self.#wnd).framework.activeApp.appName : (<any>self.#wnd).egw_appName;
	})(this);

	/**
	 * Update app-header and website-title
	 *
	 * Needs this instance's own #wnd plus dynamic `this.is_popup()` dispatch -
	 * self-capture.
	 *
	 * @param _header
	 * @param _app Application name, if not for the current app
	 */
	app_header = ((self : Message) => function(this : any, _header : string, _app? : string) : void
	{
		// not for popups and only for framed templates
		if (!this.is_popup() && (<any>self.#wnd).framework && (<any>self.#wnd).framework.setWebsiteTitle)
		{
			// Ignore
			return;
		}
		if (self.#wnd.document.querySelector('div#divAppboxHeader'))
		{
			self.#wnd.document.querySelector('div#divAppboxHeader').textContent = _header;
		}

		self.#wnd.document.title = self.#wnd.document.title.replace(/\[.*\]$/, '['+_header+']');
	})(this);

	/**
	 * Loading prompt is for building a loading animation and show it to user
	 * while a request is under progress.
	 *
	 * Only needs this instance's own #wnd, no dynamic dispatch - plain arrow
	 * field.
	 *
	 * @param _id a unique id to be able to distinguish loading-prompts
	 * @param _stat true to show the loading and false to remove it
	 * @param _msg a message to show while loading
	 * @param _node DOM selector, DOM element or jQuery-wrapped element, default is body
	 * @param _mode	defines the animation mode, default mode is spinner
	 *	animation modes:
	 *		- spinner: a sphere with a spinning bar inside
	 *		- horizental: a horizental bar
	 *
	 * @returns the created container element, or null in case of hiding
	 */
	loading_prompt = (_id : string, _stat : boolean, _msg? : string, _node? : string|JQuery|HTMLElement, _mode? : string) : HTMLElement|null =>
	{
		var id = _id? 'egw-loadin-prompt_'+_id: 'egw-loading-prompt_1';
		var mode = _mode || 'spinner';
		if (_stat)
		{
			var node : HTMLElement;
			if (typeof _node === 'string') node = this.#wnd.document.querySelector(_node);
			else if (_node && (<any>_node).jquery) node = (<any>_node)[0];
			else if (_node) node = <HTMLElement>_node;
			else node = this.#wnd.document.body;

			var container = this.#wnd.document.createElement('div');
			container.id = id;
			container.classList.add('egw-loading-prompt-container', 'ui-front');

			var text = this.#wnd.document.createElement('span');
			text.classList.add('egw-loading-prompt-'+mode+'-msg');
			text.textContent = _msg;
			container.appendChild(text);

			var animator = this.#wnd.document.createElement('div');
			animator.classList.add('egw-loading-prompt-'+mode+'-animator');
			container.appendChild(animator);

			if (!this.#wnd.document.getElementById(id)) node.parentNode.insertBefore(container, node);
			return container;
		}
		else
		{
			var existing = this.#wnd.document.getElementById(id);
			if (existing) existing.remove();
			return null;
		}
	}

	/**
	 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
	 *
	 * Default implementation here only reloads window with it's current url with an added msg=_msg attached
	 *
	 * Needs this instance's own #wnd plus dynamic `this.debug(...)`/
	 * `this.message(...)`/`this.app_name(...)` dispatch - self-capture.
	 *
	 * @param _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param _app application name
	 * @param _id id of entry to refresh or null
	 * @param _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - update-in-place: update row, but do NOT move it, or refresh if uid does not exist
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param _targetapp which app's window should be refreshed, default current
	 * @param _replace regular expression to replace in url
	 * @param _with
	 * @param _msg_type 'error', 'warning' or 'success' (default)
	 * @param _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 */
	refresh = ((self : Message) => function(this : any, _msg : string, _app : string, _id? : any, _type? : string, _targetapp? : string, _replace? : any, _with? : string, _msg_type? : "error" | "warning" | "success", _links? : any) : void
	{
		// Log for debugging purposes
		this.debug("log", "egw_refresh(%s, %s, %s, %o, %s, %s)", _msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type, _links);

		var win : any = _targetapp ? (<any>self.#wnd).egw_appWindow(_targetapp) : self.#wnd;

		this.message(_msg, _msg_type);

		// Message only, no actual refresh
		if (_app == "msg-only-push-refresh")
		{
			return;
		}
		if(typeof _links == "undefined")
		{
			_links = [];
		}

		// notify app observers: if observer for _app itself returns false, no regular refresh will take place
		// app's own observer can replace current app_refresh functionality
		var no_regular_refresh = false;
		for(var app_obj of (<any>self.#wnd).egw.window.EgwApp)	// run observers in main window (eg. not iframe, which might be opener!)
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
			!(win = win.framework.refresh(_msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type)) || !win.location)
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
	})(this);

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * Needs this instance's own #wnd plus dynamic `this.debug(...)`/
	 * `this.push(...)` self-recursion/`this.registerPush(...)` dispatch (to
	 * egw_jsonq.ts's Jsonq module merged onto the same calling instance) -
	 * self-capture.
	 *
	 * @param pushData one or multiple push-objects
	 * @param pushData.app application name
	 * @param pushData.id id of entry to refresh or null
	 * @param pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param pushData.account_id User that caused the notification
	 */
	push = ((self : Message) => function(this : any, pushData : any) : void
	{
		// Log for debugging purposes
		this.debug("log", "push(%o)", pushData);

		if (typeof pushData == "undefined")
		{
			this.debug('warn', "Push sent nothing");
			return;
		}
		// multiple push-objects in one messages
		if (Array.isArray(pushData))
		{
			pushData = pushData.forEach((data) => this.push(data));
			return;
		}

		// notify app observers
		for (var app_obj of (<any>self.#wnd).egw.window.EgwApp)	// run observers in main window (eg. not iframe, which might be opener!)
		{
			if (typeof app_obj.push == 'function')
			{
				app_obj.push(pushData);
			}
		}

		// call the global registered push callbacks
		this.registerPush(pushData);
	})(this);
}

egw.extend('message', egw.MODULE_WND_LOCAL, (_app : string, _wnd : Window) => new Message(_wnd));
