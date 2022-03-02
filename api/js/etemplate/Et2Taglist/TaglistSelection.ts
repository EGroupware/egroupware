/**
 * EGroupware eTemplate2 - TaglistSelection (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */



import {css, html, LitElement} from "@lion/core";
import {TaglistComboBox} from "./TaglistComboBox";
import {taglistStyles} from "./TaglistStyles";


/**
 * Implementation of selection tags
 */
export class TaglistSelection extends LitElement {
	static get properties() {
		return {
			selectedTags: Array,
			comboxElement: TaglistComboBox,
			canBeClosed: Boolean,
			canBeEdited: Boolean
		}
	};

	static get styles() {
		return [
			taglistStyles,
			css`
			:host {
				display: flex;
			}
			
			.taglist-selection__tags {
				flex: none;
			}
			
			.combobox__input {
				display: block;
			}
			
			.taglist-selection__tag {
				margin: 0 5px 3px 0;
				padding: 3px 20px 3px 5px;
				border: 1px solid var(--taglist-selection__tag-boder-color);
				border-radius: 3px;
				background-color: var(--taglist-selection__tag-bg-color);
				background-image: var(--taglist-selection__tag-bg-img);
				background-clip: padding-box;
				box-shadow: var(--taglist-selection__tag-box-shadow);
				color: var(--taglist-selection__tag-color);
				line-height: 13px;
				font-size: 11px;
				white-space: normal;
				max-width: calc(100% - 30px);
				display:flex;
			}
			.tag-label {
				display:flex;
			}
			.tag-closeBtn{
				width: 10px;
				height: 10px;
				background-position: 0 -10px;
				background-size: cover;
				background-repeat: no-repeat;
				display:flex;
				background-image: var(--tag-closeBtn-img);
			}
			`
    	];
	}

	/**
	 *
	 */
	get _inputNode() {
		return this._getComboBoxElement()._inputNode;
	}


	/**
	 * @return {TaglistComboBox} returns comboboxElement from TaglistComboBox
	 */
	_getComboBoxElement()
	{
		// @ts-ignore
		return this.comboboxElement;
	}

	/**
	 * @private
	 * @return returns checked formElements
	 */
	__getSelectedTags() {
		return this._getComboBoxElement().formElements.filter((_tags) => {
				return _tags.checked;
			}
		);
	}

	get multipleChoice() {
		return this._getComboBoxElement()?.multipleChoice;
	}


	constructor() {
		super();

		this.selectedTags = [];
		this.__handleCloseBtn = this.__handleCloseBtn.bind(this);
		this.__handleEditBtn = this.__handleEditBtn.bind(this);
		this.__inputOnKeyup = this.__inputOnKeyup.bind(this);

	}

	/**
	 *
	 * @param changedProperties
	 */
	firstUpdated(changedProperties) {
		super.firstUpdated(changedProperties);

		if (this.multipleChoice) {
			this._inputNode.addEventListener('keyup', this.__inputOnKeyup);
		}
	}

	/**
	 *
	 * @param changedProperties
	 */
	onComboboxElementUpdated(changedProperties) {
		if (changedProperties.has('modelValue')) {
			this.selectedTags = this.__getSelectedTags();
		}
	}

	__handleEditBtn()
	{
		console.log('editBtn')
	}

	__handleCloseBtn(_v)
	{
		console.log('closeBtn')
	}

	/**
	 *
	 * @param option
	 */
	_selectedTagTemplate(option) {
		return html`
			<div class="taglist-selection__tag">
                ${this._canBeEdited? html`<span class="tag-editBtn" @click="${this.__handleEditBtn}"></span>`:''}
				<span class="tag-label">${option.value}</span>
				${this._canBeClosed? html`<span class="tag-closeBtn" @click="${this.__handleCloseBtn}"></span>`:''}
			</div>
    `;
	}

	/**
	 *
	 */
	_selectedTagsTemplate() {
		return html`
		  <div class="taglist-selection__tags">
			${this.selectedTags.map((option) => {
				return this._selectedTagTemplate(option);
			})}
		  </div>
    `;
	}

	/**
	 *
	 */
	render() {
		return html` ${this._selectedTagsTemplate()} `;
	}

	/**
	 * @private
	 * @param ev
	 */
	__inputOnKeyup(ev) {
		if (ev.key === 'Backspace') {
			if (!this._inputNode.value) {
				if (this.selectedTags.length) {
					this.selectedTags[this.selectedTags.length - 1].checked = false;
				}
			}
		}
	}
}
customElements.define('taglist-selection', TaglistSelection);
