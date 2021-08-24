/**
 * EGroupware eTemplate2 - Box widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, LitElement} from "../../../../node_modules/@lion/core/index.js";
import {Et2Widget} from "../Et2Widget";

export class Et2Box extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			css`
            :host {
				display: block;
				width: 100%;
            }
            :host > div {
            	display: flex;
            	flex-wrap: nowrap;
            	justify-content: space-between;
            	align-items: stretch;
						}
            ::slotted(*) {
            	/* CSS for child elements */
            }`,
		];
	}

	render()
	{
		return html`
            <div class="et2_box" ${this.id ? html`id="${this.id}"` : ''}>
                <slot><p>Empty box</p></slot>
            </div> `;
	}

	set_label(new_label)
	{
		// Boxes don't have labels
	}

	_createNamespace() : boolean
	{
		return true;
	}
}

customElements.define("et2-box", Et2Box);

export class Et2HBox extends Et2Box
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host > div {
            	flex-direction: row;
						}`
		];
	}
}

customElements.define("et2-hbox", Et2HBox);

export class Et2VBox extends Et2Box
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host > div {
            	flex-direction: column;
						}`
		];
	}
}

customElements.define("et2-vbox", Et2VBox);