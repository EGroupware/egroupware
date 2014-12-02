/**
 * eGroupware Framework base object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @author Andreas Stoeckel <as@stylite.de>
 * @copyright Stylite AG 2014
 * @description Framework base module which creates fw_base object and includes basic framework functionallity
 */

"use strict";
/*egw:uses
	jquery.jquery;
	egw_inheritance.js;
*/

var fw_base =  Class.extend({

	/**
	 * Framework base class constructor sets up basic initialization
	 * @param {type} _sidemenuId
	 * @param {type} _tabsId
	 * @param {type} _webserverUrl
	 * @param {type} _sideboxSizeCallback
	 * @returns {undefined}
	 */
	init: function (_sidemenuId, _tabsId, _webserverUrl, _sideboxSizeCallback){
		/* Get the base div */
		this.sidemenuDiv = document.getElementById(_sidemenuId);
		this.tabsDiv = document.getElementById(_tabsId);
		this.webserverUrl = _webserverUrl;
		this.sideboxSizeCallback = _sideboxSizeCallback;
		window.egw_webserverUrl = _webserverUrl;

		this.serializedTabState = '';
		this.notifyTabChangeEnabled = false;

		this.sidemenuUi = null;
		this.tabsUi = null;

		this.applications = new Object();
		this.activeApp = null;

		//Register the resize handler
		$j(window).resize(function(){window.framework.resizeHandler();});

		//Register the global alert handler
		window.egw_alertHandler = this.alertHandler;

		//Register the key press handler
		//$j(document).keypress(this.keyPressHandler);

		//Override the app_window function
		window.egw_appWindow = this.egw_appWindow;

		// Override the egw_appWindowOpen function
		window.egw_appWindowOpen = this.egw_appWindowOpen;

		// Override the egw_getAppName function
		window.egw_getAppName = this.egw_getAppName;
	},

	/**
	 * Load applications
	 * @param {object} apps an object list of all applications
	 */
	loadApplications: function (apps)
	{
		//Close all open tabs, remove all applications from the application list
		this.sidemenuUi.clean();
		this.tabsUi.clean();

		var defaultApp = null;
		var restore = new Object;
		var restore_count = 0;

		var mkRestoreEntry = function(_app, _pos, _url, _active) {
			return {
				'app': _app,
				'position': _pos,
				'url': _url,
				'active': _active
			};
		};

		//Iterate through the application array returned
		for (var i = 0; i < apps.length; i++)
		{
			var app = apps[i];

			// Retrieve the application base url
			var baseUrl = false;
			if (typeof app.baseUrl == 'string')
			{
				baseUrl = app.baseUrl;
			}

			// Compute the instance internal name
			var internalName = app.name;
			if (typeof app.internalName == 'string')
			{
				internalName = app.internalName;
			}

			this.appData = new egw_fw_class_application(this,
				app.name, app.title, app.icon, app.url, app.sideboxwidth,
				baseUrl, internalName);

			//Create a sidebox menu entry for each application
			if (!app.noNavbar)
			{
				this.appData.sidemenuEntry = this.sidemenuUi.addEntry(
					this.appData.displayName, this.appData.icon,
					this.applicationClickCallback, this.appData, app.name);
			}

			//If this entry is the default entry, show it using the click callback
			if (app.isDefault && (app.isDefault === true) && (restore.length == 0))
			{
				defaultApp = this.appData;
			}

			//If the opened field is set, add the application to the restore array.
			if ((typeof app.opened != 'undefined') && (app.opened !== false))
			{
				defaultApp = null;

				var url = null;
				if (typeof app.openOnce != 'undefined' && app.openOnce)
					url = app.openOnce;

				restore[this.appData.appName] = mkRestoreEntry(this.appData, app.opened,
					url, app.active ? 1 : 0);
				restore_count += 1;
			}

			this.applications[this.appData.appName] = this.appData;
		}

		// else display the default application
		if (defaultApp && restore_count == 0)
		{
			restore[defaultApp.appName] = mkRestoreEntry(defaultApp, 0, null, 1);
		}
		return restore;
	},

	/**
	 * Navigate to the tab of an application (opening the tab if not yet open)
	 *
	 * @param {egw_fw_class_application} _app
	 * @param {string} _url optional url, default index page of app
	 * @param {bool} _hidden specifies, whether the application should be set active
	 *   after opening the tab
	 * @param {int} _pos
	 */
	applicationTabNavigate: function(_app, _url, _hidden, _pos)
	{
		//Default the post parameter to -1
		if (typeof _pos == 'undefined')
			_pos = -1;

		//Create the tab for that application
		this.createApplicationTab(_app, _pos);

		if (typeof _url == 'undefined' || _url == null)
		{
			_url = _app.indexUrl;
		}
		else if (_app.browser != null &&
			// check if app has its own linkHandler
			!(this.applications[_app.appName].app_refresh) &&
			_app.browser.iframe == null && _url == _app.browser.currentLocation)
		{
			// Just do an egw_refresh to avoid a full reload
			egw_refresh('',_app.appName);
			//Show the application tab
			if (_app.tab)
			{
				this.setActiveApp(_app);
			}
			return;
		}

		if (_app.browser == null)
		{
			//Create a new browser ui and set it as application tab callback
			var callback = new egw_fw_class_callback(this, this.getIFrameHeight);
			_app.browser = new fw_browser(_app, callback);
			_app.tab.setContent(_app.browser.baseDiv);
		}

		if (typeof _hidden == 'undefined' || !_hidden)
		{
			_app.browser.browse(_url);
			this.setActiveApp(_app);
		}
		else
		{
			this.notifyTabChange();
		}
	},

	/**
	 * Callback to calculate height of browser iframe or div
	 *
	 * @param {object} _iframe dom node of iframe or null for div
	 * @returns number in pixel
	 */
	getIFrameHeight: function(_iframe)
	{
		var $header = $j(this.tabsUi.appHeaderContainer);
		var height = $j(this.sidemenuDiv).height()-this.tabsUi.appHeaderContainer.outerHeight();
		return height;
	},

	/**
	 * Sets the sidebox data of an application
	 * @param {object} _app the application whose sidebox content should be set.
	 * @param {object} _data an array/object containing the data of the sidebox content
	 * @param {string} _md5 an md5 hash of the sidebox menu content: Only if this hash differs between two setSidebox calles, the sidebox menu will be updated.
	 */
	setSidebox: function(_app, _data, _md5)
	{
		if (typeof _app == 'string') _app = this.getApplicationByName(_app);

		if ((_app != null) && (_app.sidebox_md5 != _md5) && (_app.sidemenuEntry != null))
		{
			//Parse the sidebox data
			if (_data != null)
			{
				var contDiv = document.createElement('div');
				var contJS = ''; //new Array();
				for (var i = 0; i < _data.length; i++)
				{
					var catContent = '';
					for (var j = 0; j < _data[i].entries.length; j++)
					{
						/* As jquery executes all script tags which are found inside
						   the html and removes them afterwards, we have to seperate the
						   javaScript from the html in lang_item and add it manually. */
						this.html = new Object();
						this.html.html = _data[i].entries[j].lang_item;
						this.html.js = '';

						egw_seperateJavaScript(this.html);
						contJS += this.html.js;//contJS.concat(html.js);

						if (_data[i].entries[j].icon_or_star)
						{
							catContent += '<div class="egw_fw_ui_sidemenu_listitem"><img class="egw_fw_ui_sidemenu_listitem_icon" src="' + _data[i].entries[j].icon_or_star + '" />';
						}
						if (_data[i].entries[j].item_link == '')
						{
							catContent += this.html.html;
						}
						else
						{
							var link = _data[i].entries[j].item_link;
							if (link)
							{
								catContent += '<a href="' + link +
									(_data[i].entries[j].target ? '" target="'+_data[i].entries[j].target : '') +
									'">' + this.html.html + '</a>';
							}
						}
						if (_data[i].entries[j].icon_or_star)
						{
							catContent += '</div>';
						}
					}

					/* Append the category content */
					if (catContent != '')
					{
						var categoryUi = new egw_fw_ui_category(contDiv,_data[i].menu_name,
							_data[i].title, catContent, this.categoryOpenCloseCallback,
							this.categoryAnimationCallback, _app);

						//Lookup whether this entry was opened before. If no data is
						//stored about this, use the information we got from the server
						var opened = egw.preference('jdots_sidebox_'+_data[i].menu_name, _app.appName);
						if (typeof opened == 'undefined')
						{
							opened = _data[i].opened;
						}

						if (opened)
						{
							categoryUi.open(true);
						}
					}
				}
				// Stop ajax loader spinner icon in case there's no data and still is not stopped
				if (_data.length <= 0) _app.sidemenuEntry.hideAjaxLoader();
				//Rewrite all form actions if they contain some javascript
				var forms = $j('form', contDiv).toArray();
				for (var i = 0; i < forms.length; ++i)
				{
					var form = forms[i];
					if (form.action.indexOf('javascript:') == 0)
					{
						var action = form.action.match(/\('([^']*)/)[0].substr(2);
						form.action = action;
						form.target = 'egw_app_iframe_' + this.parseAppFromUrl(action).appName;
					}
				}

				_app.sidemenuEntry.setContent(contDiv);
				_app.sidebox_md5 = _md5;

				//console.log(contJS);
				$j(contDiv).append(contJS);
			}

			_app.hasSideboxMenuContent = true;

			//Only view the sidemenu content if this is really the active application
			if (_app == _app.parentFw.activeApp)
			{
				//Set the sidebox width if a application specific sidebox width is set
				if (_app.sideboxWidth !== false)
				{
					this.sideboxSizeCallback(_app.sideboxWidth);
				}
				_app.sidemenuEntry.parent.open(_app.sidemenuEntry);

				// reliable init sidebox, as app.js might initialise earlier
				if (typeof app[_app.appName] == 'object')
				{
					var sidebox = $j('#favorite_sidebox_'+_app.appName, this.sidemenuDiv);
					var self = this;
					var currentAppName = _app.appName;
					// make sidebox
					sidebox.children().sortable({

						items:'li:not([data-id$="add"])',
						placeholder:'ui-fav-sortable-placeholder',
						update: function (event, ui)
						{
							var favSortedList = jQuery(this).sortable('toArray', {attribute:'data-id'});

							egw().set_preference(currentAppName,'fav_sort_pref',favSortedList);
						}
					});
					if (sidebox.length) app[_app.appName]._init_sidebox.call(app[_app.appName], sidebox);
				}
			}
		}
	},
	/**
	 *
	 */
	notifyTabChange: function()
	{
		// Call the "resize" function of the currently active app
		if (this.activeApp)
		{
			var browser = this.activeApp.browser;
			if (browser)
			{
				window.setTimeout(function() {
					browser.callResizeHandler();

					// Focus the current window so that keyboard input is forwarderd
					// to it. The timeout is needed, as this is function is often
					// called by the click on a jdots-tab. And that click immediately
					// focuses the outer window again.
					if (browser.iframe && browser.iframe.contentWindow)
					{
						browser.iframe.contentWindow.focus();
					}
					else
					{
						window.focus();
					}
				}, 100);
			}
		}

		if (this.notifyTabChangeEnabled)
		{
			//Send the current tab list to the server
			var data = this.assembleTabList();

			//Serialize the tab list and check whether it really has changed since the last
			//submit
			var serialized = egw.jsonEncode(data);
			if (serialized != this.serializedTabState)
			{
				this.serializedTabState = serialized;

				var request = egw.jsonq("home.jdots_framework.ajax_tab_changed_state", [data]);
			}
		}
	},
	/**
	 * @param {function} _opened
	 * Sends sidemenu entry category open/close information to the server using an AJAX request
	 */
	categoryOpenCloseCallback: function(_opened)
	{
		egw.set_preference(this.tag.appName, 'jdots_sidebox_'+this.catName, _opened);
	},

	categoryAnimationCallback: function()
	{

	},


	/**
	 * Creates an ordered list with all opened tabs and whether the tab is currently active
	 * @return {array} returns an array of tabs
	 */
	assembleTabList: function()
	{
		var result = [];
		for (var i = 0; i < this.tabsUi.tabs.length; i++)
		{
			var tab = this.tabsUi.tabs[i];
			result[i] = {
				'appName': tab.tag.appName,
				'active': tab == this.tabsUi.activeTab
			};
		}

		return result;
	},

	/**
	 *
	 * @param {app object} _app
	 * @param {int} _pos
	 * Checks whether the application already owns a tab and creates one if it doesn't exist
	 */
	createApplicationTab: function(_app, _pos)
	{
		//Default the pos parameter to -1
		if (typeof _pos == 'undefined')
			_pos = -1;

		if (_app.tab == null)
		{
			//Create the tab
			_app.tab = this.tabsUi.addTab(_app.icon, this.tabClickCallback, this.tabCloseClickCallback,
				_app, _pos);
			_app.tab.setTitle(_app.displayName);

			//Set the tab closeable if there's more than one tab
			this.tabsUi.setCloseable(this.tabsUi.tabs.length > 1);


		}
	},

	/**
	 * applicationClickCallback is used internally by egw_fw in order to handle clicks on
	 * an application in the sidebox menu.
	 *
	 * @param {egw_fw_ui_tab} _sender specifies the tab ui object, the user has clicked
	 */
	applicationClickCallback: function(_sender)
	{
		this.tag.parentFw.applicationTabNavigate(this.tag, this.tag.indexUrl);
	},
	/**
	 * tabClickCallback is used internally by egw_fw in order to handle clicks on
	 * a tab.
	 *
	 * @param {egw_fw_ui_tab} _sender specifies the tab ui object, the user has clicked
	 */
	tabClickCallback: function(_sender)
	{
	   //Set the active application in the framework
	   this.tag.parentFw.setActiveApp(this.tag);
	},

	/**
	 * tabCloseClickCallback is used internally by egw_fw in order to handle clicks
	 * on the close button of every tab.
	 *
	 * @param {egw_fw_ui_tab} _sender specifies the tab ui object, the user has clicked
	 */
	tabCloseClickCallback: function(_sender)
	{
		//Save references to the application and the tabsUi as "this" will be deleted
		var app = this.tag;
		var tabsUi = this.parent;

		//At least one tab must stay open
		if (tabsUi.tabs.length > 1)
		{
			//Tell the browser object to browse to an empty page, which will trigger the
			//unload handler
			app.browser.blank();

			this.tag.parentFw.notifyTabChangeEnabled = false;

			tabsUi.removeTab(this);
			app.tab = null;
			app.browser = null;

			if (app.sidemenuEntry)
				app.sidemenuEntry.hideAjaxLoader();

			//Set the active application to the application of the currently active tab
			app.parentFw.setActiveApp(tabsUi.activeTab.tag);

			this.tag.parentFw.notifyTabChangeEnabled = true;

			this.tag.parentFw.notifyTabChange();
		}

		tabsUi.setCloseable(tabsUi.tabs.length > 1);

		//As a new tab might remove a row from the tab header, we have to resize all tab content browsers
		 this.tag.parentFw.resizeHandler();
	 },

	/**
	 * @param {string} _url
	 * Tries to obtain the application from a menuaction
	 */
	parseAppFromUrl: function(_url)
	{
		var _app = null;

		//Read the menuaction parts from the url and check whether the first part
		//of the url contains a valid app name
		var matches = _url.match(/menuaction=([a-z0-9_-]+)\./i);
		if (matches && (_app = this.getApplicationByName(matches[1])))
		{
			return _app;
		}

		//Check the url for a scheme of "/app/something.php" and check this one for a valid app
		//name
		var matches = _url.match(/\/([^\/]+)\/[^\/]+\.php/i);
		if (matches && (_app = this.getApplicationByName(matches[1])))
		{
			return _app;
		}

		return null;
	},

	/**
	 * Goes through all applications and returns the application with the specified name.
	 * @param {string} _name the name of the application which should be returned.
	 * @return object or null if application is not found.
	 */
	getApplicationByName: function(_name)
	{
		if (typeof this.applications[_name] != 'undefined')
		{
			return this.applications[_name];
		}

		return null;
	},

	/**
	 * Sets the website title of an application
	 * @param {object} _app the application whose title should be set.
	 * @param {string} _title title to set
	 * @param {object} _header
	 */
	setWebsiteTitle: function(_app, _title, _header)
	{
		if (typeof _app == 'string') _app = this.getApplicationByName(_app);

		if (_app) {
			_app.website_title = _title;

			// only set app_header if different from app-name
			if (_header && _header != egw.lang(_app.appName))
			{
				_app.app_header = _header;
			}
			else
			{
				_app.app_header = '';
			}
			if (_app == this.activeApp)
				this.refreshAppTitle();
		}
	},

	/**
	 * Handles alert message
	 *
	 * @param {type} _message
	 * @param {type} _details
	 */
	alertHandler: function (_message, _details)
	{
		if (_details)
		{
			alert('Error:\n ' + _message + '\n\nDetails:\n ' + _details);
		}
		else
		{
			alert(_message);
		}
	},

	/**
	 * Call online manual
	 *
	 * @param {string} referer optional referer, default use activeApp
	 */
	callManual: function(referer)
	{
		if (typeof referer == 'undefined' && this.activeApp && this.activeApp.appName != 'manual')
		{
			referer = this.activeApp.indexUrl;
			if (this.activeApp.browser.iframe && this.activeApp.browser.iframe.contentWindow.location)
			{
				//this.activeApp.browser.iframe.contentWindow.callManual();
				referer = this.activeApp.browser.iframe.contentWindow.location.href;
			}
		}
		if (typeof referer != 'undefined')
		{
			this.linkHandler(egw.link('/index.php', {
				menuaction: 'manual.uimanual.view',
				referer: referer
			}), 'manual', true);
		}
	},

	/**
	 *
	 * @param {type} _link
	 * @param {type} _app
	 * @param {type} _useIframe
	 * @param {type} _linkSource
	 * @returns {undefined}
	 */
	linkHandler: function(_link, _app, _useIframe, _linkSource)
	{
		//Determine the app string from the application parameter
		var app = null;
		if (_app && typeof _app == 'string')
		{
			app = this.getApplicationByName(_app);
		}

		if (!app)
		{
			//The app parameter was false or not a string or the application specified did not exists.
			//Determine the target application from the link that had been passed to this function
			app = this.parseAppFromUrl(_link);
		}

		if (app)
		{
			this.applicationTabNavigate(app, _link);
		}
		else
		{
			//Display some error messages to have visible feedback
			if (typeof _app == 'string')
			{
				egw_alertHandler('Application "' + _app + '" not found.',
					'The application "' + _app + '" the link "' + _link + '" points to is not registered.');
			}
			else
			{
				egw_alertHandler("No appropriate target application has been found.",
					"Target link: " + _link);
			}
		}
	},
	/**
	 * Redirect window to the URL
	 * @param {string} _url
	 */
	redirect: function(_url)
	{
		window.location = _url;
	},

	/**
	* Sets the active framework application to the application specified by _app
	*
	* @param {egw_fw_class_application} _app application object
	*/
	setActiveApp: function(_app)
	{
		//Only perform the following commands if a new application is activated
		if (_app != this.activeApp)
		{
			// tab not yet loaded, load it now
			if (!_app.browser.currentLocation && !_app.browser.iframe)
			{
				this.applicationTabNavigate(_app, _app.indexUrl);
				return;
			}
			this.activeApp = _app;

			//Open the sidemenuUi that belongs to the app, if no sidemenu is attached
			//to the app, close the sidemenuUi
			if (_app.sidemenuEntry)
			{
				if (_app.hasSideboxMenuContent)
				{
					this.sidemenuUi.open(_app.sidemenuEntry);
				}
			}
			else
			{
				this.sidemenuUi.open(null);
			}

			//Set the website title
			this.refreshAppTitle();

			//Show the application tab
			if (_app.tab)
			{
				this.tabsUi.showTab(_app.tab);

				//Going to a new tab changes the tab state
				this.notifyTabChange(_app.tab);
			}
		}
	},

	/**
	 * Open a (centered) popup window with given size and url
	 *
	 * @param {string} _url
	 * @param {number} _width
	 * @param {number} _height
	 * @param {string} _windowName or "_blank"
	 * @param {string|boolean} _app app-name for framework to set correct opener or false for current app
	 * @param {boolean} _returnID true: return window, false: return undefined
	 * @param {type} _status "yes" or "no" to display status bar of popup
	 * @param {DOMWindow} _parentWnd parent window
	 * @returns {DOMWindow|undefined}
	 */
	openPopup: function(_url, _width, _height, _windowName, _app, _returnID, _status, _parentWnd)
	{
		//Determine the window the popup should be opened in - normally this is the iframe of the currently active application
		var parentWindow = _parentWnd || window;
		var navigate = false;
		if (typeof _app != 'undefined' && _app !== false)
		{
			var appEntry = framework.getApplicationByName(_app);
			if (appEntry && appEntry.browser == null)
			{
				navigate = true;
				framework.applicationTabNavigate(appEntry, 'about:blank');
			}
		}
		else
		{
			var appEntry = framework.activeApp;
		}

		if (appEntry != null && appEntry.browser.iframe != null && (_app || !egw(parentWindow).is_popup()))
			parentWindow = appEntry.browser.iframe.contentWindow;

		var windowID = egw(parentWindow).openPopup(_url, _width, _height, _windowName, _app, true, _status, true);

		windowID.framework = this;

		if (navigate)
		{
			window.setTimeout("framework.applicationTabNavigate(framework.activeApp, framework.activeApp.indexUrl);", 500);
		}

		if (_returnID !== false) return windowID;
	},

	/**
	 * Get application window
	 * @param {type} _app
	 * @returns {window|iframe content}
	 */
	egw_appWindow: function(_app)
	{
		var app = framework.getApplicationByName(_app);
		var result = window;
		if (app != null && app.browser != null && app.browser.iframe != null)
		{
			result = app.browser.iframe.contentWindow;
		}
		return result;
	},

	/**
	 * Opens application with provided url
	 * @param {string|app object} _app app name or app object
	 * @param {string} _url url
	 */
	egw_appWindowOpen: function(_app, _url)
	{
		if (typeof _url == "undefined") {
			_url = "about:blank";
		}

		// Do a global location change if the given application name is null (as this function
		// is called by egw_json.js redirect handler, where the _app parameter defaults to null)
		if (_app == null) {
			window.location = _url;
		}

		var app = null;
		if (typeof _app == "string") {
			app = framework.getApplicationByName(_app);
		} else {
			app = _app;
		}

		if (app != null) {
			framework.applicationTabNavigate(app, _url);
		}
	},

	/**
	 * Gets application name
	 *
	 * @returns {string} returns application name
	 */

	egw_getAppName: function()
	{
		return framework.activeApp.appName;
	},

	/**
	 * Display an error or regular message
	 *
	 * @param {string} _msg message to show
	 * @param {string} _type 'error', 'warning' or 'success' (default)
	 */
	setMessage: function(_msg, _type)
	{
		if (typeof _type == 'undefined')
			_type = _msg.match(/error/i) ? 'error' : 'success';

		if (this.messageTimer)
		{
			window.clearTimeout(this.messageTimer);
			delete this.messageTimer;
		}

		this.tabsUi.setAppHeader(_msg, _type+'_message');
		this.resizeHandler();

		if (_type != 'error')	// clear message again after some time, if no error
		{
			var self = this;
			this.messageTimer = window.setTimeout(function() {
				self.refreshAppTitle.call(self);
			}, 5000);
		}
	},

	/**
	 * Change timezone and refresh current app
	 * @param _tz
	 */
	tzSelection: function(_tz)
	{
		//Perform an AJAX request to tell server
		var req = egw.json('home.jdots_framework.ajax_tz_selection.template',[_tz],null,null,false); // false = synchron
		req.sendRequest();

		if (this.activeApp.browser)
		{
			this.activeApp.browser.reload();
		}
	},

	/**
	 * Refresh application title
	 */
	refreshAppTitle: function()
	{
		if (this.activeApp)
		{
			if (this.messageTimer)
			{
				window.clearTimeout(this.messageTimer);
				delete this.messageTimer;
			}

			this.tabsUi.setAppHeader(this.activeApp.app_header);
			document.title = this.activeApp.website_title;
		}

		this.resizeHandler();
	},

	/**
	 *
	 */
	resizeHandler: function()
	{
		//Resize the browser area of the applications
		for (var app in this.applications)
		{
			if (this.applications[app].browser != null)
			{
				this.applications[app].browser.resize();
			}
		}
	},

	/**
	 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string|undefined} _app application name
	 * @param {string|number|undefined} _id id of entry to refresh
	 * @param {string|undefined} _type either 'edit', 'delete', 'add' or undefined
	 * @param {string|undefined} _targetapp which app's window should be refreshed, default current
	 * @param {string|RegExp} _replace regular expression to replace in url
	 * @param {string} _with
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @return {DOMwindow|null} null if refresh was triggered, or DOMwindow of app
	 */
	refresh: function(_msg, _app, _id, _type, _targetapp, _replace, _with, _msg_type)
	{
		//alert("egw_refresh(\'"+_msg+"\',\'"+_app+"\',\'"+_id+"\',\'"+_type+"\')");

		if (!_app)	// force reload of entire framework, eg. when template-set changes
		{
			window.location.href = window.egw_webserverUrl+'/index.php?cd=yes'+(_msg ? '&msg='+encodeURIComponent(_msg) : '');
		}
		// Call appropriate default / fallback refresh
		var win = window;
		var app = this.getApplicationByName(_app);
		if (app)
		{
			// app with closed, or not yet loaded tab --> ignore update, happens automatic when tab loads
			if (!app.browser)
			{
				return;
			}
			if (app.browser && app.browser.iframe)
			{
				win = app.browser.iframe.contentWindow;
			}
		}

		// app running top-level (no full refresh / window reload!)
		if (win == window)
		{
			var refresh_done = false;
			// et2 nextmatch available, let it refresh
			if(typeof etemplate2 == "function" && etemplate2.app_refresh)
			{
				refresh_done = etemplate2.app_refresh(_msg, _app, _id, _type);
			}
			// if not trigger a regular refresh
			if (!refresh_done)
			{
				if (!app) app = this.activeApp;
				if (app && app.browser)	app.browser.reload();
			}
		}

		// if different target-app given, refresh it too
		if (_targetapp && _app != _targetapp)
		{
			this.refresh(_msg, _targetapp, null, null, null, _replace, _with, _msg_type);
		}

		// app runs in iframe (refresh iframe content window)
		if (win != window)
		{
			return win;
		}
	},

	/**
	 * Print function prints the active window
	 */
	print: function()
	{
		if (this.activeApp && this.activeApp.appName != 'manual')
		{
			var appWindow = this.egw_appWindow(this.activeApp.appName);
			if (appWindow)
			{
				appWindow.focus();
				appWindow.print();
			}
		}
	}
});
