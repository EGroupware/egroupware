/**
 * EGroupware eTemplate2 - Dropdown Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {SlButtonGroup, SlDropdown} from "@shoelace-style/shoelace";
import {css, html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import shoelace from "../Styles/shoelace";
import {property} from "lit/decorators/property.js";

/**
 * A split button - a button with a dropdown list
 *
 * There are several parts to the button UI:
 * - Container: This is what is percieved as the dropdown button, the whole package together
 *   - Button: The part on the left that can be clicked
 *   - Arrow: The button to display the choices
 *   - Menu: The list of choices
 *
 * Menu options are passed via the select_options.  They are normally the same
 * as for a select box, but the title can also be full HTML if needed.
 *
 * @slot prefix - Content placed before the main button's label
 * @slot suffix - Content placed after the main button's label
 */
export class Et2DropdownButton extends Et2WidgetWithSelectMixin(LitElement)
{

	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
                :host {
                    /* Avoid unwanted style overlap from button */
                    border: none;
                    background-color: none;
                }

                :host, sl-menu {
                    /**
					Adapt shoelace color variables to what we want
					Maybe some logical variables from etemplate2.css here?
					*/
                    --sl-color-primary-50: var(--sl-input-background-color-hover);
                    --sl-color-primary-100: var(--gray-10);
                    --sl-color-primary-300: var(--sl-input-border-color-hover, var(--input-border-color));
                    --sl-color-primary-400: var(--input-border-color);
                    --sl-color-primary-600: var(--primary-background-color);
                    --sl-color-primary-700: var(--sl-input-color-hover);
                }

                :host(:active), :host([active]) {
                    background-color: initial;
                }

                sl-button-group {
                    display: initial;
                }

                #main {
                    flex: 1 1 auto;
                }

                et2-image {
                    width: 1em;
                }

                ::slotted(et2-image[slot="prefix"]), ::slotted(et2-image[slot="suffix"]) {
                    width: 20px;
                    height: 20px;
                    display: flex;
                    font-size: 20px !important;
                }

                sl-menu-item::part(label) {
                    color: var(--item-color, inherit);
                }

                /* Leave the label in the accessibility tree, but hide it visually */

                :host([iconOnly]) {
	                #main::part(base){
		                padding-inline: var(--sl-spacing-small);
	                }
                    #main::part(label) {

                        position: absolute;
                        left: -999px;
                    }
                }
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties
		};
	}

	@property()
	placement:string = "bottom-end";

	/**
	 * Name of a preference to read/store the default selected option in.
	 *
	 * When set, the option matching the stored preference value
	 * (falling back to whichever option is marked `default: true`)
	 * is preselected on load, and every user selection is written back to the same preference
	 * - so the main/left part of the button becomes a quick-action for "whatever was last picked",
	 * instead of doing nothing until the user has opened the menu at least once.
	 */
	@property()
	defaultPreference : string = "";

	/**
	 * Show only the selected option's icon on the main button, not its text label
	 */
	@property({type: Boolean, reflect: true})
	iconOnly : boolean = false;

	// Make sure imports stay
	private _group : SlButtonGroup;
	private _dropdow : SlDropdown;

	constructor()
	{
		super();

		// Bind handlers - parent already got click
		this._handleSelect = this._handleSelect.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Rebind click to just the main button, not the whole thing
		this.removeEventListener("click", this._handleClick);

		// Et2Toolbar assigns select_options before appending us to the DOM,
		// so the willUpdate() driven by that assignment can run before egw() is reliably resolvable
		// - resolve again once connected, as a safety net. _resolveDefault() is idempotent (guarded by _value).
		this._resolveDefault();
	}

	willUpdate(changedProperties : PropertyValues<this>)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("select_options") || changedProperties.has("defaultPreference"))
		{
			this._resolveDefault();
		}
	}

	/**
	 * Resolve the default selected option from defaultPreference: a stored preference value
	 * first, falling back to whichever option is marked `default: true`.
	 *
	 * No-op unless defaultPreference is set, options are loaded, and nothing has been
	 * selected yet this session (avoids clobbering an active user selection).
	 */
	protected _resolveDefault()
	{
		if(!this.defaultPreference || !this.select_options?.length || this._value || !this.egw())
		{
			return;
		}

		const stored = this.egw().preference(this.defaultPreference, this.egw().getAppName());
		let option = stored ? this.select_options.find((o : SelectOption) => o.value === stored) : undefined;
		if(!option)
		{
			option = this.select_options.find((o : SelectOption) => o.default);
		}
		if(option)
		{
			this._value = option.value;
			this.label = this.noLang ? option.label : this.egw().lang(option.label);
		}
	}

	protected _renderOptions()
	{
		// We have our own render, so we can handle it internally
	}

	protected get _selectedOption() : SelectOption
	{
		return (this.select_options || []).find((o : SelectOption) => o.value === this._value);
	}

	render() : TemplateResult
	{
		if(this.readonly)
		{
			return html``;
		}
		const selected = this.iconOnly ? this._selectedOption : undefined;
		const icon = selected?.icon;
		return html`
            <sl-button-group>
                <sl-button size="${egwIsMobile() ? "large" : "medium"}" id="main" part="main" exportparts="base"
                           ?disabled=${this.disabled}
                           @click=${this._handleClick}
                >
                    ${this.iconOnly && icon ? html`
                        <et2-image slot="prefix" src=${icon}
                                   style=${selected?.iconColor ? `color: ${selected.iconColor}` : nothing}></et2-image>` : nothing}
                    <slot name="prefix" slot="prefix"></slot>
                    ${this.label}
                    <slot name="suffix" slot="suffix"></slot>
                </sl-button>
                <sl-dropdown placement=${this.placement} hoist part="dropdown">
                    <slot name="trigger" slot="trigger">
                        <sl-button exportparts="base, base:trigger__base" part="trigger" size="${egwIsMobile() ? "large" : "medium"}"
                                   slot="trigger" caret
                               ?disabled=${this.disabled}></sl-button>
                    </slot>
                    <sl-menu @sl-select=${this._handleSelect} part="menu">
                        ${(this.select_options || []).map((option : SelectOption) => this._optionTemplate(option))}
                        <slot></slot>
                    </sl-menu>
                </sl-dropdown>
            </sl-button-group>
		`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" src=${option.icon} icon
                       style=${option.iconColor ? `color: ${option.iconColor}` : nothing}></et2-image>` : '';

		return html`
            <sl-menu-item
                    value="${option.value}"
                    type="${option.checkbox ? "checkbox" : "normal"}"
                    ?checked=${option.checked}
					title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
					style="${option.color ? `--item-color: ${option.color}` : ''}"
            >
                ${icon}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-menu-item>`;
	}

	protected _handleSelect(ev)
	{
		this._value = ev.detail.item.value;

		if(this.defaultPreference)
		{
			this.egw().set_preference(this.egw().getAppName(), this.defaultPreference, this._value);
		}

		// Trigger a change event
		this.dispatchEvent(new Event("change"));

		// Let it bubble, if anyone else is interested
	}

	get value() : string
	{
		return this._value;
	}

	set value(new_value)
	{
		let oldValue = this.value;
		this._value = new_value;
		this.requestUpdate("value", oldValue);
	}

	get _optionTargetNode() : HTMLElement
	{
		return this.shadowRoot.querySelector("sl-menu");
	}

	get buttonNode()
	{
		return this.shadowRoot.querySelector("#main");
	}

	get triggerButtonNode()
	{
		return this.shadowRoot.querySelector("[slot='trigger']");
	}

	get dropdownNode()
	{
		return this.shadowRoot.querySelector("sl-dropdown");
	}

	blur()
	{
		this.shadowRoot.querySelector("sl-button-group")?.dispatchEvent(new Event('blur'));
	}

	focus()
	{
		this.shadowRoot.querySelector("sl-button-group")?.dispatchEvent(new Event('focus'));
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Button is a LitElement
customElements.define("et2-dropdown-button", Et2DropdownButton);