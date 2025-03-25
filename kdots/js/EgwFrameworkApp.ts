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
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_IPrint} from "../../api/js/etemplate/et2_core_interfaces";
import {repeat} from "lit/directives/repeat.js";
import {until} from "lit/directives/until.js";
import {Favorite} from "../../api/js/etemplate/Et2Favorites/Favorite";

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
 * @cssproperty [--application-header-text-color=white] - Text color in the application header
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

	@property({reflect: true})
	name = "Application name";

	@property()
	title : string = "";

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
	protected _sideboxData : any;
	private _hasFavorites = false;

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

		this.addEventListener("load", this.handleEtemplateLoad);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("load", this.handleEtemplateLoad);

		this.childNodes.forEach((childNode : HTMLElement) =>
		{
			const et = etemplate2.getById(childNode.id);
			if(et !== null)
			{
				et.clear();
				// Clean up DOM nodes that are outside the etemplate2
				const domContainer = et.DOMContainer;
				domContainer.parentNode?.querySelector("[name='egw_iframe_autocomplete_helper']")?.remove();
				domContainer.remove();
				et._DOMContainer = null;
			}
		});
	}

	firstUpdated()
	{
		this.load(this.url);
	}

	protected async getUpdateComplete() : Promise<boolean>
	{
		const result = await super.getUpdateComplete();
		await this.loadingPromise;

		return result
	}

	public load(url)
	{
		if(!url)
		{
			while(this.firstChild)
			{
				this.removeChild(this.lastChild);
			}
			return;
		}
		this.url = url;
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
			).then((data : string | string[] | { DOMNodeID? : string } | { DOMNodeID? : string }[]) =>
			{
				if(!data)
				{
					return;
				}
				// Load request returns HTML.  Shove it in.
				if(typeof data == "string" || typeof data == "object" && typeof data[0] == "string")
				{
					render(html`${unsafeHTML((<string[]>data).join(""))}`, this);
				}
				else
				{
					// We got some data, use it
					const items = (Array.isArray(data) ? data : [data])
						.filter(data => (typeof data.DOMNodeID == "string" && document.querySelector("[id='" + data.DOMNodeID + "']") == null));

					render(html`${repeat(items, i => i.DOMNodeID, (item) => html`
                        <div id="${item.DOMNodeID}"></div>`)}`, this);
				}

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
				render(this._iframeTemplate(), this);
				this.querySelector("iframe").addEventListener("load", () =>
				{
					clearTimeout(timeout);
					resolve()
				}, {once: true});
			});
			// Might have just changed useIFrame, need to update to show that
			this.requestUpdate();
			return this.loadingPromise;
		}
	}

	public getMenuaction(_fun, _ajax_exec_url, appName = "")
	{
		return this.framework.getMenuaction(_fun, _ajax_exec_url, appName || this.name);
	}

	public setSidebox(sideboxData, hash?)
	{
		this._sideboxData = sideboxData;

		if(this._sideboxData?.some(s => s.title == "Favorites" || s.title == this.egw.lang("favorites")))
		{
			// This might be a little late, but close enough for rendering
			Favorite.load(this.egw, this.name).then((favorites) =>
			{
				this._hasFavorites = (Object.values(favorites).length > 1)
				this.requestUpdate();
			});
		}

		this.requestUpdate();
	}

	public showLeft()
	{
		return this.showSide("left");
	}

	public hideLeft()
	{
		return this.hideSide("left");
	}

	public showRight()
	{
		return this.showSide("right");
	}

	public hideRight()
	{
		return this.hideSide("right");
	}

	public refresh()
	{
		return this.egw.refresh("", this.name);
		/* Could also be this.load(false); this.load(this.url) */
	}

	public async print()
	{

		let template;
		let deferred = [];
		let et2_list = [];
		const appWindow = this.framework.egw.window;

		if((template = appWindow.etemplate2.getById(this.id)) && this == template.DOMContainer)
		{
			deferred = deferred.concat(template.print());
			et2_list.push(template);
		}
		else
		{
			// et2 inside, let its widgets prepare
			this.querySelectorAll(":scope > *").forEach((domNode : HTMLElement) =>
			{
				let et2 = appWindow.etemplate2.getById(domNode.id);
				if(et2 && (domNode.offsetWidth > 0 || domNode.offsetHeight > 0 || domNode.getClientRects().length > 0))
				{
					deferred = deferred.concat(et2.print());
					et2_list.push(et2);
				}
			});
		}

		if(et2_list.length)
		{
			// Try to clean up after - not guaranteed
			let afterPrint = () =>
			{
				this.egw.loading_prompt(this.name, true, this.egw.lang('please wait...'), this, egwIsMobile() ? 'horizental' : 'spinner');

				// Give framework a chance to deal, then reset the etemplates
				appWindow.setTimeout(() =>
				{
					for(let i = 0; i < et2_list.length; i++)
					{
						et2_list[i].widgetContainer.iterateOver(function(_widget)
						{
							_widget.afterPrint();
						}, et2_list[i], et2_IPrint);
					}
					this.egw.loading_prompt(this.name, false);
				}, 100);
				appWindow.onafterprint = null;
			};
			/* Not sure what this did, it triggers while preview is still up
			if(appWindow.matchMedia)
			{
				var mediaQueryList = appWindow.matchMedia('print');
				var listener = function(mql)
				{
					if(!mql.matches)
					{
						mediaQueryList.removeListener(listener);
						afterPrint();
					}
				};
				mediaQueryList.addListener(listener);
			}

			 */

			appWindow.addEventListener("afterprint", afterPrint, {once: true});

			// Wait for everything to be ready
			return Promise.all(deferred).catch((e) =>
			{
				afterPrint();
				if(typeof e == "undefined")
				{
					throw "rejected";
				}
			});
		}
	}

	protected showSide(side)
	{
		const attribute = `${side}Collapsed`;
		this[attribute] = false;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].preferenceWidth || this[`${side}PanelInfo`].defaultWidth;
		return this.updateComplete;
	}

	protected hideSide(side : "left" | "right")
	{
		const attribute = `${side}Collapsed`;
		const oldValue = this[attribute];
		this[attribute] = true;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].hiddenWidth;
		this.requestUpdate(attribute, oldValue);
		return this.updateComplete;
	}

	get egw()
	{
		return window.egw(this.name) ?? (<EgwFramework>this.parentElement).egw ?? null;
	}

	get framework() : EgwFramework
	{
		return this.closest("egw-framework");
	}

	get appName() : string
	{
		return this.name;
	}

	private hasSideContent(side : "left" | "right")
	{
		return this.hasSlotController.test(`${side}-header`) ||
			this.hasSlotController.test(side) || this.hasSlotController.test(`${side}-footer`);
	}

	/**
	 * An etemplate has loaded inside
	 * Move anything top-level that has a slot
	 */
	protected handleEtemplateLoad(event)
	{
		const etemplate = etemplate2.getById(event.target.id);
		if(!etemplate || !event.composedPath().includes(this))
		{
			return;
		}

		// Move templates with slots (along with DOMContainer if only one template there to keep it together)
		const slottedTemplates = etemplate.DOMContainer.querySelectorAll(":scope > [slot]");
		if(slottedTemplates.length == 1 && etemplate.DOMContainer.childElementCount == 1)
		{
			etemplate.DOMContainer.slot = slottedTemplates[0].slot;
		}
		else
		{
			slottedTemplates.forEach(node => {this.appendChild(node);});
		}

		// Move top level slotted components
		const slottedWidgets = etemplate.widgetContainer.querySelectorAll(":scope > [slot]")
		slottedWidgets.forEach(node => {this.appendChild(node);});

		// Request update, since slotchanged events are only fired when the attribute changes and they're already set
		if(slottedTemplates.length > 0 || slottedWidgets.length > 0)
		{
			this.requestUpdate();
		}
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

		let panelInfo = event.target.panelInfo;

		await this.loadingPromise;

		// Left side is in pixels, round to 2 decimals
		let newPosition = Math.round(panelInfo.side == "left" ? event.target.positionInPixels * 100 : Math.max(100, event.target.position) * 100) / 100;

		// Update collapsed
		this[`${panelInfo.side}Collapsed`] = newPosition == panelInfo.hiddenWidth;

		let preferenceName = panelInfo.preference;
		let currentPreference = parseFloat("" + await this.egw.preference(preferenceName, this.name, true));

		if(newPosition != currentPreference && !isNaN(newPosition))
		{
			panelInfo.preferenceWidth = newPosition;
			if(panelInfo.resizeTimeout)
			{
				window.clearTimeout(panelInfo.resizeTimeout);
			}
			panelInfo.resizeTimeout = window.setTimeout(() =>
			{
				this.egw.set_preference(this.name, preferenceName, newPosition);

				// Tell etemplates to resize
				this.querySelectorAll("[id]").forEach(e =>
				{
					if(etemplate2.getById(e.id))
					{
						etemplate2.getById(e.id).resize(new Event("resize"));
					}
				});
			}, 500);
		}
	}

	protected async handleSideboxMenuClick(event)
	{
		return this.egw.open_link(event.target.dataset.item_link);
	}

	protected handleAppMenuClick(event)
	{
		return egw_link_handler(`/egroupware/index.php?menuaction=admin.admin_ui.index&load=admin.uiconfig.index&appname=${this.name}&ajax=true`, 'admin');
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

	protected _asideTemplate(parentSlot, side : "left" | "right", label?)
	{
		const asideClassMap = classMap({
			"egw_fw_app__aside": true,
			"egw_fw_app__left": side == "left",
			"egw_fw_app__right": side == "right",
			"egw_fw_app__aside-collapsed": side == "left" ? this.leftCollapsed : this.rightCollapsed,
		});
		return html`
            <aside slot="${parentSlot}" part="${side}" class=${asideClassMap} aria-label="${label}">
                <div class="egw_fw_app__aside_header header">
                    <slot name="${side}-header"><span class="placeholder">${side}-header</span></slot>
                </div>
                <div class="egw_fw_app__aside_content content">
                    ${side == "left" ? this._leftMenuTemplate() : nothing}
                    <slot name="${side}"><span class="placeholder">${side}</span></slot>
                </div>
                <div class="egw_fw_app__aside_footer footer">
                    <slot name="${side}-footer"><span class="placeholder">${side}-footer</span></slot>
                </div>
            </aside>`;
	}

	/**
	 * Left sidebox automatic content
	 *
	 * @protected
	 */
	protected _leftMenuTemplate()
	{
		// Put favorites in left sidebox if any are set
		if(!this._hasFavorites)
		{
			return nothing;
		}
		return html`${until(Favorite.load(this.egw, this.name).then((favorites) =>
		{
			// If more than the blank favorite is found, add favorite menu to sidebox
			if(Object.values(favorites).length > 1)
			{
				const favSidebox = this._sideboxData.find(s => s.title.toLowerCase() == "favorites" || s.title == this.egw.lang("favorites"));
				return html`
                    <sl-details class="favorites" slot="left"
                                ?open=${favSidebox?.opened}
                                summary=${this.egw.lang("Favorites")}
                                @sl-show=${() => {this.egw.set_preference(this.name, 'jdots_sidebox_' + favSidebox.menu_name, true);}}
                                @sl-hide=${() => {this.egw.set_preference(this.name, 'jdots_sidebox_' + favSidebox.menu_name, false);}}
                    >
                        <et2-favorites-menu application=${this.name}></et2-favorites-menu>
                    </sl-details>
				`;
			}
		}), nothing)}`;
	}

	/**
	 * Top right header, contains application action buttons (reload, print, config)
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	protected _rightHeaderTemplate()
	{
		return html`
            <et2-button-icon nosubmit name="arrow-clockwise"
                             label=${this.egw.lang("Reload %1", this.egw.lang(this.name))}
                             statustext=${this.egw.lang("Reload %1", this.egw.lang(this.name))}
                             @click=${this.refresh}
            ></et2-button-icon>
            <et2-button-icon nosubmit name="printer"
                             label=${this.egw.lang("Print")}
                             statustext=${this.egw.lang("Print")}
                             @click=${this.framework.print}
            ></et2-button-icon>
            <sl-dropdown class="egw_fw_app__menu">
                <div slot="trigger">${this.egw.lang("Menu")}
                    <sl-icon-button name="chevron-double-down"></sl-icon-button>
                </div>
                <sl-menu>
                    ${this.egw.user('apps')['admin'] !== undefined ? html`
                        <sl-menu-item
                                @click=${this.handleAppMenuClick}
                        >
                            <sl-icon slot="prefix" name="gear-wide"></sl-icon>
                            ${this.egw.lang("App configuration")}
                        </sl-menu-item>
                        <sl-divider></sl-divider>
                    ` : nothing}
                    ${this._threeDotsMenuTemplate()}
                </sl-menu>
            </sl-dropdown>
		`;
	}

	/**
	 * This is the "three dots menu" in the top-right corner.
	 * Most of what was in the sidebox now goes here.
	 *
	 * @returns {TemplateResult<1> | typeof nothing }
	 * @protected
	 */
	protected _threeDotsMenuTemplate()
	{
		if(!this._sideboxData)
		{
			return nothing;
		}

		return html`${repeat(this._sideboxData, (menu) => menu['menu_name'], this._threeDotsMenuItemTemplate)}`;
	}

	_threeDotsMenuItemTemplate(menu)
	{
		// No favorites here
		if(menu["title"] == "Favorites" || menu["title"] == this.egw.lang("favorites"))
		{
			return html`
                <sl-menu-item>
                    <et2-image style="width:1em;" src="fav_filter" slot="prefix"></et2-image>
                    ${menu["title"]}
                    <et2-favorites-menu slot="submenu" application="${this.appName}"></et2-favorites-menu>
                </sl-menu-item>
			`;
		}
		// Just one thing, don't bother with submenu
		if(menu["entries"].length == 1)
		{
			return this._sideboxMenuItemTemplate({...menu["entries"][0], lang_item: menu["title"]})
		}
		return html`
            <sl-menu-item>
                ${menu["title"]}
                <sl-menu slot="submenu">
                    ${repeat(menu["entries"], (entry) =>
                    {
                        return this._sideboxMenuItemTemplate(entry);
                    })}
                </sl-menu>
            </sl-menu-item>`;
	}

	/**
	 * An individual sub-item in the 3-dots menu
	 * @param item
	 * @returns {TemplateResult<1>}
	 */
	_sideboxMenuItemTemplate(item)
	{
		if(item["lang_item"] == "<hr />")
		{
			return html`
                <sl-divider></sl-divider>`;
		}
		return html`
            <sl-menu-item
                    ?disabled=${!item["item_link"]}
                    data-link=${item["item_link"]}
                    @click=${this.handleSideboxMenuClick}
            >
                ${typeof item["icon_or_star"] == "string" && item["icon_or_star"].endsWith("bullet.svg") ? nothing : html`
                    <et2-image name=${item["icon_or_star"]}></et2-image>
                `}
                ${item["lang_item"]}
            </sl-menu-item>`;

	}

	render()
	{
		const hasLeftSlots = this.hasSideContent("left") || this._hasFavorites;
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
                    <h2>${this.title || this.egw?.lang(this.name) || this.name}</h2>
                </div>
                <header class="egw_fw_app__header" part="header">
                    <slot name="main-header"><span class="placeholder"> ${this.name} main-header</span></slot>
                </header>
                ${until(this.framework?.getEgwComplete().then(() => this._rightHeaderTemplate()), html`
                    <sl-spinner></sl-spinner>`)}
            </div>
            <div class="egw_fw_app__main" part="main">
                <sl-split-panel class=${classMap({"egw_fw_app__outerSplit": true, "no-content": !hasLeftSlots})}
                                primary="start" position-in-pixels="${leftWidth}"
                                snap="0px 20%" snap-threshold="50"
                                .panelInfo=${this.leftPanelInfo}
                                @sl-reposition=${this.handleSlide}
                >
                    <sl-icon slot="divider" name="grip-vertical" @dblclick=${this.hideLeft}></sl-icon>
                    ${this._asideTemplate("start", "left")}
                    <sl-split-panel slot="end"
                                    class=${classMap({"egw_fw_app__innerSplit": true, "no-content": !hasRightSlots})}
                                    primary="start"
                                    position=${rightWidth} snap="50% 80% 100%"
                                    snap-threshold="50"
                                    .panelInfo=${this.rightPanelInfo}
                                    @sl-reposition=${this.handleSlide}
                    >
                        <sl-icon slot="divider" name="grip-vertical" @dblclick=${this.hideRight}></sl-icon>
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