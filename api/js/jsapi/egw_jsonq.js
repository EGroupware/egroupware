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

/*egw:uses
	egw_core;
	egw_debug;
*/
import './egw_core.js';

egw.extend('jsonq', egw.MODULE_GLOBAL, function()
{
	"use strict";

	/**
	 * Explicit registered push callbacks
	 *
	 * @type {Function[]}
	 */
	let push_callbacks = [];

	/**
	 * Queued json requests (objects with attributes menuaction, parameters, context, callback, sender and callbeforesend)
	 *
	 * @access private, use jsonq method to queue requests
	 */
	const jsonq_queue = {};

	/**
	 * Next uid (index) in queue
	 */
	let jsonq_uid = 0;

	/**
	 * Running timer for next send of queued items
	 */
	let jsonq_timer = null;

	/**
	 * Send the whole job-queue to the server in a single json request with menuaction=queue
	 */
	function jsonq_send()
	{
		if (jsonq_uid > 0 && typeof jsonq_queue['u'+(jsonq_uid-1)] == 'object')
		{
			const jobs_to_send = {};
			let something_to_send = false;
			for(let uid in jsonq_queue)
			{
				const job = jsonq_queue[uid];

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
						if (typeof jsonq_queue[uid] == 'undefined')
						{
							console.log("jsonq_callback received response for not existing queue uid="+uid+"!");
							console.log(_data[uid]);
							continue;
						}
						const job = jsonq_queue[uid];
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
									json.handleResponse({response: response});
								}
							}
						}

						delete jsonq_queue[uid];
					}
					// if nothing left in queue, stop interval-timer to give browser a rest
					if (jsonq_timer && typeof jsonq_queue['u'+(jsonq_uid-1)] != 'object')
					{
						window.clearInterval(jsonq_timer);
						jsonq_timer = null;
					}
				});
			}
		}
	}

	return {
		/**
		 * Send a queued JSON call to the server
		 *
		 * @param {string} _menuaction the menuaction function which should be called and
		 *   which handles the actual request. If the menuaction is a full featured
		 *   url, this one will be used instead.
		 * @param {array} _parameters which should be passed to the menuaction function.
		 * @param {function|undefined} _callback callback function which should be called upon a "data" response is received
		 * @param {object|undefined} _sender is the reference object the callback function should get
		 * @param {function|undefined} _callbeforesend optional callback function which can modify the parameters, eg. to do some own queuing
		 * @return Promise
		 */
		jsonq: function(_menuaction, _parameters, _callback, _sender, _callbeforesend)
		{
			const uid = 'u'+(jsonq_uid++);
			jsonq_queue[uid] = {
				menuaction: _menuaction,
				// IE JSON-serializes arrays passed in from different window contextx (eg. popups)
				// as objects (it looses object-type of array), causing them to be JSON serialized
				// as objects and loosing parameters which are undefined
				// JSON.stringify([123,undefined]) --> '{"0":123}' instead of '[123,null]'
				parameters: _parameters ? [].concat(_parameters) : [],
				callbeforesend: _callbeforesend && _sender ? _callbeforesend.bind(_sender) : _callbeforesend,
			};
			let promise = new Promise(resolve => {
				jsonq_queue[uid].resolve = resolve;
			});
			if (typeof _callback === 'function')
			{
				const callback = _callback.bind(_sender);
				promise = promise.then(_data => {
					callback(_data);
					return _data;
				});
			}

			if (jsonq_timer == null)
			{
				// check / send queue every N ms
				jsonq_timer = window.setInterval(() => jsonq_send(), 100);
			}
			return promise;
		},

		/**
		 * Register a callback to receive push broadcasts eg. in a popup or iframe
		 *
		 * It's also used internally by egw_message's push method to dispatch to the registered callbacks.
		 *
		 * @param {Function|PushData} data callback (with bound context) or PushData to dispatch to callbacks
		 */
		registerPush: function(data)
		{
			if (typeof data === "function")
			{
				push_callbacks.push(data);
			}
			else
			{
				for (let n in push_callbacks)
				{
					try {
						push_callbacks[n].call(this, data);
					}
					// if we get an exception, we assume the callback is no longer available and remove it
					catch (ex) {
						push_callbacks.splice(n, 1);
					}
				}
			}
		}

	};
});