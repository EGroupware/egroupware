import {css, html, LitElement, nothing} from "lit";
import {SlMenu, SlMenuItem} from "@shoelace-style/shoelace";
import {egwMenuItem} from "./egw_menu";
import {customElement} from "lit/decorators/custom-element.js";
import {repeat} from "lit/directives/repeat.js";
import {classMap} from "lit/directives/class-map.js";

@customElement("egw-menu-shoelace")
export class EgwMenuShoelace extends LitElement
{
	static get styles()
	{
		return [
			css`
				:host {
					display: block;

					/* Fit in popup, scroll if not enough height */
					max-height: var(--auto-size-available-height, auto);
					overflow-y: auto;
				}

				.default-item::part(label) {
					font-weight: var(--sl-font-weight-bold, bold);
				}

				et2-image {
					width: 1.5em;
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
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
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

	public showAt(_x, _y, _onHide)
	{
		this.removeCallback = _onHide;
		if(this.popup == null)
		{
			this.popup = document.createElement("sl-popup");
			this.popup.placement = "bottom";
			this.popup.autoSize = "vertical";
			this.popup.flip = true;
			this.popup.shift = true;
			this.popup.classList.add("egw_menu")
			document.body.append(this.popup);
			this.popup.append(this);
		}
		this.popup.anchor = {
			getBoundingClientRect()
			{
				return {
					x: _x,
					y: _y,
					width: 0,
					height: 0,
					top: _y,
					left: _x,
					right: _x,
					bottom: _y
				}
			}
		};
		this.popup.active = true;
		Promise.all([this.updateComplete, this.popup.updateComplete]).then(() =>
		{
			// Causes scroll issues if we don't position
			this.popup.popup.style = "top: 0px";
			(<SlMenuItem>this.menu.querySelector('sl-menu-item')).focus();
		});
	}

	public hide()
	{
		this.popup.active = false;
	}

	handleSelect(event)
	{
		if(!this.popup)
		{
			return;
		}

		if(event.detail.item.value)
		{
			const item = <egwMenuItem>event.detail.item.value;
			if(item.checkbox || typeof item.checked !== "undefined")
			{
				item.checked = event.detail.item.checked;
				return;
			}
			if(typeof item.onClick == "function")
			{
				this.hide();
				item.onClick.call(event.detail.item, item, event);
			}
		}
	}

	private itemTemplate(item : egwMenuItem)
	{
		if(item.caption == "-")
		{
			return html`
                <sl-divider></sl-divider>`;
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
            >
                ${item.iconUrl ? html`
                    <et2-image slot="prefix" src="${item.iconUrl}"></et2-image>` : nothing}
                ${item.caption}
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