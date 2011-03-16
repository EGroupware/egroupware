/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

if (typeof window._egwActionClasses == "undefined")
	window._egwActionClasses = {}
_egwActionClasses["popup"] = {
	"actionConstructor": egwPopupAction,
	"implementation": getPopupImplementation
}

function egwPopupAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
{
	var action = new egwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

	action.type = "popup";
	action["default"] = false;
	action.order = 0;
	action.group = 0;

	action.set_default = function(_value) {
		action["default"] = _value;
	}

	action.set_order = function(_value) {
		action.order = _value;
	}

	action.set_group = function(_value) {
		action.group = _value;
	}

	return action;
}

var
	_popupActionImpl = null;

function getPopupImplementation()
{
	if (!_popupActionImpl)
	{
		_popupActionImpl = new egwPopupActionImplementation();
	}
	return _popupActionImpl
}

function egwPopupActionImplementation()
{
	var ai = new egwActionImplementation();

	ai.type = "popup";

	ai.doRegisterAction = function(_aoi, _callback, _context)
	{
		var node = _aoi.getDOMNode();

		if (node)
		{
			node.ondblclick = function(e) {
				if (typeof document.selection != "undefined" && typeof document.selection.empty != "undefined")
				{
					document.selection.empty();
				}
				else if( typeof window.getSelection != "undefined")
				{
					var sel = window.getSelection();
					sel.removeAllRanges();
				}

				_callback.call(_context, "default", ai);

				return false;
			}

			node.oncontextmenu = function(e) {
				//Obtain the event object
				if (!e)
				{
					e = window.event;
				}

				if (_egw_active_menu)
				{
					_egw_active_menu.hide()
				}
				else
				{
					_xy = ai._getPageXY(e);
					_callback.call(_context, _xy, ai);
				}

				e.cancelBubble = true;
				if (e.stopPropagation)
				{
					e.stopPropagation();
				}
				return false;
			}

			return true;
		}
		return false;
	}

	ai.doUnregisterAction = function(_aoi)
	{
		//
	}


	/**
	 * Builds the context menu and shows it at the given position/DOM-Node.
	 */
	ai.doExecuteImplementation = function(_context, _selected, _links)
	{
		if (_context != "default")
		{
			//Check whether the context has the posx and posy parameters
			if ((typeof _context.posx != "number" || typeof _context.posy != "number") &&
			    typeof _context.id != "undefined")
			{
				// Calculate context menu position from the given DOM-Node
				var node = _context;

				x = node.offsetLeft;
				y = node.offsetTop;

				_context = {"posx": x, "posy": y}
			}

			var menu = ai._buildMenu(_links, _selected);
			menu.showAt(_context.posx, _context.posy);

			return true;
		}
		else
		{
			var defaultAction = null;
			for (k in _links)
			{
				if (_links[k].actionObj["default"] && _links[k].enabled)
				{
					defaultAction = _links[k].actionObj;
					break;
				}
			}

			if (defaultAction)
			{
				defaultAction.execute(_selected);
			}
		}

		return false;
	}

	/**
	 * Builds the context menu from the given action links
	 */
	ai._buildMenu = function(_links, _selected)
	{
		var menu = new egwMenu();

		//Sort the links in ordered groups
		var link_groups = {};
		for (k in _links)
		{
			// Check whether the link group of the current element already exists,
			// if not, create the group
			var grp = _links[k].actionObj.group;
			if (typeof link_groups[grp] == "undefined")
			{
				link_groups[grp] = [];
			}

			// Insert the element in order
			var inserted = false;
			for (var i = 0; i < link_groups[grp].length; i++)
			{
				var elem = link_groups[grp][i];
				if (elem.actionObj.order > _links[k].actionObj.order)
				{
					inserted = true;
					link_groups[grp].splice(i, 0, _links[k]);
					break;
				}
			}

			// If the object hasn't been inserted, add it to the end of the list
			if (!inserted)
			{
				link_groups[grp].push(_links[k]);
			}
		}

		// Insert the link groups sorted into an array
		var groups = [];
		for (k in link_groups)
			groups.push({"grp": k, "links": link_groups[k]});
		groups.sort(function(a, b) {
			return (a.grp > b.grp) ? 1 : ((a.grp < b.grp) ? -1 : 0);
		});

		for (var i = 0; i < groups.length; i++)
		{
			// Add an seperator after each group
			if (i != 0)
			{
				menu.addItem("", "-");
			}

			// Go through the elements of each group
			for (var j = 0; j < groups[i].links.length; j++)
			{
				var link = groups[i].links[j];
				if (link.visible)
				{
					var item = menu.addItem(link.actionObj.id, link.actionObj.caption,
						link.actionObj.iconUrl);
					item["default"] = link.actionObj["default"];
					item.data = link.actionObj;
					if (link.enabled)
					{
						item.set_onClick(function(elem) {
							elem.data.execute(_selected);
						});
					}
					else
					{
						item.set_enabled(false);
					}
				}
			}
		}

		return menu;
	}

	ai._getPageXY = function getPageXY(event)
	{
		// document.body.scrollTop does not work in IE
		var scrollTop = document.body.scrollTop ? document.body.scrollTop :
			document.documentElement.scrollTop;
		var scrollLeft = document.body.scrollLeft ? document.body.scrollLeft :
			document.documentElement.scrollLeft;

		return {'posx': (event.clientX + scrollLeft), 'posy': (event.clientY + scrollTop)};
	}

	return ai;
}



