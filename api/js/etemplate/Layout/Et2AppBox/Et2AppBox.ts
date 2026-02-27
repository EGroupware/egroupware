import type {TemplateResult} from "lit";
import {html, LitElement, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import type {Et2Filterbox} from "../../Et2Filterbox/Et2Filterbox";
import type {et2_nextmatch} from "../../et2_extension_nextmatch";
import type {Et2Template} from "../../Et2Template/Et2Template";
import {etemplate2} from "../../etemplate2";
import {waitForEvent} from "../../Et2Widget/event";
import styles from "./Et2AppBox.styles";

type FilterInfo = {
	icon : string,
	tooltip : string
};

/**
 * @summary Minimal application layout component for inside etemplate templates
 * @deprecated Use `egw-app` / `EgwFrameworkApp` for complete framework behavior.
 *
 * @slot - Main application content.
 * @slot main-header - Top of app. Contains logo, application toolbar, search box etc.
 * @slot header-actions - Top right. Filter, refresh, print and menu controls.
 * @slot filter - Custom filter panel content.
 * @slot header - Top of main content.
 * @slot footer - Bottom of main content.
 * @slot left - Optional content on the left for application navigation.
 * @slot left-header - Top of left side.
 * @slot left-top - Between left-header and favourites.
 * @slot left-footer - Bottom of left side.
 * @slot right - Optional content on the right for application details.
 * @slot right-header - Top of right side.
 * @slot right-footer - Bottom of right side.
 */
@customElement("et2-app-box")
export class Et2AppBox extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			styles
		];
	}

	/**
	 * Application name.  Must be the internal name of an application, used for preferences & settings
	 */
	@property({reflect: true})
	name = "Application name";

	/**
	 * URL to load.
	 */
	@property()
	url = "";

	/**
	 * Current number of rows or records being shown.
	 */
	@property({reflect: true})
	rowCount = "";

	/**
	 * Component is currently loading.
	 */
	@property({type: Boolean, reflect: true})
	loading = false;

	/**
	 * Used to specify how this component can find the framework.
	 */
	@property({type: Function})
	getFramework = () => this.closest("egw-framework");

	/**
	 * Used to specify how this component can find its current / active nextmatch.
	 */
	@property({type: Function})
	getNextmatch : () => et2_nextmatch = () : et2_nextmatch =>
	{
		let nm = null;
		const nmDiv = this.querySelector(".et2_nextmatch");
		if(nmDiv)
		{
			const template = <Et2Template>nmDiv.closest("et2-template");
			const widgetId = nmDiv.id.replace(template?.getInstanceManager().uniqueId + "_", "");
			nm = template.getWidgetById(widgetId);
		}
		return nm;
	};

	/**
	 * Information for filter button and filter drawer header.
	 */
	@property({type: Function})
	getFilterInfo = (filterValues : { [id : string] : any }, _app : Et2AppBox) : FilterInfo =>
	{
		return this.filterInfo(filterValues);
	};

	@state()
	protected loadingPromise : Promise<void> = Promise.resolve();

	protected useIframe = false;

	constructor()
	{
		super();
		this.handleEtemplateLoad = this.handleEtemplateLoad.bind(this);
		this.handleEtemplateClear = this.handleEtemplateClear.bind(this);
		this.handleSearchResults = this.handleSearchResults.bind(this);
		this.handleShow = this.handleShow.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.addEventListener("load", this.handleEtemplateLoad);
		this.addEventListener("clear", this.handleEtemplateClear);
		this.addEventListener("et2-search-result", this.handleSearchResults);
		this.addEventListener("et2-show", this.handleShow);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("load", this.handleEtemplateLoad);
		this.removeEventListener("clear", this.handleEtemplateClear);
		this.removeEventListener("et2-search-result", this.handleSearchResults);
		this.removeEventListener("et2-show", this.handleShow);
	}

	firstUpdated()
	{
		this.load(this.url);
	}

	protected async getUpdateComplete() : Promise<boolean>
	{
		const result = await super.getUpdateComplete();
		await Promise.allSettled([
			this.loadingPromise
		]);
		return result
	}

	get framework() : any
	{
		return this.getFramework();
	}

	get appName() : string
	{
		return this.name;
	}

	get nextmatch() : et2_nextmatch
	{
		return this.getNextmatch();
	}

	get filters() : Et2Filterbox
	{
		return <Et2Filterbox>this.querySelector("et2-filterbox:not([hidden],[disabled])");
	}

	get filtersDrawer() : any
	{
		return this.shadowRoot?.querySelector(".egw_fw_app__filter_drawer");
	}

	public filterInfo(filterValues : { [id : string] : any } = {}) : FilterInfo
	{
		const info : FilterInfo = {
			icon: "filter-circle",
			tooltip: this.egw().lang("Filters") + ": " + (this.rowCount || "0")
		};

		// Don't consider sort as a filter.
		delete filterValues.sort;

		const emptyFilter = (v : any) => typeof v == "object" ? Object.values(v).filter(emptyFilter).length : v;
		if(Object.values(filterValues).filter(emptyFilter).length !== 0)
		{
			info.icon = "filter-circle-fill";
		}
		return info;
	}

	public load(url : string)
	{
		if(window.app[this.name]?.linkHandler && this.egw().window.app[this.name].linkHandler(url))
		{
			return;
		}

		Array.from(this.children).forEach(n =>
		{
			etemplate2.getById((<HTMLElement>n).id)?.clear();
			n.remove();
		});
		if(!url)
		{
			return;
		}

		let targetUrl = "";
		this.useIframe = true;
		const matches = url.match(/\/index.php\?menuaction=([A-Za-z0-9_\.]*.*[&?]ajax=[^&]+.*)/);
		if(matches)
		{
			targetUrl = "index.php?menuaction=" + matches[1];
			this.useIframe = false;
		}

		this.loading = true;
		if(!this.useIframe)
		{
			this.loadingPromise = this.egw().request(
				this.framework.getMenuaction("ajax_exec", targetUrl, this.name),
				[targetUrl]
			).then((data : string | string[]) =>
			{
				if(!data)
				{
					return;
				}
				// Can't have nested form elements, use a div
				this.innerHTML = (<string[]>data).join("").replace('form', 'div');
				this.requestUpdate();
				return this.waitForLoad(Array.from(this.querySelectorAll("[id]")) as HTMLElement[]);
			}) as Promise<void>;
		}
		else
		{
			this.loadingPromise = new Promise<void>((resolve) =>
			{
				this.append(this._createIframeNodes(url));
				this.requestUpdate();
				resolve();
				return this.waitForLoad(Array.from(this.querySelectorAll("iframe")) as HTMLElement[]);
			});
		}

		this.loadingPromise.finally(() =>
		{
			this.loading = false;
		});
		return this.loadingPromise;
	}

	protected waitForLoad(nodes : HTMLElement[]) : Promise<void>
	{
		let timeout = null;
		const loadTimeoutPromise = new Promise<void>((resolve) =>
		{
			timeout = setTimeout(() =>
			{
				console.warn(this.name + " loading timeout", this);
				resolve();
			}, 10000);
		});
		const loadPromises = nodes.map((node) =>
		{
			if(node.localName === "iframe")
			{
				return new Promise<void>((resolve) =>
				{
					const interval = setInterval(() =>
					{
						if((<HTMLIFrameElement>node).contentDocument?.body?.innerHTML !== "")
						{
							clearInterval(interval);
							resolve();
						}
					}, 500);
				});
			}
			return waitForEvent(node, "load") as Promise<void>;
		});

		return Promise.race([
			Promise.allSettled(loadPromises).finally(() => clearTimeout(timeout)).then(() => undefined),
			loadTimeoutPromise
		]);
	}

	public refresh(_msg, _id, _type)
	{
		this.loading = true;
		if(typeof _msg !== "string")
		{
			_msg = "";
		}

		let refreshDone = false;
		this.querySelectorAll(":scope > [id]").forEach((t : HTMLElement) =>
		{
			refreshDone = etemplate2.getById(t.id)?.refresh(_msg, this.appName, _id, _type) || refreshDone;
		});

		if(!refreshDone)
		{
			this.load(this.url + (_msg ? "&msg=" + encodeURIComponent(_msg) : ""));
		}
		else
		{
			this.loading = false;
		}
	}

	public setSidebox(_sideboxData, _hash?)
	{
		// Intentionally ignored in Et2AppBox.
	}

	public showLeft()
	{
		return Promise.resolve();
	}

	public hideLeft()
	{
		return Promise.resolve();
	}

	public showRight()
	{
		return Promise.resolve();
	}

	public hideRight()
	{
		return Promise.resolve();
	}

	protected handleEtemplateLoad(event)
	{
		const etemplate = etemplate2.getById(event.target.id);
		if(!etemplate || !event.composedPath().includes(this))
		{
			return;
		}

		const slottedTemplates = etemplate.DOMContainer.querySelectorAll(":scope > [slot]");
		if(slottedTemplates.length == 1 && etemplate.DOMContainer.childElementCount == 1)
		{
			etemplate.DOMContainer.slot = (<HTMLElement>slottedTemplates[0]).slot;
		}
		else
		{
			slottedTemplates.forEach(node => {this.appendChild(node);});
		}
		if(slottedTemplates.length > 0 || this.nextmatch)
		{
			this.requestUpdate();
		}
	}

	protected handleEtemplateClear(event)
	{
		if(this.nextmatch && this.nextmatch.getInstanceManager().DOMContainer === event.target)
		{
			if(this.filters)
			{
				this.filters.nextmatch = null;
			}
			this.requestUpdate("nextmatch");
		}
	}

	protected handleShow(event)
	{
		const detail = event.detail;
		if(detail?.controller?.getTotalCount)
		{
			this.rowCount = detail.controller.getTotalCount();
		}
	}

	protected handleSearchResults(event)
	{
		if(event.detail?.nextmatch == this.nextmatch && !event.defaultPrevented)
		{
			this.rowCount = event.detail?.total ?? "";
		}
	}

	protected _createIframeNodes(url? : string)
	{
		if(!this.useIframe)
		{
			return null;
		}
		return Object.assign(document.createElement("iframe"), {src: url});
	}

	protected _loadingTemplate()
	{
		if(this.useIframe)
		{
			return nothing;
		}
		return html`
            <div class="egw_fw_app__loading">
                <sl-spinner part="spinner"></sl-spinner>
            </div>`;
	}

	protected _toggleFilterDrawer()
	{
		if(this.filtersDrawer)
		{
			this.filtersDrawer.open = !this.filtersDrawer.open;
		}
	}

	protected _filterButtonTemplate() : TemplateResult | symbol
	{
		if(!this.nextmatch && !this.querySelector("[slot='filter']"))
		{
			return nothing;
		}
		const info = this.getFilterInfo(this.filters?.value ?? {}, this);
		return html`
            <et2-button-icon nosubmit
                             name=${info.icon}
                             label=${this.egw().lang("Filters")}
                             statustext=${info.tooltip}
                             @click=${this._toggleFilterDrawer}
            ></et2-button-icon>
		`;
	}

	protected _rightHeaderTemplate()
	{
		return html`
            ${this._filterButtonTemplate()}
            <et2-button-icon nosubmit name="arrow-clockwise"
                             label=${this.egw().lang("Reload %1", this.egw().lang(this.name))}
                             statustext=${this.egw().lang("Reload %1", this.egw().lang(this.name))}
                             @click=${() => this.refresh("", undefined, undefined)}
            ></et2-button-icon>
            <slot name="header-actions"></slot>
		`;
	}

	protected _filterTemplate()
	{
		const info = this.getFilterInfo(this.filters?.value ?? {}, this);
		return html`
            <sl-drawer part="filter"
                       exportparts="panel:filter__panel"
                       class="egw_fw_app__filter_drawer"
                       label=${info.tooltip}
                       contained>
                <slot name="filter"></slot>
            </sl-drawer>
		`;
	}

	render()
	{
		return html`
            <div class="et2_appbox">
                <div class="et2_appbox__header" part="app-header">
                    <header part="header">
                        <slot name="main-header"></slot>
                    </header>
                    <div part="name">${this._rightHeaderTemplate()}</div>
                </div>
                ${this.loading ? this._loadingTemplate() : nothing}
                ${this._filterTemplate()}
                <div class="et2_appbox__body" part="main">
                    <aside class="et2_appbox__side" part="left">
                        <header part="content-header">
                            <slot name="left-header"></slot>
                        </header>
                        <div>
                            <slot name="left-top"></slot>
                        </div>
                        <div>
                            <slot name="left"></slot>
                        </div>
                        <footer>
                            <slot name="left-footer"></slot>
                        </footer>
                    </aside>
                    <section class="et2_appbox__center">
                        <header part="content-header">
                            <slot name="header"></slot>
                        </header>
                        <div class="et2_appbox__content" part="content">
                            <slot></slot>
                        </div>
                        <footer part="footer">
                            <slot name="footer"></slot>
                        </footer>
                    </section>
                    <aside class="et2_appbox__side" part="right">
                        <header part="content-header">
                            <slot name="right-header"></slot>
                        </header>
                        <div>
                            <slot name="right"></slot>
                        </div>
                        <footer>
                            <slot name="right-footer"></slot>
                        </footer>
                    </aside>
                </div>
            </div>
		`;
	}
}
