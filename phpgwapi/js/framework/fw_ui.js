/**
 * eGroupware Framework ui object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @author Andreas Stoeckel <as@stylite.de>
 * @copyright Stylite AG 2014
 * @description Framework ui object, is implementation of UI class
 */

/*egw:uses
	jquery.jquery;
	/phpgwapi/js/jquery/mousewheel/mousewheel.js;
	egw_inheritance.js;
*/

/**
 * ui siemenu entry class
 * Basic sidebar menu implementation
 *
 * @type @exp;Class@call;extend
 */
var fw_ui_sidemenu_entry = (function(){ "use strict"; return Class.extend(
{
	/**
	 * Framework ui sidemenu entry class constructor
	 *
	 * @param {object} _parent specifies the parent egw_fw_ui_sidemenu
	 * @param {object} _baseDiv specifies "div" element the entries should be appended to.
	 * @param {object} _elemDiv
	 * @param {string} _name specifies the title of the entry in the side menu
	 * @param {string} _icon specifies the icon which should be viewd besides the title in the side menu
	 * @param {function}(_sender) _callback specifies the function which should be called when the entry is clicked. The _sender parameter passed is a reference to this egw_fw_ui_sidemenu_entry element.
	 * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
	 * @param {string} _app application name
	 */
	init: function (_parent, _baseDiv, _elemDiv, _name, _icon, _callback, _tag, _app)
	{
		this.baseDiv = _baseDiv;
		this.elemDiv = _elemDiv;
		this.entryName = _name;
		this.icon = _icon;
		this.tag = _tag;
		this.parent = _parent;
		this.atTop = false;
		this.isDraged = false;

		//Add a new div for the new entry to the base div
		this.headerDiv = document.createElement("div");
		this.headerDiv.id = _app+'_sidebox_header';
		$j(this.headerDiv).addClass("egw_fw_ui_sidemenu_entry_header");

		//Create the icon and set its image
		var iconDiv = egw.image_element(this.icon, _name);
		$j(iconDiv).addClass("egw_fw_ui_sidemenu_entry_icon");

		//Create the AJAX loader image (currently NOT used)
		this.ajaxloader = document.createElement("div");
		$j(this.ajaxloader).addClass("egw_fw_ui_ajaxloader");
		$j(this.ajaxloader).hide();

		//Create the entry name header
		var entryH1 = document.createElement("h1");
		$j(entryH1).text(this.entryName);

		//Append icon, name, and ajax loader
		$j(this.headerDiv).append(iconDiv);
		$j(this.headerDiv).append(entryH1);
		$j(this.headerDiv).append(this.ajaxloader);
		this.headerDiv._parent = this;
		this.headerDiv._callbackObject = new egw_fw_class_callback(this, _callback);
		$j(this.headerDiv).click(function(){
			if (!this._parent.isDraged)
			{
				this._callbackObject.call(this);
			}
			this._parent.isDraged = false;
			return true;
		});

		//Create the content div
		this.contentDiv = document.createElement("div");
		this.contentDiv.id = _app+'_sidebox_content';
		$j(this.contentDiv).addClass("egw_fw_ui_sidemenu_entry_content");
		$j(this.contentDiv).hide();

		//Add in invisible marker to store the original position of this element in the DOM tree
		this.marker = document.createElement("div");
		this.marker._parent = this;
		this.marker.className = 'egw_fw_ui_sidemenu_marker';
		var entryH1_ = document.createElement("h1");
		$j(entryH1_).text(this.entryName);
		$j(this.marker).append(entryH1_);
		$j(this.marker).hide();

		//Create a container which contains all generated elements and is then added
		//to the baseDiv
		this.containerDiv = document.createElement("div");
		this.containerDiv._parent = this;
		$j(this.containerDiv).append(this.marker);
		$j(this.containerDiv).append(this.headerDiv);
		$j(this.containerDiv).append(this.contentDiv);

		//Append header and content div to the base div
		$j(this.elemDiv).append(this.containerDiv);
	},

	/**
	 * setContent replaces the content of the sidemenu entry with the content given by _content.
	 * @param {string} _content HTML/Text which should be displayed.
	 */
	setContent: function(_content)
	{
		//Set the content of the contentDiv
		$j(this.contentDiv).empty();
		$j(this.contentDiv).append(_content);
	},

	/**
	 * open openes this sidemenu_entry and displays the content.
	 */
	open: function()
	{
		/* Move this entry to the top of the list */
		if (egwIsMobile())
		{
			$j(this.baseDiv).append(this.headerDiv);
			$j(this.baseDiv).append(this.contentDiv);

		}
		else
		{
			$j(this.baseDiv).prepend(this.contentDiv);
			$j(this.baseDiv).prepend(this.headerDiv);
		}

		this.atTop = true;

		$j(this.headerDiv).addClass("egw_fw_ui_sidemenu_entry_header_active");
		$j(this.contentDiv).show();
	},

	/**
	 * close closes this sidemenu_entry and hides the content.
	 */
	close: function()
	{
		/* Move the content and header div behind the marker again */
		if (this.atTop)
		{
			$j(this.marker).after(this.contentDiv);
			$j(this.marker).after(this.headerDiv);
			this.atTop = false;
		}

		$j(this.headerDiv).removeClass("egw_fw_ui_sidemenu_entry_header_active");
		$j(this.contentDiv).hide();
	},

	/**
	 * egw_fw_ui_sidemenu_entry_header_active
	 * showAjaxLoader shows the AjaxLoader animation which should be displayed when
	 * the content of the sidemenu entry is just being loaded.
	 */
	showAjaxLoader: function()
	{
		$j(this.ajaxloader).show();
	},

	/**
	 * showAjaxLoader hides the AjaxLoader animation
	 */
	hideAjaxLoader: function()
	{
		$j(this.ajaxloader).hide();
	},

	/**
	 * Removes this entry.
	 */
	remove: function()
	{
		$j(this.headerDiv).remove();
		$j(this.contentDiv).remove();
	}
});}).call(this);

/**
 *
 * @type @exp;Class@call;extend
 */
var fw_ui_sidemenu = (function(){ "use strict"; return Class.extend(
{
	/**
	* The constructor of the egw_fw_ui_sidemenu.
	*
	* @param {object} _baseDiv specifies the "div" in which all entries added by the addEntry function should be displayed.
	*/
   init:function(_baseDiv)
   {
	   this.baseDiv = _baseDiv;
	   this.elemDiv = document.createElement('div');
	   $j(this.baseDiv).append(this.elemDiv);
	   this.entries = new Array();
	   this.activeEntry = null;
   },

   /**
	* Funtion used internally to recursively step through a dom tree and add all appliction
	* markers in their order of appereance
	*
	* @param {array} _resultArray
	* @param {array} _children
	*/
   _searchMarkers: function(_resultArray, _children)
   {
	   for (var i = 0; i < _children.length; i++)
	   {
		   var child = _children[i];

		   if (child.className == 'egw_fw_ui_sidemenu_marker' && typeof child._parent != 'undefined')
		   {
			   _resultArray.push(child._parent);
		   }

		   this._searchMarkers(_resultArray, child.childNodes);
	   }
   },


   /**
	* Adds an entry to the sidemenu.
	*
	* @param {string} _name specifies the title of the new sidemenu entry
	* @param {string} _icon specifies the icon displayed aside the title
	* @param {function}(_sender) _callback specifies the function which should be called when a callback is clicked
	* @param {object} _tag extra data
	* @param {string} _app application name
	*/
   addEntry: function(_name, _icon, _callback, _tag, _app)
   {
	   //Create a new sidemenu entry and add it to the list
	   var entry = new egw_fw_ui_sidemenu_entry(this, this.baseDiv, this.elemDiv, _name, _icon,
		   _callback, _tag, _app);
	   this.entries[this.entries.length] = entry;

	   return entry;
   },

   /**
	* Openes the specified entry whilst closing all other entries in the list.
	*
	* @param {object} _entry specifies the entry which should be opened.
	*/
   open: function(_entry)
   {
	   //Close all other entries
	   for (var i = 0; i < this.entries.length; i++)
	   {
		   if (this.entries[i] != _entry)
		   {
			   this.entries[i].close();
		   }
	   }

	   if (_entry != null)
	   {
		   _entry.open();
	   }

	   this.activeEntry = _entry;
   },


   /**
	* Deletes all sidemenu entries.
	*/
   clean: function()
   {
	   for (var i = 0; i < this.entries.length; i++)
	   {
		   this.entries[i].remove();
	   }

	   this.entries = new Array();
   }
});}).call(this);

/**
 * Class: egw_fw_ui_tab
 * The egw_fw_ui_tab represents a single tab "sheet" in the ui
 */


/**
 * The constructor of the egw_fw_ui_tab class.
 *
 * @param {object} _parent specifies the parent egw_fw_ui_tabs class
 * @param {object} _contHeaderDiv specifies the container "div" element, which should contain the headers
 * @param {object} _contDiv specifies the container "div" element, which should contain the contents of the tabs
 * @param {string} _icon specifies the icon which should be viewed besides the title of the tab
 * @param {function}(_sender) _callback specifies the function which should be called when the tab title is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {function}(_sender) _closeCallback specifies the function which should be called when the tab close button is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 * @param {int} _pos is the position where the tab will be inserted
 */
function egw_fw_ui_tab(_parent, _contHeaderDiv, _contDiv, _icon, _callback,
	_closeCallback,	_tag, _pos)
{
	this.parent = _parent;
	this.contHeaderDiv = _contHeaderDiv;
	this.contDiv = _contDiv;
	this.title = '';
	this.tag = _tag;
	this.closeable = true;
	this.callback = _callback;
	this.closeCallback = _closeCallback;
	this.position = _pos;

	//Create the header div and set its "click" function and "hover" event
	this.headerDiv = document.createElement("span");
	this.headerDiv._position = _pos;
	$j(this.headerDiv).addClass("egw_fw_ui_tab_header");

	//Create a new callback object and attach it to the header div
	this.headerDiv._callbackObject = new egw_fw_class_callback(this, _callback);
	$j(this.headerDiv).click(
		function(){
			this._callbackObject.call(this);
		});

	//Attach the hover effect to the header div
	$j(this.headerDiv).hover(
		function() {
			if (!$j(this).hasClass("egw_fw_ui_tab_header_active"))
				$j(this).addClass("egw_fw_ui_tab_header_hover");
		},
		function() {
			$j(this).removeClass("egw_fw_ui_tab_header_hover");
		}
	);

	// If dragging something over the tab, activate that app
	var tab = this.headerDiv;
	$j(this.headerDiv).droppable({
		tolerance:"pointer",
		over: function() {
			tab._callbackObject.call(tab);
		}
	});


	//Create the close button and append it to the header div
	this.closeButton = document.createElement("span");
	this.closeButton._callbackObject = new egw_fw_class_callback(this, _closeCallback);
	$j(this.closeButton).addClass("egw_fw_ui_tab_close_button");
	$j(this.closeButton).click(
		function(){
			//Only call the close callback if the tab is set closeable
			if (this._callbackObject.context.closeable)
			{
				this._callbackObject.call(this);
				return false;
			}
			return true;
		});


	$j(this.headerDiv).append(this.closeButton);

	//Create the icon and append it to the header div
	var icon = egw.image_element(_icon);
	$j(icon).addClass("egw_fw_ui_tab_icon");
	$j(this.headerDiv).append(icon);

	//Create the title h1 and append it to the header div
	this.headerH1 = document.createElement("h1");
	this.setTitle('');
	$j(this.headerDiv).append(this.headerH1);


	$j(this.headerDiv).append(this.closeButton);

	this.contentDiv = document.createElement("div");
	$j(this.contentDiv).addClass("egw_fw_ui_tab_content");
	$j(this.contentDiv).hide();

	//Sort the element in at the given position
	var _this = this;
	var $_children = $j(this.contHeaderDiv).children();
	var _cnt = $_children.size();

	if (_cnt > 0 && _pos > -1)
	{
		$_children.each(function(i) {
			if (_pos <= this._position)
			{
				$j(this).before(_this.headerDiv);
				return false;
			}
			else if (i == (_cnt - 1))
			{
				$j(this).after(_this.headerDiv);
				return false;
			}
		});
	}
	else
	{
		$j(this.contHeaderDiv).append(this.headerDiv);
	}

	$j(this.contDiv).append(this.contentDiv);
}

/**
 * setTitle sets the title of this tab. An existing title will be removed.
 *
 * @param {string} _title HTML/Text which should be displayed.
 */
egw_fw_ui_tab.prototype.setTitle = function(_title)
{
	this.title = _title;
	$j(this.headerH1).empty();
	$j(this.headerH1).text(_title);
};

/**
 * setTitle sets the content of this tab. Existing content is removed.
 *
 * @param {string} _content HTML/Text which should be displayed.
 */
egw_fw_ui_tab.prototype.setContent = function(_content)
{
	$j(this.contentDiv).empty();
	$j(this.contentDiv).append(_content);
};

/**
 * Shows the content of the tab. Only one tab should be displayed at once. By using egw_fw_ui_tabs.showTab
 * you can assure this.
 */
egw_fw_ui_tab.prototype.show = function()
{
	$j(this.headerDiv).addClass("egw_fw_ui_tab_header_active");
	var content = $j(this.contentDiv);
	if(!content.is(':visible'))
	{
		content.show();

		// Trigger an event on the browser content, so apps & widgets know
		if(this.tag && this.tag.browser && this.tag.browser.contentDiv)
		{
			$j(this.tag.browser.contentDiv).trigger('show');
		}
		else if(content) // if the content is an iframe (eg. Calendar views)
		{
			$j(content).find('.egw_fw_content_browser_iframe').trigger('show');
		}
	}
};

/**
 * Hides the content of this tab.
 */
egw_fw_ui_tab.prototype.hide = function()
{
	$j(this.headerDiv).removeClass("egw_fw_ui_tab_header_active");
	var content = $j(this.contentDiv);
	if(content.is(':visible'))
	{
		content.hide();

		// Trigger an event on the browser content, so apps & widgets know
		if(this.tag && this.tag.browser && this.tag.browser.contentDiv)
		{
			$j(this.tag.browser.contentDiv).trigger('hide');
		}
	}
};

/**
 * Removes this tab and all its content.
 */
egw_fw_ui_tab.prototype.remove = function()
{
	this.hide();
	$j(this.contentDiv).remove();
	$j(this.headerDiv).remove();
};

/**
 * Sets whether the close button is shown/the close callback ever gets called.
 *
 * @param {boolean} _closeable if true, the close button is shown, if false, the close button is hidden. default is true.
 */
egw_fw_ui_tab.prototype.setCloseable = function(_closeable)
{
	this.closeable = _closeable;
	if (_closeable)
		$j(this.closeButton).show();
	else
		$j(this.closeButton).hide();
};


/**
 * Class: egw_fw_ui_tabs
 * The egw_fw_ui_tabs class cares about displaying a set of tab sheets.
 */


/**
 * The constructor of the egw_fw_ui_sidemenu_tabs class. Two "divs" are created inside the specified container element, one for the tab headers and one for the tab contents.
 *
 * @param {object} _contDiv specifies "div" element the tab ui element should be displayed in.
 */
function egw_fw_ui_tabs(_contDiv)
{
	this.contDiv = _contDiv;

	//Create a div for the tab headers
	this.contHeaderDiv = document.createElement("div");
	$j(this.contHeaderDiv).addClass("egw_fw_ui_tabs_header");
	$j(this.contDiv).append(this.contHeaderDiv);

	this.appHeaderContainer = $j(document.createElement("div"));
	this.appHeaderContainer.addClass("egw_fw_ui_app_header_container");
	$j(this.contDiv).append(this.appHeaderContainer);

	this.appHeader = $j(document.createElement("div"));
	this.appHeader.addClass("egw_fw_ui_app_header");
	this.appHeader.hide();
	this.appHeaderContainer.append(this.appHeader);

	this.tabs = Array();

	this.activeTab = null;
	this.tabHistory = Array();
}

/**
 * Sets the "appHeader" text below the tabs list.
 *
 * @param {string} _text is the text which will be seen in the appHeader.
 * @param {string} _msg_class css class for message
 */
egw_fw_ui_tabs.prototype.setAppHeader = function(_text, _msg_class)
{
	this.appHeader.text(_text);
	this.appHeader.prop('class', "egw_fw_ui_app_header");
	if (_msg_class) this.appHeader.addClass(_msg_class);
	this.appHeader.show();
};

/**
 * Function internally used to remove double entries from the tab history. The tab
 * history is used to store the order in which the tabs have been opened, to be able
 * to switch back to the last tab when a tab is closed. Double entries in the tab history
 * may appear whenever a tab is deleted.
 */
egw_fw_ui_tabs.prototype.cleanHistory = function()
{
	for (var i = this.tabHistory.length - 1; i >= 0; i--)
	{
		if (this.tabHistory[i] == this.tabHistory[i - 1])
		{
			array_remove(this.tabHistory, i);
		}
	}
};

/**
 * Adds a new tab to the tabs ui element.
 * @param {string} _icon which should be displayed on the tab sheet header
 * @param {function} _callback (_sender) function which should be called whenever the tab header is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {function} _closeCallback (_sender) function which should be called whenever the close button of the tab is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 * @param {int} _pos specifies the position in the tab list. If _pos is -1, the tab will be added to the end of the tab list
 */
egw_fw_ui_tabs.prototype.addTab = function(_icon, _callback, _closeCallback, _tag, _pos)
{
	var pos = -1;
	if (typeof _pos != 'undefined')
		pos = _pos;

	var tab = new egw_fw_ui_tab(this, this.contHeaderDiv, this.contDiv, _icon, _callback,
		_closeCallback, _tag, pos);

	//Insert the tab into the tab list.
	var inserted = false;
	if (pos > -1)
	{
		for (var i in this.tabs)
		{
			if (this.tabs[i].position > pos)
			{
				this.tabs.splice(i, 0, tab);
				inserted = true;
				break;
			}
		}
	}

	if (pos == -1 || !inserted)
	{
		this.tabs[this.tabs.length] = tab;
	}

	if (this.activeTab == null)
		this.showTab(tab);

	return tab;
};

/**
 * Removes the specified tab from the tab list whilst trying to keep one tab open.
 * The tab which will be opened is determined throughout the tab open history.
 *
 * @param {object} _tab is the object which should be closed.
 */
egw_fw_ui_tabs.prototype.removeTab = function(_tab)
{
	//Delete the deleted tab from the history
	for (var i = this.tabHistory.length - 1; i >= 0; i--)
	{
		if (this.tabHistory[i] == _tab)
			array_remove(this.tabHistory, i);
	}

	//Delete entries in the histroy which might be double
	this.cleanHistory();

	//Special treatement if the currently active tab gets deleted
	if (_tab == this.activeTab)
	{
		//Search for the next tab which should be selected
		if (this.tabs.length > 0)
		{
			//Check whether there is another tab in the tab history,
			//if not, simply show the first (or next, if tab is first) tab in the list.
			var tab = _tab == this.tabs[0] ? this.tabs[1] : this.tabs[0];
			if (typeof this.tabHistory[this.tabHistory.length - 1] != 'undefined')
			{
				tab = this.tabHistory[this.tabHistory.length - 1];
			}

			tab.callback.call(tab);
		}
	}

	//Perform the actual deletion of the tab
	_tab.remove();
	for (var i = this.tabs.length - 1; i >= 0; i--)
	{
		if (this.tabs[i] == _tab)
			array_remove(this.tabs, i);
	}
};

/**
 * Shows the specified _tab whilst closing all others.
 *
 * @param {object} _tab is the object which should be opened.
 */
egw_fw_ui_tabs.prototype.showTab = function(_tab)
{
	if (this.activeTab != _tab)
	{
		for (var i = 0; i < this.tabs.length; i++)
		{
			if (this.tabs[i] != _tab)
			{
				this.tabs[i].hide();
			}
		}

		_tab.show();
		this.activeTab = _tab;

		if (this.tabHistory[this.tabHistory.length - 1] != _tab)
			this.tabHistory[this.tabHistory.length] = _tab;

		//Limit the tabHistory size in order to save memory
		if (this.tabHistory.length > 50)
		{
			array_remove(this.tabHistory, 0);
		}
	}
};

/**
 * Calls the setCloseable function of all tabs in the list.
 *
 * @param {boolean} _closeable
 */
egw_fw_ui_tabs.prototype.setCloseable = function(_closeable)
{
	for (var i = 0; i < this.tabs.length; i++)
	{
		this.tabs[i].setCloseable(_closeable);
	}
};

/**
 * Clears all data, removes all tabs, independently from the question, whether they may be closed or
 * not.
 */
egw_fw_ui_tabs.prototype.clean = function()
{
	//Remove all tabs, clean the tabs array
	for (var i = 0; i < this.tabs.length; i++)
	{
		array_remove(this.tabs, i);
	}

	//Reset all arrays and references
	this.tabs = new Array();
	this.activeTab = null;
	this.tabHistroy = new Array();

	return true;
};


/**
 * Class: egw_fw_ui_category
 * A class which manages and renderes a simple menu with categories, which can be opened and shown
 *
 * @param {object} _contDiv
 * @param {string} _name
 * @param {string} _title
 * @param {object} _content
 * @param {function} _callback
 * @param {function} _animationCallback
 * @param {object} _tag
 */

function egw_fw_ui_category(_contDiv, _name, _title, _content, _callback, _animationCallback, _tag)
{
	//Copy the parameters
	this.contDiv = _contDiv;
	this.catName = _name;
	this.callback = _callback;
	this.animationCallback = _animationCallback;
	this.tag = _tag;

	//Create the ui divs
	this.headerDiv = document.createElement('div');
	$j(this.headerDiv).addClass('egw_fw_ui_category');

	//Add the text
	var entryH1 = document.createElement('h1');
	$j(entryH1).append(_title);
	$j(this.headerDiv).append(entryH1);

	//Add the content
	this.contentDiv = document.createElement('div');
	this.contentDiv._parent = this;
	$j(this.contentDiv).addClass('egw_fw_ui_category_content');
	$j(this.contentDiv).append(_content);
	$j(this.contentDiv).hide();

	//Add content and header to the content div, add some magic jQuery code in order to make it foldable
	this.headerDiv._parent = this;
	$j(this.headerDiv).click(
		function() {
			if (!$j(this).hasClass('egw_fw_ui_category_active'))
			{
				this._parent.open(false);
			}
			else
			{
				this._parent.close(false);
			}
		});
	$j(this.contDiv).append(this.headerDiv);
	$j(this.contDiv).append(this.contentDiv);
}

egw_fw_ui_category.prototype.open = function(_instantly)
{
	this.callback.call(this, true);
	$j(this.headerDiv).addClass('egw_fw_ui_category_active');

	if (_instantly)
	{
		$j(this.contentDiv).show();
		this.animationCallback();
	}
	else
	{
		$j(this.contentDiv).slideDown(200, function() {
			this._parent.animationCallback.call(this._parent);
		});
	}
};

egw_fw_ui_category.prototype.close = function(_instantly)
{
	this.callback.call(this, false);
	$j(this.headerDiv).removeClass('egw_fw_ui_category_active');

	if (_instantly)
	{
		$j(this.contentDiv).hide();
		this.animationCallback();
	}
	else
	{
		$j(this.contentDiv).slideUp(200, function() {
			this._parent.animationCallback.call(this._parent);
		});
	}
};

egw_fw_ui_category.prototype.remove = function()
{
	//Delete the content and header div
	$j(this.contDiv).remove();
	$j(this.headerDiv).remove();
};

/**
 * egw_fw_ui_scrollarea class
 *
 * @param {object} _contDiv
 */

function egw_fw_ui_scrollarea(_contDiv)
{
	this.startScrollSpeed = 50.0; //in px/sec
	this.endScrollSpeed = 250.0; //in px/sec
	this.scrollSpeedAccel = 75.0; //in px/sec^2
	this.timerInterval = 0.04; //in seconds  //20ms is the timer base timer resolution on windows systems

	this.contDiv = _contDiv;
	this.contHeight = 0;
	this.boxHeight = 0;
	this.scrollPos = 0;
	this.buttonScrollOffs = 0;
	this.maxScrollPos = 0;
	this.buttonsVisible = true;
	this.mouseOver = false;
	this.scrollTime = 0.0;
	this.btnUpEnabled = true;
	this.btnDownEnabled = true;

	//Wrap a new "scroll" div around the content of the content div
	this.scrollDiv = document.createElement("div");
	this.scrollDiv.style.position = "relative";
	$j(this.scrollDiv).addClass("egw_fw_ui_scrollarea");

	//Mousewheel handler
	var self = this;
	$j(this.scrollDiv).on('mousewheel',function(e, delta) {
		var noscroll = false;

		// Do not scrolldown/up when we are on selectbox items
		// seems Firefox does not prevent the mousewheel event over
		// selectbox items with scrollbars
		// Do not scroll on video tutorials as well
		if (e.target.tagName == "OPTION" || e.target.tagName == "SELECT" ||
				e.target.getAttribute('class') && e.target.getAttribute('class').match(/egw_tutorial/ig))
		{
			noscroll = true;
		}
		if (delta && !noscroll)
		{
			e.stopPropagation();
			self.scrollDelta(- delta * 30);
			if (self.contHeight != this.scrollHeight) self.update();
		}

	});

	//Create a container which contains the up/down buttons and the scrollDiv
	this.outerDiv = document.createElement("div");
	$j(this.outerDiv).addClass("egw_fw_ui_scrollarea_outerdiv");
	$j(this.outerDiv).append(this.scrollDiv);

	$j(this.contDiv).children().appendTo(this.scrollDiv);
	$j(this.contDiv).append(this.outerDiv);
	this.contentDiv = this.scrollDiv;

	//Create the "up" and the "down" button
	this.btnUp = document.createElement("span");
	$j(this.btnUp).addClass("egw_fw_ui_scrollarea_button");
	$j(this.btnUp).addClass("egw_fw_ui_scrollarea_button_up");
	$j(this.btnUp).hide();

	this.btnUp._parent = this;
	$j(this.btnUp).mouseenter(function(){
		this._parent.mouseOverToggle(true, -1);
		$j(this).addClass("egw_fw_ui_scrollarea_button_hover");
	});
	$j(this.btnUp).click(function(){
		this._parent.setScrollPos(0);
	});
	$j(this.btnUp).mouseleave(function(){
		this._parent.mouseOverToggle(false, -1);
		$j(this).removeClass("egw_fw_ui_scrollarea_button_hover");
	});

	$j(this.outerDiv).prepend(this.btnUp);

	this.btnDown = document.createElement("span");
	$j(this.btnDown).addClass("egw_fw_ui_scrollarea_button");
	$j(this.btnDown).addClass("egw_fw_ui_scrollarea_button_down");
	$j(this.btnDown).hide();

	this.btnDown._parent = this;
	$j(this.btnDown).mouseenter(function(){
		this._parent.mouseOverToggle(true, 1);
		$j(this).addClass("egw_fw_ui_scrollarea_button_hover");
	});
	$j(this.btnDown).click(function() {
		this._parent.setScrollPos(this._parent.maxScrollPos);
	});
	$j(this.btnDown).mouseleave(function(){
		this._parent.mouseOverToggle(false, 1);
		$j(this).removeClass("egw_fw_ui_scrollarea_button_hover");
	});

	$j(this.outerDiv).prepend(this.btnDown);

	//Update - read height of the children elements etc.
	this.update();
}

egw_fw_ui_scrollarea.prototype.setScrollPos = function(_pos)
{
	if (this.buttonsVisible)
	{
		if (_pos <= 0)
		{
			if (this.btnUpEnabled)
				$j(this.btnUp).addClass("egw_fw_ui_scrollarea_button_disabled");
			if (!this.btnDownEnabled)
				$j(this.btnDown).removeClass("egw_fw_ui_scrollarea_button_disabled");
			this.btnDownEnabled = true;
			this.btnUpEnabled = false;

			_pos = 0;
		}
		else if (_pos >= this.maxScrollPos)
		{
			if (this.btnDownEnabled)
				$j(this.btnDown).addClass("egw_fw_ui_scrollarea_button_disabled");
			if (!this.btnUpEnabled)
				$j(this.btnUp).removeClass("egw_fw_ui_scrollarea_button_disabled");
			this.btnDownEnabled = false;
			this.btnUpEnabled = true;

			_pos = this.maxScrollPos;
		}
		else
		{
			if (!this.btnUpEnabled)
				$j(this.btnUp).removeClass("egw_fw_ui_scrollarea_button_disabled");
			if (!this.btnDownEnabled)
				$j(this.btnDown).removeClass("egw_fw_ui_scrollarea_button_disabled");
			this.btnUpEnabled = true;
			this.btnDownEnabled = true;
		}

		this.scrollPos = _pos;

		//Apply the calculated scroll position to the scrollDiv
		this.scrollDiv.style.top = Math.round(-_pos) + 'px';
	}
};

egw_fw_ui_scrollarea.prototype.scrollDelta = function(_delta)
{
	this.setScrollPos(this.scrollPos + _delta);
};

egw_fw_ui_scrollarea.prototype.toggleButtons = function(_visible)
{
	if (_visible)
	{
		$j(this.btnDown).show();
		$j(this.btnUp).show();
		this.buttonHeight = $j(this.btnDown).outerHeight();
		this.maxScrollPos = this.contHeight - this.boxHeight;
		this.setScrollPos(this.scrollPos);
	}
	else
	{
		this.scrollDiv.style.top = '0';
		$j(this.btnDown).hide();
		$j(this.btnUp).hide();
	}

	this.buttonsVisible = _visible;
};

egw_fw_ui_scrollarea.prototype.update = function()
{
	//Get the height of the content and the outer box
	this.contHeight = $j(this.scrollDiv).outerHeight();
	this.boxHeight = $j(this.contDiv).height();

	this.toggleButtons(this.contHeight > this.boxHeight);
	this.setScrollPos(this.scrollPos);
};

egw_fw_ui_scrollarea.prototype.getScrollDelta = function(_timeGap)
{
	//Calculate the current scroll speed
	var curScrollSpeed = this.startScrollSpeed + this.scrollSpeedAccel * this.scrollTime;
	if (curScrollSpeed > this.endScrollSpeed)
	{
		curScrollSpeed = this.endScrollSpeed;
	}

	//Increment the scroll time counter
	this.scrollTime = this.scrollTime + _timeGap;

	//Return the actual delta value
	return curScrollSpeed * _timeGap;
};

egw_fw_ui_scrollarea.prototype.mouseOverCallback = function(_context)
{
	//Do the scrolling
	_context.scrollDelta(_context.getScrollDelta(_context.timerInterval) *
		_context.dir);

	if (_context.mouseOver)
	{
		//Set the next timeout
		setTimeout(function(){_context.mouseOverCallback(_context);},
			Math.round(_context.timerInterval * 1000));
	}
};

egw_fw_ui_scrollarea.prototype.mouseOverToggle = function(_over, _dir)
{
	this.mouseOver = _over;
	this.dir = _dir;
	this.update();
	if (_over)
	{
		var _context = this;
		setTimeout(function(){_context.mouseOverCallback(_context);},
			Math.round(_context.timerInterval * 1000));
	}
	else
	{
		this.scrollTime = 0.0;
	}
};

/**
 * egw_fw_ui_splitter class
 */

var EGW_SPLITTER_HORIZONTAL = 0;
var EGW_SPLITTER_VERTICAL = 1;

function egw_fw_ui_splitter(_contDiv, _orientation, _resizeCallback, _constraints, _tag)
{
	//Copy the parameters
	this.tag = _tag;
	this.contDiv = _contDiv;
	this.orientation = _orientation;
	this.resizeCallback = _resizeCallback;
	this.startPos = 0;
	this.constraints =
	[
		{
			"size": 0,
			"minsize": 0,
			"maxsize": 0
		},
		{
			"size": 0,
			"minsize": 0,
			"maxsize": 0
		}
	];

	//Copy the given constraints parameter, keeping the default values set above
	if (_constraints.constructor == Array)
	{
		for (var i = 0; i < 2; i++)
		{
			if (typeof _constraints[i] != 'undefined')
			{
				if (typeof _constraints[i].size != 'undefined')
					this.constraints[i].size = _constraints[i].size;
				if (typeof _constraints[i].minsize != 'undefined')
					this.constraints[i].minsize = _constraints[i].minsize;
				if (typeof _constraints[i].maxsize != 'undefined')
					this.constraints[i].maxsize = _constraints[i].maxsize;
			}
		}
	}

	//Create the actual splitter div
	this.splitterDiv = document.createElement('div');
	this.splitterDiv._parent = this;
	$j(this.splitterDiv).addClass("egw_fw_ui_splitter");

	//Setup the options for the dragable object
	var dragoptions = {
		opacity: 0.7,
		helper: 'clone',
		start: function(event, ui) {
			return this._parent.dragStartHandler.call(this._parent, event, ui);
		},
		drag: function(event, ui) {
			return this._parent.dragHandler.call(this._parent, event, ui);
		},
		stop: function(event, ui) {
			return this._parent.dragStopHandler.call(this._parent, event, ui);
		},
		containment: 'document',
		appendTo: 'body',
		axis: 'y',
		iframeFix: true,
		zIndex: 10000
	};

	switch (this.orientation)
	{
		case EGW_SPLITTER_HORIZONTAL:
			dragoptions.axis = 'y';
			$j(this.splitterDiv).addClass("egw_fw_ui_splitter_horizontal");
			break;
		case EGW_SPLITTER_VERTICAL:
			dragoptions.axis = 'x';
			$j(this.splitterDiv).addClass("egw_fw_ui_splitter_vertical");
			break;
	}
	$j(this.splitterDiv).draggable(dragoptions);

	//Handle mouse hovering of the splitter div
	$j(this.splitterDiv).mouseenter(function() {
		$j(this).addClass("egw_fw_ui_splitter_hover");
	});
	$j(this.splitterDiv).mouseleave(function() {
		$j(this).removeClass("egw_fw_ui_splitter_hover");
	});

	$j(this.contDiv).append(this.splitterDiv);
}

egw_fw_ui_splitter.prototype.clipDelta = function(_delta)
{
	var result = _delta;

	for (var i = 0; i < 2; i++)
	{
		var mul = (i == 0) ? 1 : -1;

		if (this.constraints[i].maxsize > 0)
		{
			var size = this.constraints[i].size + mul * result;
			if (size > this.constraints[i].maxsize)
				result += mul * (this.constraints[i].maxsize - size);
		}

		if (this.constraints[i].minsize > 0)
		{
			var size = this.constraints[i].size + mul * result;
			if (size < this.constraints[i].minsize)
				result += mul * (this.constraints[i].minsize - size);
		}
	}

	return result;
};

egw_fw_ui_splitter.prototype.dragStartHandler = function(event, ui)
{
	switch (this.orientation)
	{
		case EGW_SPLITTER_HORIZONTAL:
			this.startPos = ui.offset.top;
			break;
		case EGW_SPLITTER_VERTICAL:
			this.startPos = ui.offset.left;
			break;
	}
};

egw_fw_ui_splitter.prototype.dragHandler = function(event, ui)
{
/*	var delta = 0;
	switch (this.orientation)
	{
		case EGW_SPLITTER_HORIZONTAL:
			var old = ui.offset.top - this.startPos;
			clipped = this.clipDelta(old);
			$j(this.splitterDiv).data('draggable').offset.click.top += (old - clipped);
			break;
		case EGW_SPLITTER_VERTICAL:
			var old = ui.offset.left - this.startPos;
			clipped = this.clipDelta(old);
			$j(this.splitterDiv).data('draggable').offset.click.left += (old - clipped);
			break;
	}*/
};


egw_fw_ui_splitter.prototype.dragStopHandler = function(event, ui)
{
	var delta = 0;
	switch (this.orientation)
	{
		case EGW_SPLITTER_HORIZONTAL:
			delta = ui.offset.top - this.startPos;
			break;
		case EGW_SPLITTER_VERTICAL:
			delta = ui.offset.left - this.startPos;
			break;
	}

	//Clip the delta value
	delta = this.clipDelta(delta);

	this.constraints[0].size += delta;
	this.constraints[1].size -= delta;

	this.resizeCallback(this.constraints[0].size, this.constraints[1].size);
};

/**
 * Disable/Enable drabbale splitter
 * @param {type} _state
 */
egw_fw_ui_splitter.prototype.set_disable = function (_state)
{
	$j(this.splitterDiv).draggable(_state?'disable':'enable');
};

/**
 * Constructor for toggleSidebar UI object
 *
 * @param {type} _contentDiv sidemenu div
 * @param {function} _toggleCallback callback function to set toggle prefernces and resize handling
 * @param {object} _callbackContext context of the toggleCallback
 * @returns {egw_fw_ui_toggleSidebar}
 */
function egw_fw_ui_toggleSidebar (_contentDiv, _toggleCallback, _callbackContext)
{
	var self = this;
	this.toggleCallback = _toggleCallback;
	this.toggleDiv = $j(document.createElement('div'))
			.attr({id:"egw_fw_toggler"})
			.click(function(){
				self.onToggle(_callbackContext);
			});
	var span = $j(document.createElement('span')).addClass('et2_clickable').appendTo(this.toggleDiv);

	if (egw.preference('audio_effect', 'common') === "1")
	{
		this.toggleAudio = $j(document.createElement('audio'))
				.attr({src:"data:audio/mp3;base64,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AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAP/71gBgAAAAAEsAAAAAAAAJYAAAAAAAASwAAAAAAAAlgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/+9YAYAAAAABLAAAAAAAACWAAAAAAAAEsAAAAAAAAJYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//vWAGAAAAAASwAAAAAAAAlgAAAAAAABLAAAAAAAACWAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAP/71gBgAAAAAEsAAAAAAAAJYAAAAAAAASwAAAAAAAAlgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/+9YAYAAAAABLAAAAAAAACWAAAAAAAAEsAAAAAAAAJYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//vWAGAAAAAASwAAAAAAAAlgAAAAAAABLAAAAAAAACWAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAP/71gBgAAAAAEsAAAAAAAAJYAAAAAAAASwAAAAAAAAlgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/+9YAYAAAAABLAAAAAAAACWAAAAAAAAEsAAAAAAAAJYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//vWAGAAAAAASwAAAAAAAAlgAAAAAAABLAAAAAAAACWAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAP/71gBgAAAAAEsAAAAAAAAJYAAAAAAAASwAAAAAAAAlgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAAgACAAIAA="})
				.appendTo(this.toggleDiv);
	}
	this.contDiv = $j(_contentDiv);
	this.contDiv.prepend(this.toggleDiv);
};

/**
 * Toggle menu on/off
 * @param {object} _callbackContext context of the toggleCallback
 */
egw_fw_ui_toggleSidebar.prototype.onToggle = function(_callbackContext)
{
	if (typeof this.toggleAudio != 'undefined') this.toggleAudio[0].play();
	if (this.contDiv.hasClass('egw_fw_sidebar_toggleOn'))
	{
		this.contDiv.removeClass('egw_fw_sidebar_toggleOn');
		var splitter = _callbackContext.splitterUi;
		splitter.set_disable(false);
		this.toggleCallback.call(_callbackContext,'off');
		window.setTimeout(function() {
			$j(window).resize();
		},500);
	}
	else
	{
		this.contDiv.addClass('egw_fw_sidebar_toggleOn');
		_callbackContext.splitterUi.set_disable(true);
		this.toggleCallback.call(_callbackContext, 'on');
	}
};

/**
 * Set sidebar toggle state
 *
 * @param {string} _state state can be 'on' or 'off'
 * @param {type} _toggleCallback callback function to handle toggle preference and resize
 * @param {type} _context context of callback function
 */
egw_fw_ui_toggleSidebar.prototype.set_toggle = function (_state, _toggleCallback, _context)
{
	this.contDiv.toggleClass('egw_fw_sidebar_toggleOn',_state === 'on'?true:false);
	_context.splitterUi.set_disable(_state === 'on'?true:false);
	_toggleCallback.call(_context, _state);
};
