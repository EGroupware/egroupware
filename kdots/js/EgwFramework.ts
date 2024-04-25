import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import "@shoelace-style/shoelace/dist/components/split-panel/split-panel.js";

import styles from "./EgwFramework.styles";
import {repeat} from "lit/directives/repeat.js";

@customElement('egw-framework')
//@ts-ignore
export class EgwFramework extends LitElement
{
	static get styles()
	{
		return [
			styles,

			// TEMP STUFF
			css`
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

				.egw_fw__base {
					--placeholder-background-color: #75bd20;
				}

				.egw_fw__footer .placeholder {
					background-color: hsl(182, 58%, 62%);
				}

				.egw_fw__main-wrapper {
					--placeholder-background-color: #e97234;
				}

				.egw_fw__status .placeholder {
					writing-mode: vertical-rl;
					text-orientation: mixed;
					height: 100%;
				}

				[class*="left"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(1, 1, 1, .5));
				}

				[class*="footer"] .placeholder {
					background-color: color-mix(in lch, var(--placeholder-background-color), rgba(1, 1, 1, .05));
				}


				::slotted(div#egw_fw_sidebar_r) {
					position: relative;
				}
			`
		];
	}

	@property()
	layout = "default";

	@property({type: Array})
	applicationList = [];

	get egw()
	{
		return window.egw ?? {
			app_name: () => "",
			lang: (t) => t,
			preference: (n, app) => ""
		};
	}

	render()
	{
		const statusPosition = this.egw?.preference("statusPosition", this.egw?.app_name()) ?? "36";

		const classes = {
			"egw_fw__base": true
		}
		classes[`egw_fw__layout-${this.layout}`] = true;

		return html`
            <div class=${classMap(classes)} part="base">
                <div class="egw_fw__banner" part="banner" role="banner">
                    <slot name="banner"><span class="placeholder">Banner</span></slot>
                </div>
                <header class="egw_fw__header" part="header">
                    <slot name="logo"></slot>
                    <sl-dropdown class="egw_fw__app_list">
                        <sl-icon-button slot="trigger" name="grid-3x3-gap"
                                        label="${this.egw.lang("Application list")}"
                                        aria-description="${this.egw.lang("Activate for a list of applications")}"
                        ></sl-icon-button>
                        ${repeat(this.applicationList, (app) => html`
                            <et2-image src="${app.icon}" aria-label="${app.title}"></et2-image>`)}
                    </sl-dropdown>

                    <slot name="header"><span class="placeholder">header</span></slot>
                    <slot name="header-right"><span class="placeholder">header-right</span></slot>
                </header>
                <div class="egw_fw__divider">
                    <sl-split-panel part="status-split" position-in-pixels="${statusPosition}" primary="end"
                                    snap="150px 45px 0px"
                                    snap-threshold="40"
                                    aria-label="Side menu resize">

                        <main slot="start" part="main">
                            <slot></slot>
                        </main>
                        <sl-icon slot="divider" name="grip-vertical"></sl-icon>
                        <aside slot="end" class="egw_fw__status" part="status">
                            <slot name="status"><span class="placeholder">status</span></slot>
                        </aside>
                    </sl-split-panel>
                </div>
                <footer class="egw_fw__footer" part="footer">
                    <slot name="footer"><span class="placeholder">footer</span></slot>
                </footer>
            </div>
		`;
	}
}