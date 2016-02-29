/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @version $Id$
 */

/*egw:uses
	egw_core;
	egw_utils;
	egw_debug;
*/

/**
 * @augments Class
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('ready', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	"use strict";

	var egw = this;

	var registeredCallbacks = [];
	var registeredProgress = [];
	var readyPending = {'readyEvent': true};
	var readyPendingCnt = 1;
	var readyDoneCnt = 0;
	var isReady = false;

	function doReadyWaitFor() {
		if (!isReady)
		{
			var uid = egw.uid();
			readyPending[uid] = true;
			readyPendingCnt++;

			readyProgressChange();

			return uid;
		}

		this.debug('warning', 'ready has already been called!');

		return null;
	}

	function doReadyDone(_token) {
		if (typeof readyPending[_token] !== 'undefined')
		{
			delete readyPending[_token];
			readyPendingCnt--;
			readyDoneCnt++;

			readyProgressChange();

			testCallReady();
		}
	}

	function readyProgressChange()
	{
		// Call all registered progress callbacks
		for (var i = 0; i < registeredProgress.length; i++)
		{
			registeredProgress[i].callback.call(
				registeredProgress[i].context,
				readyDoneCnt,
				readyPendingCnt
			);
		}

		egw.debug('log', 'Ready events, processed %s/%s', readyDoneCnt,
				readyPendingCnt + readyDoneCnt);
	}

	function readyEventHandler() {
		doReadyDone('readyEvent');
	}

	function testCallReady()
	{
		// Check whether no further event is pending
		if (readyPendingCnt <= 1 && !isReady)
		{
			// If exactly one event is pending and that one is not the ready
			// event, abort
			if (readyPendingCnt === 1 && !readyPending['readyEvent'])
			{
				return;
			}

			// Set "isReady" to true, if readyPendingCnt is zero
			var isReady = readyPendingCnt === 0;

			// Call all registered callbacks
			for (var i = registeredCallbacks.length - 1; i >= 0; i--)
			{
				if (registeredCallbacks[i].before || readyPendingCnt === 0)
				{
					registeredCallbacks[i].callback.call(
						registeredCallbacks[i].context
					);

					// Delete the callback from the list
					registeredCallbacks.splice(i, 1);
				}
			}
		}
	}

	// Register the event handler for "ready" (code adapted from jQuery)

	// Mozilla, Opera and webkit nightlies currently support this event
	if (_wnd.document.addEventListener) {
		// Use the handy event callback
		_wnd.document.addEventListener("DOMContentLoaded", readyEventHandler, false);

		// A fallback to window.onload, that will always work
		_wnd.addEventListener("load", readyEventHandler, false);

	// If IE event model is used
	} else if (_wnd.document.attachEvent) {
		// ensure firing before onload,
		// maybe late but safe also for iframes
		_wnd.document.attachEvent("onreadystatechange", readyEventHandler);

		// A fallback to window.onload, that will always work
		_wnd.attachEvent("onload", readyEventHandler);
	}

	return {

		/**
		 * The readyWaitFor function can be used to register an event, that has
		 * to be marked as "done" before the ready function will call its
		 * registered callbacks. The function returns an id that has to be
		 * passed to the "readDone" function once
		 *
		 * @memberOf egw
		 */
		readyWaitFor: function() {
			return doReadyWaitFor();
		},

		/**
		 * The readyDone function can be used to mark a event token as
		 * previously requested by "readyWaitFor" as done.
		 *
		 * @param _token is the token which has now been processed.
		 */
		readyDone: function(_token) {
			doReadyDone(_token);
		},

		/**
		 * The ready function can be used to register a function that will be
		 * called, when the window is completely loaded. All ready handlers will
		 * be called exactly once. If the ready handler has already been called,
		 * the given function will be called defered using setTimeout.
		 *
		 * @param _callback is the function which will be called when the page
		 * 	is ready. No parameters will be passed.
		 * @param _context is the context in which the callback function should
		 * 	get called.
		 * @param _beforeDOMContentLoaded specifies, whether the callback should
		 * 	get called, before the DOMContentLoaded event has been fired.
		 */
		ready: function(_callback, _context, _beforeDOMContentLoaded) {
			if (!isReady)
			{
				registeredCallbacks.push({
					'callback': _callback,
					'context': _context ? _context : null,
					'before': _beforeDOMContentLoaded ? true : false
				});
			}
			else
			{
				setTimeout(function() {
					_callback.call(_context);
				}, 1);
			}
		},

		/**
		 * The readyProgress function can be used to register a function that
		 * will be called whenever a ready event is done or registered.
		 *
		 * @param _callback is the function which will be called when the
		 * 	progress changes.
		 * @param _context is the context in which the callback function which
		 * 	should get called.
		 */
		readyProgress: function(_callback, _context) {
			if (!isReady)
			{
				registeredProgress.unshift({
					'callback': _callback,
					'context': _context ? _context : null
				});
			}
			else
			{
				this.debug('warning', 'ready has already been called!');
			}
		},

		/**
		 * Returns whether the ready events have already been called.
		 */
		isReady: function() {
			return isReady;
		}
	};

});


