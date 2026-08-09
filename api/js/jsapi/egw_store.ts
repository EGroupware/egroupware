/**
 * EGroupware clientside API for persistant storage
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
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
 * @see http://www.w3.org/TR/webstorage/#storage
 */
class Store implements StoreModule
{
	constructor(private _wnd : Window)
	{
	}

	/**
	 * Since the storage is shared across at least all applications, make
	 * the key include some extra info.
	 */
	private mapKey(application : string, key : string) : string
	{
		return application + '-' + key;
	}

	/**
	 * Retrieve a value from session storage
	 */
	getSessionItem = (application : string, key : string) : string =>
	{
		key = this.mapKey(application, key);
		return this._wnd.sessionStorage.getItem(key);
	}

	/**
	 * Set a value in session storage
	 */
	setSessionItem = (application : string, key : string, value : string) : void =>
	{
		key = this.mapKey(application, key);
		return this._wnd.sessionStorage.setItem(key, value);
	}

	/**
	 * Remove a value from session storage
	 */
	removeSessionItem = (application : string, key : string) : void =>
	{
		key = this.mapKey(application, key);
		return this._wnd.sessionStorage.removeItem(key);
	}

	/**
	 * Set an item to localStorage
	 */
	setLocalStorageItem = (application : string, item : string, value : any) : void =>
	{
		item = this.mapKey(application, item);
		return localStorage.setItem(item,value);
	}

	/**
	 * Get an item from localStorage
	 *
	 * @return reutrns requested item value otherwise null
	 */
	getLocalStorageItem = (application : string, item : string) : string|null =>
	{
		item = this.mapKey(application, item);
		return localStorage.getItem(item);
	}

	/**
	 * Remove an item from localStorage
	 */
	removeLocalStorageItem = (application : string, item : string) : void =>
	{
		item = this.mapKey(application, item);
		return localStorage.removeItem(item);
	}
}

egw.extend('store', egw.MODULE_GLOBAL, (_app : string, _wnd : Window) => new Store(_wnd));
