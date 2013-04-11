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

/*egw:uses
	egw_action;
	egw_action_common;
	egw_action_popup;
	jquery.jquery;
	jquery.jquery-ui;
*/

/**
 * Register the drag and drop handlers
 */
if (typeof window._egwActionClasses == "undefined")
	window._egwActionClasses = {}
_egwActionClasses["drag"] = {
	"actionConstructor": egwDragAction,
	"implementation": getDragImplementation
}
_egwActionClasses["drop"] = {
	"actionConstructor": egwDropAction,
	"implementation": getDropImplementation
}

/**
 * The egwDragAction class overwrites the egwAction class and adds the new
 * "dragType" propery. The "onExecute" event of the drag action will be called
 * whenever dragging starts. The onExecute JS handler should return the
 * drag-drop helper object - otherwise an default helper will be generated.
 */
function egwDragAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
{
	var action = new egwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

	action.type = "drag";
	action.dragType = "default";
	action.hideOnDisabled = true;

	action.set_dragType = function(_value) {
		action.dragType = _value;
	}

	return action;
}


var
	_dragActionImpl = null;

function getDragImplementation()
{
	if (!_dragActionImpl)
	{
		_dragActionImpl = new egwDragActionImplementation();
	}
	return _dragActionImpl
}

function egwDragActionImplementation()
{
	var ai = new egwActionImplementation();

	ai.type = "drag";

	ai.helper = null;
	ai.ddTypes = [];
	ai.selected = [];

	ai.doRegisterAction = function(_aoi, _callback, _context)
	{
		var node = _aoi.getDOMNode();

		if (node)
		{
			// Prevent selection
			node.onselectstart = function () {
				return false;
			};

			$j(node).draggable(
				{
					"distance": 20,
					"cursor": "move",
					"cursorAt": { top: -12, left: -12 },
					"helper": function(e) {
						// The helper function is called before the start function
						// is evoked. Call the given callback function. The callback
						// function will gather the selected elements and action links
						// and call the doExecuteImplementation function. This
						// will call the onExecute function of the first action
						// in order to obtain the helper object (stored in ai.helper)
						// and the multiple dragDropTypes (ai.ddTypes)
						 _callback.call(_context, false, ai);

						$j(node).data("ddTypes", ai.ddTypes);
						$j(node).data("selected", ai.selected);

						if (ai.helper)
						{
							// Append the helper object to the body element - this
							// fixes a bug in IE: If the element isn't inserted into
							// the DOM-tree jquery appends it to the parent node.
							// In case this is a table it doesn't work correctly
							$j("body").append(ai.helper);
							return ai.helper;
						}

						// Return an empty div if the helper dom node is not set
						return $j(document.createElement("div"));
					},
					"start": function(e) {
						return ai.helper != null;
					},
					// Solves problem with scroll position changing in the grid
					// component
					"refreshPositions": true,
					"scroll": false,
					"containment": "document",
					"iframeFix": true
				}
			);

			return true;
		}
		return false;
	}

	ai.doUnregisterAction = function(_aoi)
	{
		var node = _aoi.getDOMNode();

		if (node) {
			$j(node).draggable("destroy");
		}
	}

	/**
	 * Builds the context menu and shows it at the given position/DOM-Node.
	 */
	ai.doExecuteImplementation = function(_context, _selected, _links)
	{
		// Reset the helper object of the action implementation
		this.helper = null;
		var hasLink = false;

		// Store the drag-drop types
		this.ddTypes = [];
		this.selected = _selected;

		// Call the onExecute event of the first actionObject
		for (var k in _links)
		{
			if (_links[k].visible)
			{
				hasLink = true;

				// Only execute the following code if a JS function is registered
				// for the action and this is the first action link
				if (!this.helper && _links[k].actionObj.onExecute.hasHandler())
				{
					this.helper = _links[k].actionObj.execute(_selected);
				}

				// Push the dragType of the associated action object onto the
				// drag type list - this allows an element to support multiple
				// drag/drop types.
				var type = $j.isArray(_links[k].actionObj.dragType) ? _links[k].actionObj.dragType : [_links[k].actionObj.dragType];
				for(var i = 0; i < type.length; i++)
				{
					if (this.ddTypes.indexOf(type[i]) == -1)
					{
						this.ddTypes.push(type[i]);
					}
				}
			}
		}

		// If no helper has been defined, create an default one
		if (!this.helper && hasLink)
		{
			this.helper = $j(document.createElement("div"));
			this.helper.addClass("egw_action_ddHelper");
			this.helper.text("(" + _selected.length + ")");
		}

		return true;
	}

	return ai;
}



/**
 * The egwDropAction class overwrites the egwAction class and adds the "acceptedTypes"
 * property. This array should contain all "dragTypes" the drop action is allowed
 * to
 */
function egwDropAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
{
	var action = new egwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

	action.type = "drop";
	action.acceptedTypes = ["default"];
	action.canHaveChildren = ["drag","popup"];
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

	/**
	 * The acceptType property allows strings as well as arrays - strings are
	 * automatically included in an array.
	 */
	action.set_acceptedTypes = function(_value) {
		if (_value instanceof Array)
		{
			action.acceptedTypes = _value;
		}
		else
		{
			action.acceptedTypes = [_value];
		}
	}

	return action;
}

var
	_dropActionImpl = null;

function getDropImplementation()
{
	if (!_dropActionImpl)
	{
		_dropActionImpl = new egwDropActionImplementation();
	}
	return _dropActionImpl
}

var EGW_AI_DRAG = 0x0100; // Use the first byte as mask for event types - 01 is for events used with drag stuff
var EGW_AI_DRAG_OUT = EGW_AI_DRAG | 0x01;
var EGW_AI_DRAG_OVER = EGW_AI_DRAG | 0x02;

function egwDropActionImplementation()
{
	var ai = new egwActionImplementation();

	ai.type = "drop";

	ai.doRegisterAction = function(_aoi, _callback, _context)
	{
		var node = _aoi.getDOMNode();
		var self = this;

		if (node)
		{
			$j(node).droppable(
				{
					"accept": function(_draggable) {
						if (typeof _draggable.data("ddTypes") != "undefined")
						{
							var accepted = self._fetchAccepted(
								_callback.call(_context, "links", self, EGW_AO_EXEC_THIS));

							// Check whether all drag types of the selected objects
							// are accepted
							var ddTypes = _draggable.data("ddTypes");

							for (var i = 0; i < ddTypes.length; i++)
							{
								if (accepted.indexOf(ddTypes[i]) != -1)
								{
									return true;
								}
							}

							return false;
						}
					},
					"drop": function(event, ui) {
						var draggable = ui.draggable;
						var ddTypes = draggable.data("ddTypes");
						var selected = draggable.data("selected");

						var links = _callback.call(_context, "links", self, EGW_AO_EXEC_THIS);

						// Disable all links which only accept types which are not
						// inside ddTypes
						for (var k in links)
						{
							var accepted = links[k].actionObj.acceptedTypes;

							var enabled = false
							for (var i = 0; i < ddTypes.length; i++)
							{
								if (accepted.indexOf(ddTypes[i]) != -1)
								{
									enabled = true;
								}
							}
							if(!enabled)
							{
								links[k].enabled = false;
								if (links[k].actionObj.hideOnDisabled)
								{
									links[k].visible = true;
								}
							}
						}

						// Check whether there is only one link
						var cnt = 0;
						var lnk = null;
						for (var k in links)
						{
							if (links[k].enabled && links[k].visible)
							{
								lnk = links[k];
								cnt += 1 + links[k].actionObj.children.length;
							}
						}

						if (cnt == 1)
						{
							window.setTimeout(function() {
								lnk.actionObj.execute(selected, _context);
							},0);
						}

						if (cnt > 1)
						{
							// More than one drop action link is associated
							// to the drop event - show those as a popup menu
							// and let the user decide which one to use.
							// This is possible as the popup and the popup action
							// object and the drop action object share same
							// set of properties.
							var popup = getPopupImplementation();
							var pos = popup._getPageXY(event.originalEvent);
							window.setTimeout(function() {
								popup.doExecuteImplementation(pos, selected, links,
									_context);
							}, 0); // Timeout is needed to have it working in IE
						}

						_aoi.triggerEvent(EGW_AI_DRAG_OUT);
					},
					"over": function() {
						_aoi.triggerEvent(EGW_AI_DRAG_OVER);
					},
					"out": function() {
						_aoi.triggerEvent(EGW_AI_DRAG_OUT);
					},
					"tolerance": "pointer",
					activeClass: "ui-state-hover",
					hoverClass: "ui-state-active"
				}
			);

			return true;
		}
		return false;
	}

	ai.doUnregisterAction = function(_aoi)
	{
		var node = _aoi.getDOMNode();

		if (node) {
			$j(node).droppable("destroy");
		}
	}

	ai._fetchAccepted = function(_links)
	{
		// Accumulate the accepted types
		var accepted = [];
		for (var k in _links)
		{
			for (var i = 0; i < _links[k].actionObj.acceptedTypes.length; i++)
			{
				var type = _links[k].actionObj.acceptedTypes[i];

				if (accepted.indexOf(type) == -1)
				{
					accepted.push(type);
				}
			}
		}

		return accepted;
	}

	/**
	 * Builds the context menu and shows it at the given position/DOM-Node.
	 */
	ai.doExecuteImplementation = function(_context, _selected, _links)
	{
		if (_context == "links")
		{
			return _links;
		}
	}

	return ai;
}

