import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import type {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";
import {Et2Favorites} from "../../api/js/etemplate/Et2Favorites/Et2Favorites";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import type {Et2Nextmatch} from "../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch";
import {loadWebComponent} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import {css, html} from "lit";
import {classMap} from "lit/directives/class-map.js";

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
					// A favorite's selectcols can come back as an object keyed by index, not an array
					this.nm.setColumns(Object.values(state.state.selectcols));
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

	protected get nm() : Et2Nextmatch | false
	{
		return <Et2Nextmatch>this.getWidgetById('nm') || etemplate2.getById(this.id) && <Et2Nextmatch>etemplate2.getById(this.id).widgetContainer.getWidgetById('nm') || false;
	}

	/**
	 * Slot the app's own header widgets (Filemanager's up / home / path navigation) above the list.
	 *
	 * Et2Nextmatch has no header_left / header_right attributes - the legacy widget's header bar is
	 * gone, and the filter drawer that replaced it belongs to the app shell, which a portlet sitting
	 * on Home has no access to.  What it does have is a "header" slot rendered directly above the
	 * grid, so an app that sends a header template still gets it, in the same place as before.
	 *
	 * The template name is deliberately read out of the portlet's content rather than the
	 * nextmatch's settings: header_left is not in Et2Nextmatch's ALLOWED_SETTINGS, and shouldn't
	 * be - no other app needs it.  Called by home's app.ts once the portlet's own etemplate is
	 * ready, since that is the first point at which both the content and the nextmatch exist.
	 */
	public applyHeaderTemplate()
	{
		const nm = this.nm;
		if(!nm || nm.querySelector("[slot='header']"))
		{
			return;
		}
		const template = etemplate2.getById(this.id)?.widgetContainer.getArrayMgr("content")?.getEntry("nm[header_left]");
		if(!template)
		{
			return;
		}
		loadWebComponent("et2-template", {id: template, slot: "header"}, nm);
	}

	/**
	 * Show or hide the list's column header row.
	 *
	 * Nothing is asked of the nextmatch here: the collapsed look is a "header_hidden" class on
	 * the portlet plus a ::part() rule in app.css, so the grid keeps its column widths and does
	 * not have to re-render or resize.
	 */
	public toggleHeader()
	{
		this.classList.toggle("header_hidden");
		this.requestUpdate();
	}
}

if(!customElements.get("et2-portlet-favorite"))
{
	customElements.define("et2-portlet-favorite", Et2PortletFavorite);
}