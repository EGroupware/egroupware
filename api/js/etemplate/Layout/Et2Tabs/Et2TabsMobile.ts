import {Et2Tabs} from "./Et2Tabs";
import {html, TemplateResult} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import {Et2Details} from "../Et2Details/Et2Details";
import {SlTab, SlTabPanel} from "@shoelace-style/shoelace";

/**
 * Widget to render tabs in a mobile-friendly way
 *
 * We render tabs as a series of details instead of normal tabs.
 * loadWebComponent() will load this component instead of Et2Tabs on mobile browsers
 */
export class Et2TabsMobile extends Et2Tabs
{
	connectedCallback()
	{
		super.connectedCallback();
	}

	protected createTabs(tabData)
	{
		// "Tabs" are created in render()
		this.tabData = tabData;

		// Create tab panels here though
		tabData.forEach((tab, index) =>
		{
			let panel = this.createPanel(tab, true);
			panel.slot = tab.id;
		});
	}

	getAllTabs(includeDisabled = false)
	{
		const slot = <Et2Details[]><unknown>this.shadowRoot.querySelectorAll('et2-details');
		const tabNames = ["et2-details"];

		// It's really not a list of SlTab...
		return <SlTab[]><unknown>[...slot].filter((el) =>
		{
			return includeDisabled ? tabNames.indexOf(el.tagName.toLowerCase()) != -1 : tabNames.indexOf(el.tagName.toLowerCase()) !== -1 && !el.disabled;
		});
	}

	getAllPanels()
	{
		const slot = this.querySelector('slot')!;
		return <[SlTabPanel]><unknown>[...this.querySelectorAll('et2-tab-panel')]
	}

	syncIndicator()
	{
		// Don't have an indicator to sync
	}

	repositionIndicator()
	{
		// Don't have an indicator to reposition
	}

	preventIndicatorTransition()
	{
		// Don't have an indicator
	}

	/**
	 * Reimplement to allow our existing function signatures too
	 *
	 * @deprecated use this.show(name : string)
	 * @param tab number or name of tab (Sl uses that internally with a SlTab!)
	 * @param options
	 */
	setActiveTab(tab : SlTab | String | Number, options? : {
		emitEvents? : boolean;
		scrollBehavior? : 'auto' | 'smooth';
	})
	{
		if(typeof tab === 'number')
		{
			tab = this.getAllTabs()[tab];
			return this.show(tab.panel);
		}
		if(typeof tab === 'string')
		{
			return this.show(tab);
		}
		// Don't call super, it hides tab content
	}


	get nav() : HTMLElement
	{
		return this.shadowRoot.querySelector("et2-vbox");
	}


	protected tabTemplate(tab, index : number) : TemplateResult
	{
		return html`
            <et2-details
                    id="${tab.id}"
                    summary="${tab.label}"
                    ?open=${index == this._selectedIndex}
                    ?disabled=${tab.disabled}
                    ?hidden=${tab.hidden}
            >
                <slot name="${tab.id}"/>
            </et2-details>`
	}

	render()
	{
		return html`
            <et2-vbox
                    part="base"
                    class=${classMap({
                        'tab-group': true,
                        'tab-group-mobile': true,
                        // Get styling as if it were top
                        'tab-group--top': true
                    })}
                    @click=${this.handleClick}
                    @keydown=${this.handleKeyDown}
            >
                ${repeat(this.tabData, this.tabTemplate.bind(this))}
                <slot>
            </et2-vbox>
		`;
	}
}

if(typeof customElements.get("et2-tabbox_mobile") == "undefined")
{
	customElements.define("et2-tabbox_mobile", Et2TabsMobile);
}