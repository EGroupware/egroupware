import {css, html, LitElement, nothing, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Favorite} from "./Favorite";
import {property} from "lit/decorators/property.js";
import {until} from "lit/directives/until.js";
import {repeat} from "lit/directives/repeat.js";

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

				sl-menu-item:hover et2-image[src="trash"] {
					display: initial;
				}`
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

	private favorites : { [name : string] : Favorite } = {
		'blank': {
			name: typeof this.egw()?.lang == "function" ? this.egw().lang("No filters") : "No filters",
			state: {},
			group: false
		}
	};
	private loadingPromise = Promise.resolve();

	constructor()
	{
		super();
		this.handlePreferenceChange = this.handlePreferenceChange.bind(this);
	}
	connectedCallback()
	{
		super.connectedCallback();

		if(this.application)
		{
			this._load();
		}
		document.addEventListener("preferenceChange", this.handlePreferenceChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		document.removeEventListener("preferenceChange", this.handlePreferenceChange);
	}

	private _load()
	{
		this.loadingPromise = Favorite.load(this.egw(), this.application).then((favorites) =>
		{
			this.favorites = favorites;
		});
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
		let icon = (favorite.group !== false && !is_admin || ['blank', '~add~'].includes(name)) ? "" : html`
            <et2-image slot="suffix" src="trash" icon @click=${this.handleDelete}
                       statustext="${this.egw()?.lang("Delete") ?? "Delete"}"></et2-image>`;

		return html`
            <sl-menu-item value="${name}">
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