/**
 * eGroupWare eTemplate2 - JS DOM Widget class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_core_widget;
*/

/**
 * Interface for all widget classes, which are based on a DOM node.
 */
var et2_IDOMNode = new Interface({
	/**
	 * Returns the DOM-Node of the current widget. The return value has to be
	 * a plain DOM node. If you want to return an jQuery object as you receive
	 * it with
	 * 
	 * 	obj = $j(node);
	 * 
	 * simply return obj[0];
	 * 
	 * @param _sender The _sender parameter defines which widget is asking for
	 * 	the DOMNode. Depending on that, the widget may return different nodes.
	 * 	This is used in the grid. Normally the _sender parameter can be omitted
	 * 	in most implementations of the getDOMNode function.
	 * 	However, you should always define the _sender parameter when calling
	 * 	getDOMNode!
	 */
	getDOMNode: function(_sender) {}
});

/**
 * Abstract widget class which can be inserted into the DOM. All widget classes
 * deriving from this class have to care about implementing the "getDOMNode"
 * function which has to return the DOM-Node.
 */
var et2_DOMWidget = et2_widget.extend(et2_IDOMNode, {

	attributes: {
		"disabled": {
			"name": "Visible",
			"type": "boolean",
			"description": "Defines whether this widget is visible.",
			"default": false
		},
		"width": {
			"name": "Width",
			"type": "dimension",
			"default": et2_no_init,
			"description": "Width of the element in pixels, percentage or 'auto'"
		},
		"height": {
			"name": "Height",
			"type": "dimension",
			"default": et2_no_init,
			"description": "Height of the element in pixels, percentage or 'auto'"
		},
		"class": {
			"name": "CSS Class",
			"type": "string",
			"default": et2_no_init,
			"description": "CSS Class which is applied to the dom element of this node"
		},
		"overflow": {
			"name": "Overflow",
			"type": "string",
			"default": et2_no_init,
			"description": "If set, the css-overflow attribute is set to that value"
		}
	},

	/**
	 * When the DOMWidget is initialized, it grabs the DOM-Node of the parent
	 * object (if available) and passes it to its own "createDOMNode" function
	 */
	init: function() {
		// Call the inherited constructor
		this._super.apply(this, arguments);

		this.parentNode = null;

		this._attachSet = {
			"node": null,
			"parent": null
		};

		this._disabled = false;
		this._surroundingsMgr = null;
	},

	/**
	 * Detatches the node from the DOM and clears all references to the parent
	 * node or the dom node of this widget.
	 */
	destroy: function() {

		this.detatchFromDOM();
		this.parentNode = null;
		this._attachSet = {};

		if (this._surroundingsMgr)
		{
			this._surroundingsMgr.destroy();
			this._surroundingsMgr = null;
		}

		this._super();
	},

	/**
	 * Attaches the container node of this widget to the DOM-Tree
	 */
	doLoadingFinished: function() {
		// Check whether the parent implements the et2_IDOMNode interface. If
		// yes, grab the DOM node and create our own.
		if (this._parent && this._parent.implements(et2_IDOMNode)) {
			this.setParentDOMNode(this._parent.getDOMNode(this));
		}

		return true;
	},

	/**
	 * Detaches the widget from the DOM tree, if it had been attached to the
	 * DOM-Tree using the attachToDOM method.
	 */
	detatchFromDOM: function() {

		if (this._attachSet.node && this._attachSet.parent)
		{
			// Remove the current node from the parent node
			this._attachSet.parent.removeChild(this._attachSet.node);

			// Reset the "attachSet"
			this._attachSet = {
				"node": null,
				"parent": null
			};

			return true;
		}

		return false;
	},

	/**
	 * Attaches the widget to the DOM tree. Fails if the widget is already
	 * attached to the tree or no parent node or no node for this widget is
	 * defined.
	 */
	attachToDOM: function() {
		// Attach the DOM node of this widget (if existing) to the new parent
		var node = this.getDOMNode(this);
		if (node && this.parentNode &&
		    (node != this._attachSet.node ||
		    this.parentNode != this._attachSet.parent))
		{
			// If the surroundings manager exists, surround the DOM-Node of this
			// widget with the DOM-Nodes inside the surroundings manager.
			if (this._surroundingsMgr)
			{
				node = this._surroundingsMgr.getDOMNode(node);
			}

			this.parentNode.appendChild(node);

			// Store the currently attached nodes
			this._attachSet = {
				"node": node,
				"parent": this.parentNode
			};

			return true;
		}

		return false;
	},

	isAttached: function() {
		return this.parentNode != null;
	},

	getSurroundings: function() {
		if (!this._surroundingsMgr)
		{
			this._surroundingsMgr = new et2_surroundingsMgr(this);
		}

		return this._surroundingsMgr;
	},

	/**
	 * Set the parent DOM node of this element. If another parent node is already
	 * set, this widget removes itself from the DOM tree
	 */
	setParentDOMNode: function(_node) {
		if (_node != this.parentNode)
		{
			// Detatch this element from the DOM tree
			this.detatchFromDOM();

			this.parentNode = _node;

			// And attatch the element to the DOM tree
			this.attachToDOM();
		}
	},

	/**
	 * Returns the parent node.
	 */
	getParentDOMNode: function() {
		return this.parentNode;
	},

	/**
	 * Sets the id of the DOM-Node.
	 */
	set_id: function(_value) {

		this.id = _value;

		var node = this.getDOMNode(this);
		if (node)
		{
			if (_value != "")
			{
				node.setAttribute("id", _value);
			}
			else
			{
				node.removeAttribute("id");
			}
		}
	},

	set_disabled: function(_value) {
		var node = this.getDOMNode(this);
		if (node)
		{
			this.disabled = _value;

			if (_value)
			{
				$j(node).hide();
			}
			else
			{
				$j(node).show();
			}
		}
	},

	set_width: function(_value) {
		this.width = _value;

		var node = this.getDOMNode(this);
		if (node)
		{
			$j(node).css("width", _value);
		}
	},

	set_height: function(_value) {
		this.height = _value;

		var node = this.getDOMNode(this);
		if (node)
		{
			$j(node).css("height", _value);
		}
	},

	set_class: function(_value) {
		var node = this.getDOMNode(this);
		if (node)
		{
			if (this["class"])
			{
				$j(node).removeClass(this["class"]);
			}
			$j(node).addClass(_value);
		}

		this["class"] = _value;
	},

	set_overflow: function(_value) {
		this.overflow = _value;

		var node = this.getDOMNode(this);
		if (node)
		{
			$j(node).css("overflow", _value);
		}
	}
});

/**
 * The surroundings manager class allows to append or prepend elements around
 * an widget node.
 */
var et2_surroundingsMgr = Class.extend({

	init: function(_widget) {
		this.widget = _widget;

		this._widgetContainer = null;
		this._widgetSurroundings = [];
		this._widgetPlaceholder = null;
		this._widgetNode = null;
		this._ownPlaceholder = true;
	},

	destroy: function() {
		this._widgetContainer = null;
		this._widgetSurroundings = null;
		this._widgetPlaceholder = null;
		this._widgetNode = null;
	},

	prependDOMNode: function(_node) {
		this._widgetSurroundings.unshift(_node);
		this._surroundingsUpdated = true;
	},

	appendDOMNode: function(_node) {
		// Append an placeholder first if none is existing yet
		if (this._ownPlaceholder && this._widgetPlaceholder == null)
		{
			this._widgetPlaceholder = document.createElement("span");
			this._widgetSurroundings.push(this._widgetPlaceholder);
		}

		// Append the given node
		this._widgetSurroundings.push(_node);
		this._surroundingsUpdated = true;
	},

	insertDOMNode: function(_node) {
		if (!this._ownPlaceholder || this._widgetPlaceholder == null)
		{
			this.appendDOMNode(_node);
			return;
		}

		// Get the index of the widget placeholder and delete it, insert the
		// given node instead
		var idx = this._widgetSurroundings.indexOf(this._widgetPlaceholder);
		this._widgetSurroundings.splice(idx, 1, _node);

		// Delete the reference to the own placeholder
		this._widgetPlaceholder = null;
		this._ownPlaceholder = false;
	},

	removeDOMNode: function(_node) {
		for (var i = 0; i < this._widgetSurroundings.length; i++)
		{
			if (this._widgetSurroundings[i] == _node)
			{
				this._widgetSurroundings.splice(i, 1);
				this._surroundingsUpdated = true;
				break;
			}
		}
	},

	setWidgetPlaceholder: function(_node) {
		if (_node != this._widgetPlaceholder)
		{
			if (_node != null && this._ownPlaceholder && this._widgetPlaceholder != null)
			{
				// Delete the current placeholder which was created by the
				// widget itself
				var idx = this._widgetSurroundings.indexOf(this._widgetPlaceholder);
				this._widgetSurroundings.splice(idx, 1);

				// Delete any reference to the own placeholder and set the
				// _ownPlaceholder flag to false
				this._widgetPlaceholder = null;
				this._ownPlaceholder = false;
			}
			
			this._ownPlaceholder = (_node == null);
			this._widgetPlaceholder = _node;
			this._surroundingsUpdated = true;
		}
	},

	_rebuildContainer: function() {
		// Return if there has been no change in the "surroundings-data"
		if (!this._surroundingsUpdated)
		{
			return false;
		}

		// Build the widget container
		if (this._widgetSurroundings.length > 0)
		{
			// Check whether the widgetPlaceholder is really inside the DOM-Tree
			var hasPlaceholder = et2_hasChild(this._widgetSurroundings,
				this._widgetPlaceholder);

			// If not, append another widget placeholder
			if (!hasPlaceholder)
			{
				this._widgetPlaceholder = document.createElement("span");
				this._widgetSurroundings.push(this._widgetPlaceholder);

				this._ownPlaceholder = true;
			}

			// If the surroundings array only contains one element, set this one
			// as the widget container
			if (this._widgetSurroundings.length == 1)
			{
				if (this._widgetSurroundings[0] == this._widgetPlaceholder)
				{
					this._widgetContainer = null;
				}
				else
				{
					this._widgetContainer = this._widgetSurroundings[0];
				}
			}
			else
			{
				// Create an outer "span" as widgetContainer
				this._widgetContainer = document.createElement("span");

				// Append the children inside the widgetSurroundings array to
				// the widget container
				for (var i = 0; i < this._widgetSurroundings.length; i++)
				{
					this._widgetContainer.appendChild(this._widgetSurroundings[i]);
				}
			}
		}
		else
		{
			this._widgetContainer = null;
			this._widgetPlaceholder = null;
		}

		this._surroundingsUpdated = false;

		return true;
	},

	update: function() {
		if (this._surroundingsUpdated)
		{
			var attached = this.widget ? this.widget.isAttached() : false;

			// Reattach the widget - this will call the "getDOMNode" function
			// and trigger the _rebuildContainer function.
			if (attached && this.widget)
			{
				this.widget.detatchFromDOM();
				this.widget.attachToDOM();
			}
		}
	},

	getDOMNode: function(_widgetNode) {
		// Update the whole widgetContainer if this is not the first time this
		// function has been called but the widget node has changed.
		if (this._widgetNode != null && this._widgetNode != _widgetNode)
		{
			this._surroundingsUpdated = true;
		}

		// Copy a reference to the given node
		this._widgetNode = _widgetNode;

		// Build the container if it didn't exist yet.
		var updated = this._rebuildContainer(true);

		// Return the widget node itself if there are no surroundings arround
		// it
		if (this._widgetContainer == null)
		{
			return _widgetNode;
		}

		// Replace the widgetPlaceholder with the given widget node if the
		// widgetContainer has been updated
		if (updated)
		{
			this._widgetPlaceholder.parentNode.replaceChild(_widgetNode,
				this._widgetPlaceholder);
			if (!this._ownPlaceholder)
			{
				this._widgetPlaceholder = _widgetNode;
			}
		}

		// Return the widget container
		return this._widgetContainer;
	}

});


