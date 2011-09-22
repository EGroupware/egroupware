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

var egw;

/**
 * Central object providing all kinds of api services on clientside:
 * - preferences:   egw.preferences(_name, _app)
 * - translation:   egw.lang("%1 entries deleted", 5)
 * - link registry: egw.open(_id, _app), egw.link_get_registry(_app, _name), egw.link_app_list(_must_support)
 * - configuration: egw.config(_name[, _app='phpgwapi'])
 * - image urls:    egw.image(_name[, _app='phpgwapi'])
 * - user data:     egw.user(_field)
 * - user app data: egw.app(_app[, _name])
 */
if (window.opener && typeof window.opener.egw == 'object')
{
	egw = window.opener.egw;
}
else if (window.top && typeof window.top.egw == 'object')
{
	egw = window.top.egw;
}
else
{
	egw = {
		/**
		 * Object holding the prefences as 2-dim. associative array, use egw.preference(name[,app]) to access it
		 * 
		 * @access: private, use egw.preferences() or egw.set_perferences()
		 */
		prefs: {
			common: { 
				dateformat: "Y-m-d", 
				timeformat: 24,
				lang: "en"
			}
		},
		
		/**
		 * base-URL of the EGroupware installation
		 * 
		 * get set via egw_framework::header()
		 */
		webserverUrl: "/egroupware",
	
		/**
		 * Setting prefs for an app or 'common'
		 * 
		 * @param object _data object with name: value pairs to set
		 * @param string _app application name, 'common' or undefined to prefes of all apps at once
		 */
		set_preferences: function(_data, _app)
		{
			if (typeof _app == 'undefined')
			{
				this.prefs = _data;
			}
			else
			{
				this.prefs[_app] = _data;
			}
		},
	
		/**
		 * Query an EGroupware user preference
		 * 
		 * If a prefernce is not already loaded (only done for "common" by default), it is synchroniosly queryed from the server!
		 * 
		 * @param string _name name of the preference, eg. 'dateformat'
		 * @param string _app='common'
		 * @return string preference value
		 * @todo add a callback to query it asynchron
		 */
		preference: function(_name, _app) 
		{
			if (typeof _app == 'undefined') _app = 'common';
			
			if (typeof this.prefs[_app] == 'undefined')
			{
				xajax_doXMLHTTPsync('home.egw_framework.ajax_get_preference.template', _app);
				
				if (typeof this.prefs[_app] == 'undefined') this.prefs[_app] = {};
			}
			return this.prefs[_app][_name];
		},
		
		/**
		 * Set a preference and sends it to the server
		 * 
		 * Server will silently ignore setting preferences, if user has no right to do so!
		 * 
		 * @param string _app application name or "common"
		 * @param string _name name of the pref
		 * @param string _val value of the pref
		 */
		set_preference: function(_app, _name, _val)
		{
			xajax_doXMLHTTP('home.egw_framework.ajax_set_preference.template', _app, _name, _val);
			
			// update own preference cache, if _app prefs are loaded (dont update otherwise, as it would block loading of other _app prefs!)
			if (typeof this.prefs[_app] != 'undefined') this.prefs[_app][_name] = _val;
		},
		
		/**
		 * Translations
		 * 
		 * @access: private, use egw.lang() or egw.set_lang_arr()
		 */
		lang_arr: {},
		
		/**
		 * Set translation for a given application
		 * 
		 * @param string _app
		 * @param object _message message => translation pairs
		 */
		set_lang_arr: function(_app, _messages)
		{
			this.lang_arr[_app] = _messages;
		},
		
		/**
		 * Translate a given phrase replacing optional placeholders
		 * 
		 * @param string _msg message to translate
		 * @param string _arg1 ... _argN
		 */
		lang: function(_msg, _arg1)
		{
			var translation = _msg;
			_msg = _msg.toLowerCase();
			
			// search apps in given order for a replacement
			var apps = [window.egw_appName, 'etemplate', 'common'];
			for(var i = 0; i < apps.length; ++i)
			{
				if (typeof this.lang_arr[apps[i]] != "undefined" &&
					typeof this.lang_arr[apps[i]][_msg] != 'undefined')
				{
					translation = this.lang_arr[apps[i]][_msg];
					break;
				}
			}
			if (arguments.length == 1) return translation;
			
			if (arguments.length == 2) return translation.replace('%1', arguments[1]);
			
			// to cope with arguments containing '%2' (eg. an urlencoded path like a referer),
			// we first replace all placeholders '%N' with '|%N|' and then we replace all '|%N|' with arguments[N]
			translation = translation.replace(/%([0-9]+)/g, '|%$1|');
			for(var i = 1; i < arguments.length; ++i)
			{
				translation = translation.replace('|%'+i+'|', arguments[i]);
			}
			return translation;
		},
		
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
			if (typeof this.link_registry != 'object')
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
			if (!app || typeof this.link_registry[app] != 'object')
			{
				alert('egw.open() app "'+app+'" NOT defined in link registry!');
				return;	
			}
			var app_registry = this.link_registry[app];
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
			if (typeof this.link_registry[_app] == 'undefined')
			{
				return false;
			}
			var reg = this.link_registry[_app];
			
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
			for (var type in this.link_registry)
			{
				var reg = this.link_registry[type];
				
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
		 * Link registry
		 * 
		 * @access: private, use egw.open() or egw.set_link_registry()
		 */
		link_registry: null,
		
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
				this.link_registry = _registry;
			}
			else
			{
				this.link_registry[_app] = _registry;
			}
		},
		
		/**
		 * Clientside config
		 * 
		 * @access: private, use egw.config(_name, _app="phpgwapi")
		 */
		configs: {},
		
		/**
		 * Query clientside config
		 * 
		 * @param string _name name of config variable
		 * @param string _app default "phpgwapi"
		 * @return mixed
		 */
		config: function (_name, _app)
		{
			if (typeof _app == 'undefined') _app = 'phpgwapi';

			if (typeof this.configs[_app] == 'undefined') return null;
			
			return this.configs[_app][_name];
		},
		
		/**
		 * Set clientside configuration for all apps
		 * 
		 * @param array/object
		 */
		set_configs: function(_configs)
		{
			this.configs = _configs;
		},
		
		/**
		 * Map to serverside available images for users template-set
		 * 
		 * @access: private, use egw.image(_name, _app)
		 */
		images: {},
		
		/**
		 * Set imagemap, called from /phpgwapi/images.php
		 * 
		 * @param array/object _images
		 */
		set_images: function (_images)
		{
			this.images = _images;
		},
		
		/**
		 * Get image URL for a given image-name and application
		 * 
		 * @param string _name image-name without extension
		 * @param string _app application name, default current app of window
		 * @return string with URL of image
		 */
		image: function (_name, _app)
		{
			// For logging all paths tried
			var tries = {};

			if (typeof _app == 'undefined')
			{
				if(_name.indexOf('/') > 0)
				{
					var split = et2_csvSplit(_value, 2,"/");
					var _app = split[0];
					_name = split[1];
				}
				else
				{
					_app = this.getAppName();
				}
			}
			
			// own instance specific images in vfs have highest precedence
			tries['vfs']=_name;
			if (typeof this.images['vfs'] != 'undefined' && typeof this.images['vfs'][_name] != 'undefined')
			{
				return this.webserverUrl+this.images['vfs'][_name];
			}
			tries[_app + (_app == 'phpgwapi' ? " (current app)" : "")] = _name;
			if (typeof this.images[_app] != 'undefined' && typeof this.images[_app][_name] != 'undefined')
			{
				return this.webserverUrl+this.images[_app][_name];
			}
			tries['phpgwapi'] = _name;
			if (typeof this.images['phpgwapi'] != 'undefined' && typeof this.images['phpgwapi'][_name] != 'undefined')
			{
				return this.webserverUrl+this.images['phpgwapi'][_name];
			}
			// if no match, check if it might contain an extension
			var matches = [];
			if (matches = _name.match(/\.(png|gif|jpg)$/i))
			{
				return this.image(_name.replace(/.(png|gif|jpg)$/i,''), _app);
			}
			if(matches != null) tries[_app + " (matched)"]= matches;
			console.log('egw.image("'+_name+'", "'+_app+'") image NOT found!  Tried ', tries);
			return null;
		},
		
		/**
		 * Returns the name of the currently active application
		 * 
		 * @ToDo: fixme: does not work, as egw object runs in framework for jdots
		 */
		getAppName: function ()
		{
			if (typeof egw_appName == 'undefined')
			{
				return 'egroupware';
			}
			else
			{
				return egw_appName;
			}
		},
		
		/**
		 * Data about current user
		 * 
		 * @access: private, use egw.user(_field) or egw.app(_app)
		 */
		userData: {},
		
		/**
		 * Set data of current user
		 * 
		 * @param object _data
		 */
		set_user: function(_data)
		{
			this.userData = _data;
		},
		
		/**
		 * Get data about current user
		 *
		 * @param string _field
		 * - 'account_id','account_lid','person_id','account_status',
		 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
		 * - 'apps': object with app => data pairs the user has run-rights for
		 * @return string|array|null
		 */
		user: function (_field)
		{
			return this.userData[_field];
		},

		/**
		 * Return data of apps the user has rights to run
		 * 
		 * Can be used the check of run rights like: if (egw.app('addressbook')) { do something if user has addressbook rights }
		 * 
		 * @param string _app
		 * @param string _name attribute to return, default return whole app-data-object
		 * @return object|string|null null if not found
		 */
		app: function(_app, _name)
		{
			return typeof _name == 'undefined' || typeof this.userData.apps[_app] == 'undefined' ? 
				this.userData.apps[_app] : this.userData.apps[_app][_name];
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
		 * Queued json requests (objects with attributes menuaction, parameters, context, callback, sender and callbeforesend)
		 * 
		 * @access private, use jsonq method to queue requests
		 */
		jsonq_queue: {},
		
		/**
		 * Next uid (index) in queue
		 */
		jsonq_uid: 0,
		
		/**
		 * Running timer for next send of queued items
		 */
		jsonq_timer: null,
		
		/**
		 * Send a queued JSON call to the server
		 * 
		 * @param string _menuaction the menuaction function which should be called and
		 *   which handles the actual request. If the menuaction is a full featured
		 *   url, this one will be used instead.
		 * @param array _parameters which should be passed to the menuaction function.
		 * @param function _callback callback function which should be called upon a "data" response is received
		 * @param object _sender is the reference object the callback function should get
		 * @param function _callbeforesend optional callback function which can modify the parameters, eg. to do some own queuing
		 * @return string uid of the queued request
		 */
		jsonq: function(_menuaction, _parameters, _callback, _sender, _callbeforesend)
		{
			var uid = 'u'+(this.jsonq_uid++);
			this.jsonq_queue[uid] = {
				menuaction: _menuaction,
				parameters: _parameters,
				callback: _callback,
				sender: _sender,
				callbeforesend: _callbeforesend
			};
			
			if (this.jsonq_time == null)
			{
				// check / send queue every N ms
				var self = this;
				this.jsonq_timer = window.setInterval(function(){ self.jsonq_send();}, 100);
			}
			return uid;
		},
		
		/**
		 * Send the whole job-queue to the server in a single json request with menuaction=queue
		 */
		jsonq_send: function()
		{
			if (this.jsonq_uid > 0 && typeof this.jsonq_queue['u'+(this.jsonq_uid-1)] == 'object')
			{
				var jobs_to_send = {};
				var something_to_send = false;
				for(var uid in this.jsonq_queue)
				{
					var job = this.jsonq_queue[uid];

					if (job.menuaction == 'send') continue;	// already send to server

					// if job has a callbeforesend callback, call it to allow it to modify pararmeters
					if (typeof job.callbeforesend == 'function')
					{
						job.callbeforesend.call(job.sender, job.parameters);
					}
					jobs_to_send[uid] = {
						menuaction: job.menuaction,
						parameters: job.parameters
					};
					job.menuaction = 'send';
					job.parameters = null;
					something_to_send = true;
				}
				if (something_to_send)
				{
					new egw_json_request('home.queue', jobs_to_send, this).sendRequest(true, this.jsonq_callback, this);
				}
			}
		},
		
		/**
		 * Dispatch responses received
		 * 
		 * @param object _data uid => response pairs
		 */
		jsonq_callback: function(_data)
		{
			if (typeof _data != 'object') throw "jsonq_callback called with NO object as parameter!";

			var json = new egw_json_request('none');
			for(var uid in _data)
			{
				if (typeof this.jsonq_queue[uid] == 'undefined')
				{
					console.log("jsonq_callback received response for not existing queue uid="+uid+"!");
					console.log(_data[uid]);
					continue;
				}
				var job = this.jsonq_queue[uid];
				var response = _data[uid];
				
				// fake egw_json_request object, to call it with the current response
				json.callback = job.callback;
				json.sender = job.sender;
				json.handleResponse({response: response});

				delete this.jsonq_queue[uid];
			}
			// if nothing left in queue, stop interval-timer to give browser a rest
			if (this.jsonq_timer && typeof this.jsonq_queue['u'+(this.jsonq_uid-1)] != 'object')
			{
				window.clearInterval(this.jsonq_timer);
				this.jsonq_timer = null;
			}
		},
		
		/**
		 * Local cache for link-titles
		 * 
		 * @access private, use egw.link_title(_app, _id[, _callback, _context])
		 */
		title_cache: {},
		/**
		 * Queue for link_title requests
		 * 
		 * @access private, use egw.link_title(_app, _id[, _callback, _context])
		 * @var object _app._id.[{callback: _callback, context: _context}[, ...]]
		 */
		title_queue: {},
		/**
		 * Uid of active jsonq request, to not start an other one, as we get notified
		 * before it's actually send to the server via our link_title_before_send callback.
		 * @access private
		 */
		title_uid: null,
		
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
			if (typeof this.title_cache[_app] != 'undefined' && typeof this.title_cache[_app][_id] != 'undefined')
			{
				if (typeof _callback == 'function')
				{
					_callback.call(_context, this.title_cache[_app][_id]);
				}
				return this.title_cache[_app][_id];
			}
			// no callback --> return null
			if (typeof _callback != 'function')
			{
				return null;	// not found in local cache and cant do a synchronious request
			}
			// queue the request
			if (typeof this.title_queue[_app] == 'undefined')
			{
				this.title_queue[_app] = {};
			}
			if (typeof this.title_queue[_app][_id] == 'undefined')
			{
				this.title_queue[_app][_id] = [];
			}
			this.title_queue[_app][_id].push({callback: _callback, context: _context});
			// if there's no active jsonq request, start a new one
			if (this.title_uid == null)
			{
				this.title_uid = this.jsonq(_app+'.etemplate_widget_link.ajax_link_titles.etemplate',[{}], this.link_title_callback, this, this.link_title_before_send);
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
			for(var app in this.title_queue)
			{
				if (typeof _params[0][app] == 'undefined')
				{
					_params[0][app] = [];
				}
				for(var id in this.title_queue[app])
				{
					_params[0][app].push(id);
				}
			}
			this.title_uid = null;	// allow next request to jsonq
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
				if (typeof this.title_cache[app] != 'object') 
				{
					this.title_cache[app] = {};
				}
				for (var id in _response[app])
				{
					var title = _response[app][id];
					// cache locally
					this.title_cache[app][id] = title;
					// call callbacks waiting for title of app/id
					for(var i=0; i < this.title_queue[app][id].length; ++i)
					{
						var callback = this.title_queue[app][id][i];
						callback.callback.call(callback.context, title);
					}
					delete this.title_queue[app][id];
				}
			}
		}
	};
}
