import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {classMap, css, html} from "@lion/core";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {etemplate2} from "../../api/js/etemplate/etemplate2";

export class Et2PortletFavorite extends Et2Portlet
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
			  .portlet__header et2-button {
				visibility: hidden;
			  }

			  .portlet__header:hover et2-button {
				visibility: visible;
			  }
			`
		]
	}

	constructor()
	{
		super();
		this.toggleHeader = this.toggleHeader.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.classList.add("header_hidden");
	}

	headerTemplate()
	{
		const hidden = this.classList.contains("header_hidden");
		return html`${super.headerTemplate()}
        <et2-button-icon id="header_toggle" slot="header"
                         name="${hidden ? "chevron-down" : "chevron-up"}"
                         class=${classMap({
                             hidden: hidden
                         })}
                         noSubmit=true
                         @click=${this.toggleHeader}
        ></et2-button-icon>
		`;
	}

	public toggleHeader()
	{
		//widget.set_class(widget.class == 'opened' ? 'closed' : 'opened');
		// We operate on the DOM here, nm should be unaware of our fiddling
		let nm = this.getWidgetById('nm') || etemplate2.getById(this.id) && etemplate2.getById(this.id).widgetContainer.getWidgetById('nm') || false;
		if(!nm)
		{
			return;
		}

		// Hide header
		nm.div.toggleClass('header_hidden');
		nm.set_hide_header(nm.div.hasClass('header_hidden'));
		nm.resize();

		// Toggle class that changes everything
		this.classList.toggle("header_hidden");
		this.requestUpdate();
	}
}

if(!customElements.get("et2-portlet-favorite"))
{
	customElements.define("et2-portlet-favorite", Et2PortletFavorite);
}