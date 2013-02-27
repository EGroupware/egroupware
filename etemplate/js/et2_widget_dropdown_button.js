/**
 * eGroupWare eTemplate2 - JS Dropdown Button object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	jquery.jquery-ui;
	et2_baseWidget;
*/

/**
 * A split button - a button with a dropdown list
 * 
 * There are several parts to the button UI:
 * - Container: This is what is percieved as the dropdown button, the whole package together
 *   - Button: The part on the left that can be clicked
 *   - Arrow: The button to display the choices
 *   - Menu: The list of choices
 *
 * Menu options are passed via the select_options.  They are normally ID => Title pairs, 
 * as for a select box, but the title can also be full HTML if needed.
 */ 
var et2_dropdown_button = et2_inputWidget.extend({

	attributes: {
		"label": {
			"name": "caption",
			"type": "string",
			"description": "Label of the button",
			"translate": true,
			"default": "Select..."
		},
		"label_updates": {
			"name": "Label updates",
			"type": "boolean",
			"description": "Button label updates when an option is selected from the menu",
			"default": true
		},
		"image": { 
			"name": "Icon",
			"type": "string",
			"description": "Add an icon"
		},
		"ro_image": { 
			"name": "Read-only Icon",
			"type": "string",
			"description": "Use this icon instead of hiding for read-only"
		},
		"onclick": {
			"name": "onclick",
			"type": "string",
			"description": "JS code which gets executed when the button is clicked"
		},
		"select_options": {
			"type": "any",
			"name": "Select options",
			"default": {},
			"description": "Select options for dropdown.  Can be a simple key => value list, or value can be full HTML",
			// Skip normal initialization for this one
			"ignore": true
		},
		"accesskey": {
			"name": "Access Key",
			"type": "string",
			"default": et2_no_init,
			"description": "Alt + <key> activates widget"
		},
		"tabindex": {
			"name": "Tab index",
			"type": "integer",
			"default": et2_no_init,
			"description": "Specifies the tab order of a widget when the 'tab' button is used for navigating."
		},
		// No such thing as a required button
		"required": {
			"ignore": true,
		}
	},

	internal_ids: {
		div:	"",
		button:	"",
		menu:	""
	},

	div: null,
	buttons: null,
	button: null,
	menu: null,

	/**
	 * Default menu, so there is something for the widget browser / editor to show
	 */
	default_menu: '<ul> \
	<li id="opt_1.1"><a href="javascript:void(0);"><img src="' + egw().image("navbar") + '"/>Option-1.1</a></li>\
	<li id="opt_1.2"><a href="javascript:void(0);">Option-1.2</a></li>\
	<li id="opt_1.3"><a href="javascript:void(0);">Option-1.3</a></li>\
	<li id="opt_1.4"><a href="javascript:void(0);">Option-1.4<br>\
		<small>with second line</small>\
	</a></li>\
	<li id="opt_1.5"><a href="javascript:void(0);">Option-1.5</a></li>\
</ul>',

	init: function() {
		this._super.apply(this, arguments);

		this.clicked = false;

		var self = this;

		// Create the individual UI elements

		// Menu is a UL
		this.menu = $j(this.default_menu).attr("id",this.internal_ids.menu)
			.hide()
			.menu({
				selected: function(event,ui) {
					self.onselect(event,ui.item);
				}
			});

		this.buttons = $j(document.createElement("div"))
			.addClass("et2_dropdown");
		
		// Main "wrapper" div
		this.div = $j(document.createElement("div"))
			.attr("id", this.internal_ids.div)
			.append(this.buttons)
			.append(this.menu);

		// Left side - activates click action
		this.button = $j(document.createElement("button"))
			.attr("id", this.internal_ids.button)
			.addClass("ui-widget ui-corner-left").removeClass("ui-corner-all")
			.appendTo(this.buttons);

		// Right side - shows dropdown
		this.arrow = $j(document.createElement("button"))
			.addClass("ui-widget ui-corner-right").removeClass("ui-corner-all")
			.click(function() {
				// Clicking it again hides menu
				if(self.menu.is(":visible"))
				{
					self.menu.hide();
					return false;
				}
				// Show menu dropdown
				var menu = self.menu.show().position({
					my: "left top",
					at: "left bottom",
					of: self.buttons
				});
				// Hide menu if clicked elsewhere
				$j( document ).one( "click", function() {
					menu.hide();
				});
				return false;
			})
			// This is the actual down arrow icon
			.append("<div class='ui-icon ui-icon-triangle-1-s'/>")
			.appendTo(this.buttons);

		// Common button UI
		this.buttons.children("button")
			.addClass("ui-state-default")
			.hover(
				function() {$j(this).addClass("ui-state-hover");},
				function() {$j(this).removeClass("ui-state-hover");}
			);
	
		// Icon
		this.image = jQuery(document.createElement("img"))

		this.setDOMNode(this.div[0]);
	},

	destroy: function() {
		// Destroy widget
		this.menu.menu("destroy");

		// Null children
		this.image = null;
		this.button = null;
		this.arrow = null;
		this.buttons = null;
		this.menu = null;

		// Remove
		this.div.empty().remove();
	},

	set_id: function(_id) {
		this._super.apply(this, arguments);

		// Update internal IDs - not really needed since we refer by internal 
		// javascript reference, but good to keep up to date
		this.internal_ids = {
			div:	this.id + "_wrapper",
			button:	this.id,
			menu:	this.id + "_menu"
		};
		for(var key in this.internal_ids)
		{
			if(this[key] == null) continue;
			this[key].attr("id", this.internal_ids[key]);
		}
	},

	/**
	 * Set if the button label changes to match the selected option
	 *
	 * @param updates boolean Turn updating on or off
	 */
	set_label_updates: function(updates) {
		this.label_updates = updates;
	},

	set_accesskey: function(key) {
		jQuery(this.node).attr("accesskey", key);
	},
	set_ro_image: function(_image) {
		if(this.options.readonly)
		{
			this.set_image(_image);
		}
	},

	set_image: function(_image) {
		if(!this.isInTree() || this.image == null) return;
		var found_image = false;
		if(!_image.trim())
		{
			this.image.hide();
		}
		else
		{
			this.image.show();
		}

		var src = this.egw().image(_image);
		if(src)
		{
			this.image.attr("src", src);
			found_image = true;
		}
		// allow url's too
		else if (_image[0] == '/' || _image.substr(0,4) == 'http')
		{
			this.image.attr('src', _image);
			found_image = true;
		}
		else
		{
			this.image.hide();
		}
	},

	onclick: function(_node) {
		this.clicked = true;

		// Execute the JS code connected to the event handler
		if (this.options.onclick)
		{
			// Exectute the legacy JS code
			if (!(et2_compileLegacyJS(this.options.onclick, this, _node))())
			{
				this.clicked = false;
				return false;
			}
		}

		this.clicked = false;
	},

	onselect: function(event, selected_node) {
		this.set_value(selected_node.attr("id"));
		this.change(selected_node);
	},

	attachToDOM: function() {
		this._super.apply(this, arguments);

		// Move the parent's handler to the button, or we can't tell the difference between the clicks
		$j(this.node).unbind("click.et2_baseWidget");
		this.button.bind("click.et2_baseWidget", this, function(e) {
			return e.data.click.call(e.data, this);
		});
	},

	set_label: function(_value) {
		if (this.button)
		{
			this.label = _value;

			this.button.text(_value)
				.prepend(this.image);
		}
	},

	/**
	 * Set the options for the dropdown
	 *
	 * @param options Object ID => Label pairs
	 */
	set_select_options: function(options) {
		this.menu.first().empty();

		// Allow more complicated content, if passed
		if(typeof options == "string")
		{
			this.menu.append(options);
		}
		else
		{
			for(var key in options)
			{
				this.menu.first().append("<li id='"+key+"'><a href='javascript:void(0);'>"+options[key]+"</a></li>");
			}
		}
		this.menu.menu("refresh");
	},

	/**
	 * Set tab index
	 */
	set_tabindex: function(index) {
		jQuery(this.button).attr("tabindex", index);
	},

	set_value: function(new_value) {
		var menu_item = $j("[id='"+new_value+"']",this.menu);
		if(menu_item.length)
		{
			this.value = new_value;
			if(this.label_updates)
			{
				this.set_label(menu_item.text());
			}
		}
		else
		{
			this.value = null;
			if(this.label_updates)
			{
				this.set_label(this.options.label);
			}
		}
	},

	getValue: function() {
		return this.value;
	}
});

et2_register_widget(et2_dropdown_button, ["dropdown_button"]);

