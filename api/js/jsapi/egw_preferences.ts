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

export interface PreferencesModule
{
	/**
	 * Setting prefs for an app or 'common'
	 *
	 * @param _data object with name: value pairs to set
	 * @param _app application name, 'common' or undefined to prefes of all apps at once
	 * @param _need_clone _data need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_preferences(_data : object, _app? : string, _need_clone? : boolean) : void;

	/**
	 * Query an EGroupware user preference
	 *
	 * If a preference is not already loaded (only done for "common" by default),
	 * it is synchronously queried from the server, if no _callback parameter is given!
	 *
	 * @param _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
	 * @param _app default 'common'
	 * @param _callback optional callback, if preference needs loading first
	 *  - default/undefined: preference is synchronously queried, if not loaded, and returned
	 *  - function: if loaded, preference is returned, if not false and callback is called once it's loaded
	 * 	- true:  a promise for the preference is returned
	 *	- false: if preference is not loaded, undefined is return and no (synchronous) request is send to server
	 * @param _context context for callback
	 * @return (Promise for) preference value or false, if callback given and preference not yet loaded
	 */
	preference(_name : string, _app? : string, _callback? : Function|boolean, _context? : object) : any;

	/**
	 * Set a preference and sends it to the server
	 *
	 * Server will silently ignore setting preferences, if user has no right to do so!
	 *
	 * Preferences are only send to server, if they are changed!
	 *
	 * @param _app application name or "common"
	 * @param _name name of the pref
	 * @param _val value of the pref, null, undefined or "" to unset it
	 * @param _callback Function passed along to the queue, called after preference is set server-side,
	 *	IF the preference is changed / has a value different from the current one
	 */
	set_preference(_app : string, _name : string, _val : any, _callback? : Function) : void;

	/**
	 * Endpoint for push to request reload of preference, if loaded and affected
	 *
	 * @param _app app-name of prefs to reload
	 * @param _account_id 0: allways reload (default or forced prefs), <0: reload if member of group
	 */
	reload_preferences(_app : string, _account_id : number|string) : void;

	/**
	 * Call context / open app specific preferences function
	 *
	 * @param name type 'acl', 'prefs', or 'cats'
	 * @param apps array with apps allowing to call that type, or object/hash with app and boolean or hash with url-params
	 */
	show_preferences(name : "acl"|"prefs"|"cats", apps : object|string[]) : void;

	/**
	 * Setting prefs for an app or 'common'
	 *
	 * @param _data
	 * @param _app application name or undefined to set grants of all apps at once
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_grants(_data : object, _app? : string) : void;

	/**
	 * Query an EGroupware user preference
	 *
	 * We currently load grants from all apps in egw.js, so no need for a callback or promise.
	 *
	 * @param _app app-name
	 * @return grant object, false if not (yet) loaded and no callback or undefined
	 */
	grants(_app : string) : any;

	/**
	 * Get mime types supported by file editor AND not excluded by user
	 *
	 * @param _mime current mime type
	 * @returns returns object of filemanager editor hook
	 */
	file_editor_prefered_mimes(_mime : string) : object | null;
}

declare global
{
	interface IegwGlobal extends PreferencesModule
	{
	}
}

class Preferences implements PreferencesModule
{
	/**
	 * Object holding the prefences as 2-dim. associative array, use
	 * egw.preference(name[,app]) to access it.
	 *
	 * True JS private fields (#foo), not TS `private` - a `private` field is
	 * still a normal, enumerable own property at runtime, which
	 * egw_core.ts's for...in-based module merge would copy onto every
	 * egw(app,wnd) instance, risking silently colliding with another
	 * module's same-named property (this bit egw_user.ts's `request` field
	 * vs. egw_json.ts's `request()` method). #-private fields are never
	 * enumerable, so this can't happen, and #prefs/#grants don't need to
	 * dodge the public preference()/grants() names either.
	 *
	 * @access: private, use egw.preferences() or egw.set_perferences()
	 */
	#prefs : {[app : string] : any} = {
		common:{textsize:12}
	};

	#grants : {[app : string] : any} = {};

	/**
	 * App-names in egw_preference table are limited to 16 chars, so we can not store anything longer
	 *
	 * Also modify tab-names used in CRM-view ("addressbook-*") to "infolog".
	 */
	private sanitizeApp(_app : string) : string
	{
		if (typeof _app === 'undefined') _app = 'common';

		if (_app.length > 16)
		{
			_app = _app.substring(0, 16);
		}
		if (_app.match(/^addressbook-/))
		{
			_app = 'infolog';
		}
		return _app;
	}

	/**
	 * Setting prefs for an app or 'common'
	 *
	 * @param _data object with name: value pairs to set
	 * @param _app application name, 'common' or undefined to prefes of all apps at once
	 * @param _need_clone _data need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_preferences = (_data : object, _app? : string, _need_clone? : boolean) : void =>
	{
		if (typeof _app == 'undefined')
		{
			this.#prefs = _need_clone ? deepExtend({}, _data) : _data;
		}
		else
		{
			this.#prefs[this.sanitizeApp(_app)] = deepExtend({}, _data);	// we always clone here, as call can come from this.preferences!
		}
	}

	/**
	 * Query an EGroupware user preference
	 *
	 * If a preference is not already loaded (only done for "common" by default),
	 * it is synchronously queried from the server, if no _callback parameter is given!
	 *
	 * Called as egw(app,wnd).preference(...) - `this.json(...)` and the
	 * recursive `this.preference(...)` must dispatch through whichever
	 * instance called it, hence a plain `function` field. `self` reaches
	 * this Preferences instance's own private `prefs` state.
	 *
	 * @param _name name of the preference, eg. 'dateformat', or '*' to get all the application's preferences
	 * @param _app default 'common'
	 * @param _callback optional callback, if preference needs loading first
	 *  - default/undefined: preference is synchronously queried, if not loaded, and returned
	 *  - function: if loaded, preference is returned, if not false and callback is called once it's loaded
	 * 	- true:  a promise for the preference is returned
	 *	- false: if preference is not loaded, undefined is return and no (synchronous) request is send to server
	 * @param _context context for callback
	 * @return (Promise for) preference value or false, if callback given and preference not yet loaded
	 */
	preference = ((self : Preferences) => function(this : any, _name : string, _app? : string, _callback? : Function|boolean, _context? : object) : any
	{
		_app = self.sanitizeApp(_app);

		if (typeof self.#prefs[_app] === 'undefined')
		{
			if (_callback === false) return undefined;
			const request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app], _callback, _context);
			const promise = request.sendRequest(typeof _callback !== 'undefined', 'GET');
			if (typeof self.#prefs[_app] === 'undefined') self.#prefs[_app] = promise;
			if (_callback === true) return promise.then(() => this.preference(_name, _app));
			if (typeof _callback === 'function') return false;
		}
		else if (typeof self.#prefs[_app] === 'object' && typeof self.#prefs[_app].then === 'function')
		{
			if (_callback === false) return undefined;
			if (_callback === true) return self.#prefs[_app].then(() => this.preference(_name, _app));
			if (typeof _callback === 'function') return false;
		}
		let ret;
		if (_name === "*")
		{
			ret = typeof self.#prefs[_app] === 'object' ? {...self.#prefs[_app]} : self.#prefs[_app];
		}
		else
		{
			ret = typeof self.#prefs[_app][_name] === 'object' && self.#prefs[_app][_name] !== null ?
				{...self.#prefs[_app][_name]} : self.#prefs[_app][_name];
		}
		if (_callback === true)
		{
			return Promise.resolve(ret);
		}
		return ret;
	})(this);

	/**
	 * Set a preference and sends it to the server
	 *
	 * Server will silently ignore setting preferences, if user has no right to do so!
	 *
	 * Preferences are only send to server, if they are changed!
	 *
	 * Called as egw(app,wnd).set_preference(...) - `this.jsonq(...)` must
	 * dispatch through whichever instance called it, hence a plain
	 * `function` field.
	 *
	 * @param _app application name or "common"
	 * @param _name name of the pref
	 * @param _val value of the pref, null, undefined or "" to unset it
	 * @param _callback Function passed along to the queue, called after preference is set server-side,
	 *	IF the preference is changed / has a value different from the current one
	 */
	set_preference = ((self : Preferences) => function(this : any, _app : string, _name : string, _val : any, _callback? : Function) : void
	{
		_app = self.sanitizeApp(_app);

		// if there is no change, no need to submit it to server
		if (typeof self.#prefs[_app] != 'undefined')
		{
			var current = self.#prefs[_app][_name];
			var setting = _val;
			// to compare objects we serialize them
			if (typeof current == 'object') current = JSON.stringify(current);
			if (typeof setting == 'object') setting = JSON.stringify(setting);
			if (setting === current) return;
		}

		this.jsonq('EGroupware\\Api\\Framework::ajax_set_preference',[_app, _name, _val], _callback);

		// update own preference cache, if _app prefs are loaded (don't update otherwise, as it would block loading of other _app prefs!)
		if (typeof self.#prefs[_app] != 'undefined')
		{
			if (_val === undefined || _val === "" || _val === null)
			{
				delete self.#prefs[_app][_name];
			}
			else
			{
				self.#prefs[_app][_name] = _val;
			}
		}
	})(this);

	/**
	 * Endpoint for push to request reload of preference, if loaded and affected
	 *
	 * Called as egw(app,wnd).reload_preferences(...) - `this.user(...)` and
	 * `this.json(...)` must dispatch through whichever instance called it,
	 * hence a plain `function` field.
	 *
	 * @param _app app-name of prefs to reload
	 * @param _account_id _account_id 0: allways reload (default or forced prefs), <0: reload if member of group
	 */
	reload_preferences = ((self : Preferences) => function(this : any, _app : string, _account_id : number|string) : void
	{
		if (typeof _account_id !== 'number') _account_id = parseInt(_account_id);
		if (typeof self.#prefs[_app] === 'undefined' ||	// prefs not loaded
			_account_id < 0 && this.user('memberships').indexOf(_account_id) < 0)	// no member of this group
		{
			return;
		}
		var request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app]);
		request.sendRequest();
	})(this);

	/**
	 * Call context / open app specific preferences function
	 *
	 * Called as egw(app,wnd).show_preferences(...) - `this.app_name()` must
	 * dispatch through whichever instance called it, hence a plain
	 * `function` field.
	 *
	 * @param name type 'acl', 'prefs', or 'cats'
	 * @param apps array with apps allowing to call that type, or object/hash with app and boolean or hash with url-params
	 */
	show_preferences = function(this : any, name : "acl"|"prefs"|"cats", apps : object|string[]) : void
	{
		var current_app = this.app_name();
		var query : any = {menuaction:'',current_app: current_app};
		// give warning, if app does not support given type, but all apps link to common prefs, if they dont support prefs themselfs
		if (Array.isArray(apps) && !apps.includes(current_app) && (name != 'prefs' && name != 'acl') ||
			!Array.isArray(apps) && (typeof apps[current_app] == 'undefined' || !apps[current_app]))
		{
			(<any>window).egw_message(egw.lang('Not supported by current application!'), 'warning');
		}
		else
		{
			var url = '/index.php';
			switch(name)
			{
				case 'prefs':
					query.menuaction ='preferences.preferences_settings.index';
					if (Array.isArray(apps) && apps.includes(current_app)) query.appname=current_app;
					egw.open_link(egw.link(url, query), '_blank', '1200x600');
					break;

				case 'acl':
					query.menuaction='preferences.preferences_acl.index';
					if (Array.isArray(apps) && apps.includes(current_app)) query.acl_app=current_app;
					egw.open_link(egw.link(url, query), '_blank', '1200x600');
					break;

				case 'cats':
					if (typeof apps[current_app] == 'object')
					{
						for(var key in apps[current_app])
						{
							query[key] = encodeURIComponent(apps[current_app][key]);
						}
					}
					else
					{
						query.menuaction='preferences.preferences_categories_ui.index';
						query.cats_app=current_app;
					}
					query.ajax = true;
					egw.link_handler(egw.link(url, query), current_app);
					break;
			}
		}
	}

	/**
	 * Setting prefs for an app or 'common'
	 *
	 * @param _data
	 * @param _app application name or undefined to set grants of all apps at once
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_grants = (_data : object, _app? : string) : void =>
	{
		if (_app)
		{
			this.#grants[_app] = deepExtend({}, _data);
		}
		else
		{
			this.#grants = deepExtend({}, _data);
		}
	}

	/**
	 * Query an EGroupware user preference
	 *
	 * We currently load grants from all apps in egw.js, so no need for a callback or promise.
	 *
	 * @param _app app-name
	 * @return grant object, false if not (yet) loaded and no callback or undefined
	 */
	grants = (_app : string) : any =>
	{
		/* we currently load grants from all apps in egw.js, so no need for a callback or promise
		if (typeof grants[_app] == 'undefined')
		{
			if (_callback === false) return undefined;
			var request = this.json('EGroupware\\Api\\Framework::ajax_get_preference', [_app], _callback, _context);
			request.sendRequest(typeof _callback == 'function', 'GET');	// use synchronous (cachable) GET request
			if (typeof grants[_app] == 'undefined') grants[_app] = {};
			if (typeof _callback == 'function') return false;
		}*/
		return typeof this.#grants[_app] === 'object' ? {...this.#grants[_app]} : this.#grants[_app];
	}

	/**
	 * Get mime types supported by file editor AND not excluded by user
	 *
	 * Called as egw(app,wnd).file_editor_prefered_mimes(...) -
	 * `this.link_get_registry(...)`/`this.preference(...)` must dispatch
	 * through whichever instance called it, hence a plain `function` field.
	 *
	 * @param _mime current mime type
	 * @returns returns object of filemanager editor hook
	 */
	file_editor_prefered_mimes = function(this : any, _mime : string) : object | null
	{
		const fe = deepExtend({}, this.link_get_registry('filemanager-editor'));
		let ex_mimes = this.preference('collab_excluded_mimes', 'filemanager');
		const dblclick_action = this.preference('document_doubleclick_action', 'filemanager');
		if (dblclick_action === 'download' && typeof _mime === 'string')
		{
			ex_mimes = !ex_mimes ? _mime : ex_mimes+','+_mime;
		}
		if (fe && fe.mime && ex_mimes && typeof ex_mimes === 'string')
		{
			ex_mimes = ex_mimes.split(',');
			for (let mime in fe.mime)
			{
				for (let i in ex_mimes)
				{
					if (ex_mimes[i] === mime) delete(fe.mime[mime]);
				}
			}
		}
		return fe && fe.mime ? fe : null;
	}
}

egw.extend('preferences', egw.MODULE_GLOBAL, () => new Preferences());
