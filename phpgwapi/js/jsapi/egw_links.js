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
	egw_link;
*/

egw().extend('links', function() {

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
		 * View an EGroupware entry: opens a popup of correct size or redirects window.location to requested url
		 * 
		 * Examples: 
		 * - egw.open(123,'infolog') or egw.open('infolog:123') opens popup to edit or view (if no edit rights) infolog entry 123
		 * - egw.open('infolog:123','timesheet','add') opens popup to add new timesheet linked to infolog entry 123
		 * - egw.open(123,'addressbook','view') opens addressbook view for entry 123 (showing linked infologs)
		 * - egw.open('','addressbook','view_list',{ search: 'Becker' }) opens list of addresses containing 'Becker'
		 * 
		 * @param string|int id either just the id or "app:id" if app==""
		 * @param string app app-name or empty (app is part of id)
		 * @param string type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
		 * @param object|string extra extra url parameters to append as object or string
		 * @param string target target of window to open
		 */
		open: function(id, app, type, extra, target)
		{
			if (typeof link_registry != 'object')
			{
				alert('egw.open() link registry is NOT defined!');
				return;
			}
			if (!app)
			{
				var app_id = id.split(':',2);
				app = app_id[0];
				id = app_id[1];
			}
			if (!app || typeof link_registry[app] != 'object')
			{
				alert('egw.open() app "'+app+'" NOT defined in link registry!');
				return;	
			}
			var app_registry = link_registry[app];
			if (typeof type == 'undefined') type = 'edit';
			if (type == 'edit' && typeof app_registry.edit == 'undefined') type = 'view';
			if (typeof app_registry[type] == 'undefined')
			{
				alert('egw.open() type "'+type+'" is NOT defined in link registry for app "'+app+'"!');
				return;	
			}
			var url = this.webserverUrl+'/index.php';
			var delimiter = '?';
			var params = app_registry[type];
			if (type == 'view' || type == 'edit')	// add id parameter for type view or edit
			{
				params[app_registry[type+'_id']] = id;
			}
			else if (type == 'add' && id)	// add add_app and app_id parameters, if given for add
			{
				var app_id = id.split(':',2);
				params[app_registry.add_app] = app_id[0];
				params[app_registry.add_id] = app_id[1];
			}
			for(var attr in params)
			{
				url += delimiter+attr+'='+encodeURIComponent(params[attr]);
				delimiter = '&';
			}
			if (typeof extra == 'object')
			{
				for(var attr in extra)
				{
					url += delimiter+attr+'='+encodeURIComponent(extra[attr]);
				}
			}
			else if (typeof extra == 'string')
			{
				url += delimiter + extra;
			}
			if (typeof app_registry[type+'_popup'] == 'undefined')
			{
				if (target)
				{
					window.open(url, target);
				}
				else
				{
					egw_appWindowOpen(app, url);
				}
			}
			else
			{
				var w_h = app_registry[type+'_popup'].split('x');
				if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = egw_getWindowOuterHeight();
				egw_openWindowCentered2(url, target, w_h[0], w_h[1], 'yes', app, false);
			}
		},

		/**
		 * Check if $app is in the registry and has an entry for $name
		 *
		 * @param string $app app-name
		 * @param string $name name / key in the registry, eg. 'view'
		 * @return boolean|string false if $app is not registered, otherwise string with the value for $name
		 */
		link_get_registry: function(_app, _name)
		{
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
						if (typeof app_data != 'undefined' && typeof app_data.icon != 'undefined')
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
			return typeof reg[_name] == 'undefined' ? false : reg[_name];
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
		 * Call a link, which can be either a menuaction, a EGroupware relative url or a full url
		 * 
		 * @param string _link menuaction, EGroupware relative url or a full url (incl. "mailto:" or "javascript:")
		 * @param string _target optional target
		 * @param string _popup widthxheight, if a popup should be used
		 */
		call_link: function(_link, _target, _popup)
		{
			var url = _link;
			if (url.indexOf('javascript:') == 0)
			{
				eval(url.substr(11));
				return;
			}
			// link is not necessary an url, it can also be a menuaction!
			if (url.indexOf('/') == -1 &&
				url.split('.').length >= 3 &&
				url.indexOf('mailto:') == -1 ||
				url.indexOf('://') == -1)
			{
				url = "/index.php?menuaction="+url;
			}
			if (url[0] == '/')		// link relative to eGW
			{
				url = this.webserverUrl + url;
			}
			if (_popup)
			{
				var w_h = _popup.split('x');
				if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = egw_getWindowOuterHeight();
				egw_openWindowCentered2(url, _target, w_h[0], w_h[1]);
			}
			else
			{
				window.open(url, _target);
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
				var app = window.egw_appName;
				if (app != 'login' && app != 'logout') _url = app+'/'+_url;
			}
			// append the url to the webserver url, but avoid more then one slash between the parts of the url
			var webserver_url = this.webserverUrl;
			// patch inspired by vladimir kolobkov -> we should not try to match the webserver url against the url without '/' as delimiter,
			// as webserver_url may be part of _url (as /egw is part of phpgwapi/js/egw_instant_load.html)
			if ((_url[0] != '/' || webserver_url != '/') && (!webserver_url || _url.indexOf(webserver_url+'/') == -1))
			{
				if(_url[0] != '/' && webserver_url.lastIndexOf('/') != webserver_url.length-1)
				{
					_url = webserver_url +'/'+ _url;
				}
				else
				{
					_url = webserver_url + _url;
				}
			}
	
			var vars = {};
			/* not sure we still need to support that
			// add session params if not using cookies
			if (!$GLOBALS['egw_info']['server']['usecookies'])
			{
				$vars['sessionid'] = $GLOBALS['egw']->session->sessionid;
				$vars['kp3'] = $GLOBALS['egw']->session->kp3;
				$vars['domain'] = $GLOBALS['egw']->session->account_domain;
			}*/
	
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
				if (othervars) _extravars += (_extravars?'&':'').othervars;
			}
	
			// parse extravars string into the vars array
			if (_extravars)
			{
				_extravars = _extravars.split('&');
				for(var i=0; i < _extravars.length; ++i)
				{
					var name_val = _extravars[i].split('=',2);
					var name = name_val[0];
					var val = name_val[1];
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
			for(var name in vars)
			{
				var val = vars[name];
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
			return query.length ? _url+'?'+query.join('&') : _url;
		},

		/**
		 * Query a title of _app/_id
		 * 
		 * @param string _app
		 * @param string|int _id
		 * @param function _callback optinal callback, required if for responses from the server
		 * @param object _context context for the callback
		 * @return string|boolean|null string with title if it exist in local cache or null if not
		 */
		link_title: function(_app, _id, _callback, _context)
		{
			// check if we have a cached title --> return it direct
			if (typeof title_cache[_app] != 'undefined' && typeof title_cache[_app][_id] != 'undefined')
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
				if (typeof _params[0][app] == 'undefined')
				{
					_params[0][app] = [];
				}
				for(var id in title_queue[app])
				{
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
					for(var i=0; i < title_queue[app][id].length; ++i)
					{
						var callback = title_queue[app][id][i];
						callback.callback.call(callback.context, title);
					}
					delete title_queue[app][id];
				}
			}
		}
	};

});

