import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import type {Et2Filterbox} from "../../Et2Filterbox/Et2Filterbox";
import type {SlDrawer} from "@shoelace-style/shoelace";

/**
 * @summary Minimal application box layout for etemplate templates
 *
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
	static styles = css`
		:host {
			display: block;
			height: 100%;
		}

		.et2_appbox {
			display: flex;
			flex-direction: column;
			height: 100%;
			min-height: 0;
		}

		.et2_appbox__header {
			display: grid;
			grid-template-columns: minmax(0, 1fr) auto;
			gap: var(--sl-spacing-x-small, 0.25rem);
			align-items: center;
		}

		.et2_appbox__body {
			display: grid;
			grid-template-columns: minmax(0, auto) minmax(0, 1fr) minmax(0, auto);
			flex: 1 1 auto;
			min-height: 0;
		}

		.et2_appbox__filter {
			position: relative;
			z-index: 0;
			min-height: 0;
		}

		.et2_appbox__filter slot[name="filter"]::slotted(*) {
			inset: auto !important;
			display: block;
			max-width: 100%;
		}

		.et2_appbox__side {
			min-width: 0;
		}

		.et2_appbox__center {
			display: flex;
			flex-direction: column;
			min-width: 0;
			min-height: 0;
		}

		.et2_appbox__content {
			flex: 1 1 auto;
			min-height: 0;
		}
	`;

	@property()
	name = "";

	@property({reflect: true})
	rowCount = "";

	/**
	 * Function property to access framework
	 */
	@property({type: Function})
	getFramework = () => this.closest("egw-framework");

	/**
	 * A function that provides icon + tooltip for the filter button.
	 */
	@property({type: Function})
	getFilterInfo = (filterValues : { [id : string] : string | { value : any } }, _fwApp : Et2AppBox) : FilterInfo =>
	{
		return this.filterInfo(filterValues);
	};

	get filters() : Et2Filterbox
	{
		return <Et2Filterbox>this.querySelector("et2-filterbox:not([hidden],[disabled])");
	}

	get filtersDrawer() : SlDrawer
	{
		return <SlDrawer>this.shadowRoot?.querySelector(".egw_fw_app__filter_drawer");
	}

	public filterInfo(filterValues : { [id : string] : any } = {}) : FilterInfo
	{
		const info : FilterInfo = {
			icon: "filter-circle",
			tooltip: this.egw().lang("Filters")
		};

		// Don't consider sort as a filter.
		delete filterValues["sort"];

		const emptyFilter = (v : any) => typeof v == "object" ? Object.values(v).filter(emptyFilter).length : v;
		if(Object.values(filterValues).filter(emptyFilter).length !== 0)
		{
			info.icon = "filter-circle-fill";
		}
		return info;
	}

	protected _filterButtonTemplate()
	{
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

	protected _toggleFilterDrawer()
	{
		if(this.filtersDrawer)
		{
			this.filtersDrawer.open = !this.filtersDrawer.open;
		}
	}

	render()
	{
		return html`
            <div class="et2_appbox">
                <div class="et2_appbox__header" part="app-header">
                    <header part="header">
                        <slot name="main-header"></slot>
                    </header>
                    <div part="name">
                        ${this._filterButtonTemplate()}
                        <slot name="header-actions"></slot>
                    </div>
                </div>
                <sl-drawer class="et2_appbox__filter egw_fw_app__filter_drawer" part="filter"
                           exportparts="panel:filter__panel">
                    <slot name="filter"></slot>
                </sl-drawer>
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

export type FilterInfo = {
	icon : string,
	tooltip : string
}
