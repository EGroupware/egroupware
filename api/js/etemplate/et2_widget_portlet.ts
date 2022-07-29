/**
 * EGroupware eTemplate2 - JS Portlet object - used by Home
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package home
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

/*egw:uses
        /vendor/bower-asset/jquery/dist/jquery.js;
        et2_core_baseWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_action_object_impl, et2_DOMWidget} from "./et2_core_DOMWidget";
import {egw} from "../jsapi/egw_global";
import {et2_no_init} from "./et2_core_common";
import {et2_IResizeable} from "./et2_core_interfaces";
import {
	egw_getAppObjectManager,
	egwAction,
	egwActionObject,
	egwActionObjectInterface
} from "../egw_action/egw_action.js";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {ColorTranslator} from 'colortranslator';
/**
 * Class which implements the UI of a Portlet
 *
 * This manages the frame and decoration, but also provides the UI for properties.
 *
 * Portlets are only internal to EGroupware.
 *
 * Home does not fully implement WSRP, but tries not to conflict, ether.
 * @link http://docs.oasis-open.org/wsrp/v2/wsrp-2.0-spec-os-01.html
 * @augments et2_baseWidget
 */
export class et2_portlet extends et2_valueWidget
{
	static readonly _attributes : any = {
		"title": {
			"name": "Title",
			"description": "Goes in the little bit at the top with the icons",
			"type": "string",
			"default": ""
		},
		"edit_template": {
			"name": "Edit template",
			"description": "Custom eTemplate used to customize / set up the portlet",
			"type": "string",
			"default": egw.webserverUrl+"/home/templates/default/edit.xet"
		},
		"color": {
			"name": "Color",
			"description": "Set the portlet color",
			"type": "string",
			"default": ''
		},
		"settings": {
			"name": "Customization settings",
			"description": "Array of customization settings, similar in structure to preference settings",
			"type": "any",
			"default": et2_no_init
		},
		"actions": {
			default: {}
		},
		"width": { "default": 2, "ignore": true},
		"height": { "default": 1, "type": "integer"},
		"rows": {"ignore": true, default: et2_no_init},
		"cols": {"ignore": true, default: et2_no_init},
		"resize_ratio": {"ignore": true, default: et2_no_init}, // Portlets are explicitly sized
		"row": {
			"name": "Row",
			"description": "Home page location (row) - handled by home app",
			"default": 1
		},
		"col": {
			"name": "Column",
			"description": "Home page location(column) - handled by home app",
			"default": 1
		}
	};

	private GRID : number = 55;
	private settings: any;
	/**
	 * These are the "normal" actions that every portlet is expected to have.
	 * The widget provides default actions for all of these, but they can
	 * be added to or overridden if needed by setting the action attribute.
	 */
	protected default_actions : any = {
		edit_settings: {
			icon:	"edit",
			caption: "Configure",
			"default": true,
			hideOnDisabled: true,
			group:	"portlet"
		},
		remove_portlet: {
			icon:	"delete",
			caption: "Remove",
			group:	"portlet"
		}
	};
	protected div: JQuery;
	protected header: JQuery;
	protected content: JQuery;

	/**
	 * Constructor
	 *
	 * @memberOf et2_portlet
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_portlet._attributes, _child || {}));

		let self = this;

		// Create DOM nodes
		this.div = jQuery(document.createElement("div"))
			.addClass(this.options.class)
			.addClass("ui-widget ui-widget-content ui-corner-all")
			.addClass("et2_portlet")
			/* Gridster */
			.attr("data-sizex", this.options.width)
			.attr("data-sizey", this.options.height)
			.attr("data-row", this.options.row)
			.attr("data-col", this.options.col)

			.resizable( {
				autoHide: true,
				grid:	this.GRID,
				//containment: this.getParent().getDOMNode(),
				stop: function(event, ui) {
					self.set_width(Math.round(ui.size.width / self.GRID));
					self.set_height(Math.round(ui.size.height / self.GRID));
					self.egw().jsonq("home.home_ui.ajax_set_properties",[self.id, {},{
							width: self.options.width,
							height: self.options.height
						}],
						null,
						self
					);
					// Tell children
					self.iterateOver(function(widget) {
						if (typeof widget.resize === 'function')
						{
							widget.resize();
						}
					},null,et2_IResizeable);
				}
			});
		this.header = jQuery(document.createElement("div"))
			.attr('id', this.getInstanceManager().uniqueId+'_'+this.id.replace(/\./g, '-') + '_header')
			.addClass("ui-widget-header ui-corner-all")
			.appendTo(this.div)
			.html(this.options.title);
		this.content = jQuery(document.createElement("div"))
			.attr('id', this.getInstanceManager().uniqueId+'_'+this.id.replace(/\./g, '-') + '_content')
			.appendTo(this.div);

		this.setDOMNode(this.div[0]);
	}

	destroy()
	{
		for(let i = 0; i < this._children.length; i++)
		{
			// Check for child is a different template and clear it,
			// since it won't be cleared by destroy()
			if(this._children[i].getInstanceManager() != this.getInstanceManager())
			{
				this._children[i].getInstanceManager().clear();
			}
		}
		super.destroy();
	}

	doLoadingFinished()
	{
		this.set_color(this.options.color);
		return true;
	}

	/**
	 * If anyone asks, return the content node, so content goes inside
	 */
	getDOMNode(_sender)
	{
		if(typeof _sender != 'undefined' && _sender != this)
		{
			return this.content[0];
		}
		return super.getDOMNode(_sender);
	}

	/**
	 * Overriden from parent to add in default actions
	 */
	set_actions(actions)
	{
		// Set targets for actions
		let defaults: any = {};
		for(let action_name in this.default_actions)
		{
			defaults[action_name] = this.default_actions[action_name];
			// Translate caption here, as translations aren't available earlier
			defaults[action_name].caption = this.egw().lang(this.default_actions[action_name].caption);
			if(typeof this[action_name] == "function")
			{
				defaults[action_name].onExecute = jQuery.proxy(this[action_name],this);
			}
		}

		// Add in defaults, but let provided actions override them
		this.options.actions = jQuery.extend(true,{},defaults,actions);
		super.set_actions(this.options.actions);
	}

	/**
	 * Override _link_actions to remove edit action, if there is no settings
	 *
	 * @param actions
	 */
	_link_actions(actions)
	{
		// Get the top level element
		let objectManager = <egwActionObject><unknown>egw_getAppObjectManager(true);
		let widget_object =objectManager.getObjectById(this.id);
		if (widget_object == null)
		{
			// Add a new container to the object manager which will hold the widget
			// objects
			widget_object = objectManager.insertObject(false, new egwActionObject(
				this.id, objectManager, <egwActionObjectInterface><unknown>new et2_action_object_impl(<et2_DOMWidget><unknown>this).getAOI(),
				this._actionManager || (<egwAction><unknown>objectManager.manager).getActionById(this.id) || objectManager.manager
			));
		}

		// Delete all old objects
		widget_object.clear();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		let action_links = [];
		for(let i in actions)
		{
			let id = typeof actions[i].id != 'undefined' ? actions[i].id : i;
			let action = {
				actionId: id,
				enabled: true
			};

			// If there are no settings, there can be no customization, so remove the edit action
			if(id == 'edit_settings' && (!this.options.settings || jQuery.isEmptyObject(this.options.settings)))
			{
				this.egw().debug("log", "No settings for portlet %o, edit_settings action removed", this);
				action.enabled = false;
			}
			action_links.push(action);
		}

		widget_object.updateActionLinks(action_links);
	}

	/**
	 * Create & show a dialog for customizing this portlet
	 *
	 * Properties for customization are sent in the 'settings' attribute
	 */
	edit_settings()
	{
		let dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
			callback: this._process_edit.bind(this),
			template: this.options.edit_template,
			value: {
				content: this.options.settings
			},
			buttons: Et2Dialog.BUTTONS_OK_CANCEL
		});
		// Set separately to avoid translation
		dialog.title = this.egw().lang("Edit") + " " + (this.options.title || '');
	}

	_process_edit(button_id, value)
	{
		if(button_id != Et2Dialog.OK_BUTTON)
		{
			return;
		}


		// Save settings - server might reply with new content if the portlet needs an update,
		// but ideally it doesn't
		this.div.addClass("loading");

		// Pass updated settings, unless we're removing
		let settings = (typeof value == 'string') ? {} : this.options.settings || {};
		this.egw().jsonq("home.home_ui.ajax_set_properties",[this.id, settings, value, this.settings ? this.settings.group : false],
			function(data) {
				// This section not for us
				if(!data || typeof data.attributes == 'undefined') return false;

				this.div.removeClass("loading");
				this.set_value(data.content);
				for(let key in data.attributes)
				{
					if(typeof this["set_"+key] == "function")
					{
						this["set_"+key].call(this, data.attributes[key]);
					}
					else if (this.attributes[key])
					{
						this.options[key] = data.attributes[key];
					}
				}

				// Flagged as needing to edit settings?  Open dialog
				if(typeof data.edit_settings != 'undefined' && data.edit_settings)
				{
					this.edit_settings();
				}

				// Only resize once, and only if needed
				if(data.attributes.width || data.attributes.height)
				{
					// Tell children
					try {
						this.iterateOver(function(widget) {
							if (typeof widget.resize === 'function')
							{
								widget.resize();
							}
						},null,et2_IResizeable);
					} catch (e) {
						// Something went wrong, but do not stop
						egw.debug('warn',e,this);
					}
				}
			},
			this);

		// Extend, not replace, because settings has types while value has just value
		if(typeof value == 'object')
		{
			jQuery.extend(this.options.settings, value);
		}
	}

	/**
	 * Remove this portlet from the home page
	 */
	remove_portlet()
	{
		let self = this;
		Et2Dialog.show_dialog(function(button_id)
			{
				if(button_id != Et2Dialog.OK_BUTTON)
				{
					return;
				}
				self._process_edit(button_id, '~remove~');
				self.getParent().removeChild(self);
				self.destroy();
			}, this.egw().lang("Remove"), this.options.title, {},
			Et2Dialog.BUTTONS_OK_CANCEL, Et2Dialog.QUESTION_MESSAGE
		);
	}

	/**
	 * Set the HTML content of the portlet
	 *
	 * @param value String HTML fragment
	 */
	set_value(value)
	{
		this.content.html(value);
	}

	/**
	 * Set the content of the header
	 *
	 * @param value String HTML fragment
	 */
	set_title(value)
	{
		this.header.contents()
			.filter(function() {
				return this.nodeType === 3;
			})
			.remove();
		this.options.title = value;
		this.header.append(value);
	}

	/**
	 * Let this portlet stand out a little by allowing a custom color
	 */
	set_color(color)
	{
		this.options.color = color;
		this.header.css("backgroundColor", color);
		this.header.css('color', (new ColorTranslator(this.header.css("backgroundColor"))).L > 50 ? 'black':'white');
		this.content.css("backgroundColor", color);
	}

	/**
	 * Set the number of grid cells this widget spans
	 *
	 * @param value int Number of horizontal grid cells
	 */
	set_width(value)
	{
		this.options.width = value;
		this.div.attr("data-sizex", value);
		// Clear what's there from jQuery, we get width from CSS according to sizex
		this.div.css('width','');
	}

	/**
	 * Set the number of vertical grid cells this widget spans
	 *
	 * @param value int Number of vertical grid cells
	 */
	set_height(value)
	{
		this.options.height = value;
		this.div.attr("data-sizey", value);
		// Clear what's there from jQuery, we get width from CSS according to sizey
		this.div.css('height','');
	}
}
et2_register_widget(et2_portlet, ["portlet"]);