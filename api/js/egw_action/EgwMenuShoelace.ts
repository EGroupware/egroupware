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
				}

				.default-item::part(label) {
					font-weight: var(--sl-font-weight-bold, bold);
				}

				sl-menu-item::part(base) {
					height: 1.6em;
					line-height: var(--sl-line-height-dense);
					align-items: center;
					padding: 0;
				}

				sl-menu-item::part(prefix) {
					min-width: var(--sl-spacing-2x-large);
				}

				et2-image {
					line-height: normal;
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

	public showAt(_x, _y, _onHide)
	{
		this.removeCallback = _onHide;
		if(this.popup == null)
		{
			this.popup = Object.assign(document.createElement("sl-popup"), {
				placement: "top",
				autoSize: "vertical",
				flip: true,
				flipFallbackPlacements: "right bottom",
				flipFallbackStrategy: "initial",
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
					width: menu.clientWidth,
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
		if(!this.popup)
		{
			return;
		}

		if(event.detail.item.value)
		{
			const item = <egwMenuItem>event.detail.item.value;
			if(item.checkbox)
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