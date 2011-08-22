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
	et2_widget;
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
	},

	/**
	 * Detatches the node from the DOM and clears all references to the parent
	 * node or the dom node of this widget.
	 */
	destroy: function() {

		this.detatchFromDOM();
		this.parentNode = null;
		this._attachSet = {};

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


