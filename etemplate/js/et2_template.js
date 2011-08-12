/**
 * eGroupWare eTemplate2 - JS Template base class
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
 * Class which implements the "template" XET-Tag. When the id parameter is set,
 * the template class checks whether another template with this id already
 * exists. If yes, this template is removed from the DOM tree, copied and
 * inserted in place of this template.
 * 
 * TODO: Check whether this widget behaves as it should.
 */ 
var et2_template = et2_DOMWidget.extend({

	attributes: {
		"template": {
		},
		"group": {
		},
		"version": {
			"name": "Version",
			"type": "string",
			"description": "Version of the template"
		},
		"lang": {
			"name": "Language",
			"type": "string",
			"description": "Language the template is written in"
		}
	},

	/**
	 * Initializes this template widget as a simple container.
	 */
	init: function(_parent) {
		this.proxiedTemplate = null;
		this.isProxied = false;

		this.div = document.createElement("div");
		this.id = "";

		this._super.apply(this, arguments);
	},

	/**
	 * If the parent node is changed, either the DOM-Node of the proxied template
	 * or the DOM-Node of this template is connected to the parent DOM-Node.
	 */
	onSetParent: function() {
		// Check whether the parent implements the et2_IDOMNode interface. If
		// yes, grab the DOM node and create our own.
		if (this._parent && this._parent.implements(et2_IDOMNode)) {
			var parentNode = this._parent.getDOMNode(this);

			if (parentNode)
			{
				if (this.proxiedTemplate)
				{
					this.proxiedTemplate.setParentDOMNode(parentNode);
				}
				else if (!this.isProxied)
				{
					this.setParentDOMNode(parentNode);
				}
			}
		}
	},

	makeProxied: function() {
		if (!this.isProxied)
		{
			this.detatchFromDOM();
			this.div = null;
			this.parentNode = null;
		}

		this.isProxied = true;
	},

	set_id: function(_value) {
		if (_value != this.id)
		{
			// Check whether a template with the given name already exists and
			// is not a proxy.
			var tmpl = this.getRoot().getWidgetById(_value);
			if (tmpl instanceof et2_template && tmpl.proxiedTemplate == null &&
			    tmpl != this)
			{
				// Check whether we still have a proxied template, if yes,
				// destroy it
				if (this.proxiedTemplate != null)
				{
					this.proxiedTemplate.destroy();
					this.proxiedTemplate = null;
				}

				// This element does not have a node in the tree
				this.detatchFromDOM();

				// Detatch the proxied template from the DOM to and set its
				// isProxied property to true
				tmpl.makeProxied();

				// Do not copy the id when cloning as this leads to infinit
				// recursion
				tmpl.attributes["id"].ignore = true;

				// Create a clone of the template and add it as child of this
				// template (done by passing "this" to the clone function)
				this.proxiedTemplate = tmpl.clone(this);

				// Reset the "ignore" flag and manually copy the id
				tmpl.attributes["id"].ignore = false;
				this.proxiedTemplate.id = tmpl.id;

				// Disallow adding any new node to this template
				this.supportedWidgetClasses = [];

				// Call the parent change event function
				this.onSetParent();
			}
			else
			{
				this._super(_value);

				// Check whether a namespace exists for this element
				this.checkCreateNamespace();
			}
		}
	},

	getDOMNode: function(_fromProxy) {
		return this.div;
	},

	getValues: function(_target) {
		if (this.proxiedTemplate)
		{
			return this.proxiedTemplate.getValues(_target);
		}
		else if (!this.isProxied)
		{
			return this._super(_target);
		}

		return _target;
	}

});

et2_register_widget(et2_template, ["template"]);


