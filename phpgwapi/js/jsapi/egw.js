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
	egw_message;
	app_base;
*/

(function(){
	var debug = false;
	var egw_script = document.getElementById('egw_script_id');
	var start_time = (new Date).getTime();
	if(typeof console != "undefined" && console.timeline) console.timeline("egw");

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
		// call egw.link_handler, if attr specified
		var egw_redirect = egw_script.getAttribute('data-egw-redirect');
		if (egw_redirect)
		{
			// set sidebox for tabed templates, we need to set it now, as framework will not resent it!
			var sidebox = egw_script.getAttribute('data-setSidebox');
			if (window.framework && sidebox)
			{
				window.framework.setSidebox.apply(window.framework, JSON.parse(sidebox));
			}
			egw_redirect = JSON.parse(egw_redirect);
			egw.link_handler.apply(egw, egw_redirect);
			return;	// do NOT execute any further code, as IE(11) will give errors because framework already redirects
		}

		// call egw_refresh on opener, if attr specified
		var refresh_opener = egw_script.getAttribute('data-refresh-opener');
		if (refresh_opener && window.opener)
		{
			refresh_opener = JSON.parse(refresh_opener) || {};
			window.opener.egw(window.opener).refresh.apply(window.opener, refresh_opener);
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
				egw(window.opener).message(JSON.parse(egw_script.getAttribute('data-message')));
			}
			egw(window).close();
		}

		// call egw.open_link, if popup attr specified
		var egw_popup = egw_script.getAttribute('data-popup');
		if (egw_popup)
		{
			egw_popup = JSON.parse(egw_popup) || [];
			egw.open_link.apply(egw, egw_popup);
		}

		if(typeof console != "undefined" && console.timelineEnd) console.timelineEnd("egw");
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
		var sidebox = egw_script.getAttribute('data-setSidebox') || jQuery('#late-sidebox').attr('data-setSidebox');
		if (window.framework && sidebox)
		{
			window.framework.setSidebox.apply(window.framework, JSON.parse(sidebox));
		}

		// rest needs DOM to be ready
		$j(function() {
			// load etemplate2 template(s)
			$j('div.et2_container[data-etemplate]').each(function(index, node){
				var data = JSON.parse(node.getAttribute('data-etemplate')) || {};
				var currentapp = data.data.currentapp || window.egw_appName;
				if(popup || window.opener)
				{
					// Resize popup when et2 load is done
					jQuery(node).on("load",function() {
						window.setTimeout(function() {
							var $main_div = $j('#popupMainDiv');
							var $et2 = $j('.et2_container');
							var w = {
								width: egw_getWindowInnerWidth(),
								height: egw_getWindowInnerHeight()
							};
							// Use et2_container for width since #popupMainDiv is full width, but we still need
							// to take padding/margin into account
							var delta_width = w.width - ($et2.outerWidth(true) + ($main_div.outerWidth(true) - $main_div.width()));
							var delta_height = w.height - ($et2.outerHeight(true) + ($main_div.outerHeight(true) - $main_div.height()));
							if (delta_height && egw_getWindowOuterHeight() >= egw.availHeight())
							{
								delta_height = 0;
							}
							if(delta_width != 0 || delta_height != 0)
							{
								window.resizeTo(egw_getWindowOuterWidth() - delta_width,egw_getWindowOuterHeight() - delta_height);
							}
						}, 200);
					});
				}
				var et2 = new etemplate2(node, currentapp+".etemplate_new.ajax_process_content.etemplate");
				et2.load(data.name,data.url,data.data);
				if (typeof data.response != 'undefined')
				{
					var json_request = egw(window).json();
					json_request.handleResponse({response: data.response});
				}
			});

			// set app-header
			if (window.framework && egw_script.getAttribute('data-app-header'))
			{
				egw(window).app_header(egw_script.getAttribute('data-app-header'), appname);
			}
			// display a message
			if (egw_script.getAttribute('data-message'))
			{
				var params = JSON.parse(egw_script.getAttribute('data-message')) || [''];
				egw(window).message.apply(egw(window), params);
			}
			// hide location bar for mobile browsers
			if (egw_script.getAttribute('data-mobile'))
			{
				window.scrollTo(0, 1);
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

/**
 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
 *
 * @param {string} _func dot-separated function name
 * variable number of arguments
 * @returns {Boolean}
 */
function et2_call(_func)
{
	var args = [].slice.call(arguments);	// convert arguments to array
	var func = args.shift();
	var parent = window;

	if (typeof _func == 'string')
	{
		var parts = _func.split('.');
		func = parts.pop();
		for(var i=0; i < parts.length; ++i)
		{
			if (typeof parent[parts[i]] != 'undefined')
			{
				parent = parent[parts[i]];
			}
			// check if we need a not yet instanciated app.js object --> instanciate it now
			else if (i == 1 && parts[0] == 'app' && typeof window.app.classes[parts[1]] == 'function')
			{
				parent = parent[parts[1]] = new window.app.classes[parts[1]]();
			}
		}
		if (typeof parent[func] == 'function')
		{
			func = parent[func];
		}
	}
	if (typeof func != 'function')
	{
		throw _func+" is not a function!";
	}
	return func.apply(parent, args);
}
