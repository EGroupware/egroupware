import {Et2Tabs} from "./Et2Tabs";
import {classMap, html, repeat, TemplateResult} from "@lion/core";
import {et2_createWidget} from "../../et2_core_widget";
import {et2_template} from "../../et2_widget_template";
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
		this.nav = this.shadowRoot.querySelector("et2-vbox");
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

	protected tabTemplate(tab, index : number) : TemplateResult
	{
		if(tab.XMLNode)
		{
			// Just read the XMLNode
			let tabContent = this.createElementFromNode(tab.XMLNode);
			tabContent.getDOMNode().slot = tab.id;
		}
		else
		{
			let template = <et2_template>et2_createWidget('template', tab.widget_options, this);
			template.getDOMNode().slot = tab.id;
		}
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