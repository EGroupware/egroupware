/**
 * EGroupware eTemplate2 - JS Diff object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
	/vendor/bower-asset/diff2html/dist/diff2html.min.js;
	et2_core_valueWidget;
*/

/**
 * Class that displays the diff between two [text] values
 *
 * @augments et2_valueWidget
 */
var et2_diff = (function(){ "use strict"; return et2_valueWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"value": {
			"type": "any"
		}
	},

	diff_options: {
		"inputFormat":"diff",
		"matching": "words"
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_diff
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.mini = true;

		// included via etemplate2.css
		//this.egw().includeCSS('../../../vendor/bower-asset/dist/dist2html.css');
		this.div = document.createElement("div");
		jQuery(this.div).addClass('diff');
	},

	set_value: function(value) {
		jQuery(this.div).empty();
		if(typeof value == 'string') {

			// Diff2Html likes to have files, we don't have them
			if(value.indexOf('---') !== 0)
			{
				value = "--- diff\n+++ diff\n"+value;
			}
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
	},

	check_mini: function() {
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
			.click({diff: view, div: self.div}, function(e) {
				var diff = e.data.diff;
				var div = e.data.div;
				self.un_minify(diff);
				var dialog_div = jQuery('<div>')
					.append(diff);

				dialog_div.dialog({
					title: self.options.label,
					width: 'auto',
					autoResize: true,
					modal: true,
					buttons: [{text: self.egw().lang('ok'), click: function() {jQuery(this).dialog("close");}}],
					close: function(event, ui) {
						// Need to destroy the dialog, etemplate widget needs divs back where they were
						dialog_div.dialog("destroy");
						self.minify(this);

						// Put it back where it came from, or et2 will error when clear() is called
						diff.prependTo(div);
					}
				});
			});
	},
	set_label: function(_label) {
		this.options.label = _label;

	},

	/**
	 * Make the diff into a mini-diff
	 *
	 * @param {DOMNode|String} view
	 */
	minify: function(view) {
		view = jQuery(view)
			.addClass('mini')
			// Dialog changes these, if resized
			.width('100%').css('height', 'inherit')
			.show();
		jQuery('th', view).hide();
		jQuery('td.equal',view).hide()
			.prevAll().hide();
	},

	/**
	 * Expand mini-diff
	 *
	 * @param {DOMNode|String} view
	 */
	un_minify: function(view) {
		jQuery(view).removeClass('mini').show();
		jQuery('th',view).show();
		jQuery('td.equal',view).show();
	},

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
        getDetachedAttributes: function(_attrs) {
                _attrs.push("value", "label");
        },

        /**
         * Returns an array of DOM nodes. The (relativly) same DOM-Nodes have to be
         * passed to the "setDetachedAttributes" function in the same order.
         */
        getDetachedNodes: function() {
                return [this.div];
        },

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
        setDetachedAttributes: function(_nodes, _values) {
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
});}).call(this);
et2_register_widget(et2_diff, ["diff"]);
