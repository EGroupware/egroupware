import {Et2Tabs} from "./Et2Tabs";
import {html, TemplateResult} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import {Et2Details} from "../Et2Details/Et2Details";
import {SlDetails, SlTab, SlTabPanel} from "@shoelace-style/shoelace";
import {et2_IPrint} from "../../et2_core_interfaces";
import type {Et2TabPanel} from "./Et2TabPanel";

/**
 * Widget to render tabs in a mobile-friendly way
 *
 * We render tabs as a series of details instead of normal tabs.
 * loadWebComponent() will load this component instead of Et2Tabs on mobile browsers
 */
export class Et2TabsMobile extends Et2Tabs
{
	/**
	 * Details opened by beforePrint(), to close again afterPrint()
	 */
	protected _printOpened : SlDetails[] = [];

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

	/**
	 * Use the height of the first tab if height not set
	 * @protected
	 */
	protected _sizeTabs(tabDates : Array<object>)
	{
		// no need to do anything, as we use details
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

	set value(tab)
	{
		super.value = tab;
	}

	get value()
	{
		return this.tabs.find(el => el.open)?.getAttribute("id")
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


	/**
	 * Set up for printing
	 *
	 * Every panel is already active, it sits inside its tab's details, so open all details that are not
	 * hidden.  Opening animates, so widgets in details that were closed are not visible yet when
	 * etemplate2.print() checks them right after this returns, and get skipped there.  Once the details
	 * are open we prepare those widgets for printing ourselves, the same way etemplate2.print() does.
	 *
	 * @return {Promise} resolves when all details are open and their widgets are ready for printing
	 */
	async beforePrint()
	{
		this._printOpened = (<SlDetails[]><unknown>this.getAllTabs()).filter(details => !details.hidden && !details.open);
		await Promise.all(this._printOpened.map(details => details.show()));

		const instanceManager = this.getInstanceManager();
		const panels = <Et2TabPanel[]><unknown>this.getAllPanels();
		const deferred = [];
		this._printOpened.forEach(details =>
		{
			panels.find(panel => panel.slot == details.getAttribute("id"))?.iterateOver(widget =>
			{
				// Skip widgets from a different etemplate, and hidden widgets
				const node = widget.getDOMNode?.() ?? widget;
				if(widget.getInstanceManager() != instanceManager ||
					!node || !(node.offsetWidth || node.offsetHeight || node.getClientRects().length))
				{
					return;
				}
				const result = widget.beforePrint();
				if(result && typeof result == "object")
				{
					deferred.push(result);
				}
			}, this, et2_IPrint);
		});
		await Promise.all(deferred);
	}

	/**
	 * Reset after printing
	 *
	 * Close the details beforePrint() opened
	 */
	afterPrint()
	{
		this._printOpened.forEach(details => details.hide());
		this._printOpened = [];
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