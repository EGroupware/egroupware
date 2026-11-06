/**
 * Start the first app's index request while the framework's JavaScript is still loading
 *
 * Classic script in the page head, parameters in the data attributes of its own tag:
 * data-url and data-body are exactly the url and body egw.request() will send for the initially
 * active app. The request is started at once and window.fetch is wrapped, so the first POST with
 * that url and body gets the pending response instead of a second request. Everything after that
 * is the normal egw.request() handling.
 *
 * Other requests are sent before the index one, so a non-matching fetch passes through and leaves
 * the early one alone. It is used once, only while younger than data-max-age (ms, default 10000),
 * and if it failed or answered with an error status the request is sent as usual.
 * Does nothing without fetch(); nothing here may throw.
 */
(function()
{
	var script = document.currentScript;
	try
	{
		if(!script || !window.fetch || !script.dataset.url)
		{
			return;
		}
		var original = window.fetch;
		var url = new URL(script.dataset.url, document.baseURI).href;
		var body = script.dataset.body;
		var maxAge = parseInt(script.dataset.maxAge || "10000");
		var started = Date.now();
		var early = original.call(window, url, {
			method: "POST",
			headers: {"Content-Type": "application/json"},
			body: body
		});
		// an unused response failing is not an error
		early.then(function() {performance.mark("egw_early_fetch_response");}, function() {});
		performance.mark("egw_early_fetch_start");

		window.fetch = function(input, init)
		{
			try
			{
				if(early && init && init.method === "POST" && init.body === body &&
					new URL(typeof input === "string" ? input : input.url, document.baseURI).href === url)
				{
					var pending = early;
					early = null;
					window.fetch = original;	// nothing left to hand over
					if(Date.now() - started <= maxAge)
					{
						var args = arguments, self = this;
						performance.mark("egw_early_fetch_taken");
						return pending.then(function(response)
						{
							return response.ok ? response : original.apply(self, args);
						}, function()
						{
							return original.apply(self, args);
						});
					}
				}
			}
			catch(e) {}
			return original.apply(this, arguments);
		};
	}
	catch(e)
	{
		if(original)
		{
			window.fetch = original;
		}
	}
})();
