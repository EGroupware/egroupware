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

export interface UtilsModule
{
	/**
	 * Get url for ajax request
	 *
	 * @param _menuaction
	 * @return full url incl. webserver_url
	 */
	ajaxUrl(_menuaction : string) : string;

	/**
	 * Get window of element
	 *
	 * @param _elem
	 */
	elemWindow(_elem : HTMLElement) : Window;

	/**
	 * Get unique identifier
	 *
	 * @return hex encoded, per call incremented counter
	 */
	uid() : string;

	/**
	 * Decode encoded vfs special chars
	 *
	 * @param _path path to decode
	 */
	decodePath(_path : string) : string;

	/**
	 * Encode vfs special chars excluding /
	 *
	 * @param _path path to decode
	 */
	encodePath(_path : string) : string;

	/**
	 * Encode vfs special chars removing /
	 *
	 * '%' => '%25',
	 * '#' => '%23',
	 * '?' => '%3F',
	 * '/' => '',	// better remove it completly
	 *
	 * @param _comp path to decode
	 */
	encodePathComponent(_comp : string) : string;

	/**
	 * Hash a string
	 */
	hashString(string : string) : Promise<string>;

	/**
	 * Escape HTML special chars, just like PHP
	 *
	 * @param s String to encode
	 */
	htmlspecialchars(s : string) : string;

	/**
	 * If an element has display: none (or a parent like that), it has no size.
	 * Use this to get its dimensions anyway.
	 *
	 * @param element HTML element
	 * @param boolOuter Pass true to get outerWidth() / outerHeight() instead of width() / height()
	 *
	 * @author Ryan Wheale
	 * @see http://www.foliotek.com/devblog/getting-the-width-of-a-hidden-element-with-jquery-using-width/
	 */
	getHiddenDimensions(element : HTMLElement | JQuery, boolOuter? : boolean) : {w : number, h : number, top : number, left : number};

	/**
	 * Store a window's name in egw.store so we can have a list of open windows
	 *
	 * @param appname
	 * @param popup
	 */
	storeWindow(appname : string, popup : Window) : void;

	/**
	 * Get a list of the names of open popups
	 *
	 * Using the name, you can get a reference to the popup using:
	 * window.open('', name);
	 * Popups that were not given a name when they were opened are not tracked.
	 *
	 * @param appname Application that owns/opened the popup
	 * @param regex Optionally filter names by the given regular expression
	 *
	 * @returns List of window names
	 */
	getOpenWindows(appname : string, regex? : string) : string[] | {[name : string] : number};

	/**
	 * Notify egw of closing a named window, which removes it from the list
	 *
	 * @param appname
	 * @param closed Window that was closed, or its name
	 */
	windowClosed(appname : string, closed : Window | string) : void;

	/**
	 * Copy text to the clipboard
	 *
	 * @param text Actual text to copy.  Usually target_element.value
	 * @param target_element Optional, but useful for fallback copy attempts
	 * @param event Optional, but if you have an event we can try some fallback options with it
	 */
	copyTextToClipboard(text : string, target_element? : HTMLElement, event? : ClipboardEvent | Event) : Promise<undefined | boolean | void>;

	/**
	 * Get a cache object shared between all EGroupware windows
	 *
	 * @param _name unique name for the cache-object
	 */
	getCache(_name : string) : {[key : string] : any};

	/**
	 * Invalidate / delete given part of the cache
	 *
	 * @param _name unique name of cache-object
	 * @param _attr undefined: invalidate/unset whole object or just the given attribute _attr or matching RegExp _attr
	 */
	invalidateCache(_name : string, _attr? : string | RegExp) : void;

	jsonEncode(value : any) : string;
}

declare global
{
	interface IegwGlobal extends UtilsModule
	{
	}
}

/**
 * jQuery.extend(true, ...) alternative - deep-merges arguments_ into out
 *
 * Do NOT specify true as first parameter!
 *
 * For jQuery.extend(out, obj1, ...) (a SHALLOW merge) use: {...out, ...obj1, ...}
 * instead - this one is deep-merge only.
 *
 * A plain, directly-importable function (not a class method reached via
 * `this.deepExtend(...)`/`egw.deepExtend(...)`) so any module can deep-clone
 * without depending on some OTHER module being registered first - egw_links.ts
 * used to be the only place this lived, so anything wanting it had to wait on
 * the 'links' module. egw_links.ts's own public deepExtend() (kept for
 * external callers, e.g. mail/js/app.ts, Favorite.ts, that already call
 * egw.deepExtend(...)) delegates to this.
 */
export function deepExtend(out : any, ...arguments_ : any[]) : any
{
	if (!out) {
		return {};
	}

	for (const obj of arguments_) {
		if (!obj) {
			continue;
		}

		for (const [key, value] of Object.entries(obj)) {
			switch (Object.prototype.toString.call(value)) {
				case '[object Object]':
					out[key] = out[key] || {};
					out[key] = deepExtend(out[key], value);
					break;
				case '[object Array]':
					out[key] = deepExtend(new Array((<any>value).length), value);
					break;
				default:
					out[key] = value;
			}
		}
	}

	return out;
}

function json_escape_string(input : string) : string
{
	var len = input.length;
	var res = "";

	for (var i = 0; i < len; i++)
	{
		switch (input.charAt(i))
		{
			case '"':
				res += '\\"';
				break;

			case '\n':
				res += '\\n';
				break;

			case '\r':
				res += '\\r';
				break;

			case '\\':
				res += '\\\\';
				break;

			case '\/':
				res += '\\/';
				break;

			case '\b':
				res += '\\b';
				break;

			case '\f':
				res += '\\f';
				break;

			case '\t':
				res += '\\t';
				break;

			default:
				res += input.charAt(i);
		}
	}

	return res;
}

function json_encode_simple(input : any) : string | null
{
	switch (input.constructor)
	{
		case String:
			return '"' + json_escape_string(input) + '"';

		case Number:
			return input.toString();

		case Boolean:
			return input ? 'true' : 'false';

		default:
			return null;
	}
}

function json_encode(input : any) : string
{
	if (input == null || !input && input.length == 0) return 'null';

	var simple_res = json_encode_simple(input);
	if (simple_res == null)
	{
		switch (input.constructor)
		{
			case Array:
				var buf = [];
				for (var k in input)
				{
					//Filter non numeric entries
					if (!isNaN(<any>k))
						buf.push(json_encode(input[k]));
				}
				return '[' + buf.join(',') + ']';

			case Object:
				var buf = [];
				for (var k in input)
				{
					buf.push(json_encode_simple(k) + ':' + json_encode(input[k]));
				}
				return '{' + buf.join(',') + '}';

			default:
				switch(<string>typeof input)
				{
					case 'array':
						var buf = [];
						for (var k in input)
						{
							//Filter non numeric entries
							if (!isNaN(<any>k))
								buf.push(json_encode(input[k]));
						}
						return '[' + buf.join(',') + ']';

					case 'object':
						var buf = [];
						for (var k in input)
						{
							buf.push(json_encode_simple(k) + ':' + json_encode(input[k]));
						}
						return '{' + buf.join(',') + '}';

				}
				return 'null';
		}
	}
	else
	{
		return simple_res;
	}
}

/**
 * Try some deprecated ways of copying to the OS clipboard
 *
 * @param event Optional, but if you have an event we can try some things on it
 * @param target_element Element whose contents you're trying to copy
 * @param text Actual text.  Usually target_element.value.
 */
function fallbackCopyTextToClipboard(event : ClipboardEvent | Event, target_element : HTMLElement, text : string) : boolean
{
	const win : any = (<any>target_element)?.ownerDocument.defaultView ?? (<any>target_element).ownerDocument.parentWindow ?? window;

	// Cancel any no-select css
	if (target_element)
	{
		let old_select = target_element.style.userSelect;
		target_element.style.userSelect = 'all'

		let range = document.createRange();
		range.selectNode(target_element);
		win.getSelection().removeAllRanges();
		win.getSelection().addRange(range);

		target_element.style.userSelect = old_select;

		// detect we are in IE via checking setActive, since it's
		// only supported in IE, and make sure there's clipboardData object
		if (event && typeof (<any>event.target).setActive != 'undefined' && win.clipboardData)
		{
			win.clipboardData.setData('Text', target_element.textContent.trim());
		}
		if (event && (<any>event).clipboardData)
		{
			(<any>event).clipboardData.setData('text/plain', target_element.textContent.trim());
			(<any>event).clipboardData.setData('text/html', target_element.outerHTML);
		}
	}
	let textArea : HTMLTextAreaElement;
	if (!win.clipboardData)
	{

		textArea = document.createElement("textarea");
		textArea.value = text;

		// Avoid scrolling to bottom
		textArea.style.top = "0";
		textArea.style.left = "0";
		textArea.style.position = "fixed";

		win.document.body.appendChild(textArea);
		textArea.focus();
		textArea.select();
	}

	let successful = false;
	try
	{
		successful = win.document.execCommand('copy');
		const msg = successful ? 'successful' : 'unsuccessful';
		console.log('Fallback: Copying text command was ' + msg);
	}
	catch (err)
	{
		successful = false;
	}

	win.document.body.removeChild(textArea);
	return successful;
}

class Utils implements UtilsModule
{
	#uid_counter = 0;

	/**
	 * Global cache shared between all EGroupware windows
	 */
	#cache : {[name : string] : {[key : string] : any}} = {};

	constructor()
	{
		// Check whether the browser already supports encoding JSON -- if yes, use
		// its implementation, otherwise our own
		this.jsonEncode = (typeof window.JSON !== 'undefined' && typeof window.JSON.stringify !== 'undefined')
			? JSON.stringify : json_encode;
	}

	/**
	 * Get a cache object shared between all EGroupware windows
	 *
	 * @param _name unique name for the cache-object
	 */
	getCache = (_name : string) : any =>
	{
		if (typeof this.#cache[_name] === 'undefined') this.#cache[_name] = {};

		return this.#cache[_name];
	}

	/**
	 * Invalidate / delete given part of the cache
	 *
	 * @param _name unique name of cache-object
	 * @param _attr undefined: invalidate/unset whole object or just the given attribute _attr or matching RegExp _attr
	 */
	invalidateCache = (_name : string, _attr? : string | RegExp) : void =>
	{
		// string with regular expression like "/^something/i"
		if (typeof _attr === 'string' && (_attr[0] === '/', _attr.indexOf('/', 1) !== -1))
		{
			let parts = _attr.split('/');
			parts.shift();
			const flags = parts.pop();
			_attr = new RegExp(parts.join('/'), flags);
		}
		if (typeof _attr === 'undefined' || typeof this.#cache[_name] === 'undefined')
		{
			delete this.#cache[_name];
		}
		else if (typeof _attr === 'object' && _attr.constructor.name === 'RegExp')
		{
			for(const attr in this.#cache[_name])
			{
				if (attr.match(_attr)) delete this.#cache[_name][attr];
			}
		}
		else
		{
			delete this.#cache[_name][<string>_attr];
		}
	}

	/**
	 * Get url for ajax request
	 *
	 * Called as egw(app,wnd).ajaxUrl(...) - `this.webserverUrl` must
	 * dispatch through whichever instance called it, hence a plain
	 * `function` field. Touches no private state, so no `self` needed.
	 */
	ajaxUrl = function(this : any, _menuaction : string)
	{
		if(_menuaction.indexOf('menuaction=') >= 0)
		{
			return _menuaction;
		}
		return this.webserverUrl + '/json.php?menuaction=' + _menuaction;
	}

	elemWindow = (_elem : HTMLElement) : any =>
	{
		var res : any =
			(<any>_elem.ownerDocument).parentNode ||
			_elem.ownerDocument.defaultView;
		return res;
	}

	uid = () : string =>
	{
		return (this.#uid_counter++).toString(16);
	}

	/**
	 * Decode encoded vfs special chars
	 *
	 * @param _path path to decode
	 */
	decodePath = (_path : string) : string =>
	{
		try {
			return decodeURIComponent(_path);
		}
		catch(e) {
			// ignore decoding errors, as they usually only mean _path is not encoded
			egw.debug("error", "decodePath('"+_path+"'): "+e.stack);
		}
		return _path;
	}

	/**
	 * Encode vfs special chars excluding /
	 *
	 * @param _path path to decode
	 */
	encodePath = (_path : string) : string =>
	{
		var components = _path.split('/');
		for(var n=0; n < components.length; n++)
		{
			components[n] = this.encodePathComponent(components[n]);
		}
		return components.join('/');
	}

	/**
	 * Encode vfs special chars removing /
	 *
	 * '%' => '%25',
	 * '#' => '%23',
	 * '?' => '%3F',
	 * '/' => '',	// better remove it completly
	 *
	 * @param _comp path to decode
	 */
	encodePathComponent = (_comp : string) : string =>
	{
		return _comp.replace(/%/g,'%25').replace(/#/g,'%23').replace(/\?/g,'%3F').replace(/\//g,'');
	}

	/**
	 * Hash a string
	 */
	hashString = async (string : string) : Promise<string> =>
	{
		const data = (new TextEncoder()).encode(string);
		const hashBuffer = await crypto.subtle.digest('SHA-256', data);
		const hashArray = Array.from(new Uint8Array(hashBuffer));
		const hashHex = hashArray.map(byte => byte.toString(16).padStart(2, '0')).join('');
		return hashHex;
	}

	/**
	 * Escape HTML special chars, just like PHP
	 *
	 * @param s String to encode
	 */
	htmlspecialchars = (s : string) : string =>
	{
		return s.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	/**
	 * If an element has display: none (or a parent like that), it has no size.
	 * Use this to get its dimensions anyway.
	 *
	 * @param element HTML element
	 * @param boolOuter Pass true to get outerWidth() / outerHeight() instead of width() / height()
	 *
	 * @author Ryan Wheale
	 * @see http://www.foliotek.com/devblog/getting-the-width-of-a-hidden-element-with-jquery-using-width/
	 */
	getHiddenDimensions = (element : HTMLElement | JQuery, boolOuter? : boolean) =>
	{
		var $item : any = (<any>window).jQuery(element);
		var props : any = { position: "absolute", visibility: "hidden", display: "block" };
		var dim = { "w":0, "h":0 , "left":0, "top":0};
		var $hiddenParents = $item.parents().andSelf().not(":visible");

		var oldProps : any[] = [];
		$hiddenParents.each(function() {
			var old : any = {};
			if ((<any>this).styles)
			{
				for (var name in props)
				{
					old[name] = this.style[name];
				}
			}
			else if ((<any>this).computedStyleMap)
			{
				for (var name in props)
				{
					let s = (<any>this).computedStyleMap().get(name)
					if (s)
					{
						old[name] = s.value || "";
					}
				}
			}
			(<any>window).jQuery(this).show();
			oldProps.push(old);
		});

		dim.w = (boolOuter === true) ? $item.outerWidth() : $item.width();
		dim.h = (boolOuter === true) ? $item.outerHeight() : $item.height();
		dim.top = $item.offset().top;
		dim.left = $item.offset().left;

		$hiddenParents.each(function(i : number) {
			var old = oldProps[i];
			if (this.style)
			{
				for (var name in props)
				{
					this.style[name] = old[name];
				}
			}
		});
		//$.log(”w: ” + dim.w + ”, h:” + dim.h)
		return dim;
	}

	/**
	 * Store a window's name in egw.store so we can have a list of open windows
	 *
	 * Called as egw(app,wnd).storeWindow(...) - `this.appName`/
	 * `this.getSessionItem(...)`/`this.setSessionItem(...)` must dispatch
	 * through whichever instance called it, hence a plain `function` field.
	 *
	 * @param appname
	 * @param popup
	 */
	storeWindow = function(this : any, appname : string, popup : Window)
	{
		if ((<any>popup).opener && (<any>popup).opener.framework) (<any>popup).opener.framework.popups_garbage_collector();

		// Don't store if it has no name
		if(!popup.name || ['_blank'].indexOf(popup.name) >= 0)
		{
			return;
		}

		var _target_app = appname || this.appName || (<any>window).egw_appName || 'common';
		var open_windows = JSON.parse(this.getSessionItem(_target_app, 'windows')) || {};
		open_windows[popup.name] = Date.now();
		this.setSessionItem(_target_app, 'windows', JSON.stringify(open_windows));

		// We don't want to start the timer on the popup here, because this is the function that updates the timeout, so it would set a timer each time.  Timer is started in egw.js
	}

	/**
	 * Get a list of the names of open popups
	 *
	 * Using the name, you can get a reference to the popup using:
	 * window.open('', name);
	 * Popups that were not given a name when they were opened are not tracked.
	 *
	 * Called as egw(app,wnd).getOpenWindows(...) - `this.getSessionItem(...)`
	 * must dispatch through whichever instance called it, hence a plain
	 * `function` field.
	 *
	 * @param appname Application that owns/opened the popup
	 * @param regex Optionally filter names by the given regular expression
	 *
	 * @returns List of window names
	 */
	getOpenWindows = function(this : any, appname : string, regex? : string)
	{
		var open_windows = JSON.parse(this.getSessionItem(appname, 'windows')) || {};
		if(typeof regex == 'undefined')
		{
			return open_windows;
		}
		var list : string[] = [];
		var now = Date.now();
		for(var i in open_windows)
		{
			// Expire old windows (5 seconds since last update)
			if(now - open_windows[i] > 5000)
			{
				egw.windowClosed(appname,i);
				continue;
			}
			if(i.match(regex))
			{
				list.push(i);
			}
		}
		return list;
	}

	/**
	 * Notify egw of closing a named window, which removes it from the list
	 *
	 * @param appname
	 * @param closed Window that was closed, or its name
	 */
	windowClosed = (appname : string, closed : Window | string) : void =>
	{
		var closed_name = typeof closed == "string" ? closed : closed.name;
		var closed_window = typeof closed == "string" ? null : closed;
		window.setTimeout(function ()
		{
			if (closed_window != null && !closed_window.closed)
			{
				return;
			}

			var open_windows = JSON.parse(egw().getSessionItem(appname, 'windows')) || {};
			delete open_windows[closed_name];
			egw.setSessionItem(appname, 'windows', JSON.stringify(open_windows));
		}, 100);
	}

	/**
	 * Copy text to the clipboard
	 *
	 * @param text Actual text to copy.  Usually target_element.value
	 * @param target_element Optional, but useful for fallback copy attempts
	 * @param event Optional, but if you have an event we can try some fallback options with it
	 */
	copyTextToClipboard = (text : string, target_element? : HTMLElement, event? : ClipboardEvent | Event) : any =>
	{
		if (!(<any>navigator).clipboard)
		{
			let success = fallbackCopyTextToClipboard(event, target_element, text);
			return Promise.resolve(success ? undefined : false);
		}
		// Use Clipboard API
		const win : any = (<any>target_element)?.ownerDocument.defaultView ?? (<any>target_element).ownerDocument.parentWindow ?? window;
		return win.navigator.clipboard.writeText(text);
	}

	jsonEncode : (value : any) => string;
}

egw.extend('utils', egw.MODULE_GLOBAL, () => new Utils());
