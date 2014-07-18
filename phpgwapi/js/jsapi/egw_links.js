/**
 * EGroupware clientside API: link-registry, link-titles, generation links
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

/**
 * @augments Class
 */
egw.extend('links', egw.MODULE_GLOBAL, function() {

	/**
	 * Link registry
	 *
	 * @access: private, use egw.open() or egw.set_link_registry()
	 */
	var link_registry = null;

	/**
	 * Local cache for link-titles
	 *
	 * @access private, use egw.link_title(_app, _id[, _callback, _context])
	 */
	var title_cache = {};

	/**
	 * Queue for link_title requests
	 *
	 * @access private, use egw.link_title(_app, _id[, _callback, _context])
	 * @var object _app._id.[{callback: _callback, context: _context}[, ...]]
	 */
	var title_queue = {};

	/**
	 * Uid of active jsonq request, to not start an other one, as we get notified
	 * before it's actually send to the server via our link_title_before_send callback.
	 * @access private
	 */
	var title_uid = null;

	return {
		/**
		 * Check if $app is in the registry and has an entry for $name
		 *
		 * @param string $app app-name
		 * @param string $name name / key in the registry, eg. 'view'
		 * @return boolean|string false if $app is not registered, otherwise string with the value for $name
		 * @memberOf egw
		 */
		link_get_registry: function(_app, _name)
		{
			if (typeof link_registry != 'object')
			{
				alert('egw.open() link registry is NOT defined!');
				return false;
			}
			if (typeof link_registry[_app] == 'undefined')
			{
				return false;
			}
			var reg = link_registry[_app];

			// some defaults (we set them directly in the registry, to do this only once)
			if (typeof reg[_name] == 'undefined')
			{
				switch(_name)
				{
					case 'name':
						reg.name = _app;
						break;
					case 'icon':
						var app_data = this.app(_app);
						if (typeof app_data != 'undefined' &&
							typeof app_data.icon != 'undefined' && app_data.icon != null)
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
			if (reg && typeof _name == 'undefined')
			{
				// No key requested, return the whole thing
				return reg;
			}
			return typeof reg[_name] == 'undefined' ? false : reg[_name];
		},

		/**
		 * Get mime-type information from app-registry
		 *
		 * @param string _type
		 * @return array with values for keys 'menuaction', 'mime_id' (path) or 'mime_url' and options 'mime_popup' and other values to pass one
		 */
		get_mime_info: function(_type)
		{
			for(var app in link_registry)
			{
				var reg = link_registry[app];
				if (typeof reg.mime != 'undefined')
				{
					for(var mime in reg.mime)
					{
						if (mime == _type) return reg.mime[_type];
					}
				}
			}
			return null;
		},

		/**
		 * Get handler (link-data) for given path and mime-type
		 *
		 * @param string|object _path vfs path or object with attr path or id, app2 and id2 (path=/apps/app2/id2/id)
		 * @param string _type mime-type, if not given in _path object
		 * @return string|object string with EGw relative link, array with get-parameters for '/index.php' or null (directory and not filemanager access)
		 */
		mime_open: function(_path, _type)
		{
			var path;
			if (typeof _path == 'object')
			{
				if (typeof _path.path == 'undefined')
				{
					path = '/apps/'+_path.app2+'/'+_path.id2+'/'+_path.id;
				}
				else
				{
					path = _path.path;
				}
				if (typeof _path.type == 'string')
				{
					_type = _path.type;
				}
			}
			else
			{
				path = _path;
			}
			var mime_info = this.get_mime_info(_type);
			if (mime_info)
			{
				var data = {};
				for(var attr in mime_info)
				{
					switch(attr)
					{
						case 'mime_url':
							data[mime_info.mime_url] = 'vfs://default' + path;
							break;
						case 'mime_id':
							data[mime_info.mime_id] = path;
							break;
						default:
							data[attr] = mime_info[attr];
					}
				}
			}
			else
			{
				var data = '/webdav.php' + path;
			}
			return data;
		},

		/**
		 * Get list of link-aware apps the user has rights to use
		 *
		 * @param string $must_support capability the apps need to support, eg. 'add', default ''=list all apps
		 * @return array with app => title pairs
		 */
		link_app_list: function(_must_support)
		{
			var apps = [];
			for (var type in link_registry)
			{
				var reg = link_registry[type];

				if (typeof _must_support != 'undefined' && _must_support && typeof reg[_must_support] == 'undefined') continue;

				var app_sub = type.split('-');
				if (this.app(app_sub[0]))
				{
					apps.push({"type": type, "label": this.lang(this.link_get_registry(type,'name'))});
				}
			}
			// sort labels (caseinsensitive) alphabetic
			apps = apps.sort(function(_a,_b) {
				var al = _a.label.toUpperCase();
				var bl = _b.label.toUpperCase();
				return al == bl ? 0 : (al > bl ? 1 : -1);
			});
			// create sorted associative array / object
			var sorted = {};
			for(var i = 0; i < apps.length; ++i)
			{
				sorted[apps[i].type] = apps[i].label;
			}
			return sorted;
		},

		/**
		 * Set link registry
		 *
		 * @param object _registry whole registry or entries for just one app
		 * @param string _app
		 */
		set_link_registry: function (_registry, _app)
		{
			if (typeof _app == 'undefined')
			{
				link_registry = _registry;
			}
			else
			{
				link_registry[_app] = _registry;
			}
		},

		/**
		 * Generate a url which supports url or cookies based sessions
		 *
		 * Please note, the values of the query get url encoded!
		 *
		 * @param string _url a url relative to the egroupware install root, it can contain a query too
		 * @param array|string _extravars query string arguements as string or array (prefered)
		 * 	if string is used ambersands in vars have to be already urlencoded as '%26', function ensures they get NOT double encoded
		 * @return string generated url
		 */
		link: function(_url, _extravars)
		{
			if (_url[0] != '/')
			{
				alert("egw.link('"+_url+"') called with url starting NOT with a slash!");
				var app = window.egw_appName;
				if (app != 'login' && app != 'logout') _url = app+'/'+_url;
			}
			// append the url to the webserver url, if not already contained or empty
			if (this.webserverUrl && this.webserverUrl != '/' && _url.indexOf(this.webserverUrl+'/') != 0)
			{
				_url = this.webserverUrl + _url;
			}

			var vars = {};

			// check if the url already contains a query and ensure that vars is an array and all strings are in extravars
			var url_othervars = _url.split('?',2);
			_url = url_othervars[0];
			var othervars = url_othervars[1];
			if (_extravars && typeof _extravars == 'object')
			{
				$j.extend(vars, _extravars);
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
				for(var i=0; i < _extravars.length; ++i)
				{
					var name_val = _extravars[i].split('=',2);
					var name = name_val[0];
					var val = name_val[1] || '';
					if (val.indexOf('%26') != -1) val = val.replace(/%26/g,'&');	// make sure to not double encode &
					if (name.lastIndexOf('[]') == name.length-2)
					{
						name = name.substr(0,name.length-2);
						if (typeof vars[name] == 'undefined') vars[name] = [];
						vars[name].push(val);
					}
					else
					{
						vars[name] = val;
					}
				}
			}

			// if there are vars, we add them urlencoded to the url
			var query = [];

			// If ajax flag is there, it must be the last one
			var ajax = vars.ajax || false;
			delete vars.ajax;

			for(var name in vars)
			{
				var val = vars[name] || '';	// fix error for eg. null, which is an object!
				if (typeof val == 'object')
				{
					for(var i=0; i < val.length; ++i)
					{
						query.push(name+'[]='+encodeURIComponent(val[i]));
					}
				}
				else
				{
					query.push(name+'='+encodeURIComponent(val));
				}
			}

			// Add ajax flag at the end
			if(ajax)
			{
				query.push('ajax='+encodeURIComponent(ajax));
			}
			return query.length ? _url+'?'+query.join('&') : _url;
		},

		/**
		 * Query a title of _app/_id
		 *
		 * @param {string} _app
		 * @param {(string|int)} _id
		 * @param {function} _callback optinal callback, required if for responses from the server
		 * @param {object} _context context for the callback
		 * @param {boolean} _force_reload true load again from server, even if already cached
		 * @return string|boolean|null string with title if it exist in local cache or null if not
		 */
		link_title: function(_app, _id, _callback, _context, _force_reload)
		{
			// check if we have a cached title --> return it direct
			if (typeof title_cache[_app] != 'undefined' && typeof title_cache[_app][_id] != 'undefined' && _force_reload !== true)
			{
				if (typeof _callback == 'function')
				{
					_callback.call(_context, title_cache[_app][_id]);
				}
				return title_cache[_app][_id];
			}
			// no callback --> return null
			if (typeof _callback != 'function')
			{
				return null;	// not found in local cache and cant do a synchronious request
			}
			// queue the request
			if (typeof title_queue[_app] == 'undefined')
			{
				title_queue[_app] = {};
			}
			if (typeof title_queue[_app][_id] == 'undefined')
			{
				title_queue[_app][_id] = [];
			}
			title_queue[_app][_id].push({callback: _callback, context: _context});
			// if there's no active jsonq request, start a new one
			if (title_uid == null)
			{
				title_uid = this.jsonq(_app+'.etemplate_widget_link.ajax_link_titles.etemplate',[{}], this.link_title_callback, this, this.link_title_before_send);
			}
		},

		/**
		 * Callback to add all current title requests
		 *
		 * @param array of parameters, only first parameter is used
		 */
		link_title_before_send: function(_params)
		{
			// add all current title-requests
			for(var app in title_queue)
			{
				for(var id in title_queue[app])
				{
					if (typeof _params[0][app] == 'undefined')
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
		 * @param object _response _app => _id => title
		 */
		link_title_callback: function(_response)
		{
			if (typeof _response != 'object')
			{
				throw "Wrong parameter for egw.link_title_callback!";
			}
			for(var app in _response)
			{
				if (typeof title_cache[app] != 'object')
				{
					title_cache[app] = {};
				}
				for (var id in _response[app])
				{
					var title = _response[app][id];
					// cache locally
					title_cache[app][id] = title;
					// call callbacks waiting for title of app/id
					if(typeof title_queue[app][id] != "undefined")
					{
						for(var i=0; i < title_queue[app][id].length; ++i)
						{
							var callback = title_queue[app][id][i];
							callback.callback.call(callback.context, title);
						}
					}
					delete title_queue[app][id];
				}
			}
		},

		/**
		 * Create quick add selectbox
		 *
		 * @param _parent parent to create selectbox in
		 * @returns
		 */
		link_quick_add: function(_parent)
		{
			// check if quick-add selectbox is alread there, only create it again if not
			if (document.getElementById('quick_add_selectbox')) return;

			var select = jQuery(document.createElement('select')).attr('id', 'quick_add_selectbox');
			jQuery(typeof _parent == 'string' ? '#'+_parent : _parent).append(select);

			var self = this;
			// bind change handler
			select.change(function(){
				if (this.value) self.open('', this.value, 'add', {}, undefined, this.value);
				this.value = '';
			});
			// need to load common translations for app-names
			this.langRequire(window, [{app: 'common', lang: this.preference('lang')}], function(){
				select.append(jQuery(document.createElement('option')).attr('value', '').text(self.lang('Add')+' ...'));
				var apps = self.link_app_list('add');
				for(var app in apps)
				{
					var option = jQuery(document.createElement('option')).attr('value', app).text(self.lang(apps[app]));
					select.append(option);
				}
			});
		}
	};

});

