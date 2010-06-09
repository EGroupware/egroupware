/**
 * eGroupware JavaScript Framework - Ui
 *
 * This javascript file contains all classes of the eGroupware JavaScript Framework
 * which represent UI elements.
 *
 * @link http://www.egroupware.org
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */


/**
 * Class: egw_fw_ui_sidemenu_entry
 * The egw_fw_ui_sidemenu_entry class represents an entry in the application sidemenu
 */


/**
 * The constructor of the egw_fw_ui_sidemenu_entry class.
 *
 * @param object _parent specifies the parent egw_fw_ui_sidemenu
 * @param object _baseDiv specifies "div" element the entries should be appended to.
 * @param string _name specifies the title of the entry in the side menu
 * @param string _icon specifies the icon which should be viewd besides the title in the side menu
 * @param function(_sender) _callback specifies the function which should be called when the entry is clicked. The _sender parameter passed is a reference to this egw_fw_ui_sidemenu_entry element.
 * @param object _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 */
function egw_fw_ui_sidemenu_entry(_parent, _baseDiv, _name, _icon, _callback, _tag)
{
	this.baseDiv = _baseDiv;
	this.entryName = _name;
	this.icon = _icon;
	this.tag = _tag;
	this.parent = _parent;
	this.atTop = false;

	//Add a new div for the new entry to the base div
	this.headerDiv = document.createElement("div");
	$(this.headerDiv).addClass("egw_fw_ui_sidemenu_entry_header");

	//Create the icon and set its image
	var iconDiv = document.createElement("img");
	iconDiv.src = this.icon;
	iconDiv.alt = _name;
	$(iconDiv).addClass("egw_fw_ui_sidemenu_entry_icon");
	
	//Create the AJAX loader image (currently NOT used)
	this.ajaxloader = document.createElement("div");
	$(this.ajaxloader).addClass("egw_fw_ui_ajaxloader");
	$(this.ajaxloader).hide();

	//Create the entry name header
	var entryH1 = document.createElement("h1");
	$(entryH1).append(this.entryName);

	//Append icon, name, and ajax loader
	$(this.headerDiv).append(iconDiv);
	$(this.headerDiv).append(entryH1);
	$(this.headerDiv).append(this.ajaxloader);
	this.headerDiv._callbackObject = new egw_fw_class_callback(this, _callback);
	$(this.headerDiv).click(function(){
		this._callbackObject.call(this);
	});

	//Create the content div
	this.contentDiv = document.createElement("div");
	$(this.contentDiv).addClass("egw_fw_ui_sidemenu_entry_content");
	$(this.contentDiv).hide();

	this.setBottomLine(this.parent.entries);

	//Add in invisible marker to store the original position of this element in the DOM tree
	this.marker = document.createElement("div");
	$(this.marker).hide();

	//Append header and content div to the base div
	$(this.baseDiv).append(this.marker);
	$(this.baseDiv).append(this.headerDiv);
	$(this.baseDiv).append(this.contentDiv);
}

/**
 * setBottomLine marks this element as the bottom element in the application list.
 * This adds the egw_fw_ui_sidemenu_entry_content_bottom/egw_fw_ui_sidemenu_entry_header_bottom CSS classes
 * which should care about adding an closing bottom line to the sidemenu. These classes are removed from
 * all other entries in the side menu.
 *
 * @param array _entryList is a reference to the list which contains the sidemenu_entry entries.
 */
egw_fw_ui_sidemenu_entry.prototype.setBottomLine = function(_entryList)
{
	//If this is the last tab in the tab list, the bottom line must be closed
	for (i = 0; i < _entryList.length; i++)
	{
		$(_entryList[i].contentDiv).removeClass("egw_fw_ui_sidemenu_entry_content_bottom");
		$(_entryList[i].headerDiv).removeClass("egw_fw_ui_sidemenu_entry_header_bottom");
	}
	$(this.contentDiv).addClass("egw_fw_ui_sidemenu_entry_content_bottom");
	$(this.headerDiv).addClass("egw_fw_ui_sidemenu_entry_header_bottom");
}

/**
 * setContent replaces the content of the sidemenu entry with the content given by _content.
 *
 * @param string _content HTML/Text which should be displayed.
 */
egw_fw_ui_sidemenu_entry.prototype.setContent = function(_content)
{
	//Set the content of the contentDiv
	$(this.contentDiv).empty();
	$(this.contentDiv).append(_content);
}

/**
 * open openes this sidemenu_entry and displays the content.
 */
egw_fw_ui_sidemenu_entry.prototype.open = function()
{
	/* Move this entry to the top of the list */
	$(this.baseDiv).prepend(this.contentDiv);
	$(this.baseDiv).prepend(this.headerDiv);
	this.atTop = true;

	$(this.headerDiv).addClass("egw_fw_ui_sidemenu_entry_header_active");
	$(this.contentDiv).show();
}

/**
 * close closes this sidemenu_entry and hides the content.
 */
egw_fw_ui_sidemenu_entry.prototype.close = function()
{
	/* Move the content and header div behind the marker again */
	if (this.atTop)
	{
		$(this.marker).after(this.contentDiv);
		$(this.marker).after(this.headerDiv);
		this.atTop = false;
	}

	$(this.headerDiv).removeClass("egw_fw_ui_sidemenu_entry_header_active");
	$(this.contentDiv).hide();
}

/**
 * showAjaxLoader shows the AjaxLoader animation which should be displayed when
 * the content of the sidemenu entry is just being loaded.
 */
egw_fw_ui_sidemenu_entry.prototype.showAjaxLoader = function()
{
	$(this.ajaxloader).show();
}

/**
 * showAjaxLoader hides the AjaxLoader animation
 */
egw_fw_ui_sidemenu_entry.prototype.hideAjaxLoader = function()
{
	$(this.ajaxloader).hide();
}

/**
 * Removes this entry.
 */
egw_fw_ui_sidemenu_entry.prototype.remove = function()
{
	$(this.headerDiv).remove();
	$(this.contentDiv).remove();
}


/**
 * Class: egw_fw_ui_sidemenu_entry
 * The egw_fw_ui_sidemenu_entry class represents the whole application sidemenu
 */


/**
 * The constructor of the egw_fw_ui_sidemenu.
 *
 * @param object _baseDiv specifies the "div" in which all entries added by the addEntry function should be displayed.
 */
function egw_fw_ui_sidemenu(_baseDiv)
{
	this.baseDiv = _baseDiv;
	this.entries = new Array();
}

/**
 * Adds an entry to the sidemenu.
 * 
 * @param string _name specifies the title of the new sidemenu entry
 * @param string _icon specifies the icon displayed aside the title
 * @param function(_sender) _callback specifies the function which should be called when a callback is clicked
 */
egw_fw_ui_sidemenu.prototype.addEntry = function(_name, _icon, _callback, _tag)
{
	//Create a new sidemenu entry and add it to the list
	var entry = new egw_fw_ui_sidemenu_entry(this, this.baseDiv, _name, _icon, _callback, _tag);
	this.entries[this.entries.length] = entry;
	
	return entry;
}

/**
 * Openes the specified entry whilst closing all other entries in the list.
 *
 * @param object _entry specifies the entry which should be opened.
 */
egw_fw_ui_sidemenu.prototype.open = function(_entry)
{
	//Close all other entries
	for (i = 0; i < this.entries.length; i++)
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
}


/**
 * Deletes all sidemenu entries.
 */
egw_fw_ui_sidemenu.prototype.clean = function()
{
	for (i = 0; i < this.entries.length; i++)
	{
		this.entries[i].remove();
	}

	this.entries = new Array();
}


/**
 * Class: egw_fw_ui_tab
 * The egw_fw_ui_tab represents a single tab "sheet" in the ui
 */


/**
 * The constructor of the egw_fw_ui_tab class.
 *
 * @param object _parent specifies the parent egw_fw_ui_tabs class
 * @param object _contHeaderDiv specifies the container "div" element, which should contain the headers
 * @param object _contDiv specifies the container "div" element, which should contain the contents of the tabs
 * @param string _icon specifies the icon which should be viewed besides the title of the tab
 * @param function(_sender) _callback specifies the function which should be called when the tab title is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param function(_sender) _closeCallback specifies the function which should be called when the tab close button is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param object _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 */
function egw_fw_ui_tab(_parent, _contHeaderDiv, _contDiv, _icon, _callback,
	_closeCallback,	_tag)
{
	this.parent = _parent;
	this.contHeaderDiv = _contHeaderDiv;
	this.contDiv = _contDiv;
	this.title = '';
	this.tag = _tag;
	this.closeable = true;
	this.callback = _callback;
	this.closeCallback = _closeCallback;
	
	//Create the header div and set its "click" function and "hover" event
	this.headerDiv = document.createElement("span");
	$(this.headerDiv).addClass("egw_fw_ui_tab_header");

	//Create a new callback object and attach it to the header div	
	this.headerDiv._callbackObject = new egw_fw_class_callback(this, _callback);
	$(this.headerDiv).click(
		function(){
			this._callbackObject.call(this);
		});

	//Attach the hover effect to the header div
	$(this.headerDiv).hover(
		function() {
			if (!$(this).hasClass("egw_fw_ui_tab_header_active"))
				$(this).addClass("egw_fw_ui_tab_header_hover");
		},
		function() {http://localhost/egroupware/index.php?menuaction=addressbook.addressbook_ui.index
			$(this).removeClass("egw_fw_ui_tab_header_hover")
		});
		
	//Create the icon and append it to the header div
	var icon = document.createElement("img");
	$(icon).addClass("egw_fw_ui_tab_icon");
	icon.src = _icon;
	icon.alt = 'Tab icon';
	$(this.headerDiv).append(icon);

	//Create the title h1 and append it to the header div
	this.headerH1 = document.createElement("h1");	
	this.setTitle('');
	$(this.headerDiv).append(this.headerH1);

	//Create the close button and append it to the header div
	this.closeButton = document.createElement("span");
	this.closeButton._callbackObject = new egw_fw_class_callback(this, _closeCallback);
	$(this.closeButton).addClass("egw_fw_ui_tab_close_button");
	$(this.closeButton).click(
		function(){
			//Only call the close callback if the tab is set closeable
			if (this._callbackObject.context.closeable)
			{
				this._callbackObject.call(this);
				return false;
			}
			return true;
		});

	//Special treatment for IE	
	if (typeof jQuery.browser['msie'] != 'undefined')
	{
		this.closeButton.style.styleFloat = 'none';
	}

	$(this.headerDiv).append(this.closeButton);
		
	this.contentDiv = document.createElement("div");
	$(this.contentDiv).addClass("egw_fw_ui_tab_content");
	$(this.contentDiv).hide();
	
	$(this.contHeaderDiv).append(this.headerDiv);
	$(this.contDiv).append(this.contentDiv);	
}

/**
 * setTitle sets the title of this tab. An existing title will be removed.
 *
 * @param string _title HTML/Text which should be displayed.
 */
egw_fw_ui_tab.prototype.setTitle = function(_title)
{
	this.title = _title;
	$(this.headerH1).empty;
	$(this.headerH1).append(_title);
}

/**
 * setTitle sets the content of this tab. Existing content is removed.
 *
 * @param string _content HTML/Text which should be displayed.
 */
egw_fw_ui_tab.prototype.setContent = function(_content)
{
	$(this.contentDiv).empty();
	$(this.contentDiv).append(_content);
}

/**
 * Shows the content of the tab. Only one tab should be displayed at once. By using egw_fw_ui_tabs.showTab
 * you can assure this.
 */
egw_fw_ui_tab.prototype.show = function()
{
	$(this.headerDiv).addClass("egw_fw_ui_tab_header_active");
	$(this.contentDiv).show();
}

/**
 * Hides the content of this tab.
 */
egw_fw_ui_tab.prototype.hide = function()
{
	$(this.headerDiv).removeClass("egw_fw_ui_tab_header_active");
	$(this.contentDiv).hide();
}

/**
 * Removes this tab and all its content.
 */
egw_fw_ui_tab.prototype.remove = function()
{
	this.hide();
	$(this.contentDiv).remove();
	$(this.headerDiv).remove();
}

/**
 * Sets whether the close button is shown/the close callback ever gets called.
 * 
 * @param boolean _closeable if true, the close button is shown, if false, the close button is hidden. default is true.
 */
egw_fw_ui_tab.prototype.setCloseable = function(_closeable)
{
	this.closeable = _closeable;
	if (_closeable)
		$(this.closeButton).show();
	else
		$(this.closeButton).hide();
}


/**
 * Class: egw_fw_ui_tabs
 * The egw_fw_ui_tabs class cares about displaying a set of tab sheets.
 */


/**
 * The constructor of the egw_fw_ui_sidemenu_tabs class. Two "divs" are created inside the specified container element, one for the tab headers and one for the tab contents.
 *
 * @param object _contDiv specifies "div" element the tab ui element should be displayed in.
 */
function egw_fw_ui_tabs(_contDiv)
{
	this.contDiv = _contDiv;

	//Create a div for the tab headers
	this.contHeaderDiv = document.createElement("div");
	$(this.contHeaderDiv).addClass("egw_fw_ui_tabs_header");
	$(this.contDiv).append(this.contHeaderDiv);

	this.tabs = Array();
	
	this.activeTab = null;
	this.tabHistory = Array();
}

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
			this.tabHistory.remove(i);
	}
}

/**
 * Adds a new tab to the tabs ui element.
 * @param string _icon which should be displayed on the tab sheet header
 * @param function _callback(_sender) function which should be called whenever the tab header is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param function _closeCallback(_sender) function which should be called whenever the close button of the tab is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param object _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 */
egw_fw_ui_tabs.prototype.addTab = function(_icon, _callback, _closeCallback, _tag)
{
	var tab = new egw_fw_ui_tab(this, this.contHeaderDiv, this.contDiv, _icon, _callback, 
		_closeCallback, _tag);
	this.tabs[this.tabs.length] = tab;
	
	if (this.activeTab == null)
		this.showTab(tab);
	
	return tab;
}

/**
 * Removes the specified tab from the tab list whilst trying to keep one tab open.
 * The tab which will be opened is determined throughout the tab open history.
 *
 * @param object _tab is the object which should be closed.
 */
egw_fw_ui_tabs.prototype.removeTab = function(_tab)
{
	//Delete the deleted tab from the history
	for (var i = this.tabHistory.length - 1; i >= 0; i--)
	{
		if (this.tabHistory[i] == _tab)
			this.tabHistory.remove(i);
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
			//if not, simply show the first tab in the list.
			var tab = this.tabs[0];
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
			this.tabs.remove(i);
	}
}

/**
 * Shows the specified _tab whilst closing all others.
 *
 * @param object _tab is the object which should be opened.
 */
egw_fw_ui_tabs.prototype.showTab = function(_tab)
{
	if (this.activeTab != _tab)
	{
		for (i = 0; i < this.tabs.length; i++)
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
			this.tabHistory.remove(0);
		}
	}
}

/**
 * Calls the setCloseable function of all tabs in the list.
 */
egw_fw_ui_tabs.prototype.setCloseable = function(_closeable)
{	
	for (i = 0; i < this.tabs.length; i++)
	{
		this.tabs[i].setCloseable(_closeable);
	}
}

/**
 * Clears all data, removes all tabs, independently from the question, whether they may be closed or
 * not.
 */
egw_fw_ui_tabs.prototype.clean = function()
{
	//Remove all tabs, clean the tabs array
	for (i = 0; i < this.tabs.length; i++)
	{
		this.tabs[i].remove();
	}

	//Reset all arrays and references
	this.tabs = new Array();
	this.activeTab = null;
	this.tabHistroy = new Array();

	return true;
}


/**
 * Class: egw_fw_ui_category
 * A class which manages and renderes a simple menu with categories, which can be opened and shown
 */


function egw_fw_ui_category(_contDiv, _name, _title, _content, _callback, _tag)
{
	//Copy the parameters
	this.contDiv = _contDiv;
	this.catName = _name;
	this.callback = _callback;
	this.tag = _tag;

	//Create the ui divs
	this.headerDiv = document.createElement('div');
	$(this.headerDiv).addClass('egw_fw_ui_category');
	
	//Add the text	
	var entryH1 = document.createElement('h1');
	$(entryH1).append(_title);
	$(this.headerDiv).append(entryH1);

	//Add the content
	this.contentDiv = document.createElement('div');
	$(this.contentDiv).addClass('egw_fw_ui_category_content');
	$(this.contentDiv).append(_content);
	$(this.contentDiv).hide();

	//Add content and header to the content div, add some magic jQuery code in order to make it foldable
	this.headerDiv._parent = this;
	$(this.headerDiv).click(
		function() {
			if (!$(this).hasClass('egw_fw_ui_category_active'))
			{
				this._parent.open(false);
			}
			else
			{
				this._parent.close(false);
			}
		});
	$(this.contDiv).append(this.headerDiv);
	$(this.contDiv).append(this.contentDiv);
}

egw_fw_ui_category.prototype.open = function(_instantly)
{
	this.callback.call(this, true);
	$(this.headerDiv).addClass('egw_fw_ui_category_active');

	if (_instantly)
	{
		$(this.contentDiv).show();
	}
	else
	{
		$(this.contentDiv).slideDown();
	}
}

egw_fw_ui_category.prototype.close = function(_instantly)
{
	this.callback.call(this, false);
	$(this.headerDiv).removeClass('egw_fw_ui_category_active');

	if (_instantly)
	{
		$(this.contentDiv).hide();
	}
	else
	{
		$(this.contentDiv).slideUp();
	}
}

egw_fw_ui_category.prototype.remove = function()
{
	//Delete the content and header div
	$(this.contDiv).remove();
	$(this.headerDiv).remove();
}

