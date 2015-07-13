/**
 * eGroupware Framework browser object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @copyright Stylite AG 2014
 * @description Framework browser object, is implementation of browser class in order to display application content
 */

/*egw:uses
	jquery.jquery;
	egw_inheritance.js;
*/

/**
 * Constants definition
 */
EGW_BROWSER_TYPE_NONE = 0;
EGW_BROWSER_TYPE_IFRAME = 1;
EGW_BROWSER_TYPE_DIV = 2;
"use strict";
var fw_browser =  Class.extend({



	/**
	 * @param {string} _app
	 * @param {function} _heightCallback
	 * Framework browser class constructor
	 */
	init: function (_app, _heightCallback){
		//Create a div which contains both, the legacy iframe and the contentDiv
		this.baseDiv = document.createElement('div');
		this.type = EGW_BROWSER_TYPE_NONE;
		this.iframe = null;
		this.contentDiv = null;
		this.heightCallback = _heightCallback;
		this.app = _app;
		this.currentLocation = '';
		this.ajaxLoaderDiv = null;
		this.loadingDeferred = null;
	},

	/**
	 * Triggers resize event on window
	 */
	callResizeHandler: function()
	{
		var wnd = window;
		if (this.iframe)
		{
			wnd = this.iframe.contentWindow;
		}

		// Call the resize handler (we have to use the jquery object of the iframe!)
		if (wnd && typeof wnd.$j != "undefined")
		{
			wnd.$j(wnd).trigger("resize");
		}
	},

	/**
	* Resizes both, the contentDiv and the iframe to the size returned from the heightCallback
	*/
	resize: function()
	{
		var height = this.heightCallback.call(this.iframe) + 'px';

		//Set the height of the content div or the iframe
		if (this.contentDiv)
		{
			this.contentDiv.style.height = height;
		}
		if (this.iframe)
		{
			this.iframe.style.height = height;
		}
	},

	/**
	 * Sets browser type either DIV or IFRAME
	 *
	 * @param {int} _type
	 */
	setBrowserType: function(_type)
	{
		//Only do anything if the browser type has changed
		if (_type != this.type)
		{
			//Destroy the iframe and/or the contentDiv
			$j(this.baseDiv).empty();
			this.iframe = null;
			this.contentDiv = null;
			if(this.loadingDeferred && this.type)
			{
				this.loadingDeferred.reject();
			}

			switch (_type)
			{
				//Create the div for displaying the content
				case EGW_BROWSER_TYPE_DIV:
					this.contentDiv = document.createElement('div');
					$j(this.contentDiv).addClass('egw_fw_content_browser_div');
					$j(this.baseDiv).append(this.contentDiv);

					break;

				case EGW_BROWSER_TYPE_IFRAME:
					//Create the iframe
					this.iframe = document.createElement('iframe');
					this.iframe.style.width = "100%";
					this.iframe.style.borderWidth = 0;
					this.iframe.frameBorder = 0;
					this.iframe.name = 'egw_app_iframe_' + this.app.appName;
					$j(this.iframe).addClass('egw_fw_content_browser_iframe');
					$j(this.baseDiv).append(this.iframe);

					break;
			}

			this.resize();
			this.type = _type;
		}
	},

	/**
	 * Sets url to browse and load the content in proper content browser
	 * @param {string} _url
	 * @return {Deferred} Returns a Deferred promise object
	 */
	browse: function(_url)
	{
		// check if app has its own linkHandler and it accepts the link (returns true), or returns different url instead
		if (typeof window.app == 'object' && typeof window.app[this.app.appName] == 'object' &&
				typeof window.app[this.app.appName].linkHandler == 'function')
		{
			var ret = window.app[this.app.appName].linkHandler.call(window.app[this.app.appName], _url);
			{
				if (ret === true) return;
				if (typeof ret === 'string')
				{
					_url = ret;
				}
			}
		}
		var useIframe = true;
		var targetUrl = _url;
		if(_url == this.currentLocation && this.loadingDeferred != null)
		{
			// Still loading
			return this.loadingDeferred.promise();
		}

		// Show loader div, start blocking
		var self = this;
		this.ajaxLoaderDiv = jQuery('<div class="loading ui-widget-overlay ui-front">'+egw.lang('please wait...')+'</div>').insertBefore(this.baseDiv);
		this.loadingDeferred = new jQuery.Deferred();
		
		// Try to escape from infinitive not resolved loadingDeferred
		// At least user can close the broken tab and work with the others.
		// Define a escape timeout for 5 sec
		this.ajaxLoaderDivTimeout = setTimeout(function(){
			self.ajaxLoaderDiv.hide().remove();
			self.ajaxLoaderDiv = null;
		},5000);
			
		this.loadingDeferred.always(function() {
			if(self.ajaxLoaderDiv)
			{
				self.ajaxLoaderDiv.hide().remove();
				self.ajaxLoaderDiv = null;
				// Remove escape timeout
				clearTimeout(self.ajaxLoaderDivTimeout);
			}
		});
		
		// Check whether the given url is a pseudo url which should be executed
		// by calling the ajax_exec function
		// we now send whole url back to server, so apps can use $_GET['ajax']==='true'
		// to detect app-icon was clicked and eg. further reset filters
		var matches = _url.match(/\/index.php\?menuaction=([A-Za-z0-9_\.]*.*&ajax=true.*)$/);
		if (matches) {
			// Matches[1] contains the menuaction which should be executed - replace
			// the given url with the following line. This will be evaluated by the
			// jdots_framework ajax_exec function which will be called by the code
			// below as we set useIframe to false.
			targetUrl = "index.php?menuaction=" + matches[1];
			useIframe = false;
		}
		// External link, but we'd still like to use everything without iframe
		if(_url.indexOf('no_popup=1') > 0)
		{
			useIframe = false;
		}

		// Destroy application js
		if(window.app[this.app.appName] && window.app[this.app.appName].destroy)
		{
			window.app[this.app.appName].destroy();
		}

		// Unload etemplate2, if there
		if(typeof etemplate2 == "function")
		{
			// Clear all etemplates on this tab, regardless of application, by using DOM nodes
			$j('.et2_container',this.contentDiv||this.baseDiv).each(function() {
				var et = etemplate2.getById(this.id);
				if(et !== null)
				{
					et.clear();
				}
			});
		}
		else if(this.iframe && typeof this.iframe.contentWindow.etemplate2 == "function")
		{
			try
			{
				if(typeof this.iframe.contentWindow.etemplate2 == "function")
				{
					// Clear all etemplates on this tab, regardless of application, by using DOM nodes
					var content = this.iframe.contentWindow;
					$j('.et2_container',this.iframe.contentWindow).each(function() {
						var et = content.etemplate2.getById(this.id);
						if(et !== null)
						{
							et.clear();
						}
					});
				}
			}
			catch(e) {}	// catch error if eg. SiteMgr runs a different origin, otherwise tab cant be closed
		}

		// Save the actual url which has been passed as parameter
		this.currentLocation = _url;

		//Set the browser type
		if (useIframe)
		{
			this.setBrowserType(EGW_BROWSER_TYPE_IFRAME);

			//Postpone the actual "navigation" - gives some speedup with internet explorer
			//as it does no longer blocks the complete page until all frames have loaded.
			window.setTimeout(function() {
				//Load the iframe content
				self.iframe.src = _url;

				//Set the "_legacy_iframe" flag to allow link handlers to easily determine
				//the type of the link source
				if (self.iframe && self.iframe.contentWindow) {
					try {
						self.iframe.contentWindow._legacy_iframe = true;

						// Focus the iframe of the current application
						if (self.app == framework.activeApp)
						{
							self.iframe.contentWindow.focus();
						}
					}
					catch (e) {
						// ignoer SecurityError: Blocked a frame ..., caused by different origin
					}
				}

				if(self.loadingDeferred)
				{
					self.loadingDeferred.resolve();
					self.loadingDeferred = null;
				}
			}, 1);
		}
		else
		{
			this.setBrowserType(EGW_BROWSER_TYPE_DIV);

			//Special treatement of "about:blank"
			if (targetUrl == "about:blank")
			{
				if (this.app.sidemenuEntry)
					this.app.sidemenuEntry.hideAjaxLoader();

				egw_widgetReplace(this.app.appName, this.contentDiv, '');
			}
			else
			{
				//Perform an AJAX request loading application output
				if (this.app.sidemenuEntry)
					this.app.sidemenuEntry.showAjaxLoader();
				this.data = "";
				$j(this.contentDiv).empty();
				var self_egw = egw(this.app.appName);
				var req = self_egw.json(
					this.app.getMenuaction('ajax_exec'),
					[targetUrl], this.browse_callback,this, true, this
				);
				req.sendRequest();
			}
		}
		return this.loadingDeferred.promise();
	},

	/**
	 *
	 * @param {type} _data
	 * @return {undefined} return undefined if data is not from the right response
	 */
	browse_callback: function(_data)
	{
		// Abort if data is from wrong kind of response - only 'data'
		if(!_data || _data.type != undefined) return;

		this.data = _data[0];
		this.browse_finished();
	},

	/**
	 *  Get call via browse_callback in order to attaching nodes to the DOM
	 */
	browse_finished: function()
	{
		if (this.app.sidemenuEntry)
			this.app.sidemenuEntry.hideAjaxLoader();
	//	egw_widgetReplace(this.app.appName, this.contentDiv, this.data);
		content = {
			html: this.data,
			js: ''
		};

		if (this.app == framework.activeApp)
		{
			window.focus();
		}

		egw_seperateJavaScript(content);

		// Insert the content
		$j(this.contentDiv).append(content.html);

		// Run the javascript code
		//console.log(content.js);
		$j(this.contentDiv).append(content.js);

		if(this.loadingDeferred)
		{
			this.loadingDeferred.resolve();
		}
	},

	/**
	 * REload the content of the browser object
	 */
	reload: function()
	{
		switch (this.type)
		{
			case EGW_BROWSER_TYPE_DIV:
				this.browse(this.currentLocation);
				break;

			case EGW_BROWSER_TYPE_IFRAME:
				//Do a simple reload in the iframe case
				this.iframe.contentWindow.location.reload();
				break;
		}
	},

	/**
	 *
	 */
	blank: function()
	{
		this.browse('about:blank', this.type == EGW_BROWSER_TYPE_IFRAME);
	}



});
