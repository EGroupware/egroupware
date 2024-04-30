import {css, html, LitElement, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";

import styles from "./EgwFrameworkApp.styles";

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
export class EgwFrameworkApp extends LitElement
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
		const leftClassMap = classMap({
			"egw_fw_app__aside": true,
			"egw_fw_app__left": true,
			"egw_fw_app__aside-collapsed": this.leftCollapsed
		});
		const leftWidth = this.leftCollapsed ? 0 : parseInt(this.egw.preference("jdotssideboxwidth", this.name) || 0) || 250;
		const rightWidth = (parseInt(this.egw.preference("app_right_width", this.name) || 0) || 0);
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
            <main class="egw_fw_app__main" part="main" aria-label="${this.name}" tabindex="0">
                <sl-split-panel class="egw_fw_app__outerSplit" primary="start" position-in-pixels="${leftWidth}">
                    <aside slot="start" part="left" class=${leftClassMap}>
                        <div class="egw_fw_app__aside_header header">
                            <slot name="left-header"><span class="placeholder">left-header</span></slot>
                        </div>
                        <div class="egw_fw_app__aside_content content">
                            <slot name="left"><span class="placeholder">left</span></slot>
                        </div>

                        <div class="egw_fw_app__aside_footer footer">
                            <slot name="left-footer"><span class="placeholder">left-footer</span></slot>
                        </div>
                    </aside>
                    <sl-split-panel slot="end" class="egw_fw_app__innerSplit" primary="start"
                                    position-in-pixels=${rightWidth || nothing}
                                    position=${rightWidth == 0 ? "100" : nothing}
                    >
                        <header slot="start" class="egw_fw_app__header header" part="content-header">
                            <slot name="header"><span class="placeholder">header</span></slot>
                        </header>
                        <main slot=start" class="egw_fw_app__main_content content" part="content">
                            <slot><span class="placeholder">main</span></slot>
                        </main>
                        <footer slot="start" class="egw_fw_app__footer footer" part="footer">
                            <slot name="footer"><span class="placeholder">main-footer</span></slot>
                        </footer>
                        <aside slot="end" class="egw_fw_app__aside egw_fw_app__right" part="right">
                            <div class="egw_fw_app__aside_header header">
                                <slot name="right-header"><span class="placeholder">right-header</span></slot>
                            </div>
                            <div class="egw_fw_app__aside_content content">
                                <slot name="right"><span class="placeholder">right</span></slot>
                            </div>
                            <div class="egw_fw_app__aside_footer footer">
                                <slot name="right-footer"><span class="placeholder">right-footer</span></slot>
                            </div>
                        </aside>
                    </sl-split-panel>
                </sl-split-panel>
            </main>
		`;
	}
}