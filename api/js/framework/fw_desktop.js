/**
 * EGroupware desktop framework
 *
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @author Andreas Stoeckel <as@stylite.de>
 * @copyright EGroupware GmbH 2014-2021
 * @description Create desktop framework
 */

/*egw:uses
	vendor.bower-asset.jquery.dist.jquery;
	framework.fw_base;
	framework.fw_browser;
	framework.fw_ui;
	framework.fw_classes;
	egw_inheritance.js;
*/

import "../../../vendor/bower-asset/jquery/dist/jquery.min.js";
import "../jquery/jquery.noconflict.js";
import './fw_base.js';
import './fw_browser.js';
import './fw_ui.js';
import './fw_classes.js';
import '../jsapi/egw_inheritance.js';
import "sortablejs/Sortable.min.js";
/**
 *
 * @param {DOMWindow} window
 */
(function(window)
{
	"use strict";

	/**
	 *
	 * @type @exp;fw_ui_sidemenu_entry@call;extend
	 */
	window.desktop_ui_sidemenu_entry = fw_ui_sidemenu_entry.extend(
	{
		/**
		 * Override fw_ui_sidemenu_entry class constructor
		 *
		 * @returns {undefined}
		 */
		init: function()
		{
			this._super.apply(this,arguments);
			let self = this;
			this.setBottomLine(this.parent.entries);

			this.elemDiv.classList.add('ui-sortable')
			Sortable.create(this.elemDiv,{
				onSort: function (evt) {
					self.parent.isDraged = true;
					self.parent.refreshSort();
				},
				direction: 'vertical'
			});
		},

		/**
		 * setBottomLine marks this element as the bottom element in the application list.
		 * This adds the egw_fw_ui_sidemenu_entry_content_bottom/egw_fw_ui_sidemenu_entry_header_bottom CSS classes
		 * which should care about adding an closing bottom line to the sidemenu. These classes are removed from
		 * all other entries in the side menu.
		 * @param {type} _entryList is a reference to the list which contains the sidemenu_entry entries.
		 */
	   setBottomLine: function(_entryList)
	   {
		   //If this is the last tab in the tab list, the bottom line must be closed
		   for (var i = 0; i < _entryList.length; i++)
		   {
			   jQuery(_entryList[i].contentDiv).removeClass("egw_fw_ui_sidemenu_entry_content_bottom");
			   jQuery(_entryList[i].headerDiv).removeClass("egw_fw_ui_sidemenu_entry_header_bottom");
		   }
		   jQuery(this.contentDiv).addClass("egw_fw_ui_sidemenu_entry_content_bottom");
		   jQuery(this.headerDiv).addClass("egw_fw_ui_sidemenu_entry_header_bottom");
	   }
	});

	/**
	 *
	 * @type @exp;fw_ui_sidemenu@call;extend
	 */
	window.desktop_ui_sidemenu = fw_ui_sidemenu.extend(
	{
		init: function(_baseDiv, _sortCallback)
		{
			this._super.apply(this,arguments);
			this.sortCallback = _sortCallback;
		},

		/**
		 * Called by the sidemenu elements whenever they were sorted. An array containing
		 * the sidemenu_entries ui-objects is generated and passed to the sort callback
		 */
		refreshSort: function()
		{
			//Step through all children of elemDiv and add all markers to the result array
			var resultArray = new Array();
			this._searchMarkers(resultArray, this.elemDiv.childNodes);

			//Call the sort callback with the array containing the sidemenu_entries
			this.sortCallback(resultArray);
		},

		/**
		 * Adds an entry to the sidemenu.
		 * @param {type} _name specifies the title of the new sidemenu entry
		 * @param {type} _icon specifies the icon displayed aside the title
		 * @param {type} _callback specifies the function which should be called when a callback is clicked
		 * @param {type} _tag extra data
		 * @param {type} _app application name
		 *
		 * @returns {desktop_ui_sidemenu_entry}
		 */
		addEntry: function(_name, _icon, _callback, _tag, _app)
		{
		   //Create a new sidemenu entry and add it to the list
		   var entry = new desktop_ui_sidemenu_entry(this, this.baseDiv, this.elemDiv, _name, _icon,
			   _callback, _tag, _app);
		   this.entries[this.entries.length] = entry;

		   return entry;
		}
	});

	/**
	 * jdots framework object defenition
	 * here we can add framework methods and also override fw_base methods if it is neccessary
	 * @type @exp;fw_base@call;extend
	 */
	window.fw_desktop = fw_base.extend({
		/**
		 * jdots framework constructor
		 *
		 * @param {string} _sidemenuId sidebar menu div id
		 * @param {string} _tabsId tab area div id
		 * @param {string} _splitterId splitter div id
		 * @param {string} _webserverUrl specifies the egroupware root url
		 * @param {function} _sideboxSizeCallback
		 * @param {int} _sideboxStartSize sidebox start size
		 * @param {int} _sideboxMinSize sidebox minimum size
		 */
		init:function (_sidemenuId, _tabsId, _webserverUrl, _sideboxSizeCallback, _splitterId, _sideboxStartSize, _sideboxMinSize)
		{
			// call fw_base constructor, in order to build basic DOM elements
			this._super.apply(this,arguments);

			this.splitterDiv = document.getElementById(_splitterId);
			if (this.sidemenuDiv && this.tabsDiv && this.splitterDiv)
			{
				//Wrap a scroll area handler around the applications
				this.scrollAreaUi = new egw_fw_ui_scrollarea(this.sidemenuDiv);

				// Create toggleSidebar menu
				this.toggleSidebarUi = new egw_fw_ui_toggleSidebar('#egw_fw_basecontainer', this._toggleSidebarCallback,this);

				//Create the sidemenu, the tabs area and the splitter
				this.sidemenuUi = new desktop_ui_sidemenu(this.scrollAreaUi.contentDiv,
					this.sortCallback);
				this.tabsUi = new egw_fw_ui_tabs(this.tabsDiv);
				this.splitterUi = new egw_fw_ui_splitter(this.splitterDiv,
					EGW_SPLITTER_VERTICAL, this.splitterResize,
					[
						{
							"size": _sideboxStartSize,
							"minsize": _sideboxMinSize,
							"maxsize": screen.availWidth - 50
						}
					], this);

				var egw_script = document.getElementById('egw_script_id');
				var apps = egw_script ? egw_script.getAttribute('data-navbar-apps') : null;
				this.loadApplications(JSON.parse(apps));
			}

			_sideboxSizeCallback(_sideboxStartSize);

			// warn user about using IE not compatibilities
			// we need to wait until common translations are loaded
			egw.langRequireApp(window, 'common', function()
			{
				if (navigator && navigator.userAgent.match(/Trident|msie/ig))
				{
					egw.message(egw.lang('Browser %1 %2 is not recommended. You may experience issues and not working features. Please use the latest version of Chrome, Firefox or Edge. Thank You!', 'IE',''), 'info', 'browser:ie:warning');
				}
			});

			// initiate darkmode
			let darkmode = egw.preference('darkmode', 'common');
			if (darkmode == '2')
			{
				let prefes_color_scheme = this._get_prefers_color_scheme();
				if (prefes_color_scheme)
				{

					window.matchMedia('(prefers-color-scheme: dark)')
						.addEventListener('change', event => {
							this.toggle_darkmode(document.getElementById('topmenu_info_darkmode'), event.matches?false:true);
						});
					this.toggle_darkmode(document.getElementById('topmenu_info_darkmode'), prefes_color_scheme == 'light');
				}
			}
			else if (egw.getSessionItem('api', 'darkmode') == '1')
			{
				this.toggle_darkmode(document.getElementById('topmenu_info_darkmode'), false);
			}
			else if(egw.getSessionItem('api', 'darkmode') == '0')
			{
				this.toggle_darkmode(document.getElementById('topmenu_info_darkmode'), true);
			}

		},

		/**
		 *
		 * @param {array} apps
		 */
		loadApplications: function (apps)
		{
			var restore = this._super.apply(this, arguments);

			//Generate an array with all tabs which shall be restored sorted in by
			//their active state

			//Fill in the sorted_restore array...
			var sorted_restore = [];
			for (this.appName in restore)
			sorted_restore[sorted_restore.length] = restore[this.appName];

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
					sorted_restore[i].position, sorted_restore[i]['status']);

			//Set the current state of the tabs and activate TabChangeNotification.
			this.serializedTabState = egw.jsonEncode(this.assembleTabList());
			this.notifyTabChangeEnabled = true;

			this.scrollAreaUi.update();
			// Disable loader, if present
			jQuery('#egw_fw_loading').hide();

		},

		/**
		 *
		 * @param {type} _app
		 * @returns {undefined}
		 */
		setActiveApp: function(_app)
		{
			var result = this._super.apply(this, arguments);
			this.notifyAppTab(_app.appName , 0);
			if (_app == _app.parentFw.activeApp)
			{
				//Set the sidebox width if a application specific sidebox width is set
				// do not trigger resize if the sidebar is already in toggle on mode and
				// the next set state is the same
				if (_app.sideboxWidth !== false &&  egw.preference('toggleSidebar',_app.internalName) == 'off')
				{
					this.sideboxSizeCallback(_app.sideboxWidth);
					this.splitterUi.constraints[0].size = _app.sideboxWidth;
				}
				_app.parentFw.scrollAreaUi.update();
				_app.parentFw.scrollAreaUi.setScrollPos(0);
			}
			//Resize the scroll area...
			this.scrollAreaUi.update();

			//...and scroll to the top
			this.scrollAreaUi.setScrollPos(0);

			// Handles toggleSidebar initialization
			if (typeof framework != 'undefined')
			{
				framework.getToggleSidebarState();
				framework.activeApp.browser.callResizeHandler();
			}

			return result;
		},

		/**
		 * Function called whenever the sidemenu entries are sorted
		 * @param {type} _entriesArray
		 */
		sortCallback: function(_entriesArray)
		{
			//Create an array with the names of the applications in their sort order
			var name_array = [];
			for (var i = 0; i < _entriesArray.length; i++)
			{
				name_array.push(_entriesArray[i].tag.appName);
			}

			//Send the sort order to the server via ajax
			var req = egw.jsonq('EGroupware\\Api\\Framework\\Ajax::ajax_appsort', [name_array]);
		},

		/**
		 * Splitter resize callback
		 * @param {type} _width
		 * @param {string} _toggleMode if mode is "toggle" then resize happens without changing splitter preference
		 * @returns {undefined}
		 */
		splitterResize: function(_width, _toggleMode)
		{
			if (this.tag.activeApp)
			{

				if (_toggleMode !== "toggle")
				{
					if (!framework.isAnInternalApp(this.tag.activeApp)) egw.set_preference(this.tag.activeApp.internalName, 'jdotssideboxwidth', _width);

					//If there are no global application width values, set the sidebox width of
					//the application every time the splitter is resized
					if (this.tag.activeApp.sideboxWidth !== false)
					{
						this.tag.activeApp.sideboxWidth = _width;
					}
				}
			}
			this.tag.sideboxSizeCallback(_width);

			// Notify app about change
			if(this.tag.activeApp && this.tag.activeApp.browser != null)
			{
				this.tag.activeApp.browser.callResizeHandler();
			}
		},

		/**
		 *
		 */
		resizeHandler: function()
		{
			// Tabs overflow needs to be checked again
			this.checkTabOverflow();
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
		},
		/**
		 * Sets the sidebox data of an application
		 * @param {object} _app the application whose sidebox content should be set.
		 * @param {object} _data an array/object containing the data of the sidebox content
		 * @param {string} _md5 an md5 hash of the sidebox menu content: Only if this hash differs between two setSidebox calles, the sidebox menu will be updated.
		 */
		setSidebox: function(_app, _data, _md5)
		{
			this._super.apply(this,arguments);

			if (typeof _app == 'string') _app = this.getApplicationByName(_app);
			//Set the sidebox width if a application specific sidebox width is set
			if (_app && _app == _app.parentFw.activeApp && _app.sideboxWidth !== false )
			{
				this.splitterUi.constraints[0].size = _app.sideboxWidth;
			}
			this.getToggleSidebarState();
		},

		/**
		 *
		 * @param {app object} _app
		 * @param {int} _pos
		 * Checks whether the application already owns a tab and creates one if it doesn't exist
		 */
		createApplicationTab: function(_app, _pos)
		{
			this._super.apply(this, arguments);
		},

		/**
		 * Runs after et2 is loaded
		 *
		 */
		et2_loadingFinished: function() {
			this.checkTabOverflow();
			var $logout = jQuery('#topmenu_logout');
			var self = this;
			if (!$logout.hasClass('onLogout'))
			{
				$logout.on('click', function(e){
					e.preventDefault();
					egw.onLogout_timer().then(() => {
						self.callOnLogout(e);
						window.framework.redirect(this.href);
					});
				});
				$logout.addClass('onLogout');
			}
		},

		/**
		 * Check to see if the tab header will overflow and want to wrap.
		 * Deal with it by setting some smaller widths on the tabs.
		 */
		checkTabOverflow: function()
		{
			var width = 0;
			var outer_width = jQuery(this.tabsUi.contHeaderDiv).width();
			var spans = jQuery(this.tabsUi.contHeaderDiv).children('span');
			spans.css('max-width','');
			spans.each(function() { width += jQuery(this).outerWidth(true);});
			if(width > outer_width)
			{
				var max_width = Math.floor(outer_width / this.tabsUi.contHeaderDiv.childElementCount) -
					(spans.outerWidth(true) - spans.width());
				spans.css('max-width',max_width + 'px');
			}
		},

		/**
		 * @param {function} _opened
		 * Sends sidemenu entry category open/close information to the server using an AJAX request
		 */
		categoryOpenCloseCallback: function(_opened)
		{
			if (!framework.isAnInternalApp(this.tag)) egw.set_preference(this.tag.internalName, 'jdots_sidebox_'+this.catName, _opened);
		},

		categoryAnimationCallback: function()
		{
			this.tag.parentFw.scrollAreaUi.update();
		},

		/**
		 * toggleSidebar callback function, handles preference and resize
		 * @param {string} _state state can be on/off
		 */
		_toggleSidebarCallback: function (_state)
		{
			var splitterWidth = egw.preference('jdotssideboxwidth',this.activeApp.internalName) || this.activeApp.sideboxWidth;
			if (_state === "on")
			{
				this.splitterUi.resizeCallback(70,'toggle');
				if (!framework.isAnInternalApp(this.activeApp)) egw.set_preference(this.activeApp.internalName, 'toggleSidebar', 'on');
			}
			else
			{
				this.splitterUi.resizeCallback(splitterWidth);
				if (!framework.isAnInternalApp(this.activeApp)) egw.set_preference(this.activeApp.internalName, 'toggleSidebar', 'off');
			}
		},

		/**
		 * function to get the stored toggleSidebar state and set the sidebar accordingly
		 */
		getToggleSidebarState: function()
		{
			var toggleSidebar = egw.preference('toggleSidebar',this.activeApp.internalName);
			this.toggleSidebarUi.set_toggle(toggleSidebar?toggleSidebar:"off", this._toggleSidebarCallback, this);
		},

		toggle_avatar_menu: function ()
		{
			var $menu = jQuery('#egw_fw_topmenu');
			var $body = jQuery('body');
			if (!$menu.is(":visible"))
			{
				$body.on('click', function(e){
					if (e.target.id != 'topmenu_info_user_avatar' && jQuery(e.target).parents('#topmenu_info_user_avatar').length < 1
					&& e.target.nodeName && e.target.nodeName != 'ET2-SELECT')
					{
						jQuery(this).off(e);
						$menu.toggle();
					}
				});
			}
			else
			{
				$body.off('click');
			}
			$menu.toggle();
		},

		callOnLogout: function(e) {
			var apps = Object.keys(framework.applications);
			for(var i in apps)
			{
				if (app[apps[i]] && typeof app[apps[i]].onLogout === "function")
				{
					app[apps[i]].onLogout.call(e);
				}
			}
		},

		/**
		 * Notify tab
		 *
		 * @param {string} _appname
		 * @param {int} _value to set as notification, 0 will reset notification
		 */
		notifyAppTab: function(_appname, _value)
		{
			var tab = this.tabsUi.getTab(_appname);
			// do not set tab's notification if it's the active tab
			if (tab && (this.activeApp.appName != _appname || _value == 0))
			{
				this.tabsUi.getTab(_appname).setNotification(_value);
			}
		},

		/**
		 * Get color scheme
		 * @return {string|null} returns active color scheme mode or null in case browser not supporting it
		 * @private
		 */
		_get_prefers_color_scheme: function ()
		{
			if (window.matchMedia('(prefers-color-scheme: light)').matches)
			{
				return 'light';
			}
			if (window.matchMedia('(prefers-color-scheme: dark)').matches)
			{
				return 'dark'
			}
			return null;
		},

		/**
		 *
		 * @param node
		 */
		toggle_darkmode: function(node, _state)
		{
			let state = (typeof _state != 'undefined') ? _state : node.firstElementChild.classList.contains('darkmode_on');
			this._setDarkMode(state?'0':'1');
			if (state == 1)
			{
				node.firstElementChild.classList.remove('darkmode_on');
				node.firstElementChild.title = egw.lang('dark mode');
			}
			else
			{
				node.firstElementChild.classList.add('darkmode_on');
				node.firstElementChild.title = egw.lang('light mode');
			}
		}
	});
})(window);