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

export interface FilesModule
{
	/**
	 * Load and execute javascript file(s) in order
	 *
	 * Deprecated because with egw composition happening in main window the used import statement happens in that context
	 * and NOT in the window (eg. popup or iframe) this module is instantiated for!
	 *
	 * @memberOf egw
	 * @param _jsFiles (array of) urls to include
	 * @param _callback called after JS files are loaded and executed
	 * @param _context
	 * @param _prefix prefix for _jsFiles
	 * @deprecated use es6 import statement: Promise.all([].concat(_jsFiles).map((src)=>import(_prefix+src))).then(...)
	 */
	includeJS(_jsFiles : string|string[], _callback? : Function, _context? : object, _prefix? : string) : Promise<any>;

	/**
	 * Check if file is already included and optional mark it as included if not yet included
	 *
	 * Check does NOT differenciate between file.min.js and file.js.
	 * Only .js get's recored in files for further checking, if _add_if_not set.
	 *
	 * @param _file
	 * @param _add_if_not if true mark file as included
	 * @return true if file already included, false if not
	 */
	included(_file : string, _add_if_not? : boolean) : boolean;

	/**
	 * Include a CSS file
	 *
	 * @param _cssFiles full url of file to include
	 */
	includeCSS(_cssFiles : string|string[]) : void;

	/**
	 * Show the "new updates available" notice once (main window only, not popups)
	 *
	 * Called immediately when window.egw_import() (see the Files constructor) has to skip a
	 * rebuilt file to avoid loading a 2nd, ABI-incompatible module copy, or later by the
	 * periodic build-epoch poll in egw.js.
	 */
	notifyUpdateAvailable() : void;

	/** Whether notifyUpdateAvailable() has already shown its notice in this window */
	readonly updateAvailableNotified : boolean;
}

declare global
{
	interface IegwWndLocal extends FilesModule
	{
	}
}

/**
 * Remove optional timestamp attached as query parameter, eg. /path/name.js?12345678[&other=val]
 *
 * Examples:
 *  /path/file.js --> /path/file.js
 *  /path/file.js?123456 --> /path/file.js
 *  /path/file.php?123456&param=value --> /path/file.php?param=value
 *  /path/file.php?param=value&123456 --> /path/file.php?param=value
 *
 * @param _src url
 * @return url with timestamp stripped off
 */
function removeTS(_src : string) : string
{
	return _src.replace(/[?&][0-9]+&?/, '?').replace(/\?$/, '');
}

/**
 * RegExp to extract string with comma-separated files from a bundle-url
 */
const bundle2files_regexp = /phpgwapi\/inc\/min\/\?b=[^&]+&f=([^&]+)/;

/**
 * Regexp to detect and remove .min.js extension
 */
const min_js_regexp = /\.min\.js$/;

/**
 * Return array of files-sources from bundle(s) incl. bundle-src itself
 *
 * @param _srcs all url's have to be egw releativ!
 */
function files_from_bundles(_srcs : string|string[]) : string[]
{
	var files : string[] = [];

	var srcs : string[] = typeof _srcs == 'string' ? [_srcs] : _srcs;

	for(var n=0; n < srcs.length; ++n)
	{
		var file = srcs[n];
		files.push(file.replace(min_js_regexp, '.js'));
		var contains = file.match(bundle2files_regexp);

		if (contains && contains.length > 1)
		{
			var bundle = contains[1].split(',');
			for(var i=0; i < bundle.length; ++i)
			{
				files.push(bundle[i].replace(min_js_regexp, '.js'));
			}
		}
	}
	return files;
}

/**
 * Strip of egw_url from given urls (if containing it)
 *
 * @param _urls absolute urls
 * @returns relativ urls
 */
function strip_egw_url(_urls : string[]) : string[]
{
	var egw_url = egw.webserverUrl;
	if (egw_url.charAt(egw_url.length-1) != '/') egw_url += '/';

	for(var i=0; i < _urls.length; ++i)
	{
		var file = _urls[i];
		// check if egw_url is only path and urls contains full url incl. protocol
		// --> prefix it with our protocol and host, as eg. splitting by just '/' will fail!
		var need_full_url = egw_url[0] == '/' && file.substr(0,4) == 'http' ? window.location.protocol+'//'+window.location.host : '';
		var parts = file.split(need_full_url+egw_url);
		if (parts.length > 1)
		{
			// discard protocol and host
			parts.shift();
			_urls[i] = parts.join(need_full_url+egw_url);
		}
	}
	return _urls;
}

class Files implements FilesModule
{
	/**
	 * Array which contains all currently bound in javascript and css files.
	 */
	#files : string[] = [];

	#wnd : Window;

	// urls already handed to window.egw_import() below, keyed with their cache-busting
	// timestamp stripped, so a mid-session rebuild can't load a 2nd, ABI-incompatible copy
	// of an already-resident module into this window
	#imported : {[key : string] : string} = {};
	#updateAvailableNotified = false;

	constructor(_wnd : Window)
	{
		this.#wnd = _wnd;

		// add already included scripts
		_wnd.document.querySelectorAll('script').forEach(tag =>
		{
			this.#files.push(removeTS((<HTMLScriptElement>tag).src));
		});
		// add already included css
		_wnd.document.querySelectorAll('link[type="text/css"]').forEach(tag =>
		{
			this.#files.push(removeTS((<HTMLLinkElement>tag).href));
		});
		// make urls egw-relative
		this.#files = strip_egw_url(this.#files);
		// resolve bundles and replace .min.js with .js
		this.#files = files_from_bundles(this.#files);

		// Allow egw.json to load JS into popups, in THIS window's own realm - a dynamic
		// import() must run in the importing window's realm, which is why this is assigned
		// directly on `_wnd` here (at Files construction, which happens for every window/popup
		// egw.js's bootstrap runs in) rather than left for a caller to reach via egw(wnd).files.
		//
		// De-dupes by url with any cache-busting timestamp stripped: once a file's been
		// imported here, a later "reload" of the same file (eg. an ajax_exec response whose js
		// include got a new mtime cache-buster because rollup rebuilt it while this window sat
		// open) resolves to the already-imported url instead of fetching+evaluating a 2nd copy.
		// Without this, the 2nd copy's classes never win their customElements.define() (guarded
		// in egw.js), so constructing one directly crashes with "Failed to construct
		// 'HTMLElement': Illegal constructor" the moment a Lit-based widget (Et2Widget et al)
		// is built from it. When we do skip a stale-version reload this way, let the user know
		// a page reload would pick up the new one.
		const egw_import = (url : string) : Promise<any> =>
		{
			const key = removeTS(url);
			if (typeof this.#imported[key] === 'undefined')
			{
				this.#imported[key] = url;
			}
			else if (this.#imported[key] !== url)
			{
				// someone rebuilt while this window was open - keep the already-loaded
				// version running, but let the user know a reload would pick up the new one
				this.notifyUpdateAvailable();
			}
			return import(this.#imported[key]);
		};
		// also reachable straight off window.egw_import (not just egw(wnd).files): a bare
		// egw(window) call - as used by egw.js's periodic build-epoch poll - doesn't reliably
		// have every MODULE_WND_LOCAL module merged in yet (a pre-existing quirk, independent
		// of this change - eg. the "tooltip" module has the same gap)
		(<any>egw_import).notifyUpdateAvailable = () => this.notifyUpdateAvailable();
		Object.defineProperty(egw_import, 'updateAvailableNotified', {get: () => this.#updateAvailableNotified});
		(<any>_wnd).egw_import = egw_import;
	}

	notifyUpdateAvailable = () : void =>
	{
		if (!this.#updateAvailableNotified && this.#wnd.opener == null)
		{
			this.#updateAvailableNotified = true;
			egw(this.#wnd).message(egw.lang('New updates available. Reload when convenient to get the latest updates.'), 'info');
		}
	}

	get updateAvailableNotified() : boolean
	{
		return this.#updateAvailableNotified;
	}

	/**
	 * Load and execute javascript file(s) in order
	 *
	 * Deprecated because with egw composition happening in main window the used import statement happens in that context
	 * and NOT in the window (eg. popup or iframe) this module is instantiated for!
	 *
	 * @memberOf egw
	 * @deprecated use es6 import statement: Promise.all([].concat(_jsFiles).map((src)=>import(_prefix+src))).then(...)
	 */
	includeJS = (_jsFiles : any, _callback? : Function, _context? : any, _prefix? : string) : any =>
	{
		// Also allow including a single javascript file
		if (typeof _jsFiles === 'string')
		{
			_jsFiles = [_jsFiles];
		}
		// filter out files included by script-tag via egw.js
		_jsFiles = _jsFiles.filter((src) => src.match((<any>egw).legacy_js_regexp) === null);
		let promise : any;
		if (_jsFiles.length === 1)	// running this in below case fails when loading app.js from etemplate.load()
		{
			const src = _jsFiles[0];
			promise = import(_prefix ? _prefix+src : src)
				.catch((err) => {
					console.error(src+": "+err.message);
					return Promise.reject(err.message);
				});
		}
		else
		{
			promise = Promise.all(_jsFiles.map((src) => {
				import(_prefix ? _prefix+src : src)
					.catch((err) => {
						console.error(src+": "+err.message);
						return Promise.reject(err.message);
					})
			}));
		}
		return typeof _callback === 'undefined' ? promise : promise.then(_callback.call(_context));
	}

	/**
	 * Check if file is already included and optional mark it as included if not yet included
	 *
	 * Check does NOT differenciate between file.min.js and file.js.
	 * Only .js get's recored in files for further checking, if _add_if_not set.
	 *
	 * @return true if file already included, false if not
	 */
	included = (_file : string, _add_if_not? : boolean) : boolean =>
	{
		var file = removeTS(_file).replace(min_js_regexp, '.js');
		var not_inc = this.#files.indexOf(file) == -1;

		if (not_inc && _add_if_not)
		{
			this.#files = this.#files.concat(files_from_bundles(file));
		}
		return !not_inc;
	}

	/**
	 * Include a CSS file
	 */
	includeCSS = (_cssFiles : any) : void =>
	{
		if (typeof _cssFiles == 'string') _cssFiles = [_cssFiles];
		_cssFiles = strip_egw_url(_cssFiles);

		for(var n=0; n < _cssFiles.length; ++n)
		{
			var file = _cssFiles[n];
			if (!this.included(file, true))	// check if included and marks as such if not
			{
				// Create the node which is used to include the css file
				var cssnode = this.#wnd.document.createElement('link');
				cssnode.type = "text/css";
				cssnode.rel = "stylesheet";
				cssnode.href = egw.webserverUrl+'/'+file;

				// Get the head node and append the newly created "link" nod to it
				var head = this.#wnd.document.getElementsByTagName('head')[0];
				head.appendChild(cssnode);
			}
		}
	}
}

egw.extend('files', egw.MODULE_WND_LOCAL, (_app : string, _wnd : Window) => new Files(_wnd));
