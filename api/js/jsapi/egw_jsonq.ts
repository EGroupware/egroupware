/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core';

export interface JsonqModule
{
	/**
	 * Send a queued JSON call to the server
	 *
	 * @param _menuaction the menuaction function which should be called and
	 *   which handles the actual request. If the menuaction is a full featured
	 *   url, this one will be used instead.
	 * @param _parameters which should be passed to the menuaction function.
	 * @param _callback callback function which should be called upon a "data" response is received
	 * @param _sender is the reference object the callback function should get
	 * @param _callbeforesend optional callback function which can modify the parameters, eg. to do some own queuing
	 * @return Promise
	 */
	jsonq(_menuaction : string, _parameters? : any[], _callback? : Function, _sender? : object, _callbeforesend? : Function) : Promise<any>;

	/**
	 * Register a callback to receive push broadcasts eg. in a popup or iframe
	 *
	 * It's also used internally by egw_message's push method to dispatch to the registered callbacks.
	 *
	 * @param data callback (with bound context) or PushData to dispatch to callbacks
	 */
	registerPush(data : Function|object) : void;
}

declare global
{
	interface IegwGlobal extends JsonqModule
	{
	}
}

/**
 * Module queuing json requests, to send several as one combined request
 */
class Jsonq implements JsonqModule
{
	/**
	 * Explicit registered push callbacks
	 */
	#pushCallbacks : Function[] = [];

	/**
	 * Queued json requests (objects with attributes menuaction, parameters, context, callback, sender and callbeforesend)
	 */
	#jsonqQueue : {[uid : string] : any} = {};

	/**
	 * Next uid (index) in queue
	 */
	#jsonqUid = 0;

	/**
	 * Running timer for next send of queued items
	 */
	#jsonqTimer : any = null;

	/**
	 * Send the whole job-queue to the server in a single json request with menuaction=queue
	 */
	private jsonqSend() : void
	{
		if (this.#jsonqUid > 0 && typeof this.#jsonqQueue['u'+(this.#jsonqUid-1)] == 'object')
		{
			const jobs_to_send : {[uid : string] : any} = {};
			let something_to_send = false;
			for(let uid in this.#jsonqQueue)
			{
				const job = this.#jsonqQueue[uid];

				if (job.menuaction === 'send') continue;	// already send to server

				// if job has a callbeforesend callback, call it to allow it to modify parameters
				if (typeof job.callbeforesend === 'function')
				{
					job.callbeforesend.call(job.sender, job.parameters);
				}
				jobs_to_send[uid] = {
					menuaction: job.menuaction,
					parameters: job.parameters
				};
				job.menuaction = 'send';
				job.parameters = null;
				something_to_send = true;
			}
			if (something_to_send)
			{
				egw.request('api.queue', jobs_to_send).then(_data =>
				{
					if (typeof _data != 'object') throw "jsonq_callback called with NO object as parameter!";

					const json = egw.json('none');
					for(let uid in _data)
					{
						if (typeof this.#jsonqQueue[uid] == 'undefined')
						{
							console.log("jsonq_callback received response for not existing queue uid="+uid+"!");
							console.log(_data[uid]);
							continue;
						}
						const job = this.#jsonqQueue[uid];
						const response = _data[uid];

						// The ajax request has completed, get just the data & pass it on
						if(response)
						{
							for(let value of response)
							{
								if(value.type && value.type === "data" && typeof value.data !== "undefined")
								{
									// Data was packed in response
									job.resolve(value.data);
								}
								else if (value && typeof value.type === "undefined" && typeof value.data === "undefined")
								{
									// Just raw data
									job.resolve(value);
								}
								else
								{
									// fake egw.json_request object, to call it with the current response
									(<any>json).handleResponse({response: response});
								}
							}
							// Response is there, but empty.  Make sure to resolve it or the callback doesn't get called.
							if (typeof response.length !== "undefined" && response.length == 0)
							{
								job.resolve();
							}
						}

						delete this.#jsonqQueue[uid];
					}
					// if nothing left in queue, stop interval-timer to give browser a rest
					if (this.#jsonqTimer && typeof this.#jsonqQueue['u'+(this.#jsonqUid-1)] != 'object')
					{
						window.clearInterval(this.#jsonqTimer);
						this.#jsonqTimer = null;
					}
				});
			}
		}
	}

	/**
	 * Send a queued JSON call to the server
	 *
	 * No dynamic-dispatch through `this` anywhere in here (only bare
	 * egw.request()/egw.json() global calls and this instance's own
	 * private queue state), so a plain arrow field is safe.
	 */
	jsonq = (_menuaction : string, _parameters? : any[], _callback? : Function, _sender? : any, _callbeforesend? : Function) : Promise<any> =>
	{
		const uid = 'u'+(this.#jsonqUid++);
		this.#jsonqQueue[uid] = {
			menuaction: _menuaction,
			// IE JSON-serializes arrays passed in from different window contextx (eg. popups)
			// as objects (it looses object-type of array), causing them to be JSON serialized
			// as objects and loosing parameters which are undefined
			// JSON.stringify([123,undefined]) --> '{"0":123}' instead of '[123,null]'
			parameters: _parameters ? [].concat(_parameters) : [],
			callbeforesend: _callbeforesend && _sender ? _callbeforesend.bind(_sender) : _callbeforesend,
		};
		let promise : any = new Promise(resolve => {
			this.#jsonqQueue[uid].resolve = resolve;
		});
		if (typeof _callback === 'function')
		{
			const callback = _callback.bind(_sender);
			promise = promise.then(_data => {
				callback(_data);
				return _data;
			});
		}

		if (this.#jsonqTimer == null)
		{
			// check / send queue every N ms
			this.#jsonqTimer = window.setInterval(() => this.jsonqSend(), 100);
		}
		return promise;
	}

	/**
	 * Register a callback to receive push broadcasts eg. in a popup or iframe
	 *
	 * It's also used internally by egw_message's push method to dispatch to the registered callbacks.
	 *
	 * Dispatches registered callbacks via `.call(this, data)`, so `this` must
	 * stay whichever egw instance the caller invoked registerPush() through -
	 * even though every currently registered callback already carries its
	 * own bound context (per the @param doc below) and so wouldn't
	 * observably care either way. That dynamic `this` is a different object
	 * than this Jsonq instance itself though (#pushCallbacks would throw if
	 * accessed off it directly), hence the `self`-capture.
	 *
	 * @param data callback (with bound context) or PushData to dispatch to callbacks
	 */
	registerPush = ((self : Jsonq) => function(this : any, data : Function|any) : void
	{
		if (typeof data === "function")
		{
			self.#pushCallbacks.push(data);
		}
		else
		{
			// iterate backwards, so splicing out a throwing callback doesn't
			// shift the index of callbacks not yet visited this dispatch
			for (let n = self.#pushCallbacks.length - 1; n >= 0; n--)
			{
				try {
					self.#pushCallbacks[n].call(this, data);
				}
				// if we get an exception, we assume the callback is no longer available and remove it
				catch (ex) {
					self.#pushCallbacks.splice(n, 1);
				}
			}
		}
	})(this);
}

egw.extend('jsonq', egw.MODULE_GLOBAL, () => new Jsonq());
