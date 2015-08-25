/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

"use strict";

/*egw:uses
	egw_core;
*/

/**
 * Log debug messages to browser console and persistent html5 localStorage
 *
 * localStorage is limited by a clientside quota, so we need to deal with
 * situation that storing something in localStorage will throw an exception!
 *
 * @param {string} _app
 * @param {object} _wnd
 */
egw.extend('debug', egw.MODULE_GLOBAL, function(_app, _wnd) {

	/**
	 * DEBUGLEVEL specifies which messages are printed to the console.
	 * Decrease the value of EGW_DEBUGLEVEL to get less messages.
	 *
	 * @type Number
	 * 0 = off, no logging
	 * 1 = only "error"
	 * 2 = -- " -- plus "warning"
	 * 3 = -- " -- plus "info"
	 * 4 = -- " -- plus "log"
	 * 5 = -- " -- plus a stacktrace
	 */
	var DEBUGLEVEL = 3;

	/**
	 * Log-level for local storage and error-display in GUI
	 *
	 * @type Number
	 * 0 = off, no logging AND no global error-handler bound
	 * 1 = ... see above
	 */
	var LOCAL_LOG_LEVEL = 0;
	/**
	 * Number of log-entries stored on client, new errors overwrite old ones
	 *
	 * @type Number
	 */
	var MAX_LOGS = 200;
	/**
	 * Number of last old log entry = next one to overwrite
	 *
	 * @type String
	 */
	var LASTLOG = 'lastLog';
	/**
	 * Prefix for key of log-message, message number gets appended to it
	 *
	 * @type String
	 */
	var LOG_PREFIX = 'log_';

	/**
	 * Log to clientside html5 localStorage
	 *
	 * @param {String} _level "navigation", "log", "info", "warn", "error"
	 * @param {Array} _args arguments to egw.debug
	 * @param {string} _stack
	 * @returns {Boolean} false if localStorage is NOT supported, null if level requires no logging, true if logged
	 */
	function log_on_client(_level, _args, _stack)
	{
		if (!window.localStorage) return false;

		switch(_level)
		{
			case 'warn':
				if (LOCAL_LOG_LEVEL < 2) return null;
			case 'info':
				if (LOCAL_LOG_LEVEL < 3) return null;
			case 'log':
				if (LOCAL_LOG_LEVEL < 4) return null;
			default:
				if (!LOCAL_LOG_LEVEL) return null;
		}
		var data = {
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
		if (typeof window.localStorage[LASTLOG] == 'undefined')
		{
			window.localStorage[LASTLOG] = 0;
		}
		// check if MAX_LOGS changed in code --> clear whole log
		if (window.localStorage[LASTLOG] > MAX_LOGS)
		{
			clear_client_log();
		}
		try {
			window.localStorage[LOG_PREFIX+window.localStorage[LASTLOG]] = JSON.stringify(data);
			window.localStorage[LASTLOG] = (1 + parseInt(window.localStorage[LASTLOG])) % MAX_LOGS;
		}
		catch(e) {
			switch (e.name)
			{
				case 'QuotaExceededError':	// storage quota is exceeded --> delete whole log
				case 'NS_ERROR_DOM_QUOTA_REACHED':	// FF-name
					clear_client_log();
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
							if (data.args[i] instanceof Class)
							{
								data.args[i] = jQuery.extend({}, data.args[i]);
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
				window.localStorage[LOG_PREFIX+window.localStorage[LASTLOG]] = JSON.stringify(data);
				window.localStorage[LASTLOG] = (1 + parseInt(window.localStorage[LASTLOG])) % MAX_LOGS;
			}
			catch(e) {
				// ignore error, if eg. localStorage exceeds quota on client
			}
		}
	}

	/**
	 * Get log from localStorage with oldest message first
	 *
	 * @returns {Array} of Object with values for attributes level, message, trace
	 */
	function get_client_log()
	{
		var logs = [];

		if (window.localStorage && typeof window.localStorage[LASTLOG] != 'undefined')
		{
			var lastlog = parseInt(window.localStorage[LASTLOG]);
			for(var i=lastlog; i < lastlog+MAX_LOGS; ++i)
			{
				var log = window.localStorage[LOG_PREFIX+(i%MAX_LOGS)];
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
	function clear_client_log()
	{
		// Remove indicator icon
		jQuery('#topmenu_info_error').remove();

		if (!window.localStorage) return false;

		var max = MAX_LOGS;
		// check if we have more log entries then allowed, happens if MAX_LOGS get changed in code
		if (window.localStorage[LASTLOG] > MAX_LOGS)
		{
			max = 1000;	// this should NOT be changed, if MAX_LOGS get's smaller!
		}
		for(var i=0; i < max; ++i)
		{
			if (typeof window.localStorage[LOG_PREFIX+i] != 'undefined')
			{
				delete window.localStorage[LOG_PREFIX+i];
			}
		}
		delete window.localStorage[LASTLOG];

		return true;
	}

	/**
	 * Format one log message for display
	 *
	 * @param {Object} log {{level: string, time: number, stack: string, args: array[]}} Log information
	 *	Actual message is in args[0]
	 * @returns {DOMNode}
	 */
	function format_message(log)
	{
		var row = document.createElement('tr');
		row.setAttribute('class', log.level);
		var timestamp = row.insertCell(-1);
		timestamp.appendChild(document.createTextNode(new Date(log.time)));
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
	function raise_error()
	{
		var icon = jQuery('#topmenu_info_error');
		if (!icon.length)
		{
			var icon = jQuery(egw(_wnd).image_element(egw.image('dialog_error')));
			icon.addClass('topmenu_info_item').attr('id', 'topmenu_info_error');
			// ToDo: tooltip
			icon.on('click', egw(_wnd).show_log);
			jQuery('#egw_fw_topmenu_info_items,#topmenu_info').append(icon);
		}
	}

	// bind to global error handler, only if LOCAL_LOG_LEVEL > 0
	if (LOCAL_LOG_LEVEL)
	{
		jQuery(_wnd).on('error', function(e)
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
			log_on_client('error', [event.message], typeof event.stack != 'undefined' ? event.stack : null);
			raise_error();
			// rethrow error to let browser log and show it in usual way too
			if (typeof event.error == 'object')
			{
				throw event.error;
			}
			throw event.message;
		});
	}

	/**
	 * The debug function can be used to send a debug message to the
	 * java script console. The first parameter specifies the debug
	 * level, all other parameters are passed to the corresponding
	 * console function.
	 */
	return {
		debug_level: function() {
			return DEBUGLEVEL;
		},
		debug: function(_level) {
			if (typeof _wnd.console != "undefined")
			{
				// Get the passed parameters and remove the first entry
				var args = [];
				for (var i = 1; i < arguments.length; i++)
				{
					args.push(arguments[i]);
				}

				// Add in a trace
				if (DEBUGLEVEL >= 5 && typeof (new Error).stack != "undefined")
				{
					var stack = (new Error).stack;
					args.push(stack);
				}

				if (_level == "log" && DEBUGLEVEL >= 4 &&
					typeof _wnd.console.log == "function")
				{
					_wnd.console.log.apply(_wnd.console, args);
				}

				if (_level == "info" && DEBUGLEVEL >= 3 &&
					typeof _wnd.console.info == "function")
				{
					_wnd.console.info.apply(_wnd.console, args);
				}

				if (_level == "warn" && DEBUGLEVEL >= 2 &&
					typeof _wnd.console.warn == "function")
				{
					_wnd.console.warn.apply(_wnd.console, args);
				}

				if (_level == "error" && DEBUGLEVEL >= 1 &&
					typeof _wnd.console.error == "function")
				{
					_wnd.console.error.apply(_wnd.console, args);
				}
			}
			// raise errors to user, if LOCAL_LOG_LEVEL > 0
			if (LOCAL_LOG_LEVEL && _level == "error") raise_error(args);

			// log to html5 localStorage
			if (typeof stack != 'undefined') args.pop();	// remove stacktrace again
			log_on_client(_level, args);
		},

		/**
		 * Display log to user because he clicked on icon showed by raise_error
		 *
		 * @returns {undefined}
		 */
		show_log: function()
		{
			var table = document.createElement('table');
			var body = document.createElement('tbody');
			var client_log = get_client_log();
			for(var i = 0; i < client_log.length; i++)
			{
				body.appendChild(format_message(client_log[i]));
			}
			table.appendChild(body);

			// Use a wrapper div for ease of styling
			var wrapper = document.createElement('div');
			wrapper.setAttribute('class', 'client_error_log');
			wrapper.appendChild(table);

			if(window.jQuery && window.jQuery.ui.dialog)
			{
				var $wrapper = $j(wrapper);
				// Start hidden
				$j('tr',$wrapper).addClass('hidden')
					.on('click', function() {
						$j(this).toggleClass('hidden',{});
						$j(this).find('.stack').children().toggleClass('ui-icon ui-icon-circle-plus');
					});
				// Wrap in div so we can control height
				$j('td',$wrapper).wrapInner('<div/>')
					.filter('.stack').children().addClass('ui-icon ui-icon-circle-plus');

				$wrapper.dialog({
					title: egw.lang('Error log'),
					buttons: [
						{text: egw.lang('OK'), click: function() {$j(this).dialog( "close" ); }},
						{text: egw.lang('clear'), click: function() {clear_client_log(); $j(this).empty();}}
					],
					width: 800,
					height: 400
				});
				$wrapper[0].scrollTop = $wrapper[0].scrollHeight;
			}
			if (_wnd.console) _wnd.console.log(get_client_log());
		}
	};
});

