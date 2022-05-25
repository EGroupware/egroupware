/**
 * EGroupware eTemplate2 - JS Diff object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/diff2html/dist/diff2html.min.js;
	et2_core_valueWidget;
*/
import "../../../vendor/bower-asset/diff2html/dist/diff2html.min";
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_IDetachedDOM} from "./et2_core_interfaces";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";

/**
 * Class that displays the diff between two [text] values
 *
 * @augments et2_valueWidget
 */
export class et2_diff extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes = {
		"value": {
			"type": "any"
		}
	};

	private readonly diff_options: {
		"inputFormat":"diff",
		"matching": "words"
	};
	private div: HTMLDivElement;
	private mini: boolean = true;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_diff._attributes, _child || {}));

		// included via etemplate2.css
		//this.egw().includeCSS('../../../vendor/bower-asset/dist/dist2html.css');
		this.div = document.createElement("div");
		jQuery(this.div).addClass('et2_diff');
	}

	set_value( value)
	{
		jQuery(this.div).empty();
		if(typeof value == 'string') {

			// Diff2Html likes to have files, we don't have them
			if(value.indexOf('---') !== 0)
			{
				value = "--- diff\n+++ diff\n"+value;
			}
			// @ts-ignore
			var diff = Diff2Html.getPrettyHtml(value, this.diff_options);
		//	var ui = new Diff2HtmlUI({diff: diff});
		//	ui.draw(jQuery(this.div), this.diff_options);
			jQuery(this.div).append(diff);
		}
		else if(typeof value != 'object')
		{
			jQuery(this.div).append(value);
		}
		this.check_mini();
	}

	check_mini( )
	{
		if(!this.mini)
		{
			return false;
		}
		var view = jQuery(this.div).children();
		this.minify(view);
		var self = this;
		jQuery('<span class="ui-icon ui-icon-circle-plus">&nbsp;</span>')
			.appendTo(self.div)
			.css("cursor", "pointer")
			.click({diff: view, div: self.div, label: self.options.label}, function(e)
			{
				var diff = e.data.diff;
				var div = e.data.div;
				self.un_minify(diff);
				let dialog = new Et2Dialog(self.egw());

				dialog.transformAttributes({
					title: e.data.label,
					//modal: true,
					buttons: [{label: 'ok'}],
					class: "et2_diff",
				});
				diff.attr("slot", "content");
				dialog.addEventListener("open", () =>
				{
					diff.appendTo(dialog);
					if(jQuery(this).parent().height() > jQuery(window).height())
					{
						jQuery(this).height(jQuery(window).height() * 0.7);
					}
				});
				dialog.addEventListener("close", () =>
				{
					// Need to destroy the dialog, etemplate widget needs divs back where they were
					self.minify(this);

					// Put it back where it came from, or et2 will error when clear() is called
					diff.prependTo(div);
				});
				document.body.appendChild(dialog);
			});
	}
	set_label( _label)
	{
		this.options.label = _label;

	}

	/**
	 * Make the diff into a mini-diff
	 *
	 * @param {DOMNode|String} view
	 */
	minify( view)
	{
		view = jQuery(view)
			.addClass('mini')
			// Dialog changes these, if resized
			.css('height', 'inherit')
			.show();
		jQuery('th', view).hide();
		jQuery('td.equal',view).hide()
			.prevAll().hide();
	}

	/**
	 * Expand mini-diff
	 *
	 * @param {DOMNode|String} view
	 */
	un_minify( view)
	{
		jQuery(view).removeClass('mini').show();
		jQuery('th',view).show();
		jQuery('td.equal',view).show();
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 * Fast-clonable read-only widget that only deals with DOM nodes, not the widget tree
	 */

	/**
	 * Build a list of attributes which can be set when working in the
	 * "detached" mode in the _attrs array which is provided
	 * by the calling code.
	 *
	 * @param {object} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "label");
	}

	/**
	 * Returns an array of DOM nodes. The (relativly) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes()
	{
		return [this.div];
	}

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which has to be in the same order as
	 *      the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 *      returned by the "getDetachedAttributes" function and sets them to the
	 *      given values.
	 */
	setDetachedAttributes(_nodes, _values)
	{
		this.div = _nodes[0];
		if(typeof _values['label'] != 'undefined')
		{
			this.set_label(_values['label']);
		}
		if(typeof _values['value'] != 'undefined')
		{
			this.set_value(_values['value']);
		}
	}
}
et2_register_widget(et2_diff, ["diff"]);