/**
 * EGroupware clientside API: opening of windows, popups or application entries
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
	egw_links;
*/

egw.extend('open', egw.MODULE_WND_LOCAL, function(_egw, _wnd) {
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
		 * @param string|int|object id_data either just the id or if app=="" "app:id" or object with all data
		 * 	to be able to open files you need to give: (mine-)type, path or id, app2 and id2 (path=/apps/app2/id2/id"
		 * @param string app app-name or empty (app is part of id)
		 * @param string type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
		 * @param object|string extra extra url parameters to append as object or string
		 * @param string target target of window to open
		 */
		open: function(id_data, app, type, extra, target)
		{
			var id;
			if(typeof target === 'undefined')
			{
				target = '_blank';
			}
			if (!app)
			{
				if (typeof id_data != 'object')
				{
					var app_id = id.split(':',2);
					app = app_id[0];
					id = app_id[1];
				}
				else
				{
					app = id_data.app;
					id = id_data.id;
				}
			}
			else if (app != 'file')
			{
				id_data = { 'id': id, 'app': app, 'extra': extra };
			}
			var url;
			var popup;
			var params;
			if (app == 'file')
			{
				url = this.mime_open(id_data);
				if (typeof url == 'object')
				{
			 		if(typeof url.mime_popup != 'undefined')
					{
						popup = url.mime_popup;
						delete url.mime_popup;
					}
			 		if(typeof url.mime_target != 'undefined')
					{
						target = url.mime_target;
						delete url.mime_target;
					}
					params = url;
					url = '/index.php';
				}
			}
			else
			{
				var app_registry = this.link_get_registry(app);
				
				if (!app || !app_registry)
				{
					alert('egw.open() app "'+app+'" NOT defined in link registry!');
					return;	
				}
				if (typeof type == 'undefined') type = 'edit';
				if (type == 'edit' && typeof app_registry.edit == 'undefined') type = 'view';
				if (typeof app_registry[type] == 'undefined')
				{
					alert('egw.open() type "'+type+'" is NOT defined in link registry for app "'+app+'"!');
					return;	
				}
				url = '/index.php';
				params = app_registry[type];
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
				
				if (typeof extra == 'string')
				{
					url += '?'+extra;
				}
				else if (typeof extra == 'object')
				{
					$j.extend(params, extra);
				}
				popup = app_registry[type+'_popup'];
			}
			this.open_link(this.link(url, params), target, popup);
		},
		
		/**
		 * Open a link, which can be either a menuaction, a EGroupware relative url or a full url
		 * 
		 * @param string _link menuaction, EGroupware relative url or a full url (incl. "mailto:" or "javascript:")
		 * @param string _target optional target
		 * @param string _popup widthxheight, if a popup should be used
		 */
		open_link: function(_link, _target, _popup)
		{
			var url = _link;
			if (url.indexOf('javascript:') == 0)
			{
				eval(url.substr(11));
				return;
			}
			// link is not necessary an url, it can also be a menuaction!
			if (url.indexOf('/') == -1 && url.split('.').length >= 3 &&
				!(url.indexOf('mailto:') == 0 || url.indexOf('/index.php') == 0 || url.indexOf('://') != -1))
			{
				url = "/index.php?menuaction="+url;
			}
			// append the url to the webserver url, if not already contained or empty
			if (url[0] == '/' && this.webserverUrl && this.webserverUrl != '/' && url.indexOf(this.webserverUrl+'/') != 0)
			{
				url = this.webserverUrl + url;
			}
			if (_popup)
			{
				var w_h = _popup.split('x');
				if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = egw_getWindowOuterHeight();
				_wnd.egw_openWindowCentered2(url, _target, w_h[0], w_h[1]);
			}
			else
			{
				_wnd.open(url, _target);
			}
		}
	};
});

