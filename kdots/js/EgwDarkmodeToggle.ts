import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {until} from "lit/directives/until.js";
import {EgwFramework} from "./EgwFramework";

/**
 * @summary Dark mode toggle button
 *
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
				:host {
					height: 1em;
					width: 1em;
				}
				sl-icon-button::part(base) {
					padding: 0;
				}
			`
		];
	}

	@property({type: String})
	label = "Toggle dark mode";

	@property({type: String})
	mode : 'dark' | 'light' | 'auto' = 'auto';

	private _initialValue = 'auto';

	constructor()
	{
		super();
		this._initialValue = this.hasAttribute("mode") ?
							 this.getAttribute("mode") :
							 (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
		this.handleDarkmodeChange = this.handleDarkmodeChange.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", this.handleDarkmodeChange);
		this.toggleDarkmode(this._initialValue == "dark");
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		window.matchMedia("(prefers-color-scheme: dark)").removeEventListener("change", this.handleDarkmodeChange);
	}


	protected toggleDarkmode(force = null)
	{
		if(force == null)
		{
			force = !(document.documentElement.getAttribute("data-darkmode") == "1");
		}
		this.mode = force ? "dark" : "light";
		if(force)
		{
			document.documentElement.setAttribute("data-darkmode", "1");
		}
		else
		{
			document.documentElement.setAttribute("data-darkmode", "0");
		}
		// Set class for Shoelace
		this.requestUpdate("mode")
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("egw-darkmode-change", {bubbles: true}));
		});
	}

	handleDarkmodeChange(e)
	{
		this.toggleDarkmode(e.matches);
	}

	render() : unknown
	{
		// This goes in the framework header, so we need to wait for egw.images to be loaded
		return html`${until((<EgwFramework>this.closest('egw-framework'))?.getEgwComplete().then(() => html`
            <sl-icon-button name="${this.mode == "light" ? "sun" : "moon"}"
                            label="${this.label}"
                            @click=${(e) => {this.toggleDarkmode()}}
            ></sl-icon-button>
		`), '')}`;
	}

}