/**
 * EGroupware eTemplate2 - TaglistOption widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {LionOption} from '@lion/listbox';
import {css, html} from "@lion/core";
import {taglistStyles} from "./TaglistStyles";
import {colorsDefStyles} from "../Styles/colorsDefStyles";

export class EgwOption extends LionOption
{

	static get properties()
	{
		return {
			...super.properties,
			title: {type: String},
			icon: {type: String}
		};
	}

	constructor()
	{
		super();
		this.title = '';
		this.icon = '';
	}

	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			css`
			:host([checked]) {
				display: none;
			}
			:host {
				line-height: 20px;
    			padding: 8px;
			}
			:host(:hover) {
				background-color: var(--row_hover);
			}
      	`,
		];
	}

	render()
	{
		return html`
            ${this.icon
              ? html` <img class="egw-option__icon" src="${this.icon}" alt=""/>`
              : ''}
            ${super.render()}
		`;
	}
}
customElements.define('egw-option', EgwOption);

export class EgwOptionEmail extends EgwOption {

}
customElements.define('egw-option-email', EgwOptionEmail);

export class EgwOptionState extends EgwOption {

}
customElements.define('egw-option-state', EgwOptionState);

export class EgwOptionCategory extends EgwOption {

}
customElements.define('egw-option-category', EgwOptionCategory);