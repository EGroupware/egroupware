/**
 * EGroupware eTemplate2 - Box widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {SlTab, SlTabGroup, SlTabPanel} from "@shoelace-style/shoelace";
import {loadWebComponent} from "../../Et2Widget/Et2Widget";
import {et2_directChildrenByTagName, et2_filteredNodeIterator, et2_readAttrWithDefault} from "../../et2_core_xml";
import {css, PropertyValues} from "@lion/core";
import shoelace from "../../Styles/shoelace";
import {et2_createWidget} from "../../et2_core_widget";
import {colorsDefStyles} from "../../Styles/colorsDefStyles";
import {Et2InputWidget} from "../../Et2InputWidget/Et2InputWidget";
import {et2_IResizeable} from "../../et2_core_interfaces";


export class Et2Tabs extends Et2InputWidget(SlTabGroup) implements et2_IResizeable
{
	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			...shoelace,
			css`
			.tab-group--top {
				height: 100%;
				min-height: fit-content;
			}
			.tab-group__body {
				flex: 1 1 auto;
				overflow: hidden auto;
			}
			.tab-group__body-fixed-height {
				flex: 0 0 auto;
			}
			::slotted([hidden]) {
				display: none;
			}
			::slotted(et2-tab-panel) {
				flex: 1 1 auto;
			}
			::slotted(et2-tab-panel:not([active])) {
    			display: none;
			}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Array of [extra] tabs.
			 * Each tab needs {label:..., template:...}.
			 * Additional optional keys are prepend, hidden and id, for access into content array
			 */
			extraTabs: {type: Object},

			/**
			 * Add tabs to template
			 * Set to true if tabs specified in tabs property should be added to tabs read from template,
			 * default false if not which replaces what's in template
			 */
			addTabs: {type: Boolean},

			/**
			 * Set the height for tabs
			 * Leave unset to size automatically
			 */
			tabHeight: {type: String},

			/**
			 * @deprecated use "placement" instead
			 * @see https://shoelace.style/components/tab-group
			 */
			alignTabs: {type: String},

			/**
			 * active tab is the value
			 */
			value: {type: String}
		}
	}

	/**
	 * Index of currently selected tab
	 * @type {number}
	 * @private
	 */
	protected _selectedIndex = -1;
	protected tabData = [];
	protected lazyLoaded = false;

	constructor()
	{
		super();

		this.extraTabs = [];
		this.addTabs = false;
		if (egwIsMobile())
		{
			this.placement = 'end';
		}
	}

	get value()
	{
		return this.getActiveTab()?.panel;
	}

	set value(tab)
	{
		if (this.tabs && Array.isArray(this.tabs) && this.tabs.length)
		{
			this.show(this.__value = tab);
		}
		else
		{
			this.__value = tab;
		}
	}

	connectedCallback()
	{
		super.connectedCallback();

		this.updateComplete.then(() =>
		{
			if (this.__value)
			{
				this.show(this.__value);
			}
		});
	}

	isDirty(): boolean
	{
		return false;
	}

	loadFromXML(_node)
	{
		// Get the tabs and tabpanels tags
		const tabsElems = et2_directChildrenByTagName(_node, "tabs");
		const tabpanelsElems = et2_directChildrenByTagName(_node, "tabpanels");
		const tabData = [];

		// Check for a parent height, we'll apply it to tab panels
		var height = et2_readAttrWithDefault(_node.parentNode, "height", null);
		if(height && !this.tabHeight)
		{
			this.tabHeight = height;
		}

		// if no tabs set or they should be added to tabs from xml
		if(!this.extraTabs || this.extraTabs.length == 0 || this.addTabs)
		{
			if(tabsElems.length == 1 && tabpanelsElems.length == 1)
			{
				var tabs = tabsElems[0];
				var tabpanels = tabpanelsElems[0];

				// Parse the "tabs" tag
				this._readTabs(tabData, tabs);

				// Read and create the widgets defined in the "tabpanels"
				this._readTabPanels(tabData, tabpanels);
			}
			else
			{
				this.egw().debug("error", "Error while parsing tabbox, none or multiple tabs or tabpanels tags!", this);
			}
		}
		// Add in additional tabs
		if(this.extraTabs)
		{
			let readonly = this.getArrayMgr("readonlys").getEntry(this.id) || {};
			for(let i = 0; i < this.extraTabs.length; i++)
			{
				let tab = this.extraTabs[i];
				let tab_id = tab.id || tab.template;
				let tab_options = {id: tab_id, template: tab.template, url: tab.url, content: undefined};
				if(tab.id)
				{
					tab_options.content = tab.id;
				}
				tabData[tab.prepend ? 'unshift' : 'push'].call(tabData, {
					"id": tab_id,
					"label": this.egw().lang(tab.label),
					"widget": null,
					"widget_options": tab_options,
					"contentDiv": null,
					"flagDiv": null,
					"hidden": typeof tab.hidden != "undefined" ? tab.hidden : readonly[tab_id] || false,
					"XMLNode": null,
					"promise": null
				});
			}
		}

		// Create the tab DOM-Nodes
		this.createTabs(tabData);
	}

	_readTabs(tabData, tabs)
	{
		let selected = "";
		this._selectedIndex = -1;
		let hidden = {};
		if(this.id)
		{
			// Set the value for this element
			let contentMgr = this.getArrayMgr("content");
			if(contentMgr != null)
			{
				let val = contentMgr.getEntry(this.id);
				if(val !== null)
				{
					selected = val;
				}
			}
			contentMgr = this.getArrayMgr("readonlys");
			if(contentMgr != null)
			{
				let val = contentMgr.getEntry(this.id);
				if(val !== null && typeof val !== 'undefined')
				{
					hidden = val;
				}
			}
		}
		let i = 0;
		et2_filteredNodeIterator(tabs, function(node, nodeName)
		{
			if(nodeName == "tab")
			{
				const index_name = et2_readAttrWithDefault(node, "id", '');
				var hide = false;
				var widget_options = {};
				if(index_name)
				{
					if(selected == index_name)
					{
						this.selected_index = i;
					}
					if(hidden[index_name])
					{
						hide = true;
					}
					// Get the class attribute and add it as widget_options
					const classAttr = et2_readAttrWithDefault(node, "class", '');
					if(classAttr)
					{
						widget_options = {'class': classAttr};
					}
				}
				tabData.push({
					"id": index_name,
					"label": this.egw().lang(et2_readAttrWithDefault(node, "label", "Tab")),
					"widget": null,
					"widget_options": widget_options,
					"contentDiv": null,
					"flagDiv": null,
					"hidden": hide,
					"XMLNode": null,
					"promise": null
				});
			}
			else
			{
				throw("Error while parsing: Invalid tag '" + nodeName +
					"' in tabs tag");
			}
			i++;
		}, this);

		// Make sure we don't try to display a hidden tab
		for(let i = 0; i < tabData.length && this._selectedIndex < 0; i++)
		{
			if(!tabData[i].hidden)
			{
				this._selectedIndex = i;
			}
		}
	}

	_readTabPanels(tabData, tabpanels)
	{
		var i = 0;
		et2_filteredNodeIterator(tabpanels, function(node, nodeName)
		{
			if(i < tabData.length)
			{
				// Store node for later evaluation
				tabData[i].XMLNode = node;
			}
			else
			{
				throw("Error while reading tabpanels tag, too many widgets!");
			}
			i++;
		}, this);
	}

	protected update(changedProperties : PropertyValues)
	{
		super.update(changedProperties);
		if(changedProperties.has("tabHeight"))
		{
			const body = this.shadowRoot.querySelector(".tab-group__body");
			if(body)
			{
				body.style.setProperty("height", this.tabHeight == parseInt(this.tabHeight) + "" ? this.tabHeight + "px" : this.tabHeight);
				body.classList.toggle("tab-group__body-fixed-height", this.tabHeight !== '');
			}
		}
	}

	/**
	 * Create the nodes for tabs
	 *
	 * @param tabData
	 * @protected
	 */
	protected createTabs(tabData)
	{
		this.tabData = tabData;
		tabData.forEach((tab, index) =>
		{
			// Tab - SlTabGroup looks for sl-tab, so we can't use our own without overriding a lot
			tab.flagDiv = loadWebComponent("et2-tab", {
				slot: "nav",
				panel: tab.id,
				active: index == this._selectedIndex,
				hidden: tab.hidden
			}, this);

			// Set tab label
			tab.flagDiv.appendChild(document.createTextNode(tab.label));
		});
		tabData.forEach((tab, index) =>
		{
			this.createPanel(tab);
		});
	}

	protected createPanel(tab, active = false)
	{
		// Tab panel
		tab.contentDiv = loadWebComponent('et2-tab-panel', {
			id: tab.id,
			name: tab.id,
			active: active,
			hidden: tab.hidden,
			// Set readonly so it gets passed on to children
			readonly: tab.hidden
		}, this);

		// Tab content
		if(tab.XMLNode)
		{
			// Just read the XMLNode
			let tabContent = tab.contentDiv.createElementFromNode(tab.XMLNode);
			tab.contentDiv.appendChild(
				typeof window.customElements.get(tab.XMLNode.nodeName) == "undefined" ?
				tabContent.getDOMNode() : tabContent
			);
		}
		else
		{
			et2_createWidget('template', tab.widget_options, tab.contentDiv);
		}

		return tab.contentDiv;
	}

	getAllTabs(includeDisabled = false)
	{
		const slot = <HTMLSlotElement>this.shadowRoot.querySelector('slot[name="nav"]');
		const tabNames = ["sl-tab", "et2-tab"];
		return <SlTab[]>[...slot.assignedElements()].filter((el) =>
		{
			return includeDisabled ? tabNames.indexOf(el.tagName.toLowerCase()) != -1 : tabNames.indexOf(el.tagName.toLowerCase()) !== -1 && !el.disabled;
		});
	}

	getAllPanels()
	{
		const slot = this.body.querySelector('slot')!;
		return [...slot.assignedElements()].filter(el => ['et2-tab-panel', 'sl-tab-panel'].indexOf(el.tagName.toLowerCase()) != -1) as [SlTabPanel];
	}

	handleClick(event : MouseEvent)
	{
		const target = event.target as HTMLElement;
		const tab = target.closest('et2-tab');
		const tabGroup = tab?.closest('sl-tab-group') || tab?.closest('et2-tabbox');

		// Ensure the target tab is in this tab group
		if(tabGroup !== this)
		{
			return;
		}

		if(tab !== null)
		{
			this.setActiveTab(tab, {scrollBehavior: 'smooth'});
		}
	}

	/**
	 * Keyboard navigation
	 * Copy + Paste from parent due to tagname differences (sl-tab vs et2-tab)
	 *
	 * @param event
	 */
	handleKeyDown(event)
	{
		const target = event.target;
		const tab = target.closest("et2-tab");
		const tabGroup = tab == null ? void 0 : tab.closest("et2-tabbox");
		if(tabGroup !== this)
		{
			return;
		}
		if(["Enter", " "].includes(event.key))
		{
			if(tab !== null)
			{
				this.setActiveTab(tab, {scrollBehavior: "smooth"});
				event.preventDefault();
			}
		}
		if(["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown", "Home", "End"].includes(event.key))
		{
			const activeEl = document.activeElement;
			const isRtl = this.localize.dir() === "rtl";
			if((activeEl == null ? void 0 : activeEl.tagName.toLowerCase()) === "et2-tab")
			{
				let index = this.tabs.indexOf(activeEl);
				if(event.key === "Home")
				{
					index = 0;
				}
				else if(event.key === "End")
				{
					index = this.tabs.length - 1;
				}
				else if(["top", "bottom"].includes(this.placement) && event.key === (isRtl ? "ArrowRight" : "ArrowLeft") || ["start", "end"].includes(this.placement) && event.key === "ArrowUp")
				{
					index--;
				}
				else if(["top", "bottom"].includes(this.placement) && event.key === (isRtl ? "ArrowLeft" : "ArrowRight") || ["start", "end"].includes(this.placement) && event.key === "ArrowDown")
				{
					index++;
				}
				if(index < 0)
				{
					index = this.tabs.length - 1;
				}
				if(index > this.tabs.length - 1)
				{
					index = 0;
				}
				this.tabs[index].focus({preventScroll: true});
				if(this.activation === "auto")
				{
					this.setActiveTab(this.tabs[index], {scrollBehavior: "smooth"});
				}
				if(["top", "bottom"].includes(this.placement))
				{
					scrollIntoView(this.tabs[index], this.nav, "horizontal");
				}
				event.preventDefault();
			}
		}
	}
	/**
	 * Set up for printing
	 *
	 * @return {undefined|Deferred} Return a jQuery Deferred object if not done setting up
	 *  (waiting for data)
	 */
	beforePrint()
	{
		// Remove the "active" flag from all tabs-flags
		this.querySelector("[active]").removeAttribute("active");

		// Remove height limit
		this.style.height = '';

		// Show all enabled tabs
		for(let i = 0; i < this.tabData.length; i++)
		{
			let entry = this.tabData[i];
			if(entry.hidden)
			{
				continue;
			}
			entry.flagDiv.insertBefore(entry.contentDiv);
			entry.contentDiv.show();
		}
	}

	/**
	 * Reset after printing
	 */
	afterPrint()
	{
		this.setActiveTab(this._selectedIndex);
	}

	/**
	 * Activate the tab containing the given widget
	 *
	 * @param {et2_widget} widget
	 * @return {bool} widget was found in a tab
	 */
	activateTab(widget)
	{
		let tab = widget;
		while(tab._parent && tab._parent.nodeName !== 'ET2-TABBOX')
		{
			tab = tab._parent;
		}
		if (tab.nodeName === 'ET2-TAB-PANEL')
		{
			this.show(tab.name);
			return true;
		}
		return false;
	}

	/**
	 * Reimplement to allow our existing function signatures too
	 *
	 * @deprecated use this.show(name : string)
	 * @param tab number or name of tab (Sl uses that internally with a SlTab!)
	 * @param options
	 */
	setActiveTab(tab: SlTab|String|Number, options?: {
		emitEvents?: boolean;
		scrollBehavior?: 'auto' | 'smooth';
	})
	{
		if (typeof tab === 'number')
		{
			tab = this.getAllTabs()[tab];
			return this.show(tab.panel);
		}
		if (typeof tab === 'string')
		{
			return this.show(tab);
		}
		return super.setActiveTab(<SlTab>tab, options);
	}


	resize(_height)
	{
		if(_height)
		{
			this.tabHeight = parseInt(this.tabHeight) + parseInt(_height);
		}
	}
}

customElements.define("et2-tabbox", Et2Tabs);