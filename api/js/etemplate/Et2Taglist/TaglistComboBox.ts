
/**
 * EGroupware eTemplate2 - TaglistComboBox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */



import { LionCombobox } from '@lion/combobox';
import {css} from "@lion/core";


export class TaglistComboBox extends LionCombobox {
	static get properties() {
		return {
			...super.properties,

		};
	}
	static get styles() {
		return [
			super.styles,
			css`
				::slotted([slot='input']) {
				  min-height: 23px;
				  
				}
				:host {
					border: 1px solid var(--taglist-combobox__container-boder-color);
					border-radius: 3px;
				}
      	`,
		];
	}
	constructor()
	{
		super();
	}
}
customElements.define('taglist-combobox', TaglistComboBox);



