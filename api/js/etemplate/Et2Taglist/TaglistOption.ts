/**
 * EGroupware eTemplate2 - TaglistOption widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import { LionOption } from '@lion/listbox';
import {css, html} from "@lion/core";


export class TaglistOption extends LionOption {

	static get properties() {
		return {
			...super.properties,
			title: {
				type: String,
			},
		};
	}
	constructor() {
		super();
		this.title = '';

	}

	static get styles() {
		return [
			...super.styles,
			css`
			:host([checked]) {
				visibility: hidden;
			}
      	`,
		];
	}

}

export class TaglistOptionEmail extends TaglistOption {

}

export class TaglistOptionState extends TaglistOption {

}

export class TaglistOptionCategory extends TaglistOption {

}