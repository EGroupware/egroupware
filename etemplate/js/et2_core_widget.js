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
	jsapi.egw;
	et2_core_xml;
	et2_core_common;
	et2_core_inheritance;
	et2_core_arrayMgr;
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
 * Creates a widget registered for the given tag-name. If "readonly" is listed
 * inside the attributes, et2_createWidget will try to use the "_ro" type of the
 * widget.
 *
 * @param _name is the name of the widget with which it is registered. If the
 * 	widget is not found, an et2_placeholder will be created.
 * @param _attrs is an associative array with attributes. If not passed, it will
 * 	default to true.
 * @param _parent is the parent to which the element will be attached. If _parent
 * 	is not passed, it will default to null. Then you have to attach the element
 * 	to a parent using the addChild or insertChild method.
 */
function et2_createWidget(_name, _attrs, _parent)
{
	if (typeof _attrs == "undefined")
	{
		_attrs = {};
	}

	if (typeof _parent == "undefined")
	{
		_parent = null;
	}

	// Parse the "readonly" and "type" flag for this element here, as they
	// determine which constructor is used
	var nodeName = _attrs["type"] = _name;
	var readonly = _attrs["readonly"] = 
		typeof _attrs["readonly"] == "undefined" ? false : _attrs["readonly"];

	// Get the constructor - if the widget is readonly, use the special "_ro"
	// constructor if it is available
	var constructor = typeof et2_registry[nodeName] == "undefined" ?
		et2_placeholder : et2_registry[nodeName];
	if (readonly && typeof et2_registry[nodeName + "_ro"] != "undefined")
	{
		constructor = et2_registry[nodeName + "_ro"];
	}

	// Do an sanity check for the attributes
	constructor.prototype.generateAttributeSet(_attrs);

	// Create the new widget and return it
	return new constructor(_parent, _attrs);
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

		"no_lang": {
			"name": "No translation",
			"type": "boolean",
			"default": false,
			"description": "If true, no translations are made for this widget"
		},

		/**
		 * Ignore the "span" property by default - it is read by the grid and
		 * other widgets.
		 */
		"span": {
			"ignore": true
		},

		/**
		 * Ignore the "type" tag - it is read by the "createElementFromNode"
		 * function and passed as second parameter of the widget constructor
		 */
		"type": {
			"ignore": true
		},

		/**
		 * Ignore the readonly tag by default - its also read by the
		 * "createElementFromNode" function.
		 */
		"readonly": {
			"ignore": true
		}
	},

	// Set the legacyOptions array to the names of the properties the "options"
	// attribute defines.
	legacyOptions: [],

	/**
	 * Set this variable to true if this widget can have namespaces
	 */
	createNamespace: false,

	/**
	 * The init function is the constructor of the widget. When deriving new
	 * classes from the widget base class, always call this constructor unless
	 * you know what you're doing.
	 * 
	 * @param _parent is the parent object from the XML tree which contains this
	 * 	object. The default constructor always adds the new instance to the
	 * 	children list of the given parent object. _parent may be NULL.
	 * @param _attrs is an associative array of attributes.
	 */
	init: function(_parent, _attrs) {

		// Check whether all attributes are available
		if (typeof _parent == "undefined")
		{
			_parent = null;
		}

		if (typeof _attrs == "undefined")
		{
			_attrs = {};
		}

		// Initialize all important parameters
		this._mgrs = {};
		this._inst = null;
		this._children = [];
		this._type = _attrs["type"];
		this.id = _attrs["id"];

		// Add this widget to the given parent widget
		this._parent = _parent;
		if (_parent != null)
		{
			this._parent.addChild(this);
		}

		// The supported widget classes array defines a whitelist for all widget
		// classes or interfaces child widgets have to support.
		this.supportedWidgetClasses = [et2_widget];

		if (_attrs["id"])
		{
			// Create a namespace for this object
			if (this.createNamespace)
			{
				this.checkCreateNamespace();
			}
		}

		// Add all attributes hidden in the content arrays to the attributes
		// parameter
		this.transformAttributes(_attrs);

		// Create a local copy of the options object
		this.options = et2_cloneObject(_attrs);
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
			this._children[i].free();
		}

		// Remove this element from the parent
		if (this._parent !== null)
		{
			this._parent.removeChild(this);
		}

		// Free the array managers if they belong to this widget
		for (var key in this._mgrs)
		{
			if (this._mgrs[key] && this._mgrs[key].owner == this)
			{
				this._mgrs[key].free();
			}
		}
	},

	/**
	 * Creates a copy of this widget. The parameters given are passed to the
	 * constructor of the copied object. If the parameters are omitted, _parent
	 * is defaulted to null
	 */
	clone: function(_parent) {

		// Default _parent to null
		if (typeof _parent == "undefined")
		{
			_parent = null;
		}

		// Create the copy
		var copy = new (this.constructor)(_parent, this.options);

		// Assign this element to the copy
		copy.assign(this);

		return copy;
	},

	assign: function(_obj) {
		if (typeof _obj._children == "undefined")
		{
			et2_debug("log", "Foo!");
		}

		// Create a clone of all child elements of the given object
		for (var i = 0; i < _obj._children.length; i++)
		{
			_obj._children[i].clone(this);
		}

		// Copy a reference to the content array manager
		this.setArrayMgrs(_obj.mgrs);
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
		// Check whether the node is one of the supported widget classes.
		if (this.isOfSupportedWidgetClass(_node))
		{
			// Remove the node from its original parent
			if (_node._parent)
			{
				_node._parent.removeChild(_node);
			}

			_node._parent = this;
			this._children.splice(_idx, 0, _node);
		}
		else
		{
			et2_debug("error", this, "Widget is not supported by this widget class", _node);
//			throw("Widget is not supported by this widget class!");
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

		if (this.isInTree() && this.instanceOf(_type))
		{
			_callback.call(_context, this);
		}

		for (var i = 0; i < this._children.length; i++)
		{
			this._children[i].iterateOver(_callback, _context, _type);
		}
	},

	/**
	 * Returns true if the widget currently resides in the visible part of the
	 * widget tree. E.g. Templates which have been cloned are not in the visible
	 * part of the widget tree.
	 * 
	 * @param _vis can be used by widgets overwriting this function - simply
	 * 	write
	 * 		return this._super(inTree);
	 *	when calling this function the _vis parameter does not have to be supplied.
	 */
	isInTree: function(_sender, _vis) {
		if (typeof _vis == "undefined")
		{
			_vis = true;
		}

		if (this._parent)
		{
			return _vis && this._parent.isInTree(this);
		}

		return _vis;
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

	/**
	 * The parseXMLAttrs function takes an XML DOM attributes object
	 * and adds the given attributes to the _target associative array. This
	 * function also parses the legacyOptions.
	 *
	 * @param _attrsObj is the XML DOM attributes object
	 * @param _target is the object to which the attributes should be written.
	 */
	parseXMLAttrs: function(_attrsObj, _target, _proto) {

		// Check whether the attributes object is really existing, if not abort
		if (typeof _attrsObj == "undefined")
		{
			return;
		}

		// Iterate over the given attributes and parse them
		for (var i = 0; i < _attrsObj.length; i++)
		{
			var attrName = _attrsObj[i].name;
			var attrValue = _attrsObj[i].value;

			// Special handling for the legacy options
			if (attrName == "options")
			{
				// Parse the legacy options
				var splitted = et2_csvSplit(attrValue);

				for (var j = 0; j < splitted.length && j < _proto.legacyOptions.length; j++)
				{
					_target[_proto.legacyOptions[j]] = splitted[j];
				}
			}
			else
			{
				var mgr = this.getArrayMgr("content");
				if (mgr != null && typeof _proto.attributes[attrName] != "undefined")
				{
					var attr = _proto.attributes[attrName];

					// If the attribute is marked as boolean, parse the
					// expression as bool expression.
					if (attr.type == "boolean")
					{
						attrValue = mgr.parseBoolExpression(attrValue);
					}
					else
					{
						attrValue = mgr.expandName(attrValue);
					}
				}

				// Set the attribute
				_target[attrName] = attrValue;
			}
		}
	},

	/**
	 * Apply the "modifications" to the element and translate attributes marked
	 * with "translate: true"
	 */
	transformAttributes: function(_attrs) {

		// Apply the content of the modifications array
		if (this.id)
		{
			if (typeof this.id != "string")
			{
				console.log(this.id);
			}

			var data = this.getArrayMgr("modifications").getEntry(this.id);
			if (data instanceof Object)
			{
				for (var key in data)
				{
					if (!(data[key] instanceof Object))
					{
						_attrs[key] = data[key];
					}
				}
			}
		}

		// Translate the attributes
		for (var key in _attrs)
		{
			if (_attrs[key] && typeof this.attributes[key] != "undefined")
			{
				if (this.attributes[key].translate === true || 
				   (this.attributes[key].translate === "!no_lang" && !_attrs["no_lang"]))
				_attrs[key] = egw.lang(_attrs[key]);
			}
		}
	},

	createElementFromNode: function(_node) {
		var attributes = {};

		// Parse the "readonly" and "type" flag for this element here, as they
		// determine which constructor is used
		var _nodeName = attributes["type"] = _node.getAttribute("type") ?
			_node.getAttribute("type") : _node.nodeName.toLowerCase();
		var readonly = attributes["readonly"] =
			this.getArrayMgr("readonlys").isReadOnly(
				_node.getAttribute("id"), _node.getAttribute("readonly"),
				this.readonly);

		// Check to see if modifications change type
		var modifications = this.getArrayMgr("modifications");
		if(modifications && _node.getAttribute("id")) {
			var entry = modifications.getEntry(_node.getAttribute("id"));
			if(entry && entry.type)
			{
				_nodeName = attributes["type"] = entry.type
			}
			entry = null;
		}

		// Get the constructor - if the widget is readonly, use the special "_ro"
		// constructor if it is available
		var constructor = typeof et2_registry[_nodeName] == "undefined" ?
			et2_placeholder : et2_registry[_nodeName];
		if (readonly && typeof et2_registry[_nodeName + "_ro"] != "undefined")
		{
			constructor = et2_registry[_nodeName + "_ro"];
		}

		// Parse the attributes from the given XML attributes object
		this.parseXMLAttrs(_node.attributes, attributes, constructor.prototype);

		// Do an sanity check for the attributes
		constructor.prototype.generateAttributeSet(attributes);

		// Creates the new widget, passes this widget as an instance and
		// passes the widgetType. Then it goes on loading the XML for it.
		var widget = new constructor(this, attributes);

		// Load the widget itself from XML
		widget.loadFromXML(_node);

		return widget;
	},

	/**
	 * Loads the widget tree from an XML node
	 */
	loadFromXML: function(_node) {
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
			this.createElementFromNode(node);
		}
	},

	/**
	 * Called whenever textNodes are loaded from the XML tree
	 */
	loadContent: function(_content) {
	},

	/**
	 * Called when loading the widget (sub-tree) is finished. First when this
	 * function is called, the DOM-Tree is created. loadingFinished is
	 * recursively called for all child elements. Do not directly override this
	 * function but the doLoadingFinished function which is executed before
	 * descending deeper into the DOM-Tree
	 */
	loadingFinished: function() {
		// Call all availble setters
		this.initAttributes(this.options);

		if (this.doLoadingFinished())
		{
			// Descend recursively into the tree
			for (var i = 0; i < this._children.length; i++)
			{
				this._children[i].loadingFinished();
			}
		}
	},

	doLoadingFinished: function() {
		return true;
	},

	/**
	 * Sets all array manager objects - this function can be used to set the
	 * root array managers of the container object.
	 */
	setArrayMgrs: function(_mgrs) {
		this._mgrs = et2_cloneObject(_mgrs);
	},

	/**
	 * Returns an associative array containing the top-most array managers.
	 *
	 * @param _mgrs is used internally and should not be supplied.
	 */
	getArrayMgrs: function(_mgrs) {
		if (typeof _mgrs == "undefined")
		{
			_mgrs = {};
		}

		// Add all managers of this object to the result, if they have not already
		// been set in the result
		for (var key in this._mgrs)
		{
			if (typeof _mgrs[key] == "undefined")
			{
				_mgrs[key] = this._mgrs[key];
			}
		}

		// Recursively applies this function to the parent widget
		if (this._parent)
		{
			this._parent.getArrayMgrs(_mgrs);
		}

		return _mgrs;
	},

	/**
	 * Sets the array manager for the given part
	 */
	setArrayMgr: function(_part, _mgr) {
		this._mgrs[_part] = _mgr;
	},

	/**
	 * Returns the array manager object for the given part
	 */
	getArrayMgr: function(_part) {
		if (typeof this._mgrs[_part] != "undefined")
		{
			return this._mgrs[_part];
		}
		else if (this._parent)
		{
			return this._parent.getArrayMgr(_part);
		}

		return null;
	},

	/**
	 * Checks whether a namespace exists for this element in the content array.
	 * If yes, an own perspective of the content array is created. If not, the
	 * parent content manager is used.
	 */
	checkCreateNamespace: function() {
		// Get the content manager
		var mgrs = this.getArrayMgrs();

		for (var key in mgrs)
		{
			var mgr = mgrs[key];

			// Get the original content manager if we have already created a
			// perspective for this node
			if (typeof this._mgrs[key] != "undefined" && mgr.perspectiveData.owner == this)
			{
				mgr = mgr.parentMgr;
			}

			// Check whether the manager has a namespace for the id of this object
			if (mgr.getEntry(this.id) instanceof Object)
			{
				// The content manager has an own node for this object, so
				// create an own perspective.
				this._mgrs[key] = mgr.openPerspective(this, this.id);
			}
			else
			{
				// The current content manager does not have an own namespace for
				// this element, so use the content manager of the parent.
				delete(this._mgrs[key]);
			}
		}
	},

	/**
	 * Sets the instance manager object (of type etemplate2, see etemplate2.js)
	 */
	setInstanceManager: function(_inst) {
		this._inst = _inst;
	},

	/**
	 * Returns the instance manager
	 */
	getInstanceManager: function() {
		if (this._inst != null)
		{
			return this._inst;
		}
		else if (this._parent)
		{
			return this._parent.getInstanceManager();
		}

		return null;
	},

	/**
	 * Returns the application for the template the widget is in.
	 * If a sub-template is used, this may be different from the current app.
	 */
	getTemplateApp: function() {
		if(this._template_application) 
		{
			return this._template_application;
		}
		else if(this._type == 'template' && this.id)
		{
			var parts = this.id.split(".",2);
			return parts[0];
		} else if (this.getParent()) {
			this._template_application = this.getParent().getTemplateApp();
			return this._template_application;
		}
		app = egw.getAppName() == 'egroupware' ? 'phpgwapi' : egw.getAppName();
		//console.warn("Unable to find template application, using %s", app);
		return app;
	}
});


