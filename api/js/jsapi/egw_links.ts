/**
 * EGroupware clientside API: link-registry, link-titles, generation links
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

export interface LinksModule
{
	/**
	 * Check if _app is in the registry and has an entry for _name
	 *
	 * If the returned value is an object, it will always be a clone, not the registry itself!
	 * (So no need to run this.deepExtend() or jQuery.extend(true, ...) on it.)
	 *
	 * @param _app app-name
	 * @param _name name / key in the registry, e.g. 'view' or undefined to get whole registry for _app
	 * @return false if _app or attribute _name is not registered, otherwise value for attribute _name or whole registry for _app
	 */
	link_get_registry(_app : string, _name? : string) : any;

	/**
	 * jQuery.extend(true, ...) alternative
	 *
	 * Do NOT specify true as first parameter!
	 *
	 * For jQuery.extend(out, obj1, ...) use: {...out, ...obj1, ...}
	 *
	 * @param out
	 * @param arguments_
	 */
	deepExtend(out : any, ...arguments_ : any[]) : any;

	/**
	 * Get mime-type information from app-registry
	 *
	 * We prefer a full match over a wildcard like 'text/*' (written as regualr expr. "/^text\\//"
	 *
	 * @param _type
	 * @param _app_or_num default 1, return 1st, 2nd, n-th match, or match from application _app_or_num only
	 * @return with values for keys 'menuaction', 'mime_id' (path) or 'mime_url' and options 'mime_popup' and other values to pass one
	 */
	get_mime_info(_type : string, _app_or_num? : number|string) : {menuaction : string, mime_id? : string, mime_url? : string, mime_popup? : string}|null;

	/**
	 * Get handler (link-data) for given path and mime-type
	 *
	 * @param _path vfs path, egw_link::set_data() id or
	 *	object with attr path, optional download_url or id, app2 and id2 (path=/apps/app2/id2/id)
	 * @param _type mime-type, if not given in _path object
	 * @param _app_or_num default 1, use 1st, 2nd, n-th match, or match from application _app_or_num only
	 * @return string with EGw relative link, array with get-parameters for '/index.php' or null (directory and not filemanager access)
	 */
	mime_open(_path : string|object, _type : string, _app_or_num? : number|string) : string|object;

	/**
	 * Get list of link-aware apps the user has rights to use
	 *
	 * @param _must_support capability the apps need to support, eg. 'add', default ''=list all apps
	 * @return with app => title pairs
	 */
	link_app_list(_must_support? : string) : object;

	/**
	 * Set link registry
	 *
	 * @param _registry whole registry or entries for just one app
	 * @param _app
	 * @param _need_clone _images need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_link_registry(_registry : object, _app? : string, _need_clone? : boolean) : void;

	/**
	 * Generate a url which supports url or cookies based sessions
	 *
	 * Please note, the values of the query get url encoded!
	 *
	 * @param _url a url relative to the egroupware install root, it can contain a query too or
	 *	full url containing a schema and "://"
	 * @param _extravars query string arguements as string or array (prefered)
	 * 	if string is used ambersands in vars have to be already urlencoded as '%26', function ensures they get NOT double encoded
	 * @return generated url
	 */
	link(_url : string, _extravars? : string|object) : string;

	/**
	 * Query a title of _app/_id
	 *
	 * Deprecated default of returning string or null for no callback, will change in future to always return a Promise!
	 *
	 * @param _app
	 * @param _id
	 * @param _callback true to always return a promise, false: just lookup title-cache or optional callback
	 * 	NOT giving either a boolean value or a callback is deprecated!
	 * @param _context context for the callback
	 * @param _force_reload true load again from server, even if already cached
	 * @return Promise for _callback given (function or true), string with title if it exists in local cache or null if not
	 */
	link_title(_app : string, _id : string|number, _callback? : Function|boolean, _context? : object, _force_reload? : boolean) : Promise<string>|string|null;
	link_title(_app : string, _id : string|number, _callback : true) : Promise<string>;
	link_title(_app : string, _id : string|number, _callback? : false) : string|null;

	/**
	 * Unset a (cached) link-title or all link-titles of an application
	 *
	 * @param _app
	 * @param _id
	 */
	unset_link_title(_app : string, _id? : string) : void;

	/**
	 * Callback to add all current title requests
	 *
	 * @param _params of parameters, only first parameter is used
	 */
	link_title_before_send(_params : any[]) : void;

	/**
	 * Callback for server response
	 *
	 * @param _response _app => _id => title
	 */
	link_title_callback(_response : object) : void;

	/**
	 * Create quick add selectbox
	 *
	 * @param _parent parent or selector of it to create selectbox in
	 */
	link_quick_add(_parent : HTMLElement|string) : void;

	/**
	 * Check if a mimetype is editable
	 *
	 * Check mimetype & user preference
	 */
	isEditable(mime : string) : boolean;

	/**
	 * Check if a mimetype is openable in Collabora
	 * (without needing to have Collabora JS loaded)
	 *
	 * @param mime
	 */
	isCollaborable(mime : string) : string|false;
}

declare global
{
	interface IegwGlobal extends LinksModule
	{
	}
}

/**
 * Encode query parameters
 *
 * Stateless (self-recursive, only touches its own params), so stays a
 * plain module-scope function rather than a class member.
 *
 * @param _values object|array|string
 * @param _prefix
 * @param _query
 * @return array
 */
function urlencode(_values : any, _prefix? : string, _query? : string[]) : string[]
{
	if (typeof _query === 'undefined') _query = [];
	if (Array.isArray(_values))
	{
		if (!_prefix) throw "array of value needs a prefix";
		for(const value of _values)
		{
			_query.push(_prefix+'[]='+encodeURIComponent(value));
		}
	}
	else if (_values && typeof _values === 'object')
	{
		for(const name in _values)
		{
			urlencode(_values[name], _prefix ? _prefix+'['+name+']' : name, _query);
		}
	}
	else
	{
		_query.push(_prefix+'='+encodeURIComponent(_values || ''));
	}
	return _query;
}

/**
 * Link-registry, link-titles, generation links
 */
class Links implements LinksModule
{
	/**
	 * Link registry
	 *
	 * use egw.open() or egw.set_link_registry()
	 */
	#linkRegistry : any = undefined;

	/**
	 * Local cache for link-titles
	 *
	 * use egw.link_title(_app, _id[, _callback, _context])
	 */
	#titleCache : {[app : string] : {[id : string] : string}} = {};

	/**
	 * Queue for link_title requests
	 *
	 * use egw.link_title(_app, _id[, _callback, _context])
	 * _app._id.[{callback: _callback, context: _context}[, ...]]
	 */
	#titleQueue : {[app : string] : {[id : string] : {callback : Function, context : any}[]}} = {};

	/**
	 * Uid of active jsonq request, to not start another one, as we get notified
	 * before it's actually send to the server via our link_title_before_send callback.
	 */
	#titleUid : any = null;

	/**
	 * Check if _app is in the registry and has an entry for _name
	 *
	 * If the returned value is an object, it will always be a clone, not the registry itself!
	 * (So no need to run this.deepExtend() or jQuery.extend(true, ...) on it.)
	 *
	 * Needs this instance's own #linkRegistry plus dynamic `this.app(...)`
	 * dispatch - self-capture.
	 *
	 * @param _app app-name
	 * @param _name name / key in the registry, e.g. 'view' or undefined to get whole registry for _app
	 * @return false if _app or attribute _name is not registered, otherwise value for attribute _name or whole registry for _app
	 */
	link_get_registry = ((self : Links) => function(this : any, _app : string, _name? : string) : any
	{
		if (typeof self.#linkRegistry !== 'object')
		{
			alert('egw.open() link registry is NOT defined!');
			return false;
		}
		if (typeof self.#linkRegistry[_app] === 'undefined')
		{
			return false;
		}
		const reg = self.#linkRegistry[_app];

		if (reg && typeof _name === 'undefined')
		{
			// No key requested, return the whole thing
			return deepExtend({}, reg);
		}
		// some defaults (we set them directly in the registry, to do this only once)
		if (typeof reg[_name] === 'undefined')
		{
			switch(_name)
			{
				case 'name':
					reg.name = _app;
					break;
				case 'icon':
					const app_data = this.app(_app);
					if (typeof app_data !== 'undefined' &&
						typeof app_data.icon !== 'undefined' && app_data.icon !== null)
					{
						reg.icon = (typeof app_data.icon_app != 'undefined' ? app_data.icon_app : _app)+'/'+app_data.icon;
					}
					else
					{
						reg.icon = _app+'/navbar';
					}
					break;
			}
		}
		switch (typeof reg[_name])
		{
			case 'undefined':
				return false;
			case 'object':
				return deepExtend({}, reg[_name]);
		}
		return reg[_name];
	})(this);

	/**
	 * jQuery.extend(true, ...) alternative
	 *
	 * Do NOT specify true as first parameter!
	 *
	 * For jQuery.extend(out, obj1, ...) use: {...out, ...obj1, ...}
	 *
	 * Kept here (delegating to egw_utils.ts's plain, directly-importable
	 * deepExtend()) purely for external callers already using
	 * egw.deepExtend(...)/this.deepExtend(...) (e.g. mail/js/app.ts,
	 * Et2Favorites/Favorite.ts) - new code should `import {deepExtend}
	 * from './egw_utils'` directly instead, so it doesn't need the
	 * 'links' module registered first just to deep-clone an object.
	 *
	 * @param out
	 * @param arguments_
	 */
	deepExtend = (out : any, ...arguments_ : any[]) : any =>
	{
		return deepExtend(out, ...arguments_);
	}

	/**
	 * Get mime-type information from app-registry
	 *
	 * We prefer a full match over a wildcard like 'text/*' (written as regular expr. "/^text\\//"
	 *
	 * Only needs this instance's own #linkRegistry, no dynamic dispatch -
	 * plain arrow field.
	 *
	 * @param _type
	 * @param _app_or_num default 1, return 1st, 2nd, n-th match, or match from application _app_or_num only
	 * @return with values for keys 'menuaction', 'mime_id' (path) or 'mime_url' and options 'mime_popup' and other values to pass one
	 */
	get_mime_info = (_type : string, _app_or_num? : any) : any =>
	{
		if (!_app_or_num) _app_or_num = 1;
		let wildcard_mime : any;
		for(const app of isNaN(_app_or_num) ? [_app_or_num] : Object.keys(this.#linkRegistry))
		{
			const reg = this.#linkRegistry[app];
			if (typeof reg?.mime !== 'undefined')
			{
				for(let mime in reg.mime)
				{
					if (mime === _type)
					{
						if (isNaN(_app_or_num) || !--_app_or_num)
						{
							return reg.mime[_type];
						}
						continue;
					}
					if (mime[0] === '/' && _type.match(new RegExp(mime.substring(1, mime.length-1), 'i')))
					{
						wildcard_mime = reg.mime[mime];
					}
				}
			}
		}
		return wildcard_mime ? wildcard_mime : null;
	}

	/**
	 * Get handler (link-data) for given path and mime-type
	 *
	 * Pure dynamic dispatch through `this.get_mime_info(...)`/
	 * `this.isCollaborable(...)` - no own state needed directly - plain
	 * `function` field.
	 *
	 * @param _path vfs path, egw_link::set_data() id or
	 *	object with attr path, optional download_url or id, app2 and id2 (path=/apps/app2/id2/id)
	 * @param _type mime-type, if not given in _path object
	 * @param _app_or_num default 1, use 1st, 2nd, n-th match, or match from application _app_or_num only
	 * @return string with EGw relative link, array with get-parameters for '/index.php' or null (directory and not filemanager access)
	 */
	mime_open = function(this : any, _path : any, _type : string, _app_or_num? : any) : any
	{
		let path : any;
		if (typeof _path === 'object')
		{
			if (typeof _path.path === 'undefined')
			{
				path = '/apps/'+_path.app2+'/'+_path.id2+'/'+_path.id;
			}
			else
			{
				path = _path.path;
			}
			if (typeof _path.type === 'string')
			{
				_type = _path.type;
			}
		}
		else if(_path[0] !== '/')
		{

		}
		else
		{
			path = _path;
		}
		let mime_info : any = this.get_mime_info(_type, _app_or_num);
		let data : any = {};
		if (mime_info)
		{
			if ((typeof _app_or_num === 'undefined' || _app_or_num === 'collabora') && this.isCollaborable(_type))
			{
				data = {
					'menuaction': 'collabora.EGroupware\\collabora\\Ui.editor',
					'path': path,
					'cd': 'no'	// needed to not reload framework in sharing
				};
				return data;
			}
			for(let attr in mime_info)
			{
				switch(attr)
				{
					case 'mime_url':
						if (path)
						{
							data[mime_info.mime_url] = 'vfs://default' + path;
						}
						break;
					case 'mime_data':
						if (!path && _path && typeof _path === 'string')
						{
							data[mime_info.mime_data] = _path;
						}
						break;
					case 'mime_type':
						data[mime_info.mime_type] = _type;
						break;
					case 'mime_id':
						data[mime_info.mime_id] = path;
						break;
					default:
						data[attr] = mime_info[attr];
				}
			}
			// if mime_info did NOT define mime_url attribute, we use a WebDAV url drived from path
			if (typeof mime_info.mime_url === 'undefined')
			{
				data.url = typeof _path === 'object' && _path.download_url ? _path.download_url : '/webdav.php' + path;
			}
		}
		else
		{
			data = typeof _path === 'object' && _path.download_url ? _path.download_url : '/webdav.php' + path;
		}
		return data;
	}

	/**
	 * Get list of link-aware apps the user has rights to use
	 *
	 * Needs this instance's own #linkRegistry plus dynamic `this.app(...)`/
	 * `this.lang(...)`/`this.link_get_registry(...)` dispatch - self-capture.
	 *
	 * @param _must_support capability the apps need to support, eg. 'add', default ''=list all apps
	 * @return with app => title pairs
	 */
	link_app_list = ((self : Links) => function(this : any, _must_support? : string) : any
	{
		let apps : any[] = [];
		for (let type in self.#linkRegistry)
		{
			const reg = self.#linkRegistry[type];

			if (typeof _must_support !== 'undefined' && _must_support && typeof reg[_must_support] === 'undefined') continue;

			const app_sub = type.split('-');
			if (this.app(app_sub[0]))
			{
				apps.push({"type": type, "label": this.lang(this.link_get_registry(type,'name'))});
			}
		}
		// sort labels (case-insensitive) alphabetic
		apps = apps.sort((_a, _b) =>
		{
			var al = _a.label.toUpperCase();
			var bl = _b.label.toUpperCase();
			return al === bl ? 0 : (al > bl ? 1 : -1);
		});
		// create sorted associative array / object
		const sorted : any = {};
		for(let i = 0; i < apps.length; ++i)
		{
			sorted[apps[i].type] = apps[i].label;
		}
		return sorted;
	})(this);

	/**
	 * Set link registry
	 *
	 * Only needs this instance's own #linkRegistry, no dynamic dispatch -
	 * plain arrow field.
	 *
	 * @param _registry whole registry or entries for just one app
	 * @param _app
	 * @param _need_clone _images need to be cloned, as it is from different window context
	 *	and therefore will be inaccessible in IE, after that window is closed
	 */
	set_link_registry = (_registry : object, _app? : string, _need_clone? : boolean) : void =>
	{
		if (typeof _app === 'undefined')
		{
			// guard against (unnecessary) overwriting the link-registry e.g. with partial data
			if (typeof this.#linkRegistry === 'undefined')
			{
				this.#linkRegistry = _need_clone ? deepExtend({}, _registry) : _registry;
			}
		}
		else
		{
			this.#linkRegistry[_app] = _need_clone ? deepExtend({}, _registry) : _registry;
		}
	}

	/**
	 * Generate a url which supports url or cookies based sessions
	 *
	 * Please note, the values of the query get url encoded!
	 *
	 * Pure dynamic dispatch through `this.webserverUrl` - no own state
	 * needed - plain `function` field.
	 *
	 * @param _url a url relative to the egroupware install root, it can contain a query too or
	 *	full url containing a schema and "://"
	 * @param _extravars query string arguements as string or array (prefered)
	 * 	if string is used ambersands in vars have to be already urlencoded as '%26', function ensures they get NOT double encoded
	 * @return generated url
	 */
	link = function(this : any, _url : string, _extravars? : any) : string
	{
		if (_url.substr(0,4) === 'http' && _url.indexOf('://') <= 5)
		{
			// already a full url (eg. download_url of vfs), nothing to do
		}
		else
		{
			if (_url[0] != '/')
			{
				alert("egw.link('"+_url+"') called with url starting NOT with a slash!");
				const app = (<any>window).egw_appName;
				if (app != 'login' && app != 'logout') _url = app+'/'+_url;
			}
			// append the url to the webserver url, if not already contained or empty
			if (this.webserverUrl && this.webserverUrl != '/' && _url.indexOf(this.webserverUrl+'/') != 0)
			{
				_url = this.webserverUrl + _url;
			}
		}
		const vars : any = {};

		// check if the url already contains a query and ensure that vars is an array and all strings are in extravars
		const url_othervars = _url.split('?',2);
		_url = url_othervars[0];
		const othervars = url_othervars[1];
		if (_extravars && typeof _extravars == 'object')
		{
			deepExtend(vars, _extravars);
			_extravars = othervars;
		}
		else
		{
			if (!_extravars) _extravars = '';
			if (othervars) _extravars += (_extravars?'&':'')+othervars;
		}

		// parse extravars string into the vars array
		if (_extravars)
		{
			const extravarsParts = (<string>_extravars).split('&');
			for(let i=0; i < extravarsParts.length; ++i)
			{
				const name_val = extravarsParts[i].split('=', 2);
				let name = name_val[0];
				let val = name_val[1] || '';
				if (val.indexOf('%26') !== -1) val = val.replace(/%26/g,'&');	// make sure to not double encode &
				if (name.lastIndexOf('[]') != -1 && name.lastIndexOf('[]') == name.length-2)
				{
					name = name.substr(0,name.length-2);
					if (typeof vars[name] === 'undefined') vars[name] = [];
					vars[name].push(val);
				}
				else
				{
					vars[name] = val;
				}
			}
		}

		// if there are vars, we add them urlencoded to the url
		return Object.keys(vars).length ? _url+'?'+urlencode(vars).join('&') : _url;
	}

	/**
	 * Query a title of _app/_id
	 *
	 * Deprecated default of returning string or null for no callback, will change in future to always return a Promise!
	 *
	 * Needs this instance's own #titleCache/#titleQueue/#titleUid plus
	 * dynamic `this.jsonq(...)` dispatch - self-capture.
	 *
	 * @param _app
	 * @param _id
	 * @param _callback true to always return a promise, false: just lookup title-cache or optional callback
	 * 	NOT giving either a boolean value or a callback is deprecated!
	 * @param _context context for the callback
	 * @param _force_reload true load again from server, even if already cached
	 * @return Promise for _callback given (function or true), string with title if it exists in local cache or null if not
	 */
	link_title = ((self : Links) => function(this : any, _app : string, _id : any, _callback? : any, _context? : any, _force_reload? : boolean) : any
	{
		// check if we have a cached title --> return it direct
		if (typeof self.#titleCache[_app] !== 'undefined' && typeof self.#titleCache[_app][_id] !== 'undefined' && _force_reload !== true)
		{
			if (typeof _callback === 'function')
			{
				_callback.call(_context, self.#titleCache[_app][_id]);
			}
			if (_callback)
			{
				return Promise.resolve(self.#titleCache[_app][_id]);
			}
			return self.#titleCache[_app][_id];
		}
		// no callback --> return null
		if (!_callback)
		{
			if (_callback !== false)
			{
				console.trace('Deprecated use of egw.link() without 3rd parameter callback!');
			}
			return null;	// not found in local cache and can't do a synchronous request
		}
		// queue the request
		if (typeof self.#titleQueue[_app] === 'undefined')
		{
			self.#titleQueue[_app] = {};
		}
		if (typeof self.#titleQueue[_app][_id] === 'undefined')
		{
			self.#titleQueue[_app][_id] = [];
		}
		let promise = new Promise(_resolve => {
			self.#titleQueue[_app][_id].push({callback: _resolve, context: _context});
		});
		if (typeof _callback === 'function')
		{
			promise = promise.then(_data => {
				_callback.bind(_context)(_data);
				return _data;
			});
		}
		// if there's no active jsonq request, start a new one
		if (self.#titleUid === null)
		{
			self.#titleUid = this.jsonq('EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_titles',[{}], undefined, this, this.link_title_before_send)
				.then(_response => this.link_title_callback(_response));
		}
		return promise;
	})(this);

	/**
	 * Unset a (cached) link-title or all link-titles of an application
	 *
	 * Only needs this instance's own #titleCache, no dynamic dispatch -
	 * plain arrow field.
	 *
	 * @param _app
	 * @param _id
	 */
	unset_link_title = (_app : string, _id? : string) : void =>
	{
		if (typeof _id === 'undefined' || _id === null)
		{
			delete this.#titleCache[_app];
		}
		else if (typeof this.#titleCache[_app] === 'object')
		{
			delete this.#titleCache[_app][_id];
		}
	}

	/**
	 * Callback to add all current title requests
	 *
	 * Only needs this instance's own #titleQueue/#titleUid, no dynamic
	 * dispatch - plain arrow field.
	 *
	 * @param _params of parameters, only first parameter is used
	 */
	link_title_before_send = (_params : any[]) : void =>
	{
		// add all current title-requests
		for(let app in this.#titleQueue)
		{
			for(let id in this.#titleQueue[app])
			{
				if (typeof _params[0][app] === 'undefined')
				{
					_params[0][app] = [];
				}
				_params[0][app].push(id);
			}
		}
		this.#titleUid = null;	// allow next request to jsonq
	}

	/**
	 * Callback for server response
	 *
	 * Only needs this instance's own #titleCache/#titleQueue, no dynamic
	 * dispatch - plain arrow field.
	 *
	 * @param _response _app => _id => title
	 */
	link_title_callback = (_response : any) : void =>
	{
		if (typeof _response !== 'object')
		{
			throw "Wrong parameter for egw.link_title_callback!";
		}
		for(let app in _response)
		{
			if (typeof this.#titleCache[app] !== 'object')
			{
				this.#titleCache[app] = {};
			}
			for (let id in _response[app])
			{
				const title = _response[app][id];
				// cache locally
				this.#titleCache[app][id] = title;
				// call callbacks waiting for title of app/id
				if (typeof this.#titleQueue[app] !== 'undefined' && typeof this.#titleQueue[app][id] !== "undefined")
				{
					for(let i=0; i < this.#titleQueue[app][id].length; ++i)
					{
						const callback = this.#titleQueue[app][id][i];
						callback.callback.call(callback.context, title);
					}
					delete this.#titleQueue[app][id];
				}
			}
		}
	}

	/**
	 * Create quick add selectbox
	 *
	 * Pure dynamic dispatch (this.open(...)/this.langRequire(...)/
	 * this.link_app_list(...)/this.link_get_registry(...)/this.lang(...)/
	 * this.image(...)) - no own state needed - plain `function` field.
	 *
	 * @param _parent parent or selector of it to create selectbox in
	 */
	link_quick_add = function(this : any, _parent : any) : void
	{
		// check if quick-add selectbox is already there, only create it again if not
		if (document.getElementById('quick_add_selectbox') || (<any>window).egwIsMobile())
		{
			return;
		}

		// Use node as the trigger
		const parent : any = typeof _parent == "string" ?  document.getElementById(_parent) : _parent;
		const select : any = document.createElement('et2-select');
		select.setAttribute('id', 'quick_add_selectbox');
		select.setAttribute('aria-hidden', 'true');
		// Empty label is required to clear value, but we hide it
		select.emptyLabel = "Select";
		select.placement = "bottom";
		parent.append(select);
		const plus = parent.querySelector("#quick_add");
		plus.addEventListener("click", () => {
			select.show();
		})

		// bind change handler
		select.addEventListener('change', () =>
		{
			if (select.value)
			{
				this.open('', select.value, 'add', {}, undefined, select.value, true);
			}
			select.value = '';
		});
		// need to load common translations for app-names
		this.langRequire(window, [{app: 'common', lang: this.preference('lang')}], () =>
		{
			let options : any[] = [];
			const apps = this.link_app_list('add');
			for(let app in apps)
			{
				if (this.link_get_registry(app, 'no_quick_add'))
				{
					continue;
				}
				options.push({
					value: app,
					label: this.lang(this.link_get_registry(app, 'entry') || apps[app]),
					icon: this.image('navbar', app)
				});
			}
			select.select_options = options;

			select.updateComplete.then(() =>
			{
				// Adjust popup positioning to account for hidden select parts
				select.select.popup.position = "top end";
				select.select.popup.sync = "";
				select.select.popup.distance = -32;
			});
		});
	}

	/**
	 * Check if a mimetype is editable
	 *
	 * Check mimetype & user preference
	 *
	 * Pure dynamic dispatch through `this.file_editor_prefered_mimes(...)` -
	 * no own state needed - plain `function` field.
	 */
	isEditable = function (this : any, mime : string) : boolean
	{
		if (!mime)
		{
			return false;
		}
		let fe : any = this.file_editor_prefered_mimes(mime);
		if (!fe || !fe.mime || fe && fe.mime && !fe.mime[mime])
		{
			return false;
		}
		return ['edit'].indexOf(fe.mime[mime].name) !== -1;
	}

	/**
	 * Check if a mimetype is openable in Collabora
	 * (without needing to have Collabora JS loaded)
	 *
	 * Pure dynamic dispatch through `this.user(...)`/
	 * `this.file_editor_prefered_mimes(...)`/`this.isEditable(...)` - no own
	 * state needed - plain `function` field.
	 *
	 * @param mime
	 *
	 * @return string|false
	 */
	isCollaborable = function (this : any, mime : string) : any
	{
		if (typeof this.user('apps')['collabora'] == "undefined")
		{
			return false;
		}

		// Additional check to see if Collabora can open the file at all, not just edit it
		let fe : any = this.file_editor_prefered_mimes(mime);
		if (fe && fe.mime && fe.mime[mime] && fe.mime[mime].name || this.isEditable(mime))
		{
			return fe.mime[mime].name;
		}
	}
}

egw.extend('links', egw.MODULE_GLOBAL, () => new Links());
