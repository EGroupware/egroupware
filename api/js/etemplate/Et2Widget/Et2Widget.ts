import {et2_IDOMNode, et2_implements_registry} from "../et2_core_interfaces";
import {et2_arrayMgr} from "../et2_core_arrayMgr";
import {et2_attribute_registry, et2_registry, et2_widget} from "../et2_core_widget";
import type {etemplate2} from "../etemplate2";
import {et2_compileLegacyJS} from "../et2_core_legacyJSFunctions";
import {et2_cloneObject, et2_csvSplit} from "../et2_core_common";
// @ts-ignore
import type {IegwAppLocal} from "../../jsapi/egw_global";
import {egw} from "../../jsapi/egw_global";
import {ClassWithAttributes, ClassWithInterfaces} from "../et2_core_inheritance";
import {css, dedupeMixin, LitElement, PropertyValues, unsafeCSS} from "@lion/core";
import type {et2_container} from "../et2_core_baseWidget";
import type {et2_DOMWidget} from "../et2_core_DOMWidget";

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

type Constructor<T = LitElement> = new (...args : any[]) => T;
const Et2WidgetMixin = <T extends Constructor>(superClass : T) =>
{
	class Et2WidgetClass extends superClass implements et2_IDOMNode
	{

		protected _mgrs : et2_arrayMgr[] = [];
		protected _parent : Et2WidgetClass | et2_widget | null = null;
		private _inst : etemplate2 | null = null;

		/** et2_widget compatability **/
			// @ts-ignore Some legacy widgets check their parent to see whats allowed
		public supportedWidgetClasses = [];

		/**
		 * If we put the widget somewhere other than as a child of its parent, we need to record that so
		 * we don't move it back to the parent.
		 * @type {Element}
		 * @protected
		 */
		protected _parent_node : Element;
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
		 * Internal Properties - default values, and actually creating them as fields
		 * Do not include public property defined in properties()
		 */
		protected _widget_id : string = "";
		protected _dom_id : string = "";

		/**
		 * TypeScript & LitElement ensure type correctness, so we can't have a string value like "$row_cont[disable_me]"
		 * as a boolean property so we store them here, and parse them when expanding.  Strings do not have this problem,
		 * since $row_cont[disable_me] is still a valid string.
		 */
		protected _deferred_properties : { [key : string] : string } = {};


		/** WebComponent **/
		static get styles()
		{
			return [
				...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
				css`
				:host([disabled]) {
					display: none;
				}
				
				/* CSS to align internal inputs according to box alignment */
				:host([align="center"]) .input-group__input {
					justify-content: center;
				}
				:host([align="right"]) .input-group__input {
					justify-content: flex-end;
				}
            `];
		}

		static get properties()
		{
			return {
				...super.properties,

				/**
				 * Widget ID.  Optional, and not always the same as the DOM ID if the widget is inside something
				 * else that also has an ID.
				 * Putting this in the properties() list causes the parent portion of the DOM ID to be duplicated
				 * due to how LitElement processes the change
				 */
				//id: {type: String, reflect: false},

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
				 * Accesskey provides a hint for generating a keyboard shortcut for the current element.
				 * The attribute value must consist of a single printable character.
				 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes/accesskey
				 */
				accesskey: {type: String, reflect: true},

				/**
				 * Widget ID of another node to insert this node into instead of the normal location
				 * This isn't a normal property...
				 */
				parentId: {type: String, noAccessor: true},

				/**
				 * Tooltip which is shown for this element on hover
				 */
				statustext: {
					type: String,
					reflect: true
				},

				/**
				 * The label of the widget
				 * This is usually displayed in some way.  It's also important for accessability.
				 * This is defined in the parent somewhere, and re-defining it causes labels to disappear
				 */
				label: {
					type: String
				},

				onclick: {
					type: Function
				},

				/*** Style type attributes ***/
				/**
				 * Disable any translations for the widget
				 */
				noLang: {
					type: Boolean,
					reflect: false
				},

				/**
				 * Used by Et2Box to determine alignment.
				 * Allowed values are left, right
				 */
				align: {
					type: String,
					reflect: true
				},

				/**
				 * comma-separated name:value pairs set as data attributes on DOM node
				 * data="mime:${row}[mime]" would generate data-mime="..." in DOM, eg. to use it in CSS on a parent
				 */
				data: {
					type: String,
					reflect: false
				}
			};
		}

		/**
		 * List of properties that get translated
		 * Done separately to not interfere with properties - if we re-define label property,
		 * labels go missing.
		 * @returns {{statustext : boolean, label : boolean}}
		 */
		static get translate()
		{
			return {
				label: true,
				statustext: true
			}
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

			this.disabled = false;
			this._handleClick = this._handleClick.bind(this);

			// make all sizable widgets large by default on mobile template
			if(typeof egwIsMobile == "function" && egwIsMobile())
			{
				this.size = "large";
			}
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.addEventListener("click", this._handleClick);

			if(this.statustext)
			{
				this.egw().tooltipBind(this, this.egw().lang(this.statustext));
			}
		}

		disconnectedCallback()
		{
			this.egw()?.tooltipUnbind(this);

			this.removeEventListener("click", this._handleClick);
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
			let oldValue = this.label;

			// Remove old
			let oldLabels = this.getElementsByClassName("et2_label");
			while(oldLabels[0])
			{
				this.removeChild(oldLabels[0]);
			}

			this.__label = value;
			if(value)
			{
				if(this._labelNode)
				{
					this._labelNode.textContent = this.__label;
				}
				else
				{
					let label = document.createElement("span");
					label.classList.add("et2_label");
					label.textContent = this.__label;
					// We should have a slot in the template for the label
					label.slot = "label";
					this.appendChild(label);
					this.requestUpdate('label', oldValue);
				}
			}
		}

		/**
		 * supports legacy set_statustext
		 * @deprecated use this.statustext
		 * @param value
		 */
		set_statustext(value : string)
		{
			this.statustext = value;
		}

		set statustext(value : string)
		{
			let oldValue = this.__statustext;
			this.__statustext = value;
			this.requestUpdate("statustext", oldValue);
		}

		get statustext() : string
		{
			return this.__statustext;
		}

		/**
		 * Wrapper on this.disabled because legacy had it.
		 *
		 * @param {boolean} value
		 */
		set_disabled(value : boolean)
		{
			let oldValue = this.disabled;
			this.disabled = value;
			this.requestUpdate("disabled", oldValue);
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
			let dom_id = "";
			if(this._widget_id)
			{
				// Create a namespace for this object with new ID
				if(this._createNamespace())
				{
					this.checkCreateNamespace();
				}

				let path = this.getPath();
				if(this.getInstanceManager())
				{
					path.unshift(this.getInstanceManager().uniqueId);
				}
				path.push(value);
				dom_id = path.join("_");
			}
			this.setAttribute("id", dom_id);
			this.requestUpdate("id");
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

		/**
		 * Set the dataset from a CSV
		 * @param {string} value
		 */
		set data(value : string)
		{
			// Clear existing
			Object.keys(this.dataset).forEach(dataKey =>
			{
				delete this.dataset[dataKey];
			});

			let data = value.split(",");
			data.forEach((field) =>
			{
				let f = field.split(":");
				if(f[0] && typeof f[1] !== "undefined")
				{
					this.dataset[f[0]] = f[1];
				}
			});

		}

		get data() : string
		{
			let data = [];
			Object.keys(this.dataset).forEach((k) =>
			{
				data.push(k + ":" + this.dataset[k]);
			})
			return data.join(",");
		}

		/**
		 * A property has changed, and we want to make adjustments to other things
		 * based on that
		 *
		 * @param {import('@lion/core').PropertyValues } changedProperties
		 */
		updated(changedProperties : PropertyValues)
		{
			super.updated(changedProperties);

			// required changed, add / remove validator
			if(changedProperties.has('label'))
			{
				this._set_label(this.label);
			}
			if(changedProperties.has("statustext"))
			{
				this.egw().tooltipUnbind(this);
				if(this.statustext)
				{
					this.egw().tooltipBind(this, this.statustext);
				}
			}
			if(changedProperties.has("onclick"))
			{
				this.classList.toggle("et2_clickable", this.onclick != null && typeof this.onclick != "undefined");
			}
		}

		/**
		 * Any attribute that refers to row content cannot be resolved immediately, but some like booleans cannot stay a
		 * string because it's a boolean attribute.  We store them for later, and parse when they're fully in their row.
		 */
		get deferredProperties()
		{
			return this._deferred_properties;
		}

		set deferredProperties(value)
		{
			this._deferred_properties = value;
		}

		/**
		 * Do some fancy stuff on the label, splitting it up if there's a %s in it
		 *
		 * Normally called from updated(), the "normal" setter stuff has already been run before
		 * this is called.  We only override our special cases (%s) because the normal label has
		 * been set by the parent
		 *
		 * @param value
		 * @protected
		 */
		protected _set_label(value : string)
		{
			if(!this._labelNode)
			{
				return;
			}
			// Remove any existing post label
			let existing = (Array.from(this.children)).find(
				(el : Element) => el.slot === "after" && el.tagName === "LABEL",
			)
			if(existing)
			{
				this.removeChild(existing);
			}

			// Split the label at the "%s"
			let parts = et2_csvSplit(value, 2, "%s");
			if(parts.length > 1)
			{
				let after = document.createElement("label");
				after.slot = "after";
				after.textContent = parts[1];
				this.appendChild(after);

				this._labelNode.textContent = parts[0];
			}
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
		 * Set the widget class
		 *
		 * @deprecated Use this.class or this.classList instead
		 * @param {string} new_class
		 */
		set_class(new_class : string)
		{
			this.class = new_class;
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
			if(typeof this.onclick == 'function')
			{
				// Make sure function gets a reference to the widget, splice it in as 2. argument if not
				let args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1)
				{
					args.splice(1, 0, this);
				}

				return this.onclick(...args);
			}

			return true;
		}

		/** et2_widget compatability **/
		destroy()
		{
			// Not really needed, use the disconnectedCallback() and let the browser handle it

			// Call the destructor of all children so any legacy widgets get destroyed
			for(let i = this.getChildren().length - 1; i >= 0; i--)
			{
				this.getChildren()[i].destroy();
			}

			// Free the array managers if they belong to this widget
			for(let key in this._mgrs)
			{
				if(this._mgrs[key] && this._mgrs[key].owner == this)
				{
					delete this._mgrs[key];
				}
			}
		}

		isInTree() : boolean
		{
			// TODO: Probably should watch the state or something
			return true;
		}

		/**
		 * Get property-values as object
		 *
		 * @deprecated use widget methods
		 */
		get options() : object
		{
			const options : { [key : string] : any } = {};
			// @ts-ignore not sure how to tell TS this is a ReactiveElement and properties is a static getter
			for(const name in this.constructor.properties)
			{
				options[name] = this[name];
			}
			// adding attributes too
			this.getAttributeNames().forEach(name =>
			{
				options[name] = this.getAttribute(name);
			});
			// add some (not declared) known properties
			if(typeof this.get_value === 'function')
			{
				options.value = this.get_value();
			}
			console.groupCollapsed("Deprecated widget.options use")
			console.trace("Something called widget.options on ", this);
			console.groupEnd();
			return options;
		}

		/**
		 * Loads the widget tree from an XML node
		 *
		 * @param _node xml node
		 */
		loadFromXML(_node)
		{
			// Load the child nodes.
			for(let i = 0; i < _node.childNodes.length; i++)
			{
				let node = _node.childNodes[i];
				let widgetType = node.nodeName.toLowerCase();

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
			let attributes = {};

			// Parse the "readonly" and "type" flag for this element here, as they
			// determine which constructor is used
			let _nodeName = attributes["type"] = _node.getAttribute("type") ?
												 _node.getAttribute("type") : _node.nodeName.toLowerCase();
			const readonly = attributes["readonly"] = this.getArrayMgr("readonlys") ?
													  (<any>this.getArrayMgr("readonlys")).isReadOnly(
														  _node.getAttribute("id"), _node.getAttribute("readonly"),
														  typeof this.readonly !== "undefined" ? this.readonly : false) : false;

			// Check to see if modifications change type
			let modifications = this.getArrayMgr("modifications");
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

			let widget;
			if(undefined == window.customElements.get(_nodeName))
			{
				// Get the constructor - if the widget is readonly, use the special "_ro"
				// constructor if it is available
				if (typeof et2_registry[_nodeName] === "undefined")
				{
					_nodeName = 'placeholder';
				}
				let constructor = et2_registry[_nodeName];
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
		 * N.B. This is only used for legacy widgets.  WebComponents use transformAttributes() and
		 * do their own handling of attributes.
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
			let mgr = this.getArrayMgr("content");
			for(let i = 0; i < _attrsObj.length; i++)
			{
				let attrName = _attrsObj[i].name;
				let attrValue = _attrsObj[i].value;

				// Special handling for the legacy options
				if(attrName == "options" && _proto.constructor.legacyOptions && _proto.constructor.legacyOptions.length > 0)
				{
					let legacy = _proto.constructor.legacyOptions || [];
					// Check for modifications on legacy options here.  Normal modifications
					// are handled in widget constructor, but it's too late for legacy options then
					if(_target.id && this.getArrayMgr("modifications").getEntry(_target.id))
					{
						let mod : any = this.getArrayMgr("modifications").getEntry(_target.id);
						if(typeof mod.options != "undefined")
						{
							attrValue = _attrsObj[i].value = mod.options;
						}
					}
					// expand legacyOptions with content
					if(attrValue.charAt(0) == '@' || attrValue.indexOf('$') != -1)
					{
						attrValue = mgr.expandName(attrValue);
					}

					// Parse the legacy options (as a string, other types not allowed)
					let splitted = et2_csvSplit(attrValue + "");

					for(let j = 0; j < splitted.length && j < legacy.length; j++)
					{
						// Blank = not set, unless there's more legacy options provided after
						if(splitted[j].trim().length === 0 && legacy.length >= splitted.length)
						{
							continue;
						}

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

							let attr = et2_attribute_registry[_proto.constructor.name][legacy[j]] || {};

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
						let attr = attrs[attrName];

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

		transformAttributes(attrs)
		{
			transformAttributes(this, this.getArrayMgr("content"), attrs);

			// Add in additional modifications
			if(this.id && this.getArrayMgr("modifications")?.getEntry(this.id))
			{
				transformAttributes(this, this.getArrayMgr("content"), this.getArrayMgr("modifications").getEntry(this.id));
			}
		}

		iterateOver(_callback : Function, _context, _type)
		{
			if(typeof _type === "undefined" || _type === et2_widget || _type === Et2Widget ||
				typeof _type === 'function' && this instanceof _type ||
				et2_implements_registry[_type] && et2_implements_registry[_type](this))
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
		loadingFinished(promises : Promise<any>[])
		{
			if(typeof promises === "undefined")
			{
				promises = [];
			}
			// Note that WebComponents don't do anything here, their lifecycle is different
			// This is just to support legacy widgets
			let doLoadingFinished = () =>
			{
				/**
				 * This is needed mostly as a bridge between non-WebComponent widgets and
				 * connectedCallback().  It's not really needed if the whole tree is WebComponent.
				 * WebComponents can be added as children immediately after creation, and they handle the
				 * rest themselves with their normal lifecycle (especially connectedCallback(), which is kind
				 * of the equivalent of doLoadingFinished()
				 */
				// @ts-ignore this is not an et2_widget, so getDOMNode(this) is bad
				if(!this._parent_node && this.getParent() instanceof et2_widget && (<et2_DOMWidget>this.getParent()).getDOMNode(this) != this.parentNode)
				{
					// @ts-ignore this is not an et2_widget, and Et2Widget is not a Node
					(<et2_DOMWidget>this.getParent()).getDOMNode(this).append(this);
				}

				// An empty text node causes problems with legacy widget children
				// It throws off their insertion indexing, making them get added in the wrong place
				if(this.childNodes[0]?.nodeType == this.TEXT_NODE && this.childNodes[0].textContent == "")
				{
					this.removeChild(this.childNodes[0]);
				}
				for(let i = 0; i < this.getChildren().length; i++)
				{
					let child = this.getChildren()[i];

					child.loadingFinished(promises);
				}
			};
			doLoadingFinished();

			promises.push(this.getUpdateComplete());
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
				for(let i = 0; i < children.length; i++)
				{
					let elem = children[i].getWidgetById(_id);

					if(elem != null)
					{
						return elem;
					}
				}
				if(this.id && _id.indexOf('[') > -1 && children.length)
				{
					let ids = (new et2_arrayMgr()).explodeKey(_id);
					let widget : Et2WidgetClass = this;
					for(let i = 0; i < ids.length && widget !== null; i++)
					{
						widget = widget.getWidgetById(ids[i]);
					}
					return widget;
				}
			};

			return check_children(this.getChildren()) || null;
		}

		/**
		 * Parent is different than what is specified in the template / hierarchy.
		 * Find it and re-parent there.
		 *
		 * @param {string} parent
		 */
		set parentId(parent : string | Element)
		{
			this.__parentId = parent;

			this.updateComplete.then(() =>
			{
				if(!this.__parentId)
				{
					return;
				}

				let parent = document.querySelector("#" + this.__parentId) || this.__parentId;
				if(parent && parent instanceof Element)
				{
					parent.append(<Node><unknown>this);
					this._parent_node = parent;
				}
			});
		}

		get parentId()
		{
			return this.__parentId;
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
			// @ts-ignore
			this._parent.addChild(this);
		}

		getParent() : Et2WidgetClass | et2_widget
		{
			if(this._parent)
			{
				return this._parent;
			}

			return null;
		}

		getParentDOMNode() : HTMLElement
		{
			return this._parent_node;
		}

		addChild(child : et2_widget | Et2WidgetClass)
		{
			if(this._children.indexOf(child) >= 0)
			{
				return;
			}
			if(child instanceof et2_widget)
			{
				// Type of et2_widget._parent is et2_widget, not Et2Widget.  This might cause problems, but they
				// should be fixed by getting rid of the legacy widget with problems
				// @ts-ignore
				child._parent = this;

				// During legacy widget creation, the child's DOM node won't be available yet.
				this._legacy_children.push(child);
				let child_node = null;
				try
				{
					//@ts-ignore Technically getDOMNode() is from et2_DOMWidget
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
			let copy = <Et2WidgetClass>this.cloneNode();
			copy.id = this._widget_id;

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

			let widget_class = window.customElements.get(this.localName);
			let properties = widget_class ? widget_class.properties : [];
			for(let key in properties)
			{
				copy[key] = this[key];
			}

			// Keep the deferred properties
			copy._deferred_properties = this._deferred_properties;

			// Create a clone of all child widgets of the given object
			for(let i = 0; i < this.getChildren().length; i++)
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
			for(let key in this._mgrs)
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
			let mgrs = this.getArrayMgrs();

			for(let key in mgrs)
			{
				let mgr = mgrs[key];

				// Get the original content manager if we have already created a
				// perspective for this node
				if(typeof this._mgrs[key] != "undefined" && mgr.perspectiveData.owner == this)
				{
					mgr = mgr.getParentMgr();
				}

				// Check whether the manager has a namespace for the id of this object
				let entry = mgr.getEntry(this.id);
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
				return this.getParent().getInstanceManager ? this.getParent().getInstanceManager() : null;
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
			let path = this.getArrayMgr("content")?.getPath() ?? [];

			// Prevent namespaced widgets with value from going an extra layer deep
			if(this.id && this._createNamespace() && path[path.length - 1] == this.id)
			{
				path.pop();
			}

			return path;
		}

		_createNamespace() : boolean
		{
			return false;
		}

		egw() : IegwAppLocal
		{
			if(this.getParent() != null && typeof this.getParent().egw === "function")
			{
				return (<et2_widget>this.getParent()).egw();
			}
			// Get the window this object belongs to
			let wnd = null;
			// @ts-ignore Technically this doesn't have implements(), but it's mixed in
			if(this.implements(et2_IDOMNode))
			{
				let node = (<et2_IDOMNode><unknown>this).getDOMNode();
				if(node && node.ownerDocument)
				{
					wnd = node.ownerDocument.parentNode || node.ownerDocument.defaultView;
				}
			}

			// If we're the root object, return the phpgwapi API instance
			return typeof egw === "function" ? egw('phpgwapi', wnd) : (window['egw'] ? window['egw'] : null);
		}
	}

	// Add some more stuff in
	applyMixins(Et2WidgetClass, [ClassWithInterfaces]);

	return Et2WidgetClass as unknown as Constructor<Et2WidgetClass> & T;
}
export const Et2Widget = dedupeMixin(Et2WidgetMixin);

/**
 * Load a Web Component
 * @param _nodeName
 * @param _template_node
 * @param parent Parent widget
 */
// @ts-ignore Et2Widget is I guess not the right type
export function loadWebComponent(_nodeName : string, _template_node : Element|{[index: string]: any}, parent : Et2Widget|et2_widget|undefined) : HTMLElement
{
	let attrs = {};
	let load_children = true;

	// support attributes object instead of an Element
	if(typeof _template_node.getAttribute === 'undefined')
	{
		attrs = _template_node;
		load_children = false;
	}
	else
	{
		_template_node.getAttributeNames().forEach(attribute =>
		{
			attrs[attribute] = _template_node.getAttribute(attribute);
		});
	}

	// Try to find the class for the given node
	let mobile = (typeof egwIsMobile != "undefined" && egwIsMobile());
	if(mobile && typeof window.customElements.get(_nodeName + "_mobile") != "undefined")
	{
		_nodeName += "_mobile";
	}

	let widget_class = window.customElements.get(_nodeName);
	if(!widget_class)
	{
		// Given node has no registered class.  Try some of our special things (remove type, fallback to actual node)
		let tries = [_nodeName.split('-')[0]];
		if(_template_node.nodeName)
		{
			tries = tries.concat(_template_node.nodeName.toLowerCase());
		}
		for(let i = 0; i < tries.length && !window.customElements.get(_nodeName); i++)
		{
			_nodeName = tries[i];
		}
		widget_class = window.customElements.get(_nodeName);
		if(!widget_class)
		{
			debugger;
			throw Error("Unknown or unregistered WebComponent '" + _nodeName + "', could not find class.  Also checked for " + tries.join(','));
		}
	}
	const readonly = parent?.getArrayMgr("readonlys") ?
					 (<any>parent.getArrayMgr("readonlys")).isReadOnly(
						 attrs["id"], attrs["readonly"],
						 typeof parent?.readonly !== "undefined" ? parent.readonly : parent.options?.readonly || false) : false;
	if(readonly === true && typeof window.customElements.get(_nodeName + "_ro") != "undefined")
	{
		_nodeName += "_ro";
	}

	// @ts-ignore
	let widget = <Et2Widget>document.createElement(_nodeName);

	if (parent && typeof widget.setParent === 'function') widget.setParent(parent);

	// Set read-only.  Doesn't really matter if it's a ro widget, but otherwise it needs set
	widget.readonly = readonly;

	delete attrs.readonly;
	widget.transformAttributes(attrs);

	// Children need to be loaded
	if(load_children)
	{
		widget.loadFromXML(_template_node);
	}

	return widget;
}

/**
 * Take attributes from a node in a .xet file and apply those to a WebComponent widget
 *
 * Any attributes provided that match a property (or attribute) on the widget will be adjusted according to
 * the passed arrayManager, coerced into the proper type, and set.
 * It is here that we find values or set attributes that should come from content.
 *
 * @param widget
 * @param {et2_arrayMgr} mgr
 * @param attributes
 */
function transformAttributes(widget, mgr : et2_arrayMgr, attributes)
{
	const widget_class = window.customElements.get(widget.localName);


	// Special case attributes
	if(attributes.attributes)
	{
		// Attributes in content? "attributes" is read-only in webComponent
		let mgr_attributes = mgr.getEntry(attributes.attributes);
		delete attributes.attributes;
		if(mgr_attributes)
		{
			Object.assign(attributes, ...mgr_attributes);
		}
	}
	if(attributes.width)
	{
		widget.style.setProperty("width", attributes.width);
		widget.style.setProperty("flex", "0 0 auto");
		delete attributes.width;
	}

	// Apply any set attributes - widget will do its own coercion
	for(let attribute in attributes)
	{
		let attrValue = attributes[attribute];

		// If there is no attribute set, ignore it.  Widget sets its own default.
		if(typeof attrValue === "undefined")
		{
			continue;
		}

		// preprocessor and transformer can't know if application widget is a web-component or a legacy one
		// translate attribute names to camelCase (only do it for used underscore, to not require a regexp)
		if (attribute !== 'select_options' && attribute.indexOf('_') !== -1)
		{
			let parts = attribute.split('_');
			if (attribute === 'parent_node') parts[1] = 'Id';
			attribute = parts.shift() + parts.map(part => part[0].toUpperCase() + part.substring(1)).join("");
		}

		const property = widget_class.getPropertyOptions(attribute);

		switch(typeof property === "object" ? property.type : property)
		{
			case Boolean:
				if(typeof attrValue == "boolean")
				{
					// Already boolean, nothing needed
					break;
				}
				// If the attribute is marked as boolean, parse the
				// expression as bool expression.
				attrValue = mgr ? mgr.parseBoolExpression(attrValue) : attrValue;
				if(typeof attrValue === "string")
				{
					// Parse decided we still needed a string ($row most likely) so we'll defer it until later
					// Repeating rows & nextmatch will parse it again when doing the row
					widget.deferredProperties[attribute] = attrValue;
					// Leave the current value at whatever the default is
					continue;
				}
				break;
			case Function:
				if(typeof attrValue == "string" && mgr && mgr.getPerspectiveData().row == null &&
					(attrValue.indexOf("$row") > -1 || attrValue.indexOf("$row_cont") > -1)
				)
				{
					// Need row context, defer it until later
					// Repeating rows & nextmatch will parse it again when doing the row
					widget.deferredProperties[attribute] = attrValue;
					console.log("Had to defer %s parsing for %o\nCan it be rewritten to avoid $row & $row_cont?", attribute, widget);
					break;
				}
				// We parse it into a function here so we can pass in the widget as context.
				// Leaving it to the LitElement conversion loses the widget as context
				if(typeof attrValue !== "function")
				{
					attrValue = et2_compileLegacyJS(attrValue, widget, widget);
				}
				break;
			case Object:
			case Array:
				// Leave it alone if it's not a string
				if(typeof attrValue !== "string")
				{
					break;
				}
			// fall through to look in content
			default:
				attrValue = mgr ? mgr.expandName("" + attrValue) : attrValue;
				if(attrValue && typeof attrValue == "string" && widget_class.translate[attribute])
				{
					// allow attribute to contain multiple translated sub-strings eg: {Firstname}.{Lastname}
					if(attrValue.indexOf('{') !== -1)
					{
						attrValue = attrValue.replace(/{([^}]+)}/g, (str, p1) =>
						{
							return widget.egw().lang(p1);
						});
					}
					else
					{
						attrValue = widget.egw().lang(attrValue);
					}
				}
				else if(attrValue && [Object, Array].indexOf(typeof property === "object" ? property.type : property) != -1)
				{
					// Value was not supposed to be a string, but was run through here for expandName
					try
					{
						attrValue = JSON.parse(attrValue);
					}
					catch(e)
					{
						console.info(widget_class.name + "#" + widget.id + " attribute '" + attribute + "' has type " +
							(typeof property === "object" ? property.type.name : property.name) + " but value %o could not be parsed", attrValue);
					}
				}
				break;
		}

		// Bind handlers directly, since we can do that now.  Event handlers still need to be defined
		// in properties() as {type: Function}, but this will take care of the binding.  This is
		// separate from internal events.
		// (handlers can only be bound _after_ the widget is added to the DOM
		if(attribute.startsWith("on") && typeof attrValue == "function")
		{
			//widget.updateComplete.then(() => addEventListener(attribute, attrValue));
		}

		// Set as attribute or property, as appropriate.  Don't set missing attributes.
		if(widget.getAttributeNames().indexOf(attribute) >= 0 || property.reflect && attrValue)
		{
			// Set as attribute (reflected in DOM)
			widget.setAttribute(attribute, attrValue === true ? "" : attrValue);
		}
		else if(attribute === 'options')
		{
			console.trace('Ignored setting depricated "options" attribute for widget #' + widget.id, widget);
			continue;
		}
		// Set as property
		widget[attribute] = attrValue;
	}

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
}

/**
 * Take the name of one of our images, find the full URL (including theme), and wrap it up so you can use it in a
 * widget's css block.
 *
 * @example
 * import {cssImage} from Et2Widget;
 * ...
 * static get styles()
 * {
 * 		return [
 * 			...super.styles,
 * 			css`
 * 			:host {
 * 				background-image: ${cssImage("save")};
 *			}
 *		`];
 *	}
 * @param image_name Name of the image
 * @param app_name Optional, image is from an app instead of api
 * @returns {CSSResult}
 */
export function cssImage(image_name : string, app_name? : string)
{
	let url = egw?.image(image_name, app_name);
	if(url)
	{
		return css`url(${unsafeCSS(url)})`;
	}
	else
	{
		return css``;
	}
}