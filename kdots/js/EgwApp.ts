import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import "@shoelace-style/shoelace/dist/components/split-panel/split-panel.js";
import {property} from "lit/decorators/property.js";

import styles from "./EgwApp.styles";
import {state} from "lit/decorators/state.js";

@customElement('egw-app')
//@ts-ignore
export class EgwApp extends LitElement
{
	static get styles()
	{
		return [
			styles,

			// TEMP STUFF
			css`
				:host {
					--placeholder-background-color: #e97234;
				}

				.placeholder {
					width: 100%;
					display: block;
					font-size: 200%;
					text-align: center;
					background-color: var(--placeholder-background-color, silver);
				}

				.placeholder:after {
					content: " (placeholder)";
				}

				[class*="left"] .placeholder, [class*="right"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(1, 1, 1, .5));
				}

				[class*="footer"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(1, 1, 1, .05));
				}
			`
		];
	}

	@property()
	name = "Application name";

	@state()
	leftCollapsed = false;

	get egw()
	{
		return window.egw ?? this.parentElement.egw ?? null;
	}

	render()
	{
		return html`
            <div class="egw_fw_app__name" part="name">
                <sl-icon-button name="${this.leftCollapsed ? "chevron-double-right" : "chevron-double-left"}"
                                label="${this.egw?.lang("Hide area")}"
                                @click=${() => {this.leftCollapsed = !this.leftCollapsed}}
                ></sl-icon-button>
                <h2>${this.egw?.lang(this.name) ?? this.name}</h2>
            </div>
            <aside class="egw_fw_app__left" part="left">
                <slot name="left"><span class="placeholder">left</span></slot>
            </aside>
            <aside class="egw_fw_app__right" part="right">
                <slot name="right"><span class="placeholder">right</span></slot>
            </aside>
            <header class="egw_fw_app__header" part="header">
                <slot name="main-header"><span class="placeholder"> ${this.name} main-header</span></slot>
            </header>
            <main class="egw_fw_app__main" part="main"
                  aria-label="${this.name}" tabindex="0">
                <slot><span class="placeholder">main</span></slot>
            </main>
            <footer class="egw_fw_app__footer" part="footer">
                <slot name="footer"><span class="placeholder">main-footer</span></slot>
            </footer>
		`;
	}
}