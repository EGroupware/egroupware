/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {css, html, TemplateResult} from "@lion/core";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {LionCombobox} from "@lion/combobox";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {EgwOption} from "./EgwOption";

// Force the include, we really need this and without it the file will be skipped
const really_import_me = EgwOption;

/**
 * Taglist base class implementation
 */
export class Et2Taglist extends Et2widgetWithSelectMixin(LionCombobox)
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
			multiple: {type : Boolean},
		}
	}

	/**
	 * @type {SlotsMap}
	 */
	get slots() {
		return {
			...super.slots,
			"selection-display": () => {
				return html `<taglist-selection
                        slot="selection-display"
                        style="display: contents;"></taglist-selection>`;
			}
		}
	}

	constructor()
	{
		super();

	}

	/**
	 * Get the node where we're putting the options
	 *
	 * If this were a normal selectbox, this would be just the <select> tag (this._inputNode) but in a more
	 * complicated widget, this could be anything.
	 *
	 * It doesn't really matter what we return here in Et2Taglist, since LionListbox will find the options and put them
	 * where it wants them, and bind any needed handlers (and listen for new options).
	 * We just return the parent.
	 *
	 * @overridable
	 * @returns {HTMLElement}
	 */
	get _optionTargetNode() : HTMLElement
	{
		return super._optionTargetNode;
	}

	/**
	 * Render the "empty label", used when the selectbox does not currently have a value
	 *
	 * @overridable
	 * @returns {TemplateResult}
	 */
	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html`
            <egw-option .choiceValue="${option.value}" ?checked=${option.value == this.modelValue} ?icon="${option.icon}">${option.label}</egw-option>`;
	}

	set multiple (value)
	{
		this.multipleChoice = value;
	}

	get multiple ()
	{
		return this.multipleChoice;
	}

}
customElements.define('et2-taglist', Et2Taglist);


/**
 * Taglist-email implementation
 */
export class Et2TaglistEmail extends Et2Taglist
{

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html`
            <egw-option-email .choiceValue="${option.value}" ?checked=${option.value == this.modelValue} ?icon="${option.icon}">
				${option.label}
			</egw-option-email>`;
	}
}
customElements.define('et2-taglist-email', Et2TaglistEmail);