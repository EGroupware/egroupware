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
			editModeEnabled : {type : Boolean},
			allowFreeEntries : {type : Boolean}
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

		this.value = [];
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

		if (this.allowFreeEntries)
		{
			this.value.forEach(_v => {
				this.__appendSelOption(_v);
			})
		}
	}

	__appendSelOption(_value)
	{
		const optionsMappedValues = (<SelectOption[]>this.select_options).map(({value}) =>{return value});
		if (!optionsMappedValues.includes(_value))
		{
			this.select_options = (<SelectOption[]>this.select_options).concat({label:_value, value:_value});
		}

		// we need to wait for the actuall rendering of select options before being able to set our newly added value.
		// So far the only way to make sure of that is binding set_value into form-element-register event which does get
		// called when the option gets attached to dom. We make sure to unbind that event because we only want that set
		// value for a newly added value and not all selected values.
		const modelValueChanged = (ev) => {
			if (this._inputNode.value == _value)
			{
				this.set_value(this.getValue().concat([this._inputNode.value]));
				// reset the entered value otherwise to clean up the inputbox after
				// new entry has been set as new value and option.
				this._inputNode.value = '';
			}
			this.removeEventListener('form-element-register', modelValueChanged);
		};
		this.addEventListener('form-element-register', modelValueChanged);

	}


	/**
	 * @override of _listboxOnKeyDown
	 * @desc
	 * Handle various keyboard controls; UP/DOWN will shift focus; SPACE selects
	 * an item.
	 *
	 * @param {KeyboardEvent} ev - the keydown event object
	 * @protected
	 */
	_listboxOnKeyDown(ev) {
		const { key } = ev;

		// make sure we don't mess up with activeIndex after a free entry gets added into options
		// it's very important to intercept the key down handler before the listbox (parent) happens.
		if (key === 'Enter' && this.allowFreeEntries && this._inputNode.value
			&& !this.formElements.filter(_o=>{return _o.choiceValue == this._inputNode.value;}).length)
		{
			this.activeIndex = -1;
		}

		super._listboxOnKeyDown(ev);
	}

	/**
	 * @param {string} v
	 * @protected
	 */
	_setTextboxValue(v) {
		// Make sure that we don't loose inputNode.selectionStart and inputNode.selectionEnd
		if (!this.allowFreeEntries && this._inputNode.value !== v) {
			this._inputNode.value = v;
		}
		else if (this._inputNode.value !='' && !(<SelectOption[]>this.select_options).filter((_option)=>{return _option.value == this._inputNode.value}).length)
		{
			this.__appendSelOption(this._inputNode.value);
		}
	}

	getValue(): String[]
	{
		return this.modelValue;
	}

	/**
	 * Set value(s) of taglist
	 *
	 * @param value (array of) ids
	 */
	set_value(value)
	{
		if (value === '' || value === null)
		{
			value = [];
		}
		else if (typeof value === 'string' && this.multiple)
		{
			value = value.split(',');
		}

		let values = Array.isArray(value) ? value : [value];

		// Switch multiple according to attribute and more than 1 value
		if(this.multiple !== true)
		{
			this.multiple = this.multiple ? values.length > 1 : false;
		}
		if(this.allowFreeEntries)
		{
			values.forEach(val =>
			{
				if(!this.select_options.find(opt => opt.value == val))
				{
					this.__appendSelOption(val);
				}
			});
		}

		this.value = values;

		if(!this.multiple)
		{
			values = values.shift();
		}
		this.modelValue = values;
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