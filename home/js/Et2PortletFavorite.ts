import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {classMap, css, html} from "@lion/core";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import type {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {Et2Favorites} from "../../api/js/etemplate/Et2Favorites/Et2Favorites";

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

	/**
	 * Get a list of user-configurable properties
	 * @returns {[{name : string, type : string, select_options? : [SelectOption]}]}
	 */
	get portletProperties() : { name : string, type : string, label : string, select_options? : SelectOption[] }[]
	{
		// Default blank filter
		let favorites = [
			{value: 'blank', label: this.egw().lang("No filters")}
		];

		// Load favorites
		let preferences : any = this.egw().preference("*", this.settings.appname);
		for(let pref_name in preferences)
		{
			if(pref_name.indexOf(Et2Favorites.PREFIX) == 0 && typeof preferences[pref_name] == 'object')
			{
				let name = pref_name.substr(Et2Favorites.PREFIX.length);
				favorites.push({value: name, label: preferences[pref_name]['name']});
			}
		}
		return [
			...super.portletProperties,
			{name: "favorite", type: "et2-select", label: "Favorite", select_options: favorites}
		]
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