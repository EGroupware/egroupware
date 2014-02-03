/**
 * EGroupware eTemplate2 - JS Progrss object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_interfaces;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "progress" XET-Tag
 *
 * @augments et2_valueWidget
 */
var et2_progress = et2_valueWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"href": {
			"name": "Link Target",
			"type": "string",
			"description": "Link URL, empty if you don't wan't to display a link."
		},
		"extra_link_target": {
			"name": "Link target",
			"type": "string",
			"default": "_self",
			"description": "Link target descriptor"
		},
		"extra_link_popup": {
			"name": "Popup",
			"type": "string",
			"description": "widthxheight, if popup should be used, eg. 640x480"
		},
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
			"description": "The label is displayed as the title.  The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			"translate": true
		}
	},
	legacyOptions: ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_progress
	 */
	init: function()
	{
		this._super.apply(this, arguments);

		var outer = document.createElement("div");
		outer.className = "et2_progress";
		this.progress = document.createElement("div");
		this.progress.style.width = "0";
		outer.appendChild(this.progress);

		if (this.options.href)
		{
			outer.className += ' et2_clickable';
		}
		if(this.options["class"])
		{
			outer.className += ' '+this.options["class"];
		}
		this.setDOMNode(outer);	// set's this.node = outer
	},

	click: function()
	{
		this._super.apply(this, arguments);

		if(this.options.href)
		{
			this.egw().open_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
		}
	},

	// setting the value as width of the progress-bar
	set_value: function(_value)
	{
		_value = parseInt(_value)+"%";	// make sure we have percent attached
		this.progress.style.width = _value;
		if (!this.options.label) this.set_label(_value);
	},

	// set's label as title of this.node
	set_label: function(_value)
	{
		this.node.title = _value;
	},

	// set's class of this.node; preserve baseclasses et2_progress and if this.options.href is set et2_clickable
	set_class: function(_value)
	{
		var baseClass = "et2_progress";
		if (this.options.href)
		{
			baseClass += ' et2_clickable';
		}
		this.node.setAttribute('class', baseClass + ' ' + _value);
	},

	set_href: function (_value)
	{
		if (!this.isInTree())
		{
			return false;
		}

		this.options.href = _value;
		jQuery(this.node).wrapAll('<a href="'+_value+'"></a>"');

		var href = this.options.href;
		var popup = this.options.extra_link_popup;
		var target = this.options.extra_link_target;
		jQuery(this.node).parent().click(function(e)
		{
			egw.open_link(href,target,popup);
			e.preventDefault();
			return false;
		});

		return true;
	},

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 *
	 * * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value", "label", "href");
	},

	getDetachedNodes: function() {
		return [this.node, this.progress];
	},

	setDetachedAttributes: function(_nodes, _values) {
		// Set the given DOM-Nodes
		this.node = _nodes[0];
		this.progress = _nodes[1];

		// Set the attributes
		if (_values["label"])
		{
			this.set_label(_values["label"]);
		}
		if (_values["value"])
		{
			this.set_value(_values["value"]);
		}
		else if (_values["label"])
		{
			this.set_value(_values["label"]);
		}
		if(_values["href"])
		{
			jQuery(this.node).addClass('et2_clickable');
			this.set_href(_values["href"]);
		}
	}
});
et2_register_widget(et2_progress, ["progress"]);
