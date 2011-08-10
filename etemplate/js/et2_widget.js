/**
 * eGroupWare eTemplate2 - JS Widget base class
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
	jquery.jquery;
	lib/tooltip.js;
	et2_xml;
	et2_common;
	et2_inheritance;
*/

/**
 * The registry contains all XML tag names and the corresponding widget
 * constructor.
 */
var et2_registry = {};

/**
 * Registers the widget class defined by the given constructor and associates it
 * with the types in the _types array.
 */
function et2_register_widget(_constructor, _types)
{
	// Iterate over all given types and register those
	for (var i = 0; i < _types.length; i++)
	{
		var type = _types[i].toLowerCase();

		// Check whether a widget has already been registered for one of the
		// types.
		if (et2_registry[type])
		{
			et2_debug("warn", "Widget class registered for " + type +
				" will be overwritten.");
		}

		et2_registry[type] = _constructor;
	}
}

/**
 * The et2 widget base class.
 */
var et2_widget = Class.extend({

	attributes: {
		"id": {
			"name": "ID",
			"type": "string",
			"description": "Unique identifier of the widget"
		},

		/**
		 * Ignore the "span" property by default - it is read by the grid and
		 * other widgets.
		 */
		"span": {
			"ignore": true
		}
	},

	// Set the legacyOptions array to the names of the properties the "options"
	// attribute defines.
	legacyOptions: [],

	/**
	 * The init function is the constructor of the widget. When deriving new
	 * classes from the widget base class, always call this constructor unless
	 * you know what you're doing.
	 * 
	 * @param _parent is the parent object from the XML tree which contains this
	 * 	object. The default constructor always adds the new instance to the
	 * 	children list of the given parent object. _parent may be NULL.
	 * @param _type is the node name with which the widget has been created. This
	 * 	is usefull if a single widget class implements multiple XET-Node widgets.
	 */
	init: function(_parent, _type) {

		if (typeof _type == "undefined")
		{
			_type = "widget";
		}

		// Copy the parent parameter and add this widget to its parent children
		// list.
		this._parent = _parent;
		this.onSetParent();

		if (_parent != null)
		{
			this._parent.addChild(this);
		}

		this._children = [];
		this.type = _type;

		// The supported widget classes array defines a whitelist for all widget
		// classes or interfaces child widgets have to support.
		this.supportedWidgetClasses = [et2_widget];
	},

	/**
	 * The destroy function destroys all children of the widget, removes itself
	 * from the parents children list.
	 * In all classes derrived from et2_widget ALWAYS override the destroy
	 * function and remove ALL references to other objects. Also remember to
	 * unbind ANY event this widget created and to remove all DOM-Nodes it
	 * created.
	 */
	destroy: function() {

		// Call the destructor of all children
		for (var i = this._children.length - 1; i >= 0; i--)
		{
			this._children[i].destroy();
		}

		// Remove this element from the parent
		if (this._parent !== null)
		{
			this._parent.removeChild(this);
		}

		// Delete all references to other objects
		this._children = [];
		this._parent = null;
		this.onSetParent();
	},

	/**
	 * Creates a copy of this widget. The parameters given are passed to the
	 * constructor of the copied object. If the parameters are omitted, _parent
	 * is defaulted to null
	 */
	clone: function(_parent, _type) {

		// Default _parent to null
		if (typeof _parent == "undefined")
		{
			_parent = null;
		}

		// Create the copy
		var copy = new (this.constructor)(_parent, _type);

		// Assign this element to the copy
		copy.assign(this);

		return copy;
	},

	assign: function(_obj) {

		// Create a clone of all child elements of the given object
		for (var i = 0; i < _obj._children.length; i++)
		{
			_obj._children[i].clone(this, _obj._children[i].type);
		}

		// Copy all properties
		for (var key in  _obj.attributes)
		{
			if (!_obj.attributes[key].ignore && key != "id")
			{
				this.setAttribute(key, _obj.getAttribute(key));
			}
		}
	},

	/**
	 * Returns the parent widget of this widget
	 */
	getParent: function() {
		return this._parent;
	},

	/**
	 * The set parent event is called, whenever the parent of the widget is set.
	 * Child classes can overwrite this function. Whe onSetParent is called,
	 * the change of the parent has already taken place.
	 */
	onSetParent: function() {
	},

	/**
	 * Returns the list of children of this widget.
	 */
	getChildren: function() {
		return this._children;
	},

	/**
	 * Returns the base widget
	 */
	getRoot: function() {
		if (this._parent != null)
		{
			return this._parent.getRoot();
		}
		else
		{
			return this;
		}
	},

	/**
	 * Inserts an child at the end of the list.
	 * 
	 * @param _node is the node which should be added. It has to be an instance
	 * 	of et2_widget
	 */
	addChild: function(_node) {
		this.insertChild(_node, this._children.length);
	},

	/**
	 * Inserts a child at the given index.
	 * 
	 * @param _node is the node which should be added. It has to be an instance
	 * 	of et2_widget
	 * @param _idx is the position at which the element should be added.
	 */
	insertChild: function(_node, _idx) {
		// Check whether the node is one of the supported widget classes.
		if (this.isOfSupportedWidgetClass(_node))
		{
			_node.parent = this;
			this._children.splice(_idx, 0, _node);
		}
		else
		{
			throw("_node is not supported by this widget class!");
		}
	},

	/**
	 * Removes the child but does not destroy it.
	 */
	removeChild: function(_node) {
		// Retrieve the child from the child list
		var idx = this._children.indexOf(_node);

		if (idx >= 0)
		{
			// This element is no longer parent of the child
			_node._parent = null;
			_node.onSetParent();

			this._children.splice(idx, 1);
		}
	},

	/**
	 * Searches an element by id in the tree, descending into the child levels.
	 * 
	 * @param _id is the id you're searching for
	 */
	getWidgetById: function(_id) {
		if (this.id == _id)
		{
			return this;
		}

		for (var i = 0; i < this._children.length; i++)
		{
			var elem = this._children[i].getWidgetById(_id);

			if (elem != null)
			{
				return elem;
			}
		}

		return null;
	},

	/**
	 * Function which allows iterating over the complete widget tree.
	 *
	 * @param _callback is the function which should be called for each widget
	 * @param _context is the context in which the function should be executed
	 * @param _type is an optional parameter which specifies a class/interface
	 * 	the elements have to be instanceOf.
	 */
	iterateOver: function(_callback, _context, _type) {
		if (typeof _type == "undefined")
		{
			_type = et2_widget;
		}

		if (this.instanceOf(_type))
		{
			_callback.call(_context, this);
		}

		for (var i = 0; i < this._children.length; i++)
		{
			this._children[i].iterateOver(_callback, _context, _type);
		}
	},

	isOfSupportedWidgetClass: function(_obj)
	{
		for (var i = 0; i < this.supportedWidgetClasses.length; i++)
		{
			if (_obj.instanceOf(this.supportedWidgetClasses[i]))
			{
				return true;
			}
		}
		return false;
	},

	createElementFromNode: function(_node, _nodeName) {
		if (typeof _nodeName == "undefined")
		{
			_nodeName = _node.nodeName.toLowerCase();
		}

		// Check whether a widget with the given type is registered.
		var constructor = typeof et2_registry[_nodeName] == "undefined" ?
			et2_placeholder : et2_registry[_nodeName];

		// Creates the new widget, passes this widget as an instance and
		// passes the widgetType. Then it goes on loading the XML for it.
		var widget = new constructor(this, _nodeName)
		widget.loadFromXML(_node);

		return widget;
	},

	/**
	 * Loads the widget tree from an XML node
	 */
	loadFromXML: function(_node) {
		// Try to load the attributes of the current node
		if (_node.attributes)
		{
			this.loadAttributes(_node.attributes);
		}

		// Load the child nodes.
		for (var i = 0; i < _node.childNodes.length; i++)
		{
			var node = _node.childNodes[i];
			var widgetType = node.nodeName.toLowerCase();

			if (widgetType == "#comment")
			{
				continue;
			}

			if (widgetType == "#text")
			{
				if (node.data.replace(/^\s+|\s+$/g, ''))
				{
					this.loadContent(node.data);
				}
				continue;
			}

			// Create the new element
			this.createElementFromNode(node, widgetType);
		}
	},

	/**
	 * Loads the widget attributes from the passed DOM attributes array.
	 */
	loadAttributes: function(_attrs) {
		for (var i = 0; i < _attrs.length; i++)
		{
			if (_attrs[i].name == "options")
			{
				// Parse the legacy options
				var splitted = et2_csvSplit(_attrs[i].value);

				for (var i = 0; i < splitted.length && i < this.legacyOptions.length; i++)
				{
					this.setAttribute(this.legacyOptions[i], splitted[i]);
				}
			}
			else
			{
				this.setAttribute(_attrs[i].name, _attrs[i].value);
			}
		}
	},

	/**
	 * Called whenever textNodes are loaded from the XML tree
	 */
	loadContent: function(_content) {
	},

	/**
	 * Calls the setter of each property with its current value, calls the
	 * update function of all child nodes.
	 */
	update: function() {

		// Go through every property of this object and check whether a
		// corresponding setter function exists. If yes, it is called.
		for (var key in  this.attributes)
		{
			if (!this.attributes[key].ignore && key != "id")
			{
				this.setAttribute(key, this.getAttribute(key));
			}
		}

		// Call the update function of all children.
		for (var i = 0; i < this._children.length; i++)
		{
			this._children[i].update();
		}
	}

});

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
	 * 	the DOMNode.depending on that, the widget may return different nodes.
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

	/**
	 * When the DOMWidget is initialized, it grabs the DOM-Node of the parent
	 * object (if available) and passes it to its own "createDOMNode" function
	 */
	init: function(_parent, _type) {
		this.parentNode = null;

		this._attachSet = {
			"node": null,
			"parent": null
		};

		// Call the inherited constructor
		this._super.apply(this, arguments);
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
	 * Automatically tries to attach this node to the parent widget.
	 */
	onSetParent: function() {
		// Check whether the parent implements the et2_IDOMNode interface. If
		// yes, grab the DOM node and create our own.
		if (this._parent && this._parent.implements(et2_IDOMNode)) {
			this.setParentDOMNode(this._parent.getDOMNode(this));
		}
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
	}

});


