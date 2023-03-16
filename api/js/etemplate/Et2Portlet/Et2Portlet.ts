/**
 * EGroupware eTemplate2 - Portlet base
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2022 Nathan Gray
 */


import {Et2Widget} from "../Et2Widget/Et2Widget";
import {SlCard} from "@shoelace-style/shoelace";
import interact from "@interactjs/interactjs";
import type {InteractEvent} from "@interactjs/core/InteractEvent";
import {egw} from "../../jsapi/egw_global";
import {classMap, css, html, TemplateResult} from "@lion/core";
import {HasSlotController} from "@shoelace-style/shoelace/dist/internal/slot";
import shoelace from "../Styles/shoelace";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {et2_IResizeable} from "../et2_core_interfaces";
import {HomeApp} from "../../../../home/js/app";
import {etemplate2} from "../etemplate2";
import {SelectOption} from "../Et2Select/FindSelectOptions";

/**
 * Participate in Home
 */

export class Et2Portlet extends Et2Widget(SlCard)
{
	static get properties()
	{
		return {
			...super.properties,


			/**
			 * Give a title
			 * Goes in the header at the top with the icons
			 */
			title: {type: String},

			/**
			 * Custom etemplate used to customize / set up the portlet
			 */
			editTemplate: {type: String},
			/**
			 * Set the portlet color
			 */
			color: {type: String},

			/**
			 * Array of customization settings, similar in structure to preference settings
			 */
			settings: {type: Object},
			actions: {type: Object},
		}
	}

	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
			  :host {
				--header-spacing: var(--sl-spacing-medium);
			  }

			  .portlet__header {
				flex: 0 0 auto;
				display: flex;
				font-style: inherit;
				font-variant: inherit;
				font-weight: inherit;
				font-stretch: inherit;
				font-family: inherit;
				font-size: var(--sl-font-size-medium);
				line-height: var(--sl-line-height-dense);
				padding: 0px;
				padding-left: var(--header-spacing);
				padding-right: calc(2em + var(--header-spacing));
				margin: 0px;
				position: relative;
			  }

			  .portlet__title {
				flex: 1 1 auto;
				font-size: var(--sl-font-size-medium);
				user-select: none;
			  }

			  .portlet__header et2-button-icon {
				display: none;
			  }

			  .portlet__header:hover et2-button-icon {
				display: initial;
			  }

			  .portlet__header #settings {
				position: absolute;
				right: 0px;
			  }

			  .card {
				width: 100%;
				height: 100%
			  }

			  .card_header {
				margin-right: calc(var(--sl-spacing-medium) + 1em);
			  }

			  .card__body {
				/* display block to prevent overflow from our size */
				display: block;
				overflow: hidden;

				flex: 1 1 auto;
				padding: 0px;
			  }


			  ::slotted(div) {
			  }
			`
		]
	}

	protected readonly hasSlotController = new HasSlotController(this, 'footer', 'header', 'image');

	/**
	 * These are the "normal" actions that every portlet is expected to have.
	 * The widget provides default actions for all of these, but they can
	 * be added to or overridden if needed by setting the action attribute.
	 */
	protected static default_actions : any = {
		edit_settings: {
			icon: "edit",
			caption: "Configure",
			"default": true,
			hideOnDisabled: true,
			group: "portlet"
		},
		remove_portlet: {
			icon: "delete",
			caption: "Remove",
			group: "portlet"
		}
	};

	protected static DEFAULT_WIDTH = 2;
	protected static DEFAULT_HEIGHT = 2;

	constructor()
	{
		super();
		this.editTemplate = egw.webserverUrl + "/home/templates/default/edit.xet"
		this.actions = {};

		this._onMoveResize = this._onMoveResize.bind(this);
		this._onMoveResizeEnd = this._onMoveResizeEnd.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		Promise.all([/* any others here...*/ this.updateComplete])
			.then(() => this._setupMoveResize());
	}

	/**
	 * Load further details from content
	 *
	 * Normal load & attribute assign will cast our settings object to a string
	 * @param _template_node
	 */
	transformAttributes(attrs)
	{
		// Pull out width - super will handle it wrong then remove it
		let width
		if(typeof attrs.width != "undefined")
		{
			width = attrs.width;
			delete attrs.width;
		}

		super.transformAttributes(attrs);

		// If width was provided, put it back
		if(typeof width != "undefined")
		{
			attrs.width = width;
		}
		let data = this.getArrayMgr("content").data.find(e => e.id && e.id == this.id) || {};
		this.settings = typeof attrs.settings == "string" ? data.value || data.settings || {} : attrs.settings;

		// Set size & position, if available
		// NB: initial load can't find them by entry in array mgr, we check the data directly
		if(attrs.row || attrs.height || data.row || data.height)
		{
			this.style.gridRow = (attrs.row || data.row || "auto") + " / span " + (attrs.height || data.height || this.constructor.DEFAULT_HEIGHT);
		}
		if(attrs.col || attrs.width || data.col || data.width)
		{
			this.style.gridColumn = (attrs.col || data.col || "auto") + " / span " + (attrs.width || data.width || this.constructor.DEFAULT_WIDTH);
		}
	}

	/**
	 * Overriden from parent to add in default actions
	 */
	set_actions(actions)
	{
		// Set targets for actions
		let defaults : any = {};
		for(let action_name in Et2Portlet.default_actions)
		{
			defaults[action_name] = Et2Portlet.default_actions[action_name];
			// Translate caption here, as translations aren't available earlier
			defaults[action_name].caption = this.egw().lang(Et2Portlet.default_actions[action_name].caption);
			if(typeof this[action_name] == "function")
			{
				defaults[action_name].onExecute = this[action_name].bind(this);
			}
		}

		// Add in defaults, but let provided actions override them
		this.actions = jQuery.extend(true, {}, defaults, actions);
	}

	/**
	 * Set up moving & resizing
	 */
	_setupMoveResize()
	{
		// Quick calculation of min size - dialog is made up of header, content & buttons
		let minHeight = 0;
		for(let e of this.children)
		{
			minHeight += e.getBoundingClientRect().height + parseFloat(getComputedStyle(e).marginTop) + parseFloat(getComputedStyle(e).marginBottom)
		}

		// Get parent's dimensions
		let style = getComputedStyle(this.parentElement);
		let parentDimensions = {
			width: parseInt(style.gridAutoColumns) + parseInt(style.gap) || HomeApp.GRID,
			height: parseInt(style.gridAutoRows) + parseInt(style.gap) || HomeApp.GRID
		};

		let gridTarget = interact.snappers.grid({
			x: parentDimensions.width,
			y: parentDimensions.height
		});

		interact(this)
			.resizable({
				edges: {bottom: true, right: true},
				listeners: {
					move: this._onMoveResize,
					end: this._onMoveResizeEnd
				},
				modifiers: [
					// Snap to grid
					interact.modifiers.snap({
						targets: [gridTarget],
						offset: "startCoords",
						limits: {top: 0, left: 0}
					}),

					// keep the edges inside the parent
					interact.modifiers.restrictEdges({
						outer: 'parent'
					})
				]
			})
			.draggable({
				allowFrom: ".portlet__header",
				autoScroll: true,
				listeners: {
					move: this._onMoveResize,
					end: this._onMoveResizeEnd
				},
				modifiers: [
					// Restrict interferes with grid making it act strangely
					//interact.modifiers.restrict({
					//	restriction: 'parent'
					//}),
					// Snap to grid
					interact.modifiers.snap({
						targets: [gridTarget],
						offset: "startCoords",
						limits: {top: 0, left: 0}
					})
				]
			});
	}

	/**
	 * Handle moving and resizing
	 *
	 * @param event
	 */
	_onMoveResize(event : InteractEvent)
	{
		let target = event.target
		let x = (parseFloat(target.getAttribute('data-x')) || 0) + (event.deltaRect ? 0 : event.dx);
		let y = (parseFloat(target.getAttribute('data-y')) || 0) + (event.deltaRect ? 0 : event.dy);

		// update the element's style
		// Size
		target.style.width = event.rect.width + 'px'
		target.style.height = event.rect.height + 'px'

		// Position
		target.style.transform = 'translate(' + x + 'px,' + y + 'px)';

		target.setAttribute('data-x', x);
		target.setAttribute('data-y', y);
	}

	/**
	 * Move or resize has completed.  Now into parent grid and update settings.
	 *
	 * @param {InteractEvent} event
	 */
	_onMoveResizeEnd(event : InteractEvent)
	{

		// Get parent's dimensions
		let style = getComputedStyle(this.parentElement);
		let parentDimensions = {
			x: parseInt(style.gridAutoColumns) || 1,
			y: parseInt(style.gridAutoRows) || 1
		}
		let target = event.target
		let dx = Math.round((parseInt(target.getAttribute('data-x')) || 0) / parentDimensions.x);
		let dy = Math.round((parseInt(target.getAttribute('data-y')) || 0) / parentDimensions.y);
		let dwidth = Math.round((event.deltaRect?.width || 1) / parentDimensions.x);
		let dheight = Math.round((event.deltaRect?.height || 1) / parentDimensions.y);
		let [o_x, o_width] = this.style.gridColumn.split(" / span");
		let [o_y, o_height] = this.style.gridRow.split(" / span");

		// Clear temp stuff from moving
		target.style.transform = "";
		target.style.width = "";
		target.style.height = "";
		target.removeAttribute('data-x');
		target.removeAttribute('data-y');
		if(o_x == "auto")
		{
			o_x = "" + (1 + Math.round((this.getBoundingClientRect().left - this.parentElement.getBoundingClientRect().left) / parentDimensions.x));
		}

		let col = Math.max(1, (dx + (parseInt(o_x) || 0)));
		let row = Math.max(1, (dy + (parseInt(o_y) || 0)));
		let width = (dwidth + parseInt(o_width)) || 1;
		let height = (dheight + parseInt(o_height)) || 1;

		// Set grid position
		target.style.gridArea = row + " / " +
			col + "/ span " +
			height + " / span " +
			width;

		// Update position settings
		this.update_settings({row: row, col: col, width: width, height: height});

		// If there's a full etemplate living inside, make it resize
		etemplate2.getById(this.id)?.resize();
	}


	imageTemplate()
	{
		return '';
	}

	headerTemplate()
	{
		return html`
            <h2 class="portlet__title">${this.title}</h2>`;
	}

	bodyTemplate() : TemplateResult
	{
		return html``;
	}

	footerTemplate() : TemplateResult
	{
		return html``;
	}


	/**
	 * Get a list of user-configurable properties
	 * @returns {[{name : string, type : string, select_options? : [SelectOption]}]}
	 */
	get portletProperties() : { name : string, type : string, label : string, select_options? : SelectOption[] }[]
	{
		return [
			{name: 'color', label: "Color", type: 'et2-colorpicker'}
		];
	}

	/**
	 * Create & show a dialog for customizing this portlet
	 *
	 * Properties for customization are sent in the 'settings' attribute
	 */
	edit_settings()
	{
		let content = this.portletProperties;

		// Add values, but skip any duplicate properties
		Object.keys(this.settings || {}).forEach(k =>
		{
			if(typeof k == "string" && isNaN(parseInt(k)) || content.filter(v => v.name == this.settings[k].name).length == 0)
			{
				content[k] = this.settings[k];
			}
		});

		let dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
			callback: this._process_edit.bind(this),
			template: this.editTemplate,
			value: {
				content: content
			},
			buttons: [
				{
					"button_id": Et2Dialog.OK_BUTTON,
					label: this.egw().lang('ok'),
					id: 'dialog[ok]',
					image: 'check',
					"default": true
				},
				{
					label: this.egw().lang('delete'),
					id: 'delete',
					image: 'delete',
					align: "right"
				},
				{
					"button_id": Et2Dialog.CANCEL_BUTTON,
					label: this.egw().lang('cancel'),
					id: 'cancel',
					image: 'cancel'
				}
			],
		});
		// Set separately to avoid translation
		dialog.title = this.egw().lang("Edit") + " " + (this.title || '');
		this.appendChild(dialog);
	}

	_process_edit(button_id, value)
	{
		if(button_id != Et2Dialog.OK_BUTTON)
		{
			if(button_id == "delete")
			{
				this.update_settings('~remove~').then(() =>
				{
					this.remove();
				});
			}
			return;
		}

		// Pass updated settings, unless we're removing
		this.update_settings({...this.settings, ...value});

		// Extend, not replace, because settings has types while value has just value
		if(typeof value == 'object')
		{
			this.settings = {...this.settings, value};
		}
		this.requestUpdate();
	}

	public update_settings(settings)
	{
		// Skip any updates during loading
		if(this.getInstanceManager() && !this.getInstanceManager().isReady)
		{
			return Promise.resolve();
		}

		// We can set some things immediately, server will overwrite if it doesn't like them
		this.portletProperties.forEach(p =>
		{
			if(typeof settings[p.name] != "undefined")
			{
				this[p.name] = settings[p.name];
			}
		})

		// Save settings - server might reply with new content if the portlet needs an update,
		// but ideally it doesn't
		this.classList.add("loading");

		return this.egw().request("home.home_ui.ajax_set_properties", [this.id, [], settings, this.settings ? this.settings.group : false])
			.then((data) =>
			{
				// This section not for us
				if(!data || typeof data.attributes == 'undefined')
				{
					return false;
				}

				this.classList.remove("loading");

				this.transformAttributes(data.attributes);

				// Flagged as needing to edit settings?  Open dialog
				if(typeof data.edit_settings != 'undefined' && data.edit_settings)
				{
					this.edit_settings();
				}

				// Only resize once, and only if needed
				if(data.attributes.width || data.attributes.height)
				{
					// Tell children
					try
					{
						this.iterateOver(function(widget)
						{
							if(typeof widget.resize === 'function')
							{
								widget.resize();
							}
						}, null, et2_IResizeable);
					}
					catch(e)
					{
						// Something went wrong, but do not stop
						this.egw().debug('warn', e, this);
					}
				}
			});
	}

	render()
	{
		return html`
            <style>
                ${this.color ? ".card  {--border-color: " + this.color + "}" : ""}
            </style>
            <div
                    part="base"
                    class=${classMap({
                        card: true,
                        'card--has-footer': this.hasSlotController.test('footer'),
                        'card--has-image': this.hasSlotController.test('image'),
                        'card--has-header': true,
                        'et2-portlet': true
                    })}
            >
                <slot name="image" part="image" class="card__image">${this.imageTemplate()}</slot>

                <header class="portlet__header">
                    <slot name="header" part="header" class="card__header">${this.headerTemplate()}</slot>
                    <et2-button-icon id="settings" name="gear" label="Settings" noSubmit=true
                                     @click="${() => this.edit_settings()}"></et2-button-icon>
                </header>
                <slot part="body" class="card__body">${this.bodyTemplate()}</slot>
                <slot name="footer" part="footer" class="card__footer">${this.footerTemplate()}</slot>
            </div>
		`;
	}

}

if(!customElements.get("et2-portlet"))
{
	customElements.define("et2-portlet", Et2Portlet);
}