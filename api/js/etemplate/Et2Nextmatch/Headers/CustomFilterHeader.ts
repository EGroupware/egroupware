import {loadWebComponent} from "../../Et2Widget/Et2Widget";
import {Et2Select} from "../../Et2Select/Et2Select";
import {Et2InputWidget, Et2InputWidgetInterface} from "../../Et2InputWidget/Et2InputWidget";
import {FilterMixin} from "./FilterMixin";
import {html, LitElement} from "lit";
import {cleanSelectOptions} from "../../Et2Select/FindSelectOptions";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * @summary Nextmatch custom filter header.
 *
 * Wraps another eTemplate input widget and handles nextmatch filter integration
 * while forwarding configured attributes to the inner filter widget.
 *
 * @slot - Hosts the dynamically created filter widget.
 */
@customElement("et2-nextmatch-header-custom")
export class Et2CustomFilterHeader extends FilterMixin(Et2InputWidget(LitElement))
{
	private widgetType : string;
	private widgetOptions : {};
	private filter_node : Et2InputWidgetInterface & LitElement;

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * tag of widget we want to use to filter
			 */
			widgetType: {type: String},

			/**
			 * Attributes / properties used for the filter widget
			 */
			widgetOptions: {type: Object}
		};
	}

	constructor(...args : any[])
	{
		super();
		this.widgetType = "et2-description";
		this.widgetOptions = {};
	}

	transformAttributes(attrs)
	{
		super.transformAttributes(attrs);

		let widgetType = this.getArrayMgr("modifications").getEntry(this.id)?.widgetType || attrs.widgetType || "";

		switch(widgetType)
		{
			case "link-entry":
				this.widgetType = 'et2-nextmatch-header-entry';
				break;
			default:
				this.widgetType = widgetType;
				// Prefer webcomponent, if legacy type was sent
				if(window.customElements.get("et2-" + this.widgetType))
				{
					this.widgetType = "et2-" + this.widgetType;
				}
		}
		if(!window.customElements.get(this.widgetType))
		{
			console.error("Unknown widget type '%s'", this.widgetType);
			this.widgetType = 'et2-select';
		}
		// @ts-ignore TS doesn't know about this.getParent()
		this.filter_node = <LitElement>loadWebComponent(this.widgetType, {...attrs, ...this.widgetOptions}, this);
		if(this.filter_node instanceof Et2Select)
		{
			(this.filter_node as any).hoist = true;
			this.filter_node.clearable = true;
		}
	}

	/**
	 * New filter options from server
	 * @param new_options
	 */
	set_select_options(new_options)
	{
		const widget_class = window.customElements.get(this.filter_node?.localName) as any;
		const property = widget_class?.getPropertyOptions?.('select_options');
		if(this.filter_node && property)
		{
			(this.filter_node as any).select_options = cleanSelectOptions(new_options);
		}
	}

	render()
	{
		return html`
            <slot></slot>`;
	}

	// @ts-expect-error Legacy wrapper intentionally proxies the base input value through the inner widget.
	get value()
	{
		return (this.filter_node as any)?.value || undefined;
	}

	set value(new_value)
	{
		if(this.filter_node)
		{
			(this.filter_node as any).value = new_value;
		}
	}
}
