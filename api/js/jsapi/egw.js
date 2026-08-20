/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

/**
 * Object to collect instantiated application objects
 *
 * Attributes classes collects loaded application classes,
 * which can get instantiated:
 *
 *	app[appname] = new app.classes[appname]();
 *
 * On destruction only app[appname] gets deleted, app.classes[appname] need to be used again!
 *
 * @type object
 */
window.app = {classes: {}};

(function()
{
	"use strict";

	// Guard against a fatal crash when a mid-session rebuild loads a new-hash chunk that
	// re-registers an already-defined custom element: the browser has no "redefine" API for
	// an existing tag name anyway, so without this guard the second define() throws a fatal,
	// uncatchable-at-module-scope DOMException instead of silently keeping the first version.
	if (!window.customElements.__egwGuarded)
	{
		const _define = window.customElements.define.bind(window.customElements);
		window.customElements.define = function(name, ctor, options)
		{
			if (window.customElements.get(name))
			{
				console.debug('egw: keeping already-registered', name, '(an older build already defined it earlier this session; skipping the just-fetched redefinition)');
				return;
			}
			return _define(name, ctor, options);
		};
		window.customElements.__egwGuarded = true;
	}

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
	window.egw_buildEpoch = parseInt(egw_script.getAttribute('data-epoch')) || null;

	// split includes in legacy js and modules
	const legacy_js_regexp = /\/dhtmlx|jquery-ui|^etemplate\/|^phpbrain\/|^phpgwapi\//;

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
				legacy_js_regexp: legacy_js_regexp,
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
			!window.location.pathname.match(/\/(registration\/|smallpart\/|login.php)/) && // not for login page
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
			legacy_js_regexp: legacy_js_regexp,
			webserverUrl: egw_webserverUrl
		};
	}
	// set top window in egw object
	if (typeof window.egw.top === "undefined")
	{
		window.egw.top = window;
	}

	// Register unload listener for cleanup
	if (popup)
	{
		// Uncomment this to debug pagehide events
		// window.onpagehide = (e) => {	debugger;};
		window.addEventListener("pagehide", (e) =>
		{
			window.framework = null;
			window.egw = null;
		})
	}

	// focus window / call window.focus(), if data-window-focus is specified
	var window_focus = egw_script.getAttribute('data-window-focus');
	if (window_focus && JSON.parse(window_focus))
	{
		window.focus();
	}

	/**
	 * Import JavaScript legacy code: global scope, non-strict and executed in order
	 *
	 * @param String|Array _src
	 * @param String|undefined _baseurl
	 * @return {Promise<Promise<unknown>[]>}
	 */
	async function legacy_js_import(_src, _baseurl)
	{
		console.log("Legacy import: ", _src,_baseurl);
		if (!Array.isArray(_src)) _src = [].concat(_src);
		return Promise.all(_src.map(src => {
			return new Promise(function(_resolve, _reject)
			{
				const script = document.createElement('script');
				script.src = (_baseurl ? _baseurl+'/' : '')+src;
				script.async = _src.length === 1;
				script.onload = _resolve;
				script.onerror = _reject;
				document.head.appendChild(script);
			})
				// catch and display, but not stop execution
			.catch((err) => { console.error(src+": "+err.message)});
		}));
	}

	// make our promise global, as legacy code calls egw_LAB.wait which we assign to egw_ready.then
	window.egw_LAB = window.egw_ready =
		legacy_js_import(include.filter((src) => src.match(legacy_js_regexp) !== null), window.egw_webserverUrl)
			.then(() => Promise.all(include.filter((src) => src.match(legacy_js_regexp) === null)	//.reverse()
				.map(rel_src => import(window.egw_webserverUrl+'/'+rel_src)
					.catch((err) => {window.setTimeout(() => {throw err;},0)})
	))).then(() =>
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
		gen_time_async.text(egw.lang ? egw.lang('async includes took %1s', (end_time - start_time) / 1000) : 'async includes took ' + (end_time - start_time) / 1000);
		gen_time_async = null;

		// Make sure opener knows when we close - start a heartbeat
		try {
			if (window.opener && window.opener.framework || popup) {
				// capture the opener's framework instance now, to notice later if the opener reloads/navigates
				var opener_framework_at_init = window.opener && window.opener.framework;
				// Timeout is 5 seconds, but it iks only applied(egw_utils) when something asks for the window list
				window.setInterval(function () {
					try {
						// opener reloaded/navigated: it now has a brand new (or no) framework instance
						if (window.opener && window.opener.framework !== opener_framework_at_init)
						{
							opener_framework_at_init = window.opener.framework;
							window.egw_rejoin();
							return;
						}
					}
					catch(e) {
						// ignore SecurityError exception if opener is different security context / cross-origin
					}
					// Only track named windows in the opener's window list
					if (window.name != '') {
						if (window.opener && window.opener.framework && typeof window.opener.framework.popup_idx(window) == 'undefined' && !egwIsMobile()) {
							window.opener.framework.popups.add(window);
						}
						egw().storeWindow(this.egw_appName, this);
					}
				}, 2000);
			}
		}
		catch(e) {
			// ignore SecurityError exception if opener is different security context / cross-origin
		}

		// Poll for a newer build and show one dismissible notice once meaningfully stale (main window only)
		if (!popup && window.egw_buildEpoch)
		{
			let build_check_interval = window.setInterval(function ()
			{
				fetch(window.egw_webserverUrl+'/api/js/build-epoch.json', {cache: 'no-store'})
					.then(r => r.json())
					.then(data =>
					{
						if (data.epoch && data.epoch - window.egw_buildEpoch > 12*3600000) // 12 hours
						{
							egw(window).message(egw.lang('New updates available. Reload when convenient to get the latest updates.'), 'info');
							window.clearInterval(build_check_interval);
						}
					})
					.catch(() => {});	// ignore network errors, just retry next interval
			}, 15*60000);
		}
		// instantiate app object
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
		sidebox = null;

		var resize_attempt = 0;
		var resize_popup = function()
		{
			var $main_div = jQuery('#popupMainDiv');
			let $et2 = jQuery('.et2_container');
			let $layoutTable = jQuery(".et2_container > * > table", $main_div);
			if ($layoutTable.length && $et2.width() < $layoutTable.width())
			{
				// Still using a layout table, and it's bigger.
				// Use the layout table for width calculation
				$et2 = $layoutTable;
			}
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
			var scrollHeight = document.body.scrollHeight - w.height;
			if (scrollWidth > 0 && scrollWidth + egw_getWindowOuterWidth() < screen.availWidth) delta_width = -scrollWidth;
			if (scrollHeight > 0 && scrollHeight + egw_getWindowOuterHeight() < screen.availHeight)
			{
				delta_height = -scrollHeight;
			}

			if (delta_height && egw_getWindowOuterHeight() >= egw.availHeight())
			{
				delta_height = 0;
			}
			if((delta_width != 0 || delta_height != 0) &&
				(delta_width > 2 || delta_height > 2 || delta_width < -2 || delta_height < -2))
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
			$main_div = null;
			$et2 = null;
			$layoutTable = null;
		};

		// rest needs DOM to be ready
		jQuery(function()
		{
			// load etemplate2 template(s)
			document.querySelectorAll('form.et2_container[data-etemplate]').forEach(node =>
			{
				const data = JSON.parse(node.getAttribute('data-etemplate')) || {};
				if (popup || window.opener && !egwIsMobile()) {
					// Resize popup when et2 load is done
					node.addEventListener("load", (event) =>
					{
						// Only the top-level template, not any sub-templates
						if (event.target == node)
						{
							window.setTimeout(resize_popup, 50)
						}
					});
				}
				const et2 = new etemplate2(node, data.data.menuaction);
				et2.load(data.name, data.url, data.data);
				if (typeof data.response !== 'undefined') {
					const json_request = egw(window).json("");
					json_request.handleResponse({response: data.response});
				}
			});

			// Offline/Online checking part
			if (typeof window.Offline != 'undefined')
			{
				Offline.options = {
					// Should we check the connection status immediately on page load.
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
	//
	window.egw_LAB.wait = window.egw_ready.then;

	// dispatch egw-is-created event in order to let login.js knows about egw object readiness
	document.dispatchEvent(new Event('egw-is-created'));
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
 * @param {string|Function} _func dot-separated function name
 * @param {mixed} ...args variable number of arguments
 * @returns {Mixed|Promise}
 * @deprecated use egw.callFunc(_func, ...) or egw.applyFunc(_func, args)
 */
window.et2_call = function(_func)
{
	return egw.applyFunc(_func, [].slice.call(arguments, 1), window);
}

/**
 * Main window was reloaded - reconnect this popup to its (new) opener
 *
 * Called reactively from the popup heartbeat once it notices the opener's framework
 * instance changed (ie. the opener navigated/reloaded), so the opener has typically
 * already finished its own re-init by the time this runs - unlike a proactive
 * "wait for the opener to load" approach, this just retries until it's ready.
 */
window.egw_rejoin = function ()
{
	let reconnecting = egw(window).message("Reconnecting...");
	window.setTimeout(function retry()
	{
		try {
			if (!window.opener || typeof window.opener.top.framework == 'undefined')
			{
				window.setTimeout(retry, 1000);
				return;
			}
			window.framework = window.opener.top.framework;
			window.egw = window.opener.top.egw;
			window.framework.popups.add(window);
			reconnecting.close();
			console.log("Popup %s rejoined", window.location.href);
		}
		catch(e)
		{
			// opener still initializing, or cross-origin - try again shortly
			window.setTimeout(retry, 1000);
		}
	}, 500);
}
/**
 * Allow egw.json to load JS into popups
 *
 * @param url
 * @returns {Promise<*>}
 */
window.egw_import = function (url)
{
	return import(url);
}
/**
 * Run a JSON request in *this* window's realm, calling back with the parsed response
 *
 * Popups adopt the opener's egw object (see the bootstrap at the top of this file), so the
 * egw modules' code - and every closure it creates, including its .then() callbacks - belongs
 * to the opener's realm, not the popup's. Once the opener navigates, promise reactions queued
 * while that no-longer-fully-active document is on the call stack are silently discarded: the
 * request still reaches the server and is processed, but its response is never handled, so eg.
 * a save in a reconnected popup would neither close the popup nor show its confirmation.
 *
 * Starting the chain here, in a realm that is still fully active, is what fixes it: the
 * reactions registered below are functions of *this* realm. The onSuccess/onError handed in
 * may well belong to a dead realm - that is fine, dead-realm functions execute normally when
 * *called*, they just never get *scheduled*.
 *
 * The returned promise is passed through egw_chainable() so callers can still .then() onto it
 * with callbacks of their own (dead) realm - see there.
 *
 * @param url
 * @param init fetch() init object
 * @param onSuccess called with the parsed JSON; its return value resolves the promise
 * @param onError called with (error, response_ok)
 * @returns {Promise<*>} resolving to onSuccess's return value
 */
window.egw_fetch_json = function (url, init, onSuccess, onError)
{
	let response_ok = false;
	return window.egw_chainable(fetch(url, init)
		.then(function (response) {
			response_ok = response.ok;
			if (!response.ok) {
				throw response;
			}
			return response.json();
		})
		.then(function (data) { return onSuccess(data); })
		.catch(function (_err) { return onError(_err, response_ok); }));
}
/**
 * Wrap a callback so it can be used as a promise reaction from a document that may no longer
 * be fully active
 *
 * A promise reaction whose callback function belongs to a non-fully-active document is never
 * invoked - it is the *callback's* realm that decides, not who called then(), and not whether
 * the promise was still pending. So a popup whose opener has navigated cannot schedule its own
 * continuations at all: they are silently dropped, no error (verified).
 *
 * The returned wrapper belongs to THIS realm, so it is what actually gets scheduled, and it
 * merely *calls* the original - dead-realm functions execute normally when called, they just
 * never get scheduled themselves.
 *
 * @param {function(...*):*} _callback callback which may belong to another (dead) realm
 * @returns {function(...*):*} wrapper of this realm, calling _callback with the same args/this
 */
window.egw_wrap_callback = function (_callback)
{
	return function () { return _callback.apply(this, arguments); };
}
/**
 * Make a promise of this realm safe to chain from a document that may no longer be fully active
 *
 * @see window.egw_wrap_callback for why this is needed - this is the same trick applied to a
 * whole promise instead of a single callback.
 *
 * @param {Promise<*>} _promise promise created in this realm
 * @returns {Promise<*>} the same promise, with a then() that realm-wraps its callbacks
 */
window.egw_chainable = function (_promise)
{
	const native_then = _promise.then.bind(_promise);

	/**
	 * @param {function(*):*} [_onFulfilled]
	 * @param {function(*):*} [_onRejected]
	 * @returns {Promise<*>}
	 */
	_promise.then = function (_onFulfilled, _onRejected)
	{
		const chained = window.egw_chainable(native_then(
			typeof _onFulfilled === 'function' ? function (_value) { return _onFulfilled(_value); } : _onFulfilled,
			typeof _onRejected === 'function' ? function (_reason) { return _onRejected(_reason); } : _onRejected
		));
		// keep abort() reachable after chaining: callers hold on to the chained promise, but
		// abort() is assigned to the original one by egw_json's sendRequest()
		if (typeof _promise.abort === 'function' && typeof chained.abort !== 'function')
		{
			chained.abort = function () { return _promise.abort(); };
		}
		return chained;
	};
	return _promise;
}
