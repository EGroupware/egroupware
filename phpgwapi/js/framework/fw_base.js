/**
 * eGroupware Framework base object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
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
	 */
	init: function (){
		
	},
	
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
});
