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
 * - preferences
 * - configuration
 * - link registry
 */
if (window.opener && typeof window.opener.egw == 'object')
{
	egw = window.opener.egw;
}
else if (window.top == 'object' && window.top.egw == 'object')
{
	egw = window.top.egw;
}
else
{
	egw = {
		/**
		 * Object holding the prefences as 2-dim. associative array, use egw.preference(name[,app]) to access it
		 */
		prefs: {
			common: { 
				dateformat: "Y-m-d", 
				timeformat: 24,
				lang: "en"
			}
		},
	
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
		 * @param string _name name of the preference, eg. 'dateformat'
		 * @param string _app='common'
		 * @return string preference value
		 */
		preference: function(_name, _app) 
		{
			if (typeof app == 'undefined') _app = 'common';
			
			if (typeof this.prefs[_app] == 'undefined')
			{
				throw 'Prefs for application "'+_app+'" are NOT loaded!';
			}
			return this.prefs[_app][_name];
		},
		
		/**
		 * Translations
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
			return _msg;
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
			var url = egw_webserverUrl+'/index.php';
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
		 * Link registry
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
		}
	};
}