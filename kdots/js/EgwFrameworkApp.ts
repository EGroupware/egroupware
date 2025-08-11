import {css, html, LitElement, nothing, render, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";

import styles from "./EgwFrameworkApp.styles";
import {SlSplitPanel} from "@shoelace-style/shoelace";
import {HasSlotController} from "../../api/js/etemplate/Et2Widget/slot";
import type {EgwFramework, FeatureList} from "./EgwFramework";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_IPrint} from "../../api/js/etemplate/et2_core_interfaces";
import {repeat} from "lit/directives/repeat.js";
import {until} from "lit/directives/until.js";
import {Favorite} from "../../api/js/etemplate/Et2Favorites/Favorite";
import type {Et2Template} from "../../api/js/etemplate/Et2Template/Et2Template";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {Et2Filterbox} from "../../api/js/etemplate/Et2Filterbox/Et2Filterbox";

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
 * @slot filter - Custom filter panel content, leave empty for auto-generated filters
 * @slot footer - Very bottom of the main content.
 * @slot left - Optional content to the left.  Use for application navigation.
 * @slot left-header - Top of left side
 * @slot left-top - Between left-header and Favourites
 * @slot left-footer - bottom of left side
 * @slot right - Optional content to the right.  Use for application context details.
 * @slot right-header - Top of right side
 * @slot right-footer - bottom of right side
 *
 * @csspart app-header - Top bar of application, contains name, header.
 * @csspart name - Top left, holds the application name.
 * @csspart header - Top main application header, optional application toolbar goes here.
 * @csspart app-menu - Drop down pplication menu, top right
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

	@property()
	features : FeatureList = {};

	/**
	 * Display application in a loading state.
	 * @type {boolean}
	 */
	@property({type: Boolean, reflect: true})
	loading = false;

	@state()
	leftCollapsed = false;

	@state()
	rightCollapsed = false;

	/**
	 * Pay no attention to splitter resizes (does not update preference)
	 * @type {boolean}
	 */
	@state() ignoreSplitterResize = false;

	get leftSplitter() { return <SlSplitPanel>this.shadowRoot.querySelector(".egw_fw_app__outerSplit");}

	get rightSplitter() { return <SlSplitPanel>this.shadowRoot.querySelector(".egw_fw_app__innerSplit");}

	get iframe() { return <HTMLIFrameElement>this.shadowRoot.querySelector("iframe");}

	get filters() { return <Et2Filterbox>this.shadowRoot.querySelector(".egw_fw_app__filter");}


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

	constructor()
	{
		super();
		this.handleTabHide = this.handleTabHide.bind(this);
		this.handleTabShow = this.handleTabShow.bind(this);
	}
	connectedCallback()
	{
		super.connectedCallback();

		// Get size preferences
		// @ts-ignore preference() takes _callback = true
		this.egw.preference(this.leftPanelInfo.preference, this.appName, true).then((value) =>
		{
			this.leftPanelInfo.preferenceWidth = value;
		});
		// @ts-ignore preference() takes _callback = true
		this.egw.preference(this.rightPanelInfo.preference, this.appName, true).then((value) =>
		{
			this.rightPanelInfo.preferenceWidth = value;
		});
		this.addEventListener("load", this.handleEtemplateLoad);
		this.addEventListener("clear", this.handleEtemplateClear);

		// Work around sl-split-panel resizing to 0 when app is hidden
		this.framework.addEventListener("sl-tab-hide", this.handleTabHide);
		this.framework.addEventListener("sl-tab-show", this.handleTabShow);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("load", this.handleEtemplateLoad);
		this.framework?.removeEventListener("sl-tab-hide", this.handleTabHide);
		this.framework?.removeEventListener("sl-tab-show", this.handleTabShow);

		try
		{
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
			// Destroy application js
			if(window.app[this.name] && window.app[this.name].destroy)
			{
				window.app[this.name].destroy();
				delete window.app[this.name];	// really delete it, so new object get constructed and registered for push
			}
		}
		catch(e)
		{
			this.egw.debug("error", "Error closing application", e);
		}
	}

	firstUpdated()
	{
		this.load(this.url);
	}

	protected async getUpdateComplete() : Promise<boolean>
	{
		const result = await super.getUpdateComplete();
		await Promise.allSettled([
			this.loadingPromise,
			customElements.whenDefined("sl-split-panel"),
			this.leftSplitter.updateComplete,
			this.rightSplitter.updateComplete
		]);
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
		if(window.app[this.name]?.linkHandler && this.egw.window.app[this.name].linkHandler(url))
		{
			// app.ts linkHandler handled it.
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
		this.loading = true;
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
					const items = (<HTMLElement[]>(Array.isArray(data) ? data : [data]))
						.filter(data => (typeof data.DOMNodeID == "string" && document.querySelector("[id='" + data.DOMNodeID + "']") == null));

					render(html`${repeat(items, i => i.DOMNodeID, (item) => html`
                        <div id="${item.DOMNodeID}"></div>`)}`, this);
				}

				// Might have just slotted aside content, hasSlotController will requestUpdate()
				// but we need to do it anyway for translation
				this.requestUpdate();
			}).finally(() =>
			{
				this.loading = false;
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
			}).finally(() =>
			{
				this.loading = false;
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

		if(this.features?.favorites || this._sideboxData?.some(s => s.title == "Favorites" || s.title == this.egw.lang("favorites")))
		{
			this.features.favorites = true;
			// This might be a little late, but close enough for rendering
			Favorite.load(this.egw, this.name).then((favorites) =>
			{
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

	/**
	 * Refresh given application by refreshing etemplates, or reloading URL
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string|number|undefined} _id id of entry to refresh
	 * @param {string|undefined} _type either 'edit', 'delete', 'add' or undefined
	 */
	public refresh(_msg, _id, _type)
	{
		if(this.useIframe)
		{
			this.querySelector("iframe").contentWindow.location.reload();
			return this.querySelector("iframe").contentWindow;
		}
		this.loading = true;

		// Refresh all child etemplates
		const etemplates = {};
		let refresh_done = false;
		this.querySelectorAll(":scope > div > et2-template").forEach((t : Et2Template) =>
		{
			etemplates[t.getInstanceManager().uniqueId] = t.getInstanceManager();
		})
		Object.values(etemplates).forEach((etemplate) =>
		{
			refresh_done = etemplate.refresh(_msg, this.appName, _id, _type) || refresh_done;
		});

		// if not trigger a full app refresh
		if(!refresh_done)
		{
			this.load(false);
			this.load(this.url + (_msg ? '&msg=' + encodeURIComponent(_msg) : ''));
		}
		else
		{
			this.loading = false;
		}
	}

	public async print()
	{

		let template;
		let deferred = [];
		let et2_list = [];
		const appWindow = this.framework.egw.window;

		// @ts-ignore that etemplate2 doesn't exist
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
				// @ts-ignore etemplate2 doesn't exist
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
				this.egw.loading_prompt(this.name, true, this.egw.lang('please wait...'), this, egwIsMobile() ? 'horizontal' : 'spinner');

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
		this.ignoreSplitterResize = true;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].preferenceWidth || this[`${side}PanelInfo`].defaultWidth;
		return this.updateComplete.then(() => this.ignoreSplitterResize = false);
	}

	protected hideSide(side : "left" | "right")
	{
		const attribute = `${side}Collapsed`;
		const oldValue = this[attribute];
		this[attribute] = true;
		this.ignoreSplitterResize = true;
		this[`${side}Splitter`].position = this[`${side}PanelInfo`].hiddenWidth;
		this.requestUpdate(attribute, oldValue);
		return this.updateComplete.then(() => this.ignoreSplitterResize = false);
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

	get nextmatch() : et2_nextmatch
	{
		// Look for a nextmatch by finding the DOM node by CSS class
		let nm = null;
		this.querySelectorAll(".et2_nextmatch").forEach((nm_div : HTMLElement) =>
		{
			const template = (<Et2Template>nm_div.closest("et2-template"));
			const widget_id = nm_div.id.replace(template.getInstanceManager().uniqueId + "_", "");
			nm = template.getWidgetById(widget_id);
		})
		return nm;
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
		if(slottedTemplates.length > 0 || slottedWidgets.length > 0 || this.nextmatch)
		{
			this.requestUpdate();
		}
	}

	/**
	 * An etemplate has been cleared
	 * Clear any references & clean up
	 */
	protected handleEtemplateClear(event)
	{
		if(this.nextmatch && this.nextmatch.getInstanceManager().DOMContainer === event.target)
		{
			this.filters.nextmatch = null;
			this.requestUpdate("nextmatch");
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
		event.stopPropagation();

		// Skip if there's no panelInfo - event is from the wrong place
		// Skip if loading, or not active to avoid keeping changes while user is not interacting
		if(typeof event.target?.dataset.panel == "undefined" || this.ignoreSplitterResize || this.loading || !this.hasAttribute("active"))
		{
			return;
		}
		const split = event.target;
		let panelInfo = this[split.dataset.panel];
		if(this[`${panelInfo.side}Collapsed`])
		{
			// It's collapsed, it doesn't move
			split.position = panelInfo.hiddenWidth;
			return;
		}

		// Left side is in pixels, round to 2 decimals
		let newPosition = Math.round(panelInfo.side == "left" ? split.positionInPixels * 100 : Math.min(100, split.position) * 100) / 100;
		if(isNaN(newPosition))
		{
			return;
		}

		await split.updateComplete;
		
		// Limit to maximum of actual width, splitter handles max
		if(panelInfo.side == "left")
		{
			newPosition = Math.min(newPosition, parseInt(getComputedStyle(split).gridTemplateColumns.split(" ").shift()));
		}

		// Update collapsed
		this[`${panelInfo.side}Collapsed`] = newPosition == panelInfo.hiddenWidth;

		let preferenceName = panelInfo.preference;

		// Send it out with details, in case anyone cares
		this.dispatchEvent(new CustomEvent("sl-reposition", {
				detail: {
					name: this.name,
					side: panelInfo.side,
					preference: preferenceName,
					width: newPosition,
				},
				bubbles: true,
				composed: true,
			}
		));

		// Delay preference update & etemplate resize because they're expensive
		if(!this[`${panelInfo.side}Collapsed`] && newPosition != panelInfo.preferenceWidth)
		{
			if(panelInfo.resizeTimeout)
			{
				window.clearTimeout(panelInfo.resizeTimeout);
			}
			panelInfo.resizeTimeout = window.setTimeout(() =>
			{
				console.log(`Panel resize: ${this.name} ${panelInfo.side} ${panelInfo.preferenceWidth} -> ${newPosition}`);
				panelInfo.preferenceWidth = newPosition;
				this.egw.set_preference(this.name, preferenceName, newPosition);

				// Tell etemplates to resize
				this.querySelectorAll(":scope > [id]").forEach(e =>
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
		return this.egw.open_link(event.target.dataset.link);
	}

	/**
	 * Framework's hidden this tab
	 *
	 * @param event
	 * @return {Promise<void>}
	 * @protected
	 */
	protected async handleTabHide(event)
	{
		// Only interested in this tab
		if(event.detail.name !== this.name)
		{
			return;
		}

		// Stop splitter from resizing while app is not active
		this.ignoreSplitterResize = true;
		this.rightSplitter.position = this.rightCollapsed ? this.rightPanelInfo.hiddenWidth : parseInt(<string>this.rightPanelInfo.preferenceWidth);
	}

	/**
	 * Framework has shown a tab
	 *
	 * @param event
	 * @protected
	 */
	protected async handleTabShow(event)
	{
		// Only interested in this tab
		if(event.detail.name !== this.name)
		{
			return;
		}

		// Fix splitter if it has moved while hidden
		if(this.rightSplitter && (this.rightSplitter.position !== this.rightPanelInfo.preferenceWidth || this.rightCollapsed && this.rightSplitter.position != this.rightPanelInfo.hiddenWidth))
		{
			await this.updateComplete;
			window.setTimeout(() =>
			{
				this.rightSplitter.position = this.rightCollapsed ? this.rightPanelInfo.hiddenWidth : parseInt(<string>this.rightPanelInfo.preferenceWidth);
				this.ignoreSplitterResize = false;
			}, 0);
		}
	}

	protected handleAppMenuClick(event)
	{
		// @ts-ignore
		return egw_link_handler(`/egroupware/index.php?menuaction=admin.admin_ui.index&load=admin.uiconfig.index&appname=${this.name}&ajax=true`, 'admin');
	}

	/**
	 * Displayed for the time between when the application is added and when the server responds with content
	 *
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	protected _loadingTemplate(slot = null)
	{
		// Don't show loader for iframe, it will not resolve
		if(this.useIframe)
		{
			return nothing;
		}

		return html`
            <div class="egw_fw_app__loading" slot=${slot || nothing}>
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
		let favorites : symbol | TemplateResult = nothing;
		if(this.features?.favorites)
		{
			favorites = html`${until(Favorite.load(this.egw, this.name).then((favorites) =>
			{
				// Add favorite menu to sidebox
				const favSidebox = this._sideboxData?.find(s => s.title.toLowerCase() == "favorites" || s.title == this.egw.lang("favorites"));
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
			}), nothing)}`;
		}
		return html`
            <slot name="left-top"></slot>
            ${favorites}
		`
	}

	/**
	 * Top right header, contains application action buttons (reload, print, config)
	 * @returns {TemplateResult<1>}
	 * @protected
	 */
	protected _rightHeaderTemplate()
	{
		return html`
            ${this._filterButtonTemplate()}
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
                    <sl-icon-button name="chevron-double-down"
                                    aria-label="${this.egw.lang("Application menu")}"
                    ></sl-icon-button>
                </div>
                <sl-menu part="app-menu">
                    ${!this.egw.user('apps')['preferences'] || !this.features.preferences ? nothing : html`
                        <sl-menu-item
                                @click=${() => this.egw.show_preferences('prefs', [this.name])}
                        >
                            <sl-icon slot="prefix" name="gear"></sl-icon>
                            ${this.egw.lang("Preferences")}
                        </sl-menu-item>
                    `}
                    ${!this.egw.user('apps')['preferences'] || !this.features.aclRights ? nothing : html`
                        <sl-menu-item
                                @click=${() => this.egw.show_preferences('acl', [this.name])}
                        >
                            <sl-icon slot="prefix" name="lock"></sl-icon>
                            ${this.egw.lang("Access")}
                        </sl-menu-item>
                    `}
                    ${!this.egw.user('apps')['preferences'] || !this.features.categories ? nothing : html`
                        <sl-menu-item
                                @click=${() => this.egw.show_preferences('cats', [this.name])}
                        >
                            <sl-icon slot="prefix" name="tag"></sl-icon>
                            ${this.egw.lang("Cateogries")}
                        </sl-menu-item>
                    `}
                    ${!this.features.favorites ? nothing : html`
                        <sl-menu-item>
                            <sl-icon slot="prefix" name="star"></sl-icon>
                            ${this.egw.lang("Favorites")}
                            <et2-favorites-menu slot="submenu" application="${this.name}"></et2-favorites-menu>
                        </sl-menu-item>`
                    }
                    ${this._applicationMenuTemplate()}
                </sl-menu>
            </sl-dropdown>
		`;
	}

	protected _filterButtonTemplate()
	{
		if(!this.nextmatch && !this.hasSlotController.test("filter"))
		{
			return nothing;
		}
		return html`
            <et2-button-icon nosubmit name="filter-circle"
                             label=${this.egw.lang("Filters")}
                             statustext=${this.egw.lang("Filter the list entries")}
                             @click=${() =>
                             {
                                 const filter = this.shadowRoot.querySelector("[part=filter]") ??
                                         this.querySelector("et2-filterbox").parentElement;
                                 filter.open = !filter.open;
                             }}
            ></et2-button-icon>`;
	}

	protected _filterTemplate()
	{
		if(!this.nextmatch && !this.hasSlotController.test("filter"))
		{
			return nothing;
		}

		return html`
            <sl-drawer part="filter"
                       exportparts="panel:filter__panel "
                       class="egw_fw_app__filter_drawer"
                       label=${this.egw.lang("Filters")} contained
            >
                <et2-button-icon slot="header-actions" name="selectcols"
                                 label=${this.egw.lang("Select columns")}
                                 statustext=${this.egw.lang("Select columns")}
                                 @click=${e => {this.nextmatch._selectColumnsClick(e)}} nosubmit>
                </et2-button-icon>

                <et2-filterbox
                        exportparts="filters"
                        class="egw_fw_app__filter"
                        autoapply
                        .nextmatch=${this.nextmatch} originalwidgets="replace"
                        @change=${e => e.preventDefault()}
                >
                    ${this.hasSlotController.test("filter") ? html`
                        <slot name="filter"></slot>` : nothing}
                </et2-filterbox>
                <et2-button slot="footer" label="Apply" nosubmit
                            @click=${e => this.filters.applyFilters()}
                >
                </et2-button>
            </sl-drawer>`;
	}

	/**
	 * This is the application's "Menu" in the top-right corner.
	 * Most of what was in the sidebox now goes here.
	 *
	 * @returns {TemplateResult<1> | typeof nothing }
	 * @protected
	 */
	protected _applicationMenuTemplate()
	{
		if(!this._sideboxData)
		{
			return nothing;
		}

		return html`${repeat(this._sideboxData, (menu) => menu['menu_name'], this._applicationMenuItemTemplate.bind(this))}`;
	}

	/**
	 * Template for a single item (top level) in the application menu
	 *
	 * @param menu
	 * @return {TemplateResult<1> | typeof nothing}
	 */
	_applicationMenuItemTemplate(menu)
	{
		// No favorites here
		if(menu["title"] == "Favorites" || menu["title"] == this.egw.lang("favorites"))
		{
			return nothing;
		}
		// Just one thing, don't bother with submenu
		if(menu["entries"].length == 1)
		{
			if(["--", "<hr />"].includes(menu["entries"][0]["lang_item"]))
			{
				return this._applicationSubMenuItemTemplate({...menu["entries"][0], lang_item: '<hr />'});
			}
			else
			{
				return this._applicationSubMenuItemTemplate({...menu["entries"][0], lang_item: menu["title"]})
			}
		}
		return html`
            <sl-menu-item>
                ${menu["title"]}
                <sl-menu slot="submenu">
                    ${repeat(menu["entries"], (entry) =>
                    {
                        return this._applicationSubMenuItemTemplate(entry);
                    })}
                </sl-menu>
            </sl-menu-item>`;
	}

	/**
	 * An individual sub-item in the application menu
	 * @param item
	 * @returns {TemplateResult<1>}
	 */
	_applicationSubMenuItemTemplate(item)
	{
		if(item["lang_item"] == "<hr />")
		{
			return html`
                <sl-divider></sl-divider>`;
		}
		let icon : symbol | TemplateResult<1> = nothing;
		if(typeof item["icon"] == "string" && (item["icon"].includes("://") || this.egw.image(item["icon"], this.appName)))
		{
			icon = html`
                <et2-image src=${item["icon"]} slot="prefix"></et2-image>`;
		}
		return html`
            <sl-menu-item
                    ?disabled=${!item["item_link"]}
                    data-link=${item["item_link"]}
                    @click=${this.handleSideboxMenuClick}
            >
                ${icon}
                ${item["lang_item"]}
            </sl-menu-item>`;

	}

	render()
	{
		const hasLeftSlots = this.hasSideContent("left") || this.features?.favorites;
		const hasRightSlots = this.hasSideContent("right");
		const hasHeaderContent = this.hasSlotController.test("main-header");

		const leftWidth = this.leftCollapsed || !hasLeftSlots ? this.leftPanelInfo.hiddenWidth :
						  this.leftPanelInfo.preferenceWidth;
		const rightWidth = this.rightCollapsed || !hasRightSlots ? this.rightPanelInfo.hiddenWidth :
						   this.rightPanelInfo.preferenceWidth;
		return html`
            <div class="egw_fw_app__header" part="app-header">
                <div class=${classMap({
                    egw_fw_app__name: true,
                    hasHeaderContent: hasHeaderContent,
                })} part="name">
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
                ${this._filterTemplate()}
                <sl-split-panel class=${classMap({"egw_fw_app__outerSplit": true, "no-content": !hasLeftSlots})}
                                primary="start" position-in-pixels="${leftWidth}"
                                snap="0px 20%" snap-threshold="50"
                                data-panel="leftPanelInfo"
                                @sl-reposition=${this.handleSlide}
                >
                    <sl-icon slot="divider" name="grip-vertical" @dblclick=${this.hideLeft}></sl-icon>
                    ${this._asideTemplate("start", "left", this.egw.lang("Sidebox"))}
                    <sl-split-panel slot="end"
                                    class=${classMap({
                                        "egw_fw_app__innerSplit": true,
                                        "no-content": !hasRightSlots
                                    })}
                                    primary="start"
                                    position=${rightWidth} snap="50% 80% 100%"
                                    snap-threshold="50"
                                    data-panel="rightPanelInfo"
                                    @sl-reposition=${this.handleSlide}
                    >
                        ${this.loading ? this._loadingTemplate("start") : html`
                            ${this.rightCollapsed ? nothing : html`
                                <sl-icon slot="divider" name="grip-vertical" @dblclick=${this.hideRight}></sl-icon>`
                            }
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
                        `}
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