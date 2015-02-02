/**
 * EGroupware clientside API for persistant storage
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


"use strict";

/*egw:uses
	egw_core;
	egw_ready;
	egw_debug;
*/


/**
 * Store is a wrapper around browser based, persistant storage.
 *
 *
 * @see http://www.w3.org/TR/webstorage/#storage
 *
 * @param {string} _app
 * @param {DOMWindow} _wnd
 */
egw.extend('store', egw.MODULE_GLOBAL, function(_app, _wnd) {

	var egw = this;

	/**
	 * Since the storage is shared across at least all applications, make
	 * the key include some extra info.
	 *
	 * @param {string} application
	 * @param {string} key
	 * @returns {undefined}
	 */
	function mapKey(application, key)
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
		getSessionItem: function(application, key) {
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
		setSessionItem: function(application, key, value) {
			key = mapKey(application, key);
			return _wnd.sessionStorage.setItem(key, value);
		},

		/**
		 * Remove a value from session storage
		 * @param {string} application
		 * @param {string} key
		 * @returns {@exp;window@pro;sessionStorage@call;removeItem}
		 */
		removeSessionItem: function(application, key) {
			key = uniqueKey(application, key);
			return _wnd.sessionStorage.removeItem(key);
		}
	};
});