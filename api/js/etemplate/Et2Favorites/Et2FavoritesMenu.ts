import {css, html, LitElement, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Favorite} from "./Favorite";
import {property} from "lit/decorators/property.js";
import {until} from "lit/directives/until.js";
import {repeat} from "lit/directives/repeat.js";

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

	@property()
	application : string;

	private favorites : { [name : string] : Favorite }
	private loadingPromise = Promise.resolve();

	connectedCallback()
	{
		super.connectedCallback();

		if(this.application)
		{
			this.loadingPromise = Favorite.load(this.egw(), this.application).then((favorites) =>
			{
				this.favorites = favorites;
			});
		}
	}

	handleSelect(event)
	{
		Favorite.applyFavorite(this.egw(), this.application, event.detail.item.value);
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
		});

		this.requestUpdate();
	}

	protected menuItemTemplate(name : string, favorite : Favorite) : TemplateResult
	{
		let is_admin = (typeof this.egw().app('admin') != "undefined");

		//@ts-ignore option.group does not exist
		let icon = (favorite.group !== false && !is_admin || ['blank', '~add~'].includes(name)) ? "" : html`
            <et2-image slot="suffix" src="trash" icon @click=${this.handleDelete}
                       statustext="${this.egw().lang("Delete")}"></et2-image>`;

		return html`
            <sl-menu-item value="${name}">
                ${icon}
                ${favorite.name}
            </sl-menu-item>`;
	}

	protected loadingTemplate()
	{
		return html`
            <sl-menu-item loading>${this.egw().lang("Loading")}</sl-menu-item>`;
	}

	render()
	{
		let content = this.loadingPromise.then(() =>
		{
			return html`
                <sl-menu
                        @sl-select=${this.handleSelect}
                >
                    ${repeat(Object.keys(this.favorites), (i) => this.menuItemTemplate(i, this.favorites[i]))}
                </sl-menu>
			`;
		});
		return html`
            ${until(content, this.loadingTemplate())}
		`;
	}
}