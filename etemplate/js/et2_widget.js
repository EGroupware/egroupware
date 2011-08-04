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

/*egw:uses
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
	for (var i in _types)
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
et2_widget = Class.extend({

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
		if (_parent != null)
		{
			this._parent.addChild(this);
		}

		this._children = [];
		this.id = "";
		this.type = _type;
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
		for (var i = this._children.length; i >= 0; i--)
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
	},

	/**
	 * Returns the parent widget of this widget
	 */
	getParent: function() {
		return this._parent;
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
		if (_node instanceof et2_widget)
		{
			_node.parent = this;
			this._children.splice(_idx, 0, _node);
		}
		else
		{
			throw("_node is not an instance of et2_widget!");
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

			// Check whether a widget with the given type is registered.
			var constructor = typeof et2_registry[widgetType] == "undefined" ?
				et2_placeholder : et2_registry[widgetType];

			// Creates the new widget, passes this widget as an instance and
			// passes the widgetType. Then it goes on loading the XML for it.
			(new constructor(this, widgetType)).loadFromXML(node);
		}
	},

	/**
	 * Loads the widget attributes from the passed DOM attributes array.
	 */
	loadAttributes: function(_attrs) {
		for (var i = 0; i < _attrs.length; i++)
		{
			var attr = _attrs[i];

			// Check whether a setter exists for the given attribute
			if (typeof this["set_" + attr.name] == "function" && attr.name.charAt(0) != "_")
			{
				this["set_" + attr.name](attr.value);
			}
		}
	},

	/**
	 * Calls the setter of each property with its current value, calls the
	 * update function of all child nodes.
	 */
	update: function() {
		// Go through every property of this object and check whether a
		// corresponding setter function exists. If yes, it is called.
		for (var key in this)
		{
			if (typeof this["set_" + key] == "function" && key.charAt(0) != "_")
			{
				this["set_" + key](this[key]);
			}
		}

		// Call the update function of all children.
		for (var i in this._children)
		{
			this._children[i].update();
		}
	},

	get_id: function() {
		return this.id;
	},

	set_id: function(_value) {
		this.id = _value;
	},

	get_type: function() {
		return this.type;
	}
});

/**
 * Interface for all widget classes, which are based on a DOM node.
 */
et2_IDOMNode = {
	getDOMNode: function() {}
}

/**
 * Abstract widget class which can be inserted into the DOM. All widget classes
 * deriving from this class have to care about implementing the "getDOMNode"
 * function which has to return the DOM-Node.
 */
et2_DOMWidget = et2_widget.extend(et2_IDOMNode, {

	/**
	 * When the DOMWidget is initialized, it grabs the DOM-Node of the parent
	 * object (if available) and passes it to its own "createDOMNode" function
	 */
	init: function(_parent, _type) {

		// Call the inherited constructor
		this._super.apply(this, arguments);

		this.parentNode = null;

		// Check whether the parent implements the et2_IDOMNode interface. If
		// yes, grab the DOM node and create our own.
		if (this._parent && this._parent.implements(et2_IDOMNode)) {
			this.setParentDOMNode(this._parent.getDOMNode());
		}
	},

	destroy: function() {

		this.detatchFromDOM();

		this._super();
	},

	detatchFromDOM: function() {
		if (this.parentNode)
		{
			var node = this.getDOMNode();

			if (node)
			{
				this.parentNode.removeChild(node);
			}
		}
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

			// Attach the DOM node of this widget (if existing) to the new parent
			var node = this.getDOMNode();
			if (node)
			{
				this.parentNode.appendChild(node);
			}
		}
	},

	getParentDOMNode: function() {
		return this.parentNode;
	},

	set_id: function(_value) {
		this._super(_value);

		var node = this.getDOMNode();
		if (node)
		{
			node.setAttribute("id", _value);
		}
	}

});

/**
 * Container object for not-yet supported widgets
 */
et2_placeholder = et2_DOMWidget.extend({

	init: function() {
		this.placeDiv = document.createElement("span");

		this._super.apply(this, arguments);
	},

	getDOMNode: function() {
		return this.placeDiv;
	}

});

/**
 * Common container object
 */
et2_container = et2_DOMWidget.extend({

	init: function() {
		this.div = document.createElement("div");

		this._super.apply(this, arguments);
	},

	getDOMNode: function() {
		return this.div;
	}

});

