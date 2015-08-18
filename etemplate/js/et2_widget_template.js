/**
 * EGroupware eTemplate2 - JS Template base class
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
 * @augments et2_DOMWidget
 */
var et2_template = et2_DOMWidget.extend(
{
	attributes: {
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
		}
	},

	createNamespace: true,

	/**
	 * Initializes this template widget as a simple container.
	 *
	 * @memberOf et2_template
	 * @param {et2_widget} _parent
	 * @param {object} _attrs
	 */
	init: function(_parent, _attrs) {
		// Set this early, so it's available for creating namespace
		if(_attrs.content)
		{
			this.content = _attrs.content;
		}
		this._super.apply(this, arguments);

		this.div = document.createElement("div");

		// Deferred object so we can load via AJAX
		this.loading = jQuery.Deferred();

		if (this.id != "" || this.options.template)
		{
			var parts = (this.options.template || this.id).split('?');
			var cache_buster = parts.length > 1 ? parts.pop() : null;
			var template_name = parts.pop();

			// Check to see if the template is known
			var template = null;
			var templates = etemplate2.prototype.templates;	// use global eTemplate cache
			if(!(template = templates[template_name]))
			{
				// Check to see if ID is short form --> prepend parent/top-level name
				if(template_name.indexOf('.') < 0)
				{
					var root = _parent ? _parent.getRoot() : null;
					var top_name = root && root._inst ? root._inst.name : null;
					if (top_name && template_name.indexOf('.') < 0) template_name = top_name+'.'+template_name;
				}
				template = templates[template_name];
				if(!template)
				{
					// Ask server
					var splitted = template_name.split('.');
					// use template base url from initial template, to continue using webdav, if that was loaded via webdav
					var path = this.getRoot()._inst.template_base_url + 
						splitted.join('.') + (cache_buster ? '&download='+cache_buster :
						// if server did not give a cache-buster, fall back to current time
						'&download='+(new Date).valueOf());

					if(splitted.length)
					{
						jQuery.ajax({
							url: path,
							context: this,
							type: 'GET',
							dataType: 'json',
							success: function(_data, _status, _xmlhttp){
								for(var i = 0; i < _data.children.length; i++)
								{
									var template = _data.children[i];
									if(template.tag !== "template") continue;
									templates[template.attributes.id] = template;
								}// Read the structure of the requested template
								if (typeof templates[template_name] != 'undefined') this.loadFromJSON(templates[template_name]);

								// Update flag
								this.loading.resolve();
							},
							error: function(_xmlhttp, _err) {
								egw().debug('error', 'Loading eTemplate from '+_url+' failed! '+_xmlhttp.status+' '+_xmlhttp.statusText);
							}
						});
					}
					return;
				}
			}
			if(template !== null && typeof template !== "undefined")
			{
				this.egw().debug("log", "Loading template: ", template_name);
				if(template.tag)
				{
					this.loadFromJSON(template);
				}
				
				// Don't call this here - done by caller, or on whole widget tree
				//this.loadingFinished();

				// But resolve the promise
				this.loading.resolve();
			}
			else
			{
				this.egw().debug("warn", "Unable to find ", template_name);
				this.loading.reject();
			}
		}
		else
		{
			// No actual template
			this.loading.resolve();
		}
	},

	/**
	 * Override parent to support content attribute
	 * Templates always have ID set, but seldom do we want them to
	 * create a namespace based on their ID.
	 */
	checkCreateNamespace: function() {
		if(this.content)
		{
			var old_id = this.id;
			this.id = this.content;
			this._super.apply(this, arguments);
			this.id = old_id;
		}
	},

	getDOMNode: function() {
		return this.div;
	},

	/**
	 * Override to return the promise for deferred loading
	 */
	doLoadingFinished: function() {
		// Apply parent now, which actually puts into the DOM
		this._super.apply(this, arguments);

		// Fire load event when done loading
		this.loading.done(jQuery.proxy(function() {$j(this).trigger("load");},this.div));

		// Not done yet, but widget will let you know
		return this.loading.promise();
	}
});
et2_register_widget(et2_template, ["template"]);

