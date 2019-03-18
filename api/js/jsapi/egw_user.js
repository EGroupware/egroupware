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

/*egw:uses
	egw_core;
*/

egw.extend('user', egw.MODULE_GLOBAL, function()
{
	"use strict";

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

	/**
	 * Clientside cache for accountData calls
	 */
	var accountData = {

	};

	/**
	 * Store callbacks if we get multiple requests for the same data before the
	 * answer comes back
	 */
	var callbacks = {};

	return {
		/**
		 * Set data of current user
		 *
		 * @param {object} _data
		 * @param {boolean} _need_clone _data need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_user: function(_data, _need_clone)
		{
			userData = _need_clone ? jQuery.extend(true, {}, _data) : _data;
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
		 * @returns {array}
		 */
		accounts: function(type)
		{
			if(typeof type == 'undefined') type = 'accounts';

			var list = [];
			if(jQuery.isEmptyObject(accountStore))
			{
				// Synchronous
				egw.json('EGroupware\\Api\\Framework::ajax_user_list',[],
					function(data) {
						accountStore = jQuery.extend(true, {}, data||{});
					}, this, false
				).sendRequest(false);
			}
			if(type == 'both')
			{
				list = list.concat(accountStore['accounts'], accountStore['groups']);
			}
			else
			{
				list = list.concat(accountStore[type]);
			}
			return list;
		},

		/**
		 * Get account-infos for given numerical _account_ids
		 *
		 * @param {int|array} _account_ids
		 * @param {string} _field default 'account_email'
		 * @param {boolean} _resolve_groups true: return attribute for all members, false: return attribute of group
		 * @param {function} _callback
		 * @param {object} _context
		 */
		accountData: function(_account_ids, _field, _resolve_groups, _callback, _context)
		{
			if (!_field) _field = 'account_email';
			if (!jQuery.isArray(_account_ids)) _account_ids = [_account_ids];

			// check our cache or current user first
			var data = {};
			for(var i=0; i < _account_ids.length; ++i)
			{
				var account_id = _account_ids[i];

				if (account_id == userData.account_id)
				{
					data[account_id] = userData[_field];
				}
				else if (typeof accountData[account_id] != 'undefined' && typeof accountData[account_id][_field] != 'undefined' &&
					(!_resolve_groups || account_id > 0))
				{
					data[account_id] = accountData[account_id][_field];
				}
				else if (typeof accountData[account_id] != 'undefined' && typeof accountData[account_id][_field] != 'undefined' &&
					(_resolve_groups && account_id < 0))
				{
					// Groups are resolved on the server, but then the response
					// is cached so we ca re-resolve it locally
					for(var id in accountData[account_id][_field])
					{
						data[id] = accountData[account_id][_field][id];
					}
				}
				else if (typeof callbacks[account_id] === 'object')
				{
					// Add it to the list
					callbacks[_account_ids[i]].push({callback: _callback, context: _context});
				}
				else
				{
					continue;
				}
				_account_ids.splice(i--, 1);
			}

			// something not found in cache --> ask server
			if (_account_ids.length)
			{
				egw.json('EGroupware\\Api\\Framework::ajax_account_data',[_account_ids, _field, _resolve_groups],
					function(_data) {
						var callback_list = [];
						for(var account_id in _data)
						{
							if(callbacks[account_id])
							{
								callback_list = callback_list.concat(callbacks[account_id]);
								delete callbacks[account_id];
							}
							if (typeof accountData[account_id] === 'undefined')
							{
								accountData[account_id] = {};
							}
							data[account_id] = accountData[account_id][_field] = _data[account_id];
						}
						// If resolving for 1 group, cache the whole answer too
						// (More than 1 group, we can't split to each group)
						if(_resolve_groups && _account_ids.length === 1 && _account_ids[0] < 0)
						{
							var group_id = _account_ids[0];
							if(callbacks[group_id])
							{
								callback_list = callback_list.concat(callbacks[group_id]);
								delete callbacks[group_id];
							}
							if (typeof accountData[group_id] === 'undefined')
							{
								accountData[group_id] = {};
							}
							accountData[group_id][_field] = _data;
						}
						for(var i = 0; i < callback_list.length; i++)
						{
							if(typeof callback_list[i] !== 'object' || typeof callback_list[i].callback !== 'function') continue;
							callback_list[i].callback.call(callback_list[i].context, data);
						}
					}
				).sendRequest();
				// Keep request so we know what we're waiting for
				for(var i=0; i < _account_ids.length; ++i)
				{
					if(typeof callbacks[_account_ids[i]] === 'undefined')
					{
						callbacks[_account_ids[i]] = [];
					}
					callbacks[_account_ids[i]].push({callback: _callback, context: _context});
				}
			}
			else
			{
				_callback.call(_context, data);
			}
		},

		/**
		 * Set specified account-data of selected user in an other widget
		 *
		 * Used eg. in template as: onchange="egw.set_account_data(widget, 'target', 'account_email')"
		 *
		 * @param {et2_widget} _src_widget widget to select the user
		 * @param {string} _target_name name of widget to set the data
		 * @param {string} _field name of data to set eg. "account_email" or "{account_fullname} <{account_email}>"
		 */
		set_account_data: function(_src_widget, _target_name, _field)
		{
			var user = _src_widget.get_value();
			var target = _src_widget.getRoot().getWidgetById(_target_name);
			var field = _field;

			if (user && target)
			{
				egw.accountData(user, _field, false, function(_data)
				{
					var data;
					if (field.indexOf('{') == -1)
					{
						data = _data[user];
						target.set_value(data);
					}
					else
					{
						data = field;

						/**
						 * resolve given data whilst the condition met
						 */
						var resolveData = function(_d, condition, action) {
							var whilst = function (_d) {
								return condition(_d) ? action(condition(_d)).then(whilst) : Promise.resolve(_d);
							}
							return whilst(_d);
						};

						/**
						 * get data promise
						 */
						var getData = function(_match)
						{
							var match = _match;
							return new Promise(function(resolve)
							{
							  egw.accountData(user, match, false, function(_d)
								{
									data = data.replace(/{([^}]+)}/, _d[user]);
									resolve(data);
								});
							});
						};

						// run rsolve data
						resolveData(data, function(_d){
							var r = _d.match(/{([^}]+)}/);
							return r && r.length > 0 ? r[1] : r;
						},
						getData).then(function(data){
							target.set_value(data)
						});
					}
				});
			};
		},

		/**
		 * Invalidate client-side account cache
		 *
		 * For _type == "add" we invalidate the whole cache currently.
		 *
		 * @param {number} _id nummeric account_id, !_id will invalidate whole cache
		 * @param {string} _type "add", "delete", "update" or "edit"
		 */
		invalidate_account: function(_id, _type)
		{
			if (_id)
			{
				delete accountData[_id];
			}
			else
			{
				accountData = {};
			}
			if (jQuery.isEmptyObject(accountStore)) return;

			switch(_type)
			{
				case 'delete':
				case 'edit':
				case 'update':
					if (_id)
					{
						var store = _id < 0 ? accountStore.groups : accountStore.accounts;
						for(var i=0; i < store.length; ++i)
						{
							if (store && typeof store[i] != 'undefined' && _id == store[i].value)
							{
								if (_type == 'delete')
								{
									delete(store[i]);
								}
								else
								{
									this.link_title('api-accounts', _id, function(_label)
									{
										store[i].label = _label;
										if (_id < 0)
										{
											for(var j=0; j < accountStore.owngroups.length; ++j)
											{
												if (_id == accountStore.owngroups[j].value)
												{
													accountStore.owngroups[j].label = _label;
													break;
												}
											}
										}
									}, this, true);	// true = force reload
								}
								break;
							}
						}
						break;
					}
					// fall through
				default:
					accountStore = {};
					break;
			}
		}
	};
});
