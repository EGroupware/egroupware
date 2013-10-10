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
	egw_debug;
	egw_preferences;
	egw_lang;
	egw_links;
	egw_open;
	egw_user;
	egw_config;
	egw_images;
	egw_jsonq;
	egw_files;
	egw_json;
	egw_store;
	egw_tooltip;
	egw_css;
	egw_calendar;
	egw_ready;
	egw_data;
	egw_inheritance;
//	egw_jquery;
	app_base;
*/

(function(){
	var debug = false;
	var egw_script = document.getElementById('egw_script_id');
	
	// Flag for if this is opened in a popup
	var popup = false;
	
	window.egw_webserverUrl = egw_script.getAttribute('data-url');
	window.egw_appName = egw_script.getAttribute('data-app');

	// check if egw object was injected by window open
	if (typeof window.egw == 'undefined')
	{
		// try finding it in top or opener's top
		if (window.opener && typeof window.opener.top.egw != 'undefined')
		{
			window.egw = window.opener.top.egw;
			if (typeof window.opener.top.framework != 'undefined') window.framework = window.opener.top.framework;
			popup = true;
			if (debug) console.log('found egw object in opener');
		}
		else if (window.top && typeof window.top.egw != 'undefined')
		{
			window.egw = window.top.egw;
			if (typeof window.top.framework != 'undefined') window.framework = window.top.framework;
			if (debug) console.log('found egw object in top');
		}
		else
		{
			window.egw = {
				prefsOnly: true,
				webserverUrl: egw_webserverUrl
			};
			if (debug) console.log('creating new egw object');
		}
	}
	else if (debug) console.log('found injected egw object');
	
	// check for a framework object
	if (typeof window.framework == 'undefined')
	{
		// try finding it in top or opener's top
		if (window.opener && typeof window.opener.top.framework != 'undefined')
		{
			window.framework = window.opener.top.framework;
			if (debug) console.log('found framework object in opener top');
		}
		else if (window.top && typeof window.top.framework != 'undefined')
		{
			window.framework = window.top.framework;
			if (debug) console.log('found framework object in top');
		}
		// if framework not found, but requested to check for it, redirect to cd=yes to create it
		else if (egw_script.getAttribute('data-check-framework'))
		{
			window.location.search += window.location.search ? "&cd=yes" : "?cd=yes";
		}
	}
	
	// call egw_refresh on opener, if attr specified
	var refresh_opener = egw_script.getAttribute('data-refresh-opener');
	if (refresh_opener && window.opener)
	{
		refresh_opener = JSON.parse(refresh_opener) || {};
		window.opener.egw_refresh.apply(window.opener, refresh_opener);
	}
	
	// close window / call window.close(), if data-window-close is specified
	var window_close = egw_script.getAttribute('data-window-close');
	if (window_close)
	{
		if (typeof window_close == 'string' && window_close !== '1')
		{
			alert(window_close);
		}		
		window.close();
	}

	// focus window / call window.focus(), if data-window-focus is specified
	var window_focus = egw_script.getAttribute('data-window-focus');
	if (window_focus && JSON.parse(window_focus))
	{
		window.focus();
	}

	window.egw_LAB = $LAB.setOptions({AlwaysPreserveOrder:true,BasePath:window.egw_webserverUrl+'/'});
	var include = JSON.parse(egw_script.getAttribute('data-include'));
	
	// remove this script from include, until server-side no longer requires it
	for(var i=0; i < include.length; ++i)
	{
		if (include[i].match(/^phpgwapi\/js\/jsapi\/egw\.js/))
		{
			include.splice(i, 1);
			break;
		}
	}
	window.egw_LAB.script(include).wait(function(){			
		// Make sure opener knows when we close - start a heartbeat
		if((popup || window.opener) && window.name != '')
		{
			// Timeout is 5 seconds, but it iks only applied(egw_utils) when something asks for the window list
			window.setInterval(function() {
				egw().storeWindow(this.egw_appName, this);
			}, 2000);
		}
		
		var data = egw_script.getAttribute('data-etemplate');
		if (data)
		{
			data = JSON.parse(data) || {};
			// Initialize application js
			var callback = null;
			// Only initialize once
			if(typeof app[window.egw_appName] == "function")
			{
				(function() { new app[window.egw_appName]();}).call();
			}
			else
			{
				egw.debug("warn", "Did not load '%s' JS object",window.egw_appName); 
			}
			if(typeof app[window.egw_appName] == "object")
			{
				callback = function(et2) {app[window.egw_appName].et2_ready(et2);};
			}
			var node = document.getElementById(data.DOMNodeID);
			if(!node)
			{
				egw.debug("error", "Could not find target node %s", data.DOMNodeID);
			}
			else
			{
				var et2 = new etemplate2(node, "etemplate_new::ajax_process_content");
				et2.load(data.name,data.url,data.data,callback);
			}
		}
		// set app-header
		if (window.framework && (data = egw_script.getAttribute('data-app-header')))
		{
			window.egw_app_header(data);
		}
		// display a message
		if ((data = egw_script.getAttribute('data-message')) && (data = JSON.parse(data)))
		{
			window.egw_message.apply(window, data);
		}
	});
	
	/**
	 * 
	 */
	window.callManual = function()
	{
		if (window.framework)
		{
			window.framework.callManual.call(window.framework, window.location.href);
		}
	};
})();
