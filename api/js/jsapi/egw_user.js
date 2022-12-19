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

/*egw:uses
	egw_core;
*/
import './egw_core.js';

egw.extend('user', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Data about current user
	 *
	 * @access: private, use egw.user(_field) or egw.app(_app)
	 */
	let userData = {apps: {}};

	/**
	 * Client side cache of accounts user has access to
	 * Used by account select widgets
	 */
	let accountStore = {
		// Filled by AJAX when needed
		//accounts: {},
		//groups: {},
		//owngroups: {}
	};

	/**
	 * Clientside cache for accountData calls
	 */
	let accountData = {};
	let resolveGroup = {};

	// Hold in-progress request to avoid making more
	let request = null;

	return {
		/**
		 * Set data of current user
		 *
		 * @param {object} _data
		 * @param {boolean} _need_clone _data need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_user: function (_data, _need_clone)
		{
			userData = _need_clone ? jQuery.extend(true, {}, _data) : _data;
		},

		/**
		 * Get data about current user
		 *
		 * @param {string} _field
		 * - 'account_id','account_lid','person_id','account_status','memberships'
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
		 * @returns {Promise<{value:string,label:string,icon?:string}[]>}
		 */
		accounts: function (type)
		{
			if (typeof type === 'undefined')
			{
				type = 'accounts';
			}

			if (request !== null)
			{
				return request.then(() =>
				{
					return this.accounts(type)
				});
			}
			if (jQuery.isEmptyObject(accountStore))
			{
				const cache_it = data =>
				{
					let types = ["accounts", "groups", "owngroups"];
					for (let t of types)
					{
						if (typeof data[t] === "object")
						{
							accountStore[t] = jQuery.extend(true, [], data[t] || []);
						}
					}
				}
				request = egw.request("EGroupware\\Api\\Framework::ajax_user_list", []).then(_data =>
				{
					cache_it(_data);
					request = null;
					return this.accounts(type);
				});
				return request;
			}
			let result = [];
			if (type === 'both')
			{
				result = [].concat(accountStore.accounts, accountStore.groups);
			}
			else
			{
				result = [].concat(accountStore[type]);
			}
			return Promise.resolve(result);
		},

		/**
		 * Get account-infos for given numerical _account_ids
		 *
		 * @param {int|int[]} _account_ids
		 * @param {string} _field default 'account_email'
		 * @param {boolean} _resolve_groups true: return attribute for all members, false: return attribute of group
		 * @param {function|undefined} _callback deprecated, use egw.accountDate(...).then(data => _callback.bind(_context)(data))
		 * @param {object|undefined} _context deprecated, see _context
		 * @return {Promise} resolving to object { account_id => value, ... }
		 */
		accountData: function(_account_ids, _field, _resolve_groups, _callback, _context)
		{
			if (!_field) _field = 'account_email';
			if (!Array.isArray(_account_ids)) _account_ids = [_account_ids];

			// check our cache or current user first
			const data = {};
			let pending = false;
			for(let i=0; i < _account_ids.length; ++i)
			{
				const account_id = _account_ids[i];

				if (account_id == userData.account_id)
				{
					data[account_id] = userData[_field];
				}
				else if ((!_resolve_groups || account_id > 0) && typeof accountData[account_id] !== 'undefined' &&
					typeof accountData[account_id][_field] !== 'undefined')
				{
					data[account_id] = accountData[account_id][_field];
					pending = pending || data[account_id] instanceof Promise;
				}
				else if (_resolve_groups && account_id < 0 && typeof resolveGroup[account_id] !== 'undefined' &&
					typeof resolveGroup[account_id][_field] != 'undefined')
				{
					// Groups are resolved on the server, but then the response
					// is cached, so we can re-resolve it locally
					for(let id in resolveGroup[account_id][_field])
					{
						data[id] = resolveGroup[account_id][_field][id];
						pending = pending || data[id] instanceof Promise;
					}
				}
				else
				{
					continue;
				}
				_account_ids.splice(i--, 1);
			}

			let promise;
			// something not found in cache --> ask server
			if (_account_ids.length)
			{
				promise = egw.request('EGroupware\\Api\\Framework::ajax_account_data',[_account_ids, _field, _resolve_groups]).then(_data =>
				{
					for(let account_id in _data)
					{
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
						const group_id = _account_ids[0];
						if (typeof resolveGroup[group_id] === 'undefined')
						{
							resolveGroup[group_id] = {};
						}
						resolveGroup[group_id][_field] = _data;
					}
					return data;
				});

				// store promise, in case someone asks while the request is pending, to not query the server again
				_account_ids.forEach(account_id =>
				{
					if (_resolve_groups && account_id < 0) return;	// we must NOT cache the promise for account_id!

					if (typeof accountData[account_id] === 'undefined')
					{
						accountData[account_id] = {};
					}
					accountData[account_id][_field] = promise.then(function(_data)
					{
						const result = {};
						result[this.account_id] = _data[this.account_id];
						return result;
					}.bind({ account_id: account_id }));
				});
				if (_resolve_groups && _account_ids.length === 1 && _account_ids[0] < 0)
				{
					resolveGroup[_account_ids[0]] = promise;
				}
			}
			else
			{
				promise = Promise.resolve(data);
			}

			// if we have any pending promises, we need to resolve and merge them
			if (pending)
			{
				promise = promise.then(_data =>
				{
					const promises = [];
					for (let account_id in _data)
					{
						if (_data[account_id] instanceof Promise)
						{
							promises.push(_data[account_id]);
						}
					}
					return Promise.all(promises).then(_results =>
					{
						_results.forEach(result =>
						{
							for (let account_id in result)
							{
								_data[account_id] = result[account_id];
							}
						});
						return _data;
					});
				});
			}

			// if deprecated callback is given, call it with then
			if (typeof _callback === 'function')
			{
				promise = promise.then(_data =>
				{
					_callback.bind(_context)(_data);
					return _data;
				});
			}
			return promise;
		},

		/**
		 * Set account data.  This one can be called from the server to pre-fill the cache.
		 *
		 * @param {Array} _data
		 * @param {String} _field
		 */
		set_account_cache: function(_data, _field)
		{
			for(let account_id in _data)
			{
				if (typeof accountData[account_id] === 'undefined')
				{
					accountData[account_id] = {};
				}
				accountData[account_id][_field] = _data[account_id];
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
			const user = _src_widget.get_value();
			const target = _src_widget.getRoot().getWidgetById(_target_name);
			const field = _field;

			if (user && target)
			{
				egw.accountData(user, _field, false, function(_data)
				{
					let data;
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
						const resolveData = function(_d, condition, action) {
							const whilst = function (_d) {
								return condition(_d) ? action(condition(_d)).then(whilst) : Promise.resolve(_d);
							}
							return whilst(_d);
						};

						/**
						 * get data promise
						 */
						const getData = function(_match)
						{
							const match = _match;
							return new Promise(function(resolve)
							{
							  egw.accountData(user, match, false, function(_d)
								{
									data = data.replace(/{([^}]+)}/, _d[user]);
									resolve(data);
								});
							});
						};

						// run resolve data
						resolveData(data, function(_d) {
							const r = _d.match(/{([^}]+)}/);
							return r && r.length > 0 ? r[1] : r;
						},
						getData).then(function(data){
							target.set_value(data)
						});
					}
				});
			}
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
				delete resolveGroup[_id];
			}
			else
			{
				accountData = {};
				resolveGroup = {};
			}
			if (jQuery.isEmptyObject(accountStore)) return;

			switch(_type)
			{
				case 'delete':
				case 'edit':
				case 'update':
					if (_id)
					{
						const store = _id < 0 ? accountStore.groups : accountStore.accounts;
						for(let i=0; i < store.length; ++i)
						{
							if (store && typeof store[i] != 'undefined' && _id == store[i].value)
							{
								if (_type === 'delete')
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
											for(let j=0; j < accountStore.owngroups.length; ++j)
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