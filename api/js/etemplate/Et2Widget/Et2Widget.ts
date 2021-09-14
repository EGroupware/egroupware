import {et2_IDOMNode, et2_implements_registry} from "../et2_core_interfaces";
import {et2_arrayMgr} from "../et2_core_arrayMgr";
import {et2_attribute_registry, et2_registry, et2_widget} from "../et2_core_widget";
import type {etemplate2} from "../etemplate2";
import {et2_compileLegacyJS} from "../et2_core_legacyJSFunctions";
import {et2_cloneObject, et2_csvSplit} from "../et2_core_common";
// @ts-ignore
import type {IegwAppLocal} from "../../jsapi/egw_global";
import {ClassWithAttributes, ClassWithInterfaces} from "../et2_core_inheritance";
import {dedupeMixin} from "@lion/core";
import type {et2_container} from "../et2_core_baseWidget";

/**
 * This mixin will allow any LitElement to become an Et2Widget
 *
 * Usage:
 * @example
 * export class Et2Loading extends Et2Widget(BXLoading) { ... }
 * @example
 * export class Et2Button extends Et2InputWidget(Et2Widget(BXButton)) { ... }
 *
 * @see Mixin explanation https://lit.dev/docs/composition/mixins/
 */
function applyMixins(derivedCtor : any, baseCtors : any[])
{
	baseCtors.forEach(baseCtor =>
	{
		Object.getOwnPropertyNames(baseCtor.prototype).forEach(name =>
		{
			if(name !== 'constructor')
			{
				derivedCtor.prototype[name] = baseCtor.prototype[name];
			}
		});
	});
}

const Et2WidgetMixin = (superClass) =>
{
	class Et2WidgetClass extends superClass implements et2_IDOMNode
	{

		/** et2_widget compatability **/
		protected _mgrs : et2_arrayMgr[] = [];
		protected _parent : Et2WidgetClass | et2_widget | null = null;
		private _inst : etemplate2 | null = null;
		private supportedWidgetClasses = [];

		/**
		 * Not actually required by et2_widget, but needed to keep track of non-webComponent children
		 */
		private _legacy_children : et2_widget[] = [];

		/**
		 * Keep track of child widgets
		 * This can differ from this.children, as it only includes the widgets where this.children will be child DOM nodes,
		 * not guaranteed to be widgets
		 */
		private _children : (et2_widget | Et2WidgetClass)[] = [];

		/**
		 * Properties - default values, and actually creating them as fields
		 */
		protected _widget_id : string = "";
		protected _dom_id : string = "";
		protected _label : string = "";
		private statustext : string = "";
		protected disabled : Boolean = false;


		/** WebComponent **/
		static get properties()
		{
			return {
				...super.properties,

				/**
				 * CSS Class.  This class is applied to the _outside_, on the web component itself.
				 * Due to how WebComponents work, this might not change anything inside the component.
				 */
				class: {type: String, reflect: true},

				/**
				 * Defines whether this widget is visible.
				 * Not to be confused with an input widget's HTML attribute 'disabled'.",
				 */
				disabled: {
					type: Boolean,
					reflect: true
				},

				/**
				 * Tooltip which is shown for this element on hover
				 */
				statustext: {type: String},

				// Defined in parent hierarchy
				//label: {type: String},

				onclick: {
					type: Function
				},

				/*** Style type attributes ***/
				/**
				 * Used by Et2Box to determine alignment.
				 * Allowed values are left, right
				 */
				align: {
					type: String,
					reflect: true
				},

				/**
				 * Allow styles to be set on widgets.
				 * Any valid CSS will work.  Mostly for testing, maybe we won't use this?
				 * That kind of style should normally go in the app.css
				 */
				style: {
					type: String,
					reflect: true
				}
			};
		}

		/**
		 * Widget Mixin constructor
		 *
		 * Note the ...args parameter and super() call
		 *
		 * @param args
		 */
		constructor(...args : any[])
		{
			super(...args);

			this.addEventListener("click", this._handleClick.bind(this));
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.set_label(this._label);

			if(this.statustext)
			{
				this.egw().tooltipBind(this, this.statustext);
			}
		}

		disconnectedCallback()
		{
			this.egw()?.tooltipUnbind(this);

			this.removeEventListener("click", this._handleClick.bind(this));
		}

		/**
		 * NOT the setter, since we cannot add to the DOM before connectedCallback()
		 *
		 * TODO: This is not best practice.  Should just set property, DOM modification should be done in render
		 * https://lit-element.polymer-project.org/guide/templates#design-a-performant-template
		 *
		 * @param value
		 */
		set_label(value : string)
		{
			let oldValue = this._label;

			// Remove old
			let oldLabels = this.getElementsByClassName("et2_label");
			while(oldLabels[0])
			{
				this.removeChild(oldLabels[0]);
			}

			this._label = value;
			if(value)
			{
				let label = document.createElement("span");
				label.classList.add("et2_label");
				label.textContent = this._label;
				// We should have a slot in the template for the label
				//label.slot="label";
				this.appendChild(label);
				this.requestUpdate('label', oldValue);
			}
		}

		/**
		 * Wrapper on this.disabled because legacy had it.
		 *
		 * @param {boolean} value
		 */
		set_disabled(value : boolean)
		{
			this.disabled = value;
		}

		/**
		 * Get the actual DOM ID, which has been prefixed to make sure it's unique.
		 *
		 * @returns {string}
		 */
		get dom_id()
		{
			return this.getAttribute("id");
		}

		/**
		 * Set the ID of the widget
		 *
		 * This is the "widget" ID, which is used as an index into the managed arrays (content, etc) and when
		 * trying to find widgets by ID.
		 *
		 * This is not the DOM ID.
		 *
		 * @param {string} value
		 */
		set id(value)
		{
			this._widget_id = value;
			this.setAttribute("id", this._widget_id ? this.getInstanceManager().uniqueId + '_' + this._widget_id.replace(/\./g, '-') : "");
		}

		/**
		 * Get the ID of the widget
		 *
		 * @returns {string}
		 */
		get id()
		{
			return this._widget_id;
		}

		set label(value : string)
		{
			let oldValue = this.label;
			this._label = value;
			this.requestUpdate('label', oldValue);
		}

		set class(value : string)
		{
			let oldValue = this.classList.value;
			this.classList.value = value;

			this.requestUpdate('class', oldValue);
		}

		get class()
		{
			return this.classList.value;
		}

		/**
		 * Event handlers
		 */

		/**
		 * Click handler calling custom handler set via onclick attribute to this.onclick
		 *
		 * @param _ev
		 * @returns
		 */
		_handleClick(_ev : MouseEvent) : boolean
		{
			if (typeof this.onclick == 'function')
			{
				// Make sure function gets a reference to the widget, splice it in as 2. argument if not
				var args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1) args.splice(1, 0, this);

				return this.onclick(...args);
			}

			return true;
		}

		/** et2_widget compatability **/
		destroy()
		{
			// Not really needed, use the disconnectedCallback() and let the browser handle it
		}

		isInTree() : boolean
		{
			// TODO: Probably should watch the state or something
			return true;
		}

		/**
		 * Loads the widget tree from an XML node
		 *
		 * @param _node xml node
		 */
		loadFromXML(_node)
		{
			// Load the child nodes.
			for(var i = 0; i < _node.childNodes.length; i++)
			{
				var node = _node.childNodes[i];
				var widgetType = node.nodeName.toLowerCase();

				if(widgetType == "#comment")
				{
					continue;
				}

				if(widgetType == "#text")
				{
					if(node.data.replace(/^\s+|\s+$/g, ''))
					{
						this.innerText = node.data;
					}
					continue;
				}

				// Create the new element
				this.createElementFromNode(node);
			}
		}

		/**
		 * Create a et2_widget from an XML node.
		 *
		 * First the type and attributes are read from the node.  Then the readonly & modifications
		 * arrays are checked for changes specific to the loaded data.  Then the appropriate
		 * constructor is called.  After the constructor returns, the widget has a chance to
		 * further initialize itself from the XML node when the widget's loadFromXML() method
		 * is called with the node.
		 *
		 * @param _node XML node to read
		 * @param _name XML node name
		 *
		 * @return et2_widget
		 */
		createElementFromNode(_node, _name?)
		{
			var attributes = {};

			// Parse the "readonly" and "type" flag for this element here, as they
			// determine which constructor is used
			let _nodeName = attributes["type"] = _node.getAttribute("type") ?
												 _node.getAttribute("type") : _node.nodeName.toLowerCase();
			const readonly = attributes["readonly"] = this.getArrayMgr("readonlys") ?
													  (<any>this.getArrayMgr("readonlys")).isReadOnly(
														  _node.getAttribute("id"), _node.getAttribute("readonly"),
														  typeof this.readonly !== "undefined" ? this.readonly : false) : false;

			// Check to see if modifications change type
			var modifications = this.getArrayMgr("modifications");
			if(modifications && _node.getAttribute("id"))
			{
				let entry : any = modifications.getEntry(_node.getAttribute("id"));
				if(entry == null)
				{
					// Try again, but skip the fancy stuff
					// TODO: Figure out why the getEntry() call doesn't always work
					entry = modifications.data[_node.getAttribute("id")];
					if(entry)
					{
						this.egw().debug("warn", "getEntry(" + _node.getAttribute("id") + ") failed, but the data is there.", modifications, entry);
					}
					else
					{
						// Try the root, in case a namespace got missed
						entry = modifications.getRoot().getEntry(_node.getAttribute("id"));
					}
				}
				if(entry && entry.type && typeof entry.type === 'string')
				{
					_nodeName = attributes["type"] = entry.type;
				}
				entry = null;
			}

			// if _nodeName / type-attribute contains something to expand (eg. type="@${row}[type]"),
			// we need to expand it now as it defines the constructor and by that attributes parsed via parseXMLAttrs!
			if(_nodeName.charAt(0) == '@' || _nodeName.indexOf('$') >= 0)
			{
				_nodeName = attributes["type"] = this.getArrayMgr('content').expandName(_nodeName);
			}

			let widget = null;
			if(undefined == window.customElements.get(_nodeName))
			{
				// Get the constructor - if the widget is readonly, use the special "_ro"
				// constructor if it is available
				var constructor = et2_registry[typeof et2_registry[_nodeName] == "undefined" ? 'placeholder' : _nodeName];
				if(readonly === true && typeof et2_registry[_nodeName + "_ro"] != "undefined")
				{
					constructor = et2_registry[_nodeName + "_ro"];
				}

				// Parse the attributes from the given XML attributes object
				this.parseXMLAttrs(_node.attributes, attributes, constructor.prototype);

				// Do an sanity check for the attributes
				ClassWithAttributes.generateAttributeSet(et2_attribute_registry[constructor.name], attributes);

				// Creates the new widget, passes this widget as an instance and
				// passes the widgetType. Then it goes on loading the XML for it.
				widget = new constructor(this, attributes);

				// Load the widget itself from XML
				widget.loadFromXML(_node);
			}
			else
			{
				if(readonly === true && typeof window.customElements.get(_nodeName + "_ro") != "undefined")
				{
					_nodeName += "_ro";
				}
				widget = loadWebComponent(_nodeName, _node, this);

				if(this.addChild)
				{
					// webcomponent going into old et2_widget
					this.addChild(widget);
				}
			}
			return widget;
		}


		/**
		 * The parseXMLAttrs function takes an XML DOM attributes object
		 * and adds the given attributes to the _target associative array. This
		 * function also parses the legacyOptions.
		 *
		 * @param _attrsObj is the XML DOM attributes object
		 * @param {object} _target is the object to which the attributes should be written.
		 * @param {et2_widget} _proto prototype with attributes and legacyOptions attribute
		 */
		parseXMLAttrs(_attrsObj, _target, _proto)
		{
			// Check whether the attributes object is really existing, if not abort
			if(typeof _attrsObj == "undefined")
			{
				return;
			}

			// Iterate over the given attributes and parse them
			var mgr = this.getArrayMgr("content");
			for(var i = 0; i < _attrsObj.length; i++)
			{
				var attrName = _attrsObj[i].name;
				var attrValue = _attrsObj[i].value;

				// Special handling for the legacy options
				if(attrName == "options" && _proto.constructor.legacyOptions && _proto.constructor.legacyOptions.length > 0)
				{
					let legacy = _proto.constructor.legacyOptions || [];
					let attrs = et2_attribute_registry[Object.getPrototypeOf(_proto).constructor.name] || {};
					// Check for modifications on legacy options here.  Normal modifications
					// are handled in widget constructor, but it's too late for legacy options then
					if(_target.id && this.getArrayMgr("modifications").getEntry(_target.id))
					{
						var mod : any = this.getArrayMgr("modifications").getEntry(_target.id);
						if(typeof mod.options != "undefined") attrValue = _attrsObj[i].value = mod.options;
					}
					// expand legacyOptions with content
					if(attrValue.charAt(0) == '@' || attrValue.indexOf('$') != -1)
					{
						attrValue = mgr.expandName(attrValue);
					}

					// Parse the legacy options (as a string, other types not allowed)
					var splitted = et2_csvSplit(attrValue + "");

					for(var j = 0; j < splitted.length && j < legacy.length; j++)
					{
						// Blank = not set, unless there's more legacy options provided after
						if(splitted[j].trim().length === 0 && legacy.length >= splitted.length) continue;

						// Check to make sure we don't overwrite a current option with a legacy option
						if(typeof _target[legacy[j]] === "undefined")
						{
							attrValue = splitted[j];

							/**
						If more legacy options than expected, stuff them all in the last legacy option
						Some legacy options take a comma separated list.
							 */
							if(j == legacy.length - 1 && splitted.length > legacy.length)
							{
								attrValue = splitted.slice(j);
							}

							var attr = et2_attribute_registry[_proto.constructor.name][legacy[j]] || {};

							// If the attribute is marked as boolean, parse the
							// expression as bool expression.
							if(attr.type == "boolean")
							{
								attrValue = mgr.parseBoolExpression(attrValue);
							}
							else if(typeof attrValue != "object")
							{
								attrValue = mgr.expandName(attrValue);
							}
							_target[legacy[j]] = attrValue;
						}
					}
				}
				else if(attrName == "readonly" && typeof _target[attrName] != "undefined")
				{
					// do NOT overwrite already evaluated readonly attribute
				}
				else
				{
					let attrs = et2_attribute_registry[_proto.constructor.name] || {};
					if(mgr != null && typeof attrs[attrName] != "undefined")
					{
						var attr = attrs[attrName];

						// If the attribute is marked as boolean, parse the
						// expression as bool expression.
						if(attr.type == "boolean")
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
		}

		iterateOver(_callback : Function, _context, _type)
		{
			if(typeof _type == "undefined" || et2_implements_registry[_type] && et2_implements_registry[_type](this))
			{
				_callback.call(_context, this);
			}

			// Ask children
			for(let i = 0; i < this._children.length; i++)
			{
				this._children[i].iterateOver(_callback, _context, _type);
			}
		}

		/**
		 * Needed for legacy compatability.
		 *
		 * @param {Promise[]} promises List of promises from widgets that are not done.  Pass an empty array, it will be filled if needed.
		 */
		loadingFinished(promises)
		{
			/**
			 * This is needed mostly as a bridge between non-WebComponent widgets and
			 * connectedCallback().  It's not really needed if the whole tree is WebComponent.
			 * WebComponents can be added as children immediately after creation, and they handle the
			 * rest themselves with their normal lifecycle (especially connectedCallback(), which is kind
			 * of the equivalent of doLoadingFinished()
			 */
			if(this.getParent() instanceof et2_widget && this.getParent().getDOMNode(this))
			{
				this.getParent().getDOMNode(this).append(this);
			}

			// An empty text node causes problems with legacy widget children
			// It throws off their insertion indexing, making them get added in the wrong place
			if(this.childNodes[0]?.nodeType == this.TEXT_NODE)
			{
				this.removeChild(this.childNodes[0]);
			}
			for(let i = 0; i < this.getChildren().length; i++)
			{
				let child = this.getChildren()[i];

				child.loadingFinished(promises);
			}
		}

		getWidgetById(_id)
		{
			if(this.id == _id)
			{
				return this;
			}
			if(this.getChildren().length == 0)
			{
				return null;
			}

			let check_children = children =>
			{
				for(var i = 0; i < children.length; i++)
				{
					var elem = children[i].getWidgetById(_id);

					if(elem != null)
					{
						return elem;
					}
				}
				if(this.id && _id.indexOf('[') > -1 && children.length)
				{
					var ids = (new et2_arrayMgr()).explodeKey(_id);
					var widget : Et2WidgetClass = this;
					for(var i = 0; i < ids.length && widget !== null; i++)
					{
						widget = widget.getWidgetById(ids[i]);
					}
					return widget;
				}
			};

			return check_children(this.getChildren()) || null;
		}

		setParent(new_parent : Et2WidgetClass | et2_widget)
		{
			this._parent = new_parent;

			if(this.id)
			{
				// Create a namespace for this object
				if(this._createNamespace())
				{
					this.checkCreateNamespace();
				}
			}
			this._parent.addChild(this);
		}

		getParent() : HTMLElement | et2_widget
		{
			let parentNode = this.parentNode;

			// If parent is an old et2_widget, use it
			if(this._parent)
			{
				return this._parent;
			}

			return <HTMLElement>parentNode;
		}

		addChild(child : et2_widget | Et2WidgetClass)
		{
			if(this._children.indexOf(child) >= 0)
			{
				return;
			}
			if(child instanceof et2_widget)
			{
				child._parent = this;

				// During legacy widget creation, the child's DOM node won't be available yet.
				this._legacy_children.push(child);
				let child_node = null;
				try
				{
					child_node = typeof child.getDOMNode !== "undefined" ? child.getDOMNode(child) : null;
				}
				catch(e)
				{
					// Child did not give up its DOM node nicely but errored instead
				}
				if(child_node && child_node !== this)
				{
					this.append(child_node);
				}
			}
			else
			{
				this.append(child);
			}
			this._children.push(child);
		}

		/**
		 * Get child widgets
		 * Use <obj>.children to get web component children
		 * @returns {et2_widget[]}
		 */
		getChildren()
		{
			return this._children;
		}

		getType() : string
		{
			return this.nodeName;
		}

		getDOMNode() : HTMLElement
		{
			return <HTMLElement><unknown>this;
		}

		/**
		 * Creates a copy of this widget.
		 *
		 * @param {et2_widget} _parent parent to set for clone, default null
		 */
		clone(_parent?) : Et2WidgetClass
		{
			// Default _parent to null
			if(typeof _parent == "undefined")
			{
				_parent = null;
			}

			// Create the copy
			var copy = <Et2WidgetClass>this.cloneNode();

			let widget_class = window.customElements.get(this.nodeName);
			let properties = widget_class ? widget_class.properties() : {};

			if(_parent)
			{
				copy.setParent(_parent);
			}
			else
			{
				// Copy a reference to the content array manager
				copy.setArrayMgrs(this.getArrayMgrs());

				// Pass on instance too
				copy.setInstanceManager(this.getInstanceManager());
			}

			// Create a clone of all child widgets of the given object
			for(var i = 0; i < this.getChildren().length; i++)
			{
				this.getChildren()[i].clone(copy);
			}

			return copy;
		}


		/**
		 * Sets the array manager for the given part
		 *
		 * @param {string} _part which array mgr to set
		 * @param {object} _mgr
		 */
		setArrayMgr(_part : string, _mgr : et2_arrayMgr)
		{
			this._mgrs[_part] = _mgr;
		}

		/**
		 * Returns the array manager object for the given part
		 *
		 * @param {string} managed_array_type name of array mgr to return
		 */
		getArrayMgr(managed_array_type : string) : et2_arrayMgr | null
		{
			if(this._mgrs && typeof this._mgrs[managed_array_type] != "undefined")
			{
				return this._mgrs[managed_array_type];
			}
			else if(this.getParent())
			{
				return this.getParent().getArrayMgr(managed_array_type);
			}

			return null;
		}

		/**
		 * Sets all array manager objects - this function can be used to set the
		 * root array managers of the container object.
		 *
		 * @param {object} _mgrs
		 */
		setArrayMgrs(_mgrs)
		{
			this._mgrs = <et2_arrayMgr[]>et2_cloneObject(_mgrs);
		}

		/**
		 * Returns an associative array containing the top-most array managers.
		 *
		 * @param _mgrs is used internally and should not be supplied.
		 */
		getArrayMgrs(_mgrs? : object)
		{
			if(typeof _mgrs == "undefined")
			{
				_mgrs = {};
			}

			// Add all managers of this object to the result, if they have not already
			// been set in the result
			for(var key in this._mgrs)
			{
				if(typeof _mgrs[key] == "undefined")
				{
					_mgrs[key] = this._mgrs[key];
				}
			}

			// Recursively applies this function to the parent widget
			if(this._parent)
			{
				this._parent.getArrayMgrs(_mgrs);
			}

			return _mgrs;
		}

		/**
		 * Checks whether a namespace exists for this element in the content array.
		 * If yes, an own perspective of the content array is created. If not, the
		 * parent content manager is used.
		 *
		 * Constructor attributes are passed in case a child needs to make decisions
		 */
		checkCreateNamespace()
		{
			// Get the content manager
			var mgrs = this.getArrayMgrs();

			for(var key in mgrs)
			{
				var mgr = mgrs[key];

				// Get the original content manager if we have already created a
				// perspective for this node
				if(typeof this._mgrs[key] != "undefined" && mgr.perspectiveData.owner == this)
				{
					mgr = mgr.parentMgr;
				}

				// Check whether the manager has a namespace for the id of this object
				var entry = mgr.getEntry(this.id);
				if(typeof entry === 'object' && entry !== null || this.id)
				{
					// The content manager has an own node for this object, so
					// create an own perspective.
					this._mgrs[key] = mgr.openPerspective(this, this.id);
				}
				else
				{
					// The current content manager does not have an own namespace for
					// this element, so use the content manager of the parent.
					delete (this._mgrs[key]);
				}
			}
		}

		/**
		 * Set the instance manager
		 * Normally this is not needed as it's set on the top-level container, and we just return that reference
		 *
		 */
		setInstanceManager(manager : etemplate2)
		{
			this._inst = manager;
		}

		/**
		 * Returns the instance manager
		 *
		 * @return {etemplate2}
		 */
		getInstanceManager()
		{
			if(this._inst != null)
			{
				return this._inst;
			}
			else if(this.getParent())
			{
				return this.getParent().getInstanceManager();
			}

			return null;
		}

		/**
		 * Returns the base widget
		 * Usually this is the same as getInstanceManager().widgetContainer
		 */
		getRoot() : et2_container
		{
			if(this.getParent() != null)
			{
				return this.getParent().getRoot();
			}
			else
			{
				return <et2_container><unknown>this;
			}
		}

		/**
		 * Returns the path into the data array.  By default, array manager takes care of
		 * this, but some extensions need to override this
		 */
		getPath()
		{
			var path = this.getArrayMgr("content").getPath();

			// Prevent namespaced widgets with value from going an extra layer deep
			if(this.id && this._createNamespace() && path[path.length - 1] == this.id) path.pop();

			return path;
		}

		_createNamespace() : boolean
		{
			return false;
		}

		egw() : IegwAppLocal
		{
			if(this.getParent() != null && !(this.getParent() instanceof HTMLElement))
			{
				return (<et2_widget>this.getParent()).egw();
			}

			// Get the window this object belongs to
			var wnd = null;
			// @ts-ignore Technically this doesn't have implements(), but it's mixed in
			if(this.implements(et2_IDOMNode))
			{
				var node = (<et2_IDOMNode><unknown>this).getDOMNode();
				if(node && node.ownerDocument)
				{
					wnd = node.ownerDocument.parentNode || node.ownerDocument.defaultView;
				}
			}

			// If we're the root object, return the phpgwapi API instance
			return typeof egw === "function" ? egw('phpgwapi', wnd) : null;
		}
	};

	// Add some more stuff in
	applyMixins(Et2WidgetClass, [ClassWithInterfaces]);

	return Et2WidgetClass;
}
export const Et2Widget = dedupeMixin(Et2WidgetMixin);

/**
 * Load a Web Component
 * @param _nodeName
 * @param _template_node
 */
export function loadWebComponent(_nodeName : string, _template_node, parent : Et2Widget | et2_widget) : HTMLElement
{
	// @ts-ignore
	let widget = <Et2Widget>document.createElement(_nodeName);
	widget.textContent = _template_node.textContent;

	const widget_class = window.customElements.get(_nodeName);
	if(!widget_class)
	{
		throw Error("Unknown or unregistered WebComponent '" + _nodeName + "', could not find class");
	}
	widget.setParent(parent);
	var mgr = widget.getArrayMgr("content");

	// Set read-only.  Doesn't really matter if it's a ro widget, but otherwise it needs set
	widget.readOnly = parent.getArrayMgr("readonlys") ?
					  (<any>parent.getArrayMgr("readonlys")).isReadOnly(
						  _template_node.getAttribute("id"), _template_node.getAttribute("readonly"),
						  typeof parent.readonly !== "undefined" ? parent.readonly : false) : false;

	// Apply any set attributes - widget will do its own coercion
	_template_node.getAttributeNames().forEach(attribute =>
	{
		let attrValue = _template_node.getAttribute(attribute);

		// If there is not attribute set, ignore it.  Widget sets its own default.
		if(typeof attrValue === "undefined") return;
		const property = widget_class.getPropertyOptions(attribute);

		switch(property.type)
		{
			case Boolean:
				// If the attribute is marked as boolean, parse the
				// expression as bool expression.
				attrValue = mgr.parseBoolExpression(attrValue);
				break;
			case Function:
				// We parse it into a function here so we can pass in the widget as context.
				// Leaving it to the LitElement conversion loses the widget as context
				if(typeof attrValue !== "function")
				{
					attrValue = et2_compileLegacyJS(attrValue, widget, widget);
				}
				break;
			default:
				attrValue = mgr.expandName(attrValue);
				break;
		}

		// Set as attribute or property, as appropriate
		if(widget.getAttributeNames().indexOf(attribute) >= 0)
		{
			// Set as attribute (reflected in DOM)
			widget.setAttribute(attribute, attrValue);
		}
		else
		{
			// Set as property, not attribute
			widget[attribute] = attrValue;
		}
	});

	if(widget_class.getPropertyOptions("value") && widget.set_value)
	{
		if(mgr != null)
		{
			let val = mgr.getEntry(widget.id, false, true);
			if(val !== null)
			{
				widget.set_value(val);
			}
		}
	}

	// Children need to be loaded
	widget.loadFromXML(_template_node);

	return widget;
}