/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 */

import './egw_core';

export interface ReadyModule
{
	/**
	 * The readyWaitFor function can be used to register an event, that has
	 * to be marked as "done" before the ready function will call its
	 * registered callbacks. The function returns an id that has to be
	 * passed to the "readDone" function once
	 */
	readyWaitFor() : string;

	/**
	 * The readyDone function can be used to mark a event token as
	 * previously requested by "readyWaitFor" as done.
	 *
	 * @param _token is the token which has now been processed.
	 */
	readyDone(_token : string) : void;

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
	ready(_callback : Function, _context? : object, _beforeDOMContentLoaded? : boolean) : void;

	/**
	 * The readyProgress function can be used to register a function that
	 * will be called whenever a ready event is done or registered.
	 *
	 * @param _callback is the function which will be called when the
	 * 	progress changes.
	 * @param _context is the context in which the callback function which
	 * 	should get called.
	 */
	readyProgress(_callback : Function, _context? : object) : void;

	/**
	 * Returns whether the ready events have already been called.
	 */
	isReady() : boolean;
}

declare global
{
	interface IegwWndLocal extends ReadyModule
	{
	}
}

/**
 * @augments Class
 */
class Ready implements ReadyModule
{
	#registeredCallbacks : {callback : Function, context : object, before : boolean}[] = [];
	#registeredProgress : {callback : Function, context : object}[] = [];
	#readyPending : {[token : string] : boolean} = {'readyEvent': true};
	#readyPendingCnt = 1;
	#readyDoneCnt = 0;
	#isReady = false;

	constructor(_wnd : Window)
	{
		// Register the event handler for "ready" (code adapted from jQuery)

		// Mozilla, Opera and webkit nightlies currently support this event
		if (_wnd.document.addEventListener) {
			// Use the handy event callback
			_wnd.document.addEventListener("DOMContentLoaded", this.#readyEventHandler, false);

			// A fallback to window.onload, that will always work
			_wnd.addEventListener("load", this.#readyEventHandler, false);

		// If IE event model is used
		} else if ((<any>_wnd.document).attachEvent) {
			// ensure firing before onload,
			// maybe late but safe also for iframes
			(<any>_wnd.document).attachEvent("onreadystatechange", this.#readyEventHandler);

			// A fallback to window.onload, that will always work
			(<any>_wnd).attachEvent("onload", this.#readyEventHandler);
		}
	}

	private doReadyWaitFor() : string
	{
		if (!this.#isReady)
		{
			var uid = egw.uid();
			this.#readyPending[uid] = true;
			this.#readyPendingCnt++;

			this.readyProgressChange();

			return uid;
		}

		// preexisting typo ('warning' instead of 'warn') - preserved exactly,
		// same category of loosely-typed-call cast used elsewhere in this
		// migration (e.g. egw_data.ts); it makes this debug() call a silent
		// no-op since 'warning' matches none of debug()'s level cases.
		(<any>egw.debug)('warning', 'ready has already been called!');

		return null;
	}

	private doReadyDone(_token : string) : void
	{
		if (typeof this.#readyPending[_token] !== 'undefined')
		{
			delete this.#readyPending[_token];
			this.#readyPendingCnt--;
			this.#readyDoneCnt++;

			this.readyProgressChange();

			this.testCallReady();
		}
	}

	private readyProgressChange() : void
	{
		// Call all registered progress callbacks
		for (var i = 0; i < this.#registeredProgress.length; i++)
		{
			this.#registeredProgress[i].callback.call(
				this.#registeredProgress[i].context,
				this.#readyDoneCnt,
				this.#readyPendingCnt
			);
		}

		egw.debug('log', 'Ready events, processed %s/%s', this.#readyDoneCnt,
				this.#readyPendingCnt + this.#readyDoneCnt);
	}

	// Passed directly to addEventListener/attachEvent in the constructor, so
	// this must stay an arrow field (safe when detached from its instance),
	// unlike the other private helpers here which are only ever called via
	// this.foo() from within the class.
	#readyEventHandler = () : void =>
	{
		this.doReadyDone('readyEvent');
	}

	private testCallReady() : void
	{
		// Check whether no further event is pending
		if (this.#readyPendingCnt <= 1 && !this.#isReady)
		{
			// If exactly one event is pending and that one is not the ready
			// event, abort
			if (this.#readyPendingCnt === 1 && !this.#readyPending['readyEvent'])
			{
				return;
			}

			// Set "isReady" to true, if readyPendingCnt is zero
			this.#isReady = this.#readyPendingCnt === 0;

			// Call all registered callbacks
			for (var i = this.#registeredCallbacks.length - 1; i >= 0; i--)
			{
				if (this.#registeredCallbacks[i].before || this.#readyPendingCnt === 0)
				{
					this.#registeredCallbacks[i].callback.call(
						this.#registeredCallbacks[i].context
					);

					// Delete the callback from the list
					this.#registeredCallbacks.splice(i, 1);
				}
			}
		}
	}

	/**
	 * The readyWaitFor function can be used to register an event, that has
	 * to be marked as "done" before the ready function will call its
	 * registered callbacks. The function returns an id that has to be
	 * passed to the "readDone" function once
	 */
	readyWaitFor = () : string =>
	{
		return this.doReadyWaitFor();
	}

	/**
	 * The readyDone function can be used to mark a event token as
	 * previously requested by "readyWaitFor" as done.
	 *
	 * @param _token is the token which has now been processed.
	 */
	readyDone = (_token : string) : void =>
	{
		this.doReadyDone(_token);
	}

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
	ready = (_callback : Function, _context? : object, _beforeDOMContentLoaded? : boolean) : void =>
	{
		if (!this.#isReady)
		{
			this.#registeredCallbacks.push({
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
	}

	/**
	 * The readyProgress function can be used to register a function that
	 * will be called whenever a ready event is done or registered.
	 *
	 * Called as egw(app,wnd).readyProgress(...) - `this.debug(...)` must
	 * dispatch through whichever instance called it, hence a plain
	 * `function` field rather than an arrow field.
	 *
	 * @param _callback is the function which will be called when the
	 * 	progress changes.
	 * @param _context is the context in which the callback function which
	 * 	should get called.
	 */
	readyProgress = ((self : Ready) => function(this : any, _callback : Function, _context? : object) : void {
		if (!self.#isReady)
		{
			self.#registeredProgress.unshift({
				'callback': _callback,
				'context': _context ? _context : null
			});
		}
		else
		{
			this.debug('warning', 'ready has already been called!');
		}
	})(this);

	/**
	 * Returns whether the ready events have already been called.
	 */
	isReady = () : boolean =>
	{
		return this.#isReady;
	}
}

egw.extend('ready', egw.MODULE_WND_LOCAL, (_app : string, _wnd : Window) => new Ready(_wnd));
