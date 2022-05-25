/**
 * EGroupware eTemplate2 - JS Dropdown Button object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_baseWidget;
*/

import {et2_inputWidget} from './et2_core_inputWidget';
import {WidgetConfig, et2_register_widget} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";

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
 *
 * @augments et2_inputWidget
 */
export class et2_dropdown_button extends et2_inputWidget
{
	static readonly attributes : any = {
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
			"ignore": true
		}
	};

	internal_ids : any = {
		div:	"",
		button:	"",
		menu:	""
	};

	div : JQuery =  null;
	buttons : JQuery = null;
	button : JQuery = null;
	arrow : JQuery = null;
	menu : JQuery = null;
	image : JQuery = null;
	clicked : boolean = false;
	label_updates : boolean = true;
	value : any = null;
	/**
	 * Default menu, so there is something for the widget browser / editor to show
	 */
	readonly default_menu : string = '<ul> \
<li data-id="opt_1.1"><a href="#">Option-1.1</a></li>\
<li data-id="opt_1.2"><a href="#">Option-1.2</a></li>\
<li data-id="opt_1.3"><a href="#">Option-1.3</a></li>\
<li data-id="opt_1.4"><a href="#">Option-1.4<br>\
	<small>with second line</small>\
</a></li>\
<li data-id="opt_1.5"><a href="#">Option-1.5</a></li>\
</ul>';

	/**
	 * Constructor
	 *
	 * @memberOf et2_dropdown_button
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object) {
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_dropdown_button._attributes, _child || {}));

		this.clicked = false;

		let self = this;

		// Create the individual UI elements

		// Menu is a UL
		this.menu = jQuery(this.default_menu).attr("id",this.internal_ids.menu)
			.hide()
			.menu({
				select: function(event,ui) {
					self.onselect.call(self,event,ui.item);
				}
			});

		this.buttons = jQuery(document.createElement("div"))
			.addClass("et2_dropdown");

		// Main "wrapper" div
		this.div = jQuery(document.createElement("div"))
			.attr("id", this.internal_ids.div)
			.append(this.buttons)
			.append(this.menu);

		// Left side - activates click action
		this.button = jQuery(document.createElement("button"))
			.attr("id", this.internal_ids.button)
			.attr("type", "button")
			.addClass("ui-widget ui-corner-left").removeClass("ui-corner-all")
			.appendTo(this.buttons);

		// Right side - shows dropdown
		this.arrow = jQuery(document.createElement("button"))
			.addClass("ui-widget ui-corner-right").removeClass("ui-corner-all")
			.attr("type", "button")
			.click(function() {
				// ignore click on readonly button
				if (self.options.readonly) return false;
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
				jQuery( document ).one( "click", function() {
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
				function() {jQuery(this).addClass("ui-state-hover");},
				function() {jQuery(this).removeClass("ui-state-hover");}
			);

		// Icon
		this.image = jQuery(document.createElement("img"));

		this.setDOMNode(this.div[0]);
	}

	destroy() {
		// Destroy widget
		if(this.menu && this.menu.data('ui-menu')) this.menu.menu("destroy");

		// Null children
		this.image = null;
		this.button = null;
		this.arrow = null;
		this.buttons = null;
		this.menu = null;

		// Remove
		this.div.empty().remove();
	}

	set_id(_id) {
		super.set_id(_id);

		// Update internal IDs - not really needed since we refer by internal
		// javascript reference, but good to keep up to date
		this.internal_ids = {
			div:	this.dom_id + "_wrapper",
			button:	this.dom_id,
			menu:	this.dom_id + "_menu"
		};
		for(let key in this.internal_ids)
		{
			if(this[key] == null) continue;
			this[key].attr("id", this.internal_ids[key]);
		}
	}

	/**
	 * Set if the button label changes to match the selected option
	 *
	 * @param updates boolean Turn updating on or off
	 */
	set_label_updates(updates)
	{
		this.label_updates = updates;
	}

	set_accesskey(key)
	{
		jQuery(this.node).attr("accesskey", key);
	}

	set_ro_image(_image)
	{
		if(this.options.readonly)
		{
			this.set_image(_image);
		}
	}

	set_image(_image)
	{
		if(!this.isInTree() || this.image == null) return;
		if(!_image.trim())
		{
			this.image.hide();
		}
		else
		{
			this.image.show();
		}

		let src = this.egw().image(_image);
		if(src)
		{
			this.image.attr("src", src);
		}
		// allow url's too
		else if (_image[0] == '/' || _image.substr(0,4) == 'http')
		{
			this.image.attr('src', _image);
		}
		else
		{
			this.image.hide();
		}
	}

	/**
	 * Overwritten to maintain an internal clicked attribute
	 *
	 * @param _ev
	 * @returns {Boolean}
	 */
	click(_ev)
	{
		// ignore click on readonly button
		if (this.options.readonly) return false;

		this.clicked = true;

		if (!super.click(_ev))
		{
			this.clicked = false;
			return false;
		}
		this.clicked = false;
		return true;
	}

	onselect(event, selected_node)
	{
		this.set_value(selected_node.attr("data-id"));
		this.change(selected_node);
	}

	attachToDOM()
	{
		let res = super.attachToDOM();

		// Move the parent's handler to the button, or we can't tell the difference between the clicks
		jQuery(this.node).unbind("click.et2_baseWidget");
		this.button.off().bind("click.et2_baseWidget", this, function(e) {
			return e.data.click.call(e.data, this);
		});
		return res;
	}

	set_label(_value)
	{
		if (this.button)
		{
			this.label = _value;

			this.button.text(_value)
				.prepend(this.image);
		}
	}

	/**
	 * Set the options for the dropdown
	 *
	 * @param options Object ID => Label pairs
	 */
	set_select_options(options) {
		this.menu.first().empty();

		// Allow more complicated content, if passed
		if(typeof options == "string")
		{
			this.menu.append(options);
		}
		else
		{
			let add_complex = function(node, options)
			{
				for(let key in options)
				{
					let item;
					if(typeof options[key] == "string")
					{
						item = jQuery("<li data-id='"+key+"'><a href='#'>"+options[key]+"</a></li>");
					}
					else if (options[key]["label"])
					{
						item =jQuery("<li data-id='"+key+"'><a href='#'>"+options[key]["label"]+"</a></li>");
					}
					// Optgroup
					else
					{
						item = jQuery("<li><a href='#'>"+key+"</a></li>");
						add_complex(node.append("<ul>"), options[key]);
					}
					node.append(item);
					if(item && options[key].icon)
					{
						// we supply a applicable class for item images
						jQuery('a',item).prepend('<img class="et2_button_icon" src="' + (options[key].icon.match(/^(http|https|\/)/) ? options[key].icon : egw.image(options[key].icon)) +'"/>');
					}
				}
			}
			add_complex(this.menu.first(), options);
		}
		this.menu.menu("refresh");
	}

	/**
	 * Set tab index
	 */
	set_tabindex(index)
	{
		jQuery(this.button).attr("tabindex", index);
	}

	set_value(new_value)
	{
		let menu_item = jQuery("[data-id='"+new_value+"']",this.menu);
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
	}

	getValue()
	{
		return this.value;
	}

	/**
	 * Set options.readonly
	 *
	 * @param {boolean} _ro
	 */
	set_readonly(_ro : boolean)
	{
		if (_ro != this.options.readonly)
		{
			this.options.readonly = _ro;

			// don't make readonly dropdown buttons clickable
			if (this.buttons)
			{
				this.buttons.find('button')
					.toggleClass('et2_clickable', !_ro)
					.toggleClass('et2_button_ro', _ro)
					.css('cursor', _ro ? 'default' : 'pointer');
			}
		}
	}
}
et2_register_widget(et2_dropdown_button, ["dropdown_button"]);