/**
 * EGroupware eTemplate2 - JS code for implementing inheritance with attributes
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link: https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	et2_core_common;
*/

import {egw} from "../jsapi/egw_global";
import {et2_checkType, et2_no_init, et2_validateAttrib} from "./et2_core_common";
import {et2_IDOMNode, et2_IInput, et2_IInputNode, et2_implements_registry} from "./et2_core_interfaces";
import {LitElement} from "../../../node_modules/lit-element/lit-element.js";
import {et2_arrayMgr} from "./et2_core_arrayMgr";
import {et2_widget} from "./et2_core_widget";
import {et2_compileLegacyJS} from "./et2_core_legacyJSFunctions";
import {etemplate2} from "./etemplate2";

export class ClassWithInterfaces
{
	/**
	 * The implements function can be used to check whether the object
	 * implements the given interface.
	 *
	 * As TypeScript can not (yet) check if an objects implements an interface on runtime,
	 * we currently implements with each interface a function called 'implements_'+interfacename
	 * to be able to check here.
	 *
	 * @param _iface name of interface to check
	 */
	implements (_iface_name : string)
	{
		if (typeof et2_implements_registry[_iface_name] === 'function' &&
			et2_implements_registry[_iface_name](this))
		{
			return true
		}
		return false;
	}

	/**
	 * Check if object is an instance of a class or implements an interface (specified by the interfaces name)
	 *
	 * @param _class_or_interfacename class(-name) or string with name of interface
	 */
	instanceOf(_class_or_interfacename: any) : boolean
	{
		if (typeof _class_or_interfacename === 'string')
		{
			return this.implements(_class_or_interfacename);
		}
		return this instanceof _class_or_interfacename;
	}
}

export class ClassWithAttributes extends ClassWithInterfaces
{
	/**
	 * Object to collect the attributes we operate on
	 */
	attributes: object;

	/**
	 * Returns the value of the given attribute. If the property does not
	 * exist, an error message is issued.
	 *
	 * @param {string} _name
	 * @return {*}
	 */
	getAttribute(_name)
	{
		if (typeof this.attributes[_name] != "undefined" &&
			!this.attributes[_name].ignore) {
			if (typeof this["get_" + _name] == "function") {
				return this["get_" + _name]();
			} else {
				return this[_name];
			}
		} else {
			egw.debug("error", this, "Attribute '" + _name + "' does not exist!");
		}
	}

	/**
	 * The setAttribute function sets the attribute with the given name to
	 * the given value. _override defines, whether this[_name] will be set,
	 * if this key already exists. _override defaults to true. A warning
	 * is issued if the attribute does not exist.
	 *
	 * @param {string} _name
	 * @param {*} _value
	 * @param {boolean} _override
	 */
	setAttribute(_name, _value, _override)
	{
		if (typeof this.attributes[_name] != "undefined") {
			if (!this.attributes[_name].ignore) {
				if (typeof _override == "undefined") {
					_override = true;
				}

				var val = et2_checkType(_value, this.attributes[_name].type,
					_name, this);

				if (typeof this["set_" + _name] == "function") {
					this["set_" + _name](val);
				} else if (_override || typeof this[_name] == "undefined") {
					this[_name] = val;
				}
			}
		} else {
			egw.debug("warn", this, "Attribute '" + _name + "' does not exist!");
		}
	}

	/**
	 * generateAttributeSet sanitizes the given associative array of attributes
	 * (by passing each entry to "et2_checkType" and checking for existance of
	 * the attribute) and adds the default values to the associative array.
	 *
	 * @param {object} _attrs is the associative array containing the attributes.
	 */
	static generateAttributeSet(widget, _attrs)
	{
		// Sanity check and validation
		for (var key in _attrs) {
			if (typeof widget[key] != "undefined") {
				if (!widget[key].ignore) {
					_attrs[key] = et2_checkType(_attrs[key], widget[key].type,
						key, this);
				}
			} else {
				// Key does not exist - delete it and issue a warning
				delete (_attrs[key]);
				egw.debug("warn", this, "Attribute '" + key +
					"' does not exist in " + _attrs.type + "!");
			}
		}

		// Include default values or already set values for this attribute
		for (var key in widget) {
			if (typeof _attrs[key] == "undefined") {
				var _default = widget[key]["default"];
				if (_default == et2_no_init) {
					_default = undefined;
				}

				_attrs[key] = _default;
			}
		}

		return _attrs;
	}

	/**
	 * The initAttributes function sets the attributes to their default
	 * values. The attributes are not overwritten, which means, that the
	 * default is only set, if either a setter exists or this[propName] does
	 * not exist yet.
	 *
	 * @param {object} _attrs is the associative array containing the attributes.
	 */
	initAttributes(_attrs)
	{
		for (var key in _attrs) {
			if (typeof this.attributes[key] != "undefined" && !this.attributes[key].ignore && !(_attrs[key] == undefined)) {
				this.setAttribute(key, _attrs[key], false);
			}
		}
	}

	static buildAttributes(class_prototype: object)
	{
		let class_tree = [];
		let attributes = {};
		let n = 0;
		do {
			n++;
			class_tree.push(class_prototype);
			class_prototype = Object.getPrototypeOf(class_prototype);
		} while (class_prototype !== ClassWithAttributes && n < 50);

		for (let i = class_tree.length - 1; i >= 0; i--) {
			attributes = ClassWithAttributes.extendAttributes(attributes, class_tree[i]._attributes);
		}
		return attributes;
	}

	/**
	 * Extend current _attributes with the one from the parent class
	 *
	 * This gives inheritance from the parent plus the ability to override in the current class.
	 *
	 * @param _attributes
	 * @param _parent
	 */
	static extendAttributes(_parent: object, _attributes: object): object
	{
		function _copyMerge(_new, _old)
		{
			var result = {};

			// Copy the new object
			if (typeof _new != "undefined") {
				for (var key in _new) {
					result[key] = _new[key];
				}
			}

			// Merge the old object
			for (var key in _old) {
				if (typeof result[key] == "undefined") {
					result[key] = _old[key];
				}
			}

			return result;
		}

		var attributes = {};

		// Copy the old attributes
		for (var key in _attributes) {
			attributes[key] = _copyMerge({}, _attributes[key]);
		}

		// Add the old attributes to the new ones. If the attributes already
		// exist, they are merged.
		for (var key in _parent) {
			var _old = _parent[key];

			attributes[key] = _copyMerge(attributes[key], _old);
		}

		// Validate the attributes
		for (var key in attributes) {
			et2_validateAttrib(key, attributes[key]);
		}

		return attributes;
	}
}


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

type Constructor<T = {}> = new (...args: any[]) => T;
export const Et2Widget = <T extends Constructor>(superClass: T) => {
	class Et2WidgetClass extends superClass implements et2_IDOMNode {

		/** et2_widget compatability **/
		protected _mgrs: et2_arrayMgr[] = [] ;
		protected _parent: Et2WidgetClass | et2_widget | null = null;
		private _inst: etemplate2 | null = null;

		/** WebComponent **/
		static get properties() {
			return {
				label: {type: String},
				onclick: {
					type: Function,
					converter: (value) => {
						debugger;
						return et2_compileLegacyJS(value, this, this);
					}
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
		constructor(...args: any[]) {
			super(...args);

			// Provide *default* property values in constructor
			this.label = "";
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.set_label(this.label);
		}


		/**
		 * NOT the setter, since we cannot add to the DOM before connectedCallback()
		 *
		 * @param value
		 */
		set_label(value)
		{
			let oldValue = this.label;

			// Remove old
			let oldLabels = this.getElementsByClassName("et2_label");
			while(oldLabels[0])
			{
				this.removeChild(oldLabels[0]);
			}

			let label = document.createElement("span");
			label.classList.add("et2_label");
			label.textContent = this.label;
			// We should have a slot in the template for the label
			//label.slot="label";
			this.appendChild(label);
			this.requestUpdate('label',oldValue);
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
				var args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1) args.splice(1, 0, this);

				return this.onclick.apply(this, args);
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
		iterateOver(_callback: Function, _context, _type)
		{
			if(et2_implements_registry[_type](this))
			{
				_callback.call(_context, this);
			}
			// TODO: children
		}
		loadingFinished()
		{}
		getWidgetById(_id)
		{
			if (this.id == _id) {
				return this;
			}
		}

		setParent(new_parent: Et2WidgetClass | et2_widget)
		{
			this._parent = new_parent;
		}
		getParent() : HTMLElement | et2_widget {
			let parentNode = this.parentNode;

			// If parent is an old et2_widget, use it
			if(this._parent)
			{
				return this._parent;
			}

			return parentNode;
		}
		getDOMNode(): HTMLElement {
			return this;
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
			if (this._mgrs && typeof this._mgrs[managed_array_type] != "undefined") {
				return this._mgrs[managed_array_type];
			} else if (this.getParent()) {
				return this.getParent().getArrayMgr(managed_array_type);
			}

			return null;
		}

		/**
		 * Returns an associative array containing the top-most array managers.
		 *
		 * @param _mgrs is used internally and should not be supplied.
		 */
		getArrayMgrs(_mgrs? : object)
		{
			if (typeof _mgrs == "undefined") {
				_mgrs = {};
			}

			// Add all managers of this object to the result, if they have not already
			// been set in the result
			for (var key in this._mgrs) {
				if (typeof _mgrs[key] == "undefined") {
					_mgrs[key] = this._mgrs[key];
				}
			}

			// Recursively applies this function to the parent widget
			if (this._parent) {
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
		checkCreateNamespace(_attrs? : any)
		{
			// Get the content manager
			var mgrs = this.getArrayMgrs();

			for (var key in mgrs) {
				var mgr = mgrs[key];

				// Get the original content manager if we have already created a
				// perspective for this node
				if (typeof this._mgrs[key] != "undefined" && mgr.perspectiveData.owner == this) {
					mgr = mgr.parentMgr;
				}

				// Check whether the manager has a namespace for the id of this object
				var entry = mgr.getEntry(this.id);
				if (typeof entry === 'object' && entry !== null || this.id) {
					// The content manager has an own node for this object, so
					// create an own perspective.
					this._mgrs[key] = mgr.openPerspective(this, this.id);
				} else {
					// The current content manager does not have an own namespace for
					// this element, so use the content manager of the parent.
					delete (this._mgrs[key]);
				}
			}
		}

		/**
		 * Returns the instance manager
		 *
		 * @return {etemplate2}
		 */
		getInstanceManager()
		{
			if (this._inst != null) {
				return this._inst;
			} else if (this.getParent()) {
				return this.getParent().getInstanceManager();
			}

			return null;
		}

		/**
		 * Returns the path into the data array.  By default, array manager takes care of
		 * this, but some extensions need to override this
		 */
		getPath()
		{
			var path = this.getArrayMgr("content").getPath();

			// Prevent namespaced widgets with value from going an extra layer deep
			if (this.id && this._createNamespace() && path[path.length - 1] == this.id) path.pop();

			return path;
		}

		_createNamespace()
		{
			return false;
		}
	};
	return Et2WidgetClass as unknown as Constructor<et2_IDOMNode> & T;
}