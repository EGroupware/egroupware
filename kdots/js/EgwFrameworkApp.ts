import {css, html, LitElement, nothing, render} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";

import styles from "./EgwFrameworkApp.styles";
import {SlSplitPanel} from "@shoelace-style/shoelace";
import {HasSlotController} from "../../api/js/etemplate/Et2Widget/slot";
import type {EgwFramework} from "./EgwFramework";

/**
 * @summary Application component inside EgwFramework
 *
 * Contain an EGroupware application inside the main framework.  It consists of left, main and right areas.  Each area
 * has a header, content and footer.  Side content areas are not shown when there is no content.
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
 * @cssproperty [--application-color=--primary-background-color] - Color to use for this application
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

	@property()
	url = "";

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

	protected loadingPromise = Promise.resolve();

	/** The application's content must be in an iframe instead of handled normally */
	protected useIframe = false;

	connectedCallback()
	{
		super.connectedCallback();
		(<Promise<string>>this.egw.preference(this.leftPanelInfo.preference, this.name, true)).then((width) =>
		{
			this.leftPanelInfo.preferenceWidth = typeof width !== "undefined" ? parseInt(width) : this.leftPanelInfo.defaultWidth;
		});
		(<Promise<string>>this.egw.preference(this.rightPanelInfo.preference, this.name, true)).then((width) =>
		{
			this.rightPanelInfo.preferenceWidth = typeof width !== "undefined" ? parseInt(width) : this.rightPanelInfo.defaultWidth;
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
	}

	firstUpdated()
	{
		this.load(this.url);
	}

	protected load(url)
	{
		if(!url)
		{
			while(this.firstChild)
			{
				this.removeChild(this.lastChild);
			}
			return;
		}
		let targetUrl = "";
		this.useIframe = true;
		let matches = url.match(/\/index.php\?menuaction=([A-Za-z0-9_\.]*.*&ajax=true.*)$/);
		if(matches)
		{
			// Matches[1] contains the menuaction which should be executed - replace
			// the given url with the following line. This will be evaluated by the
			// jdots_framework ajax_exec function which will be called by the code
			// below as we set useIframe to false.
			targetUrl = "index.php?menuaction=" + matches[1];
			this.useIframe = false;
		}

		// Destroy application js
		if(window.app[this.name] && window.app[this.name].destroy)
		{
			window.app[this.name].destroy();
			delete window.app[this.name];	// really delete it, so new object get constructed and registered for push
		}
		if(!this.useIframe)
		{
			return this.loadingPromise = this.egw.request(
				this.framework.getMenuaction('ajax_exec', targetUrl, this.name),
				[targetUrl]
			).then((data : string[]) =>
			{
				// Load request returns HTML.  Shove it in.
				render(html`${unsafeHTML(data.join(""))}`, this);

				// Might have just slotted aside content, hasSlotController will requestUpdate()
				// but we need to do it anyway for translation
				this.requestUpdate();
			});
		}
		else
		{
			this.loadingPromise = new Promise((resolve, reject) =>
			{
				const timeout = setTimeout(() => reject(this.name + " load failed"), 5000);
				this.addEventListener("load", () =>
				{
					clearTimeout(timeout);
					resolve()
				}, {once: true});

				render(this._iframeTemplate(), this);
			});
			// Might have just changed useIFrame, need to update to show that
			this.requestUpdate();
			return this.loadingPromise;
		}
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
		const oldValue = this[attribute];
		this[attribute] = true;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].hiddenWidth;
		this.requestUpdate(attribute, oldValue);
	}

	get egw()
	{
		return window.egw ?? (<EgwFramework>this.parentElement).egw ?? null;
	}

	get framework() : EgwFramework
	{
		return this.closest("egw-framework");
	}

	private hasSideContent(side : "left" | "right")
	{
		return this.hasSlotController.test(`${side}-header`) ||
			this.hasSlotController.test(side) || this.hasSlotController.test(`${side}-footer`);
	}

	/**
	 * User adjusted side slider, update preference
	 *
	 * @param event
	 * @protected
	 */
	protected async handleSlide(event)
	{
		// Skip if there's no panelInfo - event is from the wrong place
		if(typeof event.target?.panelInfo != "object")
		{
			return;
		}

		// Skip if there's no side-content
		if(!this.hasSideContent(event.target.panelInfo.side))
		{
			return;
		}

		// Left side is in pixels, round to 2 decimals
		let newPosition = Math.round(event.target.panelInfo.side == "left" ? event.target.positionInPixels * 100 : event.target.position * 100) / 100;

		// Update collapsed
		this[`${event.target.panelInfo.side}Collapsed`] = newPosition == event.target.panelInfo.hiddenWidth;

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

	/**
	 * Displayed for the time between when the application is added and when the server responds with content
	 *
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	protected _loadingTemplate()
	{
		// Don't show loader for iframe, it will not resolve
		if(this.useIframe)
		{
			return nothing;
		}

		return html`
            <div class="egw_fw_app__loading">
                <sl-spinner></sl-spinner>
            </div>`;
	}

	/**
	 * If we have to use an iframe, this is where it is made
	 * @returns {typeof nothing | typeof nothing}
	 * @protected
	 */
	protected _iframeTemplate()
	{
		if(!this.useIframe)
		{
			return nothing;
		}
		return html`
            <iframe src="${this.url}"></iframe>`;
	}

	protected _asideTemplate(parentSlot, side, label?)
	{
		const asideClassMap = classMap({
			"egw_fw_app__aside": true,
			"egw_fw_app__left": true,
			"egw_fw_app__aside-collapsed": this.leftCollapsed,
		});
		return html`
            <aside slot="${parentSlot}" part="${side}" class=${asideClassMap} aria-label="${label}">
                <div class="egw_fw_app__aside_header header">
                    <slot name="${side}-header"><span class="placeholder">${side}-header</span></slot>
                </div>
                <div class="egw_fw_app__aside_content content">
                    <slot name="${side}"><span class="placeholder">${side}</span></slot>
                </div>

                <div class="egw_fw_app__aside_footer footer">
                    <slot name="${side}-footer"><span class="placeholder">${side}-footer</span></slot>
                </div>
            </aside>`;
	}

	render()
	{
		const hasLeftSlots = this.hasSideContent("left");
		const hasRightSlots = this.hasSideContent("right");

		const leftWidth = this.leftCollapsed || !hasLeftSlots ? this.leftPanelInfo.hiddenWidth :
						  this.leftPanelInfo.preferenceWidth;
		const rightWidth = this.rightCollapsed || !hasRightSlots ? this.rightPanelInfo.hiddenWidth :
						   this.rightPanelInfo.preferenceWidth;
		return html`
            <div class="egw_fw_app__header">
                <div class="egw_fw_app__name" part="name">
                    ${hasLeftSlots ? html`
                    <sl-icon-button name="${this.leftCollapsed ? "chevron-double-right" : "chevron-double-left"}"
                                    label="${this.leftCollapsed ? this.egw.lang("Show left area") : this.egw?.lang("Hide left area")}"
                                    @click=${() =>
                                    {
                                        this.leftCollapsed = !this.leftCollapsed;
                                        // Just in case they collapsed it manually, reset
                                        this.leftPanelInfo.preferenceWidth = this.leftPanelInfo.preferenceWidth || this.leftPanelInfo.defaultWidth;
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
                <sl-button-group>
                    <sl-icon-button name="arrow-clockwise"
                                    label=${this.egw.lang("Reload %1", this.egw.lang(this.name))}></sl-icon-button>
                    <sl-icon-button name="printer"
                                    label=${this.egw.lang("Reload %1", this.egw.lang(this.name))}></sl-icon-button>
                    <sl-icon-button name="gear-wide"
                                    label=${this.egw.lang("Site configuration for %1", this.egw.lang(this.name))}></sl-icon-button>
                </sl-button-group>
            </div>
            <div class="egw_fw_app__main" part="main">
                <sl-split-panel class=${classMap({"egw_fw_app__outerSplit": true, "no-content": !hasLeftSlots})}
                                primary="start" position-in-pixels="${leftWidth}"
                                snap="0px 20%" snap-threshold="50"
                                .panelInfo=${this.leftPanelInfo}
                                @sl-reposition=${(e) => this.handleSlide(e)}
                >
                    <sl-icon slot="divider" name="grip-vertical" @dblclick=${() =>
                    {
                        this.hideLeft();
                    }}></sl-icon>
                    ${this._asideTemplate("start", "left")}
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
                            this.hideRight();
                        }}></sl-icon>
                        <header slot="start" class="egw_fw_app__header header" part="content-header">
                            <slot name="header"><span class="placeholder">header</span></slot>
                        </header>
                        <div slot="start" class="egw_fw_app__main_content content" part="content"
                             aria-label="${this.name}" tabindex="0">
                            <slot>
                                ${this._loadingTemplate()}
                                <span class="placeholder">main</span>
                            </slot>
                        </div>
                        <footer slot="start" class="egw_fw_app__footer footer" part="footer">
                            <slot name="footer"><span class="placeholder">main-footer</span></slot>
                        </footer>
                        ${this._asideTemplate("end", "right", this.egw.lang("%1 application details", this.egw.lang(this.name)))}
                    </sl-split-panel>
                </sl-split-panel>
            </div>
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