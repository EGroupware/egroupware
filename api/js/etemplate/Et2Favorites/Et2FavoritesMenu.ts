import {css, html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Favorite} from "./Favorite";
import {property} from "lit/decorators/property.js";
import {until} from "lit/directives/until.js";
import {repeat} from "lit/directives/repeat.js";
import {createRef, Ref, ref} from "lit/directives/ref.js";
import Sortable from "sortablejs/modular/sortable.complete.esm.js";
import {SlMenu} from "@shoelace-style/shoelace";
import {state} from "lit/decorators/state.js";
import {app} from "../../jsapi/egw_global";

/**
 * @summary A menu listing a user's favorites.  Populated from the user's preferences.
 *
 * @dependency sl-menu
 * @dependency sl-menu-item
 * @dependency sl-menu-label
 * @dependency et2-image
 *
 * @slot - Add additional menu items
 */
@customElement("et2-favorites-menu")
export class Et2FavoritesMenu extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			super.styles,
			css`
				:host {
					min-width: 15em;
				}

				et2-image[src="trash"] {
					display: none;
				}
				[part="menu"]{
					padding: 0;
				}

				sl-menu-item:hover et2-image[src="trash"] {
					display: initial;
				}

				sl-menu-item[active] {
					background-color: var(--highlight-background-color);
				}
				sl-menu-item::part(submenu-icon), sl-menu-item::part(checked-icon){
					width: 0;
				}
                sl-menu-item::part(base){
	                padding-block: 0;
                }
				
				[slot="prefix"]:not([name="plus"]){
					color: var(--sl-color-neutral-500);
				}
			`
		]
	};

	/**
	 * The current application we're showing favorites for.
	 *
	 * @type {string}
	 */
	@property()
	application : string;

	@property()
	noAdd : boolean = false;

	@property({type: Boolean})
	sortable : boolean = false;

	@state()
	activeFavorite : string = "";

	private favorites : { [name : string] : Favorite } = {
		'blank': {
			name: typeof this.egw()?.lang == "function" ? this.egw().lang("No filters") : "No filters",
			state: {},
			group: false
		}
	};
	private loadingPromise = Promise.resolve();
	private _sortable : Sortable;
	private menuRef : Ref<SlMenu> = createRef();

	constructor()
	{
		super();
		this.handlePreferenceChange = this.handlePreferenceChange.bind(this);
		this.handleStateChange = this.handleStateChange.bind(this);
	}
	connectedCallback()
	{
		super.connectedCallback();

		if(this.application)
		{
			this._load();
		}
		document.addEventListener("preferenceChange", this.handlePreferenceChange);
		document.addEventListener("et2-filter", this.handleStateChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._sortable && this._sortable.el && this._sortable.destroy();
		this._sortable = null;
		document.removeEventListener("preferenceChange", this.handlePreferenceChange);
		document.removeEventListener("et2-filter", this.handleStateChange);
	}

	firstUpdated(changedProperties : PropertyValues<this> | Map<PropertyKey, unknown> | undefined)
	{
		super.firstUpdated(changedProperties);
		this.loadingPromise.then(async() =>
		{
			await this.updateComplete;
			this.highlightFavorite(window.app[this.application]?.getState());
		});
	}

	willUpdate(changedProperties : PropertyValues)
	{
		if(changedProperties.has("application") && this.application)
		{
			this._load();
		}
	}

	private _load()
	{
		this.loadingPromise = Favorite.load(this.egw(), this.application).then((favorites) =>
		{
			this.favorites = favorites;
		});
	}

	private async _menuChanged(menu? : SlMenu)
	{
		if(!menu || !this.sortable)
		{
			if(this._sortable)
			{
				this._sortable.destroy();
			}
			return;
		}
		await this.updateComplete;
		this._sortable = Sortable.create(menu, {
			ghostClass: 'ui-fav-sortable-placeholder',
			draggable: 'sl-menu-item:not([value="~add~"])',
			delay: 25,
			dataIdAttr: 'value',
			onSort: (event) =>
			{
				let favSortedList = this._sortable.toArray();
				this.egw().set_preference(this.application, 'fav_sort_pref', favSortedList);
				this._load();
				
				// Trigger event so other widgets can update and be in sync
				document.dispatchEvent(new CustomEvent("preferenceChange", {
					bubbles: true,
					detail: {
						application: this.application,
						preference: 'fav_sort_pref'
					}
				}));
			}
		})
	}

	/**
	 * Highlight the favorite that matches the given state, if any
	 *
	 * @param currentState
	 * @return {Promise<void>}
	 * @private
	 */
	public async highlightFavorite(currentState)
	{
		let best_match = null;
		let best_count = 0;
		this.activeFavorite = "";

		// Skip it all if currentState is empty
		if(!currentState || Object.keys(currentState).length == 0)
		{
			return;
		}

		Object.entries(this.favorites).forEach(([name, favorite]) =>
		{
			const app_object = app[this.application];
			if (app_object) favorite = app_object.fixState(favorite);

			let match_count = 0;
			let extra_keys = Object.keys(favorite.state);

			// Look through each key in the current state
			for(const state_key in currentState)
			{
				extra_keys.splice(extra_keys.indexOf(state_key), 1);
				if(typeof favorite.state != "undefined" && typeof currentState[state_key] != "undefined" && typeof favorite.state[state_key] != "undefined" && (currentState[state_key] == favorite.state[state_key] || !currentState[state_key] && !favorite.state[state_key]))
				{
					match_count++;
				}
				else if(state_key == "selectcols" && typeof favorite.state["selectcols"] == "undefined")
				{
					// Skip, not set in favorite
				}
				else if(typeof currentState[state_key] != "undefined" && currentState[state_key] && typeof currentState[state_key] === "object"
					&& typeof favorite.state != "undefined" && typeof favorite.state[state_key] != "undefined" && favorite.state[state_key] && typeof favorite.state[state_key] === "object")
				{
					if((typeof currentState[state_key].length !== "undefined" || typeof currentState[state_key].length !== "undefined")
						&& (currentState[state_key].length || Object.keys(currentState[state_key]).length) != (favorite.state[state_key].length || Object.keys(favorite.state[state_key]).length))
					{
						// State or favorite has a length, but the other does not
						if((currentState[state_key].length === 0 || Object.keys(currentState[state_key]).length === 0) &&
							(favorite.state[state_key].length == 0 || Object.keys(favorite.state[state_key]).length === 0))
						{
							// Just missing, or one is an array and the other is an object
							continue;
						}
						// One has a value and the other doesn't, no match
						return;
					}
					else if(currentState[state_key].length !== "undefined" && typeof favorite.state[state_key].length !== "undefined" &&
						currentState[state_key].length === 0 && favorite.state[state_key].length === 0)
					{
						// Both set, but both empty
						match_count++;
						continue;
					}
					// Consider sub-objects (column filters) individually
					for(var sub_key in currentState[state_key])
					{
						if(currentState[state_key][sub_key] == favorite.state[state_key][sub_key] || !currentState[state_key][sub_key] && !favorite.state[state_key][sub_key])
						{
							match_count++;
						}
						else if(currentState[state_key][sub_key] && favorite.state[state_key][sub_key] &&
							typeof currentState[state_key][sub_key] === "object" && typeof favorite.state[state_key][sub_key] === "object")
						{
							// Too deep to keep going, just string compare for perfect match
							if(JSON.stringify(currentState[state_key][sub_key]) === JSON.stringify(favorite.state[state_key][sub_key]))
							{
								match_count++;
							}
						}
						else if(typeof currentState[state_key][sub_key] !== "undefined" && currentState[state_key][sub_key] != favorite.state[state_key][sub_key])
						{
							// Different values, do not match
							return;
						}
					}
				}
				else if(typeof currentState[state_key] !== "undefined"
					&& typeof favorite.state != "undefined" && typeof favorite.state[state_key] !== "undefined"
					&& currentState[state_key] != favorite.state[state_key])
				{
					// Different values, do not match
					return;
				}
			}
			// Check for anything set that the current one does not have
			for(var i = 0; i < extra_keys.length; i++)
			{
				if(favorite.state[extra_keys[i]])
				{
					return;
				}
			}
			// match noFilter, if none is set to a non-empty value
			if (name === 'blank')
			{
				match_count = !currentState.filter && !currentState.filter2 && !currentState.cat_id && !currentState.search ? 9 : 0;
				Object.entries(currentState.col_filter || {}).forEach(([name, value]) => {
					if (value) match_count=0;
				});
			}
			// Better match?  Hold on to it and keep looking
			if(match_count > best_count)
			{
				best_match = name;
				best_count = match_count;
			}
		});
		if(best_match)
		{
			this.activeFavorite = best_match;
		}
	}

	/**
	 * Nextmatch filter has changed, change which favorite is highlighted
	 *
	 * @param e
	 */
	handleStateChange(e)
	{
		if(e && e.detail?.nm?.getInstanceManager().app == this.application)
		{
			// Get the full state of the app and highlight if a favourite matches
			this.highlightFavorite(e.detail.activeFilters ?? this.getInstanceManager()?.app_obj[this.application]?.getState());
		}
		// Could also be a non-nm state change
	}

	handlePreferenceChange(e)
	{
		if(e && e.detail?.application == this.application)
		{
			this._load();
			this.requestUpdate();
		}
	}

	handleSelect(event)
	{
		if(event.detail.item.value == Favorite.ADD_VALUE)
		{
			return this.handleAdd(event);
		}
		Favorite.applyFavorite(this.egw(), this.application, event.detail.item.value);
	}

	handleAdd(event)
	{
		event.stopPropagation();
		if(this.egw().window && this.egw().window.app[this.application])
		{
			this.egw().window.app[this.application].add_favorite({});
		}
	}

	handleDelete(event)
	{
		// Don't trigger click
		event.stopPropagation();

		const menuItem = event.target.closest("sl-menu-item");
		menuItem.setAttribute("loading", "");

		const favoriteName = menuItem.value;

		// Remove from server
		Favorite.remove(this.egw(), this.application, favoriteName).then(() =>
		{
			// Remove from widget
			delete this.favorites[favoriteName];
			this.requestUpdate();

			this.updateComplete.then(() =>
			{
				this.dispatchEvent(new CustomEvent("preferenceChange", {
					bubbles: true,
					composed: true,
					detail: {
						application: this.application,
						preference: favoriteName
					}
				}));
			});
		});

		this.requestUpdate();
	}

	protected menuItemTemplate(name : string, favorite : Favorite) : TemplateResult
	{
		let is_admin = (typeof this.egw()?.app == "function") && (typeof this.egw()?.app('admin') != "undefined");

		//@ts-ignore option.group does not exist
		let icon = (favorite.group !== false && !is_admin || ['blank', '~add~'].includes(name)) ? "" :
			html`
                <et2-image slot="suffix" src="trash" icon @click=${this.handleDelete}
                           statustext="${this.egw()?.lang("Delete") ?? "Delete"}"></et2-image>
			`;

		return html`
            <sl-menu-item value="${name}"
                          ?active=${name == this.activeFavorite}
            >
	            <sl-icon slot="prefix" name="record-fill"></sl-icon>
                ${icon}
                ${favorite.name}
            </sl-menu-item>`;
	}

	protected loadingTemplate()
	{
		return html`
            <sl-menu-item loading>${typeof this.egw()?.lang == "function" ? this.egw().lang("Loading") : "Loading"}
            </sl-menu-item>`;
	}

	render()
	{
		let content = this.loadingPromise.then(() =>
		{
			return html`
                <sl-menu
                        part="menu"
                        @sl-select=${this.handleSelect}
                        ${ref(this._menuChanged)}
                >
                    ${this.label ? html`
                        <sl-menu-label>${this.label}</sl-menu-label>` : nothing}
                    ${repeat(Object.keys(this.favorites), (i) => this.menuItemTemplate(i, this.favorites[i]))}
                    <slot></slot>
                    ${this.noAdd ? nothing : html`
                        <sl-menu-item value=${Favorite.ADD_VALUE}
                                      @sl-select=${this.handleAdd}
                        >
                            <sl-icon name="plus" slot="prefix"></sl-icon>
                            ${this.egw().lang("Current view as favourite")}
                        </sl-menu-item>`
                    }
                </sl-menu>
			`;
		});
		return html`
            ${until(content, this.loadingTemplate())}
		`;
	}
}