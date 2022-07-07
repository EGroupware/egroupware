import {loadWebComponent} from "../../Et2Widget/Et2Widget";
import {Et2Select} from "../../Et2Select/Et2Select";
import {Et2InputWidget, Et2InputWidgetInterface} from "../../Et2InputWidget/Et2InputWidget";
import {FilterMixin} from "./FilterMixin";
import {html, LitElement} from "@lion/core";

/**
 * Filter by some other type of widget
 * Acts as a wrapper around the other widget, but handles all the nm stuff here
 * Any attributes set are passed to the filter widget
 */
export class Et2CustomFilterHeader extends FilterMixin(Et2InputWidget(LitElement))
{
	private widget_type : string;
	private widget_options : {};
	private filter_node : Et2InputWidgetInterface & LitElement;

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * tag of widget we want to use to filter
			 */
			widget_type: {type: String},

			/**
			 * Attributes / properties used for the filter widget
			 */
			widget_options: {type: Object}
		};
	}

	constructor(...args : any[])
	{
		super();
		this.widget_type = "et2-description";
		this.widget_options = {};
	}

	transformAttributes(attrs)
	{
		super.transformAttributes(attrs);

		switch(attrs.widget_type)
		{
			case "link-entry":
				this.widget_type = 'et2-nextmatch-header-entry';
				break;
			default:
				this.widget_type = attrs.widget_type;
				// Prefer webcomponent, if legacy type was sent
				if(window.customElements.get("et2-" + this.widget_type))
				{
					this.widget_type = "et2-" + this.widget_type;
				}
		}
		// @ts-ignore TS doesn't know about this.getParent()
		this.filter_node = <LitElement>loadWebComponent(this.widget_type, {...attrs, ...this.widget_options}, this);
		if(this.filter_node instanceof Et2Select)
		{
			this.filter_node.hoist = true;
			this.filter_node.clearable = true;
		}
	}

	connectedCallback()
	{
		super.connectedCallback();
		if(this.filter_node)
		{
			this.filter_node.updateComplete.then(() =>
			{
				this.filter_node.addEventListener("change", this.handleChange);
			})
		}
	}

	render()
	{
		return html`
            <slot></slot>`;
	}

	get value() { return this.filter_node?.value || undefined;}

	set value(new_value) { this.filter_node.value = new_value;}

}

customElements.define("et2-nextmatch-header-custom", Et2CustomFilterHeader);