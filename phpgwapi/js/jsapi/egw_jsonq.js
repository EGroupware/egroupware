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
*/

egw.extend('jsonq', egw.MODULE_GLOBAL, function() {

	/**
	 * Queued json requests (objects with attributes menuaction, parameters, context, callback, sender and callbeforesend)
	 * 
	 * @access private, use jsonq method to queue requests
	 */
	var jsonq_queue = {};

	/**
	 * Next uid (index) in queue
	 */
	var jsonq_uid = 0;

	/**
	 * Running timer for next send of queued items
	 */
	var jsonq_timer = null;

	/**
	 * Dispatch responses received
	 * 
	 * @param object _data uid => response pairs
	 */
	function jsonq_callback(_data)
	{
		if (typeof _data != 'object') throw "jsonq_callback called with NO object as parameter!";

		var json = egw.json('none');
		for(var uid in _data)
		{
			if (typeof jsonq_queue[uid] == 'undefined')
			{
				console.log("jsonq_callback received response for not existing queue uid="+uid+"!");
				console.log(_data[uid]);
				continue;
			}
			var job = jsonq_queue[uid];
			var response = _data[uid];
			
			// fake egw.json_request object, to call it with the current response
			json.callback = job.callback;
			json.sender = job.sender;
			json.handleResponse({response: response});

			delete jsonq_queue[uid];
		}
		// if nothing left in queue, stop interval-timer to give browser a rest
		if (jsonq_timer && typeof jsonq_queue['u'+(jsonq_uid-1)] != 'object')
		{
			window.clearInterval(jsonq_timer);
			jsonq_timer = null;
		}
	}

	/**
	 * Send the whole job-queue to the server in a single json request with menuaction=queue
	 */
	function jsonq_send()
	{
		if (jsonq_uid > 0 && typeof jsonq_queue['u'+(jsonq_uid-1)] == 'object')
		{
			var jobs_to_send = {};
			var something_to_send = false;
			for(var uid in jsonq_queue)
			{
				var job = jsonq_queue[uid];

				if (job.menuaction == 'send') continue;	// already send to server

				// if job has a callbeforesend callback, call it to allow it to modify pararmeters
				if (typeof job.callbeforesend == 'function')
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
				// TODO: Passing this to the "home" application looks quite ugly
				var request = egw.json('home.queue', jobs_to_send, jsonq_callback, this)
				request.sendRequest();
			}
		}
	}

	return {
		/**
		 * Send a queued JSON call to the server
		 * 
		 * @param string _menuaction the menuaction function which should be called and
		 *   which handles the actual request. If the menuaction is a full featured
		 *   url, this one will be used instead.
		 * @param array _parameters which should be passed to the menuaction function.
		 * @param function _callback callback function which should be called upon a "data" response is received
		 * @param object _sender is the reference object the callback function should get
		 * @param function _callbeforesend optional callback function which can modify the parameters, eg. to do some own queuing
		 * @return string uid of the queued request
		 */
		jsonq: function(_menuaction, _parameters, _callback, _sender, _callbeforesend)
		{
			var uid = 'u'+(jsonq_uid++);
			jsonq_queue[uid] = {
				menuaction: _menuaction,
				parameters: _parameters,
				callback: _callback,
				sender: _sender,
				callbeforesend: _callbeforesend
			};
			
			if (jsonq_timer == null)
			{
				// check / send queue every N ms
				var self = this;
				jsonq_timer = window.setInterval(function(){
					jsonq_send.call(self);
				}, 100);
			}
			return uid;
		}

	};

});


