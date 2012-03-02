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
	et2_core_xml;
	et2_core_DOMWidget;
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
			"name": "Template",
			"type": "string",
			"description": "Name / ID of template"
		},
		"group": {
			// TODO: Not implemented
			"name": "Group",
			"description":"Not implemented",
			"default": 0
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

	createNamespace: true,

	/**
	 * Initializes this template widget as a simple container.
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.proxiedTemplate = null;
		this.isProxied = false;
		this.isProxy = false;

		this.div = document.createElement("div");

		if (this.id != "")
		{
			// Set the api instance to the first part of the name of the
			// template
			var splitted = this.id.split('.');
			this.setApiInstance(egw(splitted[0]));

			this.createProxy();
		}
	},

	createProxy: function() {
		// Check whether a template with the given name already exists and
		// is not a proxy.
		var tmpl = this.getRoot().getWidgetById(this.id);
		if (tmpl instanceof et2_template && tmpl.proxiedTemplate == null &&
		    tmpl != this)
		{
			// Detatch the proxied template from the DOM to and set its
			// isProxied property to true
			tmpl.makeProxied();

			// Do not copy the id when cloning as this leads to infinit
			// recursion
			tmpl.options.id = "";

			// Create a clone of the template and add it as child of this
			// template (done by passing "this" to the clone function)
			this.proxiedTemplate = tmpl.clone(this);

			// Reset the id and manually copy the id to the proxied template
			tmpl.options.id = this.id;
			this.proxiedTemplate.id = tmpl.id;
			this.proxiedTemplate.isProxy = true;

			// Disallow adding any new node to this template
			this.supportedWidgetClasses = [];
		}
	},

	/**
	 * If the parent node is changed, either the DOM-Node of the proxied template
	 * or the DOM-Node of this template is connected to the parent DOM-Node.
	 */
	doLoadingFinished: function() {
		// Check whether the parent implements the et2_IDOMNode interface.
		if (this._parent && this._parent.implements(et2_IDOMNode)) {
			var parentNode = this._parent.getDOMNode(this);

			if (parentNode)
			{
				if (this.proxiedTemplate)
				{
					this.proxiedTemplate.setParentDOMNode(parentNode);
					this.proxiedTemplate.loadingFinished();
					return false;
				}
				else if (!this.isProxied && !this.isProxy)
				{
					this.setParentDOMNode(parentNode);
				}
			}
		}

		return true;
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

	getDOMNode: function() {
		return this.div;
	},

	isInTree: function(_sender) {
		return this._super(this, !this.isProxied);
	}

});

et2_register_widget(et2_template, ["template"]);


