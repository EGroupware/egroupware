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

/*egw:uses
	egw_core;
*/
import './egw_core.js';

/**
 * @augments Class
 */
egw.extend('links', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Link registry
	 *
	 * @access: private, use egw.open() or egw.set_link_registry()
	 */
	let link_registry = undefined;

	/**
	 * Local cache for link-titles
	 *
	 * @access private, use egw.link_title(_app, _id[, _callback, _context])
	 */
	let title_cache = {};

	/**
	 * Queue for link_title requests
	 *
	 * @access private, use egw.link_title(_app, _id[, _callback, _context])
	 * @var object _app._id.[{callback: _callback, context: _context}[, ...]]
	 */
	let title_queue = {};

	/**
	 * Uid of active jsonq request, to not start another one, as we get notified
	 * before it's actually send to the server via our link_title_before_send callback.
	 * @access private
	 */
	let title_uid = null;

	/**
	 * Encode query parameters
	 *
	 * @param object|array|string _values
	 * @param string? _prefix
	 * @param array? _query
	 * @return array
	 */
	function urlencode(_values, _prefix, _query)
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

	return {
		/**
		 * Check if $app is in the registry and has an entry for $name
		 *
		 * @param {string} _app app-name
		 * @param {string} _name name / key in the registry, eg. 'view'
		 * @return {boolean|string} false if $app is not registered, otherwise string with the value for $name
		 * @memberOf egw
		 */
		link_get_registry: function(_app, _name)
		{
			if (typeof link_registry !== 'object')
			{
				alert('egw.open() link registry is NOT defined!');
				return false;
			}
			if (typeof link_registry[_app] === 'undefined')
			{
				return false;
			}
			const reg = link_registry[_app];

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
			if (reg && typeof _name === 'undefined')
			{
				// No key requested, return the whole thing
				return reg;
			}
			return typeof reg[_name] === 'undefined' ? false : reg[_name];
		},

		/**
		 * Get mime-type information from app-registry
		 *
		 * We prefer a full match over a wildcard like 'text/*' (written as regualr expr. "/^text\\//"
		 *
		 * @param {string} _type
		 * @return {object} with values for keys 'menuaction', 'mime_id' (path) or 'mime_url' and options 'mime_popup' and other values to pass one
		 */
		get_mime_info: function(_type)
		{
			let wildcard_mime;
			for(var app in link_registry)
			{
				const reg = link_registry[app];
				if (typeof reg.mime !== 'undefined')
				{
					for(let mime in reg.mime)
					{
						if (mime === _type) return reg.mime[_type];
						if (mime[0] === '/' && _type.match(new RegExp(mime.substring(1, mime.length-1), 'i')))
						{
							wildcard_mime = reg.mime[mime];
						}
					}
				}
			}
			return wildcard_mime ? wildcard_mime : null;
		},

		/**
		 * Get handler (link-data) for given path and mime-type
		 *
		 * @param {string|object} _path vfs path, egw_link::set_data() id or
		 *	object with attr path, optinal download_url or id, app2 and id2 (path=/apps/app2/id2/id)
		 * @param {string} _type mime-type, if not given in _path object
		 * @return {string|object} string with EGw relative link, array with get-parameters for '/index.php' or null (directory and not filemanager access)
		 */
		mime_open: function(_path, _type)
		{
			let path;
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
			let mime_info = this.get_mime_info(_type);
			let data = {};
			if (mime_info)
			{
				if (this.isCollaborable(_type))
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
							data[mime_info.mime_url] = 'vfs://default' + path;
							break;
						case 'mime_data':
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
		},

		/**
		 * Get list of link-aware apps the user has rights to use
		 *
		 * @param {string} _must_support capability the apps need to support, eg. 'add', default ''=list all apps
		 * @return {object} with app => title pairs
		 */
		link_app_list: function(_must_support)
		{
			let apps = [];
			for (let type in link_registry)
			{
				const reg = link_registry[type];

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
			const sorted = {};
			for(let i = 0; i < apps.length; ++i)
			{
				sorted[apps[i].type] = apps[i].label;
			}
			return sorted;
		},

		/**
		 * Set link registry
		 *
		 * @param {object} _registry whole registry or entries for just one app
		 * @param {string} _app
		 * @param {boolean} _need_clone _images need to be cloned, as it is from different window context
		 *	and therefore will be inaccessible in IE, after that window is closed
		 */
		set_link_registry: function (_registry, _app, _need_clone)
		{
			if (typeof _app === 'undefined')
			{
				link_registry = _need_clone ? jQuery.extend(true, {}, _registry) : _registry;
			}
			else
			{
				link_registry[_app] = _need_clone ? jQuery.extend(true, {}, _registry) : _registry;
			}
		},

		/**
		 * Generate a url which supports url or cookies based sessions
		 *
		 * Please note, the values of the query get url encoded!
		 *
		 * @param {string} _url a url relative to the egroupware install root, it can contain a query too or
		 *	full url containing a schema and "://"
		 * @param {object|string} _extravars query string arguements as string or array (prefered)
		 * 	if string is used ambersands in vars have to be already urlencoded as '%26', function ensures they get NOT double encoded
		 * @return {string} generated url
		 */
		link: function(_url, _extravars)
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
					const app = window.egw_appName;
					if (app != 'login' && app != 'logout') _url = app+'/'+_url;
				}
				// append the url to the webserver url, if not already contained or empty
				if (this.webserverUrl && this.webserverUrl != '/' && _url.indexOf(this.webserverUrl+'/') != 0)
				{
					_url = this.webserverUrl + _url;
				}
			}
			const vars = {};

			// check if the url already contains a query and ensure that vars is an array and all strings are in extravars
			const url_othervars = _url.split('?',2);
			_url = url_othervars[0];
			const othervars = url_othervars[1];
			if (_extravars && typeof _extravars == 'object')
			{
				jQuery.extend(vars, _extravars);
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
				_extravars = _extravars.split('&');
				for(let i=0; i < _extravars.length; ++i)
				{
					const name_val = _extravars[i].split('=', 2);
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
		},

		/**
		 * Query a title of _app/_id
		 *
		 * Deprecated default of returning string or null for no callback, will change in future to always return a Promise!
		 *
		 * @param {string} _app
		 * @param {string|number} _id
		 * @param {boolean|function|undefined} _callback true to always return a promise, false: just lookup title-cache or optional callback
		 * 	NOT giving either a boolean value or a callback is deprecated!
		 * @param {object|undefined} _context context for the callback
		 * @param {boolean} _force_reload true load again from server, even if already cached
		 * @return {Promise|string|null} Promise for _callback given (function or true), string with title if it exists in local cache or null if not
		 */
		link_title: function(_app, _id, _callback, _context, _force_reload)
		{
			// check if we have a cached title --> return it direct
			if (typeof title_cache[_app] !== 'undefined' && typeof title_cache[_app][_id] !== 'undefined' && _force_reload !== true)
			{
				if (typeof _callback === 'function')
				{
					_callback.call(_context, title_cache[_app][_id]);
				}
				if (_callback)
				{
					return Promise.resolve(title_cache[_app][_id]);
				}
				return title_cache[_app][_id];
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
			if (typeof title_queue[_app] === 'undefined')
			{
				title_queue[_app] = {};
			}
			if (typeof title_queue[_app][_id] === 'undefined')
			{
				title_queue[_app][_id] = [];
			}
			let promise = new Promise(_resolve => {
				title_queue[_app][_id].push({callback: _resolve, context: _context});
			});
			if (typeof _callback === 'function')
			{
				promise = promise.then(_data => {
					_callback.bind(_context)(_data);
					return _data;
				});
			}
			// if there's no active jsonq request, start a new one
			if (title_uid === null)
			{
				title_uid = this.jsonq('EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_titles',[{}], undefined, this, this.link_title_before_send)
					.then(_response => this.link_title_callback(_response));
			}
			return promise;
		},

		/**
		 * Callback to add all current title requests
		 *
		 * @param {object} _params of parameters, only first parameter is used
		 */
		link_title_before_send: function(_params)
		{
			// add all current title-requests
			for(let app in title_queue)
			{
				for(let id in title_queue[app])
				{
					if (typeof _params[0][app] === 'undefined')
					{
						_params[0][app] = [];
					}
					_params[0][app].push(id);
				}
			}
			title_uid = null;	// allow next request to jsonq
		},

		/**
		 * Callback for server response
		 *
		 * @param {object} _response _app => _id => title
		 */
		link_title_callback: function(_response)
		{
			if (typeof _response !== 'object')
			{
				throw "Wrong parameter for egw.link_title_callback!";
			}
			for(let app in _response)
			{
				if (typeof title_cache[app] !== 'object')
				{
					title_cache[app] = {};
				}
				for (let id in _response[app])
				{
					const title = _response[app][id];
					// cache locally
					title_cache[app][id] = title;
					// call callbacks waiting for title of app/id
					if (typeof title_queue[app] !== 'undefined' && typeof title_queue[app][id] !== "undefined")
					{
						for(let i=0; i < title_queue[app][id].length; ++i)
						{
							const callback = title_queue[app][id][i];
							callback.callback.call(callback.context, title);
						}
						delete title_queue[app][id];
					}
				}
			}
		},

		/**
		 * Create quick add selectbox
		 *
		 * @param {DOMnode} _parent parent to create selectbox in
		 */
		link_quick_add: function(_parent)
		{
			// check if quick-add selectbox is already there, only create it again if not
			if (document.getElementById('quick_add_selectbox') || egwIsMobile())
			{
				return;
			}

			// Use node as the trigger
			const parent = document.getElementById(_parent);
			const select = document.createElement('et2-select');
			select.setAttribute('id', 'quick_add_selectbox');
			// Empty label is required to clear value, but we hide it
			select.emptyLabel = "Select";
			select.placement = "bottom end";
			parent.append(select);
			const plus = parent.querySelector("span");
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
				let options = [];
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
				/*
				select.updateComplete.then(() =>
				{
					// Adjust popup positioning to account for hidden select parts
					select.popup.distance = -32;
				});

				 */
			});
		},

		/**
		 * Check if a mimetype is editable
		 *
		 * Check mimetype & user preference
		 */
		isEditable: function (mime)
		{
			if (!mime)
			{
				return false;
			}
			let fe = this.file_editor_prefered_mimes(mime);
			if (!fe || !fe.mime || fe && fe.mime && !fe.mime[mime])
			{
				return false;
			}
			return ['edit'].indexOf(fe.mime[mime].name) !== -1;
		},

		/**
		 * Check if a mimetype is openable in Collabora
		 * (without needing to have Collabora JS loaded)
		 *
		 * @param mime
		 *
		 * @return string|false
		 */
		isCollaborable: function (mime)
		{
			if (typeof this.user('apps')['collabora'] == "undefined")
			{
				return false;
			}

			// Additional check to see if Collabora can open the file at all, not just edit it
			let fe = this.file_editor_prefered_mimes(mime);
			if (fe && fe.mime && fe.mime[mime] && fe.mime[mime].name || this.isEditable(mime))
			{
				return fe.mime[mime].name;
			}
		}
	}
});