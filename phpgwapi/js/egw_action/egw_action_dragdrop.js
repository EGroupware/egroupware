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
	window._egwActionClasses = {};
_egwActionClasses["drag"] = {
	"actionConstructor": egwDragAction,
	"implementation": getDragImplementation
};
_egwActionClasses["drop"] = {
	"actionConstructor": egwDropAction,
	"implementation": getDropImplementation
};

/**
 * The egwDragAction class overwrites the egwAction class and adds the new
 * "dragType" propery. The "onExecute" event of the drag action will be called
 * whenever dragging starts. The onExecute JS handler should return the
 * drag-drop helper object - otherwise an default helper will be generated.
 *
 * @param {egwAction} _id
 * @param {string} _handler
 * @param {string} _caption
 * @param {string} _icon
 * @param {(string|function)} _onExecute
 * @param {bool} _allowOnMultiple
 * @returns {egwDragAction}
 */
function egwDragAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
{
	var action = new egwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

	action.type = "drag";
	action.dragType = "default";
	action.hideOnDisabled = true;

	action.set_dragType = function(_value) {
		action.dragType = _value;
	};

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
	return _dragActionImpl;
}

function egwDragActionImplementation()
{
	var ai = new egwActionImplementation();

	ai.type = "drag";

	ai.helper = null;
	ai.ddTypes = [];
	ai.selected = [];

	// Define default helper DOM
	// default helper also can be called later in application code in order to customization
	ai.defaultDDHelper = function (_selected)
	{
		// Table containing clone of rows
		var table = $j(document.createElement("table")).addClass('egwGridView_grid et2_egw_action_ddHelper_row');
		// tr element to use as last row to show lable more ...
		var moreRow = $j(document.createElement('tr')).addClass('et2_egw_action_ddHelper_moreRow');
		// Main div helper container
		var div = $j(document.createElement("div")).append(table);

		var rows = [];
		// Maximum number of rows to show
		var maxRows = 3;
		// item label
		var itemLabel = egw.lang(egw.link_get_registry(egw.app_name(),_selected.length > 1?'entries':'entry')||egw.app_name());

		var index = 0;
		for (var i = 0; i < _selected.length;i++)
		{
			var row = $j(_selected[i].iface.getDOMNode()).clone();
			if (row)
			{
				rows.push(row);
				table.append(row);
			}
			index++;
			if (index == maxRows)
			{
				// Lable to show number of items
				var spanCnt = $j(document.createElement('span'))
						.addClass('et2_egw_action_ddHelper_itemsCnt')
						.appendTo(div);

				spanCnt.text(_selected.length +' '+ itemLabel);
				// Number of not shown rows
				var restRows = _selected.length - maxRows;
				if (restRows)
				{
					moreRow.text((_selected.length - maxRows) +' '+egw.lang('more %1 selected ...', itemLabel));
				}
				table.append(moreRow);
				break;
			}
		}

		var text = $j(document.createElement('div')).addClass('et2_egw_action_ddHelper_tip');
		div.append(text);

		// Add notice of Ctrl key, if supported
		if('draggable' in document.createElement('span') &&
			navigator && navigator.userAgent.indexOf('Chrome') >= 0 && egw.app_name() == 'filemanager') // currently only filemanager supports drag out
		{
			var key = ["Mac68K","MacPPC","MacIntel"].indexOf(window.navigator.platform) < 0 ?
				egw.lang('Alt') : egw.lang('Command ⌘');
			text.text(egw.lang('Hold [%1] and [%2] key to drag %3 to your desktop', key, egw.lang('Shift ⇧'), itemLabel));
		}
		// Final html DOM return as helper structor
		return div;
	};

	ai.doRegisterAction = function(_aoi, _callback, _context)
	{
		var node = _aoi.getDOMNode();

		if (node)
		{
			// Prevent selection
			node.onselectstart = function () {
				return false;
			};
			if (!(window.FileReader && 'draggable' in document.createElement('span')) )
			{
				// No DnD support
				return;
			}

			// It shouldn't be so hard to get the action...
			var action = null;
			var groups = _context.getActionImplementationGroups();
			if(!groups.drag) return;
			for(var i = 0; i < groups.drag.length; i++)
			{
				// dragType 'file' says it can be dragged as a file
				if(groups.drag[i].link.actionObj.dragType == 'file' || groups.drag[i].link.actionObj.dragType.indexOf('file') > -1)
				{
					action = groups.drag[i].link.actionObj;
					break;
				}
			}
			if(action)
			{
				/**
				 * We found an action with dragType 'file', so by holding Ctrl
				 * key & dragging, user can drag from browser to system.
				 * The global data store must provide a full, absolute URL in 'download_url'
				 * and a mime in 'mime'.
				 *
				 * Unfortunately, Native DnD to drag the file conflicts with jQueryUI draggable,
				 * which handles all the other DnD actions.  We get around this by:
				 * 1.  Require the user indicate a file drag with Ctrl key
				 * 2.  Disable jQueryUI draggable, then turn on native draggable attribute
				 * This way we can at least toggle which one is operating, so they
				 * both work alternately if not together.
				 */
				// Native DnD - Doesn't play nice with jQueryUI Sortable
				// Tell jQuery to include this property
				jQuery.event.props.push('dataTransfer');

				$j(node).off("mousedown")
					.on("mousedown", function(event) {
							var dragOut = _context.isDragOut(event);
							$j(this).attr("draggable", dragOut? "true" : "");
							$j(node).draggable("option","disabled",dragOut);
							if (dragOut)
							{
								// Disabling draggable adds some UI classes, but we don't care so remove them
								$j(node).removeClass("ui-draggable-disabled ui-state-disabled");

							}
							else
							{
								if (_context.isSelection(event))
								{
									$j(node).draggable("disable");
									// Disabling draggable adds some UI classes, but we don't care so remove them
									$j(node).removeClass("ui-draggable-disabled ui-state-disabled");
								}
								else if(event.which != 3)
								{
									document.getSelection().removeAllRanges();
								}
								if(!(dragOut) || !this.addEventListener) return;
							}
					})
					.on ("mouseup", function (event){
						if (_context.isSelection(event))
							$j(node).draggable("enable");
					})
					.on("dragstart", function(event) {
						if(_context.isSelection(event)) return;
						if(event.dataTransfer == null) {
							return;
						}
						event.dataTransfer.effectAllowed="copy";

						// Get all selected
						// Multiples aren't supported by event.dataTransfer, yet, so
						// select only the row they clicked on.
						// var selected = _context.getSelectedLinks('drag');
						var selected = [_context];
						_context.parent.setAllSelected(false);
						_context.setSelected(true);

						// Set file data
						for(var i = 0; i < selected.length; i++)
						{
							var data = selected[i].data || egw.dataGetUIDdata(selected[i].id).data || {};
							if(data && data.mime && data.download_url)
							{
								var url = data.download_url;

								// NEED an absolute URL
								if (url[0] == '/') url = egw.link(url);
								// egw.link adds the webserver, but that might not be an absolute URL - try again
								if (url[0] == '/') url = window.location.origin+url;

								// Unfortunately, dragging files is currently only supported by Chrome
								if(navigator && navigator.userAgent.indexOf('Chrome'))
								{
									event.dataTransfer.setData("DownloadURL", data.mime+':'+data.name+':'+url);
								}
								else
								{
									// Include URL as a fallback
									event.dataTransfer.setData("text/uri-list", url);
								}
							}
						}
						if(event.dataTransfer.types.length == 0)
						{
							// No file data? Abort: drag does nothing
							event.preventDefault();
							return;
						}

						// Create drag icon
						_callback.call(_context, _context, ai);
						// Drag icon must be visible for setDragImage() - we'll remove it on drag
						$j("body").append(ai.helper);
						event.dataTransfer.setDragImage(ai.helper[0],-12,-12);
					})
					.on("drag", function(e) {
						// Remove the helper, it has been copied into the dataTransfer object now
						// Hopefully user didn't notice it...
						if(e.dataTransfer != null)
						{
							ai.helper.remove();
						}
					});
			}
			else
			{
				// Use Ctrl key in order to select content
				$j(node).off("mousedown")
						.on({
							mousedown: function(event){
								if (_context.isSelection(event)){
									$j(node).draggable("disable");
									// Disabling draggable adds some UI classes, but we don't care so remove them
									$j(node).removeClass("ui-draggable-disabled ui-state-disabled");
								}
								else if(event.which != 3)
								{
									document.getSelection().removeAllRanges();
								}
							},
							mouseup: function (){
								$j(node).draggable("enable");
								// Set cursor back to auto. Seems FF can't handle cursor reversion
								$j('body').css({cursor:'auto'});
							}
				});
			}
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
							// Add a basic class to the helper in order to standardize the background layout
							ai.helper.addClass('et2_egw_action_ddHelper');

							// Append the helper object to the body element - this
							// fixes a bug in IE: If the element isn't inserted into
							// the DOM-tree jquery appends it to the parent node.
							// In case this is a table it doesn't work correctly
							$j("body").append(ai.helper);
							return ai.helper;
						}

						// Return an empty div if the helper dom node is not set
						return ai.defaultDDHelper(ai.selected);//$j(document.createElement("div")).addClass('et2_egw_action_ddHelper');
					},
					"start": function(e) {
						
						//Stop dragging if user tries to do scrolling by mouse down and drag
						//Seems this issue is only happening in FF
						var $target = $j(e.originalEvent.target);
						if(e.originalEvent.pageX - $target.offset().left + 15 > $target.innerWidth())
						{
							return false;
						}
						
						return ai.helper != null;
					},
					revert: function(valid)
					{
						var dTarget = this;
						if (!valid)
						{
							// Tolerance value of pixels arround the draggable target
							// to distinguish whether the action was intended for dragging or selecting content.
							var tipTelorance = 10;
							var helperTop = ai.helper.position().top;

							if (helperTop >= dTarget.offset().top
									&& helperTop <= (dTarget.height() + dTarget.offset().top) + tipTelorance)
							{
								var key = ["Mac68K","MacPPC","MacIntel"].indexOf(window.navigator.platform) < 0 ?
									egw.lang("Ctrl") : egw.lang("Command ⌘");
								egw.message(egw.lang('Hold [%1] key to select text eg. to copy it', key), 'info');
							}
							
							// Invalid target
							return true;
						}
						else
						{
							// Valid target
							return false;
						}
					},
					// Solves problem with scroll position changing in the grid
					// component
					"refreshPositions": true,
					"scroll": false,
					//"containment": "document",
					"iframeFix": true
				}
			);


			return true;
		}
		return false;
	};

	ai.doUnregisterAction = function(_aoi)
	{
		var node = _aoi.getDOMNode();

		if (node && $j(node).data("uiDraggable")){
			$j(node).draggable("destroy");
		}
	};

	/**
	 * Builds the context menu and shows it at the given position/DOM-Node.
	 *
	 * @param {string} _context
	 * @param {array} _selected
	 * @param {object} _links
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
			this.helper = ai.defaultDDHelper(_selected);
		}

		return true;
	};

	return ai;
}



/**
 * The egwDropAction class overwrites the egwAction class and adds the "acceptedTypes"
 * property. This array should contain all "dragTypes" the drop action is allowed to
 *
 * @param {egwAction} _id
 * @param {string} _handler
 * @param {string} _caption
 * @param {string} _icon
 * @param {(string|function)} _onExecute
 * @param {bool} _allowOnMultiple
 * @returns {egwDropAction}
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
	};

	action.set_order = function(_value) {
		action.order = _value;
	};

	action.set_group = function(_value) {
		action.group = _value;
	};

	/**
	 * The acceptType property allows strings as well as arrays - strings are
	 * automatically included in an array.
	 *
	 * @param {(string|array)} _value
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
	};

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
	return _dropActionImpl;
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

							var enabled = false;
							for (var i = 0; i < ddTypes.length; i++)
							{
								if (accepted.indexOf(ddTypes[i]) != -1)
								{
									enabled = true;
									break;
								}
							}
							// Check for allowing multiple selected
							if(!links[k].actionObj.allowOnMultiple && selected.length > 1)
							{
								enabled = false;
							}
							if(!enabled)
							{
								links[k].enabled = false;
								links[k].visible = !links[k].actionObj.hideOnDisabled;
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

								// Add ui, so you know what happened where
								lnk.actionObj.ui = ui;

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
							
							// Don't add paste actions, this is a drop
							popup.auto_paste = false;
							
							window.setTimeout(function() {
								popup.doExecuteImplementation(pos, selected, links,
									_context);
								// Reset, popup is reused
								popup.auto_paste = true;
							}, 0); // Timeout is needed to have it working in IE
						}
						// Set cursor back to auto. Seems FF can't handle cursor reversion
						$j('body').css({cursor:'auto'});
						
						_aoi.triggerEvent(EGW_AI_DRAG_OUT,{event: event,ui:ui});
					},
					"over": function(event, ui) {
						_aoi.triggerEvent(EGW_AI_DRAG_OVER,{event: event,ui:ui});
					},
					"out": function(event,ui) {
						_aoi.triggerEvent(EGW_AI_DRAG_OUT,{event: event,ui:ui});
					},
					"tolerance": "pointer",
					hoverClass: "drop-hover",
					// Greedy is for nested droppables - children consume the action
					greedy: true
				}
			);

			return true;
		}
		return false;
	};

	ai.doUnregisterAction = function(_aoi)
	{
		var node = _aoi.getDOMNode();

		if (node && $j(node).data("uiDroppable")) {
			$j(node).droppable("destroy");
		}
	};

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
	};

	/**
	 * Builds the context menu and shows it at the given position/DOM-Node.
	 *
	 * @param {string} _context
	 * @param {array} _selected
	 * @param {object} _links
	 */
	ai.doExecuteImplementation = function(_context, _selected, _links)
	{
		if (_context == "links")
		{
			return _links;
		}
	};

	return ai;
}
