/**
 * eGroupWare egw_action framework - JS Menu abstraction
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

//Global variable which is used to store the currently active menu so that it
//may be closed when another menu openes
var _egw_active_menu = null;

/**
 * Internal function which generates a menu item with the given parameters as used
 * in e.g. the egwMenu.addItem function.
 */
//TODO Icons: write PHP GD script which is cabable of generating the menu icons in various states (disabled, highlighted)
function _egwGenMenuItem(_parent, _id, _caption, _iconUrl, _onClick)
{
	//Preset the parameters
	if (typeof _parent == "undefined")
		_parent = null;
	if (typeof _id == "undefined")
		_id = "";
	if (typeof _caption == "undefined")
		_caption = "";
	if (typeof _iconUrl == "undefined")
		_iconUrl = "";
	if (typeof _onClick == "undefined")
		_onClick = null;

	//Create a menu item with no parent (null) and set the given parameters
	var item = new egwMenuItem(_parent, _id);
	item.set_caption(_caption);
	item.set_iconUrl(_iconUrl);
	item.set_onClick(_onClick);

	return item;
}

/**
 * Internal function which parses the given menu tree in _elements and adds the 
 * elements to the given parent.
 */
function _egwGenMenuStructure(_elements, _parent)
{
	var items = [];

	//Go through each object in the elements array
	for (var i = 0; i < _elements.length; i++)
	{
		//Go through each key of the current object
		var obj = _elements[i];
		var item = new egwMenuItem(_parent, null);
		for (key in obj)
		{
			if (key == "children" && obj[key].constructor === Array)
			{
				//Recursively load the children.
				item.children = _egwGenMenuStructure(obj[key], item);
			}
			else
			{
				//Directly set the other keys
				//TODO Sanity neccessary checks here?
				//TODO Implement menu item getters?
				if (key == "id" || key == "caption" || key == "iconUrl" ||
				    key == "checkbox" || key == "checked" || key == "groupIndex" ||
				    key == "enabled" || key == "default" || key == "onClick")
				{
					item['set_' + key](obj[key]);
				}
			}
		}

		items.push(item);
	}

	return items;
}

/**
 * Internal function which searches for the given ID inside an element tree.
 */
function _egwSearchMenuItem(_elements, _id)
{
	for (var i = 0; i < _elements.length; i++)
	{
		if (_elements[i].id === _id)
			return _elements[i];

		var item = _egwSearchMenuItem(_elements[i].children, _id);
		if (item)
			return item;
	}

	return null;
}

/**
 * Internal function which alows to set the onClick handler of multiple menu items
 */
function _egwSetMenuOnClick(_elements, _onClick)
{
	for (var i = 0; i < _elements.length; i++)
	{
		if (_elements[i].onClick === null)
		{
			_elements[i].onClick = _onClick;
		}
		_egwSetMenuOnClick(_elements[i].children, _onClick);
	}
}

/**
 * Constructor for the egwMenu object. The egwMenu object is a abstract representation
 * of a context/popup menu. The actual generation of the menu can by done by so
 * called menu implementations. Those are activated by simply including the JS file
 * of such an implementation.
 *
 * The currently available implementation is the "egwDhtmlxMenu.js" which is based
 * upon the dhtmlxmenu component.
 */
function egwMenu()
{
	//The "items" variable contains all menu items of the menu
	this.children = [];

	//The "instance" variable contains the currently opened instance. There may
	//only be one instance opened at a time.
	this.instance = null;
}

/**
 * The private _checkImpl function checks whether a menu implementation is available.
 *
 * @returns bool whether a menu implemenation is available.
 */
egwMenu.prototype._checkImpl = function()
{
	return typeof egwMenuImpl == 'function';
}

/**
 * The showAtElement function shows the menu at the given screen position in an
 * (hopefully) optimal orientation. There can only be one instance of the menu opened at
 * one time and the menu implementation should care that there is only one menu
 * opened globaly at all.
 *
 * @param int _x is the x position at which the menu will be opened
 * @param int _y is the y position at which the menu will be opened
 * @param bool _force if true, the menu will be reopened at the given position,
 * 	even if it already had been opened. Defaults to false.
 * @returns bool whether the menu had been opened
 */
egwMenu.prototype.showAt = function(_x, _y, _force)
{
	if (typeof _force == "undefined")
		_force = false;

	//Hide any other currently active menu
	if (_egw_active_menu != null)
	{
		if (_egw_active_menu == this && !_force)
		{
			this.hide();
			return false;
		}
		else
		{
			_egw_active_menu.hide();
		}
	}

	if (this.instance == null && this._checkImpl)
	{
		//Obtain a new egwMenuImpl object and pass this instance to it
		this.instance = new egwMenuImpl(this.children);

		_egw_active_menu = this;

		var self = this;
		this.instance.showAt(_x, _y, function() {
			self.instance = null;
			_egw_active_menu = null;
		});
		return true;
	}

	return false;
}

/**
 * Hides the menu if it is currently opened. Otherwise nothing happenes.
 */
egwMenu.prototype.hide = function()
{
	//Reset the currently active menu variable
	if (_egw_active_menu == this)
		_egw_active_menu = null;

	//Check whether an currently opened instance exists. If it does, close it.
	if (this.instance != null)
	{
		this.instance.hide();
		this.instance = null;
	}
}

/**
 * Adds a new menu item to the list and returns a reference to that object.
 *
 * @param string _id is a unique identifier of the menu item. You can use the
 * 	the getItem function to search a specific menu item inside the menu tree. The
 * 	id may also be false, null or "", which makes sense for items like seperators,
 * 	which you don't want to access anymore after adding them to the menu tree.
 * @param string _caption is the caption of the newly generated menu item. Set the caption
 * 	to "-" in order to create a sperator.
 * @param string _iconUrl is the URL of the icon which should be prepended to the
 * 	menu item. It may be false, null or "" if you don't want a icon to be displayed.
 * @param function _onClick is the JS function which is being executed when the
 * 	menu item is clicked.
 * @returns egwMenuItem the newly generated menu item, which had been appended to the
 * 	menu item list.
 */
egwMenu.prototype.addItem = function(_id, _caption, _iconUrl, _onClick)
{
	//Append the item to the list
	var item = _egwGenMenuItem(this, _id, _caption, _iconUrl, _onClick);
	this.children.push(item);

	return item;
}

/**
 * Removes all elements fromt the menu structure.
 */
egwMenu.prototype.clear = function()
{
	this.children = [];
}

/**
 * Loads the menu structure from the given object tree. The object tree is an array
 * of objects which may contain a subset of the menu item properties. The "children"
 * property of such an object is interpreted as a new sub-menu tree and appended
 * to that child.
 *
 * @param array _elements is a array of elements which should be added to the menu
 */
egwMenu.prototype.loadStructure = function(_elements)
{
	this.children = _egwGenMenuStructure(_elements, this);
}

/**
 * Searches for the given item id within the element tree.
 */
egwMenu.prototype.getItem = function(_id)
{
	return _egwSearchMenuItem(this.children, _id);
}

/**
 * Applies the given onClick handler to all menu items which don't have a clicked
 * handler assigned yet.
 */
egwMenu.prototype.setGlobalOnClick = function(_onClick)
{
	_egwSetMenuOnClick(this.children, _onClick);
}

/**
 * Constructor for the egwMenuItem. Each entry in a menu (including seperators)
 * is represented by a menu item.
 */
function egwMenuItem(_parent, _id)
{
	this.id = _id;
	this.caption = "";
	this.checkbox = false;
	this.checked = false;
	this.groupIndex = 0;
	this.enabled = true;
	this.iconUrl = "";
	this.onClick = null;
	this["default"] = false;
	this.data = null;

	this.children = [];
	this.parent = _parent;
}

/**
 * Searches for the given item id within the element tree.
 */
egwMenuItem.prototype.getItem = function(_id)
{
	if (this.id === _id)
		return this;

	return _egwSearchMenuItem(this.children, _id);
}

/**
 * Applies the given onClick handler to all menu items which don't have a clicked
 * handler assigned yet.
 */
egwMenuItem.prototype.setGlobalOnClick = function(_onClick)
{
	this.onClick = _onClick;
	_egwSetMenuOnClick(this.children, _onClick);
}

/**
 * Adds a new menu item to the list and returns a reference to that object.
 *
 * @param string _id is a unique identifier of the menu item. You can use the
 * 	the getItem function to search a specific menu item inside the menu tree. The
 * 	id may also be false, null or "", which makes sense for items like seperators,
 * 	which you don't want to access anymore after adding them to the menu tree.
 * @param string _caption is the caption of the newly generated menu item. Set the caption
 * 	to "-" in order to create a sperator.
 * @param string _iconUrl is the URL of the icon which should be prepended to the
 * 	menu item. It may be false, null or "" if you don't want a icon to be displayed.
 * @param function _onClick is the JS function which is being executed when the
 * 	menu item is clicked.
 * @returns egwMenuItem the newly generated menu item, which had been appended to the
 * 	menu item list.
 */
egwMenuItem.prototype.addItem = function(_id, _caption, _iconUrl, _onClick)
{
	//Append the item to the list
	var item = _egwGenMenuItem(this, _id, _caption, _iconUrl, _onClick);
	this.children.push(item);

	return item;
}


//Setter functions for the menuitem properties

egwMenuItem.prototype.set_id = function(_value)
{
	this.id = _value;
}

egwMenuItem.prototype.set_caption = function(_value)
{
	//A value of "-" means that this element is a seperator.
	this.caption = _value;
}

egwMenuItem.prototype.set_checkbox = function(_value)
{
	this.checkbox = _value;
}

egwMenuItem.prototype.set_checked = function(_value)
{
	if (_value && this.groupIndex > 0)
	{
		//Uncheck all other elements in this radio group
		for (var i = 0; i < this.parent.children.length; i++)
		{
			var obj = this.parent.children[i];
			if (obj.groupIndex == this.groupIndex)
				obj.checked = false;
		}
	}
	this.checked = _value;
}

egwMenuItem.prototype.set_groupIndex = function(_value)
{
	//If groupIndex is greater than 0 and the element is a checkbox, it is
	//treated like a radio box
	this.groupIndex = _value;
}

egwMenuItem.prototype.set_enabled = function(_value)
{
	this.enabled = _value;
}

egwMenuItem.prototype.set_onClick = function(_value)
{
	this.onClick = _value;
}

egwMenuItem.prototype.set_iconUrl = function(_value)
{
	this.iconUrl = _value;
}

egwMenuItem.prototype.set_default = function(_value)
{
	this["default"] = _value;
}

egwMenuItem.prototype.set_data = function(_value)
{
	this.data = _value;
}

egwMenuItem.prototype.set_hint = function(_value)
{
	this.hint = _value;
}

