import {css, html, LitElement, nothing, PropertyValues} from "lit";
import {SlIcon, SlMenu, SlMenuItem} from "@shoelace-style/shoelace";
import {egwMenuItem} from "./egw_menu";
import {customElement} from "lit/decorators/custom-element.js";
import {repeat} from "lit/directives/repeat.js";
import {classMap} from "lit/directives/class-map.js";
import {state} from "lit/decorators/state.js";

@customElement("egw-menu-shoelace")
export class EgwMenuShoelace extends LitElement
{
	static get styles()
	{
		return [
			css`
				:host {
					display: block;
				}

				.default-item::part(label) {
					font-weight: var(--sl-font-weight-bold, bold);
				}

				.custom-color::part(label) {
					--color: var(--color);
				}

				sl-menu-item::part(base) {
					height: 1.7em;
					line-height: var(--sl-line-height-dense);
					align-items: center;
					padding: 0;
				}

				sl-menu-item::part(prefix) {
					min-width: var(--sl-spacing-2x-large);
				}

				/* Customise checkbox menuitem */

				sl-menu-item[type="checkbox"]::part(checked-icon) {
					visibility: hidden;
				}

				sl-menu-item[type="checkbox"]:not([checked])::part(checked-icon) {
					color: var(--sl-color-neutral-300);
				}

				et2-image {
					line-height: normal;
					width: 1.3em;
				}
			`
		]
	}

	private structure = [];
	private popup = null;
	private removeCallback = null;

	private get menu() : SlMenu { return this.shadowRoot?.querySelector("sl-menu");}

	constructor(_structure : egwMenuItem[])
	{
		super();
		this.structure = _structure;

		this.handleDocumentClick = this.handleDocumentClick.bind(this);
		this.handleKeypress = this.handleKeypress.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		document.addEventListener("click", this.handleDocumentClick);
		document.addEventListener("keydown", this.handleKeypress);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		document.removeEventListener("click", this.handleDocumentClick);
		document.removeEventListener("keydown", this.handleKeypress);
		if(this.popup)
		{
			this.popup.remove();
			this.popup = null;
		}
		if(this.removeCallback)
		{
			this.removeCallback.call();
		}
	}

	protected updated(_changedProperties : PropertyValues)
	{
		super.updated(_changedProperties);

		// Checkbox indicators
		this.shadowRoot.querySelectorAll("sl-menu-item[type=checkbox]").forEach(async(item : SlMenuItem) =>
		{
			await item.updateComplete;
			const icon : SlIcon = item.shadowRoot.querySelector("[part=\"checked-icon\"] sl-icon");
			if(!icon)
			{
				return;
			}
			icon.name = item.checked ? "check-square" : "square";
			icon.library = "default";
		})
	}

	public showAt(_x, _y, _onHide)
	{
		this.removeCallback = _onHide;
		if(this.popup == null)
		{
			this.popup = Object.assign(document.createElement("sl-popup"), {
				placement: "right-start",
				autoSize: "vertical",
				flip: true,
				shift: true
			});
			this.popup.append(this);
			this.popup.classList.add("egw_menu");
		}
		let menu = this;
		this.popup.anchor = {
			getBoundingClientRect()
			{
				return {
					x: _x,
					y: _y,
					width: 0,	// placement="right-start" only works well with 0, not menu.clientWidth,
					height: menu.clientHeight,
					top: _y,
					left: _x,
					right: _x,
					bottom: _y
				}
			}
		};
		this.popup.active = true;
		document.body.append(this.popup);
		Promise.all([this.updateComplete, this.popup.updateComplete]).then(() =>
		{
			// Causes scroll issues if we don't position
			this.popup.popup.style = "top: 0px";
			(<SlMenuItem>this.menu.querySelector('sl-menu-item')).focus();
		});
	}

	public hide()
	{
		if(this.popup)
		{
			this.popup.active = false;
		}

		// egw_menu always creates a new menu
		this.remove();
	}

	handleSelect(event)
	{
		// If not open, skip
		if(!this.popup)
		{
			return;
		}

		if(event.detail.item.value)
		{
			const item = <egwMenuItem>event.detail.item.value;
			if(item.checkbox)
			{
				// Update our internal data
				item.data.checked = item.checked = event.detail.item.checked;

				// Update image of a checkbox item to be toggle on or off
				// this happens by requesting an update because item.checked has changed
				this.requestUpdate("structure")
				return;
			}
			if(typeof item.onClick == "function")
			{
				this.hide();
				item.onClick.call(event.detail.item, item, event);
			}
		}
	}

	handleCheckboxClick(event)
	{
		const check = event.target.closest("sl-menu-item");
		if(!check || check.parentElement == this)
		{
			return;
		}

		// Make sure sub-menu does not close
		event.stopPropagation();

		// Normal select event
		check.checked = !check.checked;
		check.dispatchEvent(new CustomEvent("sl-select", {
			bubbles: true,
			cancelable: false,
			composed: true,
			detail: {item: check}
		}));
	}

	handleDocumentClick(event)
	{
		if(!event.composedPath().includes(this))
		{
			this.hide();
		}
	}

	handleKeypress(event : KeyboardEvent)
	{
		if(event.key == "Escape")
		{
			event.preventDefault();
			event.stopPropagation();
			this.hide();
		}
	}

	private itemTemplate(item : egwMenuItem)
	{
		if(item.caption == "-")
		{
			return html`
                <sl-divider></sl-divider>`;
		}

		//if we have a checkbox, change the icon to be a toggle slider. Either on or off
		if (item.checkbox)
		{
			item.iconUrl = item.checked ? "toggle-on" : "toggle-off";
		}

		return html`
            <sl-menu-item
                    class=${classMap({
                        "default-item": item.default
                    })}
                    id=${item.id}
                    type="${item.checkbox ? "checkbox" : "normal"}"
                    ?checked=${item.checkbox && item.checked}
                    ?disabled=${!item.enabled}
                    .value=${item}
                    @click=${item.checkbox ? this.handleCheckboxClick : nothing}
            >
                ${item.iconUrl ? html`
                    <et2-image slot="prefix" src="${item.iconUrl}"></et2-image>` : nothing}
				<span style="color: ${item.color || nothing}">${item.caption}</span>
                ${item.shortcutCaption ? html`<span slot="suffix"
                                                    class="keyboard_shortcut">
					${item.shortcutCaption}
				</span>` : nothing}
                ${item.children.length == 0 ? nothing : html`
                    <sl-menu slot="submenu">
                        ${repeat(item.children, i => this.itemTemplate(i))}
                    </sl-menu>
                `}
            </sl-menu-item>
		`;
	}


	render()
	{
		return html`
            <sl-menu
                    @sl-select=${this.handleSelect}
            >
                ${repeat(this.structure, i => this.itemTemplate(i))}
            </sl-menu>`;
	}
}
