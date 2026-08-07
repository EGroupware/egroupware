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

/*egw:uses
	egw_core;
	egw_files;
	egw_ready;
*/
import './egw_core';

export interface LangModule
{
	/**
	 * Set translation for a given application
	 *
	 * @param _app
	 * @param _messages message => translation pairs
	 * @param _need_clone _messages need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_lang_arr(_app : string, _messages : object, _need_clone? : true) : void;

	/**
	 * Translate a given phrase replacing optional placeholders
	 *
	 * @param _msg message to translate
	 * @param _args optional parameters (%{number} replacements)
	 */
	lang(_msg : string, ..._args : string[] | number[]) : string;

	/**
	 * Load default langfiles for an application: common, _appname, custom
	 *
	 * @param _window
	 * @param _appname name of application to load translations for
	 * @param _callback
	 * @param _context
	 */
	langRequireApp(_window : Window, _appname : string, _callback? : Function, _context? : object) : void;

	/**
	 * Includes the language files for the given applications -- if those
	 * do not already exist, include them.
	 *
	 * @param _window is the window which needs the language -- this is
	 * 	needed as the "ready" event has to be postponed in that window until
	 * 	all lang files are included.
	 * @param _apps is an array containing the applications for which the
	 * 	data is needed as objects of the following form:
	 * 		{
	 * 			app: <APPLICATION NAME>,
	 * 			lang: <LANGUAGE CODE>
	 * 		}
	 * @param _callback called after loading, if not given ready event will be postponed instead
	 * @param _context for callback
	 * @return Promise
	 */
	langRequire(_window : Window, _apps : {app : string, lang : string}[], _callback? : Function, _context? : object) : any;
}

declare global
{
	interface IegwGlobal extends LangModule
	{
	}
}

/**
 * @augments Class
 */
egw.extend('lang', egw.MODULE_GLOBAL, function() : LangModule
{
	"use strict";

	/**
	 * Translations
	 *
	 * @access: private, use egw.lang() or egw.set_lang_arr()
	 */
	const lang_arr : {[app : string] : {[message : string] : string}} = {};

	// Return the actual extension
	return {
		/**
		 * Set translation for a given application
		 *
		 * @param {string} _app
		 * @param {object} _messages message => translation pairs
		 * @param {boolean} _need_clone _messages need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 * @memberOf egw
		 */
		set_lang_arr: function(_app : string, _messages : object, _need_clone? : boolean) : void
		{
			if(!(<any>jQuery).isArray(_messages))
			{
				// no deep clone jQuery.extend(true,...) neccessary, as _messages contains only string values
				lang_arr[_app] = _need_clone ? (<any>jQuery).extend({}, _messages) : <any>_messages;
			}
		},

		/**
		 * Translate a given phrase replacing optional placeholders
		 *
		 * @param {string} _msg message to translate
		 * @param {...string} _arg1 ... _argN
		 * @return {string}
		 */
		lang: function(this : any, _msg : string, _arg1? : any) : string
		{
			if(_msg === null || _msg === undefined)
			{
				return '';
			}
			if(typeof _msg !== "string" && _msg)
			{
				egw().debug("warn", "Cannot translate an object", _msg);
				return _msg;
			}
			var translation = _msg;
			_msg = _msg.toLowerCase();

			// search apps in given order for a replacement
			var apps : string[] = this.lang_order || ['custom', this.getAppName(), 'etemplate', 'common', 'notifications'];
			for(var i = 0; i < apps.length; ++i)
			{
				if (typeof lang_arr[apps[i]] != "undefined" &&
					typeof lang_arr[apps[i]][_msg] != 'undefined')
				{
					translation = lang_arr[apps[i]][_msg];
					break;
				}
			}
			if (arguments.length == 1) return translation;

			if (arguments.length == 2) return translation.replace('%1', arguments[1]);

			// to cope with arguments containing '%2' (eg. an urlencoded path like a referer),
			// we first replace all placeholders '%N' with '|%N|' and then we replace all '|%N|' with arguments[N]
			translation = translation.replace(/%([0-9]+)/g, '|%$1|');
			for(var i = 1; i < arguments.length; ++i)
			{
				translation = translation.replace('|%'+i+'|', arguments[i]);
			}
			return translation;
		},

		/**
		 * Load default langfiles for an application: common, _appname, custom
		 *
		 * @param {DOMElement} _window
		 * @param {string} _appname name of application to load translations for
		 * @param {function} _callback
		 * @param {object} _context
		 */
		langRequireApp: function(this : any, _window : Window, _appname : string, _callback? : Function, _context? : object) : void
		{
			var lang = egw.preference('lang');
			var langs = [{app: 'common', lang: lang}];

			if (_appname && _appname != 'eGroupWare')
			{
				langs.push({app: _appname, lang: lang});
			}
			langs.push({app: 'custom', lang: 'en'});

			this.langRequire(_window, langs, _callback, _context);
		},

		/**
		 * Includes the language files for the given applications -- if those
		 * do not already exist, include them.
		 *
		 * @param {DOMElement} _window is the window which needs the language -- this is
		 * 	needed as the "ready" event has to be postponed in that window until
		 * 	all lang files are included.
		 * @param {array} _apps is an array containing the applications for which the
		 * 	data is needed as objects of the following form:
		 * 		{
		 * 			app: <APPLICATION NAME>,
		 * 			lang: <LANGUAGE CODE>
		 * 		}
		 * @param {function} _callback called after loading, if not given ready event will be postponed instead
		 * @param {object} _context for callback
		 * @return Promise
		 */
		langRequire: function(this : any, _window : Window, _apps : {app : string, lang : string, etag? : string}[], _callback? : Function, _context? : object)
		{
			// Get the ready and the files module for the given window
			var ready = this.module("ready", _window);
			var files = this.module("files", this.window);

			// Build the file names which should be included
			var jss : string[] = [];
			var apps : string[] = [];
			for (var i = 0; i < _apps.length; i++)
			{
				if (!_apps[i].app) continue;
				if (typeof lang_arr[_apps[i].app] === "undefined")
				{
					jss.push(this.webserverUrl +
						'/api/lang.php?app=' + _apps[i].app +
						'&lang=' + _apps[i].lang +
						'&etag=' + (_apps[i].etag || this.config('max_lang_time')));
				}
				apps.push(_apps[i].app);
			}
			if (this !== egw && apps.length > 0)
			{
				this.lang_order = apps.reverse();
			}

			const promise = Promise.all(jss.map((src) => import(src)));
			return typeof _callback === 'function' ? promise.then(() => _callback.call(_context)) : promise;
		}
	};

});
