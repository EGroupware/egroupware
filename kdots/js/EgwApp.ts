import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import "@shoelace-style/shoelace/dist/components/split-panel/split-panel.js";
import {property} from "lit/decorators/property.js";

import styles from "./EgwApp.styles";
import {state} from "lit/decorators/state.js";

/**
 * @summary Application component inside EgwFramework
 *
 * @dependency sl-icon-button
 *
 * @slot - Main application content.  Other slots are normally hidden if they have no content
 * @slot header - Top of app, contains logo, app icons.
 * @slot footer - Very bottom of the main content.
 * @slot left - Optional content to the left.  Use for application navigation.
 * @slot left-header - Top of left side
 * @slot left-footer - bottom of left side
 * @slot right - Optional content to the right.  Use for application context details.
 * @slot right-header - Top of right side
 * @slot right-footer - bottom of right side
 *
 * @csspart name - Top left, holds the application name.
 * @csspart header - Top main application header, optional application toolbar goes here.
 * @csspart content-header - Top of center, optional.
 * @csspart main - Main application content.
 * @csspart left - Left optional content.
 * @csspart right - Right optional content.
 * @csspart footer - Very bottom of the main content.
 *
 * @cssproperty [--icon-size=32] - Height of icons used in the framework
 */
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
				:host .placeholder {
					display: none;
				}

				:host(.placeholder) .placeholder {
					display: block;
					--placeholder-background-color: #e97234;
				}
				.placeholder {
					width: 100%;
					font-size: 200%;
					text-align: center;
					background-color: var(--placeholder-background-color);
				}

				.placeholder:after, .placeholder:before {
					content: " ⌖ ";
				}

				:host(.placeholder) [class*="left"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(.5, .5, 1, .1));
				}

				:host(.placeholder) [class*="right"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(.5, 1, .5, .1));
				}

				:host(.placeholder) [class*="footer"] .placeholder {
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
            <div class="egw_fw_app__header">
                <div class="egw_fw_app__name" part="name">
                    <sl-icon-button name="${this.leftCollapsed ? "chevron-double-right" : "chevron-double-left"}"
                                    label="${this.egw?.lang("Hide area")}"
                                    @click=${() => {this.leftCollapsed = !this.leftCollapsed}}
                    ></sl-icon-button>
                    <h2>${this.egw?.lang(this.name) ?? this.name}</h2>
                </div>
                <header class="egw_fw_app__header" part="header">
                    <slot name="main-header"><span class="placeholder"> ${this.name} main-header</span></slot>
                </header>
            </div>
            <main class="egw_fw_app__main" part="main"
                  aria-label="${this.name}" tabindex="0">
                <aside class="egw_fw_app__aside egw_fw_app__left" part="left">
                    <div class="egw_fw_app__aside_header">
                        <slot name="left-header"><span class="placeholder">left-header</span></slot>
                    </div>
                    <div class="egw_fw_app__aside_content">
                        <slot name="left"><span class="placeholder">left</span></slot>
                    </div>

                    <div class="egw_fw_app__aside_footer">
                        <slot name="left-footer"><span class="placeholder">left-footer</span></slot>
                    </div>
                </aside>
                <aside class="egw_fw_app__aside egw_fw_app__right" part="right">
                    <div class="egw_fw_app__aside_header">
                        <slot name="right-header"><span class="placeholder">right-header</span></slot>
                    </div>
                    <div class="egw_fw_app__aside_content">
                        <slot name="right"><span class="placeholder">right</span></slot>
                    </div>
                    <div class="egw_fw_app__aside_footer">
                        <slot name="right-footer"><span class="placeholder">right-footer</span></slot>
                    </div>
                </aside>
                <header class="egw_fw_app__header" part="content-header">
                    <slot name="header"><span class="placeholder">header</span></slot>
                </header>
                <main class="egw_fw_app__main_content" part="content">
                <slot><span class="placeholder">main</span></slot>
                </main>
                <footer class="egw_fw_app__footer" part="footer">
                    <slot name="footer"><span class="placeholder">main-footer</span></slot>
                </footer>
            </main>
		`;
	}
}