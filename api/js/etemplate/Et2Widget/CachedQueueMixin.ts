import {Et2Widget} from "./Et2Widget";
import type {IegwAppLocal} from "../../jsapi/egw_global";
import {LitElement} from "lit";

export interface ICachedQueueMixin
{
	cachedQueue(parameters : any) : Promise<any>;

	getCacheKey(parameters : any) : string;
}

export type CachedQueueData = {
	owner : typeof CachedQueueMixin;
	parameters : any;
	cacheKey : string; // Stringified parameters for matching
	resolve : (value : any) => void;
}

type Constructor<T = LitElement> = new (...args : any[]) => T;

/**
 * CachedQueueMixin allows a widget to request something from the server and the response be cached for future calls
 *
 * To implement, the widget class needs to set searchUrl.  Each instance then calls queueCache()
 * with the specific parameters to be sent to searchUrl.  These parameters are used to cache the results, so next time
 * an instance of the widget calls queueCache() with the same parameters, the same response is returned immediately
 * with no server call.
 *
 * export class MyWidget extends CachedQueueMixin(...) {
 *     protected static searchUrl = "\\EGroupware\\Api\\Etemplate\\Widget\\MyWidget::ajax_check";
 *     constructor() {
 *         ...
 *     }
 *
 *     set something(new_something) {
 *     	   // Do cleanup and validation of new_something ...
 *     	   // Call cachedQueue for data:
 *         this.cachedQueue({something: new_something}).then((cachedData) => {
 *         		// Do what is needed with the cachedData ...
 *         });
 *     }
 * }
 *
 * searchUrl should expect an array of parameters, and return the result indexed by the JSON stringification of the parameters:
 * 	public function ajax_check(array $parameterList) : array
 * 	{
 * 		$result = [];
 * 		foreach($parameterList as $parameters)
 * 		{
 * 				...
 * 				$result[json_encode($parameters)] = doCheck($parameters) ?? false;
 * 		}
 *
 * 		Response::get()->data($result);
 * 	}
 *
 * @param {T} superClass
 * @returns {Constructor<CachedQueueMixinClass> & T}
 * @constructor
 */
export const CachedQueueMixin = <T extends Constructor<typeof Et2Widget & {
	egw() : IegwAppLocal
} & LitElement>>(superClass : T) =>
{
	class CachedQueueMixinClass extends superClass
	{
		protected static widgetCacheKey : string = "";
		protected static searchUrl : string = "";

		private static _queues : Map<string, CachedQueueData[]> = new Map();
		private static _queue_timeouts : Map<string, ReturnType<typeof setTimeout>> = new Map();
		private static _queue_timeout_delay : number = 100;
		private static _queue_timeout_max : number = 1000;

		constructor(...args : any[])
		{
			super(...args);

			// Automatically set widgetCacheKey based on widget name
			if(!this.staticThis.widgetCacheKey)
			{
				this.staticThis.widgetCacheKey = this.constructor.name;
			}
		}

		async cachedQueue(parameters : any)
		{
			if(!this.staticThis.widgetCacheKey)
			{
				throw new Error("CachedQueueMixin: widget widgetCacheKey not set");
			}
			if(!this.staticThis.searchUrl)
			{
				throw new Error("CachedQueueMixin: widget searchUrl not set");
			}

			const cacheKey = this.getCacheKey(parameters);
			if(!cacheKey || cacheKey.trim() == "")
			{
				throw new Error("CachedQueueMixin: widget cacheKey() is missing or invalid");
			}
			const cache = this.getFromCache(cacheKey);

			// Already have the data cached, return it
			if(typeof cache !== "undefined" && cache !== null)
			{
				return Promise.resolve(cache);
			}
			// Check for pending request
			if(CachedQueueMixinClass._queues.has(this.staticThis.widgetCacheKey))
			{
				const pending = CachedQueueMixinClass._queues.get(this.staticThis.widgetCacheKey)?.find((item) =>
				{
					return item.cacheKey == cacheKey;
				});
				if(pending)
				{
					return new Promise(pending.resolve);
				}
			}

			// Add to the queue and fire when ready
			return new Promise((resolve) =>
			{
				// Add to the queue
				if(!CachedQueueMixinClass._queues.has(this.staticThis.widgetCacheKey))
				{
					CachedQueueMixinClass._queues.set(this.staticThis.widgetCacheKey, []);
				}
				CachedQueueMixinClass._queues.get(this.staticThis.widgetCacheKey).push({
					owner: this,
					parameters: parameters,
					cacheKey: this.getCacheKey(parameters),
					resolve: resolve
				});

				// Start the queue if it's not already running
				if(!CachedQueueMixinClass._queue_timeouts[this.staticThis.widgetCacheKey])
				{
					CachedQueueMixinClass._queue_timeouts[this.staticThis.widgetCacheKey] = setTimeout(() => {CachedQueueMixinClass._sendQueue(this.staticThis.widgetCacheKey)}, CachedQueueMixinClass._queue_timeout_delay);
					// Force queue processing after max timeout
					setTimeout(() =>
					{
						if(CachedQueueMixinClass._queues.get(this.staticThis.widgetCacheKey)?.length > 0)
						{
							CachedQueueMixinClass._sendQueue(this.staticThis.widgetCacheKey);
						}
					}, CachedQueueMixinClass._queue_timeout_max);
				}
			});
		}

		// Helper method to access static members
		protected get staticThis() : typeof CachedQueueMixinClass
		{
			return this.constructor as typeof CachedQueueMixinClass;
		}

		// Get a string key for indexing into the cache for this widget class
		private getCacheStorageKey(cacheKey : string) : string
		{
			// cacheKey not actually used at the widget level right now
			return `api-${this.staticThis.widgetCacheKey}`;
		}

		// Get a string key for this particular widget's queuedCache() call
		private getCacheKey(parameters : any) : string
		{
			return JSON.stringify(parameters);
		}

		// Get cached data
		private getFromCache(cacheKey : string) : any
		{
			const storageKey = this.getCacheStorageKey(cacheKey);
			const cached = this.egw().getSessionItem(storageKey, cacheKey);
			return cached ? JSON.parse(cached) : undefined;
		}

		// Set cached data
		private setToCache(cacheKey : string, data : any) : void
		{
			const storageKey = this.getCacheStorageKey(cacheKey);

			// Here is where we actually hold on to the data.
			// If we want to change how long / where the data is held, this (& get...) is the place to change
			this.egw().setSessionItem(storageKey, cacheKey, JSON.stringify(data));
		}

		/**
		 * Send a request for the accumulated data in a particular widget queue, then pass it back to the individual
		 * widgets and cache it for next time.
		 *
		 * @param {string} widgetCacheKey
		 * @returns {Promise<any>}
		 * @private
		 */
		private static async _sendQueue(widgetCacheKey : string)
		{
			const queue = CachedQueueMixinClass._queues.get(widgetCacheKey) ?? [];

			// Clear the timeout
			if(CachedQueueMixinClass._queue_timeouts[widgetCacheKey])
			{
				clearTimeout(CachedQueueMixinClass._queue_timeouts[widgetCacheKey]);
				CachedQueueMixinClass._queue_timeouts[widgetCacheKey] = null;
			}

			// Nothing to do
			if(queue.length == 0)
			{
				return;
			}
			const first = queue[0];
			const widget = <CachedQueueMixinClass>first.owner;

			// Get all parameters and remove duplicates (though there shouldn't be any)
			const uniqueParams = Array.from(new Set(queue.map(item => item.cacheKey)))
				.map(item => JSON.parse(item));
			if(uniqueParams.length !== queue.length)
			{
				// Should not be here, figure out how we got a duplicate in the queue
				debugger;
			}

			// Send the request
			const data = await widget.egw().request(widget.staticThis.searchUrl, [uniqueParams]) ?? {};

			// Map results back to all queued items
			for(let i = queue.length - 1; i >= 0; i--)
			{
				const item = queue[i];
				const widgetData = data[item.cacheKey];
				if(typeof widgetData === "undefined")
				{
					// No response yet - either coming in the next one, or server didn't give an answer for it
					return;
				}

				// Cache it
				widget.setToCache(item.cacheKey, widgetData);

				// Resolve the promise for this request
				item.resolve(widgetData);

				// Remove from the cache
				queue.splice(i, 1);
			}

			return data;
		}
	}

	return CachedQueueMixinClass as unknown as Constructor<CachedQueueMixinClass> & T;
}