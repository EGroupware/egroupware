/**
 * eGroupware mobile framework object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @copyright Stylite AG 2014
 * @description Create mobile framework
 */


/*egw:uses
	jquery.jquery;
	/phpgwapi/js/jquery/TouchSwipe/jquery.touchSwipe.js;
	framework.fw_base;
	framework.fw_browser;
	framework.fw_ui;
	egw_fw_classes;
	egw_inheritance.js;
*/

/**
 *
 * @param {DOMWindow} window
 */
(function(window){
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
			var $baseDiv = $j(this.baseDiv);
			$baseDiv.swipe({
				swipe: function (e, direction,distance)
				{

					switch (direction)
					{
						case "up":
						case "down":
							if ($baseDiv.css('overflow') == 'hidden')
								$baseDiv.css('overflow-y','auto');
							break;
						case "left":
							if (distance >= 10)
							{
								framework.toggleMenu();
							}

							break;
						 case "right":
							 framework.toggleMenu();
					}
				},
				swipeStatus:function(event, phase, direction, distance, duration, fingers)
				{
					switch (direction)
					{



					}
				},
				allowPageScroll: "vertical"
			});
			// Do not attach sidebox application entries
			$j(this.elemDiv).detach();
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
			$j(this.baseDiv).hide();
			$j('#egw_fw_top_toolbar').hide();
		},

		/**
		 * * Show sidebar menu and top toolbar
		 */
		enable: function ()
		{
			$j(this.baseDiv).show();
			$j('#egw_fw_top_toolbar').show();
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
			this.$container = $j(document.createElement('div')).addClass('egw_fw_mobile_popup_container egw_fw_mobile_popup_loader');
			this.$iFrame = $j(document.createElement('iframe'))
					.addClass('egw_fw_mobile_popupFrame')
					.appendTo(this.$container);
			this.$container.appendTo('body');
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
				var $appHeader = $j(popupWindow.document).find('#divAppboxHeader');
				var $closeBtn = $appHeader.find('.egw_fw_mobile_popup_close');
				if ($closeBtn.length  == 0)
				{
					$closeBtn =  $j(document.createElement('span'))
										.addClass('egw_fw_mobile_popup_close')
										.click(function (){self.close(framework.popup_idx(self.$iFrame[0].contentWindow));});
					if ($appHeader.length > 0)
					{
						$appHeader.addClass('egw_fw_mobile_popup_appHeader');
						// Add close button only after everything is loaded
						setTimeout(function(){$appHeader.prepend($closeBtn)},0);
					}
				}

				//Remove the loading class
				self.$container.removeClass('egw_fw_mobile_popup_loader');
				self.$iFrame.css({visibility:'visible'});
			});


			this.$iFrame.on('load',
				//In this function we can override all popup window objects
				function ()
				{
					var popupWindow = this.contentWindow;
					var $appHeader = $j(popupWindow.document).find('#divAppboxHeader');
					var $et2_container = $j(popupWindow.document).find('.et2_container');
					if ($appHeader.length > 0)
					{
						// Extend the dialog to 100% width
						$et2_container.css({width:'100%', height:'100%'});
						if (framework.getUserAgent() === 'iOS' && !framework.isNotFullScreen()) $appHeader.addClass('egw_fw_mobile_iOS_popup_appHeader');
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

			//Bind handler to orientation change
			$j(window).on("orientationchange",function(){
				self.orientation();
			});

			this.baseContainer = document.getElementById(_baseContainer);
			this.mobileMenu = document.getElementById(_mobileMenu);

			//Bind the click handler to menu
			$j(this.mobileMenu).on({
				click:function()
				{
					self.toggleMenu();
				}
			});

			if (this.sidemenuDiv && this.tabsDiv)
			{
				//Create the sidemenu, the tabs area
				this.sidemenuUi = new mobile_ui_sidemenu(this.sidemenuDiv);
				this.tabsUi = new egw_fw_ui_tabs(this.tabsDiv);

				var egw_script = document.getElementById('egw_script_id');
				var apps = egw_script ? egw_script.getAttribute('data-navbar-apps') : null;
				this.loadApplications(JSON.parse(apps));
			}

			this.sideboxSizeCallback(_sideboxStartSize);

			// Check if user runs the app in full screen or not, then prompt user base on the mode
			var fullScreen = this.isNotFullScreen()
			if (fullScreen) egw.message(fullScreen,'info');
		},

		/**
		 *
		 * @returns {undefined}
		 */
		setSidebox:function()
		{
			this._super.apply(this,arguments);
			this.setSidebarState(this.activeApp.preferences.toggleMenu);
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
		 * Arranging toolbar icons according to device orientation
		 *
		 * @param {string} _orientation in order to determine which box should be transfered {"top"|"side"}.
		 * default value is landscape
		 */
		arrangeToolbar: function (_orientation)
		{
			var orientation = _orientation || 'landscape';
			var $toolbar = $j('#egw_fw_top_toolbar');
			//tabs container
			var $tabs = $j('.egw_fw_ui_tabs_header');

			if (orientation === 'landscape')
			{
				$toolbar.css('height','auto');
				this.toggleMenuResizeHandler(this.getToggleMenuState() === "off"?72:280);
				$tabs.appendTo('#egw_fw_sidemenu');
				// Remove tabs header portriat's specific styles
				$tabs.removeClass('tabs-header-portrait-collapsed');
			}
			else
			{
				$toolbar.css('height','60px');
				$tabs.appendTo($toolbar);
				this.toggleMenuResizeHandler(this.getToggleMenuState() === "off"?1:280);
				if (this.getToggleMenuState() === "off")
				{
					$tabs.addClass('tabs-header-portrait-collapsed');
				}
				else
				{
					$tabs.removeClass('tabs-header-portrait-collapsed');
				}
				//Tabs are scrollable
				if ($tabs[0].scrollHeight > $tabs.height())
				{
					$tabs.addClass('egw-fw-tabs-scrollable');
				}
			}
		},

		/**
		 * Orientation on change method
		 */
		orientation: function ()
		{
			this.arrangeToolbar(this.isLandscape()?'landscape':'portrait');

			//Mail splitter needs to be docked after oriantation
			if (this.activeApp.appName === 'mail' && egwIsMobile())
			{
				var splitter = etemplate2?etemplate2.getByApplication('mail')[0].widgetContainer.getWidgetById('mailSplitter'):false;
				if (splitter)
				{
					splitter.undock();
					//Try to make sure that the docking happens after the mail index rendered by browser
					setTimeout(function(){
						splitter.dock();
					},500);
				}
			}
		},

		/**
		 * Toggle sidebar menu
		 * @param {string} _state
		 */
		toggleMenu: function (_state)
		{
			var state = _state || this.getToggleMenuState();
			var collapseSize = this.isLandscape()?72:1;
			var expandSize = 280;
			var $toggleMenu = $j(this.baseContainer);
			var $tabs =  $j('.egw_fw_ui_tabs_header');
			if (state === 'on')
			{
				$toggleMenu.addClass('sidebar-toggle');
				if (!this.isLandscape()) $tabs.addClass('tabs-header-portrait-collapsed');
				this.toggleMenuResizeHandler(collapseSize);
				this.setToggleMenuState('off');

			}
			else
			{
				$toggleMenu.removeClass('sidebar-toggle');
				this.toggleMenuResizeHandler(expandSize);
				this.setToggleMenuState('on');
				if (!this.isLandscape()) $tabs.removeClass('tabs-header-portrait-collapsed');
			}

			//Audio effect for toggleMenu
			var audio = $j('#egw_fw_menuAudioTag');
			if (egw.preference('audio_effect','common') == '1')	audio[0].play();
		},

		/**
		 * Gets the active app toggleMenu state value
		 *
		 * @returns {string} returns state value off | on
		 */
		getToggleMenuState: function ()
		{
			var $toggleMenu = $j(this.baseContainer);
			var state = '';
			if (typeof this.activeApp.preferences.toggleMenu!='undefined')
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
				egw.set_preference(this.activeApp.appName,'egw_fw_mobile',this.activeApp.preferences);
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
			var $toggleMenu = $j(this.baseContainer);
			if (_state === 'off')
			{
				$toggleMenu.addClass('sidebar-toggle');
				this.toggleMenuResizeHandler(72);
			}
			else
			{
				$toggleMenu.removeClass('sidebar-toggle');
				this.toggleMenuResizeHandler(280);
			}
		},

		/**
		 *
		 * @returns {undefined}
		 */
		loadApplications: function ()
		{
			var restore = this._super.apply(this, arguments);
			var activeApp = '';

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
				this.applicationTabNavigate(restore[app].app, restore[app].url, app == activeApp?false:true,
					-1);
			}
			//Set the current state of the tabs and activate TabChangeNotification.
			this.serializedTabState = egw.jsonEncode(this.assembleTabList());

			// Transfer tabs to the sidebar
			var $tabs = $j('.egw_fw_ui_tabs_header');
			$tabs.appendTo(this.sidemenuDiv);

			// Disable loader, if present
			$j('#egw_fw_loading').hide();
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
		 * applicationClickCallback is used internally by fw_mobile in order to handle clicks on
		 * sideboxmenu
		 *
		 * @param {egw_fw_ui_tab} _sender specifies the tab ui object, the user has clicked
		 */
		applicationClickCallback: function(_sender)
		{
			this._super.apply(this,arguments);
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

		   framework.setSidebarState(this.tag.preferences.toggleMenu);
		},


		toggleMenuResizeHandler:function(_size)
		{
			var size= _size || 280;
			this.sideboxSizeCallback(size);
			this.appData.browser.callResizeHandler();
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
					var $body =  $j(_iframe.contentWindow.document).find('body');
					if ($body.children().length >1)
					{
						$body.children().wrapAll('<div style="height:100%;overflow:scroll;"></div>');
					}
					else if ($body.children().length == 1 && !$body.children().css('overflow') === 'scroll')
					{
						$body.children().css({overflow:'auto',height:'100%'});
					}
				}
			}
			height +=  jQuery('#egw_fw_sidebar').offset().top;

			// fullScreen iOS need to be set with different height as safari adds an extra bottom border
			if (this.getUserAgent() === 'iOS' && !this.isNotFullScreen())
			{
				height +=5;
			}
			else
			{
				height +=20;
			}

			if (!this.isLandscape()) return height - 30;

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
				_app.tab = this.tabsUi.addTab(_app.icon, this.tabClickCallback, function(){},
					_app, _pos);
				_app.tab.setTitle(_app.displayName);
			}
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
						$j(this.baseContainer).css({top:20});
						return false;
					}
					else
					{
						return egw.lang('For better experience please install mobile template in your device: tap on safari share button and then select Add to Home Screen');
					}
					break;
				case 'android':
					if (screen.height - window.outerHeight < 40)
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
			return 'unknown'
		},

		/**
		 * Calculate the excess height available on popup frame. The excess height will be use in etemplate2 resize handler
		 *
		 * @param {type} _wnd current window
		 * @returns {Number} excess height
		 */
		get_wExcessHeight: function (_wnd)
		{
			var $popup = $j(_wnd.document);
			var $appHeader = $popup.find('#divAppboxHeader');

			//Calculate the excess height
			var excess_height = egw(_wnd).is_popup()? $j(_wnd).height() - $popup.find('#popupMainDiv').height() - $appHeader.outerHeight()+10: false;
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
			mainFrame.style.marginLeft = frameSize + 'px';
			sidebar.style.width = _size + 'px';
		}

		$j(document).ready(function() {
			window.framework = new fw_mobile("egw_fw_sidemenu", "egw_fw_tabs",
					window.egw_webserverUrl, egw_setSideboxSize, 280, 'egw_fw_basecontainer', 'egw_fw_menu');
			window.callManual = window.framework.callManual;
			jQuery('#egw_fw_print').click(function(){window.framework.print();});
			jQuery('#egw_fw_logout').click(function(){ window.framework.redirect(this.getAttribute('data-logout-url')); });
			jQuery('form[name^="tz_selection"]').children().on('change', function(){framework.tzSelection(this.value);	return false;});
			window.egw.link_quick_add('quick_add');

			// allowing javascript urls in topmenu and sidebox only under CSP by binding click handlers to them
			var href_regexp = /^javascript:([^\(]+)\((.*)?\);?$/;
			jQuery('#egw_fw_topmenu_items,#egw_fw_topmenu_info_items,#egw_fw_sidemenu,#egw_fw_footer').on('click','a[href^="javascript:"]',function(ev){
				ev.stopPropagation();	// do NOT execute regular event, as it will violate CSP, when handler does NOT return false
				var matches = this.href.match(href_regexp);
				var args = [];
				if (matches.length > 1 && matches[2] !== undefined)
				{
					try {
						args = JSON.parse('['+matches[2]+']');
					}
					catch(e) {	// deal with '-encloded strings (JSON allows only ")
						args = JSON.parse('['+matches[2].replace(/','/g, '","').replace(/((^|,)'|'(,|$))/g, '$2"$3')+']');
					}
				}
				args.unshift(matches[1]);
				et2_call.apply(this, args);
				return false;	// IE11 seems to require this, ev.stopPropagation() does NOT stop link from being executed
			});
		});
	});
})(window);
