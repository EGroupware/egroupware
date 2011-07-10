/**
 * eGroupware JavaScript Framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */


/**
 * Some constant definitions
 */

EGW_LINK_SOURCE_FRAMEWORK = 0;
EGW_LINK_SOURCE_LEGACY_IFRAME = 1;
EGW_LINK_SOURCE_POPUP = 2;

/**
 * Class: egw_fw
 * The egw_fw class is the base framework class. It wraps around all egw_fw_ui and
 * egw_fw_classes. It creates both, a side bar and a tab area and cares about linking.
 */


/**
 * The constructor of the egw_fw class.
 *
 * @param string _sidemenuId specifies the name of the div container which should contain the sidebar menu
 * @param string _tabsId specifies the name of the div container which should cotain the tab area
 * @param string _webserverUrl specifies the egroupware root url
 */
function egw_fw(_sidemenuId, _tabsId, _splitterId, _webserverUrl, _sideboxSizeCallback,
	_sideboxStartSize, _sideboxMinSize)
{
	/* Get the base div */
	this.sidemenuDiv = document.getElementById(_sidemenuId);
	this.tabsDiv = document.getElementById(_tabsId);
	this.splitterDiv = document.getElementById(_splitterId);
	this.webserverUrl = _webserverUrl;
	this.sideboxSizeCallback = _sideboxSizeCallback;
	window.egw_webserverUrl = _webserverUrl;

	this.serializedTabState = '';
	this.notifyTabChangeEnabled = false;

	this.sidemenuUi = null;
	this.tabsUi = null;

	this.categoryOpenCache = new Object();

	this.applications = new Object();
	this.activeApp = null;

	if (this.sidemenuDiv && this.tabsDiv && this.splitterDiv)
	{
		//Wrap a scroll area handler around the applications
		this.scrollAreaUi = new egw_fw_ui_scrollarea(this.sidemenuDiv);

		//Create the sidemenu, the tabs area and the splitter
		this.sidemenuUi = new egw_fw_ui_sidemenu(this.scrollAreaUi.contentDiv,
			this.sortCallback);
		this.tabsUi = new egw_fw_ui_tabs(this.tabsDiv);
		this.splitterUi = new egw_fw_ui_splitter(this.splitterDiv,
			EGW_SPLITTER_VERTICAL, this.splitterResize, 
			[
				{
					"size": _sideboxStartSize,
					"minsize": _sideboxMinSize,
					"maxsize": 0
				},
			], this);
		

		this.loadApplications("home.jdots_framework.ajax_navbar_apps");
	}

	_sideboxSizeCallback(_sideboxStartSize);

	//Register the resize handler
	$j(window).resize(function(){window.framework.resizeHandler()});

	//Register the global alert handler
	window.egw_alertHandler = this.alertHandler;

	//Register the key press handler
	//$j(document).keypress(this.keyPressHandler);

	//Override the old egw_openWindowCentered2
	window.egw_openWindowCentered2 = this.egw_openWindowCentered2;

	//Override the app_window function
	window.egw_appWindow = this.egw_appWindow;

	// Override the egw_appWindowOpen function
	window.egw_appWindowOpen = this.egw_appWindowOpen;

	// Override the egw_getAppName function
	window.egw_getAppName = this.egw_getAppName;
}

egw_fw.prototype.alertHandler = function(_message, _details)
{
	if (_details)
	{
		alert('Error:\n ' + _message + '\n\nDetails:\n ' + _details);
	}
	else
	{
		alert(_message);
	}
}

egw_fw.prototype.callManual = function()
{
	if (this.activeApp && this.activeApp.appName != 'manual')
	{
		if (this.activeApp.browser.iframe)
		{
			this.activeApp.browser.iframe.contentWindow.callManual();
		}
	}
}

egw_fw.prototype.print = function()
{
	if (this.activeApp && this.activeApp.appName != 'manual')
	{
		if (this.activeApp.browser.iframe)
		{
			this.activeApp.browser.iframe.contentWindow.focus();
			this.activeApp.browser.iframe.contentWindow.print();
		}
	}
}

egw_fw.prototype.redirect = function(_url)
{
	window.location = _url;
}

/**
 * Sets the active framework application to the application specified by _app
 */
egw_fw.prototype.setActiveApp = function(_app)
{
	//Only perform the following commands if a new application is activated
	if (_app != this.activeApp)
	{
		this.activeApp = _app;

		//Set the sidebox width if a application specific sidebox width is set
		if (_app.sideboxWidth !== false)
		{
			this.sideboxSizeCallback(_app.sideboxWidth);
			this.splitterUi.constraints[0].size = _app.sideboxWidth;
		}

		//Open the sidemenuUi that belongs to the app, if no sidemenu is attached
		//to the app, close the sidemenuUi
		if (_app.sidemenuEntry)
		{
			if (_app.hasSideboxMenuContent)
			{
				this.sidemenuUi.open(_app.sidemenuEntry);
			}
			else
			{
				//Probalby the sidemenu data just got lost along the way. This
				//for example happens, when a user double clicks on a menu item
				var req = new egw_json_request(_app.getMenuaction('ajax_sidebox'),
					[_app.internalName, _app.sidebox_md5]);
				_app.sidemenuEntry.showAjaxLoader();
				req.sendRequest(false, function(data) {
						if ((typeof data.md5 != 'undefined') &&
							(typeof data.data != 'undefined'))
						{
							this.fw.setSidebox(this.app, data.data,  data.md5);
							this.app.sidemenuEntry.hideAjaxLoader();
						}
				}, {'app' : _app, 'fw' : this});
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

		//Resize the scroll area...
		this.scrollAreaUi.update();

		//...and scroll to the top
		this.scrollAreaUi.setScrollPos(0);
	}
}

/**
 * Function called whenever the sidemenu entries are sorted
 */
egw_fw.prototype.sortCallback = function(_entriesArray)
{
	//Create an array with the names of the applications in their sort order	
	var name_array = new Array();
	for (var i = 0; i < _entriesArray.length; i++)
	{
		name_array.push(_entriesArray[i].tag.appName);
	}
	
	//Send the sort order to the server via ajax
	var req = new egw_json_request('home.jdots_framework.ajax_appsort',
		[name_array]);
	req.sendRequest(true);
}

/**
 * Function called whenever the sidebox is resized
 */
egw_fw.prototype.splitterResize = function(_width)
{
	if (this.tag.activeApp)
	{
		app = this.tag.activeApp;
		var req = new egw_json_request(app.getMenuaction('ajax_sideboxwidth'),
			[app.internalName, _width]);
		req.sendRequest(true);

		//If there are no global application width values, set the sidebox width of
		//the application every time the splitter is resized
		if (this.tag.activeApp.sideboxWidth !== false)
		{
			this.tag.activeApp.sideboxWidth = _width;
		}
	}
	this.tag.sideboxSizeCallback(_width);
}

/**
 * tabCloseClickCallback is used internally by egw_fw in order to handle clicks
 * on the close button of every tab.
 *
 * @param egw_fw_ui_tab _sender specifies the tab ui object, the user has clicked
 */
egw_fw.prototype.tabCloseClickCallback = function(_sender)
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
}

egw_fw.prototype.resizeHandler = function()
{
	//Resize the browser area of the applications
	for (var app in this.applications)
	{
		if (this.applications[app].browser != null)
		{
			this.applications[app].browser.resize();
		}
	}

	//Update the scroll area
	this.scrollAreaUi.update();
}

egw_fw.prototype.getIFrameHeight = function()
{
	var height = $j(window).height() - (
		this.tabsUi.appHeaderContainer.offsetTop +
		this.tabsUi.appHeaderContainer.offsetHeight + 30); /* 30 is the height of the footer */
	return height;
}

/**
 * tabClickCallback is used internally by egw_fw in order to handle clicks on
 * a tab.
 *
 * @param egw_fw_ui_tab _sender specifies the tab ui object, the user has clicked
 */
egw_fw.prototype.tabClickCallback = function(_sender)
{
	//Set the active application in the framework
	this.tag.parentFw.setActiveApp(this.tag);
}

/**
 * applicationClickCallback is used internally by egw_fw in order to handle clicks on
 * an application in the sidebox menu.
 *
 * @param egw_fw_ui_tab _sender specifies the tab ui object, the user has clicked
 */
egw_fw.prototype.applicationClickCallback = function(_sender)
{
	this.tag.parentFw.applicationTabNavigate(this.tag, this.tag.indexUrl);
}


/**
 * Creates an ordered list with all opened tabs and whether the tab is currently active
 */
egw_fw.prototype.assembleTabList = function()
{
	var result = [];
	for (var i = 0; i < this.tabsUi.tabs.length; i++)
	{
		var tab = this.tabsUi.tabs[i];
		result[i] = {
			'appName': tab.tag.appName,
			'active': tab == this.tabsUi.activeTab
		}
	}

	return result;
}

egw_fw.prototype.notifyTabChange = function()
{
	// Call the "resize" function of the currently active app
	if (this.activeApp)
	{
		var browser = this.activeApp.browser;
		if (browser)
		{
			window.setTimeout(function() {
				browser.callResizeHandler()

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
		var serialized = egw_json_encode(data);
		if (serialized != this.serializedTabState)
		{
			this.serializedTabState = serialized;

			var request = new egw_json_request("home.jdots_framework.ajax_tab_changed_state", [data]);
			request.sendRequest();
		}
	}
}

/**
 * Checks whether the application already owns a tab and creates one if it doesn't exist
 */
egw_fw.prototype.createApplicationTab = function(_app, _pos)
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
}

/**
 * Navigate to the tab of an application (opening the tab if not yet open)
 * 
 * @param egw_fw_class_application _app
 * @param string _url optional url, default index page of app
 * @param bool _hidden specifies, whether the application should be set active
 *   after opening the tab
 */
egw_fw.prototype.applicationTabNavigate = function(_app, _url, _hidden, _pos)
{
	//Default the post parameter to -1
	if (typeof _pos == 'undefined')
		_pos = -1;

	//Create the tab for that application
	this.createApplicationTab(_app, _pos);

	if (typeof _url == 'undefined' || _url == null)
		_url = _app.indexUrl;

	if (_app.browser == null)
	{
		//Create a new browser ui and set it as application tab callback
		var callback = new egw_fw_class_callback(this, this.getIFrameHeight);
		_app.browser = new egw_fw_content_browser(_app, callback);
		_app.tab.setContent(_app.browser.baseDiv);
	}

	_app.browser.browse(_url, _hidden);

	if (typeof _hidden == 'undefined' || !_hidden)
	{
		this.setActiveApp(_app);
	}
	else
	{
		this.notifyTabChange();
	}
}

/**
 * Tries to obtain the application from a menuaction
 */
egw_fw.prototype.parseAppFromUrl = function(_url)
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
}

/**
 * loadApplicationsCallback is internally used by egw_fw in order to handle the
 * receiving of the application list.
 *
 * @param object apps contains the parsed JSON data describing the applications.
 *	The JSON object should have the following structure
 *		apps = array[
 *			{
 *				string name (the internal name of the application)
 *				string title (the name of the application how it should be viewed)
 *				string icon (path to the icon of the application)
 *				string url (path to the application) //TODO: Change this
 *				[boolean isDefault] (whether this entry is the default entry which should be opened)
 *			}
 *		]
 */
egw_fw.prototype.loadApplicationsCallback = function(apps)
{
	var defaultApp = null;
	var restore = new Object;
	var restore_count = 0;

	var mkRestoreEntry = function(_app, _pos, _url, _active) {
		return {
			'app': _app,
			'position': _pos,
			'url': _url,
			'active': _active
		}
	}

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

		appData = new egw_fw_class_application(this, 
			app.name, app.title, app.icon, app.url, app.sideboxwidth,
			baseUrl, internalName);

		//Create a sidebox menu entry for each application
		if (!app.noNavbar)
		{
			appData.sidemenuEntry = this.sidemenuUi.addEntry(
				appData.displayName, appData.icon,
				this.applicationClickCallback, appData);
		}

		//If this entry is the default entry, show it using the click callback
		if (app.isDefault && (app.isDefault === true) && (restore.length == 0))
		{
			defaultApp = appData;
		}

		//If the opened field is set, add the application to the restore array.
		if ((typeof app.opened != 'undefined') && (app.opened !== false))
		{			
			defaultApp = null;

			var url = null;
			if (typeof app.openOnce != 'undefined' && app.openOnce)
				url = app.openOnce;

			restore[appData.appName] = mkRestoreEntry(appData, app.opened,
				url, app.active ? 1 : 0);
			restore_count += 1;
		}

		this.applications[appData.appName] = appData;
	}

	//Processing of the url or the defaultApp is now deactivated. 

/*	// check if a menuaction or app is specified in the url --> display that
	var _app = this.parseAppFromUrl(window.location.href);
	if (_app)
	{
		//If this app is already opened, don't change its position. Otherwise
		//add it to the end of the tab list
		var appPos = restore_count;
		if (typeof restore[_app.appName] != 'undefined')
			appPos = restore[_app.appName].position;
		
		restore[_app.appName] = mkRestoreEntry(_app, appPos,
			window.location.href.replace(/&?cd=yes/,''), 2);
	}*/

	// else display the default application
	if (defaultApp && restore_count == 0)
	{
		restore[defaultApp.appName] = mkRestoreEntry(defaultApp, 0, null, 1);
	}

	//Generate an array with all tabs which shall be restored sorted in by
	//their active state

	//Fill in the sorted_restore array...
	var sorted_restore = [];
	for (appName in restore)
		sorted_restore[sorted_restore.length] = restore[appName];

	//...and sort it
	sorted_restore.sort(function (a, b) {
		return ((a.active < b.active) ? 1 : ((a.active == b.active) ? 0 : -1));
	});

	//Now actually restore the tabs by passing the application, the url, whether
	//this is an legacyApp (null triggers the application default), whether the
	//application is hidden (only the active tab is shown) and its position
	//in the tab list.
	for (var i = 0; i < sorted_restore.length; i++)
		this.applicationTabNavigate(
			sorted_restore[i].app, sorted_restore[i].url, i != 0,
			sorted_restore[i].position);

	this.scrollAreaUi.update();

	//Set the current state of the tabs and activate TabChangeNotification.
	this.serializedTabState = egw_json_encode(this.assembleTabList());
	this.notifyTabChangeEnabled = true;
}

/**
 * loadApplications refreshes the list of applications. Upon calling, all existing applications
 * will be deleted from the list, and all open tabs will be closed. Then an AJAX request to the
 * given URL will be send in order to obtain the application list with JSON encoding.
 *
 * @param string _menuaction specifies the menuaction
 */
egw_fw.prototype.loadApplications = function(_menuaction)
{
	//Close all open tabs, remove all applications from the application list
	this.sidemenuUi.clean();
	this.tabsUi.clean();

	//Perform an AJAX request loading all available applications
	var req = new egw_json_request(_menuaction, [window.location.href])
	req.sendRequest(true, this.loadApplicationsCallback, this);
}

/**
 * Goes through all applications and returns the application with the specified name.
 * @param string _name the name of the application which should be returned.
 * @return object or null if application is not found.
 */
egw_fw.prototype.getApplicationByName = function(_name)
{
	if (typeof this.applications[_name] != 'undefined')
	{
		return this.applications[_name];
	}

	return null;
}

/**
 * Sends sidemenu entry category open/close information to the server using an AJAX request
 */
egw_fw.prototype.categoryOpenCloseCallback = function(_opened)
{
	// switched off, 'til we start using it
	//var req = new egw_json_request("home.jdots_framework.ajax_sidebox_menu_opened",
	//	[this.tag.appName, this.catName, _opened]);
	//req.sendRequest(true);

	/* Store the state of the category localy */	
	this.tag.parentFw.categoryOpenCache[this.tag.appName + '#' + this.catName] = _opened;
//	this.tag.parentFw.scrollAreaUi.update();
}

egw_fw.prototype.categoryAnimationCallback = function()
{
	this.tag.parentFw.scrollAreaUi.update();
}

/**
 * Sets the sidebox data of an application
 * @param object _app the application whose sidebox content should be set.
 * @param object _data an array/object containing the data of the sidebox content
 * @param string _md5 an md5 hash of the sidebox menu content: Only if this hash differs between two setSidebox calles, the sidebox menu will be updated.
 */
egw_fw.prototype.setSidebox = function(_app, _data, _md5)
{
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
					html = new Object();
					html.html = _data[i].entries[j].lang_item;
					html.js = '';

					egw_seperateJavaScript(html);
					contJS += html.js;//contJS.concat(html.js);

					if (_data[i].entries[j].icon_or_star)
					{
						catContent += '<div class="egw_fw_ui_sidemenu_listitem"><img class="egw_fw_ui_sidemenu_listitem_icon" src="' + _data[i].entries[j].icon_or_star + '" />';
					}
					if (_data[i].entries[j].item_link == '')
					{
						catContent += html.html;
					}
					else
					{
						//Parse the given href and replace the given application name
						//(which might be wrong because this is an application from another
						//instance)
						var link = _data[i].entries[j].item_link;
						if (link)
						{
							var matches = link.match(/javascript:egw_link_handler\('([^']*)'/);
							if (matches)
							{
								link = "javascript:egw_link_handler('" + matches[1] + "', '" + _app.appName + "');";
							}
							catContent += '<a href="' + link + 
								(_data[i].entries[j].target ? '" target="'+_data[i].entries[j].target : '') +
								'">' + html.html + '</a>';
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
					var opened = this.categoryOpenCache[
						_app.appName + '#' + _data[i].menu_name];
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
			_app.sidemenuEntry.parent.open(_app.sidemenuEntry);
			_app.parentFw.scrollAreaUi.update();
			_app.parentFw.scrollAreaUi.setScrollPos(0);
		}
	}
}

/**
 * Sets the website title of an application
 * @param object _app the application whose title should be set.
 * @param string _title title to set
 */
egw_fw.prototype.setWebsiteTitle = function(_app, _title, _header)
{
	if (_app) {
		_app.website_title = _title;
		_app.app_header = _header;

		if (_app == this.activeApp)
			this.refreshAppTitle();
	}
}

egw_fw.prototype.refreshAppTitle = function()
{
	if (this.activeApp)
	{
		this.tabsUi.setAppHeader(this.activeApp.app_header);
		document.title = this.activeApp.website_title;
	}

	this.resizeHandler();
}

/**
 * Change timezone and refresh current app
 * @param _tz
 */
egw_fw.prototype.tzSelection = function(_tz)
{
	//Perform an AJAX request to tell server
	var req = new egw_json_request('home.jdots_framework.ajax_tz_selection.template',[_tz]);
	req.sendRequest(false);		// false = synchron
	
	if (this.activeApp.browser)
	{
		this.activeApp.browser.relode();
	}
}

egw_fw.prototype.linkHandler = function(_link, _app, _useIframe, _linkSource)
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
}

egw_fw.prototype.egw_openWindowCentered2 = function(_url, _windowName, _width, _height, _status, _app, _returnID)
{
	if (typeof _returnID == 'undefined') _returnID = false;
	windowWidth = egw_getWindowOuterWidth();
	windowHeight = egw_getWindowOuterHeight();

	positionLeft = (windowWidth/2)-(_width/2)+egw_getWindowLeft();
	positionTop  = (windowHeight/2)-(_height/2)+egw_getWindowTop();

	//Determine the window the popup should be opened in - normally this is the iframe of the currently active application	
	var parentWindow = window;
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

	if (appEntry != null && appEntry.browser.iframe != null)
		parentWindow = appEntry.browser.iframe.contentWindow;

	windowID = parentWindow.open(_url, _windowName, "width=" + _width + ",height=" + _height +
		",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
		",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);

	if (navigate)
	{
		window.setTimeout("framework.applicationTabNavigate(framework.activeApp, framework.activeApp.indexUrl);", 500);
	}

	if (_returnID === false)
	{
		// return nothing
	}
	else
	{
		return windowID;
	}
}

egw_fw.prototype.egw_appWindow = function(_app)
{
	var app = framework.getApplicationByName(_app);
	var result = window;
	if (app != null && app.browser != null && app.browser.iframe != null)
	{
		result = app.browser.iframe.contentWindow;
	}
	return result;
}

egw_fw.prototype.egw_appWindowOpen = function(_app, _url)
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
}

egw_fw.prototype.egw_getAppName = function()
{
	return framework.activeApp.appName;
}



/**
 * egw_fw_content_browser class
 */

EGW_BROWSER_TYPE_NONE = 0;
EGW_BROWSER_TYPE_IFRAME = 1;
EGW_BROWSER_TYPE_DIV = 2;

/**
 * Creates a new content browser ui, _heightCallback must either be a function
 * or an egw_fw_class_callback object.
 */
function egw_fw_content_browser(_app, _heightCallback)
{
	//Create a div which contains both, the legacy iframe and the contentDiv
	this.baseDiv = document.createElement('div');
	this.type = EGW_BROWSER_TYPE_NONE;
	this.iframe = null;
	this.contentDiv = null;
	this.heightCallback = _heightCallback;
	this.app = _app;
	this.currentLocation = '';
}

egw_fw_content_browser.prototype.callResizeHandler = function()
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
}

/**
 * Resizes both, the contentDiv and the iframe to the size returned from the heightCallback
 */
egw_fw_content_browser.prototype.resize = function()
{
	var height = this.heightCallback.call() + 'px';

	//Set the height of the content div or the iframe
	if (this.contentDiv)
	{
		this.contentDiv.style.height = height;
	}
	if (this.iframe)
	{
		this.iframe.style.height = height;
	}
}

egw_fw_content_browser.prototype.setBrowserType = function(_type)
{
	//Only do anything if the browser type has changed
	if (_type != this.type)
	{
		//Destroy the iframe and/or the contentDiv
		$j(this.baseDiv).empty();
		this.iframe = null;
		this.contentDiv = null;
		this.ajaxLoaderDiv = null;
		
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
}

egw_fw_content_browser.prototype.browse = function(_url)
{
	var useIframe = true;

//	_url = _url + "&ajax=true";

	// Check whether the given url is a pseudo url which should be executed
	// by calling the ajax_exec function
	var matches = _url.match(/\/index.php\?menuaction=([A-Za-z0-9\.]*).*&ajax=true$/);
	if (matches) {
		useIframe = false;

		// Matches[1] contains the menuaction which should be executed - replace
		// the given url with the following line. This will be evaluated by the
		// jdots_framework ajax_exec function which will be called by the code
		// below as we set useIframe to false.
		_url = "index.php?menuaction=" + matches[1];
	}

	//Set the browser type
	if (useIframe)
	{
		this.setBrowserType(EGW_BROWSER_TYPE_IFRAME);

		//Postpone the actual "navigation" - gives some speedup with internet explorer
		//as it does no longer blocks the complete page until all frames have loaded.
		var self = this;
		window.setTimeout(function() {
			//Load the iframe content
			self.iframe.src = _url;
			
			//Set the "_legacy_iframe" flag to allow link handlers to easily determine
			//the type of the link source
			if (self.iframe && self.iframe.contentWindow) {
				self.iframe.contentWindow._legacy_iframe = true;

				// Focus the iframe of the current application
				if (self.app == framework.activeApp)
				{
					self.iframe.contentWindow.focus();
				}
			}
		}, 1);
	}
	else
	{
		this.setBrowserType(EGW_BROWSER_TYPE_DIV)
		this.currentLocation = _url;

		//Special treatement of "about:blank"
		if (_url == "about:blank")
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
			var req = new egw_json_request(
				this.app.getMenuaction('ajax_exec'),
				[_url], this.contentDiv);
			req.setAppObject(this.app);
			req.sendRequest(true, this.browse_callback, this);

			//The onloadfinish function gets called after all JS depencies have
			//been loaded
			req.onLoadFinish = this.browse_finished;
		}
	}
}

egw_fw_content_browser.prototype.browse_callback = function(_data)
{
	this.data = _data;
}

egw_fw_content_browser.prototype.browse_finished = function()
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
	$j(this.contentDiv).html(content.html);

	// Run the javascript code
	//console.log(content.js);
	$j(this.contentDiv).append(content.js);
}

egw_fw_content_browser.prototype.reload = function()
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
}

egw_fw_content_browser.prototype.blank = function()
{
	this.browse('about:blank', this.type == EGW_BROWSER_TYPE_IFRAME);
}

/**
 * Global funcitons
 */

window.egw_link_handler = function(_link, _app)
{
	//Determine where the link came from
	var link_source = EGW_LINK_SOURCE_FRAMEWORK;
	if (window.framework == 'undefined')
	{
		if (typeof window._legacy_iframe != 'undefined')
		{
			var link_source = EGW_LINK_SOURCE_LEGACY_IFRAME //1, iframe ==> legacy application
		}
		else
		{
			var link_source = EGW_LINK_SOURCE_POPUP; //2, popup
		}
	}

	//Default the application parameter to false
	if (typeof _app == 'undefined')
	{
		_app = false;
	}

	//Default the _useIframe parameter to true
	if (typeof _useIframe == 'undefined')
	{
		_useIframe = true;
	}

	var frmwrk = egw_getFramework();
	if (frmwrk != null)
	{
		frmwrk.linkHandler(_link, _app, link_source)
	}
	else
	{
		window.location = _link;
	}
}

/**
 * Refresh given application _targetapp display of entry _app _id, incl. outputting _msg
 * 
 * Current implementation ignores all refresh requests for now
 * 
 * @param string _msg message (already translated) to show, eg. 'Entry deleted'
 * @param string _app application name
 * @param string|int _id=null id of entry to refresh
 * @param string _type=null either 'edit', 'delete', 'add' or null
 * @param string _targetapp which app's window should be refreshed, default current
 */
window.egw_refresh = function(_msg, _app, _id, _type, _target)
{
	//alert("egw_refresh(\'"+_msg+"\',\'"+_app+"\',\'"+_id+"\',\'"+_type+"\')");
}
