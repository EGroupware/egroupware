import {css, html, LitElement, nothing} from "lit";
import {SlDivider, SlMenu, SlMenuItem} from "@shoelace-style/shoelace";
import {egwMenuItem} from "./egw_menu";
import {customElement} from "lit/decorators/custom-element.js";
import {ref} from "lit/directives/ref.js";
import {repeat} from "lit/directives/repeat.js";
import {classMap} from "lit/directives/class-map.js";
import bootstrapIcons from "../etemplate/Styles/bootstrap-icons";
import {until} from "lit/directives/until.js";

@customElement("egw-menu-shoelace")
export class EgwMenuShoelace extends LitElement
{
	static get styles()
	{
		return [
			bootstrapIcons,
			css`
				:host {
					display: block;
				}

				.default-item::part(label) {
					font-weight: var(--sl-font-weight-bold, bold);
				}

				sl-menu {
					box-shadow: var(--sl-shadow-x-large);
				}

				/* sl-menu-item:host overrides display */

				sl-menu-item[hidden], sl-divider[hidden] {
					display: none !important;
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
				et2-image::before {
					font-size: 1.3em; /*make bi icons same size as et2-image img*/
				}
			`
		]
	}

	private structure = [];
	private popup = null;
	private hideCallback = null;

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
		if(this.hideCallback)
		{
			this.hideCallback.call();
		}
	}


	public showAt(_x, _y, _onHide)
	{
		this.hideCallback = _onHide;
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
			document.body.append(this.popup);
		}

		// Open where instructed
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
		Promise.all([this.updateComplete, this.popup.updateComplete]).then(() =>
		{
			// Causes scroll issues if we don't position
			this.popup.popup.style = "top: 0px";
			(<SlMenuItem>this.menu.querySelector('sl-menu-item'))?.focus();
		});
	}

	/**
	 * Update the menu items with current disabled / visible settings
	 *
	 * @param _links
	 */
	public applyContext(_links, _selected, _target)
	{
		// Reset & hide all, in case some actions were not included in links
		this.menu.querySelectorAll("sl-menu-item").forEach(i => i.disabled = i.hidden = true);
		this.menu.querySelectorAll("sl-divider").forEach(i => i.hidden = false);

		Object.keys(_links).forEach((actionId) =>
		{
			// Take the last one if there's more than one with the same ID as a work-around to automatic drag actions getting added twice
			// in different places in some cases (nextmatch_controller vs EgwPopupActionImplementation)
			const menuItem = <SlMenuItem>Array.from(this.shadowRoot.querySelectorAll("[data-action-id='" + actionId + "']")).pop();
			if(!menuItem)
			{
				return;
			}
			menuItem.disabled = !_links[actionId].enabled;
			menuItem.hidden = !_links[actionId].visible;
			if(menuItem.type == "checkbox")
			{
				menuItem.checked = _links[actionId].actionObj.checked ?? false;
			}
		});

		// Hide dividers before empty sections
		try
		{
			this.menu.querySelectorAll("sl-divider:not(:has( + sl-menu-item:not([hidden])))").forEach((i: SlDivider) => i.hidden = true);
		}catch (e)
		{
			console.log("It appears you are using an older browser version, please consider updating")
		}
		// Copy caption changes
		let osClipboard;
		if(_links.egw_os_clipboard && (osClipboard = <SlMenuItem>this.shadowRoot.querySelector("[data-action-id='egw_os_clipboard']")))
		{
			osClipboard.innerText = _links.egw_os_clipboard.actionObj.caption;
		}
	}
	public hide()
	{
		if(this.popup)
		{
			this.popup.active = false;
		}
		if(this.hideCallback)
		{
			this.hideCallback.call();
		}
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
				event.detail.item.querySelector('et2-image').src = item.checked ? "toggle-on" : "toggle-off";
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
		const id = CSS.escape(item.id);

		// Defer loading of sub-menus because the forced repaint takes too long with lots of children
		let childPromise = Promise.resolve(html``);
		if(item.children.length > 0)
		{
			childPromise = new Promise((resolve) =>
			{
				setTimeout(() =>
				{
					resolve(html`
                        <sl-menu slot="submenu">
                        ${repeat(item.children, i => this.itemTemplate(i))}
                        </sl-menu>
					`);
				}, item.children.length);
			})
		}
		// Remove the loading attribute when sub-menu is done
		const updateLoading = async(element : SlMenuItem) =>
		{
			if(item.children.length == 0 || !element)
			{
				return;
			}
			// Menu item was rendered, but give children a chance to render
			setTimeout(async() =>
			{
				// Wait for child creation
				await childPromise;
				// Wait for child render
				await Promise.all((<SlMenuItem[]>Array.from(element.querySelectorAll('sl-menu-item')))
					.map(e => e.updateComplete));
				// No longer loading
				setTimeout(() =>
				{
					element.loading = false;
				}, 100);
			});
		};
		const captionStyle=item.color?`color:${item.color};`:''+item.indentation?`padding-left:${item.indentation}em;`:'';
		return html`
            <sl-menu-item
                    class=${classMap({
                        "default-item": item.default
                    })}
                    id=${id}
                    type="${item.checkbox ? "checkbox" : "normal"}"
                    data-action-id="${item.id}"
                    ?checked=${item.checkbox && item.checked}
                    ?disabled=${!item.enabled}
                    ?loading=${item.children.length > 0}
                    .value=${item}
                    @click=${item.checkbox ? this.handleCheckboxClick : nothing}
                    ${ref(updateLoading)}
            >
                ${item.iconUrl ? html`
                    <et2-image slot="prefix" src="${item.iconUrl}"></et2-image>` : nothing}
				<span style=${captionStyle||nothing}>${item.caption}</span>
                ${item.shortcutCaption ? html`<span slot="suffix" class="keyboard_shortcut">
					${item.shortcutCaption}
				</span>` : nothing}
                ${item.children.length == 0 ? nothing : html`
                        ${until(childPromise)}
                `}
            </sl-menu-item>
		`;
	}


	render()
	{
		return html`
            <sl-menu
                    @sl-select=${this.handleSelect}
                    @contextmenu=${(e) =>
                    {
                        if(!e.ctrlKey)
                        {
                            e.preventDefault();
                        }
                    }}
            >
                ${repeat(this.structure, i => this.itemTemplate(i))}
            </sl-menu>`;
	}
}
