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
		},
		"content": {
			"name": "Content index",
			"default": et2_no_init
		},
	},

	createNamespace: false,

	/**
	 * Initializes this template widget as a simple container.
	 */
	init: function(_parent, _attrs) {
		// Set this early, so it's available for creating namespace
		if(_attrs.content)
		{
			this.content = _attrs.content;
		}
		this._super.apply(this, arguments);

		this.div = document.createElement("div");

		if (this.id != "")
		{
			// Set the api instance to the first part of the name of the
			// template, if it's in app.function.template format
			var splitted = this.id.split('.');
			if(splitted.length >= 3)
			{
				this.setApiInstance(egw(splitted[0], this._parent.egw().window));
			}

			// Check to see if XML is known
			var xml = null;
			var templates = this.getRoot().getInstanceManager().templates;
			if(!(xml = templates[this.id]))
			{
				// Check to see if ID is short form
				// eg: row instead of app.something.row
				for(var key in templates)
				{
					splitted = key.split('.');
					if(splitted[splitted.length-1] == this.id)
					{
						xml = templates[key];
						break;
					}
				}
				if(!xml)
				{
					// Ask server
					splitted = this.id.split('.');
					var path = this.egw().webserverUrl + "/" + splitted.shift() + "/templates/default/" + splitted.join('.') + ".xet";

					if(splitted.length)
					{
						et2_loadXMLFromURL(path, function(_xmldoc) {
							var templates = {};
							// Scan for templates and store them
							for(var i = 0; i < _xmldoc.childNodes.length; i++) {
								var template = _xmldoc.childNodes[i];
								if(template.nodeName.toLowerCase() != "template") continue;
								templates[template.getAttribute("id")] = template;
							}

							// Read the XML structure of the requested template
							this.loadFromXML(templates[this.id]);

							// Inform the widget tree that it has been successfully loaded.
							this.loadingFinished();
						}, this);
					}
					return;
				}
			}
			if(xml !== null && typeof xml !== "undefined")
			{
				this.egw().debug("log", "Loading template from XML: ", this.id);
				this.loadFromXML(xml);
				// Don't call this here - premature
				//this.loadingFinished();
			}
			else
			{
				this.egw().debug("warn", "Unable to find XML for ", this.id);
			}
		}
	},

	/**
	 * Override parent to support content attribute
	 */
	checkCreateNamespace: function() {
		if(this.content)
		{
			var old_id = this.id;
			this.id = this.content;
			this._super.apply(this, arguments);
		}
		else
		{
			this._super.apply(this, arguments);
		}
	},

	getDOMNode: function() {
		return this.div;
	}
});

et2_register_widget(et2_template, ["template"]);


