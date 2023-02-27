/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

/*egw:uses
	egw_action;
	egw_action_common;
	egw_action_popup;
	vendor.bower-asset.jquery.dist.jquery;
*/

import {egwAction,egwActionImplementation, egw_getObjectManager} from "./egw_action.js";
import {getPopupImplementation} from "./egw_action_popup.js";
import {EGW_AI_DRAG_OUT, EGW_AI_DRAG_OVER, EGW_AO_EXEC_THIS, EGW_AI_DRAG_ENTER} from "./egw_action_constants.js";

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
export function egwDragAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
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

export function getDragImplementation()
{
	if (!_dragActionImpl)
	{
		_dragActionImpl = new egwDragActionImplementation();
	}
	return _dragActionImpl;
}

export function egwDragActionImplementation()
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
		var table = jQuery(document.createElement("table")).addClass('egwGridView_grid et2_egw_action_ddHelper_row');
		// tr element to use as last row to show lable more ...
		var moreRow = jQuery(document.createElement('tr')).addClass('et2_egw_action_ddHelper_moreRow');
		// Main div helper container
		var div = jQuery(document.createElement("div")).append(table);

		var rows = [];
		// Maximum number of rows to show
		var maxRows = 3;
		// item label
		var itemLabel = egw.lang(egw.link_get_registry(egw.app_name(),_selected.length > 1?'entries':'entry')||egw.app_name());

		var index = 0;

		// Take select all into account when counting number of rows, because they may not be
		// in _selected object
		var pseudoNumRows = (_selected[0] && _selected[0]._context && _selected[0]._context._selectionMgr &&
				_selected[0]._context._selectionMgr._selectAll) ?
				_selected[0]._context._selectionMgr._total : _selected.length;

		for (var i = 0; i < _selected.length;i++)
		{
			var row = jQuery(_selected[i].iface.getDOMNode()).clone();
			if (row)
			{
				rows.push(row);
				table.append(row);
			}
			index++;
			if (index == maxRows)
			{
				// Lable to show number of items
				var spanCnt = jQuery(document.createElement('span'))
						.addClass('et2_egw_action_ddHelper_itemsCnt')
						.appendTo(div);

				spanCnt.text(pseudoNumRows +' '+ itemLabel);
				// Number of not shown rows
				var restRows = pseudoNumRows - maxRows;
				if (restRows)
				{
					moreRow.text(egw.lang("%1 more %2 selected ...", (pseudoNumRows - maxRows), itemLabel));
				}
				table.append(moreRow);
				break;
			}
		}

		var text = jQuery(document.createElement('div')).addClass('et2_egw_action_ddHelper_tip');
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
		var node = _aoi.getDOMNode() && _aoi.getDOMNode()[0] ? _aoi.getDOMNode()[0] : _aoi.getDOMNode();

		if (node)
		{
			// Prevent selection
			node.onselectstart = function () {
				return false;
			};
			if (!(window.FileReader && 'draggable' in document.createElement('span')))
			{
				// No DnD support
				return;
			}

			// It shouldn't be so hard to get the action...
			var action = null;
			var groups = _context.getActionImplementationGroups();
			if (!groups.drag)
			{
				return;
			}
			// Disable file drag and drop, it conflicts with normal drag and drop
			for (var i = 0; false && i < groups.drag.length; i++)
			{
				// dragType 'file' says it can be dragged as a file
				if(groups.drag[i].link.actionObj.dragType == 'file' || groups.drag[i].link.actionObj.dragType.indexOf('file') > -1)
				{
					action = groups.drag[i].link.actionObj;
					break;
				}
			}

			if(!action)
			{
				// Use Ctrl key in order to select content
				jQuery(node).off("mousedown")
						.on({
							mousedown: function(event){
								if (_context.isSelection(event)){
									node.setAttribute("draggable", false);
								}
								else if(event.which != 3)
								{
									document.getSelection().removeAllRanges();
								}
							},
							mouseup: function (event){
								if (_context.isSelection(event)){
									// TODO: save and retrive selected range
									node.setAttribute("draggable", true);
								}
								else
								{
									node.setAttribute("draggable", true);
								}

								// Set cursor back to auto. Seems FF can't handle cursor reversion
								jQuery('body').css({cursor:'auto'});
							}
				});
			}

			node.setAttribute('draggable', true);
			const dragstart = function(event) {
				if (action) {
					if (_context.isSelection(event)) return;

					// Get all selected
					// Multiples aren't supported by event.dataTransfer, yet, so
					// select only the row they clicked on.
					var selected = [_context];
					_context.parent.setAllSelected(false);
					_context.setSelected(true);

					// Set file data
					for (let i = 0; i < selected.length; i++) {
						let d = selected[i].data || egw.dataGetUIDdata(selected[i].id).data || {};
						if (d && d.mime && d.download_url) {
							var url = d.download_url;

							// NEED an absolute URL
							if (url[0] == '/') url = egw.link(url);
							// egw.link adds the webserver, but that might not be an absolute URL - try again
							if (url[0] == '/') url = window.location.origin + url;

							// Unfortunately, dragging files is currently only supported by Chrome
							if (navigator && navigator.userAgent.indexOf('Chrome')) {
								event.dataTransfer.setData("DownloadURL", d.mime + ':' + d.name + ':' + url);
							} else {
								// Include URL as a fallback
								event.dataTransfer.setData("text/uri-list", url);
							}
						}
					}
					event.dataTransfer.effectAllowed = 'copy';

					if (event.dataTransfer.types.length == 0) {
						// No file data? Abort: drag does nothing
						event.preventDefault();
						return;
					}
				} else {
					event.dataTransfer.effectAllowed = 'linkMove';
				}
				// The helper function is called before the start function
				// is evoked. Call the given callback function. The callback
				// function will gather the selected elements and action links
				// and call the doExecuteImplementation function. This
				// will call the onExecute function of the first action
				// in order to obtain the helper object (stored in ai.helper)
				// and the multiple dragDropTypes (ai.ddTypes)
				_callback.call(_context, false, ai);

				const data = {
					ddTypes: ai.ddTypes,
					selected: ai.selected.map((item) => {
						return {id: item.id}
					})
				};

				if (!ai.helper) {
					ai.helper = ai.defaultDDHelper(ai.selected);
				}
				// Add a basic class to the helper in order to standardize the background layout
				ai.helper[0].classList.add('et2_egw_action_ddHelper', 'ui-draggable-dragging');
				document.body.append(ai.helper[0]);
				this.classList.add('drag--moving');

				event.dataTransfer.setData('application/json', JSON.stringify(data))

				event.dataTransfer.setDragImage(ai.helper[0], 12, 12);

				this.setAttribute('data-egwActionObjID', JSON.stringify(data.selected));
			};

			const dragend = function(event){
				const helper = document.querySelector('.et2_egw_action_ddHelper');
				if (helper) helper.remove();
				const draggable = document.querySelector('.drag--moving');
				if (draggable) draggable.classList.remove('drag--moving');
			};

			// Drag Event listeners
			node.addEventListener('dragstart', dragstart , false);
			node.addEventListener('dragend', dragend, false);


			return true;
		}
		return false;
	};

	ai.doUnregisterAction = function(_aoi)
	{
		var node = _aoi.getDOMNode();

		if (node){
			node.setAttribute('draggable', false);
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
				var type = jQuery.isArray(_links[k].actionObj.dragType) ? _links[k].actionObj.dragType : [_links[k].actionObj.dragType];
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
export function egwDropAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
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

export function getDropImplementation()
{
	if (!_dropActionImpl)
	{
		_dropActionImpl = new egwDropActionImplementation();
	}
	return _dropActionImpl;
}

export function egwDropActionImplementation()
{
	var ai = new egwActionImplementation();

	//keeps track of current drop element where dragged item's entered.
	// it's necessary for dragenter/dragleave issue correction.
	var currentDropEl = null;

	ai.type = "drop";

	ai.doRegisterAction = function(_aoi, _callback, _context)
	{
		var node = _aoi.getDOMNode() && _aoi.getDOMNode()[0] ? _aoi.getDOMNode()[0] : _aoi.getDOMNode();
		var self = this;
		if (node)
		{
			node.classList.add('et2dropzone');
			const dragover = function (event) {
				if (event.preventDefault) {
					event.preventDefault();
				}
				if (!self.getTheDraggedDOM()) return ;

				const data = {
					event: event,
					ui: self.getTheDraggedData()
				};
				_aoi.triggerEvent(EGW_AI_DRAG_OVER, data);

				return true;

			};

			const dragenter = function (event) {
				event.stopImmediatePropagation();
				// don't trigger dragenter if we are entering the drag element
				// don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
				if (!self.getTheDraggedDOM() || self.isTheDraggedDOM(this) || this == currentDropEl) return;

				currentDropEl = event.currentTarget;
				event.dataTransfer.dropEffect = 'link';

				const data = {
					event: event,
					ui: self.getTheDraggedData()
				};

				_aoi.triggerEvent(EGW_AI_DRAG_ENTER, data);

				// cleanup drop hover class from all other DOMs if there's still anything left
				Array.from(document.getElementsByClassName('et2dropzone drop-hover')).forEach(_i=>{_i.classList.remove('drop-hover')})

				this.classList.add('drop-hover');

				// stop the event from being fired for its children
				event.preventDefault();
				return false;
			};

			const drop = function (event) {
				event.preventDefault();
				// don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
				if (!self.getTheDraggedDOM()) return ;

				// remove the hover class
				this.classList.remove('drop-hover');

				const helper = self.getHelperDOM();
				let ui = self.getTheDraggedData();
				ui.position = {top: event.clientY, left: event.clientX};
				ui.offset = {top: event.offsetY, left: event.offsetX};


				let data = JSON.parse(event.dataTransfer.getData('application/json'));

				if (!self.isAccepted(data, _context, _callback) || self.isTheDraggedDOM(this))
				{
					// clean up the helper dom
					if (helper) helper.remove();
					return;
				}

				let selected = data.selected.map((item) => {
					return egw_getObjectManager(item.id, false)
				});


				var links = _callback.call(_context, "links", self, EGW_AO_EXEC_THIS);

				// Disable all links which only accept types which are not
				// inside ddTypes
				for (var k in links) {
					var accepted = links[k].actionObj.acceptedTypes;

					var enabled = false;
					for (var i = 0; i < data.ddTypes.length; i++) {
						if (accepted.indexOf(data.ddTypes[i]) != -1) {
							enabled = true;
							break;
						}
					}
					// Check for allowing multiple selected
					if (!links[k].actionObj.allowOnMultiple && selected.length > 1) {
						enabled = false;
					}
					if (!enabled) {
						links[k].enabled = false;
						links[k].visible = !links[k].actionObj.hideOnDisabled;
					}
				}

				// Check whether there is only one link
				var cnt = 0;
				var lnk = null;
				for (var k in links) {
					if (links[k].enabled && links[k].visible) {
						lnk = links[k];
						cnt += 1 + links[k].actionObj.children.length;

						// Add ui, so you know what happened where
						lnk.actionObj.ui = ui;

					}
				}

				if (cnt == 1) {
					window.setTimeout(function () {
						lnk.actionObj.execute(selected, _context);
					}, 0);
				}

				if (cnt > 1) {
					// More than one drop action link is associated
					// to the drop event - show those as a popup menu
					// and let the user decide which one to use.
					// This is possible as the popup and the popup action
					// object and the drop action object share same
					// set of properties.
					var popup = getPopupImplementation();
					var pos = popup._getPageXY(event);

					// Don't add paste actions, this is a drop
					popup.auto_paste = false;

					window.setTimeout(function () {
						popup.doExecuteImplementation(pos, selected, links,
							_context);
						// Reset, popup is reused
						popup.auto_paste = true;
					}, 0); // Timeout is needed to have it working in IE
				}
				// Set cursor back to auto. Seems FF can't handle cursor reversion
				jQuery('body').css({cursor: 'auto'});

				_aoi.triggerEvent(EGW_AI_DRAG_OUT, {event: event, ui: self.getTheDraggedData()});

				// clean up the helper dom
				if (helper) helper.remove();
				self.getTheDraggedDOM().classList.remove('drag--moving');
			};

			const dragleave = function (event) {
				event.stopImmediatePropagation();

				// don't trigger dragleave if we are leaving the drag element
				// don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
				if (!self.getTheDraggedDOM() || self.isTheDraggedDOM(this) || this == currentDropEl) return;

				const data = {
					event: event,
					ui: self.getTheDraggedData()
				};

				_aoi.triggerEvent(EGW_AI_DRAG_OUT, data);

				this.classList.remove('drop-hover');

				event.preventDefault();
				return false;
			};

			// DND Event listeners
			node.addEventListener('dragover', dragover, false);

			node.addEventListener('dragenter', dragenter, false);

			node.addEventListener('drop', drop, false);

			node.addEventListener('dragleave', dragleave, false);

			return true;
		}
		return false;
	};

	ai.isTheDraggedDOM = function (_dom)
	{
		return _dom.classList.contains('drag--moving');
	}

	ai.getTheDraggedDOM = function ()
	{
		return document.querySelector('.drag--moving');
	}

	ai.getHelperDOM = function ()
	{
		return document.querySelector('.et2_egw_action_ddHelper');
	}

	ai.getTheDraggedData = function()
	{
		let data = this.getTheDraggedDOM().dataset.egwactionobjid;
		let selected = [];
		if (data)
		{
			data = JSON.parse(data);
			selected = data.map((item)=>{return egw_getObjectManager(item.id, false)});
		}
		return {
			draggable: this.getTheDraggedDOM(),
			helper: this.getHelperDOM(),
			selected: selected

		}
	}

	// check if given draggable is accepted for drop
	ai.isAccepted = function(_data, _context, _callback, _node)
	{
		if (_node && !_node.classList.contains('et2dropzone')) return false;
		if (typeof _data.ddTypes != "undefined")
		{
			const accepted = this._fetchAccepted(
				_callback.call(_context, "links", this, EGW_AO_EXEC_THIS));

			// Check whether all drag types of the selected objects
			// are accepted
			var ddTypes = _data.ddTypes;

			for (let i = 0; i < ddTypes.length; i++)
			{
				if (accepted.indexOf(ddTypes[i]) != -1)
				{
					return true;
				}
			}
		}
		return false;
	};

	ai.doUnregisterAction = function(_aoi)
	{
		var node = _aoi.getDOMNode();

		if (node) {
			node.classList.remove('et2dropzone');
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
