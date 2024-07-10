import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";

/**
 * @summary System message
 *
 * @dependency sl-alert
 * @dependency sl-icon
 *
 * @slot - Content
 * @slot icon - An icon to show in the message
 *
 * @csspart base - Wraps it all.
 * @csspart icon -
 */
@customElement('egw-darkmode-toggle')
export class EgwDarkmodeToggle extends LitElement
{
	static get styles()
	{
		return [
			css`
				sl-icon-button::part(base) {
					padding: 0;
				}
			`
		];
	}

	@property({type: Boolean})
	darkmode = false;

	private _initialValue = false;

	constructor()
	{
		super();
		this._initialValue = window.matchMedia("(prefers-color-scheme: dark)").matches;
		this.handleDarkmodeChange = this.handleDarkmodeChange.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.toggleDarkmode(this.hasAttribute("darkmode") ? this.darkmode : this._initialValue);
		window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", this.handleDarkmodeChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		window.matchMedia("(prefers-color-scheme: dark)").removeEventListener("change", this.handleDarkmodeChange);
	}

	public toggleDarkmode(force = null)
	{
		if(force == null)
		{
			force = !(document.documentElement.getAttribute("data-darkmode") == "true");
		}
		this.darkmode = force;
		if(force)
		{
			document.documentElement.setAttribute("data-darkmode", "true");
		}
		else
		{
			document.documentElement.setAttribute("data-darkmode", "0");
		}
		// Set class for Shoelace
		document.documentElement.classList.toggle("sl-theme-dark", this.darkmode);
		this.requestUpdate("darkmode")
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("egw-darkmode-change", {bubbles: true}));
		});
	}

	handleDarkmodeChange(e)
	{
		this.toggleDarkmode(e.matches ? "dark" : "light");
	}

	render() : unknown
	{
		return html`
            <sl-icon-button name="${this.darkmode ? "sun" : "moon"}"
                            @click=${(e) => {this.toggleDarkmode()}}
            ></sl-icon-button>
		`;
	}

}