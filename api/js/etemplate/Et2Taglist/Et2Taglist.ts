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
import {TaglistSelection} from "./TaglistSelection";
import {taglistStyles} from "./TaglistStyles";

// Force the include, we really need this and without it the file will be skipped
const really_import_me = EgwOption;
const really_import_me2 = TaglistSelection;

/**
 * Taglist base class implementation
 */
export class Et2Taglist extends Et2widgetWithSelectMixin(LionCombobox)
{
	static get styles()
	{
		return [
			...super.styles,
			taglistStyles,
			css`
			  :host {
				display: block;
				border: 1px solid var(--taglist-combobox__container-boder-color);
    			border-radius: 3px;
    			min-height: 24px;
			  }
			  * > ::slotted([slot="input"]){min-height:24px;}
			  .input-group__container{border:none;}
			  * > ::slotted([role="listbox"]) {
			  	border: 1px solid;
			  	border-color: var(--taglist-combobox__container-boder-color);
			  	border-bottom-left-radius: 3px;
			  	border-bottom-right-radius: 3px;
			  	border-top: none;
			  	margin-top: 1px;
			  }
			  
			`
		];
	}
	static get properties()
	{
		return {
			...super.properties,
			multiple: {type : Boolean},
			editModeEnabled : {type : Boolean}
		}
	}

	/**
	 * @type {SlotsMap}
	 */
	get slots() {
		return {
			...super.slots,
			"selection-display": () =>
			{
				let display = document.createElement("taglist-selection");
				display.setAttribute("slot", "selection-display");
				return display;
			}
		}
	}

	constructor()
	{
		super();

	}

	connectedCallback()
	{
		super.connectedCallback();
		this.addEventListener('model-value-changed', () => {this._selectionDisplayNode.requestUpdate();});

	}


	firstUpdated(changedProperties) {
		super.firstUpdated(changedProperties);

		// If there are select options, enable toggle on click so user can see them
		this.showAllOnEmpty = this.select_options.length>0;
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
            <egw-option .choiceValue="${option.value}" ?checked=${option.value == this.modelValue} ?icon="${option.icon}"
						.label="${option.label}">
				${option.label}
			</egw-option>`;
	}

	set multiple (value)
	{
		let oldValue = this.multipleChoice;
		this.multipleChoice = value;
		this.requestUpdate("multipleChoice", oldValue);
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