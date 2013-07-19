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
		var data = egw_script.getAttribute('data-preferences');
		if (data)
		{
			data = JSON.parse(data) || {};
			for(var app in data)
			{
				window.egw.set_preferences(data[app], app);
			}
		}
		if (data = egw_script.getAttribute('data-user'))
		{
			window.egw.set_user(JSON.parse(data));
		}
	});
})();
