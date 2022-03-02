/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */



import {css, html, LitElement} from "@lion/core";
import { ScopedElementsMixin } from '@open-wc/scoped-elements';
import {TaglistOption, TaglistOptionEmail} from "./TaglistOption";
import {TaglistComboBox} from "./TaglistComboBox";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {TaglistSelection} from "./TaglistSelection";

/**
 * Taglist base class implementation
 */
export class Et2Taglist extends Et2widgetWithSelectMixin(ScopedElementsMixin(LitElement))
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			  :host {
				display: block;
			  }
			`
		];
	}
	static get properties()
	{
		return {
			...super.properties,
			multiple : {type : Boolean},


		}
	}

	constructor()
	{
		super();

	}

	// can be overriden for other taglist type implementations
	static get taglistOptionImp () {return TaglistOption};
	static get taglistComboboxImp () {return TaglistComboBox};
	static get taglistSelectionImp () {return TaglistSelection};

	static get scopedElements() {
		return {
			'taglist-combobox': this.taglistComboboxImp,
			'taglist-option': this.taglistOptionImp,
			'taglist-selection': this.taglistSelectionImp
		};
	}

	_setOptionTemplate()
	{
		return html`
            ${this.get_select_options().map(entry => html` <taglist-option .choiceValue="${entry.value}">${entry.label}</taglist-option> `)}
		`;
	}

	render()
	{
		return html`
		  <taglist-combobox name="combo" multiple-choice show-all-on-empty>
			  <taglist-selection
			  slot="selection-display"
			  style="display: contents;"></taglist-selection> 
			  ${this._setOptionTemplate()}
		  </taglist-combobox>
		`;
	}
}
customElements.define('et2-taglist', Et2Taglist);


/**
 * Taglist-email implementation
 */
export class Et2TaglistEmail extends Et2Taglist
{
	static get taglistOptionImp () {return TaglistOptionEmail};

	_setOptionTemplate()
	{
		//@todo: needs to be implemented
		return super._setOptionTemplate();
	}

	get_select_options(): any
	{
		//@todo: needs to be implemented
		return super.get_select_options();
	}
}
customElements.define('et2-taglist-email', Et2TaglistEmail);