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
import './egw_core';

export interface UserModule
{
	/**
	 * Set data of current user
	 *
	 * @param _data
	 * @param _need_clone _data need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_user(_data : object, _need_clone? : boolean) : void;

	/**
	 * Get data about current user
	 *
	 * @param _field
	 * - 'account_id','account_lid','person_id','account_status','memberships'
	 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
	 * - 'apps': object with app => data pairs the user has run-rights for
	 */
	user(_field : string) : any;

	/**
	 * Return data of apps the user has rights to run
	 *
	 * Can be used the check of run rights like: if (egw.app('addressbook')) { do something if user has addressbook rights }
	 *
	 * @param _app
	 * @param _name attribute to return, default return whole app-data-object
	 * @return undefined if not found
	 */
	app(_app : string, _name : string) : string|undefined;
	app(_app : string) : Iapplication|undefined;

	/**
	 * Same as app(), but use the translated app-name / title
	 *
	 * @param _title
	 * @param _name attribute to return, default return whole app-data-object
	 */
	appByTitle(_title : string, _name : string) : string|undefined;
	appByTitle(_title : string) : Iapplication|undefined;

	/**
	 * Get a list of accounts the user has access to
	 * The list is filtered by type, one of 'accounts','groups','both', 'owngroups'
	 *
	 * @param type
	 */
	accounts(type? : "accounts" | "groups" | "both" | "owngroups") : Promise<{ value : string, label : string, icon? : string }[]>;

	/**
	 * Get account-infos for given numerical _account_ids
	 *
	 * @param _account_ids
	 * @param _field default 'account_email'
	 * @param _resolve_groups true: return attribute for all members, false: return attribute of group
	 * @param _callback deprecated, use egw.accountDate(...).then(data => _callback.bind(_context)(data))
	 * @param _context deprecated, see _context
	 * @return resolving to object { account_id => value, ... }
	 */
	accountData(_account_ids : number | number[], _field? : string, _resolve_groups? : boolean,
				_callback? : Function, _context? : object) : Promise<{[account_id : string] : any}>;

	/**
	 * Set account data.  This one can be called from the server to pre-fill the cache.
	 *
	 * @param _data account_id => value pairs
	 * @param _field
	 */
	set_account_cache(_data : object, _field : string) : void;

	/**
	 * Set specified account-data of selected user in an other widget
	 *
	 * Used eg. in template as: onchange="egw.set_account_data(widget, 'target', 'account_email')"
	 *
	 * @param _src_widget widget to select the user
	 * @param _target_name name of widget to set the data
	 * @param _field name of data to set eg. "account_email" or "{account_fullname} <{account_email}>"
	 */
	set_account_data(_src_widget : /*et2_widget*/object, _target_name : string, _field : string) : void;

	/**
	 * Invalidate client-side account cache
	 *
	 * For _type == "add" we invalidate the whole cache currently.
	 *
	 * @param _id nummeric account_id, !_id will invalidate whole cache
	 * @param _type "add", "delete", "update" or "edit"
	 */
	invalidate_account(_id? : number, _type? : "add"|"delete"|"update"|"edit") : void;

	/**
	 * Set prompts
	 *
	 * @param _prompts
	 */
	set_prompts(_prompts : {id : string, label : string, children? : any[], apps? : string[]}[]) : void;

	/**
	 * Get prompts for given app
	 *
	 * Currently, the id's "aiassist.translate" and "aiassist.generate" have children/sub-menus.
	 *
	 * @param _app
	 */
	prompts(_app : string) : {id : string, label : string, children? : any[], apps? : string[]}[];
}

declare global
{
	interface IegwGlobal extends UserModule
	{
	}
}

egw.extend('user', egw.MODULE_GLOBAL, function() : UserModule
{
	"use strict";

	/**
	 * Data about current user
	 *
	 * @access: private, use egw.user(_field) or egw.app(_app)
	 */
	let userData : any = {apps: {}};

	/**
	 * Client side cache of accounts user has access to
	 * Used by account select widgets
	 */
	let accountStore : any = {
		// Filled by AJAX when needed
		//accounts: {},
		//groups: {},
		//owngroups: {}
	};

	/**
	 * Clientside cache for accountData calls
	 */
	let accountData : any = {};
	let resolveGroup : any = {};

	// Hold in-progress request to avoid making more
	let request : Promise<any> = null;

	/**
	 * Client-side cached prompts
	 *
	 * @var Array<{id: string, label: string, children: array|undefined, apps: array|undefined}>
	 */
	let prompts : any[] = [];

	return {
		/**
		 * Set data of current user
		 *
		 * @param {object} _data
		 * @param {boolean} _need_clone _data need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_user: function (_data : object, _need_clone? : boolean) : void
		{
			userData = _need_clone ? (<any>jQuery).extend(true, {}, _data) : _data;
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
		user: function (_field : string) : any
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
		app: function(_app : string, _name? : string) : any
		{
			return typeof _name == 'undefined' || typeof userData.apps[_app] == 'undefined' ?
				userData.apps[_app] : userData.apps[_app][_name];
		},

		/**
		 * Same as app(), but use the translated app-name / title
		 *
		 * @param {string} _title
		 * @param {string} _name attribute to return, default return whole app-data-object
		 */
		appByTitle: function(_title : string, _name? : string) : any
		{
			for(const app in userData.apps)
			{
				if (userData.apps[app].title === _title)
				{
					return typeof _name == 'undefined' || typeof userData.apps[app] == 'undefined' ?
						userData.apps[app] : userData.apps[app][_name];
				}
			}
		},

		/**
		 * Get a list of accounts the user has access to
		 * The list is filtered by type, one of 'accounts','groups','both', 'owngroups'
		 *
		 * @param {string} type
		 * @returns {Promise<{value:string,label:string,icon?:string}[]>}
		 */
		accounts: function (this : any, type? : string) : Promise<any>
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
			if ((<any>jQuery).isEmptyObject(accountStore))
			{
				const cache_it = data =>
				{
					let types = ["accounts", "groups", "owngroups"];
					for (let t of types)
					{
						if (typeof data[t] === "object")
						{
							accountStore[t] = (Array.isArray(data[t]) ? data[t]:Object.values(data[t]) ?? []).map(a => {a.value = ""+a.value; return a});
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
				result = [...Object.values(accountStore.accounts), ...Object.values(accountStore.groups)];
			}
			else
			{
				result = [...Object.values(accountStore[type])];
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
		accountData: function(_account_ids : number | number[], _field? : string, _resolve_groups? : boolean, _callback? : Function, _context? : object) : Promise<any>
		{
			if (!_field) _field = 'account_email';
			// TS won't narrow _account_ids from number|number[] to number[] across
			// the closures below, so normalize into a separately-typed local instead
			const ids : number[] = Array.isArray(_account_ids) ? _account_ids : [_account_ids];

			// check our cache or current user first
			const data : any = {};
			let pending = false;
			for(let i=0; i < ids.length; ++i)
			{
				const account_id = ids[i];

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
				ids.splice(i--, 1);
			}

			let promise;
			// something not found in cache --> ask server
			if (ids.length)
			{
				promise = egw.request('EGroupware\\Api\\Framework::ajax_account_data',[ids, _field, _resolve_groups]).then(_data =>
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
					if(_resolve_groups && ids.length === 1 && ids[0] < 0)
					{
						const group_id = ids[0];
						if (typeof resolveGroup[group_id] === 'undefined')
						{
							resolveGroup[group_id] = {};
						}
						resolveGroup[group_id][_field] = _data;
					}
					return data;
				});

				// store promise, in case someone asks while the request is pending, to not query the server again
				ids.forEach(account_id =>
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
				if (_resolve_groups && ids.length === 1 && ids[0] < 0)
				{
					resolveGroup[ids[0]] = promise;
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
		set_account_cache: function(_data : object, _field : string) : void
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
		set_account_data: function(_src_widget : any, _target_name : string, _field : string) : void
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
		invalidate_account: function(this : any, _id? : number, _type? : string) : void
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
			if ((<any>jQuery).isEmptyObject(accountStore)) return;

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
		},

		/**
		 * Set prompts
		 *
		 * @param Array<{id: string, label: string, children: Array|undefined, apps: Array|undefined> _prompts
		 */
		set_prompts: function(_prompts : any[]) : void
		{
			prompts = _prompts;
		},

		/**
		 * Get prompts for given app
		 *
		 * Currently, the id's "aiassist.translate" and "aiassist.generate" have children/sub-menus.
		 *
		 * @param string _app
		 * @return Array<{id: string, label: string, children: Array|undefined, apps: Array|undefined>
		 */
		prompts: function(_app : string) : any[]
		{
			const ret = [];
			prompts.forEach((prompt) =>
			{
				if (!prompt.apps || prompt.apps.includes(_app))
				{
					const children = [];
					(prompt.children || []).forEach((child) =>
					{
						if (!child.apps || child.apps.includes(_app))
						{
							children.push(child);
						}
					});
					ret.push(children.length ? {...prompt, children: children} : prompt);
				}
			});
			return ret;
		}
	};
});
