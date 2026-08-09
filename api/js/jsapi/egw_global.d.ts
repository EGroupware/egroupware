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

	// debug: implemented in egw_debug.ts, contributing DebugModule
	// images: implemented in egw_images.ts, contributing ImagesModule
	// lang: implemented in egw_lang.ts, contributing LangModule

	// links: implemented in egw_links.ts, contributing LinksModule

	// preferences: implemented in egw_preferences.ts, contributing PreferencesModule

	// calendar: implemented in egw_calendar.ts, contributing CalendarModule

	// store: implemented in egw_store.ts, contributing StoreModule
	// user: implemented in egw_user.ts, contributing UserModule

	// utils: implemented in egw_utils.ts, contributing UtilsModule
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

	// files: implemented in egw_files.ts, contributing FilesModule

	// jsonq: implemented in egw_jsonq.ts, contributing JsonqModule
	// message: implemented in egw_message.ts, contributing MessageModule

	// notification: implemented in egw_notification.ts, contributing NotificationModule
	// open: implemented in egw_open.ts, contributing OpenModule
	// ready: implemented in egw_ready.ts, contributing ReadyModule

	// tooltip: implemented in egw_tooltip.ts, contributing TooltipModule
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