/**
 * eGroupware mobile framework object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @copyright Stylite AG 2014
 * @description Create mobile framework
 */


/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	framework.fw_base;
	framework.fw_browser;
	framework.fw_ui;
	framework.fw_classes;
	egw_inheritance.js;
*/

import '../../api/js/framework/fw_base.js';
import '../../api/js/framework/fw_browser.js';
import '../../api/js/framework/fw_ui.js';
import '../../api/js/framework/fw_classes.js';
import '../../api/js/jsapi/egw_inheritance.js';
import {tapAndSwipe} from "../../api/js/tapandswipe";
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
	var mobile_ui_sidemenu_entry = fw_ui_sidemenu_entry.extend({

		/**
		 * Override fw_ui_sidemenu_entry class constructor
		 *
		 * @returns {undefined}
		 */
		init: function()
		{
			this._super.apply(this,arguments);
			jQuery(this.elemDiv).addClass('egw_fw_ui_sidemenu_entry_apps');
		},

		open: function()
		{
			this._super.apply(this,arguments);
			jQuery('.egw_fw_ui_sidemenu_listitem', this.contentDiv).click(function(){framework.toggleMenu('on');});
			framework.toggleMenu('on');
		}
	});

	/**
	 *
	 * @type @exp;fw_ui_sidemenu@call;extend
	 */
	var mobile_ui_sidemenu = fw_ui_sidemenu.extend({

		/**
		 *
		 * @returns {undefined}
		 */
		init: function()
		{
			this._super.apply(this,arguments);
			var $baseDiv = jQuery(this.baseDiv);
			let swipe = new tapAndSwipe(this.baseDiv, {
				swipe: function(e, direction, distance)
				{
					switch (direction)
					{
						case "up":
						case "down":
							if ($baseDiv.css('overflow') == 'hidden')
								$baseDiv.css('overflow-y','auto');
							break;
						case "left":
							if (distance >= 200)
							{
								framework.toggleMenu();
							}
							break;
						case "right":
							if (distance >= 200)
							{
								framework.toggleMenu();
							}
					}
				},
				allScrolling: 'vertical'
			});
		},
		/**
		 * Adds an entry to the sidemenu.
		 * @param {type} _name specifies the title of the new sidemenu entry
		 * @param {type} _icon specifies the icon displayed aside the title
		 * @param {type} _callback specifies the function which should be called when a callback is clicked
		 * @param {type} _tag extra data
		 * @param {type} _app application name
		 *
		 * @returns {mobile_ui_sidemenu_entry}
		 */
		addEntry: function(_name, _icon, _callback, _tag, _app)
		{
		   //Create a new sidemenu entry and add it to the list
		   var entry = new mobile_ui_sidemenu_entry(this, this.baseDiv, this.elemDiv, _name, _icon,
			   _callback, _tag, _app);
		   this.entries[this.entries.length] = entry;

		   return entry;
		},

		/**
		 * Hide sidebar menu and top toolbar
		 */
		disable: function ()
		{
			jQuery(this.baseDiv).hide();
			jQuery('#egw_fw_top_toolbar').hide();
		},

		/**
		 * * Show sidebar menu and top toolbar
		 */
		enable: function ()
		{
			jQuery(this.baseDiv).show();
			jQuery('#egw_fw_top_toolbar').show();
		}
	});

	/**
	 * popup frame constructor
	 */
	var popupFrame = Class.extend({

		/**
		 * Constructor of popupFrame
		 * @param {type} _wnd
		 */
		init:function(_wnd)
		{
			var self = this;
			this.$container = jQuery(document.createElement('div')).addClass('egw_fw_mobile_popup_container');
			this.$iFrame = jQuery(document.createElement('iframe'))
					.addClass('egw_fw_mobile_popupFrame')
					.appendTo(this.$container);
			this.$container.appendTo('body');
			// Create close button for popups
			var $closeBtn = jQuery(document.createElement('span'))
				.addClass('egw_fw_mobile_popup_close')
				.click(function (){self.close(framework.popup_idx(self.$iFrame[0].contentWindow));});
			this.$container.prepend($closeBtn);
			egw.loading_prompt('popup', true,'',this.$iFrame,'horizental');
			this.windowOpener = _wnd;
		},

		/**
		 * Opens the iframe window as modal popup
		 *
		 * @param {type} _url
		 * @param {type} _width
		 * @param {type} _height
		 * @param {type} _posX
		 * @param {type} _posY
		 * @returns {undefined}
		 */
		open: function(_url,_width,_height,_posX,_posY)
		{
			//Open iframe with the url
			this.$iFrame.attr('src',_url);

			var self = this;
			//After the popup is fully loaded
			this.$iFrame.on('onpopupload', function (){
				var popupWindow = this.contentWindow;
				var $appHeader = jQuery(popupWindow.document).find('#divAppboxHeader');
				$appHeader.addClass('egw_fw_mobile_popup_appHeader');
				self.$container.find('.egw_fw_mobile_popup_close').addClass('loaded');
				//Remove the loading class
				egw.loading_prompt('popup', false);
				self.$iFrame.css({visibility:'visible'});

				// Auto scrollup when select input or select
				jQuery(popupWindow).on('resize', function(){
				   if(popupWindow.document.activeElement.tagName == "INPUT" || popupWindow.document.activeElement.tagName == "SELECT"){
					  popupWindow.setTimeout(function(){
						 popupWindow.document.activeElement.scrollIntoViewIfNeeded(false);
					  },0);
				   }
				});

				// An iframe scrolling fix for iOS Safari
				if (framework.getUserAgent() === 'iOS') {
					window.setTimeout(function(){
						jQuery(self.$iFrame).height(popupWindow.document.body.scrollHeight);
						// scrolling node
						var node = jQuery(popupWindow.document.body);
						// start point Y, X
						var startY, startX = 0;

						// kill delays on transitions
						// and set the start value for transition
						node.css ({
							transition: 'all 0s',
							transform:'translateX(0px) translateY(0px)',
						});

						node.on({
							touchmove: function (e){
								var $w = jQuery(window);
								// current touch y position
								var currentY = e.originalEvent.touches ? e.originalEvent.touches[0].screenY : e.originalEvent.screenY;
								// current touch x position
								var currentX = e.originalEvent.touches ? e.originalEvent.touches[0].screenX : e.originalEvent.screenX;
								// check if we are the top
								var isAtTop = (startY <= currentY && $w.scrollTop() <= 0);
								// check if we are at the bottom
								var isAtBottom = (startY >= currentY && node[0].scrollHeight - $w.scrollTop() === node.height());
								// check if it's left or right touch move
								var isLeftOrRight = (Math.abs(startX - currentX) > Math.abs(startY - currentY));

								if (isAtTop || isAtBottom || isLeftOrRight) e.originalEvent.preventDefault();
							},
							touchstart: function (e){
								startY = e.originalEvent.touches ? e.originalEvent.touches[0].screenY : e.originalEvent.screenY;
								startX = e.originalEvent.touches ? e.originalEvent.touches[0].screenX : e.originalEvent.screenX;
							}
						});
					}, 500);
				}
			});


			this.$iFrame.on('load',
				//In this function we can override all popup window objects
				function ()
				{
					var popupWindow = this.contentWindow;
					var $appHeader = jQuery(popupWindow.document).find('#divAppboxHeader');
					var $et2_container = jQuery(popupWindow.document).find('.et2_container');
					jQuery(popupWindow.document.body).css({'overflow-y':'auto'});

					var darkmode = egw.getSessionItem('api', 'darkmode');
					if (darkmode == '0' || darkmode == '1')
					{
						// set darkmode for iframe popup content
						jQuery(popupWindow.document.body.parentElement).attr('data-darkmode', darkmode == 0?'':'1');
					}
					if ($appHeader.length > 0)
					{
						// Extend the dialog to 100% width
						$et2_container.css({width:'100%', height:'100%'});
						if (framework.getUserAgent() === 'iOS' && !framework.isNotFullScreen()) $appHeader.addClass('egw_fw_mobile_iOS_popup_appHeader');
					}
					// If the popup is not an et2_popup
					if ($et2_container.length == 0)
					{
						egw.loading_prompt('popup', false);
						self.$iFrame.css({visibility:'visible'});
					}

					// Set the popup opener
					popupWindow.opener = self.windowOpener;
				}
			);
			this.$container.show();

		},
		/**
		 * Close popup
		 * @param {type} _idx remove the given popup index from the popups array
		 * @returns {undefined}
		 */
		close: function (_idx)
		{
			this.$container.detach();
			//Remove the closed popup from popups array
			window.framework.popups.splice(_idx,1);
		},

		/**
		 * Resize the iFrame popup
		 * @param {type} _width actuall width
		 * @param {type} _height actuall height
		 */
		resize: function (_width,_height)
		{
			//As we can not calculate the delta value, add 30 px as delta
			this.$iFrame.css({width:_width+30,	height:_height+30});
		}
	});

	/**
	 * mobile framework object defenition
	 * here we can add framework methods and also override fw_base methods if it is neccessary
	 * @type @exp;fw_base@call;extend
	 */
	var fw_mobile = fw_base.extend({

		// List of applications available on mobile devices
		DEFAULT_MOBILE_APP : ['calendar','infolog','timesheet','resources','addressbook','projectmanager','tracker','mail','filemanager'],

		/**
		 * Mobile framework constructor
		 *
		 * @param {string} _sidemenuId sidebar menu div id
		 * @param {string} _tabsId tab area div id
		 * @param {string} _webserverUrl specifies the egroupware root url
		 * @param {function} _sideboxSizeCallback
		 * @param {int} _sideboxStartSize sidebox start size
		 * @param {string} _baseContainer
		 * @param {string} _mobileMenu
		 */
		init:function (_sidemenuId, _tabsId, _webserverUrl, _sideboxSizeCallback, _sideboxStartSize, _baseContainer, _mobileMenu)
		{
			// call fw_base constructor, in order to build basic DOM elements
			this._super.apply(this,arguments);
			var self = this;

			// Stores opened popups object
			this.popups = [];

			// The size that sidebox should be opened with
			this.sideboxSize = _sideboxStartSize;

			this.sideboxCollapsedSize = egwIsMobile()?1:72;
			//Bind handler to orientation change
			jQuery(window).on("orientationchange",function(){
				self.orientation();
			});

			this.baseContainer = document.getElementById(_baseContainer);
			this.mobileMenu = document.getElementById(_mobileMenu);

			//Bind the click handler to menu
			jQuery(this.mobileMenu).on({
				click:function()
				{
					self.toggleMenu();
				}
			});

			if (this.sidemenuDiv && this.tabsDiv)
			{
				//Create the sidemenu
				this.sidemenuUi = new mobile_ui_sidemenu(this.sidemenuDiv);
				this.tabsUi = new egw_fw_ui_tabs(this.tabsDiv);

				var egw_script = document.getElementById('egw_script_id');
				var apps = egw_script ? JSON.parse(egw_script.getAttribute('data-navbar-apps')) : null;

				// fw_mobile_app_list should only be considered for mobile dvices
				// therefore, compact theme still would show all available apps.
				if (egwIsMobile())
				{
					var mobile_app_list =  egw.config('fw_mobile_app_list') || this.DEFAULT_MOBILE_APP;

					// Check if the given app is on mobile_app_list
					var is_default_app = function(_app){
						for (var j=0;j< mobile_app_list.length;j++ )
						{
							if (_app == mobile_app_list[j]) return true;
						}
						return false;
					};

					var default_apps = [];
					for (var i=0;i <= apps.length;i++)
					{
						if (apps[i] && is_default_app(apps[i]['name'])) default_apps.push(apps[i]);
					}

					apps = default_apps;
				}

				this.loadApplications(apps);
			}

			this.sideboxSizeCallback(_sideboxStartSize);

			// Check if user runs the app in full screen or not,
			// then prompt user base on the mode, and if the user
			// discards the message once then do not show it again
			var fullScreen = this.isNotFullScreen();
			if (fullScreen && this.getUserAgent() !='iOS') egw.message(fullScreen,'info', 'etemplate:fw_mobile_fullscreen');
		},

		/**
		 *
		 * @returns {undefined}
		 */
		setSidebox:function()
		{
			this._super.apply(this,arguments);
			this.setSidebarState(this.activeApp.preferences.toggleMenu);
			var $avatar = jQuery('#topmenu_info_user_avatar');
			var $sidebar = jQuery('#egw_fw_sidebar');
			$sidebar.removeClass('avatarSubmenu');
			this.updateAppsToggle();
			// Open edit contact on click
			$avatar.off().on('click',function(){
				$sidebar.toggleClass('avatarSubmenu',!$sidebar.hasClass('avatarSubmenu'));
			});
			jQuery('#topmenu_info_darkmode').click(function(){window.framework.toggle_darkmode(this);});
		},

		/**
		 * Check if the device is in landscape orientation
		 *
		 * @returns {boolean} returns true if the device orientation is on landscape otherwise return false(protrait)
		 */
		isLandscape: function ()
		{
			//if there's no window.orientation then the default is landscape
			var orient = true;
			if (typeof window.orientation != 'undefined')
			{
				orient = window.orientation & 2?true:false;
			}
			return orient;
		},


		/**
		 * Orientation on change method
		 */
		orientation: function ()
		{
			var $body = jQuery('body');
			if (!this.isLandscape()){
				this.toggleMenu('on');
				$body.removeClass('landscape').addClass('portrait');
			}
			else
			{

				$body.removeClass('portrait').addClass('landscape');
			}

		},

		/**
		 * Toggle sidebar menu
		 * @param {string} _state
		 */
		toggleMenu: function (_state)
		{
			var state = _state || this.getToggleMenuState();
			var collapseSize = this.sideboxCollapsedSize;
			var expandSize = this.sideboxSize;
			var $toggleMenu = jQuery(this.baseContainer);
			var self = this;
			if (state === 'on')
			{
				jQuery('.egw_fw_sidebar_dropMask').remove();
				$toggleMenu.addClass('sidebar-toggle egw_fw_sidebar_toggleOn');
				this.toggleMenuResizeHandler(collapseSize);
				this.setToggleMenuState('off');
			}
			else
			{
				$toggleMenu.removeClass('sidebar-toggle egw_fw_sidebar_toggleOn');
				this.toggleMenuResizeHandler(expandSize);
				this.setToggleMenuState('on');
				if (screen.width<700)
				{
					jQuery(document.createElement('div'))
							.addClass('egw_fw_sidebar_dropMask')
							.click(function(){self.toggleMenu('on');})
							.css({position:'absolute',top:0,left:0,bottom:0,height:'100%',width:'100%'})
							.appendTo('#egw_fw_main');
				}
			}

			//Audio effect for toggleMenu
			var audio = jQuery('#egw_fw_menuAudioTag');
			if (egw.preference('audio_effect','common') == '1') {
				try {
				  audio[0].play();
				}
				catch(err) {
				  console.log(err);
				}
			}
		},

		/**
		 * Gets the active app toggleMenu state value
		 *
		 * @returns {string} returns state value off | on
		 */
		getToggleMenuState: function ()
		{
			var $toggleMenu = jQuery(this.baseContainer);
			var state = '';
			if (this.activeApp && typeof this.activeApp.preferences.toggleMenu!='undefined')
			{
				state = this.activeApp.preferences.toggleMenu;
			}
			else
			{
				state = $toggleMenu.hasClass('sidebar-toggle')?'off':'on';
			}
			return state;
		},

		/**
		 * Sets toggle menu state value
		 * @param {string} _state toggle state value, either off|on
		 */
		setToggleMenuState: function (_state)
		{
			if (_state === 'on' || _state === 'off')
			{
				this.activeApp.preferences['toggleMenu'] = _state;
				if ((!framework.isAnInternalApp(this.activeApp))) egw.set_preference(this.activeApp.appName,'egw_fw_mobile',this.activeApp.preferences);
			}
			else
			{
				egw().debug("error","The toggle menu value must be either on | off");
			}
		},
		/**
		 * set sidebar state
		 * @param {type} _state
		 * @returns {undefined}
		 */
		setSidebarState: function(_state)
		{
			var $toggleMenu = jQuery(this.baseContainer);
			if (_state === 'off')
			{
				$toggleMenu.addClass('sidebar-toggle');
				this.toggleMenuResizeHandler(this.sideboxCollapsedSize);
			}
			else
			{
				$toggleMenu.removeClass('sidebar-toggle');
				this.toggleMenuResizeHandler(this.sideboxSize);
			}
		},

		/**
		 * Load applications
		 *
		 * @param {object} _apps object list of applications
		 * @returns {undefined}
		 */
		loadApplications: function (_apps)
		{
			var restore = this._super.apply(this, arguments);
			var activeApp = '';
			if (!egwIsMobile()) _apps = this.apps;
			/**
			 * Check if the given app is in the navbar or not
			 *
			 * @param {string} appName application name
			 * @returns {Boolean} returns true if it is in the navbar, otherwise false
			 */
			var app_navbar_lookup  = function (appName)
			{
				for(var i=0; i< _apps.length; i++)
				{
					// Do not show applications which are not suppose to be shown on nabvar, except home
					if (appName == _apps[i].name && (!_apps[i]['noNavbar'] || _apps[i]['name'] == 'home')) return true;
				}
				return false;
			};

			//Now actually restore the tabs by passing the application, the url, whether
			//this is an legacyApp (null triggers the application default), whether the
			//application is hidden (only the active tab is shown) and its position
			//in the tab list.
			for (var app in this.applications)
			{
				if (typeof restore[app] == 'undefined')
				{
					restore[app]= {
						app:this.applications[app],
						url:this.applications[app].url
					};
				}
				if (restore[app].active !='undefined' && restore[app].active)
				{
					activeApp = app;
				}
				// Do not load the apps which are not in the navbar
				if (app_navbar_lookup(app)) this.applicationTabNavigate(restore[app].app, restore[app].url, app == activeApp?false:true,-1);
			}
			// Check if there is no activeApp active the Home app if exist
			// otherwise the first app in the list
			if (activeApp =="" || !activeApp)
			{
				for(var i in this.applications)
				{
					if (restore[i]['status'] != 5)
					{
						activeApp = this.applications[i];
						break;
					}
				}
				this.setActiveApp(typeof this.applications.home !='undefined'?
					this.applications.home:activeApp);
			}

			//Set the current state of the tabs and activate TabChangeNotification.
			this.serializedTabState = egw.jsonEncode(this.assembleTabList());
			this.notifyTabChangeEnabled = true;

			// Transfer tabs to the sidebar
			var $tabs = jQuery('.egw_fw_ui_tabs_header');
			$tabs.remove();

			// Disable loader, if present
			jQuery('#egw_fw_loading').hide();

		},

		/**
		 * Sets the active framework application to the application specified by _app
		 *
		 * @param {egw_fw_class_application} _app application object
		 */
		setActiveApp: function(_app)
		{
			this._super.apply(this,arguments);
			this.activeApp.preferences = egw.preference('egw_fw_mobile',this.activeApp.appName)||{};

		},

		/**
		 * Keep the last opened tab as an active tab for the first time login
		 */
		storeTabsStatus: function ()
		{
			var data = [];
			//Send the current tab list to the server

			var tabs = egw.preference('open_tabs','common');
			if (tabs)
			{
				var active = this.activeApp.appName||egw.preference('active_tab','common');
				if (tabs.indexOf(active)<0) tabs += ","+active;
				tabs = tabs.split(',');
				for (var i=0;i<tabs.length;i++)
				{
					data[i]= {
						appName:tabs[i],
						active: (active == tabs[i]?1:0)
					};
				}
			}
			//Serialize the tab list and check whether it really has changed since the last
			//submit
			var serialized = egw.jsonEncode(data);
			if (serialized != this.serializedTabState)
			{
				this.serializedTabState = serialized;
				if (this.tabApps) this._setTabAppsSession(this.tabApps);
				egw.jsonq("EGroupware\\Api\\Framework\\Ajax::ajax_tab_changed_state", [data]);
			}
		},

		/**
		 * applicationClickCallback is used internally by fw_mobile in order to handle clicks on
		 * sideboxmenu
		 *
		 * @param {egw_fw_ui_tab} _sender specifies the tab ui object, the user has clicked
		 */
		applicationClickCallback: function(_sender)
		{
			this._super.apply(this,arguments);
			framework.updateAppsToggle();
		},

		/**
		 * tabClickCallback is used internally by egw_fw in order to handle clicks on
		 * a tab.
		 *
		 * @param {egw_fw_ui_tab} _sender specifies the tab ui object, the user has clicked
		 */
		tabClickCallback: function(_sender)
		{
		   this._super.apply(this,arguments);

		   //framework.setSidebarState(this.tag.preferences.toggleMenu);

		},


		toggleMenuResizeHandler:function(_size)
		{
			var size= _size || this.sideboxSize;
			this.sideboxSizeCallback(size);
			this.activeApp.browser.callResizeHandler();
		},

		/**
		 * Callback to calculate height of browser iframe or div
		 *
		 * @param {object} _iframe dom node of iframe or null for div
		 * @returns number in pixel
		 */
		getIFrameHeight: function(_iframe)
		{
			var height = this._super.apply(this, arguments);
			if (_iframe)
			{
				height +=25;

				// Fix for iFrame Scrollbar for iOS
				// ATM safari does not support regular scrolling content insdie an iframe, therefore
				// we need to wrap them all with a div and apply overflow:scroll
				if (this.getUserAgent() === 'iOS')
				{
					jQuery(_iframe.parentNode).css({"-webkit-overflow-scrolling": "touch", "overflow-y":"scroll"});
					var $body =  jQuery(_iframe.contentWindow.document).find('body');
					if ($body.children().length >1)
					{
						$body.children().wrapAll('<div style="height:100%;overflow:scroll;-webkit-overflow-scrolling:touch;"></div>');
					}
					else if ($body.children().length == 1 && !$body.children().css('overflow') === 'scroll')
					{
						$body.children().css({overflow:'auto',height:'100%'});
					}
				}
			}
			height +=  jQuery('#egw_fw_sidebar').offset().top + 40;

			if (!this.isLandscape()) return height;

			return height;
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
			}
			if (this.activeApp && this.activeApp.appName != _app.appName) this.firstload_animation(_app.appName);
		},

		/**
		 * Open a (centered) popup window with given size and url as iframe
		 *
		 * @param {string} _url
		 * @param {number} _width
		 * @param {number} _height
		 * @param {string} _windowName or "_blank"
		 * @param {string|boolean} _app app-name for framework to set correct opener or false for current app
		 * @param {boolean} _returnID true: return window, false: return undefined
		 * @param {string} _status "yes" or "no" to display status bar of popup
		 * @param {DOMWindow} _parentWnd parent window
		 * @returns {DOMWindow|undefined}
		 */
		openPopup: function(_url, _width, _height, _windowName, _app, _returnID, _status, _parentWnd)
		{
			if (typeof _returnID == 'undefined') _returnID = false;

			var $wnd = jQuery(_parentWnd.top);
			var positionLeft = ($wnd.outerWidth()/2)-(_width/2)+_parentWnd.screenX;
			var positionTop  = ($wnd.outerHeight()/2)-(_height/2)+_parentWnd.screenY;

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
			var popup = new popupFrame(_parentWnd);

			if (typeof window.framework.popups != 'undefined')
				window.framework.popups.push(popup);

			popup.open(_url,_width,_height,positionLeft,positionTop);
			framework.pushState('popup',this.popup_idx(popup.$iFrame[0].contentWindow));
			var windowID = popup.$iFrame[0].contentWindow;

			// inject framework and egw object, because opener might not yet be loaded and therefore has no egw object!
			windowID.egw = window.egw;
			windowID.framework = this;

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
		},
		/**
		 * Check if given window is a "popup" alike, returning integer or undefined if not
		 *
		 * @param {DOMWindow} _wnd
		 * @returns {number|undefined}
		 */
		popup_idx: function(_wnd)
		{
			if (typeof window.framework.popups != 'undefined')
			{
				for (var i=0; i < window.framework.popups.length; i++)
				{
					if (window.framework.popups[i].$iFrame[0].contentWindow === _wnd)
					{
						return i;
					}
				}
			}
			return undefined;
		},
		/**
		 * @param {window} _wnd window object which suppose to be closed
		 */
		popup_close:function (_wnd)
		{
			var i = this.popup_idx(_wnd);

			if (i !== undefined)
			{
				// Close the matched popup
				window.framework.popups[i].close(i);
			}
		},

		resize_popup: function (_w,_h, _wnd)
		{
			var i = this.popup_idx(_wnd);
			if (i !== undefined)
			{
				//Here we can call popup resize
			}
		},
		/**
		 * Check if the framework is not running in fullScreen mode
		 * @returns {boolean|string} returns recommendation message if the app is not running in fullscreen mode otherwise false
		 */
		isNotFullScreen: function ()
		{
			switch (this.getUserAgent())
			{
				case 'iOS':
					if (navigator.standalone)
					{
						return false;
					}
					else
					{
						return egw.lang('For better experience please install mobile template in your device: tap on safari share button and then select Add to Home Screen');
					}
					break;
				case 'android':
					if (screen.height - window.outerHeight < 40	||
							((screen.height > 640 || screen.width>640) && screen.height - window.outerHeight < 82))
					{
						return false;
					}
					else
					{
						return egw.lang('For better experience please install mobile template in your device: tap on chrome setting and then select Add to Home Screen');
					}
				case 'unknown':

			}
		},

		/**
		 * get the device platform
		 * @returns {string} returns device platform name
		 */
		getUserAgent: function ()
		{
			var userAgent = navigator.userAgent || navigator.vendor || window.opera;
			//  iOS and safari
			if( userAgent.match( /iPad/i ) || userAgent.match( /iPhone/i ) || userAgent.match( /iPod/i ) )
			{
				return 'iOS';
			}
			// Android
			if (userAgent.match(/android/i))
			{
				return 'android';
			}
			return 'unknown';
		},

		/**
		 * Calculate the excess height available on popup frame. The excess height will be use in etemplate2 resize handler
		 *
		 * @param {type} _wnd current window
		 * @returns {Number} excess height
		 */
		get_wExcessHeight: function (_wnd)
		{
			var $popup = jQuery(_wnd.document);
			var $appHeader = $popup.find('#divAppboxHeader');

			//Calculate the excess height
			var excess_height = egw(_wnd).is_popup()? jQuery(_wnd).height() - $popup.find('#popupMainDiv').height() - $appHeader.outerHeight()+10: false;
			// Recalculate excess height if the appheader is shown, e.g. mobile framework dialogs
			if ($appHeader.length > 0 && $appHeader.is(':visible')) excess_height -= $appHeader.outerHeight()-9;

			return excess_height;
		},

		/**
		 * Function runs after etemplate is fully loaded
		 * - Triggers onpopupload framework popup's event
		 *
		 * @param {type} _wnd local window
		 */
		et2_loadingFinished: function (_wnd)
		{
			if (typeof this.popups != 'undefined' && this.popups.length > 0)
			{
				var i = this.popup_idx(_wnd);
				if (i !== undefined)
				{
					//Trigger onpopupload event for the current popup
					window.framework.popups[i].$iFrame.trigger('onpopupload');
				}
			}
			framework.firstload_animation('', 100);
		},

		/**
		 * This function can trigger vibration on compatible browsers and devices
		 *
		 * @param {array|int} _duration vibrate duration in milliseconds (ms), 0 means cancel all vibrations
		 */
		vibrate: function (_duration)
		{
			// enable vibration support
			navigator.vibrate = navigator.vibrate || navigator.webkitVibrate || navigator.mozVibrate || navigator.msVibrate;

			if (navigator.vibrate) {
				// vibration API supported
				navigator.vibrate(_duration);
			}
		},
		/**
		 * Push state history, set a state as hashed url param
		 *
		 * @param {type} _type type of state
		 * @param {type} _index index of state
		 */
		pushState: function (_type, _index)
		{
			var index = _index || 1;
			history.pushState({type:_type, index:_index}, _type, '#'+ egw.app_name()+"."+_type);
			history.pushState({type:_type, index:_index}, _type, '#'+ egw.app_name()+"."+_type + '#' + index);
		},

		/**
		 * Update the app header icon used for toggling between list of apps and
		 * application menu in sidebar
		 */
		updateAppsToggle: function ()
		{
			var $apps = jQuery('#egw_fw_appsToggle');
			var $sidebar = jQuery('#egw_fw_sidebar');
			$apps.attr('style','');
			$apps.off().on('click',function(){
				var $sidebar = jQuery('#'+egw.app_name()+'_sidebox_content');
				$sidebar.toggle();
				jQuery(this).css({
					'background-image':'url('+egw.webserverUrl+'/' + ($sidebar.is(":visible")?'api/templates/default/images/apps.svg':egw.app_name()+'/templates/default/images/navbar.svg)')
				});

			});
		},

		/**
		 * Function runs after nextmatch selection callback gets called by object manager,
		 * which we can update status of header DOM objects (eg. action_header, favorite, ...)
		 *
		 * @param {object} _widget nextmatch widget
		 * @param {object} _action action object
		 * @param {object} _senders selected row(s) action object
		 */
		nm_onselect_ctrl: function(_widget, _action, _senders)
		{
			var senders = _senders? _senders:null;

			// Update action_header status (3dots)
			_widget.header.action_header.toggle(typeof _widget.getSelection().ids != 'undefined' && _widget.getSelection().ids.length > 0);

			// Update selection counter in nm header
			if (_widget._type == 'nextmatch' && _widget.getSelection().ids.length > 0)
			{
				if (senders && senders[0].actionLinks)
				{
					var delete_action = null;
					for (var i=0; i< senders[0].actionLinks.length;i++)
					{
						if (senders[0].actionLinks[i].actionId == 'delete') delete_action = senders[0].actionLinks[i];
					}
					if (delete_action && delete_action.enabled)
					{
						_widget.header.delete_action
						.show()
						.off()
						.click(function(){
							if (delete_action) delete_action.actionObj.execute(senders);
						});
					}
				}
			}
			else
			{
				_widget.header.delete_action.hide();
			}
		},

		/**
		 *
		 * @param node
		 */
		toggle_darkmode: function(_node)
		{
			let node = _node || document.getElementById('topmenu_darkmode');
			let state = node.classList.contains('darkmode_on');
			egw.set_preference('common', 'darkmode',state?'0':'1');
			this._setDarkMode(state?'0':'1');
			if (state == 1)
			{
				node.classList.remove('darkmode_on');
				if (node.hasChildNodes()) node.children[0].classList.remove('darkmode_on');
				node.title = egw.lang('dark mode');
			}
			else
			{
				node.classList.add('darkmode_on');
				node.title = egw.lang('light mode');
				if (node.hasChildNodes()) node.children[0].classList.add('darkmode_on');
			}
		}
	});

	egw_LAB.wait(function() {
		/**
		* Initialise mobile framework
		* @param {int} _size width size which sidebox suppose to be open
		* @param {boolean} _fixedFrame make either the frame fixed or resizable
		*/
		function egw_setSideboxSize(_size,_fixedFrame)
		{
			var fixedFrame = _fixedFrame || false;
			var frameSize = _size;
			var sidebar = document.getElementById('egw_fw_sidebar');
			var mainFrame = document.getElementById('egw_fw_main');
			if (fixedFrame)
			{
				frameSize = 0;
				sidebar.style.zIndex = 999;
			}
			if (frameSize <= 72 || screen.width>700) mainFrame.style.marginLeft = frameSize + 'px';
			sidebar.style.width = _size + 'px';
		}

		jQuery(document).ready(function() {
			window.framework = new fw_mobile("egw_fw_sidemenu", "egw_fw_tabs",
					window.egw_webserverUrl, egw_setSideboxSize, 300, 'egw_fw_basecontainer', 'egw_fw_toggler');
			window.callManual = window.framework.callManual;
			jQuery('#egw_fw_print').click(function(){window.framework.print();});
			jQuery('#topmenu_logout').click(function(){ window.framework.redirect(this.getAttribute('href')); return false;});
			jQuery('form[name^="tz_selection"]').children()
				.on('change', function() { framework.tzSelection(this.value); return false; })
				.on('click', function(e) { e.stopPropagation(); });
			window.egw.link_quick_add('quick_add');
			window.egw.add_timer('topmenu_info_timer');
			history.pushState({type:'main'}, 'main', '#main');
			jQuery(window).on('popstate', function(e){
				// Check if user wants to logout and ask a confirmation
				if (window.location.hash == '#main') {
					et2_dialog.show_dialog(function(button){
						if (button === 3){
							history.forward();
							return;
						}
						history.back();
					}, egw.lang('Are you sure you want to logout?'), 'Logout');
				}
				// Execute action based on
				switch (e.originalEvent.state && e.originalEvent.state.type)
				{
					case 'popup':
						window.framework.popups[e.originalEvent.state.index].close(e.originalEvent.state.index);
						break;
					case 'view':
						jQuery('.egw_fw_mobile_popup_close').click();
						break;
				}

				e.preventDefault();
			});
			// allowing javascript urls in topmenu and sidebox only under CSP by binding click handlers to them
			var href_regexp = /^javascript:([^\(]+)\((.*)?\);?$/;
			jQuery('#egw_fw_topmenu_items,#egw_fw_topmenu_info_items,#egw_fw_sidemenu,#egw_fw_footer').on('click','a[href^="javascript:"]',function(ev){
				ev.stopPropagation();	// do NOT execute regular event, as it will violate CSP, when handler does NOT return false
				// fix for Chrome 94.0.4606.54 returning all but first single quote "'" in href as "%27" :(
				var matches = this.href.replaceAll(/%27/g, "'").replaceAll(/%22/g, '"').match(href_regexp);
				var args = [];
				if (matches.length > 1 && matches[2] !== undefined)
				{
					try {
						args = JSON.parse('['+matches[2]+']');
					}
					catch(e) {	// deal with '-enclosed strings (JSON allows only ")
						args = JSON.parse('['+matches[2].replace(/','/g, '","').replace(/((^|,)'|'(,|$))/g, '$2"$3')+']');
					}
				}
				args.unshift(matches[1]);
				if (matches[1] !== 'void') et2_call.apply(this, args);
				return false;	// IE11 seems to require this, ev.stopPropagation() does NOT stop link from being executed
			});
		});
	});
})(window);