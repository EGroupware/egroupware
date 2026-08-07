/**
 * EGroupware clientside API for persistant storage
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 */

/*egw:uses
	egw_core;
	egw_ready;
	egw_debug;
*/
import './egw_core';

export interface StoreModule
{
	/**
	 * Retrieve a value from session storage
	 *
	 * @param application Name of application, or common
	 * @param key
	 */
	getSessionItem(application : string, key : string) : string;

	/**
	 * Set a value in session storage
	 *
	 * @param application Name of application, or common
	 * @param key
	 * @param value
	 */
	setSessionItem(application : string, key : string, value : string) : void;

	/**
	 * Remove a value from session storage
	 * @param application
	 * @param key
	 */
	removeSessionItem(application : string, key : string) : void;

	/**
	 * Set an item to localStorage
	 *
	 * @param application an application name or a prefix
	 * @param item
	 * @param value
	 */
	setLocalStorageItem(application : string, item : string, value : any) : void;

	/**
	 * Get an item from localStorage
	 *
	 * @param application an application name or prefix
	 * @param item an item name stored in localStorage
	 * @return reutrns requested item value otherwise null
	 */
	getLocalStorageItem(application : string, item : string) : string|null;

	/**
	 * Remove an item from localStorage
	 *
	 * @param application application name or prefix
	 * @param item an item name to remove
	 */
	removeLocalStorageItem(application : string, item : string) : void;
}

declare global
{
	interface IegwGlobal extends StoreModule
	{
	}
}

/**
 * Store is a wrapper around browser based, persistant storage.
 *
 *
 * @see http://www.w3.org/TR/webstorage/#storage
 *
 * @param {string} _app
 * @param {DOMWindow} _wnd
 */
egw.extend('store', egw.MODULE_GLOBAL, function(_app : string, _wnd : Window) : StoreModule
{
	"use strict";

	var egw : any = this;

	/**
	 * Since the storage is shared across at least all applications, make
	 * the key include some extra info.
	 *
	 * @param {string} application
	 * @param {string} key
	 * @returns {undefined}
	 */
	function mapKey(application : string, key : string) : string
	{
		return application + '-' + key;
	}

	return {
		/**
		 * Retrieve a value from session storage
		 *
		 * @param {string} application Name of application, or common
		 * @param {string} key
		 * @returns {string}
		 */
		getSessionItem: function(application : string, key : string) : string {
			key = mapKey(application, key);
			return _wnd.sessionStorage.getItem(key);
		},

		/**
		 * Set a value in session storage
		 *
		 * @param {string} application Name of application, or common
		 * @param {string} key
		 * @param {string} value
		 * @returns {@exp;window@pro;sessionStorage@call;setItem}
		 */
		setSessionItem: function(application : string, key : string, value : string) : void {
			key = mapKey(application, key);
			return _wnd.sessionStorage.setItem(key, value);
		},

		/**
		 * Remove a value from session storage
		 * @param {string} application
		 * @param {string} key
		 * @returns {@exp;window@pro;sessionStorage@call;removeItem}
		 */
		removeSessionItem: function(application : string, key : string) : void {
			key = mapKey(application, key);
			return _wnd.sessionStorage.removeItem(key);
		},

		/**
		 * Set an item to localStorage
		 *
		 * @param {string} application an application name or a prefix
		 * @param {string} item
		 * @param {any} value
		 * @returns {undefined} returns undefined
		 */
		setLocalStorageItem: function(application : string, item : string, value : any) : void {
			item = mapKey (application, item);
			return localStorage.setItem(item,value);
		},

		/**
		 * Get an item from localStorage
		 *
		 * @param {string} application an application name or prefix
		 * @param {stirng} item an item name stored in localStorage
		 * @return {string|null} reutrns requested item value otherwise null
		 */
		getLocalStorageItem: function(application : string, item : string) : string|null {
			item = mapKey(application, item);
			return localStorage.getItem(item);
		},

		/**
		 * Remove an item from localStorage
		 *
		 * @param {string} application application name or prefix
		 * @param {string} item an item name to remove
		 * @return {undefined} returns undefined
		 */
		removeLocalStorageItem: function (application : string, item : string) : void {
			item = mapKey(application, item);
			return localStorage.removeItem(item);
		}
	};
});
