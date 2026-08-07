/**
 * EGroupware clientside API TypeScript interface
 *
 * Manually compiled from various JavaScript files in api/js/jsapi.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @author Hadi Natheg <hn@egroupware.org>
 * @author Nathan Gray <ng@egroupware.org>
 * @author Andreas Stöckel
 */

import type {EgwApp} from "./egw_app";
import type {Et2Dialog} from "../etemplate/Et2Dialog/Et2Dialog";

// This file has top-level imports, which makes it a module - without "declare global",
// everything below would be scoped to this module instead of augmenting the global scope.
declare global {

/**
 * Global egw object (for now created by the diverse JavaScript files) with a TypeScript interface
 */
var egw : Iegw;

/**
 * Interface for global egw with window global or local methods or as function returning an object allowing also application local methods
 */
interface Iegw extends IegwWndLocal {
	(_app?: string | Window, _wnd?: Window) : IegwAppLocal,
	/**
	 * Copy text to the clipboard
	 *
	 * @param text Actual text to copy.  Usually target_element.value
	 * @param target_element Optional, but useful for fallback copy attempts
	 * @param event Optional, but if you have an event we can try some fallback options with it
	 *
	 * @returns {Promise<undefined|boolean>|Promise<void>}
	 */
	copyTextToClipboard:(text, target_element, event)=>any
}

/**
 * Return type for egw.app() call
 */
interface Iapplication
{
	title     : string;	// application title untranslated, better use egw.lang(app.name)
	name      : string;	// app-name
	enabled   : number;
	status    : number;
	id        : number;
	order     : number;
	version   : string;
	index?    : string;
	icon?     : string;
	icon_app? : string;
}

/**
 * Data stored by egw_data
 */
interface IegwData
{
	timestamp?: number;
	data: {[key:string]: any};
}

/**
 * Interface for all window global methods (existing only in top window)
 */
interface IegwGlobal
{
	/**
	 * Base URL of EGroupware install "/egroupware" or full URL incl. schema and domain
	 */
	webserverUrl : string;

	/**
	 * Reference to top window of EGroupware (no need to check for security exceptions!)
	 */
	top : Window;

	/**
	 * implemented in egw_core.js/egw_core.ts - the composition engine itself
	 */

	/** One shared instance for the whole page, regardless of app or window */
	MODULE_GLOBAL : number;

	/** One instance per distinct application name, independent of window */
	MODULE_APP_LOCAL : number;

	/** One instance per distinct window, independent of application name */
	MODULE_WND_LOCAL : number;

	/**
	 * Application name this instance was created for, null for the global instance
	 */
	appName : string | null;

	/**
	 * Window this instance belongs to
	 */
	window : Window;

	/**
	 * Current application name: app_name() if truthy, else the appName this
	 * instance was created with, else "api" for the global instance
	 */
	getAppName() : string;

	/**
	 * Register a new module. Its factory's return value gets merged into
	 * every existing and future instance matching the given scope.
	 *
	 * @param _module unique module name
	 * @param _flags one of MODULE_GLOBAL, MODULE_APP_LOCAL or MODULE_WND_LOCAL
	 * @param _code factory returning the object to merge into matching instances
	 */
	extend(_module : string, _flags : number, _code : (this : Iegw, _app : string | null, _wnd : Window) => object) : void;

	/**
	 * Low-level access to a single module's own instance, bypassing egw(app, wnd)'s merge.
	 * Mainly for modules to access each other while being instantiated.
	 *
	 * @param _module module name
	 * @param _for an app name, a window, or falsy for the module's global instance
	 */
	module(_module : string, _for? : string | Window) : any;

	/**
	 * Update a property on an already-instantiated MODULE_WND_LOCAL module,
	 * for every matching cached instance and the module's own stored slot.
	 *
	 * @param _module module name
	 * @param _name property name to set
	 * @param _value new value
	 * @param _window if given, only update instances/slots for this window
	 */
	constant(_module : string, _name : string, _value : any, _window? : Window) : void;

	/**
	 * Introspection: the registry of all currently registered modules, keyed by name
	 */
	dumpModules() : {[name : string] : {name : string, flags : number, code : Function}};

	/**
	 * Introspection: all currently cached egw(app, wnd) instances and per-app/
	 * per-window module instances
	 */
	dumpInstances() : {instances : object, moduleInstances : object};

	// config: implemented in egw_config.ts, contributing ConfigModule
	// data_storage: implemented in egw_data.ts, contributing DataStorageModule

	/**
	 * implemented in egw_debug.js
	 */
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
	 * @param {String} _level "navigation", "log", "info", "warn", "error"
	 * @param args arguments to egw.debug
	 */
	debug(_level : "navigation"|"log"|"info"|"warn"|"error", ...args : any[]) : void;
	/**
	 * Display log to user because he clicked on icon showed by raise_error
	 *
	 * @returns {undefined}
	 */
	show_log() : void;

	// images: implemented in egw_images.ts, contributing ImagesModule
	// lang: implemented in egw_lang.ts, contributing LangModule

	// links: implemented in egw_links.ts, contributing LinksModule

	// preferences: implemented in egw_preferences.ts, contributing PreferencesModule

	/**
	 * implemented in egw_calendar.js
	 */
	/**
	 * Get a list of holidays for the given year
	 *
	 * Returns a promise that resolves with a list of holidays indexed by date, in Ymd format:
	 * {20001225: [{day: 14, month: 2, occurence: 2021, name: "Valentinstag"}]}
	 *
	 * No need to cache the results, we do it here.
	 *
	 * @param year
	 * @returns Promise<{[key: string]: Array<object>}>
	 */
	holidays(fullYear : number) : Promise<{ [key : string] : Array<object> }>;

	// store: implemented in egw_store.ts, contributing StoreModule
	// user: implemented in egw_user.ts, contributing UserModule

	/**
	 * implemented in egw_utils.js
	 */
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
	 * @return {string} hex encoded, per call incremented counter
	 */
	uid() : string;
	/**
	 * Decode encoded vfs special chars
	 *
	 * @param {string} _path path to decode
	 * @return {string}
	 */
	decodePath(_path : string) : string;
	/**
	 * Encode vfs special chars excluding /
	 *
	 * @param {string} _path path to decode
	 * @return {string}
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
	 * @param {string} _comp path to decode
	 * @return {string}
	 */
	encodePathComponent(_comp : string) : string;

	/**
	 * Hash a string
	 *
	 * @param string
	 */
	hashString(name : any) : Promise<string>;
	/**
	 * Escape HTML special chars, just like PHP
	 *
	 * @param {string} s String to encode
	 *
	 * @return {string}
	 */
	htmlspecialchars(s : string) : string;
	/**
	 * If an element has display: none (or a parent like that), it has no size.
	 * Use this to get its dimensions anyway.
	 *
	 * @param element HTML element
	 * @param boolOuter Pass true to get outerWidth() / outerHeight() instead of width() / height()
	 *
	 * @return Object [w: width, h: height]
	 *
	 * @author Ryan Wheale
	 * @see http://www.foliotek.com/devblog/getting-the-width-of-a-hidden-element-with-jquery-using-width/
	 */
	getHiddenDimensions(element : HTMLElement | JQuery, boolOuter? : boolean) : {h: number, w: number, top: number, left: number};
	/**
	 * Store a window's name in egw.store so we can have a list of open windows
	 *
	 * @param {string} appname
	 * @param {Window} popup
	 */
	storeWindow(appname: string, popup : Window) : void;
	/**
	 * Get a list of the names of open popups
	 *
	 * Using the name, you can get a reference to the popup using:
	 * window.open('', name);
	 * Popups that were not given a name when they were opened are not tracked.
	 *
	 * @param {string} appname Application that owns/opened the popup
	 * @param {string} regex Optionally filter names by the given regular expression
	 *
	 * @returns {string[]} List of window names
	 */
	getOpenWindows(appname : string, regex? : string) : string[];
	/**
	 * Notify egw of closing a named window, which removes it from the list
	 *
	 * @param {String} appname
	 * @param {Window|String} closed Window that was closed, or its name
	 */
	windowClosed(appname : string, closed : Window|string) : void;

	/**
	 * implemented in egw_calendar.js
	 */
	/**
	 * transform PHP date/time-format to jQuery date/time-format
	 *
	 * @param {string} _php_format
	 * @returns {string}
	 */
	dateTimeFormat(_php_format : string) : string;
	/**
	 * Get timezone offset of user in seconds
	 *
	 * If browser / OS is configured correct, identical to: (new Date()).getTimezoneOffset()
	 *
	 * @return {number} offset to UTC in seconds
	 */
	getTimezoneOffset() : number;
	/**
	 * Calculate the start of the week, according to user's preference
	 *
	 * @param {string} date
	 * @return {Date}
	 */
	week_start(date : string) : Date;
}

// JsonRequest class: implemented in egw_json.ts

/**
 * Interface for window local methods (plus the global ones)
 */
interface IegwWndLocal extends IegwGlobal
{
	// css: implemented in egw_css.ts, contributing CssModule

	// json: implemented in egw_json.ts, contributing JsonModule (json(), request(),
	// callFunc(), applyFunc(), registerJSONPlugin(), unregisterJSONPlugin(), unregisterAllPlugins())

	/**
	 * implemented in egw_files.js
	 */
	/**
	 * Load and execute javascript file(s) in order
	 *
	 * @memberOf egw
	 * @param {string|array} _jsFiles (array of) urls to include
	 * @param {function} _callback called after JS files are loaded and executed
	 * @param {object} _context
	 * @param {string} _prefix prefix for _jsFiles
	 * @deprecated use es6 import statement: Promise.all([].concat(_jsFiles).map((src)=>import(_prefix+src))).then(...)
	 */
	includeJS(_jsFiles : string|string[], _callback? : Function, _context? : object, _prefix? : string);
	/**
	 * Check if file is already included and optional mark it as included if not yet included
	 *
	 * Check does NOT differenciate between file.min.js and file.js.
	 * Only .js get's recored in files for further checking, if _add_if_not set.
	 *
	 * @param {string} _file
	 * @param {boolean} _add_if_not if true mark file as included
	 * @return boolean true if file already included, false if not
	 */
	included(_file : string, _add_if_not? : boolean) : boolean;
	/**
	 * Include a CSS file
	 *
	 * @param {string|array} _cssFiles full url of file to include
	 */
	includeCSS(_cssFiles : string|string[]) : void;

	// jsonq: implemented in egw_jsonq.ts, contributing JsonqModule
	// message: implemented in egw_message.ts, contributing MessageModule

	/**
	 * implemented in egw_notifications.js
	 */
	/**
	 *
	 * @param {string} _title a string to be shown as notification message
	 * @param {object} _options an object of Notification possible options:
	 *		options = {
	 *			dir:  // direction of notification to be shown rtl, ltr or auto
	 *			lang: // a valid BCP 47 language tag
	 *			body: // DOM body
	 *			icon: // parse icon URL, default icon is app icon
	 *			tag: // a string value used for tagging an instance of notification, default is app name
	 *			onclick: // Callback function dispatches on click on notification message
	 *			onshow: // Callback function dispatches when notification is shown
	 *			onclose: // Callback function dispateches on notification close
	 *			onerror: // Callback function dispatches on error, default is a egw.debug log
	 *		    requireInteraction: // boolean value indicating that a notification should remain active until the user clicks or dismisses it
	 *		}
	 *	@return {boolean} false if Notification is not supported by browser
	 */
	notification(_title : string, _options : {dir?: "ltr"|"rtl"|"auto", lang?: string, body?: string, icon?: string,
		tag?: string, onclick?: Function, onshow?: Function, onclose?: Function, onerror?: Function, requireInteraction?: boolean}) : false|void;
	/**
	 * Check Notification availability by browser
	 *
	 * @returns {Boolean} true if notification is supported and permitted otherwise false
	 */
	checkNotification() : boolean;
	/**
	 * Check if there's any runnig notifications and will close them all
	 *
	 */
	killAliveNotifications() : void;

	// open: implemented in egw_open.ts, contributing OpenModule
	// ready: implemented in egw_ready.ts, contributing ReadyModule

	/**
	 * implemented in egw_tooltip.js
	 */
	/**
	 * Binds a tooltip to the given DOM-Node with the given html.
	 * It is important to remove all tooltips from all elements which are
	 * no longer needed, in order to prevent memory leaks.
	 *
	 * @param _elem is the element to which the tooltip should get bound. It
	 * 	has to be a jQuery node.
	 * @param _str is the html or text code which should be shown as tooltip.
	 * @param _isHtml true: add a html (no automatic quoting!), false (default): add as text
	 * @param _options tooltip options
	 */
	tooltipBind(_elem : HTMLElement, _str : string, _isHtml? : boolean, _options? : any);
	/**
	 * Unbinds the tooltip from the given DOM-Node.
	 *
	 * @param _elem is the element from which the tooltip should get
	 * removed. _elem has to be a jQuery node.
	 */
	tooltipUnbind(_elem : HTMLElement);
}

/**
 * Interface for application local methods (returned by global egw function)
 */
interface IegwAppLocal extends IegwWndLocal
{
	// data: implemented in egw_data.ts, contributing DataModule
}

/**
 * Some other global function and objects
 *
 * Please note the egw_* ones are deprecated in favor of the above API
 */
function egw_getFramework() : any;
var chrome : any;
var InstallTrigger : any;
var app : {classes: any, [propName: string]: EgwApp};
var egw_globalObjectManager : any;
var egw_LAB : any;
function egwIsMobile() : string|null;

var mailvelope : any;

var framework : any;

function egw_refresh(_msg : string, app : string, id? : string|number, _type?, targetapp?, replace?, _with?, msgtype?);
function egw_open();

function egw_getWindowLeft() : number;
function egw_getWindowTop() : number;
function egw_getWindowInnerWidth() : number;
function egw_getWindowInnerHeight() : number;
function egw_getWindowOuterWidth() : number;
function egw_getWindowOuterHeight() : number;
/**
 *
 * @param {string} _mime current mime type
 * @returns {object|null} returns object of filemanager editor hook
 */
function egw_get_file_editor_prefered_mimes(_mime : string) : {mime:object, edit:any, edit_popup?:any}|null;

// Youtube API golbal vars
var YT : any;
function onYouTubeIframeAPIReady();

}