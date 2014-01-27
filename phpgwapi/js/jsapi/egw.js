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
	egw_tail;
	egw_inheritance;
//	egw_jquery;
	app_base;
*/

(function(){
	var debug = false;
	var egw_script = document.getElementById('egw_script_id');
	var start_time = (new Date).getTime();
	if(console.timeline) console.timeline("egw");

	// Flag for if this is opened in a popup
	var popup = (window.opener != null);

	window.egw_webserverUrl = egw_script.getAttribute('data-url');
	window.egw_appName = egw_script.getAttribute('data-app');

	// check if egw object was injected by window open
	if (typeof window.egw == 'undefined')
	{
		try {
			// try finding it in top or opener's top
			if (window.opener && typeof window.opener.top.egw != 'undefined')
			{
				window.egw = window.opener.top.egw;
				if (typeof window.opener.top.framework != 'undefined') window.framework = window.opener.top.framework;
				popup = true;
				if (debug) console.log('found egw object in opener');
			}
		}
		catch(e) {
			// ignore SecurityError exception if opener is different security context / cross-origin
		}
		if (typeof window.egw != 'undefined')
		{
			// set in above try block
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
		try {
			// try finding it in top or opener's top
			if (window.opener && typeof window.opener.top.framework != 'undefined')
			{
				window.framework = window.opener.top.framework;
				if (debug) console.log('found framework object in opener top');
			}
		}
		catch(e) {
			// ignore SecurityError exception if opener is different security context / cross-origin
		}
		if (typeof window.framework != 'undefined')
		{
			// set in above try block
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
		// If there's a message & opener, set it
		if(window.opener && egw_script.getAttribute('data-message'))
		{
			var data = {};
			if ((data = egw_script.getAttribute('data-message')) && (data = JSON.parse(data)))
			{
				window.opener.egw_message.apply(window.opener, data);
			}
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

	window.egw_LAB.script(include).wait(function()
	{
		if(console.timelineEnd) console.timelineEnd("egw");
		var end_time = (new Date).getTime();
		var gen_time_div = $j('#divGenTime_'+window.egw_appName);
		if (!gen_time_div.length) gen_time_div = $j('.pageGenTime');
		gen_time_div.append('<span class="asyncIncludeTime">'+egw.lang('async includes took %1s', (end_time-start_time)/1000)+'</span>');

		// Make sure opener knows when we close - start a heartbeat
		if((popup || window.opener) && window.name != '')
		{
			// Timeout is 5 seconds, but it iks only applied(egw_utils) when something asks for the window list
			window.setInterval(function() {
				egw().storeWindow(this.egw_appName, this);
			}, 2000);
		}

		// instanciate app object
		var appname = window.egw_appName;
		if (window.app && window.app[appname] != 'object' && typeof window.app.classes[appname] == 'function')
		{
			window.app[appname] = new window.app.classes[appname]();
		}

		// set sidebox for tabed templates
		var sidebox = egw_script.getAttribute('data-setSidebox');
		if (window.framework && sidebox)
		{
			window.framework.setSidebox.apply(window.framework, JSON.parse(sidebox));
		}

		// load et2
		var data = egw_script.getAttribute('data-etemplate');
		if (data)
		{
			// Initialize application js
			var callback = null;
			// Only initialize once
			if(typeof app[window.egw_appName] == "object")
			{
				callback = function(et2) {app[window.egw_appName].et2_ready(et2);};
			}
			else
			{
				egw.debug("warn", "Did not load '%s' JS object",window.egw_appName);
			}
			// Wait until DOM loaded before we load the etemplate to make sure the target is there
			$j(function() {
				// Re-load data here, as later code may change the variable
				var data = JSON.parse(egw_script.getAttribute('data-etemplate')) || {};
				var node = document.getElementById(data.DOMNodeID);
				if(!node)
				{
					egw.debug("error", "Could not find target node %s", data.DOMNodeID);
				}
				else
				{
					if(popup || window.opener)
					{
						// Resize popup when et2 load is done
						jQuery(node).one("load",function() {
							window.resizeTo(jQuery(document).width()+10,jQuery(document).height()+70);
						});
					}
					var et2 = new etemplate2(node, window.egw_appName+".etemplate_new.ajax_process_content.etemplate");
					et2.load(data.name,data.url,data.data,callback);
				}
			});
		}
		$j(function() {
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
