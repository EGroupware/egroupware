/**
 * eGroupWare egw_action framework - JS Menu abstraction
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 *
 */
import {egwMenuImpl} from './egw_menu_dhtmlx';
import {egw_registeredShortcuts, egw_shortcutIdx} from './egw_keymanager';
import {
	EGW_KEY_ARROW_DOWN,
	EGW_KEY_ARROW_LEFT,
	EGW_KEY_ARROW_RIGHT,
	EGW_KEY_ARROW_UP, EGW_KEY_ENTER,
	EGW_KEY_ESCAPE
} from "./egw_action_constants";
//Global variable which is used to store the currently active menu so that it
//may be closed when another menu opens
export var _egw_active_menu: egwMenu = null;

/**
 * Internal function which generates a menu item with the given parameters as used
 * in e.g. the egwMenu.addItem function.
 */
//TODO Icons: write PHP GD script which is capable of generating the menu icons in various states (disabled, highlighted)
function _egwGenMenuItem(_parent = null, _id = "", _caption = "", _iconUrl = "", _onClick = null)
{
	//Create a menu item with no parent (null) and set the given parameters
	const item: egwMenuItem = new egwMenuItem(_parent, _id);
	item.set_caption(_caption);
	item.set_iconUrl(_iconUrl);
	item.set_onClick(_onClick);

	return item;
}

/**
 * Internal function which parses the given menu tree in _elements and adds the
 * elements to the given parent.
 */
function _egwGenMenuStructure(_elements: any[], _parent)
{
	const items: egwMenuItem[] = [];

	//Go through each object in the elements array
	for (const obj of _elements)
	{
		//Go through each key of the current object
		const item = new egwMenuItem(_parent, null);
		for (const key in obj)
		{
			if (key == "children" && obj[key].constructor === Array)
			{
				//Recursively load the children.
				item.children = _egwGenMenuStructure(obj[key], item);
			} else
			{
				//Directly set the other keys
				//TODO Sanity necessary checks here?
				//TODO Implement menu item getters?
				if (key == "id" || key == "caption" || key == "iconUrl" ||
					key == "checkbox" || key == "checked" || key == "groupIndex" ||
					key == "enabled" || key == "default" || key == "onClick" ||
					key == "hint" || key == "shortcutCaption")
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
function _egwSearchMenuItem(_elements: any[], _id: any): egwMenuItem
{
	for (const item1 of _elements)
	{
		if (item1.id === _id)
			return item1;

		const item = _egwSearchMenuItem(item1.children, _id);
		if (item)
			return item;
	}

	return null;
}

/**
 * Internal function which allows to set the onClick handler of multiple menu items
 */
function _egwSetMenuOnClick(_elements, _onClick)
{
	for (const item of _elements)
	{
		if (item.onClick === null)
		{
			item.onClick = _onClick;
		}
		_egwSetMenuOnClick(item.children, _onClick);
	}
}

/**
 * replacement function for jquery trigger
 * @param selector
 * @param eventType
 */
function trigger(selector, eventType)
{
	if (typeof eventType === 'string' && typeof selector[eventType] === 'function')
	{
		selector[eventType]();
	} else
	{
		const event =
			typeof eventType === 'string'
				? new Event(eventType, {bubbles: true})
				: eventType;
		selector.dispatchEvent(event);
	}
}


/**
 * Constructor for the egwMenu object. The egwMenu object is an abstract representation
 * of a context/popup menu. The actual generation of the menu can be done by
 * so-called menu implementations. Those are activated by simply including the JS file
 * of such an implementation.
 *
 * The currently available implementation is the "egwDhtmlxMenu.js" which is based
 * upon the dhtmlxmenu component.
 */
export class egwMenu
{
	//The "items" variable contains all menu items of the menu
	children: egwMenuItem[] = [];

	//The "instance" variable contains the currently opened instance. There may
	//only be one instance opened at a time.
	instance: egwMenuImpl = null; // This is equivalent to iface in other classes and holds an egwMenuImpl
	constructor()
	{
	}

	/**
	 * The private _checkImpl function checks whether a menu implementation is available.
	 *
	 * @returns bool whether a menu implementation is available.
	 */
	private _checkImpl()
	{
		return typeof egwMenuImpl == 'function';
	}

	/**
	 * Hides the menu if it is currently opened. Otherwise, nothing happens.
	 */
	public hide()
	{
		//Reset the currently active menu variable
		if (_egw_active_menu == this)
			_egw_active_menu = null;

		//Check whether a currently opened instance exists. If it does, close it.
		if (this.instance != null)
		{
			this.instance.hide();
			this.instance = null;
		}
	}

	/**
	 * The showAtElement function shows the menu at the given screen position in a
	 * (hopefully) optimal orientation. There can only be one instance of the menu opened at
	 * one time and the menu implementation should care that there is only one menu
	 * opened globally at all.
	 *
	 * @param {number} _x is the x position at which the menu will be opened
	 * @param {number} _y is the y position at which the menu will be opened
	 * @param {boolean} _force if true, the menu will be reopened at the given position,
	 * 	even if it already had been opened. Defaults to false.
	 * @returns {boolean} whether the menu had been opened
	 */
	public showAt(_x: number, _y: number, _force: boolean = false)
	{
		//Hide any other currently active menu
		if (_egw_active_menu != null)
		{
			if (_egw_active_menu == this && !_force)
			{
				this.hide();
				return false;
			} else
			{
				_egw_active_menu.hide();
			}
		}

		if (this.instance == null && this._checkImpl)
		{
			//Obtain a new egwMenuImpl object and pass this instance to it
			this.instance = new egwMenuImpl(this.children);

			_egw_active_menu = this;

			this.instance.showAt(_x, _y, () => {
				this.instance = null;
				_egw_active_menu = null;
			});
			return true;
		}

		return false;
	}

	/**
	 * Key handler to allow keyboard navigation of menu
	 * TODO does this work?
	 *
	 * @return {boolean} true if we dealt with the keypress
	 */
	public keyHandler(_keyCode, _shift, _ctrl, _alt)
	{
		// Let main key-handler deal with shortcuts
		const idx = egw_shortcutIdx(_keyCode, _shift, _ctrl, _alt);
		if (typeof egw_registeredShortcuts[idx] !== "undefined")
		{
			return false;
		}

		//TODO change with shoelace
		let current = this.instance.dhtmlxmenu.menuSelected;
		if (current !== -1)
		{
			let find_func = function (child) {
				if (child.id === current.replace(this.instance.dhtmlxmenu.idPrefix, ""))
				{
					return child;
				} else if (child.children)
				{
					for (let i = 0; i < child.children.length; i++)
					{
						const result = find_func(child.children[i]);
						if (result) return result;
					}
				}
				return null;
			}.bind(this);
			current = find_func(this);
		} else
		{
			current = this.children[0];
			trigger("#" + this.instance.dhtmlxmenu.idPrefix + current.id, "mouseover");
			return true;
		}

		switch (_keyCode)
		{
			case EGW_KEY_ENTER:
				trigger("#" + this.instance.dhtmlxmenu.idPrefix + current.id, "click");
				return true;
			case EGW_KEY_ESCAPE:
				this.hide();
				return true;
			case EGW_KEY_ARROW_RIGHT:
				if (current.children)
				{
					current = current.children[0];
				}
				break;
			case EGW_KEY_ARROW_LEFT:
				if (current.parent && current.parent !== this)
				{
					current = current.parent;
				}
				break;
			case EGW_KEY_ARROW_UP:
			case EGW_KEY_ARROW_DOWN:
				const direction = _keyCode === EGW_KEY_ARROW_DOWN ? 1 : -1;
				let parent = current.parent;
				let index = parent.children.indexOf(current);
				let cont = false;

				// Don't run off ends, skip disabled
				do
				{
					index += direction;
					cont = !parent.children[index] || !parent.children[index].enabled || !parent.children[index].id;
				} while (cont && index + direction < parent.children.length && index + direction >= 0);
				if (index > parent.children.length - 1)
				{
					index = parent.children.length - 1;
				}
				if (index < 0)
				{
					index = 0;
				}
				current = parent.children[index];
				break;
			default:
				return false;
		}

		if (current)
		{
			trigger("#" + this.instance.dhtmlxmenu.idPrefix + current.id, "mouseover");
			this.instance.dhtmlxmenu._redistribSubLevelSelection(this.instance.dhtmlxmenu.idPrefix + current.id, this.instance.dhtmlxmenu.idPrefix + (current.parent ? current.parent.id : this.instance.dhtmlxmenu.topId));
		}
		return true;
	}

	/**
	 * Adds a new menu item to the list and returns a reference to that object.
	 *
	 * @param {string} _id is a unique identifier of the menu item. You can use
	 * 	the getItem function to search a specific menu item inside the menu tree. The
	 * 	id may also be false, null or "", which makes sense for items like separators,
	 * 	which you don't want to access anymore after adding them to the menu tree.
	 * @param {string} _caption is the caption of the newly generated menu item. Set the caption
	 * 	to "-" in order to create a separator.
	 * @param {string} _iconUrl is the URL of the icon which should be prepended to the
	 * 	menu item. It may be false, null or "" if you don't want an icon to be displayed.
	 * @param {function} _onClick is the JS function which is being executed when the
	 * 	menu item is clicked.
	 * @returns {egwMenuItem} the newly generated menu item, which had been appended to the
	 * 	menu item list.
	 */
	public addItem(_id, _caption, _iconUrl, _onClick): egwMenuItem
	{
		//Append the item to the list
		const item: egwMenuItem = new egwMenuItem(this, _id, _caption, _iconUrl, _onClick);
		this.children.push(item);

		return item;
	}

	/**
	 * Removes all elements from the menu structure.
	 */
	public clear()
	{
		this.children = [];
	}

	/**
	 * Loads the menu structure from the given object tree. The object tree is an array
	 * of objects which may contain a subset of the menu item properties. The "children"
	 * property of such an object is interpreted as a new sub-menu tree and appended
	 * to that child.
	 *
	 * @param {array} _elements is an array of elements which should be added to the menu
	 */
	public loadStructure(_elements)
	{
		this.children = _egwGenMenuStructure(_elements, this);
	}

	/**
	 * Searches for the given item id within the element tree.
	 */
	public getItem(_id) {
		return _egwSearchMenuItem(this.children, _id);
	}

	/**
	 * Applies the given onClick handler to all menu items which don't have a clicked
	 * handler assigned yet.
	 */
	setGlobalOnClick(_onClick) {
	_egwSetMenuOnClick(this.children, _onClick);
}
}


/**
 * Constructor for the egwMenuItem. Each entry in a menu (including separators)
 * is represented by a menu item.
 */
export class egwMenuItem
{
	id: string;

	set_id(_value)
	{
		this.id = _value;
	}

	caption = "";

	set_caption(_value)
	{
		//A value of "-" means that this element is a separator.
		this.caption = _value;
	}

	checkbox = false;

	set_checkbox(_value)
	{
		this.checkbox = _value;
	}

	checked = false;

	set_checked(_value)
	{
		if (_value && this.groupIndex > 0)
		{
			//Uncheck all other elements in this radio group
			for (const menuItem of this.parent.children)
			{
				if (menuItem.groupIndex == this.groupIndex)
					menuItem.checked = false;
			}
		}
		this.checked = _value;
	}

	groupIndex = 0;
	enabled = true;
	iconUrl = "";
	onClick = null;
	default = false;
	data = null;
	shortcutCaption = null;

	children = [];
	parent: egwMenu;
	//is set for radio Buttons
	_dhtmlx_grpid: string = "";
	//hint might get set somewhere
	hint: string = "";

	constructor(_parent, _id, _caption="", _iconUrl="", onClick=null)
	{
		this.parent = _parent;
		this.id = _id;
		this.caption = _caption;
		this.iconUrl = _iconUrl;
		this.onClick = onClick;
	}

	/**
	 * Searches for the given item id within the element tree.
	 */
	getItem(_id)
	{
		if (this.id === _id)
			return this;

		return _egwSearchMenuItem(this.children, _id);
	}

	/**
	 * Applies the given onClick handler to all menu items which don't have a clicked
	 * handler assigned yet.
	 */
	setGlobalOnClick(_onClick)
	{
		this.onClick = _onClick;
		_egwSetMenuOnClick(this.children, _onClick);
	}

	/**
	 * Adds a new menu item to the list and returns a reference to that object.
	 *
	 * @param {string} _id is a unique identifier of the menu item. You can use
	 * 	the getItem function to search a specific menu item inside the menu tree. The
	 * 	id may also be false, null or "", which makes sense for items like separators,
	 * 	which you don't want to access anymore after adding them to the menu tree.
	 * @param {string} _caption is the caption of the newly generated menu item. Set the caption
	 * 	to "-" in order to create a separator.
	 * @param {string} _iconUrl is the URL of the icon which should be prepended to the
	 * 	menu item. It may be false, null or "" if you don't want an icon to be displayed.
	 * @param {function} _onClick is the JS function which is being executed when the
	 * 	menu item is clicked.
	 * @returns {egwMenuItem} the newly generated menu item, which had been appended to the
	 * 	menu item list.
	 */
	addItem(_id: string, _caption: string, _iconUrl: string, _onClick: any)
	{
		//Append the item to the list
		const item = _egwGenMenuItem(this, _id, _caption, _iconUrl, _onClick);
		this.children.push(item);

		return item;
	}

	set_groupIndex(_value) {
	//If groupIndex is greater than 0 and the element is a checkbox, it is
	//treated like a radio box
	this.groupIndex = _value;
}

	set_enabled (_value) {
	this.enabled = _value;
}

	set_onClick (_value) {
	this.onClick = _value;
}

	set_iconUrl (_value) {
	this.iconUrl = _value;
}

	set_default (_value) {
	this["default"] = _value;
}

	set_data (_value) {
	this.data = _value;
}

	set_hint (_value) {
	this.hint = _value;
}

	set_shortcutCaption (_value) {
	this.shortcutCaption = _value;
}

}

