/**
 * EGroupware eTemplate2 - JS Template base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	et2_core_xml;
	et2_core_DOMWidget;
*/

import './et2_core_interfaces';
import {et2_DOMWidget} from './et2_core_DOMWidget';
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {etemplate2} from "./etemplate2";
import {et2_cloneObject, et2_no_init} from "./et2_core_common";
import {et2_loadXMLFromURL} from "./et2_core_xml";
import {egw} from "../jsapi/egw_global";

/**
 * Class which implements the "template" XET-Tag. When the id parameter is set,
 * the template class checks whether another template with this id already
 * exists. If yes, this template is removed from the DOM tree, copied and
 * inserted in place of this template.
 */
export class et2_template extends et2_DOMWidget
{
	static readonly _attributes : any = {
		"template": {
			"name": "Template",
			"type": "string",
			"description": "Name / ID of template with optional cache-buster ('?'+filemtime of template on server)",
			"default": et2_no_init
		},
		"group": {
			// TODO: Not implemented
			"name": "Group",
			"description":"Not implemented",
			//"default": 0
			"default": et2_no_init
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
			"default": et2_no_init,
			"description": "Used for passing in specific content to the template other than what it would get by ID."
		},
		url: {
			name: "URL of template",
			type: "string",
			description: "full URL to load template incl. cache-buster"
		},
		"onload": {
			"name": "onload",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which is executed after the template is loaded."
		}
	};

	content : string;
	div : HTMLDivElement;
	loading : Promise<any>;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_template._attributes, _child || {}));

		// Set this early, so it's available for creating namespace
		if(_attrs.content)
		{
			this.content = _attrs.content;
		}
		// constructor was called here before!

		this.div = document.createElement("div");

		// Deferred object so we can load via AJAX
		this.loading = new Promise<any>((resolve, reject) =>
		{

			// run transformAttributes now, to get server-side modifications (url!)
			if(_attrs.template)
			{
				this.id = _attrs.template;
				this.transformAttributes(_attrs);
				this.options = et2_cloneObject(_attrs);
				_attrs = {};
			}
			if(this.id != "" || this.options.template)
			{
				var parts = (this.options.template || this.id).split('?');
				var cache_buster = parts.length > 1 ? parts.pop() : null;
				var template_name = parts.pop();

				// Check to see if XML is known
				var xml = null;
				var templates = etemplate2.templates;	// use global eTemplate cache
				if(!(xml = templates[template_name]))
				{
					// Check to see if ID is short form --> prepend parent/top-level name
					if(template_name.indexOf('.') < 0)
					{
						var root = _parent ? _parent.getRoot() : null;
						var top_name = root && root._inst ? root._inst.name : null;
						if(top_name && template_name.indexOf('.') < 0)
						{
							template_name = top_name + '.' + template_name;
						}
					}
					xml = templates[template_name];
					if(!xml)
					{
						// Ask server
						var url = this.options.url;
						if(!this.options.url)
						{
							var splitted = template_name.split('.');
							var app = splitted.shift();
							url = egw.link('/'+ app + "/templates/default/" +
								splitted.join('.')+ ".xet", {download:cache_buster? cache_buster :(new Date).valueOf()});
						}
						// if server did not give a cache-buster, fall back to current time
						if (url.indexOf('?') == -1) url += '?download='+(new Date).valueOf();

						if(this.options.url || splitted.length)
						{
							var fetch_url_callback = function(_xmldoc)
							{
								// Scan for templates and store them
								for(var i = 0; i < _xmldoc.childNodes.length; i++)
								{
									var template = _xmldoc.childNodes[i];
									if(template.nodeName.toLowerCase() != "template")
									{
										continue;
									}
									templates[template.getAttribute("id")] = template;
								}

								// Read the XML structure of the requested template
								if(typeof templates[template_name] != 'undefined')
								{
									this.loadFromXML(templates[template_name]);
								}

								// Update flag
								resolve();
							}.bind(this);

							et2_loadXMLFromURL(url, fetch_url_callback, this, function( error) {
								url = egw.link('/'+ app + "/templates/default/" +
									splitted.join('.')+ ".xet", {download:cache_buster? cache_buster :(new Date).valueOf()});

								et2_loadXMLFromURL(url, fetch_url_callback, this);
							});
						}
						return;
					}
				}
				if(xml !== null && typeof xml !== "undefined")
				{
					this.egw().debug("log", "Loading template from XML: ", template_name);
					this.loadFromXML(xml);
					// Don't call this here - done by caller, or on whole widget tree
					//this.loadingFinished();

					// But resolve the promise
					resolve();
				}
				else
				{
					this.egw().debug("warn", "Unable to find XML for ", template_name);
					reject("Unable to find XML for " + template_name);
				}
			}
			else
			{
				// No actual template - not an error, just nothing to do
				resolve();
			}
		});
	}

	/**
	 * Override parent to support content attribute
	 * Templates always have ID set, but seldom do we want them to
	 * create a namespace based on their ID.
	 */
	checkCreateNamespace(_attrs)
	{
		if(_attrs.content)
		{
			var old_id = _attrs.id;
			this.id = _attrs.content;
			super.checkCreateNamespace.apply(this, arguments);
			this.id = old_id;
		}
	}

	_createNamespace() : boolean
	{
		return true;
	}

	getDOMNode()
	{
		return this.div;
	}

	attachToDOM()
	{
		if (this.div)
		{
			jQuery(this.div)
				.off('.et2_template')
				.bind("load.et2_template", this, function(e) {
					e.data.load.call(e.data, this);
				});
		}

		return super.attachToDOM();
	}

	/**
	 * Called after the template is fully loaded to handle any onload handlers
	 */
	load()
	{
		if(typeof this.options.onload == 'function')
		{
			// Make sure function gets a reference to the widget
			var args = Array.prototype.slice.call(arguments);
			if(args.indexOf(this) == -1) args.push(this);

			return this.options.onload.apply(this, args);
		}
	}

	/**
	 * Override to return the promise for deferred loading
	 */
	doLoadingFinished()
	{
		// Apply parent now, which actually puts into the DOM
		super.doLoadingFinished();

		// Fire load event when done loading
		this.loading.then(function() {jQuery(this).trigger("load");}.bind(this.div));

		// Not done yet, but widget will let you know
		return this.loading;
	}
}
et2_register_widget(et2_template, ["template"]);