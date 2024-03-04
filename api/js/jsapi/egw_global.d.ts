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

/**
 * Global egw object (for now created by the diverse JavaScript files) with a TypeScript interface
 */
declare var egw : Iegw;

/**
 * Interface for global egw with window global or local methods or as function returning an object allowing also application local methods
 */
declare interface Iegw extends IegwWndLocal {
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
declare interface Iapplication
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
declare interface IegwData
{
	timestamp?: number;
	data: {[key:string]: any};
}

/**
 * Interface for all window global methods (existing only in top window)
 */
declare interface IegwGlobal
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
	 * implemented in egw_config.js
	 */
	/**
	 * Query clientside config
	 *
	 * @param {string} _name name of config variable
	 * @param {string} _app default "phpgwapi"
	 * @return mixed
	 */
	config(_name: string, _app?: string) : any;

	/**
	 * Set clientside configuration for all apps
	 *
	 * @param {object} _configs
	 * @param {boolean} _need_clone _configs need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_configs(_configs: object, _need_clone?: boolean) : void;

	/**
	 * implemeneted in egw_data.js
	 */
	/**
	 * Registers the intrest in a certain uid for a callback function. If
	 * the data for that uid changes or gets loaded, the given callback
	 * function is called. If the data for the given uid is available at the
	 * time of registering the callback, the callback is called immediately.
	 *
	 * @param _uid is the uid for which the callback should be registered.
	 * @param _callback is the callback which should get called.
	 * @param _context is the optional context in which the callback will be
	 * executed
	 * @param _execId is the exec id which will be used in case the data is
	 * not available
	 * @param _widgetId is the widget id which will be used in case the uid
	 * has to be fetched.
	 */
	dataRegisterUID(_uid : string, _callback : Function, _context, _execId : string, _widgetId : string) : void;
	/**
	 * Unregisters the intrest of updates for a certain data uid.
	 *
	 * @param _uid is the data uid for which the callbacks should be
	 * 	unregistered.
	 * @param _callback specifies the specific callback that should be
	 * 	unregistered. If it evaluates to false, all callbacks (or those
	 * 	matching the optionally given context) are removed.
	 * @param _context specifies the callback context that should be
	 * 	unregistered. If it evaluates to false, all callbacks (or those
	 * 	matching the optionally given callback function) are removed.
	 */
	dataUnregisterUID(_uid : string, _callback : Function, _context) : void;
	/**
	 * Returns whether data is available for the given uid.
	 *
	 * @param _uid is the uid for which should be checked whether it has some
	 * 	data.
	 */
	dataHasUID(_uid : string) : boolean;
	/**
	 * Returns data of a given uid.
	 *
	 * @param _uid is the uid for which should be checked whether it has some
	 * 	data.
	 */
	dataGetUIDdata(_uid : string) : IegwData;
	/**
	 * Returns all uids that have the given prefix
	 *
	 * @param {string} _prefix
	 * @return {array} of uids
	 * TODO: Improve this
	 */
	dataKnownUIDs(_prefix : string) : string[];
	/**
	 * Stores data for the uid and calls all callback functions registered
	 * for that uid.
	 *
	 * @param _uid is the uid for which the data should be saved.
	 * @param _data is the data which should be saved.
	 */
	dataStoreUID(_uid : string, _data : object) : void;
	/**
	 * Deletes the data for a certain uid from the local storage and
	 * unregisters all callback functions associated to it.
	 *
	 * This does NOT update nextmatch!
	 * Application code should use: egw(window).refresh(msg, app, id, "delete");
	 *
	 * @param _uid is the uid which should be deleted.
	 */
	dataDeleteUID(_uid : string) : void;
	/**
	 * Force a refreash of the given uid from the server if known, and
	 * calls all associated callbacks.
	 *
	 * If the UID does not have any registered callbacks, it cannot be refreshed because the required
	 * execID and context are missing.
	 *
	 * @param {string} _uid is the uid which should be refreshed.
	 * @return {boolean} True if the uid is known and can be refreshed, false if unknown and will not be refreshed
	 */
	dataRefreshUID(_uid : string) : boolean;
	/**
	 * Search for exact UID string or regular expression and return widgets using it
	 *
	 * @param {string|RegExp} _uid is the uid which should be refreshed.
	 * @return {object} UID: array of (nextmatch-)wigetIds
	 */
	dataSearchUIDs(_uid : string|RegExp) : /*et2_nextmatch*/any[];
	/**
	 * Search for exact UID string or regular expression and call registered (nextmatch-)widgets refresh function with given _type
	 *
	 * This method is preferable over dataRefreshUID for app code, as it takes care of things like counters too.
	 *
	 * It does not do anything for _type="add"!
	 *
	 * @param {string|RegExp} _uid is the uid which should be refreshed.
	 * @param {string} _type "delete", "edit", "update", not useful for "add"!
	 * @return {array} (nextmatch-)wigets refreshed
	 */
	dataRefreshUIDs(_uid : string|RegExp, _type : "delete"|"edit"|"update") : /*et2_nextmatch*/any[];

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

	/**
	 * implemented in egw_images.js
	 */
	/**
	 * Set imagemap, called from /api/images.php
	 *
	 * @param {array|object} _images
	 * @param {boolean} _need_clone _images need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_images(_images: object, _need_clone? : boolean);
	/**
	 * Get image URL for a given image-name and application
	 *
	 * @param {string} _name image-name without extension
	 * @param {string} _app application name, default current app of window
	 * @return string with URL of image
	 */
	image(_name : string, _app? : string) : string;
	/**
	 * Get image url for a given mime-type and option file
	 *
	 * @param {string} _mime
	 * @param {string} _path vfs path to generate thumbnails for images
	 * @param {number} _size defaults to 128 (only supported size currently)
	 * @param {number} _mtime current modification time of file to allow infinit caching as url changes
	 * @returns url of image
	 */
	mime_icon(_mime : string, _path? : string, _size? : number, _mtime? : number) : string;
	/**
	 * Create DOM img or svn element depending on url
	 *
	 * @param {string} _url source url
	 * @param {string} _alt alt attribute for img tag
	 * @returns DOM node
	 */
	image_element(_url : string, _alt? : string) : HTMLImageElement;

	/**
	 * implemented in egw_lang.js
	 */
	/**
	 * Set translation for a given application
	 *
	 * @param {string} _app
	 * @param {object} _messages message => translation pairs
	 * @param {boolean} _need_clone _messages need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_lang_arr(_app : string, _messages : object, _need_clone? : true) : void;
	/**
	 * Translate a given phrase replacing optional placeholders
	 *
	 * @param {string} _msg message to translate
	 * @param _args optional parameters (%{number} replacements)
	 * @return {string}
	 */
	lang(_msg : string, ..._args : string[] | number[]) : string;
	/**
	 * Load default langfiles for an application: common, _appname, custom
	 *
	 * @param {Window} _window
	 * @param {string} _appname name of application to load translations for
	 * @param {function} _callback
	 * @param {object} _context
	 */
	langRequireApp(_window : Window, _appname : string, _callback? : Function, _context? : object) : void;
	/**
	 * Includes the language files for the given applications -- if those
	 * do not already exist, include them.
	 *
	 * @param {Window} _window is the window which needs the language -- this is
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
	 */
	langRequire(_window : Window, _apps : {app: string, lang: string}[], _callback? : Function, _context? : object) : void;
	/**
	 * Check if $app is in the registry and has an entry for $name
	 *
	 * @param {string} _app app-name
	 * @param {string} _name name / key in the registry, eg. 'view'
	 * @return {string|object|boolean} false if $app is not registered, otherwise string with the value for $name
	 */
	link_get_registry(_app : string, _name? : string) : string|object|boolean;
	/**
	 * Get mime-type information from app-registry
	 *
	 * We prefer a full match over a wildcard like 'text/*' (written as regualr expr. "/^text\\//"
	 *
	 * @param {string} _type
	 * @return {object} with values for keys 'menuaction', 'mime_id' (path) or 'mime_url' and options 'mime_popup' and other values to pass one
	 */
	get_mime_info(_type : string) : {menuaction : string, mime_id? : string, mime_url? : string, mime_popup? : string}|null;
	/**
	 * Get handler (link-data) for given path and mime-type
	 *
	 * @param {string|object} _path vfs path, egw_link::set_data() id or
	 *	object with attr path, optinal download_url or id, app2 and id2 (path=/apps/app2/id2/id)
	 * @param {string} _type mime-type, if not given in _path object
	 * @return {string|object} string with EGw relative link, array with get-parameters for '/index.php' or null (directory and not filemanager access)
	 */
	mime_open(_path : string|object, _type : string) : string|object;
	/**
	 * Get list of link-aware apps the user has rights to use
	 *
	 * @param {string} _must_support capability the apps need to support, eg. 'add', default ''=list all apps
	 * @return {object} with app => title pairs
	 */
	link_app_list(_must_support? : string) : object;
	/**
	 * Set link registry
	 *
	 * @param {object} _registry whole registry or entries for just one app
	 * @param {string} _app
	 * @param {boolean} _need_clone _images need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_link_registry(_registry : object, _app? : string, _need_clone? : boolean) : void;
	/**
	 * Generate a url with get parameters
	 *
	 * Please note, the values of the query get url encoded!
	 *
	 * @param {string} _url a url relative to the egroupware install root, it can contain a query too or
	 *	full url containing a schema and "://"
	 * @param {object|string} _extravars query string arguements as string or array (prefered)
	 * 	if string is used ambersands in vars have to be already urlencoded as '%26', function ensures they get NOT double encoded
	 * @return {string} generated url
	 */
	link(_url : string, _extravars? : string|object) : string;
	/**
	 * Query a title of _app/_id
	 *
	 * Deprecated default of returning string or null for no callback, will change in future to always return a Promise!
	 *
	 * @param {string} _app
	 * @param {string|number} _id
	 * @param {boolean|function|undefined} _callback true to always return a promise, false: just lookup title-cache or optional callback
	 * 	NOT giving either a boolean value or a callback is deprecated!
	 * @param {object|undefined} _context context for the callback
	 * @param {boolean} _force_reload true load again from server, even if already cached
	 * @return {Promise<string>|string|null} Promise for _callback given (function or true), string with title if it exists in local cache or null if not
	 */
	link_title(_app : string, _id : string|number, _callback? : Function|boolean, _context? : object, _force_reload? : boolean) : Promise<string>|string|null;
	link_title(_app : string, _id : string|number, _callback : true) : Promise<string>;
	link_title(_app : string, _id : string|number, _callback? : false) : string|null;
	/**
	 * Callback to add all current title requests
	 *
	 * @param {object} _params of parameters, only first parameter is used
	 */
	// internal: link_title_before_send(_params : string[]) : void;
	/**
	 * Callback for server response
	 *
	 * @param {object} _response _app => _id => title
	 */
	// internal: link_title_callback(_response : object)
	/**
	 * Create quick add selectbox
	 *
	 * @param {HTMLElement|string} _parent parent or selector of it to create selectbox in
	 */
	link_quick_add(_parent : HTMLElement|string) : void;

	/**
	 * implemented in egw_preferences.js
	 */
	/**
	 * Setting prefs for an app or 'common'
	 *
	 * @param {object} _data object with name: value pairs to set
	 * @param {string} _app application name, 'common' or undefined to prefes of all apps at once
	 * @param {boolean} _need_clone _data need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_preferences(_data : object, _app? : string, _need_clone? : boolean) : void;
	/**
	 * Query an EGroupware user preference
	 *
	 * If a prefernce is not already loaded (only done for "common" by default), it is synchroniosly queryed from the server!
	 *
	 * @param {string} _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
	 * @param {string} _app default 'common'
	 * @param {function|undefined} _callback optional callback, if preference needs loading first
	 * if false given and preference is not loaded, undefined is return and no (synchronious) request is send to server
	 * @param {object} _context context for callback
	 * @return string|object|bool preference value or false, if callback given and preference not yet loaded
	 *  of object with all prefs for _name="*"
	 */
	preference(_name : string, _app? : string, _callback? : Function, _context? : object) : string|object|boolean;
	/**
	 * Set a preference and sends it to the server
	 *
	 * Server will silently ignore setting preferences, if user has no right to do so!
	 *
	 * Preferences are only send to server, if they are changed!
	 *
	 * @param {string} _app application name or "common"
	 * @param {string} _name name of the pref
	 * @param _val value of the pref, null, undefined or "" to unset it
	 * @param {function} _callback Function passed along to the queue, called after preference is set server-side,
	 *	IF the preference is changed / has a value different from the current one
	 */
	set_preference(_app : string, _name : string, _val : any, _callback? : Function) : void;
	/**
	 * Call context / open app specific preferences function
	 *
	 * @param {string} name type 'acl', 'prefs', or 'cats'
	 * @param {(array|object)} apps array with apps allowing to call that type, or object/hash with app and boolean or hash with url-params
	 */
	show_preferences(name : "acl"|"prefs"|"cats", apps : object|string[]) : void;
	/**
	 * Setting prefs for an app or 'common'
	 *
	 * @param {object} _data
	 * @param {string} _app application name or undefined to set grants of all apps at once
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_grants(_data : object, _app? : string) : void;
	/**
	 * Query an EGroupware user preference
	 *
	 * We currently load grants from all apps in egw.js, so no need for a callback or promise.
	 *
	 * @param {string} _app app-name
	 * @ param {function|false|undefined} _callback optional callback, if preference needs loading first
	 * if false given and preference is not loaded, undefined is return and no (synchronious) request is send to server
	 * @ param {object} _context context for callback
	 * @return grant object, false if not (yet) loaded and no callback or undefined
	 */
	grants(_app : string) /*, _callback, _context)*/ : any;

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

	/**
	 * Get mime types supported by file editor AND not excluded by user
	 *
	 * @param {string} _mime current mime type
	 * @returns {object|null} returns object of filemanager editor hook
	 */
	file_editor_prefered_mimes(_mime : string) : object | null;

	/**
	 * implemented in egw_store.js
	 */
	/**
	 * Retrieve a value from session storage
	 *
	 * @param {string} application Name of application, or common
	 * @param {string} key
	 * @returns {string|null}
	 */
	getSessionItem(application : string, key : string) : string;
	/**
	 * Set a value in session storage
	 *
	 * @param {string} application Name of application, or common
	 * @param {string} key
	 * @param {string | array} value
	 */
	setSessionItem(application : string, key : string, value : string[] | string) : void;
	/**
	 * Remove a value from session storage
	 * @param {string} application
	 * @param {string} key
	 */
	removeSessionItem(application : string, key : string) : void;
	/**
	 * Set an item to localStorage
	 *
	 * @param {string} application an application name or a prefix
	 * @param {string} item
	 * @param {string} value
	 * @returns {undefined} returns undefined
	 */
	setLocalStorageItem(application : string, item : string, value : string);
	/**
	 * Get an item from localStorage
	 *
	 * @param {string} application an application name or prefix
	 * @param {string} item an item name stored in localStorage
	 * @return {string|null} reutrns requested item value otherwise null
	 */
	getLocalStorageItem(application : string, item : string) : string|null;
	/**
	 * Remove an item from localStorage
	 *
	 * @param {string} application application name or prefix
	 * @param {string} item an item name to remove
	 */
	removeLocalStorageItem(application : string, item : string) : void;

	/**
	 * implemented in egw_user.js
	 */
	/**
	 * Set data of current user
	 *
	 * @param {object} _data
	 * @param {boolean} _need_clone _data need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_user(_data : object, _need_clone? : boolean) : void;
	/**
	 * Get data about current user
	 *
	 * @param {string} _field
	 * - 'account_id','account_lid','person_id','account_status',
	 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
	 * - 'apps': object with app => data pairs the user has run-rights for
	 * @return {string|array|null}
	 */
	user(_field : string) : any;
	/**
	 * Return data of apps the user has rights to run
	 *
	 * Can be used the check of run rights like: if (egw.app('addressbook')) { do something if user has addressbook rights }
	 *
	 * @param {string} _app
	 * @param {string} _name attribute to return, default return whole app-data-object
	 * @return Iapplication|string|undefined undefined if not found
	 */
	app(_app : string, _name : string) : string|undefined;
	app(_app : string) : Iapplication|undefined;
	/**
	 * Get a list of accounts the user has access to
	 * The list is filtered by type, one of 'accounts','groups','both', 'owngroups'
	 *
	 * @param {string} type
	 * @returns {Promise}
	 */
	accounts(type : "accounts" | "groups" | "both" | "owngroups") : Promise<{ value : string, label : string, icon? : string }[]>

	/**
	 * Get account-infos for given numerical _account_ids
	 *
	 * @param {int|array} _account_ids
	 * @param {string} _field default 'account_email'
	 * @param {boolean} _resolve_groups true: return attribute for all members, false: return attribute of group
	 * @param {function} _callback
	 * @param {object} _context
	 */
	accountData(_account_ids : number | number[], _field : string, _resolve_groups : boolean,
				_callback : Function, _context : object) : void;
	/**
	 * Set account data.  This one can be called from the server to pre-fill the cache.
	 *
	 * @param {object} _data account_id => value pairs
	 * @param {String} _field
	 */
	set_account_cache(_data : object, _field : string) : void;
	/**
	 * Set specified account-data of selected user in an other widget
	 *
	 * Used eg. in template as: onchange="egw.set_account_data(widget, 'target', 'account_email')"
	 *
	 * @param {et2_widget} _src_widget widget to select the user
	 * @param {string} _target_name name of widget to set the data
	 * @param {string} _field name of data to set eg. "account_email" or "{account_fullname} <{account_email}>"
	 */
	set_account_data(_src_widget : /*et2_widget*/object, _target_name : string, _field : string) : void;
	/**
	 * Invalidate client-side account cache
	 *
	 * For _type == "add" we invalidate the whole cache currently.
	 *
	 * @param {number} _id nummeric account_id, !_id will invalidate whole cache
	 * @param {string} _type "add", "delete", "update" or "edit"
	 */
	invalidate_account(_id? : number, _type? : "add"|"delete"|"update"|"edit") : void;

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
	async

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
	storeWindow(appname: boolean, popup : Window) : void;
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

declare class JsonRequest
{
	/**
	 * Sends the assembled request to the server
	 * @param {boolean|"keepalive"} _async true: asynchronious request, false: synchronious request,
	 * 	"keepalive": async. request with keepalive===true / sendBeacon, to be used in beforeunload event
	 * @param {string} method ='POST' allow to eg. use a (cachable) 'GET' request instead of POST
	 * @param {function} error option error callback(_xmlhttp, _err) used instead our default this.error
	 *
	 * @return {jqXHR} jQuery jqXHR request object
	 */
	sendRequest(async? : boolean|"keepalive", method? : "POST"|"GET", error? : Function) : Promise<any>
	/**
	 * Open websocket to push server (and keeps it open)
	 *
	 * @param {string} url this.websocket(s)://host:port
	 * @param {array} tokens tokens to subscribe too: sesssion-, user- and instance-token (in that order!)
	 * @param {number} account_id to connect for
	 * @param {function} error option error callback(_msg) used instead our default this.error
	 * @param {int} reconnect timeout in ms (internal)
	 */
	openWebSocket(url : string, tokens : string[], account_id : number, error : Function, reconnect : number);
}

/**
 * Interface for window local methods (plus the global ones)
 */
declare interface IegwWndLocal extends IegwGlobal
{
	window : Window;
	/**
	 * implemented in egw_css.js
	 */
	/**
	 * The css function can be used to introduce a rule for the given css
	 * selector. So you're capable of adding new custom css selector while
	 * runtime and also update them.
	 *
	 * @param _selector is the css select which can be used to apply the
	 * 	stlyes to the html elements.
	 * @param _rule is the rule which should be connected to the selector.
	 * 	if empty or omitted, the given selector gets removed.
	 */
	css(_selector : string, _rule? : string);

	/**
	 * implemented in egw_json.js
	 */
	/** The constructor of the egw_json_request class.
	 *
	 * @param _menuaction the menuaction function which should be called and
	 * 	which handles the actual request. If the menuaction is a full featured
	 * 	url, this one will be used instead.
	 * @param _parameters which should be passed to the menuaction function.
	 * @param {boolean|"keepalive"} _async true: asynchronious request, false: synchronious request,
	 * 	"keepalive": async. request with keepalive===true / sendBeacon, to be used in beforeunload event
	 * @param _callback specifies the callback function which should be
	 * 	called, once the request has been sucessfully executed.
	 * @param _context is the context which will be used for the callback function
	 * @param _sender is a parameter being passed to the _callback function
	 */
	json(_menuaction : string, _parameters? : any[], _callback? : Function, _context? : object, _async? : boolean|"keepalive", _sender?) : JsonRequest;

	/**
	 * Do an AJAX call and get a javascript promise, which will be resolved with the returned data.
	 *
	 * egw.request() returns immediately with a Promise.  The promise will be resolved with just the returned data,
	 * any other "piggybacked" responses will be handled by registered handlers.  The data will also be passed to
	 * any registered data handlers (egw.data) before it is passed to your handler.
	 *
	 * To use:
	 * @example
	 * 	egw.request(
	 * 		"EGroupware\\Api\\Etemplate\\Widget\\Select::ajax_get_options",
	 * 		["select-cat"]
	 * 	)
	 * 	.then(function(data) {
	 * 		// Deal with the returned data here.  data may be undefined if no data was returned.
	 * 		console.log("Here's the categories:",data);
	 * 	});
	 *
	 *
	 * 	The return is a Promise, so multiple .then() can be chained in the usual ways:
	 * 	@example
	 * 	egw.request(...)
	 * 		.then(function(data) {
	 * 		  if(debug) console.log("Requested data", data);
	 * 		}
	 * 		.then(function(data) {
	 * 			// Change the data for the rest of the chain
	 * 		    if(typeof data === "undefined") return [];
	 * 		}
	 * 		.then(function(data) {
	 * 			// data is never undefined now, if it was before it's an empty array now
	 * 		 	for(let i = 0; i < data.length; i++)
	 * 			{
	 * 		 		...
	 * 			}
	 * 		}
	 *
	 *
	 * 	You can also fire off multiple requests, and wait for them to all be answered:
	 * 	@example
	 * 	let first = egw.request(...);
	 * 	let second = egw.request(...);
	 * 	Promise.all([first, second])
	 * 		.then(function(values) {
	 * 		 	console.log("First:", values[0], "Second:", values[1]);
	 * 		}
	 *
	 *
	 * @param {string} _menuaction
	 * @param {any[]} _parameters
	 *
	 * @return Promise
	 */
	request(_menuaction: string, param2: any[]): Promise<any>;

	/**
	 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
	 *
	 * @param {string|Function} _func dot-separated function name or function
	 * @param {mixed} ...args variable number of arguments
	 * @returns {mixed|Promise}
	 */
	callFunc(_func : string|Function, ...args : any) : Promise<any>|any
	/**
	 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
	 *
	 * @param {string|Function} _func dot-separated function name or function
	 * @param {array} args arguments
	 * @param {object} _context
	 * @returns {mixed|Promise}
	 */
	applyFunc(_func : string|Function, args : IArguments, _context? : Object)  : Promise<any>|any

	/**
	 * Registers a new handler plugin.
	 *
	 * @param _callback is the callback function which should be called
	 * 	whenever a response is comming from the server.
	 * @param _context is the context in which the callback function should
	 * 	be called. If null is given, the plugin is executed in the context
	 * 	of the request object context.
	 * @param _type is an optional parameter defaulting to 'global'.
	 * 	it describes the response type which this plugin should be
	 * 	handling.
	 * @param {boolean} [_global=false] Register the handler globally or
	 *	locally.  Global handlers must stay around, so should be used
	 *	for global modules.
	 */
	registerJSONPlugin(_callback : Function, _context, _type?, _global?);
	/**
	 * Removes a previously registered plugin.
	 *
	 * @param _callback is the callback function which should be called
	 * 	whenever a response is comming from the server.
	 * @param _context is the context in which the callback function should
	 * 	be called.
	 * @param _type is an optional parameter defaulting to 'global'.
	 * 	it describes the response type which this plugin should be
	 * 	handling.
	 * @param {boolean} [_global=false] Remove a global or local handler.
	 */
	unregisterJSONPlugin(_callback : Function, _context, _type? : string, _global? : boolean);

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

	/**
	 * implemented in egw_jsonq.js
	 */
	/**
	 * Send a queued JSON call to the server
	 *
	 * @param {string} _menuaction the menuaction function which should be called and
	 *   which handles the actual request. If the menuaction is a full featured
	 *   url, this one will be used instead.
	 * @param {array} _parameters which should be passed to the menuaction function.
	 * @param {function} _callback callback function which should be called upon a "data" response is received
	 * @param {object} _sender is the reference object the callback function should get
	 * @param {function} _callbeforesend optional callback function which can modify the parameters, eg. to do some own queuing
	 * @return Promise
	 */
	jsonq(_menuaction : string, _parameters? : any[], _callback? : Function, _sender? : object, _callbeforesend? : Function) : Promise<any>;

	/**
	 * implemented in egw_message.js
	 */
	/**
	 * Display an error or regular message
	 *
	 * Alle messages but type "success" are displayed 'til next message or user clicks on it.
	 *
	 * @param {string} _msg message to show or empty to remove previous message
	 * @param {string} _type 'help', 'info', 'error', 'warning' or 'success' (default)
	 * @param {string} _discardID unique string id (appname:id) in order to register
	 * the message as discardable. If no appname given, the id will be prefixed with
	 * current app. The discardID will be stored in local storage.
	 *
	 * @returns {object} returns an object containing data and methods related to the message
	 */
	message(_msg: string, _type?: "help" | "info" | "error" | "warning" | "success", _discardID?: string): {node: JQuery, message: string, index: number, close: Function};
	/**
	 * Are we running in a popup
	 *
	 * @returns {boolean} true: popup, false: main window
	 */
	is_popup() : boolean;
	/**
	 * Active app independent if we are using a framed template-set or not
	 *
	 * @returns {string}
	 */
	app_name() : string;
	/**
	 * Update app-header and website-title
	 *
	 * @param {string} _header
	 * @param {string} _app Application name, if not for the current app
	 */
	app_header(_header : string, _app? : string)
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
	 *		- spinner: a sphere with a spinning bar inside (default)
	 *		- horizental: a horizental bar
	 *
	 * @returns {jquery dom object|null} returns jQuery DOM object or null in case of hiding
	 */
	loading_prompt(_id : string, _stat : boolean, _msg? : string, _node? : string|JQuery|HTMLElement, _mode? : "spinner"|"horizontal") : JQuery|null;
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
	refresh(_msg : string, _app : string, _id? : string|number, _type? : "update"|"edit"|"delete"|"add"|null,
			_targetapp? : string, _replace? : string|RegExp, _with? : string, _msg_type? : "error"|"warning"|"success", _links? : object) : void;
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
	// internal: push(pushData : {app:string, id:string|number}, type?:"update"|"edit"|"delete"|"add", acl? : any, account_id: number}) : void;

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
		tag?: string, onclick: Function, onshow?: Function, onclose?: Function, onerror?: Function, requireInteraction?: boolean}) : false|void;
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

	/**
	 * implemented in egw_open.js
	 */
	/**
	 * View an EGroupware entry: opens a popup of correct size or redirects window.location to requested url
	 *
	 * Examples:
	 * - egw.open(123,'infolog') or egw.open('infolog:123') opens popup to edit or view (if no edit rights) infolog entry 123
	 * - egw.open('infolog:123','timesheet','add') opens popup to add new timesheet linked to infolog entry 123
	 * - egw.open(123,'addressbook','view') opens addressbook view for entry 123 (showing linked infologs)
	 * - egw.open('','addressbook','view_list',{ search: 'Becker' }) opens list of addresses containing 'Becker'
	 *
	 * @param {string|number|object} id_data either just the id or if app=="" "app:id" or object with all data
	 * 	to be able to open files you need to give: (mine-)type, path or id, app2 and id2 (path=/apps/app2/id2/id"
	 * @param {string} app app-name or empty (app is part of id)
	 * @param {string} type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
	 * @param {object|string} extra extra url parameters to append as object or string
	 * @param {string} target target of window to open
	 * @param {string} target_app target application to open in that tab
	 * @param {boolean} _check_popup_blocker TRUE check if browser pop-up blocker is on/off, FALSE no check
	 * - This option only makes sense to be enabled when the open_link requested without user interaction
	 */
	open(id_data : string|number|object, app? : string, type? : "edit"|"view"|"view_list"|"add"|"list",
				   extra? : string|object, target? : string, target_app? : string, _check_popup_blocker? : boolean) : string;
	/**
	 * Open a link, which can be either a menuaction, a EGroupware relative url or a full url
	 *
	 * @param {string} _link menuaction, EGroupware relative url or a full url (incl. "mailto:" or "javascript:")
	 * @param {string} _target optional target / window name
	 * @param {string} _popup widthxheight, if a popup should be used
	 * @param {string} _target_app app-name for opener
	 * @param {boolean} _check_popup_blocker TRUE check if browser pop-up blocker is on/off, FALSE no check
	 * - This option only makes sense to be enabled when the open_link requested without user interaction
	 * @param {string} _mime_type if given, we check if any app has registered a mime-handler for that type and use it
	 */
	open_link(_link : string, _target? : string, _popup? : string, _target_app? : string,
			  _check_popup_blocker? : boolean, _mime_type? : string) : Window|void;

	/**
	 * Opens a menuaction in an Et2Dialog instead of a popup
	 *
	 * Please note:
	 * This method does NOT (yet) work in popups, only in the main EGroupware window!
	 * For popups you have to use the app.ts method openDialog(), which creates the dialog in the correct window / popup.
	 *
	 * @param string _menuaction
	 * @return Promise<Et2Dialog>
	 */
	openDialog(_menuaction : string) : Promise<Et2Dialog>;

	/**
	 * Open a (centered) popup window with given size and url
	 *
	 * @param {string} _url
	 * @param {number} _width
	 * @param {number} _height
	 * @param {string} _windowName or "_blank"
	 * @param {string|boolean} _app app-name for framework to set correct opener or false for current app
	 * @param {boolean} _returnID true: return window, false: return undefined
	 * @param {string} _status "yes" or "no" to display status bar of popup
	 * @param {boolean} _skip_framework
	 * @returns {Window|void}
	 */
	openPopup(_url : string, _width : number, _height : number|"availHeight", _windowName? : string, _app? : string|boolean,
			  _returnID? : boolean, _status? : "yes"|"no", _skip_framework? : boolean) : Window|void;
	/**
	 * View an EGroupware entry: opens a framework tab for the given app entry
	 *
	 * @param {string}|int|object _id either just the id or if app=="" "app:id" or object with all data
	 * @param {string} _app app-name or empty (app is part of id)
	 * @param {string} _type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
	 * @param {object|string} _extra extra url parameters to append as object or string
	 * @param {object} _framework_app framework app attributes e.g. title or displayName
	 * @return {string} appname of tab
	 */
	openTab(_id, _app, _type, _extra, _framework_app) : string|void;

	/**
	 * Get available height of screen
	 */
	availHeight() : number;
	/**
	 * Use frameworks (framed template) link handler to open a url
	 *
	 * @param {string} _url
	 * @param {string} _target
	 */
	link_handler(_url : string, _target : string) : void;
	/**
	 * Close current window / popup
	 */
	close() : void;
	/**
	 * Check if browser pop-up blocker is on/off
	 *
	 * @param {string} _link menuaction, EGroupware relative url or a full url (incl. "mailto:" or "javascript:")
	 * @param {string} _target optional target / window name
	 * @param {string} _popup widthxheight, if a popup should be used
	 * @param {string} _target_app app-name for opener
	 *
	 * @return boolean returns false if pop-up blocker is off
	 * - returns true if pop-up blocker is on,
	 * - and re-call the open_link with provided parameters, after user interaction.
	 */
	_check_popupBlocker(_link : string, _target? : string, _popup? : string, _target_app? : string) : boolean;
	/**
	 * This function helps to append content/ run commands into an already
	 * opened popup window. Popup windows now are getting stored in framework
	 * object and can be retrived/closed from framework.
	 *
	 * @param {string} _app name of application to be requested its popups
	 * @param {string} _method application method implemented in app.js
	 * @param {object} _content content to be passed to method
	 * @param {string|object} _extra url or object of extra
	 * @param {regexp} _regexp regular expression to get specific popup with matched url
	 * @param {boolean} _check_popup_blocker TRUE check if browser pop-up blocker is on/off, FALSE no check
	 */
	openWithinWindow(_app : string, _method : string, _content : object, _extra? : string|object, _regexp? : RegExp, _check_popup_blocker? : boolean) : void;

	/**
	 * implemented in egw_ready.js
	 */
	/**
	 * The readyWaitFor function can be used to register an event, that has
	 * to be marked as "done" before the ready function will call its
	 * registered callbacks. The function returns an id that has to be
	 * passed to the "readDone" function once
	 */
	readyWaitFor() : string;
	/**
	 * The readyDone function can be used to mark a event token as
	 * previously requested by "readyWaitFor" as done.
	 *
	 * @param _token is the token which has now been processed.
	 */
	readyDone(_token : string) : void;
	/**
	 * The ready function can be used to register a function that will be
	 * called, when the window is completely loaded. All ready handlers will
	 * be called exactly once. If the ready handler has already been called,
	 * the given function will be called defered using setTimeout.
	 *
	 * @param _callback is the function which will be called when the page
	 * 	is ready. No parameters will be passed.
	 * @param _context is the context in which the callback function should
	 * 	get called.
	 * @param _beforeDOMContentLoaded specifies, whether the callback should
	 * 	get called, before the DOMContentLoaded event has been fired.
	 */
	ready(_callback : Function, _context : object, _beforeDOMContentLoaded? : boolean) : void;
	/**
	 * The readyProgress function can be used to register a function that
	 * will be called whenever a ready event is done or registered.
	 *
	 * @param _callback is the function which will be called when the
	 * 	progress changes.
	 * @param _context is the context in which the callback function which
	 * 	should get called.
	 */
	readyProgress(_callback : Function, _context : object) : void;
	/**
	 * Returns whether the ready events have already been called.
	 */
	isReady() : boolean;

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
declare interface IegwAppLocal extends IegwWndLocal
{
	/**
	 * implemented in egw_data.js
	 */
	/**
	 * The dataFetch function provides an abstraction layer for the
	 * corresponding "EGroupware\Api\Etemplate\Widget\Nextmatch::ajax_get_rows" function.
	 * The server returns the following structure:
	 * 	{
	 * 		order: [uid, ...],
	 * 		data:
	 * 			{
	 * 				uid0: data,
	 * 				...
	 * 				uidN: data
	 * 			},
	 * 		total: <TOTAL COUNT>,
	 * 		lastModification: <LAST MODIFICATION TIMESTAMP>,
	 * 		readonlys: <READONLYS>
	 * 	}
	 * If a uid got deleted on the server above data is null.
	 * If a uid is omitted from data, is has not changed since lastModification.
	 *
	 * If order/data is null, this means that nothing has changed for the
	 * given range.
	 * The dataFetch function stores new data for the uid's inside the
	 * local data storage, the grid views are then capable of querying the
	 * data for those uids from the local storage using the
	 * "dataRegisterUID" function.
	 *
	 * @param _execId is the execution context of the etemplate instance
	 * 	you're querying the data for.
	 * @param _queriedRange is an object of the following form:
	 * 	{
	 * 		start: <START INDEX>,
	 * 		num_rows: <COUNT OF ENTRIES>
	 * 	}
	 * The range always corresponds to the given filter settings.
	 * @param _filters contains the filter settings. The filter settings are
	 * 	those which are crucial for the mapping between index and uid.
	 * @param _widgetId id with full namespace of widget
	 * @param _callback is the function that should get called, once the data
	 * 	is available. The data passed to the callback function has the
	 * 	following form:
	 * 	{
	 * 		order: [uid, ...],
	 * 		total: <TOTAL COUNT>,
	 * 		lastModification: <LAST MODIFICATION TIMESTAMP>,
	 * 		readonlys: <READONLYS>
	 * 	}
	 * 	Please note that the "uids" comming from the server and the ones
	 * 	being parsed to the callback function differ. While the uids
	 * 	which are returned from the server are only unique inside the
	 * 	application, the uids which are used on the client are "globally"
	 * 	unique.
	 * @param _context is the context in which the callback function will get
	 * 	called.
	 * @param _knownUids? is an array of uids already known to the client.
	 *  This parameter may be null in order to indicate that the client
	 *  currently has no data for the given filter settings.
	 */
	dataFetch(_execId: string, _queriedRange: { start?: number, num_rows?: number, refresh?: string[] },
	          _filters: object, _widgetId: string, _callback: Function, _context: object,
	          _knownUids?: string[]);
	/**
	 * Turn on long-term client side cache of a particular request
	 * (cache the nextmatch query results) for fast, immediate response
	 * with old data.
	 *
	 * The request is still sent to the server, and the cache is updated
	 * with fresh data, and any needed callbacks are called again with
	 * the fresh data.
	 *
	 * @param {string} prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param {function} callback_function A function that will analize the provided fetch
	 *	parameters and return a reproducable cache key, or false to not cache
	 *	the request.
	 * @param {function} notice_function A function that will be called whenever
	 *	cached data is used.  It is passed one parameter, a boolean that indicates
	 *	if the server is or will be queried to refresh the cache.  Do not fetch additional data
	 *	inside this callback, and return quickly.
	 * @param {object} context Context for callback function.
	 */
	dataCacheRegister(prefix : string, callback_function : Function, notice_function : Function, context : object);
	/**
	 * Unregister a previously registered cache callback
	 * @param {string} prefix UID / Application prefix should match the
	 *	individual record prefix
	 * @param {function} [callback] Callback function to un-register.  If
	 *	omitted, all functions for the prefix will be removed.
	 */
	dataCacheUnregister(prefix : string, callback : Function);
}

/**
 * Some other global function and objects
 *
 * Please note the egw_* ones are deprecated in favor of the above API
 */
declare function egw_getFramework() : any;
declare var chrome : any;
declare var InstallTrigger : any;
declare var app : {classes: any, [propName: string]: EgwApp};
declare var egw_globalObjectManager : any;
declare var egw_LAB : any;
declare function egwIsMobile() : string|null;

declare var mailvelope : any;

declare var framework : any;

declare function egw_refresh(_msg : string, app : string, id? : string|number, _type?, targetapp?, replace?, _with?, msgtype?);
declare function egw_open();

declare function egw_getWindowLeft() : number;
declare function egw_getWindowTop() : number;
declare function egw_getWindowInnerWidth() : number;
declare function egw_getWindowInnerHeight() : number;
declare function egw_getWindowOuterWidth() : number;
declare function egw_getWindowOuterHeight() : number;
/**
 *
 * @param {string} _mime current mime type
 * @returns {object|null} returns object of filemanager editor hook
 */
declare function egw_get_file_editor_prefered_mimes(_mime : string) : {mime:object, edit:any, edit_popup?:any}|null;

// Youtube API golbal vars
declare var YT : any;
declare function onYouTubeIframeAPIReady();