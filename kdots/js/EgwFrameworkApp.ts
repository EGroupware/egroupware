import {css, html, LitElement, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";

import styles from "./EgwFrameworkApp.styles";
import {SlSplitPanel} from "@shoelace-style/shoelace";
import {HasSlotController} from "../../api/js/etemplate/Et2Widget/slot";

/**
 * @summary Application component inside EgwFramework
 *
 * @dependency sl-split-panel
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
 * @cssproperty [--left-min=0] - Minimum width of the left content
 * @cssproperty [--left-max=20%] - Maximum width of the left content
 * @cssproperty [--right-min=0] - Minimum width of the right content
 * @cssproperty [--right-max=50%] - Maximum width of the right content
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

	@state()
	rightCollapsed = false;

	get leftSplitter() { return <SlSplitPanel>this.shadowRoot.querySelector(".egw_fw_app__outerSplit");}

	get rightSplitter() { return <SlSplitPanel>this.shadowRoot.querySelector(".egw_fw_app__innerSplit");}


	protected readonly hasSlotController = new HasSlotController(<LitElement><unknown>this,
		'left', 'left-header', 'left-footer',
		'right', 'right-header', 'right-footer',
	);

	// Left is in pixels
	private leftPanelInfo : PanelInfo = {
		side: "left",
		preference: "jdotssideboxwidth",
		defaultWidth: 200,
		hiddenWidth: 0,
		preferenceWidth: 200
	};
	// Right is in percentage
	private rightPanelInfo : PanelInfo = {
		side: "right",
		preference: "app_right_width",
		defaultWidth: 50,
		hiddenWidth: 100,
		preferenceWidth: 50
	};
	private resizeTimeout : number;

	connectedCallback()
	{
		super.connectedCallback();
		this.egw.preference(this.leftPanelInfo.preference, this.name, true).then((width) =>
		{
			this.leftPanelInfo.preferenceWidth = parseInt(width ?? this.leftPanelInfo.defaultWidth);
		});
		this.egw.preference(this.rightPanelInfo.preference, this.name, true).then((width) =>
		{
			this.rightPanelInfo.preferenceWidth = parseInt(width ?? this.rightPanelInfo.defaultWidth);
		});
	}

	public showLeft()
	{
		this.showSide("left");
	}

	public hideLeft()
	{
		this.hideSide("left");
	}

	public showRight()
	{
		this.showSide("right");
	}

	public hideRight()
	{
		this.hideSide("right");
	}

	protected showSide(side)
	{
		const attribute = `${side}Collapsed`;
		this[attribute] = false;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].preferenceWidth || this[`${side}PanelInfo`].defaultWidth;
	}

	protected hideSide(side : "left" | "right")
	{
		const attribute = `${side}Collapsed`;
		this[attribute] = true;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].hiddenWidth;
	}

	get egw()
	{
		return window.egw ?? this.parentElement.egw ?? null;
	}

	/**
	 * User adjusted side slider, update preference
	 *
	 * @param event
	 * @protected
	 */
	protected async handleSlide(event)
	{
		if(typeof event.target?.panelInfo != "object")
		{
			return;
		}
		// Left side is in pixels, round to 2 decimals
		let newPosition = Math.round(event.target.panelInfo.side == "left" ? event.target.positionInPixels * 100 : event.target.position * 100) / 100;
		let preferenceName = event.target.panelInfo.preference;
		if(newPosition != event.target.panelInfo.preferenceWidth)
		{
			event.target.panelInfo.preferenceWidth = newPosition;
			if(this.resizeTimeout)
			{
				window.clearTimeout(this.resizeTimeout);
			}
			window.setTimeout(() =>
			{
				this.egw.set_preference(this.name, preferenceName, newPosition);
			}, 500);
		}
	}

	render()
	{
		const hasLeftSlots = this.hasSlotController.test('left-header') || this.hasSlotController.test('left') || this.hasSlotController.test('left-footer');
		const hasRightSlots = this.hasSlotController.test('right-header') || this.hasSlotController.test('right') || this.hasSlotController.test('right-footer');

		const leftClassMap = classMap({
			"egw_fw_app__aside": true,
			"egw_fw_app__left": true,
			"egw_fw_app__aside-collapsed": this.leftCollapsed,
		});
		const rightClassMap = classMap({
			"egw_fw_app__aside": true,
			"egw_fw_app__right": true,
			"egw_fw_app__aside-collapsed": this.rightCollapsed,
		});
		const leftWidth = this.leftCollapsed || !hasLeftSlots ? this.leftPanelInfo.hiddenWidth :
						  this.leftPanelInfo.preferenceWidth;
		const rightWidth = this.rightCollapsed || !hasRightSlots ? this.rightPanelInfo.hiddenWidth :
						   this.rightPanelInfo.preferenceWidth;
		return html`
            <div class="egw_fw_app__header">
                <div class="egw_fw_app__name" part="name">
                    ${hasLeftSlots ? html`
                    <sl-icon-button name="${this.leftCollapsed ? "chevron-double-right" : "chevron-double-left"}"
                                    label="${this.egw?.lang("Hide area")}"
                                    @click=${() =>
                                    {
                                        this.leftCollapsed = !this.leftCollapsed;
                                        this.requestUpdate("leftCollapsed")
                                    }}
                    ></sl-icon-button>`
                                   : nothing
                    }
                    <h2>${this.egw?.lang(this.name) ?? this.name}</h2>
                </div>
                <header class="egw_fw_app__header" part="header">
                    <slot name="main-header"><span class="placeholder"> ${this.name} main-header</span></slot>
                </header>
            </div>
            <main class="egw_fw_app__main" part="main" aria-label="${this.name}" tabindex="0">
                <sl-split-panel class=${classMap({"egw_fw_app__outerSplit": true, "no-content": !hasLeftSlots})}
                                primary="start" position-in-pixels="${leftWidth}"
                                snap="0px 20%" snap-threshold="50"
                                .panelInfo=${this.leftPanelInfo}
                                @sl-reposition=${(e) => this.handleSlide(e)}
                >
                    <sl-icon slot="divider" name="grip-vertical" @dblclick=${() =>
                    {
                        this.leftCollapsed = !this.leftCollapsed;
                        this.requestUpdate();
                    }}></sl-icon>
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
                    <sl-split-panel slot="end"
                                    class=${classMap({"egw_fw_app__innerSplit": true, "no-content": !hasRightSlots})}
                                    primary="start"
                                    position=${rightWidth} snap="50% 80% 100%"
                                    snap-threshold="50"
                                    .panelInfo=${this.rightPanelInfo}
                                    @sl-reposition=${(e) => this.handleSlide(e)}
                    >
                        <sl-icon slot="divider" name="grip-vertical" @dblclick=${() =>
                        {
                            this.rightCollapsed = !this.rightCollapsed;
                            this.requestUpdate();
                        }}></sl-icon>
                        <header slot="start" class="egw_fw_app__header header" part="content-header">
                            <slot name="header"><span class="placeholder">header</span></slot>
                        </header>
                        <main slot="start" class="egw_fw_app__main_content content" part="content">
                            <slot><span class="placeholder">main</span></slot>
                        </main>
                        <footer slot="start" class="egw_fw_app__footer footer" part="footer">
                            <slot name="footer"><span class="placeholder">main-footer</span></slot>
                        </footer>
                        <aside slot="end" class=${rightClassMap} part="right">
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

type PanelInfo = {
	side : "left" | "right",
	preference : "jdotssideboxwidth" | "app_right_width",
	hiddenWidth : number,
	defaultWidth : number,
	preferenceWidth : number | string
}