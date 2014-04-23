/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

"use strict";

/*egw:uses
	egw_core;
*/

egw.extend('user', egw.MODULE_GLOBAL, function()
{
	/**
	 * Data about current user
	 *
	 * @access: private, use egw.user(_field) or egw.app(_app)
	 */
	var userData = {apps: {}};

	/**
	 * Client side cache of accounts user has access to
	 * Used by account select widgets
	 */
	var accountStore = {
		// Filled by AJAX when needed
		//accounts: {},
		//groups: {},
		//owngroups: {}
	};

	return {
		/**
		 * Set data of current user
		 *
		 * @param {object} _data
		 */
		set_user: function(_data)
		{
			userData = _data;
		},

		/**
		 * Get data about current user
		 *
		 * @param {string} _field
		 * - 'account_id','account_lid','person_id','account_status',
		 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
		 * - 'apps': object with app => data pairs the user has run-rights for
		 * @return {string|array|null}
		 */
		user: function (_field)
		{
			return userData[_field];
		},

		/**
		 * Return data of apps the user has rights to run
		 *
		 * Can be used the check of run rights like: if (egw.app('addressbook')) { do something if user has addressbook rights }
		 *
		 * @param {string} _app
		 * @param {string} _name attribute to return, default return whole app-data-object
		 * @return object|string|null null if not found
		 */
		app: function(_app, _name)
		{
			return typeof _name == 'undefined' || typeof userData.apps[_app] == 'undefined' ?
				userData.apps[_app] : userData.apps[_app][_name];
		},

		/**
		 * Get a list of accounts the user has access to
		 * The list is filtered by type, one of 'accounts','groups','both', 'owngroups'
		 *
		 * @param {string} type
		 * @returns {Object}
		 */
		accounts: function(type)
		{
			if(typeof type == 'undefined') type = 'accounts';

			var list = {};
			if(jQuery.isEmptyObject(accountStore))
			{
				// Synchronous
				egw.json('home.egw_framework.ajax_user_list.template',[],
					function(data) {accountStore = data||{};}
				).sendRequest();
			}
			if(type == 'both')
			{
				list = jQuery.extend(list, accountStore['accounts'],accountStore['groups']);
			}
			else
			{
				list = jQuery.extend(list, accountStore[type]);
			}
			return list;
		}
	};

});
