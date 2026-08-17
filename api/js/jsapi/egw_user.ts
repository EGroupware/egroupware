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

import './egw_core';
import {deepExtend} from './egw_utils';

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

class User implements UserModule
{
	/**
	 * Data about current user
	 *
	 * @access: private, use egw.user(_field) or egw.app(_app)
	 */
	#userData : any = {apps: {}};

	/**
	 * Client side cache of accounts user has access to
	 * Used by account select widgets
	 */
	#accountStore : any = {
		// Filled by AJAX when needed
		//accounts: {},
		//groups: {},
		//owngroups: {}
	};

	/**
	 * Clientside cache for accountData calls
	 *
	 * True JS private fields (#foo), not TS `private` - see the note on
	 * egw_utils.ts's UtilsModule.request naming collision for why: a
	 * regular `private` class field is still a normal, enumerable own
	 * property at runtime (TS's `private` is compile-time-only), so
	 * egw_core.ts's for...in-based module merge would copy it onto every
	 * egw(app,wnd) instance right alongside the real interface methods -
	 * silently colliding with (and overwriting) any other module's
	 * same-named property. #-private fields are never enumerable, so they
	 * can't leak into the merge at all, matching the original closure
	 * variable's true privacy. This also means #accountData/#prompts don't
	 * need to dodge the public accountData()/prompts() method names the way
	 * the TS-`private` versions would have.
	 */
	#accountData : any = {};
	#resolveGroup : any = {};

	/**
	 * Ids queued for a not-yet-sent accountData() batch, keyed by field+resolve_groups
	 * (see accountDataBeforeSend()), then by account_id, holding the resolve functions
	 * of everyone currently waiting for that id.
	 *
	 * Entries are only ever added here (accountData()) or removed once actually
	 * answered by a response (accountDataResponse()) - never wholesale replaced -
	 * so an id queued right as an in-flight batch is being sent (and therefore not
	 * part of that batch) safely survives to be picked up by the next one, instead
	 * of being silently dropped.
	 */
	#accountDataQueue : {[key : string] : {[account_id : string] : ((value : any) => void)[]}} = {};

	/**
	 * Whether a jsonq() batch is already queued/in-flight for a given
	 * field+resolve_groups key - avoids starting a second one before the next
	 * jsonq flush (~100ms) picks up everyone queued so far.
	 */
	#accountDataPending : {[key : string] : boolean} = {};

	// Hold in-progress request to avoid making more
	#request : Promise<any> = null;

	/**
	 * Client-side cached prompts
	 *
	 * @var Array<{id: string, label: string, children: array|undefined, apps: array|undefined}>
	 */
	#prompts : any[] = [];

	/**
	 * Set data of current user
	 */
	set_user = (_data : object, _need_clone? : boolean) : void =>
	{
		this.#userData = _need_clone ? deepExtend({}, _data) : _data;
	}

	/**
	 * Get data about current user
	 *
	 * @param _field
	 * - 'account_id','account_lid','person_id','account_status','memberships'
	 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
	 * - 'apps': object with app => data pairs the user has run-rights for
	 */
	user = (_field : string) : any =>
	{
		return this.#userData[_field];
	}

	/**
	 * Return data of apps the user has rights to run
	 *
	 * Can be used the check of run rights like: if (egw.app('addressbook')) { do something if user has addressbook rights }
	 */
	app = (_app : string, _name? : string) : any =>
	{
		return typeof _name == 'undefined' || typeof this.#userData.apps[_app] == 'undefined' ?
			this.#userData.apps[_app] : this.#userData.apps[_app][_name];
	}

	/**
	 * Same as app(), but use the translated app-name / title
	 */
	appByTitle = (_title : string, _name? : string) : any =>
	{
		for(const app in this.#userData.apps)
		{
			if (this.#userData.apps[app].title === _title)
			{
				return typeof _name == 'undefined' || typeof this.#userData.apps[app] == 'undefined' ?
					this.#userData.apps[app] : this.#userData.apps[app][_name];
			}
		}
	}

	/**
	 * Get a list of accounts the user has access to
	 * The list is filtered by type, one of 'accounts','groups','both', 'owngroups'
	 *
	 * Called as egw(app,wnd).accounts(...) and recurses via
	 * `this.accounts(type)`, which must dispatch through whichever instance
	 * called it, hence a plain `function` field. `self` reaches this User
	 * instance's own accountStore/request state.
	 */
	accounts = ((self : User) => function(this : any, type? : string) : Promise<any>
	{
		if (typeof type === 'undefined')
		{
			type = 'accounts';
		}

		if (self.#request !== null)
		{
			return self.#request.then(() =>
			{
				return this.accounts(type)
			});
		}
		if (Object.keys(self.#accountStore).length === 0)
		{
			const cache_it = data =>
			{
				let types = ["accounts", "groups", "owngroups"];
				for (let t of types)
				{
					if (typeof data[t] === "object")
					{
						self.#accountStore[t] = (Array.isArray(data[t]) ? data[t]:Object.values(data[t]) ?? []).map(a => {a.value = ""+a.value; return a});
					}
				}
			}
			self.#request = egw.request("EGroupware\\Api\\Framework::ajax_user_list", []).then(_data =>
			{
				cache_it(_data);
				self.#request = null;
				return this.accounts(type);
			});
			return self.#request;
		}
		let result = [];
		if (type === 'both')
		{
			result = [...Object.values(self.#accountStore.accounts), ...Object.values(self.#accountStore.groups)];
		}
		else
		{
			result = [...Object.values(self.#accountStore[type])];
		}
		return Promise.resolve(result);
	})(this);

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
	accountData = (_account_ids : number | number[], _field? : string, _resolve_groups? : boolean, _callback? : Function, _context? : object) : Promise<any> =>
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

			if (account_id == this.#userData.account_id)
			{
				data[account_id] = this.#userData[_field];
			}
			else if ((!_resolve_groups || account_id > 0) && typeof this.#accountData[account_id] !== 'undefined' &&
				typeof this.#accountData[account_id][_field] !== 'undefined')
			{
				data[account_id] = this.#accountData[account_id][_field];
				pending = pending || data[account_id] instanceof Promise;
			}
			else if (_resolve_groups && account_id < 0 && typeof this.#resolveGroup[account_id] !== 'undefined' &&
				typeof this.#resolveGroup[account_id][_field] != 'undefined')
			{
				// Groups are resolved on the server, but then the response
				// is cached, so we can re-resolve it locally
				for(let id in this.#resolveGroup[account_id][_field])
				{
					data[id] = this.#resolveGroup[account_id][_field][id];
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
			// Resolving the members of a single group returns a merged member-map
			// that can't be safely combined with other requests in a shared batch
			// (we'd lose which member belongs to which group) --> ask directly.
			if (_resolve_groups && ids.length === 1 && ids[0] < 0)
			{
				promise = egw.request('EGroupware\\Api\\Framework::ajax_account_data',[ids, _field, _resolve_groups]).then(_data =>
				{
					for(let account_id in _data)
					{
						if (typeof this.#accountData[account_id] === 'undefined')
						{
							this.#accountData[account_id] = {};
						}
						data[account_id] = this.#accountData[account_id][_field] = _data[account_id];
					}
					// cache the whole answer too, so it can be re-resolved locally next time
					const group_id = ids[0];
					if (typeof this.#resolveGroup[group_id] === 'undefined')
					{
						this.#resolveGroup[group_id] = {};
					}
					this.#resolveGroup[group_id][_field] = _data;
					return data;
				});
				this.#resolveGroup[ids[0]] = promise;
			}
			else
			{
				// Queue ids into a shared batch per field+resolve_groups, so several
				// accountData() calls arriving within jsonq's ~100ms batching window
				// (eg. one per row of a list showing many users) become a single
				// ajax_account_data() call instead of one request each.
				const key = _field+'|'+(_resolve_groups ? 1 : 0);
				if (typeof this.#accountDataQueue[key] === 'undefined')
				{
					this.#accountDataQueue[key] = {};
				}
				const perId : {[account_id : string] : Promise<any>} = {};
				ids.forEach(account_id =>
				{
					perId[account_id] = new Promise(resolve =>
					{
						if (typeof this.#accountDataQueue[key][account_id] === 'undefined')
						{
							this.#accountDataQueue[key][account_id] = [];
						}
						this.#accountDataQueue[key][account_id].push(resolve);
					});
				});
				if (!this.#accountDataPending[key])
				{
					this.#accountDataPending[key] = true;
					egw.jsonq('EGroupware\\Api\\Framework::ajax_account_data', [[], _field, _resolve_groups],
						undefined, this, (params) => this.#accountDataBeforeSend(key, params)
					).then(_data => this.#accountDataResponse(key, _field, _data));
				}
				promise = Promise.all(ids.map(account_id => perId[account_id])).then(values =>
				{
					ids.forEach((account_id, i) => { data[account_id] = values[i]; });
					return data;
				});
			}

			// store promise, in case someone asks while the request is pending, to not query the server again
			ids.forEach(account_id =>
			{
				if (_resolve_groups && account_id < 0) return;	// we must NOT cache the promise for account_id!

				if (typeof this.#accountData[account_id] === 'undefined')
				{
					this.#accountData[account_id] = {};
				}
				this.#accountData[account_id][_field] = promise.then(function(_data)
				{
					const result = {};
					result[this.account_id] = _data[this.account_id];
					return result;
				}.bind({ account_id: account_id }));
			});
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
	}

	/**
	 * Called by jsonq just before an accountData() batch is sent, to sweep up every
	 * id queued for this field+resolve_groups key since the batch was started, and
	 * free up the key so the next accountData() call starts a fresh batch for
	 * whatever gets queued afterward.
	 *
	 * @param _key field+resolve_groups this batch is for, see accountData()
	 * @param _params jsonq's [account_ids, field, resolve_groups] parameters, mutated in place
	 */
	#accountDataBeforeSend = (_key : string, _params : any[]) : void =>
	{
		_params[0] = Object.keys(this.#accountDataQueue[_key] ?? {}).map(id => +id);
		this.#accountDataPending[_key] = false;
	}

	/**
	 * Callback for an accountData() batch's server response
	 *
	 * Caches every returned id and resolves whoever is still waiting for it in
	 * #accountDataQueue - regardless of whether their id ended up in this
	 * particular batch or a later one, so nothing queued in the narrow race
	 * window around accountDataBeforeSend() ever gets silently dropped.
	 *
	 * @param _key field+resolve_groups this batch was for, see accountData()
	 * @param _field
	 * @param _data account_id => value pairs returned by the server
	 */
	#accountDataResponse = (_key : string, _field : string, _data : any) : void =>
	{
		if (!_data) return;

		const queue = this.#accountDataQueue[_key] ?? {};
		for (let account_id in _data)
		{
			if (typeof this.#accountData[account_id] === 'undefined')
			{
				this.#accountData[account_id] = {};
			}
			this.#accountData[account_id][_field] = _data[account_id];

			if (typeof queue[account_id] !== 'undefined')
			{
				queue[account_id].forEach(resolve => resolve(_data[account_id]));
				delete queue[account_id];
			}
		}
	}

	/**
	 * Set account data.  This one can be called from the server to pre-fill the cache.
	 */
	set_account_cache = (_data : object, _field : string) : void =>
	{
		for(let account_id in _data)
		{
			if (typeof this.#accountData[account_id] === 'undefined')
			{
				this.#accountData[account_id] = {};
			}
			this.#accountData[account_id][_field] = _data[account_id];
		}
	}

	/**
	 * Set specified account-data of selected user in an other widget
	 *
	 * Used eg. in template as: onchange="egw.set_account_data(widget, 'target', 'account_email')"
	 */
	set_account_data = (_src_widget : any, _target_name : string, _field : string) : void =>
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
	}

	/**
	 * Invalidate client-side account cache
	 *
	 * For _type == "add" we invalidate the whole cache currently.
	 *
	 * Called as egw(app,wnd).invalidate_account(...) - `this.link_title(...)`
	 * must dispatch through whichever instance called it, hence a plain
	 * `function` field. `self` reaches this User instance's own state.
	 */
	invalidate_account = ((self : User) => function(this : any, _id? : number, _type? : string) : void
	{
		if (_id)
		{
			delete self.#accountData[_id];
			delete self.#resolveGroup[_id];
		}
		else
		{
			self.#accountData = {};
			self.#resolveGroup = {};
		}
		if (Object.keys(self.#accountStore).length === 0) return;

		switch(_type)
		{
			case 'delete':
			case 'edit':
			case 'update':
				if (_id)
				{
					const store = _id < 0 ? self.#accountStore.groups : self.#accountStore.accounts;
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
										for(let j=0; j < self.#accountStore.owngroups.length; ++j)
										{
											if (_id == self.#accountStore.owngroups[j].value)
											{
												self.#accountStore.owngroups[j].label = _label;
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
				self.#accountStore = {};
				break;
		}
	})(this);

	/**
	 * Set prompts
	 */
	set_prompts = (_prompts : any[]) : void =>
	{
		this.#prompts = _prompts;
	}

	/**
	 * Get prompts for given app
	 *
	 * Currently, the id's "aiassist.translate" and "aiassist.generate" have children/sub-menus.
	 */
	prompts = (_app : string) : any[] =>
	{
		const ret = [];
		this.#prompts.forEach((prompt) =>
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
}

egw.extend('user', egw.MODULE_GLOBAL, () => new User());
