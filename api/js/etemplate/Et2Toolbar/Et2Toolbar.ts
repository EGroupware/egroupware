/**
 * EGroupware eTemplate2 - Toolbar WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {html, nothing, PropertyValueMap} from "lit";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";
import {classMap} from "lit/directives/class-map.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import shoelace from "../Styles/shoelace";
import styles from "./Et2Toolbar.styles";
import {state} from "lit/decorators/state.js";
import {EgwAction} from "../../egw_action/EgwAction";
import {Et2DropdownButton} from "../Et2DropdownButton/Et2DropdownButton";
import {loadWebComponent} from "../Et2Widget/Et2Widget";
import {egwIsMobile} from "../../egw_action/egw_action_common";
import {Et2Switch} from "../Et2Switch/Et2Switch";
import {Et2SwitchIcon} from "../Et2Switch/Et2SwitchIcon";
import {Et2ButtonToggle} from "../Et2Button/Et2ButtonToggle";
import {SlButtonGroup} from "@shoelace-style/shoelace";
import {HasSlotController} from "../Et2Widget/slot";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {Et2Button} from "../Et2Button/Et2Button";
import {Et2Box} from "../Layout/Et2Box/Et2Box";

/**
 * Toolbar shows inputs in a horizontal line.  Inputs that do not fit are hidden in a dropdown.
 *
 * @slot - Toolbar contents
 * @slot list - Toolbar contents that start hidden in the dropdown
 *
 * @event et2-resize - Emitted when the toolbar re-lays out
 */
@customElement("et2-toolbar")
export class Et2Toolbar extends Et2InputWidget(Et2Box)
{
	static get styles()
	{
		return [
			shoelace,
			super.styles,
			styles
		];
	}

	/** Actions with children should be shown as dropdown (true) or flat list (false) */
	@property({type: Boolean})
	groupChildren = false;

	/**
	 * Define a custom preference id for saving the toolbar preferences.
	 *
	 * This is useful when you have the same toolbar and you use it in a pop up but also in a tab, which have different dom ids. When not set it defaults to the dom id of the toolbar.
	 *
	 * @type {string}
	 */
	@property()
	preferenceId = "";

	/**
	 * 	Define a custom preference application for saving the toolbar preferences.
	 *
	 * 	This is useful when you have the same toolbar and you use it in multiple places, which have different application names.
	 * 	When not set it defaults to the result of this.egw().app_name();
	 *
	 * @type {string}
	 */
	@property()
	preferenceApp = "";

	/* User is admin */
	@state() _isAdmin = false;
	/* Toolbar contents overflow available space */
	@state() _isOverflowed = false;

	// Allows us to check to see if label or help-text is set.  Overriden to check additional "list" slot.
	protected readonly hasSlotController = new HasSlotController(this, 'list', 'help-text', 'label');

	// Allows us to make changes when the toolbar is bigger or smaller
	protected readonly resizeObserver = new ResizeObserver(this.handleResize);

	/**
	 * Indicates which actions go where
	 *
	 * - All actions should be stored in preference
	 * - Actions inside menu set as true
	 * - Actions outside menu set as false
	 * - Actions not set need to be added
	 */
	private _preference : { [id : string] : boolean } = {};
	/* Hold on to actions, as we don't use action system but just use them to create inputs */
	private _actions = {};
	/* Actions have been parsed into inputs */
	private _actionsParsed = false;
	/* Use a timeout to avoid lots of work when user resizes, just wait until they stop */
	private _layoutTimeout : number;
	private static LAYOUT_TIMEOUT = 100;

	constructor()
	{
		super();

		this.handleClick = this.handleClick.bind(this);
		this.handleSettingsClose = this.handleSettingsClose.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._isAdmin = typeof (this.egw() && this.egw().user && this.egw()?.user("apps")?.admin) != "undefined" || false;

		this.resizeObserver.observe(this);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.resizeObserver.disconnect();
	}

	willUpdate(changedProperties : PropertyValueMap<any>)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("preferenceId") || changedProperties.has("preferenceApp") || changedProperties.has("id"))
		{
			this.preferenceId = this.preferenceId || this.dom_id;
			this.preferenceApp = this.preferenceApp || this.egw()?.app_name() || "";
			this._preference = this.egw()?.preference(this.preferenceId, this.preferenceApp) || {};
		}
		if(!this._actionsParsed)
		{
			this._parseActions(this.actions);
		}
	}

	firstUpdated(changedProperties : PropertyValueMap<any>)
	{
		this._organiseChildren();
	}

	_createNamespace() : boolean
	{
		return true;
	}

	/**
	 * Overridden from parent because toolbar can turn actions into buttons
	 *
	 * @param {EgwAction[] | {[p : string] : object}} actions
	 */
	@property({type: Object})
	set actions(actions : EgwAction[] | { [id : string] : object })
	{
		this._initActions(actions);
		this._actionsParsed = false;
		this.requestUpdate();
	}

	get actions()
	{
		return this._actionManager?.children || {};
	}

	_link_actions() {}

	/**
	 * Parse a list of actions and create matching inputs into the toolbar
	 *
	 * @param actions
	 * @protected
	 */
	protected _parseActions(actions : EgwAction[] | { [id : string] : object })
	{
		// Clean up anything from actions that's there already - do not remove everything
		this.querySelectorAll(":scope > [data-action-id], :scope > [data-group]").forEach(n => n.remove());

		let last_group_id;
		let last_group;
		let domChildCount = this.children.length;
		let shownActionCount = domChildCount + Object.values(this._preference).filter(p => !p).length;

		// Set order on any existing children
		Array.from(this.querySelectorAll("*:not([data-order]):not([data-action-id])"))
			.forEach((c, index) => c.dataset.order = index);

		// Set groups on real children
		Array.from(this.querySelectorAll(":scope > sl-button-group:not([data-group]), :scope > et2-box:not([data-group]), :scope > et2-hbox:not([data-group])"))
			.forEach((c, index) =>
			{
				c.dataset.group = c.label ?? index + shownActionCount;
				c.querySelectorAll(":scope > *").forEach(child => child.dataset.groupId = c.dataset.group);
			})

		for(let name in actions)
		{
			let action = actions[name];
			if(typeof action == 'string')
			{
				action = {id: name, caption: action};
			}
			if(!action.id)
			{
				action.id = name;
			}

			// Make sure there's something to display
			if(!action.caption && !action.icon && !action.iconUrl)
			{
				continue;
			}

			// Add group
			if(action.group && last_group_id != action.group)
			{
				last_group = this.querySelector(`[data-group='${action.group}']`);
				if(!last_group)
				{
					last_group = document.createElement("sl-button-group");
					last_group.dataset.group = action.group;
					last_group.dataset.order = domChildCount + action.group;
					this.append(last_group);
				}

				last_group_id = action.group;
			}
			else if(!action.group)
			{
				last_group = this;
			}
			if(action.id && typeof this._preference[action.id] == "undefined")
			{
				this._preference[action.id] = false;
			}
			this._addAction(action, last_group);
		}

		// Set the flag to avoid duplicates
		this._actionsParsed = true;
	}

	/**
	 * Take a single action and turn it into an input, placing it inside parent
	 * Handles actions with children
	 *
	 * @param {EgwAction} action
	 * @param parent
	 * @protected
	 */
	protected _addAction(action : EgwAction, parent)
	{
		if(Array.isArray(action.children) && action.children.length > 0)
		{
			let children = {};
			let add_children = (root, children) =>
			{
				for(let id in root.children)
				{
					let info = {
						id: root.children[id].id ?? id,
						value: root.children[id].id ?? id,
						label: root.children[id].caption
					};
					let childaction = {};
					if(root.children[id].iconUrl)
					{
						info['icon'] = root.children[id].iconUrl;
					}
					if(root.children[id].children)
					{
						add_children(root.children[id], info);
					}
					children[id] = info;

					if(!this.groupChildren)
					{
						childaction = root.children[id];
						if(typeof this._preference[childaction['id']] === 'undefined')
						{
							this._setPrefered(childaction.id, !childaction.toolbarDefault ?? true);
						}

						if(typeof root.children[id].group !== 'undefined' &&
							typeof root.group !== 'undefined')
						{
							childaction['group'] = root.group;
						}
						this._makeInput(childaction, this);
					}
				}
			};
			add_children(action, children);
			if(!this.groupChildren && children)
			{
				return;
			}

			let dropdown = <Et2DropdownButton><unknown>loadWebComponent("et2-dropdown-button", {
				id: action.id,
				label: action.caption,
				//class: this.preference[action.id] ? `et2_toolbar-dropdown et2_toolbar_draggable${this.id} et2_toolbar-dropdown-menulist` : `et2_toolbar-dropdown et2_toolbar_draggable${this.id}`,
				onchange: function(ev)
				{
					let action = dropdown.closest("et2-toolbar")._actionManager.getActionById(dropdown.value);
					dropdown.label = action.caption;
					if(action)
					{
						dropdown.closest("et2-toolbar").handleAction(ev, action);
					}
				}.bind(action),
				image: action.iconUrl || ''
			}, this);

			dropdown.select_options = Object.values(children);

			//Set default selected action
			if(typeof action.children != 'undefined')
			{
				for(let child in action.children)
				{
					if(action.children[child].default)
					{
						dropdown.label = action.children[child].caption;
					}
				}
			}

			dropdown.onclick = function(selected, dropdown)
			{
				let action = dropdown.closest("et2-toolbar")._actionManager.getActionById(this.getValue());
				if(action)
				{
					this.value = action.id;
					action.execute([]);
				}
			}.bind(dropdown);
			parent.append(dropdown);
			dropdown.slot = this._preference[action.id] ? "list" : "";
		}
		else
		{
			this._makeInput(action, parent);
		}
	}

	/**
	 * Make an input based on the given action, adds it to parent element
	 *
	 * Handles just actions, manages common setup
	 *
	 * @param {Object} action action object with attributes icon, caption, ...
	 */
	protected _makeInput(action : EgwAction, parent : HTMLElement)
	{
		const isCheckbox = action && action.checkbox;
		const isToggleSwitch = action.data?.toggle_on || action.data?.toggle_off || action.data?.onIcon || action.data?.offIcon
			|| isCheckbox && action.data.icon;

		let widget = null;
		const attrs = {
			id: action.id,
			label: action.caption,
			readonly: action.readonly || this.readonly,
		};
		if((action.hint || action.caption) && !egwIsMobile())
		{
			attrs.statustext = action.hint || action.caption;
		}
		attrs.slot = this._preference[action.id] ? "list" : "";

		if(isToggleSwitch)
		{
			widget = this._makeSwitch(action, attrs);
		}
		else if(isCheckbox)
		{
			widget = this._makeToggle(action, attrs);
		}
		else
		{
			widget = this._makeButton(action, attrs);
		}

		if(action.caption)
		{
			widget.classList.add('toolbar--hasCaption');
		}

		widget.dataset.actionId = action.id;
		const index = Object.keys(this._preference).indexOf(action.id);
		widget.dataset.order = index >= 0 ? index : parent.childNodes.length;

		if(parent.hasAttribute("data-group") || action.group)
		{
			widget.dataset.groupId = parent.dataset.group || action.group;
		}

		parent.append(widget);
	}

	private _makeButton(action : EgwAction, attrs : { [id : string] : string })
	{
		const component = "et2-button";
		Object.assign(attrs, {
			noSubmit: true
		});
		if(typeof action.data.icon !== "undefined" || typeof action.iconUrl !== "undefined")
		{
			attrs.image = action.data.icon || action.iconUrl;
		}
		if(!attrs.image)
		{
			attrs.class = "toolbar--needsCaption";
		}
		if(egwIsMobile())
		{
			attrs.name = '';
		}
		return <Et2Switch>loadWebComponent(component, attrs, this);
	}

	private _makeSwitch(action : EgwAction, attrs : { [id : string] : string }) : Et2Switch | Et2SwitchIcon
	{
		let component = "et2-switch";
		Object.assign(attrs, {
			value: action.checked ?? false
		});
		if(typeof action.data.toggle_on !== "undefined")
		{
			attrs.toggleOn = action.data.toggle_on;
		}
		if(typeof action.data.toggle_off !== "undefined")
		{
			attrs.toggleOff = action.data.toggle_off;
		}
		if(action.data.onIcon || action.data.offIcon)
		{
			component = "et2-switch-icon";
			if(action.data.onIcon)
			{
				attrs["onIcon"] = action.data.onIcon;
			}
			if(action.data.offIcon)
			{
				attrs["offIcon"] = action.data.offIcon;
			}
		}
		else if(action.iconUrl || action.data.icon)
		{
			component = "et2-button-toggle";
			attrs['icon'] = action.data.icon ?? action.iconUrl;
			attrs["exportparts"] = "form-control-label control";
		}
		return <Et2Switch>loadWebComponent(component, attrs, this);
	}

	private _makeToggle(action, attrs : { [id : string] : string }) : Et2ButtonToggle
	{
		Object.assign(attrs, {
			image: action.data.icon || action.iconUrl || ''
		});
		return <Et2ButtonToggle>loadWebComponent("et2-button-toggle", attrs, this);
	}

	/**
	 * Makes sure preference is valid and contains the child / action
	 *
	 * @param {string} childId
	 */
	private _setPrefered(childId : string, state : boolean)
	{
		this._preference[childId] = state;
		this.egw().set_preference(this.preferenceApp, this.preferenceId, this._preference);
	}

	/**
	 * Adjust the location of child inputs without destroying / re-creating them
	 *
	 * @protected
	 */
	protected _organiseChildren()
	{
		this._isOverflowed = false;
		let elements = Array.from(this.querySelectorAll(':scope > *'));

		// Reset slot so it can participate in width calculations
		elements.forEach((el : HTMLElement) =>
		{
			if(el instanceof SlButtonGroup || el instanceof Et2Box)
			{
				el.childNodes.forEach((c : HTMLElement) =>
				{
					if(!this._preference[c.id])
					{
						c.slot = "";
						this._placeInputInGroup(c);
					}
				});
			}
			else if(!this._preference[el.id])
			{
				el.slot = "";
				this._placeInputInGroup(el);
			}
		});

		elements = Array.from(this.querySelectorAll(':scope > *'));
		elements.sort((a : HTMLElement, b : HTMLElement) => parseInt(b.dataset.order) - parseInt(a.dataset.order));
		elements.forEach((el : HTMLElement) =>
		{
			if(typeof el.dataset.group !== "undefined")
			{
				Array.from(el.childNodes).reverse().forEach((c : HTMLElement) => this._organiseChild(c));
			}
			else
			{
				this._organiseChild(el);
			}
		});

		// Move any inputs that should be in the list
		Array.from(this.querySelectorAll(":scope > * > [slot='list']"))
			.forEach(el => this.append(el));

		// Set order directly since etemplate2.css doesn't like attr()
		Array.from(this.querySelectorAll("[data-order]"))
			.forEach((el : HTMLElement) => el.style.order = el.dataset.order);

		// Do not trigger refresh to avoid looping
		this.shadowRoot.querySelector(".toolbar").classList.toggle("toolbar--overflowed", this._isOverflowed);
	}

	/**
	 * Slot a child according to preference and available space
	 *
	 * @param {HTMLElement} child
	 * @protected
	 */
	protected _organiseChild(child : HTMLElement)
	{
		if(!this.shadowRoot.querySelector(".toolbar-buttons"))
		{
			// Not ready yet
			return;
		}
		const isOverflowed = (child.offsetWidth + child.offsetLeft) > (<HTMLElement>this.shadowRoot.querySelector(".toolbar-buttons")).offsetWidth;
		if(isOverflowed || this._preference[child.id])
		{
			this._isOverflowed = this._isOverflowed || isOverflowed;
			child.slot = "list";
		}
		else if(!this._preference[child.id])
		{
			child.slot = "";
		}
		// Check if input needs to go in a group (moving the other way is done in _organiseChildren()
		this._placeInputInGroup(child);
	}

	/**
	 * Put button in its button group, if needed
	 *
	 * @param {HTMLElement} child
	 * @private
	 */
	private _placeInputInGroup(child : HTMLElement)
	{
		let groupId = child.dataset?.groupId;
		if(groupId && child.slot == "" && this.querySelector(`sl-button-group[data-group="${groupId}"]`))
		{
			this.querySelector(`sl-button-group[data-group="${groupId}"]`).append(child);
		}
	}

	/**
	 * Handle clicks on child widgets - call action when appropriate
	 *
	 * @param {Event} e
	 */
	handleClick(e : Event)
	{
		// If the element has an action, execute it
		if(e.target instanceof Et2Button && e.target?.dataset?.actionId && !e.defaultPrevented)
		{
			// Please stop, action system has it
			e.stopPropagation();

			const action = this._actionManager.getActionById(e.target.dataset.actionId);

			// Pass it to the action system
			return this.handleAction(e, action);
		}
		// Otherwise, it's just a normal component
	}

	/**
	 * Handle changes on child widgets - call action when appropriate
	 *
	 * @param {Event} e
	 */
	handleChange(e : Event)
	{
		// If the element has an action, execute it
		if(e.target?.dataset?.actionId && !e.defaultPrevented)
		{
			e.stopPropagation();
			// Pass it to the action system
			return this.handleAction(e, this._actionManager.getActionById(e.target.dataset.actionId));
		}
	}

	handleResize(entries : ResizeObserverEntry[], observer)
	{
		const toolbar = entries[0]?.target;
		if(!toolbar._actionsParsed)
		{
			return;
		}

		// Toolbar changed size, re-organise children
		// but wait a bit until things stop
		if(toolbar._layoutTimeout)
		{
			window.clearTimeout(toolbar._layoutTimeout);
		}
		toolbar._layoutTimeout = window.setTimeout(() =>
		{
			toolbar._organiseChildren();
			toolbar.requestUpdate();
			toolbar.dispatchEvent(new Event("et2-resize", {bubbles: true, composed: true}));
		}, Et2Toolbar.LAYOUT_TIMEOUT);
	}

	handleSettingsClick(e : MouseEvent)
	{
		e.stopImmediatePropagation();
		// Show settings / preferences dialog
		this.settingsDialog();

		// Close the list
		this.shadowRoot.querySelector('sl-dropdown')?.hide();
	}

	/**
	 * Update preference, reset if requested
	 * @param e
	 */
	handleSettingsClose(button_id, value, event)
	{
		if(button_id !== Et2Dialog.OK_BUTTON)
		{
			return;
		}

		if(value.reset || value.default)
		{
			// Admin change preferences for all
			this.egw().json('EGroupware\\Api\\Etemplate\\Widget\\Toolbar::ajax_setAdminSettings',
				[value, this.preferenceId, this.preferenceApp], (_result) =>
				{
					this.egw().message(_result);
				}).sendRequest(true);
		}
		this.settingsOptions().forEach(option =>
		{
			this._setPrefered(option.value, !value.actions.includes(option.value));
		});
		this._organiseChildren();
	}

	handleAction(event, action : EgwAction)
	{
		if(action.checkbox)
		{
			action.set_checked(this.getWidgetById(action.id).value);
		}
		this.value = {action: action.id};
		if(!action.data)
		{
			action.data = {};
		}
		action.data.event = event;
		action.execute([]);
	}

	protected settingsOptions()
	{
		const options = [];
		this.querySelectorAll("[id]").forEach((child : HTMLElement) =>
		{
			const option : SelectOption = {
				value: child.id,
				label: child.id
			};
			// @ts-ignore
			option.label = child.label ?? child.emptyLabel;
			// @ts-ignore
			option.icon = child.icon ?? child.image ?? child.onIcon;
			if(!option.icon && this._actionManager)
			{
				// Try harder for icon, check original action
				const action = this._actionManager.getActionById(option.value);
				option.icon = action?.data?.icon ?? action?.iconUrl

			}
			if(option.label)
			{
				options.push(option);
			}
		})
		return options;
	}

	protected settingsDialog()
	{
		const value = Object.keys(this._preference)
			.filter(key => !this._preference[key]);
		const dialog = loadWebComponent("et2-dialog", {
			title: this.egw().lang("toolbar settings"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			style: "--width: 40em",
			template: "api.toolbarAdminSettings",
			value: {
				content: {
					actions: value,
					isAdmin: this._isAdmin
				},
				sel_options: {
					actions: this.settingsOptions()
				}
			},
			callback: this.handleSettingsClose
		}, this);
		document.body.append(dialog);
	}

	protected overflowTemplate()
	{
		const hasListContent = this.hasSlotController.test("list");

		return !(this._isOverflowed || hasListContent || this._isAdmin) ? nothing : html`
            <sl-dropdown placement="bottom-end">
                <et2-button-icon slot="trigger"
                                 class="toolbar-list-trigger"
                                 image="three-dots-vertical" noSubmit="true"
                                 label="${this.egw().lang("More...")}"
                ></et2-button-icon>
                <sl-menu class="toolbar-list">
                    <slot name="list"></slot>
                    <sl-divider data-order="99"></sl-divider>
                    <et2-button class="toolbar-admin-button"
                                image="gear" data-order="99" noSubmit
                                label="${this.egw().lang("settings")}"
                                @click=${this.handleSettingsClick}
                    ></et2-button>
                </sl-menu>
            </sl-dropdown>
		`;
	}

	render()
	{
		const classes = {
			toolbar: true,
			'toolbar--disabled': this.disabled,
			'toolbar--readonly': this.readonly,
			'toolbar--overflowed': this._isOverflowed
		};
		return html`
            <div
                    part="base"
                    class=${classMap(classes)}
                    @click=${this.handleClick}
                    @change=${this.handleChange}
            >
                <div part="buttons" class="toolbar-buttons">
                    <slot></slot>
                </div>
                ${this.overflowTemplate()}
            </div>
		`;
	}
}