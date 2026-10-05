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

/**
 * Language code the server rendered into the document itself
 *
 * langRequire() uses this for any entry handed to it without a language of its own, because
 * the user preference is not reliably readable at that point: egw.preference('lang') only
 * answers once /api/user.php has been imported, which races every other module import in
 * egw.js's bootstrap, and it answers `undefined` rather than loading anything while that is
 * still outstanding (egw_preferences.ts pre-seeds its "common" slot, so the "not loaded ->
 * query the server" path it has for every other app can never trigger for "common").
 *
 * api/lang.php only accepts a syntactically valid language, so an url built from that
 * `undefined` is answered with a plain-text error instead of a module, and importing it
 * throws. The language itself is in the markup from the first byte though -
 * Api\Framework::_get_header() renders it as both the <html lang=""> attribute
 * (kdots/head.tpl) and a <meta name="language"> (api/templates/default/head.tpl).
 *
 * @param _window window whose document to read, defaults to the one this code runs in
 * @return two-letter (or "xx-yy") language code, 'en' if the document names no valid one
 */
function documentLang(_window? : Window) : string
{
	let lang;
	try {
		const doc = _window?.document || document;
		lang = doc?.documentElement?.lang ||
			doc?.querySelector('meta[name="language"]')?.getAttribute('content');
	}
	catch (e) {
		// ignore a closed or cross-origin window, fall back to 'en' below
	}
	return lang && /^[a-z]{2}(-[a-z]{2})?$/i.test(lang) ? lang : 'en';
}

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
	 * 			lang: <LANGUAGE CODE>	// optional, defaults to the document's own language
	 * 		}
	 * @param _callback called after loading, if not given ready event will be postponed instead
	 * @param _context for callback
	 * @return Promise
	 */
	langRequire(_window : Window, _apps : {app : string, lang? : string}[], _callback? : Function, _context? : object) : any;
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
class Lang implements LangModule
{
	/**
	 * Translations
	 *
	 * @access: private, use egw.lang() or egw.set_lang_arr()
	 */
	#lang_arr : {[app : string] : {[message : string] : string}} = {};

	/**
	 * Set translation for a given application
	 *
	 * Doesn't touch anything but this module's own state, so a plain arrow
	 * field is fine here (unlike lang()/langRequire()/langRequireApp() below).
	 */
	set_lang_arr = (_app : string, _messages : object, _need_clone? : boolean) : void =>
	{
		if(!Array.isArray(_messages))
		{
			// no deep clone neccessary, as _messages contains only string values
			this.#lang_arr[_app] = _need_clone ? {..._messages} : <any>_messages;
		}
	}

	/**
	 * Translate a given phrase replacing optional placeholders
	 *
	 * Called as egw(app,wnd).lang(...) - `this` must stay dynamically bound
	 * to WHICHEVER instance called it (reads this.lang_order/this.getAppName(),
	 * both per-instance), not lexically bound to this Lang instance - hence a
	 * plain `function` field rather than an arrow field. `self` captures this
	 * Lang instance itself via closure, for reaching its own #lang_arr.
	 */
	lang = ((self : Lang) => function(this : any, _msg : string, _arg1? : any) : string
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
			if (typeof self.#lang_arr[apps[i]] != "undefined" &&
				typeof self.#lang_arr[apps[i]][_msg] != 'undefined')
			{
				translation = self.#lang_arr[apps[i]][_msg];
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
	})(this);

	/**
	 * Load default langfiles for an application: common, _appname, custom
	 *
	 * Forwards to this.langRequire(...), which itself needs the dynamic
	 * `this` - see there for why this stays a plain `function` field too.
	 */
	langRequireApp = function(this : any, _window : Window, _appname : string, _callback? : Function, _context? : object) : void
	{
		var lang = egw.preference('lang');
		var langs = [{app: 'common', lang: lang}];

		if (_appname && _appname != 'eGroupWare')
		{
			langs.push({app: _appname, lang: lang});
		}
		langs.push({app: 'custom', lang: 'en'});

		this.langRequire(_window, langs, _callback, _context);
	}

	/**
	 * Includes the language files for the given applications -- if those
	 * do not already exist, include them.
	 *
	 * Called as egw(app,wnd).langRequire(...) - `this` must stay dynamically
	 * bound to whichever instance called it (reads/writes this.window,
	 * this.webserverUrl, this.config(...), this.module(...), this.lang_order,
	 * and explicitly compares `this !== egw`), same reasoning as lang() above.
	 */
	langRequire = ((self : Lang) => function(this : any, _window : Window, _apps : {app : string, lang? : string, etag? : string}[], _callback? : Function, _context? : object)
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
			if (typeof self.#lang_arr[_apps[i].app] === "undefined")
			{
				jss.push(this.webserverUrl +
					'/api/lang.php?app=' + _apps[i].app +
					'&lang=' + (_apps[i].lang || documentLang(_window)) +
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
	})(this);
}

egw.extend('lang', egw.MODULE_GLOBAL, () => new Lang());
