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
	#wnd : Window;

	constructor(_wnd : Window)
	{
		this.#wnd = _wnd;
	}

	/**
	 * Since the storage is shared across at least all applications, make
	 * the key include some extra info.
	 *
	 * The storage is shared by everyone using the browser too: sessionStorage survives a
	 * logout and login in the same tab, localStorage outlives the session entirely. So the
	 * key also has to identify the user and the instance (domain) he is logged into,
	 * otherwise the next user reads (and the framework happily restores) whatever the
	 * previous one left behind.
	 */
	private mapKey(application : string, key : string) : string
	{
		return application + '-' + key + this.userSuffix();
	}

	/**
	 * Suffix identifying current user and instance, empty as long as no user is known
	 *
	 * There is no user before /api/user.php has been imported (eg. on the login page, or in
	 * a very early call racing that import), in which case we must not silently share the
	 * unsuffixed key with everyone: callers reading too early just get nothing.
	 */
	private userSuffix() : string
	{
		const account_id = typeof egw.user === 'function' ? egw.user('account_id') : undefined;

		return account_id ? '-' + account_id + '@' + (egw.user('domain') || '') : '';
	}

	/**
	 * Retrieve a value from session storage
	 */
	getSessionItem = (application : string, key : string) : string =>
	{
		key = this.mapKey(application, key);
		return this.#wnd.sessionStorage.getItem(key);
	}

	/**
	 * Set a value in session storage
	 */
	setSessionItem = (application : string, key : string, value : string) : void =>
	{
		key = this.mapKey(application, key);
		return this.#wnd.sessionStorage.setItem(key, value);
	}

	/**
	 * Remove a value from session storage
	 */
	removeSessionItem = (application : string, key : string) : void =>
	{
		key = this.mapKey(application, key);
		return this.#wnd.sessionStorage.removeItem(key);
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
