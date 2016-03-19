/**
 * EGroupware eTemplate2 - JS widget class for an iframe
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */

/*egw:uses
        et2_core_valueWidget;
*/

/**
 * @augments et2_valueWidget
 */
var et2_iframe = (function(){ "use strict"; return et2_valueWidget.extend(
{
	attributes: {
		'label': {
			'default': "",
			description: "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			ignore: false,
			name: "Label",
			translate: true,
			type: "string",
		},
		"needed": {
			"ignore": true
		},
		"seamless": {
			name: "Seamless",
			'default': true,
			description: "Specifies that the iframe should be rendered in a manner that makes it appear to be part of the containing document",
			translate: false,
			type: "boolean"
		},
		"name": {
			name: "Name",
			"default": "",
			description: "Specifies name of frame, to be used as target for links",
			type: "string"
		},
		fullscreen: {
			name: "Fullscreen",
			"default": false,
			description: "Make the iframe compatible to be a fullscreen video player mode",
			type: "boolean"
		},
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_iframe
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		this.htmlNode = $j(document.createElement("iframe"));
		if(this.options.label)
		{
			this.htmlNode.append('<span class="et2_label">'+this.options.label+'</span>');
		}
		if (this.options.fullscreen)
		{
			this.htmlNode.attr('allowfullscreen', true);
		}
		this.setDOMNode(this.htmlNode[0]);
	},

	/**
	 * Set name of iframe (to be used as target for links)
	 *
	 * @param _name
	 */
	set_name: function(_name) {
		this.htmlNode.attr('name', this.htmlNode.name = _name);
	},

	/**
	 * Make it look like part of the containing document
	 *
	 * @param _seamless boolean
	 */
	set_seamless: function(_seamless) {
		this.options.seamless = _seamless;
		this.htmlNode.attr("seamless", _seamless);
	},

	set_value: function(_value) {
		if(typeof _value == "undefined") _value = "";

		if(_value.trim().indexOf("http") == 0 || _value.indexOf('about:') == 0 || _value[0] == '/')
		{
			// Value is a URL
			this.set_src(_value);
		}
		else
		{
			// Value is content
			this.set_srcdoc(_value);
		}
	},

	/**
	 * Set the URL for the iframe
	 *
	 * Sets the src attribute to the given value
	 *
	 * @param _value String URL
	 */
	set_src: function(_value) {
		if(_value.trim() != "")
		{
			if(_value.trim() == 'about:blank')
			{
				this.htmlNode.attr("src", _value);
			}
			else
			{
				// Load the new page, but display a loader
				var loader = $j('<div class="et2_iframe loading"/>');
				this.htmlNode
					.before(loader);
				window.setTimeout(jQuery.proxy(function() {
					this.htmlNode.attr("src", _value)
						.one('load',function() {
							loader.remove();
						});
				},this),0);

			}
		}
	},

	/**
	 * Sets the content of the iframe
	 *
	 * Sets the srcdoc attribute to the given value
	 *
	 * @param _value String Content of a document
	 */
	set_srcdoc: function(_value) {
		this.htmlNode.attr("srcdoc", _value);
	}
});}).call(this);
et2_register_widget(et2_iframe, ["iframe"]);
