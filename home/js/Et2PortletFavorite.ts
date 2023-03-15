import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {classMap, css, html} from "@lion/core";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import type {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {Et2Favorites} from "../../api/js/etemplate/Et2Favorites/Et2Favorites";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

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
		return [
			...super.portletProperties,
			{name: "favorite", type: "et2-select", label: "Favorite", select_options: this.favorites}
		]
	}

	public get favorites()
	{
		// Default blank filter
		let favorites = [
			{value: 'blank', label: this.egw().lang("No filters"), favorite: {}}
		];

		// Load favorites
		if(this.settings?.appname)
		{
			let preferences : any = this.egw().preference("*", this.settings.appname);
			for(let pref_name in preferences)
			{
				if(pref_name.indexOf(Et2Favorites.PREFIX) == 0 && typeof preferences[pref_name] == 'object')
				{
					let name = pref_name.substr(Et2Favorites.PREFIX.length);
					favorites.push({
						value: name,
						label: preferences[pref_name]['name'],
						favorite: preferences[pref_name]
					});
				}
			}
		}
		return favorites;
	}

	/**
	 * Overridden so we can just apply the favorite to the nm
	 *
	 * @param button_id
	 * @param value
	 */
	_process_edit(button_id, value)
	{
		if(button_id == Et2Dialog.OK_BUTTON && value.favorite != this.settings.favorite)
		{
			const state = this.favorites.find(f => f.value == value.favorite)?.favorite || {};
			if(this.nm && typeof state == "object")
			{
				// Firefox has trouble with spaces in search
				if(state.state && state.state.search)
				{
					state.state.search = unescape(state.state.search);
				}

				// Apply
				if(state.state && state.state.sort && state.state.sort.id)
				{
					this.nm.sortBy(state.state.sort.id, state.state.sort.asc, false);
				}
				else
				{
					// Not using resetSort() to avoid the extra applyFilters() call
					this.nm.sortBy(undefined, undefined, false);
				}
				if(state.state && state.state.selectcols)
				{
					// Make sure it's a real array, not an object, then set cols
					this.nm.set_columns(jQuery.extend([], state.state.selectcols));
				}
				this.nm.applyFilters(state.state || state.filter || {});
			}
		}
		super._process_edit(button_id, value);
	}


	/**
	 * Override parent to force resize on initial load
	 * @param settings
	 * @returns {Promise<void> | Promise<boolean>}
	 */
	update_settings(settings) : Promise<void> | Promise<boolean>
	{
		return super.update_settings(settings)
			.then(result =>
			{
				if(!this.nm)
				{
					// If child was added recently (not loaded in normal reload), resize them all
					etemplate2.getByTemplate("home.favorite").forEach(et => (<etemplate2>et).resize(undefined))
				}
				return result;
			});
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

	protected get nm()
	{
		return this.getWidgetById('nm') || etemplate2.getById(this.id) && etemplate2.getById(this.id).widgetContainer.getWidgetById('nm') || false;
	}

	public toggleHeader()
	{
		//widget.set_class(widget.class == 'opened' ? 'closed' : 'opened');
		// We operate on the DOM here, nm should be unaware of our fiddling
		let nm = this.nm
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