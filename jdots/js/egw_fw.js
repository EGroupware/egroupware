/**
 * eGroupware JavaScript Framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */


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
function egw_fw(_sidemenuId, _tabsId, _webserverUrl)
{
	/* Get the base div */
	this.sidemenuDiv = document.getElementById(_sidemenuId);
	this.tabsDiv = document.getElementById(_tabsId);
	this.webserverUrl = _webserverUrl;
	window.egw_webserverUrl = _webserverUrl;

	this.sidemenuUi = null;
	this.tabsUi = null;

	this.categoryOpenCache = new Object();

	this.applications = new Object();
	this.activeApp = null;

	if (this.sidemenuDiv && this.tabsDiv)
	{
		//Create the sidemenu and the tabs area
		this.sidemenuUi = new egw_fw_ui_sidemenu(this.sidemenuDiv);
		this.tabsUi = new egw_fw_ui_tabs(this.tabsDiv);

		this.loadApplications("home.jdots_framework.ajax_navbar_apps");
	}

	//Register the resize handler
	$(window).resize(function(){window.framework.resizeHandler()});

	//Register the global alert handler
	window.egw_alertHandler = this.alertHandler;

	//Register the key press handler
	//$(document).keypress(this.keyPressHandler);

	//Override the old egw_openWindowCentered2
	window.egw_openWindowCentered2 = this.egw_openWindowCentered2;

	//Override the app_window function
	window.egw_appWindow = this.egw_appWindow;
}

egw_fw.prototype.alertHandler = function(_message, _details)
{
	alert('Error:\n ' + _message + '\n\nDetails:\n ' + _details);
}

/**
 * Function called whenever F1 is pressed inside the framework
 * @returns boolean true if the call manual function could be called, false if the manual is not available
 */
egw_fw.prototype.f1Handler = function()
{
	if (typeof window.callManual != 'undefined')
	{
		window.callManual();
		return true;
	}
	return false;
}

/**
 * Function called whenever a key is pressed
 * @param object event describes the key press event
 */
egw_fw.prototype.keyPressHandler = function(event)
{
	switch (event.keyCode)
	{
		case 112: //F1
		{
			event.preventDefault();
			framework.f1Handler();
		}
	}
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
		tabsUi.removeTab(this);
		app.tab = null;
		app.iframe = null;

		//Activate the new application in the sidebar menu
		app.parentFw.sidemenuUi.open(tabsUi.activeTab.tag.sidemenuEntry);
	}

	tabsUi.setCloseable(tabsUi.tabs.length > 1);

	/* As a new tab might remove a row from the tab header, we have to resize all iframes */
	this.tag.parentFw.resizeHandler();
}

egw_fw.prototype.resizeHandler = function()
{
	for (var app in this.applications)
	{
		if (this.applications[app].iframe != null)
		{
			this.applications[app].iframe.style.height = this.getIFrameHeight() + 'px';
		}
	}
}

egw_fw.prototype.getIFrameHeight = function()
{
	var height = $(window).height() - (this.tabsUi.contHeaderDiv.offsetTop +
		this.tabsUi.contHeaderDiv.offsetHeight + 30); /* 30 is the height of the footer */
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
	this.parent.showTab(this);
	this.tag.parentFw.sidemenuUi.open(this.tag.sidemenuEntry);
	document.title = this.tag.website_title ? this.tag.website_title : this.tag.appName;

	//Set this application as the active application
	this.tag.parentFw.activeApp = this.tag;
}

/**
 * applicationClickCallback is used internally by egw_fw in order to handle clicks on
 * an application in the sidebox menu.
 *
 * @param egw_fw_ui_tab _sender specifies the tab ui object, the user has clicked
 */
egw_fw.prototype.applicationClickCallback = function(_sender)
{
	this.tag.parentFw.applicationTabNavigate(this.tag, this.tag.execName);
}

/**
 * navigate to tab of an applications (opening the tab if not yet open)
 * 
 * @param egw_fw_class_application _app
 * @param string _url optional url, default index page of app
 */
egw_fw.prototype.applicationTabNavigate = function(_app, _url)
{
	//Create the tab if it isn't already there
	if ((_app.iframe == null) || (_app.tab == null))
	{
		_app.tab = this.tabsUi.addTab(_app.icon, this.tabClickCallback, this.tabCloseClickCallback,
			_app);
		_app.tab.setTitle(_app.displayName);

		_app.iframe = document.createElement('iframe');
		_app.iframe.style.width = "100%";
		_app.iframe.style.borderWidth = 0;
		_app.iframe.style.height = this.getIFrameHeight() + 'px';
		_app.iframe.frameBorder = 0;
		_app.tab.setContent(_app.iframe);
		
		this.tabsUi.setCloseable(this.tabsUi.tabs.length > 1);

		/* As a new tab might add a new row in the tab header, we have to resize all iframes */
		this.resizeHandler();
	}

	//Set the iframe location
	_app.iframe.src = typeof(_url) == "undefined" ? _app.execName : _url;

	//Set this application as the active application
	_app.parentFw.activeApp = _app;

	//Show the tab
	this.tabsUi.showTab(_app.tab);

	if (_app.sidemenuEntry != null)
	{
		//Open the sidemenu entry content
		if (_app.hasSideboxMenuContent)
		{
			_app.sidemenuEntry.parent.open(_app.sidemenuEntry);
		}
	}
	else
	{
		_app.parentFw.sidemenuUi.open(null);
	}
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

	//Iterate through the application array returned
	for (var i = 0; i < apps.length; i++)
	{
		var app = apps[i];

		appData = new egw_fw_class_application(this, 
			app.name, app.title, app.icon, app.url);

		//Create a sidebox menu entry for each application
		if (!app.noNavbar)
		{
			appData.sidemenuEntry = this.sidemenuUi.addEntry(
				appData.displayName, appData.icon,
				this.applicationClickCallback, appData);
		}

		//If this entry is the default entry, show it using the click callback
		if (app.isDefault && (app.isDefault === true))
		{
			defaultApp = appData;
		}

		this.applications[appData.appName] = appData;
	}

	// check if a menuaction or app is specified in the url --> display that
	var matches = location.search.match(/menuaction=([a-z0-9_-]+)\./i);
	var _app,_url;
	if (matches && (_app = this.getApplicationByName(matches[1])) ||
		(matches = location.href.match(/\/([^\/]+)\/[^\/]+\.php/i)) &&
			(_app = this.getApplicationByName(matches[1])))
	{
		_url = window.location.href.replace(/&?cd=yes/,'');
		this.applicationTabNavigate(_app,_url);
	}
	// else display the default application
	else if (defaultApp)
	{
		this.applicationTabNavigate(defaultApp);
	}
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
	var req = new egw_json_request(_menuaction)
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
		return this.applications[_name]
	}

	return null;
}

/**
 * Seperates all script tags from the given html code and returns the seperately
 * @param object _html object that the html code from which the script should be seperated. The html code has to be stored in _html.html, the result js will be written to _html.js
 */

egw_fw.prototype.seperateJavaScript = function(_html)
{
	var html = _html.html;
	
	var in_pos = html.search(/<script/im);
	var out_pos = html.search(/<\/script>/im);
	while (in_pos > -1 && out_pos > -1)
	{
		/*Seperate the whole <script...</script> tag */
		var js_str = html.substring(in_pos, out_pos+9);

		/*Remove the initial tag */
		/*js_str = js_str.substring(js_str.search(/>/) + 1);*/
		_html.js += js_str;

		
		html = html.substring(0, in_pos - 1) + html.substring(out_pos + 9);

		var in_pos = html.search(/<script/im);
		var out_pos = html.search(/<\/script>/im);
	}

	_html.html = html;
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

	/* Store the state of the category lokaly */	
	this.tag.parentFw.categoryOpenCache[this.tag.appName + '#' + this.catName] = _opened;
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

					this.seperateJavaScript(html);
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
						catContent += '<a href="' + _data[i].entries[j].item_link + 
							(_data[i].entries[j].target ? '" target="'+_data[i].entries[j].target : '') +
							'">' + html.html + '</a>';
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
						_data[i].title, catContent, this.categoryOpenCloseCallback, _app);

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

			_app.sidemenuEntry.setContent(contDiv);
			_app.sidebox_md5 = _md5;

			$(contDiv).append(contJS);
		}

		_app.hasSideboxMenuContent = true;
		_app.sidemenuEntry.parent.open(_app.sidemenuEntry);
	}
}

/**
 * Sets the website title of an application
 * @param object _app the application whose title should be set.
 * @param string _title title to set
 */
egw_fw.prototype.setWebsiteTitle = function(_app,_title)
{
	document.title = _title;
	if (_app) _app.website_title = _title;
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
	
	if (this.activeApp)
	{
		this.activeApp.iframe.contentDocument.location.reload();
	}
}

egw_fw.prototype.linkHandler = function(_link, _app)
{
	var app = this.getApplicationByName(_app);
	if (app != null)
	{
		this.applicationTabNavigate(app, _link);
	}
	else
	{
		egw_alertHandler('Application "' + _app + '" not found.', 'The application "' + _app + '" the link "' + _link + '" points to is not registered.');
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
		if (appEntry && appEntry.iframe == null)
		{
			navigate = true;
			framework.applicationTabNavigate(appEntry, 'about:blank');
		}
	}
	else
	{
		var appEntry = framework.activeApp;
	}

	if (appEntry != null && appEntry.iframe != null)
		parentWindow = appEntry.iframe.contentWindow;

	windowID = parentWindow.open(_url, _windowName, "width=" + _width + ",height=" + _height +
		",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
		",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);

	if (navigate)
	{
		window.setTimeout("framework.applicationTabNavigate(framework.activeApp, framework.activeApp.execName);", 500);
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
	var result = null;
	if (app != null && app.iframe != null)
	{
		result = app.iframe.contentWindow;
	}
	return result;
}

window.egw_link_handler = function(_link, _app)
{
	/*var frmwrk = getFramwork();
	if (frmwrk != null)
	{
		frmwrk.linkHandler(_link, _app)
	}
	else
	{
		window.location = _link;
	}*/

	if (typeof window.framework != "undefined")
	{
		window.framework.linkHandler(_link, _app);
	}
	else if (typeof window.parent.framework != "undefined")
	{
		window.parent.framework.linkHandler(_link, _app);
	}
	else
	{
		window.location = _link;
	}
}

window.getFramework = function()
{
	if (typeof window.framework != 'undefined')
	{
		return framework;
	}
	else if (typeof window.parent.getFramework != "undefined")
	{
		return window.parent.getFramework();
	}
	else
	{
		return null;
	}
}

