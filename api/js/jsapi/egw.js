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
	egw_notification;
	app_base;
	egw_app;
*/

(function()
{
	"use strict";

	var debug = false;
	var egw_script = document.getElementById('egw_script_id');
	var start_time = (new Date).getTime();
	if(typeof console != "undefined" && console.time) console.time("egw");

	// set opener as early as possible for framework popups (not real popups)
	if (!window.opener && window.parent !== window)
	{
		try {
			if (window.parent.framework && typeof window.parent.framework.popup_idx == 'function' &&
				window.parent.framework.popup_idx.call(window.parent.framework, window) !== undefined)
			{
				window.opener = window.parent;
			}
		}
		catch(e) {
			// ignore SecurityError exception if opener is different security context / cross-origin
		}
	}
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
		try {
			// try finding it in top
			if (typeof window.egw == 'undefined' && window.top && typeof window.top.egw != 'undefined')
			{
				window.egw = window.top.egw;
				if (typeof window.top.framework != 'undefined') window.framework = window.top.framework;
				if (debug) console.log('found egw object in top');
			}
		}
		catch(e) {
			// ignore SecurityError exception if top is different security context / cross-origin
		}
		if (typeof window.egw == 'undefined')
		{
			window.egw = {
				prefsOnly: true,
				webserverUrl: egw_webserverUrl
			};
			if (debug) console.log('creating new egw object');
		}
	}
	else if (debug) console.log('found injected egw object');

	var include = JSON.parse(egw_script.getAttribute('data-include')) || [];

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
		try {
			if (typeof window.framework == 'undefined' && window.top && typeof window.top.framework != 'undefined')
			{
				window.framework = window.top.framework;
				if (debug) console.log('found framework object in top');
			}
		}
		catch(e) {
			// ignore SecurityError exception if top is different security context / cross-origin
		}
		// if framework not found, but requested to check for it, redirect to cd=yes to create it
		var check_framework = egw_script.getAttribute('data-check-framework');
		if (typeof window.framework == 'undefined' &&
			!window.location.pathname.match(/\/(smallpart\/|login.php)/) && // not for login page
			!window.location.search.match(/[&?]cd=/) &&
			// for popups check if required files are not about to be loaded (saved additional redirect and fixes LTI launches)
			(check_framework || include.filter(function(_uri){return _uri.match(/api\/(config|user)\.php/);}).length < 2))
		{
			window.location.search += (window.location.search ? "&" : "?")+(check_framework ? "cd=yes" : "cd=popup");
		}
	}
	try {
		if (typeof egw == 'function') egw(window).message;
	}
	catch (e) {
		console.log('Security exception accessing window specific egw object --> creating new one', e);
		window.egw = {
			prefsOnly: true,
			webserverUrl: egw_webserverUrl
		};
	}
	// set top window in egw object
	if (typeof window.egw.top === "undefined")
	{
		window.egw.top = window;
	}

	// focus window / call window.focus(), if data-window-focus is specified
	var window_focus = egw_script.getAttribute('data-window-focus');
	if (window_focus && JSON.parse(window_focus))
	{
		window.focus();
	}

	window.egw_LAB = $LAB.setOptions({AlwaysPreserveOrder:true,BasePath:window.egw_webserverUrl+'/'});
	window.egw_LAB.script(include).wait(function()
	{
		// We need to override the globalEval to mitigate potential execution of
		// script tag. This issue is relevant to jQuery 1.12.4, we need to check
		// if we still need this after upgrading jQuery.
		jQuery.extend({
			globalEval:function(data){}
		});

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
			window.opener.egw(window.opener).refresh.apply(egw(window.opener), refresh_opener);
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

		// set grants if given for push
		var egw_grants = egw_script.getAttribute('data-grants');
		if (egw_grants)
		{
			egw.set_grants(JSON.parse(egw_grants));
		}

		if(typeof console != "undefined" && console.timeEnd) console.timeEnd("egw");
		var end_time = (new Date).getTime();
		var gen_time_div = jQuery('#divGenTime_'+window.egw_appName);
		if (!gen_time_div.length) gen_time_div = jQuery('.pageGenTime');
		var gen_time_async = jQuery('.asyncIncludeTime').length > 0 ? jQuery('.asyncIncludeTime'):
				gen_time_div.append('<span class="asyncIncludeTime"></span>').find('.asyncIncludeTime');
		gen_time_async.text(egw.lang('async includes took %1s', (end_time-start_time)/1000));

		// Make sure opener knows when we close - start a heartbeat
		if((popup || window.opener) && window.name != '')
		{
			// Timeout is 5 seconds, but it iks only applied(egw_utils) when something asks for the window list
			window.setInterval(function() {
				if (window.opener && window.opener.framework && typeof window.opener.framework.popup_idx(window) == 'undefined' && !egwIsMobile())
				{
					window.opener.framework.popups.push(window);
				}
				egw().storeWindow(this.egw_appName, this);
			}, 2000);
		}

		// instanciate app object
		var appname = window.egw_appName;
		if (app && typeof app[appname] != 'object' && typeof app.classes[appname] == 'function')
		{
			app[appname] = new app.classes[appname]();
		}

		// set sidebox for tabed templates
		var sidebox = egw_script.getAttribute('data-setSidebox') || jQuery('#late-sidebox').attr('data-setSidebox');
		if (window.framework && sidebox && sidebox !== 'null')
		{
			window.framework.setSidebox.apply(window.framework, JSON.parse(sidebox));
		}

		var resize_attempt = 0;
		var resize_popup = function()
		{
			var $main_div = jQuery('#popupMainDiv');
			var $et2 = jQuery('.et2_container');
			var w = {
				width: egw_getWindowInnerWidth(),
				height: egw_getWindowInnerHeight()
			};
			// Use et2_container for width since #popupMainDiv is full width, but we still need
			// to take padding/margin into account
			var delta_width = w.width - ($et2.outerWidth(true) + ($main_div.outerWidth(true) - $main_div.width()));
			var delta_height = w.height - ($et2.outerHeight(true) + ($main_div.outerHeight(true) - $main_div.height()));

			// Don't let the window gets horizental scrollbar
			var scrollWidth = document.body.scrollWidth - document.body.clientWidth;
			if (scrollWidth > 0 && scrollWidth + egw_getWindowOuterWidth() < screen.availWidth) delta_width = -scrollWidth;

			if (delta_height && egw_getWindowOuterHeight() >= egw.availHeight())
			{
				delta_height = 0;
			}
			if((delta_width != 0 || delta_height != 0) &&
				(delta_width >2 || delta_height >2 || delta_width<-2 || delta_height < -2))
			{

				if (window.framework && typeof window.framework.resize_popup != 'undefined')
				{
					window.framework.resize_popup($et2.outerWidth(true), $et2.outerHeight(true), window);
				}
				else
				{
					window.resizeTo(egw_getWindowOuterWidth() - delta_width+8, egw_getWindowOuterHeight() - delta_height);
				}
			}
			// trigger a 2. resize, as one is not enough, if window is zoomed
			if (delta_width && ++resize_attempt < 2)
			{
				window.setTimeout(resize_popup, 50);
			}
			else
			{
				resize_attempt = 0;
			}
		};

		// rest needs DOM to be ready
		jQuery(function() {
			// load etemplate2 template(s)
			jQuery('form.et2_container[data-etemplate]').each(function(index, node){
				var data = JSON.parse(node.getAttribute('data-etemplate')) || {};
				var currentapp = data.data.currentapp || window.egw_appName;
				if(popup || window.opener && !egwIsMobile())
				{
					// Resize popup when et2 load is done
					jQuery(node).on('load', function() {
						if(typeof CKEDITOR !== 'undefined' && Object.keys(CKEDITOR.instances).length)
						{
							CKEDITOR.once('instanceReady',function() {
								// Trigger a resize again once CKEDITOR is ready
								window.resizeTo(egw_getWindowOuterWidth(), egw_getWindowOuterHeight() );
							});
						}
						else
						{
							window.setTimeout(resize_popup, 50);
						}
					});
				}
				var et2 = new etemplate2(node, "EGroupware\\Api\\Etemplate::ajax_process_content");
				et2.load(data.name,data.url,data.data);
				if (typeof data.response != 'undefined')
				{
					var json_request = egw(window).json("");
					json_request.handleResponse({response: data.response});
				}
			});

			// Offline/Online checking part
			if (typeof window.Offline != 'undefined')
			{
				Offline.options = {
					// Should we check the connection status immediatly on page load.
					checkOnLoad: false,

					// Should we monitor AJAX requests to help decide if we have a connection.
					interceptRequests: true,

					// Should we automatically retest periodically when the connection is down (set to false to disable).
					reconnect: {
					  // How many seconds should we wait before rechecking.
					  initialDelay: 3,

					  // How long should we wait between retries.
					  //delay: (1.5 * last delay, capped at 1 hour)
					},

					// Should we store and attempt to remake requests which fail while the connection is down.
					requests: true,

					checks: {
						xhr: {
							url: egw.webserverUrl+'/api/templates/default/images/favicon.ico?'+Date.now()
						}
					}
				};

				window.Offline.on('down', function(){
					this.loading_prompt('connectionLost', true, '', null);
				}, egw(window));
				window.Offline.on('up', function(){
					jQuery('.close', '#egw_message').click();
					this.loading_prompt('connectionLost', false);
				}, egw(window));
			}

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
			try {
				// Open tutorial popup with an introduction video about egroupware
				if (window.framework === window.top.framework && typeof et2_dialog != 'undefined' &&
					!egw.preference('egw_tutorial_noautoload', 'common') &&
					!parseInt(egw_script.getAttribute('data-framework-reload')) &&
					(!egw.config('egw_tutorial_disable', 'phpgwapi') || egw.config('egw_tutorial_disable', 'phpgwapi') == 'sidebox'))
				{
					// we need to wait until common translations are loaded
					egw.langRequireApp(window, 'common', function()
					{
						var buttons = [
							{text:egw.lang("Show now"), id:"show", image: "check", default:"true"},
							{text:egw.lang("Show next login"), id:"later", image: "right"},
							{text:egw.lang("No thanks"), id:"never", image: "cancel"}
						];
						et2_dialog.show_dialog(function (_button_id)
						{
							if (_button_id == "show" )
							{
								egw.open_link(egw.link('/index.php', 'menuaction=api.EGroupware\\Api\\Framework\\Tutorial.popup&tuid=introduction-'+egw.preference('lang')+'-0-a'),'_blank','960x580');
							}
							if(_button_id != "later")
							{
								egw.set_preference('common', 'egw_tutorial_noautoload',true);
							}
						},
						egw.lang('We would like to introduce you to EGroupware by showing a short introduction video.'),
						egw.lang('Introduction'),
						{}, buttons, et2_dialog.QUESTION_MESSAGE, undefined, egw(window));
					}, this);
				}

				// open websocket to push server for our top window
				if (egw === window.top.egw && egw_script.getAttribute('data-websocket-url'))
				{
					egw.json('websocket', {}, undefined, this).openWebSocket(
						egw_script.getAttribute('data-websocket-url'),
						JSON.parse(egw_script.getAttribute('data-websocket-tokens')),
						parseInt(egw_script.getAttribute('data-websocket-account_id'))
					);
				}
			}
			catch(e) {
				// ignore SecurityError exception if top is different security context / cross-origin
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

// get TypeScript modules working with our loader
function require(_file)
{
	switch(_file)
	{
		case 'jquery':	// cropper mistakes this for commonJS (typeof exports === 'object')
			return window.jQuery;
	}
	return window.exports;
}
var exports = {};

/**
 * Call a function specified by it's name (possibly dot separated, eg. "app.myapp.myfunc")
 *
 * @param {string} _func dot-separated function name
 * @param {mixed} ...args variable number of arguments
 * @returns {Mixed|Promise}
 * @deprecated use egw.call(_func, ...) or egw.apply(_func, args)
 */
function et2_call(_func)
{
	let args = [].slice.call(arguments);	// convert arguments to array
	let func = args.shift();
	return egw.apply(func, args, window);
}
