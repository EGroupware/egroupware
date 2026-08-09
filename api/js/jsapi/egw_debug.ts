/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core';

export interface DebugModule
{
	/**
	 * Return current log-level
	 */
	debug_level() : number;

	/**
	 * The debug function can be used to send a debug message to the
	 * java script console. The first parameter specifies the debug
	 * level, all other parameters are passed to the corresponding
	 * console function.
	 *
	 * @param _level "navigation", "log", "info", "warn", "error"
	 * @param args arguments to egw.debug
	 */
	debug(_level : "navigation"|"log"|"info"|"warn"|"error", ...args : any[]) : void;

	/**
	 * Display log to user because he clicked on icon showed by raise_error
	 */
	show_log() : void;
}

declare global
{
	interface IegwGlobal extends DebugModule
	{
	}
}

/**
 * Log debug messages to browser console and persistent html5 localStorage
 *
 * localStorage is limited by a clientside quota, so we need to deal with
 * situation that storing something in localStorage will throw an exception!
 */
class Debug implements DebugModule
{
	/**
	 * DEBUGLEVEL specifies which messages are printed to the console.
	 * Decrease the value of EGW_DEBUGLEVEL to get less messages.
	 *
	 * 0 = off, no logging
	 * 1 = only "error"
	 * 2 = -- " -- plus "warning"
	 * 3 = -- " -- plus "info"
	 * 4 = -- " -- plus "log"
	 * 5 = -- " -- plus a stacktrace
	 */
	private DEBUGLEVEL = 3;

	/**
	 * Log-level for local storage and error-display in GUI
	 *
	 * 0 = off, no logging AND no global error-handler bound
	 * 1 = ... see above
	 */
	private LOCAL_LOG_LEVEL = 0;

	/**
	 * Number of log-entries stored on client, new errors overwrite old ones
	 */
	private MAX_LOGS = 200;

	/**
	 * Number of last old log entry = next one to overwrite
	 */
	private LASTLOG = 'lastLog';

	/**
	 * Prefix for key of log-message, message number gets appended to it
	 */
	private LOG_PREFIX = 'log_';

	constructor(private _wnd : Window)
	{
		// bind to global error handler, only if LOCAL_LOG_LEVEL > 0
		if (this.LOCAL_LOG_LEVEL)
		{
			(<any>jQuery)(_wnd).on('error', (e : any) =>
			{
				// originalEvent does NOT always exist in IE
				var event = typeof e.originalEvent == 'object' ? e.originalEvent : e;
				// IE(11) gives a syntaxerror on each pageload pointing to first line of html page (doctype).
				// As I cant figure out what's wrong there, we are ignoring it for now.
				if (navigator.userAgent.match(/Trident/i) && typeof event.name == 'undefined' &&
					Object.prototype.toString.call(event) == '[object ErrorEvent]' &&
					event.lineno == 1 && event.filename.indexOf('/index.php') != -1)
				{
					return false;
				}
				this.log_on_client('error', [event.message], typeof event.stack != 'undefined' ? event.stack : null);
				this.raise_error();
				// rethrow error to let browser log and show it in usual way too
				if (typeof event.error == 'object')
				{
					throw event.error;
				}
				throw event.message;
			});
		}
	}

	/**
	 * Log to clientside html5 localStorage
	 *
	 * @param _level "navigation", "log", "info", "warn", "error"
	 * @param _args arguments to egw.debug
	 * @returns false if localStorage is NOT supported, null if level requires no logging, true if logged
	 */
	private log_on_client(_level : string, _args : any[], _stack? : string) : boolean
	{
		if (!window.localStorage) return false;

		switch(_level)
		{
			case 'warn':
				if (this.LOCAL_LOG_LEVEL < 2) return null;
			case 'info':
				if (this.LOCAL_LOG_LEVEL < 3) return null;
			case 'log':
				if (this.LOCAL_LOG_LEVEL < 4) return null;
			default:
				if (!this.LOCAL_LOG_LEVEL) return null;
		}
		var data : any = {
			time: (new Date()).getTime(),
			level: _level,
			args: _args
		};
		// Add in a trace, if no navigation _level
		if (_level != 'navigation')
		{
			if (_stack)
			{
				data.stack = _stack;
			}
			else
			{
				// IE needs to throw the error to get a stack trace!
				try {
					throw new Error;
				}
				catch(error) {
					data.stack = error.stack;
				}
			}
		}
		if (typeof window.localStorage[this.LASTLOG] == 'undefined')
		{
			window.localStorage[this.LASTLOG] = <any>0;
		}
		// check if MAX_LOGS changed in code --> clear whole log
		if (<any>window.localStorage[this.LASTLOG] > this.MAX_LOGS)
		{
			this.clear_client_log();
		}
		try {
			window.localStorage[this.LOG_PREFIX+window.localStorage[this.LASTLOG]] = JSON.stringify(data);
			window.localStorage[this.LASTLOG] = <any>((1 + parseInt(window.localStorage[this.LASTLOG])) % this.MAX_LOGS);
		}
		catch(e) {
			switch (e.name)
			{
				case 'QuotaExceededError':	// storage quota is exceeded --> delete whole log
				case 'NS_ERROR_DOM_QUOTA_REACHED':	// FF-name
					this.clear_client_log();
					break;

				default:
					// one of the args is not JSON.stringify, because it contains circular references eg. an et2 widget
					for(var i=0; i < data.args.length; ++i)
					{
						try {
							JSON.stringify(data.args[i]);
						}
						catch(e) {
							// for Class we try removing _parent and _children attributes and try again to stringify
							if (data.args[i] instanceof (<any>window).Class)
							{
								data.args[i] = (<any>jQuery).extend({}, data.args[i]);
								delete data.args[i]._parent;
								delete data.args[i]._children;
								try {
									JSON.stringify(data.args[i]);
									continue;	// stringify worked --> check other arguments
								}
								catch(e) {
									// ignore error and remove whole argument
								}
							}
							// if above doesnt work, we remove the attribute
							data.args[i] = '** removed, circular reference **';
						}
					}
			}
			try {
				window.localStorage[this.LOG_PREFIX+window.localStorage[this.LASTLOG]] = JSON.stringify(data);
				window.localStorage[this.LASTLOG] = <any>((1 + parseInt(window.localStorage[this.LASTLOG])) % this.MAX_LOGS);
			}
			catch(e) {
				// ignore error, if eg. localStorage exceeds quota on client
			}
		}
	}

	/**
	 * Get log from localStorage with oldest message first
	 *
	 * @returns Array of Object with values for attributes level, message, trace
	 */
	private get_client_log() : any[]
	{
		var logs : any[] = [];

		if (window.localStorage && typeof window.localStorage[this.LASTLOG] != 'undefined')
		{
			var lastlog = parseInt(window.localStorage[this.LASTLOG]);
			for(var i=lastlog; i < lastlog+this.MAX_LOGS; ++i)
			{
				var log = window.localStorage[this.LOG_PREFIX+(i%this.MAX_LOGS)];
				if (typeof log != 'undefined')
				{
					try {
						logs.push(JSON.parse(log));
					}
					catch(e) {
						// ignore not existing log entries
					}
				}
			}
		}
		return logs;
	}

	/**
	 * Clears whole client log
	 */
	private clear_client_log() : boolean
	{
		// Remove indicator icon
		(<any>jQuery)('#topmenu_info_error').remove();

		if (!window.localStorage) return false;

		var max = this.MAX_LOGS;
		// check if we have more log entries then allowed, happens if MAX_LOGS get changed in code
		if (<any>window.localStorage[this.LASTLOG] > this.MAX_LOGS)
		{
			max = 1000;	// this should NOT be changed, if MAX_LOGS get's smaller!
		}
		for(var i=0; i < max; ++i)
		{
			if (typeof window.localStorage[this.LOG_PREFIX+i] != 'undefined')
			{
				delete window.localStorage[this.LOG_PREFIX+i];
			}
		}
		delete window.localStorage[this.LASTLOG];

		return true;
	}

	/**
	 * Format one log message for display
	 *
	 * @param log {level: string, time: number, stack: string, args: array[]} Log information
	 *	Actual message is in args[0]
	 */
	private format_message(log : any) : HTMLTableRowElement
	{
		var row = document.createElement('tr');
		row.setAttribute('class', log.level);
		var timestamp = row.insertCell(-1);
		timestamp.appendChild(document.createTextNode(<any>(new Date(log.time))));
		timestamp.setAttribute('class', 'timestamp');

		var level = row.insertCell(-1);
		level.appendChild(document.createTextNode(log.level));
		level.setAttribute('class', 'level');

		var message = row.insertCell(-1);
		for(var i = 0; i < log.args.length; i++)
		{

			var arg = document.createElement('p');
			arg.appendChild(
				document.createTextNode(typeof log.args[i] == 'string' ? log.args[i] : JSON.stringify( log.args[i]))
			);
			message.appendChild(arg);
		}

		var stack = row.insertCell(-1);
		stack.appendChild(document.createTextNode(log.stack||''));
		stack.setAttribute('class','stack');

		return row;
	}

	/**
	 * Show user an error happend by displaying a clickable icon with tooltip of current error
	 */
	private raise_error() : void
	{
		var icon : any = (<any>jQuery)('#topmenu_info_error');
		if (!icon.length)
		{
			var icon = (<any>jQuery)(egw(this._wnd).image_element(egw.image('dialog_error')));
			icon.addClass('topmenu_info_item').attr('id', 'topmenu_info_error');
			// ToDo: tooltip
			icon.on('click', egw(this._wnd).show_log);
			(<any>jQuery)('#egw_fw_topmenu_info_items,#topmenu_info').append(icon);
		}
	}

	debug_level = () : number =>
	{
		return this.DEBUGLEVEL;
	}

	/**
	 * The debug function can be used to send a debug message to the
	 * java script console. The first parameter specifies the debug
	 * level, all other parameters are passed to the corresponding
	 * console function.
	 */
	debug = (_level : "navigation"|"log"|"info"|"warn"|"error", ...args : any[]) : void =>
	{
		if (typeof (<any>this._wnd).console != "undefined")
		{
			// Add in a trace
			var stack : string;
			if (this.DEBUGLEVEL >= 5 && typeof (new Error).stack != "undefined")
			{
				stack = (new Error).stack;
				args.push(stack);
			}

			if (_level == "log" && this.DEBUGLEVEL >= 4 &&
				typeof (<any>this._wnd).console.log == "function")
			{
				(<any>this._wnd).console.log.apply((<any>this._wnd).console, args);
			}

			if (_level == "info" && this.DEBUGLEVEL >= 3 &&
				typeof (<any>this._wnd).console.info == "function")
			{
				(<any>this._wnd).console.info.apply((<any>this._wnd).console, args);
			}

			if (_level == "warn" && this.DEBUGLEVEL >= 2 &&
				typeof (<any>this._wnd).console.warn == "function")
			{
				(<any>this._wnd).console.warn.apply((<any>this._wnd).console, args);
			}

			if (_level == "error" && this.DEBUGLEVEL >= 1 &&
				typeof (<any>this._wnd).console.error == "function")
			{
				(<any>this._wnd).console.error.apply((<any>this._wnd).console, args);
			}

			// remove stacktrace again, if we added one above
			if (typeof stack != 'undefined') args.pop();
		}
		// raise errors to user, if LOCAL_LOG_LEVEL > 0
		if (this.LOCAL_LOG_LEVEL && _level == "error") this.raise_error();

		// log to html5 localStorage
		this.log_on_client(_level, args);
	}

	/**
	 * Display log to user because he clicked on icon showed by raise_error
	 */
	show_log = () : void =>
	{
		var table = document.createElement('table');
		var body = document.createElement('tbody');
		var client_log = this.get_client_log();
		for(var i = 0; i < client_log.length; i++)
		{
			body.appendChild(this.format_message(client_log[i]));
		}
		table.appendChild(body);

		// Use a wrapper div for ease of styling
		var wrapper = document.createElement('div');
		wrapper.setAttribute('class', 'client_error_log');
		wrapper.appendChild(table);

		if((<any>window).jQuery && (<any>window).jQuery.ui.dialog)
		{
			// jQuery UI dialog button `click` handlers are called with `this` bound
			// to the dialog element - captured here as `self` for the class instance.
			var self = this;
			var $wrapper : any = (<any>jQuery)(wrapper);
			// Start hidden
			(<any>jQuery)('tr',$wrapper).addClass('hidden')
				.on('click', function() {
					(<any>jQuery)(this).toggleClass('hidden',{});
					(<any>jQuery)(this).find('.stack').children().toggleClass('ui-icon ui-icon-circle-plus');
				});
			// Wrap in div so we can control height
			(<any>jQuery)('td',$wrapper).wrapInner('<div/>')
				.filter('.stack').children().addClass('ui-icon ui-icon-circle-plus');

			$wrapper.dialog({
				title: egw.lang('Error log'),
				buttons: [
					{text: egw.lang('OK'), click: function() {(<any>jQuery)(this).dialog( "close" ); }},
					{text: egw.lang('clear'), click: function() {self.clear_client_log(); (<any>jQuery)(this).empty();}}
				],
				width: 800,
				height: 400
			});
			$wrapper[0].scrollTop = $wrapper[0].scrollHeight;
		}
		if ((<any>this._wnd).console) (<any>this._wnd).console.log(this.get_client_log());
	}
}

egw.extend('debug', egw.MODULE_GLOBAL, (_app : string, _wnd : Window) => new Debug(_wnd));
