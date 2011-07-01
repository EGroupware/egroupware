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

//
// jQuery mousewheel extension
//

/*! Copyright (c) 2009 Brandon Aaron (http://brandonaaron.net)
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 * Thanks to: http://adomas.org/javascript-mouse-wheel/ for some pointers.
 * Thanks to: Mathias Bank(http://www.mathias-bank.de) for a scope bug fix.
 *
 * Version: 3.0.2
 * 
 * Requires: 1.2.2+
 */

(function($) {

var types = ['DOMMouseScroll', 'mousewheel'];

$.event.special.mousewheel = {
	setup: function() {
		if ( this.addEventListener )
			for ( var i=types.length; i; )
				this.addEventListener( types[--i], handler, false );
		else
			this.onmousewheel = handler;
	},
	
	teardown: function() {
		if ( this.removeEventListener )
			for ( var i=types.length; i; )
				this.removeEventListener( types[--i], handler, false );
		else
			this.onmousewheel = null;
	}
};

$.fn.extend({
	mousewheel: function(fn) {
		return fn ? this.bind("mousewheel", fn) : this.trigger("mousewheel");
	},
	
	unmousewheel: function(fn) {
		return this.unbind("mousewheel", fn);
	}
});


function handler(event) {
	var args = [].slice.call( arguments, 1 ), delta = 0, returnValue = true;
	
	event = $.event.fix(event || window.event);
	event.type = "mousewheel";
	
	if ( event.wheelDelta ) delta = event.wheelDelta/120;
	if ( event.detail ) delta = -event.detail/3;
	
	// Add events and delta to the front of the arguments
	args.unshift(event, delta);

	return $.event.handle.apply(this, args);
}

})(jQuery);


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
function egw_fw_ui_sidemenu_entry(_parent, _baseDiv, _elemDiv, _name, _icon, _callback,
	_tag)
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
	$(entryH1).text(this.entryName);

	//Append icon, name, and ajax loader
	$(this.headerDiv).append(iconDiv);
	$(this.headerDiv).append(entryH1);
	$(this.headerDiv).append(this.ajaxloader);
	this.headerDiv._parent = this;
	this.headerDiv._callbackObject = new egw_fw_class_callback(this, _callback);
	$(this.headerDiv).click(function(){
		if (!this._parent.isDraged)
		{
			this._callbackObject.call(this);
		}
		this._parent.isDraged = false;
		return true;
	});

	//Create the content div
	this.contentDiv = document.createElement("div");
	$(this.contentDiv).addClass("egw_fw_ui_sidemenu_entry_content");
	$(this.contentDiv).hide();

	this.setBottomLine(this.parent.entries);

	//Add in invisible marker to store the original position of this element in the DOM tree
	this.marker = document.createElement("div");
	this.marker._parent = this;
	this.marker.className = 'egw_fw_ui_sidemenu_marker';
	var entryH1_ = document.createElement("h1");
	$(entryH1_).text(this.entryName);
	$(this.marker).append(entryH1_);
	$(this.marker).hide();

	//Create a container which contains all generated elements and is then added
	//to the baseDiv
	this.containerDiv = document.createElement("div");
	this.containerDiv._parent = this;
	$(this.containerDiv).append(this.marker);
	$(this.containerDiv).append(this.headerDiv);
	$(this.containerDiv).append(this.contentDiv);

	//Append header and content div to the base div
	$(this.elemDiv).append(this.containerDiv);

	//Make the base Div sortable. Set all elements with the style "egw_fw_ui_sidemenu_entry_header"
	//as handle
	$(this.elemDiv).sortable("destroy");
	$(this.elemDiv).sortable({
		handle: ".egw_fw_ui_sidemenu_entry_header",
		distance: 15,
		start: function(event, ui)
		{
			var parent = ui.item.context._parent;
			parent.isDraged = true;
			parent.parent.startDrag.call(parent.parent);
		},
		stop: function(event, ui)
		{
			var parent = ui.item.context._parent;
			parent.parent.stopDrag.call(parent.parent);
			parent.parent.refreshSort.call(parent.parent);
		},
		
		opacity: 0.7,
//		appendTo: 'body',
//		helper: 'clone',
		axis: 'y'
	});
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

/**egw_fw_ui_sidemenu_entry_header_active
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
function egw_fw_ui_sidemenu(_baseDiv, _sortCallback)
{
	this.baseDiv = _baseDiv;
	this.elemDiv = document.createElement('div');
	this.sortCallback = _sortCallback;
	$(this.baseDiv).append(this.elemDiv);
	this.entries = new Array();
	this.activeEntry = null;
}

/**
 * Funtion used internally to recursively step through a dom tree and add all appliction
 * markers in their order of appereance
 */
egw_fw_ui_sidemenu.prototype._searchMarkers = function(_resultArray, _children)
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
}

egw_fw_ui_sidemenu.prototype.startDrag = function()
{
	if (this.activeEntry)
	{
		$(this.activeEntry.marker).show();
		$(this.elemDiv).sortable("refresh");
	}
}

egw_fw_ui_sidemenu.prototype.stopDrag = function()
{
	if (this.activeEntry)
	{
		$(this.activeEntry.marker).hide();
		$(this.elemDiv).sortable("refresh");
	}
}

/**
 * Called by the sidemenu elements whenever they were sorted. An array containing 
 * the sidemenu_entries ui-objects is generated and passed to the sort callback
 */
egw_fw_ui_sidemenu.prototype.refreshSort = function()
{
	//Step through all children of elemDiv and add all markers to the result array
	var resultArray = new Array();
	this._searchMarkers(resultArray, this.elemDiv.childNodes);

	//Call the sort callback with the array containing the sidemenu_entries
	this.sortCallback(resultArray);
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
	var entry = new egw_fw_ui_sidemenu_entry(this, this.baseDiv, this.elemDiv, _name, _icon,
		_callback, _tag);
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

	this.activeEntry = _entry;
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
 * @param int _pos is the position where the tab will be inserted
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
		function() {
			$(this).removeClass("egw_fw_ui_tab_header_hover")
		}
	);
		
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
	else
	{
		$(this.headerDiv).append(this.closeButton);
	}

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

	if (typeof jQuery.browser['msie'] != 'undefined')
	{
		$(this.headerDiv).append(this.closeButton);
	}

	this.contentDiv = document.createElement("div");
	$(this.contentDiv).addClass("egw_fw_ui_tab_content");
	$(this.contentDiv).hide();
	
	//Sort the element in at the given position
	var _this = this;
	var $_children = $(this.contHeaderDiv).children();
	var _cnt = $_children.size();

	if (_cnt > 0 && _pos > -1)
	{
		$_children.each(function(i) {
			if (_pos <= this._position)
			{
				$(this).before(_this.headerDiv);
				return false;
			}
			else if (i == (_cnt - 1))
			{
				$(this).after(_this.headerDiv);
				return false;
			}
		});
	}
	else
	{
		$(this.contHeaderDiv).append(this.headerDiv);
	}

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
	$(this.headerH1).empty();
	$(this.headerH1).text(_title);
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

	this.appHeaderContainer = document.createElement("div");
	$(this.appHeaderContainer).addClass("egw_fw_ui_app_header_container");
	$(this.contDiv).append(this.appHeaderContainer);

	this.appHeader = document.createElement("div");
	$(this.appHeader).addClass("egw_fw_ui_app_header");
	$(this.appHeader).hide();
	$(this.appHeaderContainer).append(this.appHeader);

	this.tabs = Array();
	
	this.activeTab = null;
	this.tabHistory = Array();
}

/**
 * Sets the "appHeader" text below the tabs list.
 *
 * @param string _text is the text which will be seen in the appHeader.
 */
egw_fw_ui_tabs.prototype.setAppHeader = function(_text)
{
	if (_text != "")
	{
		$(this.appHeader).empty();
		$(this.appHeader).append("<span style=\"color:gray \">&raquo;</span> " + _text);
		$(this.appHeader).show();
	}
	else
	{
		$(this.appHeader).hide();
	}
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
		{
			array_remove(this.tabHistory, i);
		}
	}
}

/**
 * Adds a new tab to the tabs ui element.
 * @param string _icon which should be displayed on the tab sheet header
 * @param function _callback(_sender) function which should be called whenever the tab header is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param function _closeCallback(_sender) function which should be called whenever the close button of the tab is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param object _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 * @param int _pos specifies the position in the tab list. If _pos is -1, the tab will be added to the end of the tab list
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
				this.tabs.splice(i, 0, tab)
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
			array_remove(this.tabs, i);
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
			array_remove(this.tabHistory, 0);
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
		array_remove(this.tabs, i);
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
	$(this.headerDiv).addClass('egw_fw_ui_category');
	
	//Add the text	
	var entryH1 = document.createElement('h1');
	$(entryH1).append(_title);
	$(this.headerDiv).append(entryH1);

	//Add the content
	this.contentDiv = document.createElement('div');
	this.contentDiv._parent = this;
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
		this.animationCallback();
	}
	else
	{
		$(this.contentDiv).slideDown(200, function() {
			this._parent.animationCallback.call(this._parent);
		});
	}
}

egw_fw_ui_category.prototype.close = function(_instantly)
{
	this.callback.call(this, false);
	$(this.headerDiv).removeClass('egw_fw_ui_category_active');

	if (_instantly)
	{
		$(this.contentDiv).hide();
		this.animationCallback();
	}
	else
	{
		$(this.contentDiv).slideUp(200, function() {
			this._parent.animationCallback.call(this._parent);
		});
	}
}

egw_fw_ui_category.prototype.remove = function()
{
	//Delete the content and header div
	$(this.contDiv).remove();
	$(this.headerDiv).remove();
}

/**
 * egw_fw_ui_scrollarea class
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
	$(this.scrollDiv).addClass("egw_fw_ui_scrollarea");

	//Mousewheel handler
	var self = this;
	$(this.scrollDiv).mousewheel(function(e, delta) {
		if (delta)
		{
			self.scrollDelta(- delta * 30);
		}
	});

	//Create a container which contains the up/down buttons and the scrollDiv
	this.outerDiv = document.createElement("div");
	$(this.outerDiv).addClass("egw_fw_ui_scrollarea_outerdiv");
	$(this.outerDiv).append(this.scrollDiv);

	$(this.contDiv).children().appendTo(this.scrollDiv);
	$(this.contDiv).append(this.outerDiv);
	this.contentDiv = this.scrollDiv;

	//Create the "up" and the "down" button
	this.btnUp = document.createElement("span");
	$(this.btnUp).addClass("egw_fw_ui_scrollarea_button");
	$(this.btnUp).addClass("egw_fw_ui_scrollarea_button_up");
	$(this.btnUp).hide();

	this.btnUp._parent = this;
	$(this.btnUp).mouseenter(function(){
		this._parent.mouseOverToggle(true, -1);
		$(this).addClass("egw_fw_ui_scrollarea_button_hover");
	});
	$(this.btnUp).click(function(){
		this._parent.setScrollPos(0);
	});
	$(this.btnUp).mouseleave(function(){
		this._parent.mouseOverToggle(false, -1);
		$(this).removeClass("egw_fw_ui_scrollarea_button_hover");
	});

	$(this.outerDiv).prepend(this.btnUp);

	this.btnDown = document.createElement("span");
	$(this.btnDown).addClass("egw_fw_ui_scrollarea_button");
	$(this.btnDown).addClass("egw_fw_ui_scrollarea_button_down");
	$(this.btnDown).hide();

	this.btnDown._parent = this;
	$(this.btnDown).mouseenter(function(){
		this._parent.mouseOverToggle(true, 1);
		$(this).addClass("egw_fw_ui_scrollarea_button_hover");
	});
	$(this.btnDown).click(function() {
		this._parent.setScrollPos(this._parent.maxScrollPos);
	});
	$(this.btnDown).mouseleave(function(){
		this._parent.mouseOverToggle(false, 1);
		$(this).removeClass("egw_fw_ui_scrollarea_button_hover");
	});

	$(this.outerDiv).prepend(this.btnDown);

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
				$(this.btnUp).addClass("egw_fw_ui_scrollarea_button_disabled");
			if (!this.btnDownEnabled)
				$(this.btnDown).removeClass("egw_fw_ui_scrollarea_button_disabled");
			this.btnDownEnabled = true;
			this.btnUpEnabled = false;

			_pos = 0;
		}
		else if (_pos >= this.maxScrollPos)
		{
			if (this.btnDownEnabled)
				$(this.btnDown).addClass("egw_fw_ui_scrollarea_button_disabled");
			if (!this.btnUpEnabled)
				$(this.btnUp).removeClass("egw_fw_ui_scrollarea_button_disabled");
			this.btnDownEnabled = false;
			this.btnUpEnabled = true;

			_pos = this.maxScrollPos;
		}
		else
		{
			if (!this.btnUpEnabled)
				$(this.btnUp).removeClass("egw_fw_ui_scrollarea_button_disabled");
			if (!this.btnDownEnabled)
				$(this.btnDown).removeClass("egw_fw_ui_scrollarea_button_disabled");
			this.btnUpEnabled = true;
			this.btnDownEnabled = true;
		}

		this.scrollPos = _pos;

		//Apply the calculated scroll position to the scrollDiv
		this.scrollDiv.style.top = Math.round(-_pos) + 'px';
	}
}

egw_fw_ui_scrollarea.prototype.scrollDelta = function(_delta)
{
	this.setScrollPos(this.scrollPos + _delta);
}

egw_fw_ui_scrollarea.prototype.toggleButtons = function(_visible)
{
	if (_visible)
	{
		$(this.btnDown).show();
		$(this.btnUp).show();
		this.buttonHeight = $(this.btnDown).outerHeight();
		this.maxScrollPos = this.contHeight - this.boxHeight;
		this.setScrollPos(this.scrollPos);
	}
	else
	{
		this.scrollDiv.style.top = '0';
		$(this.btnDown).hide();
		$(this.btnUp).hide();
	}

	this.buttonsVisible = _visible;
}

egw_fw_ui_scrollarea.prototype.update = function()
{
	//Get the height of the content and the outer box
	this.contHeight = $(this.scrollDiv).outerHeight();
	this.boxHeight = $(this.contDiv).height();

	this.toggleButtons(this.contHeight > this.boxHeight);
	this.setScrollPos(this.scrollPos);
}

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
}

egw_fw_ui_scrollarea.prototype.mouseOverCallback = function(_context)
{
	//Do the scrolling
	_context.scrollDelta(_context.getScrollDelta(_context.timerInterval) * 
		_context.dir);

	if (_context.mouseOver)
	{
		//Set the next timeout
		setTimeout(function(){_context.mouseOverCallback(_context)},
			Math.round(_context.timerInterval * 1000));
	}
}

egw_fw_ui_scrollarea.prototype.mouseOverToggle = function(_over, _dir)
{
	this.mouseOver = _over;
	this.dir = _dir;

	if (_over)
	{
		var _context = this;
		setTimeout(function(){_context.mouseOverCallback(_context)},
			Math.round(_context.timerInterval * 1000));
	}
	else
	{
		this.scrollTime = 0.0;
	}
}

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
	$(this.splitterDiv).addClass("egw_fw_ui_splitter");

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
			$(this.splitterDiv).addClass("egw_fw_ui_splitter_horizontal");
			break;
		case EGW_SPLITTER_VERTICAL:
			dragoptions.axis = 'x';
			$(this.splitterDiv).addClass("egw_fw_ui_splitter_vertical");
			break;
	}
	$(this.splitterDiv).draggable(dragoptions);

	//Handle mouse hovering of the splitter div
	$(this.splitterDiv).mouseenter(function() {
		$(this).addClass("egw_fw_ui_splitter_hover");
	});
	$(this.splitterDiv).mouseleave(function() {
		$(this).removeClass("egw_fw_ui_splitter_hover");
	});

	$(this.contDiv).append(this.splitterDiv);
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
}

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
}

egw_fw_ui_splitter.prototype.dragHandler = function(event, ui)
{
/*	var delta = 0;
	switch (this.orientation)
	{
		case EGW_SPLITTER_HORIZONTAL:
			var old = ui.offset.top - this.startPos;
			clipped = this.clipDelta(old);
			$(this.splitterDiv).data('draggable').offset.click.top += (old - clipped);
			break;
		case EGW_SPLITTER_VERTICAL:
			var old = ui.offset.left - this.startPos;
			clipped = this.clipDelta(old);
			$(this.splitterDiv).data('draggable').offset.click.left += (old - clipped);
			break;
	}*/
}


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
}

