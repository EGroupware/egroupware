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
import {taglistStyles} from "./TaglistStyles";


/**
 * Implementation of selection tags
 */
export class TaglistSelection extends LitElement {
	static get properties() {
		return {
			comboxElement: {type: Object},
		}
	};

	static get styles() {
		return [
			taglistStyles,
			css`
			:host {
				display: flex;
				padding: 2px;
    			padding-top: 0px;
			}
			
			.taglist-selection__tags {
				flex: 0 0 auto;
				display: flex;
				flex-direction: row;
				gap:5px;
			}
			
			.combobox__input {
				display: block;
			}
			
			.taglist-selection__tag {
				margin: 2px 0px 0px 0px;
				padding: 3px 5px;
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
				display: flex;
				gap: 10px;
				flex-direction: row;
			}
			.tag-label {
				display: flex;
				flex-basis: auto;
				flex-direction: column;
				flex-grow: 1;
				justify-content: center;
			}
			.tag-btn {
				width: 10px;
				height: 10px;
				background-position: 0px -10px;
				background-size: cover;
				background-repeat: no-repeat;
				display: flex;
				align-self: center;
				cursor: pointer;
			}
			.tag-editBtn{background-image: var(--tag-editBtn-img);}
			.tag-editBtn:hover{background-position: 0 0px;}
			.tag-closeBtn{background-image: var(--tag-closeBtn-img);}
			.tag-closeBtn:hover {background-position: 0px 0px;}
			`
		];
	}

	/**
	 *
	 */
	get _inputNode()
	{
		return this._getComboBoxElement()._inputNode;
	}


	/**
	 * @return {Et2Taglist} returns comboboxElement from TaglistComboBox
	 */
	protected _getComboBoxElement()
	{
		// @ts-ignore
		return <Et2Taglist> this.parentElement;
	}

	/**
	 * check if the tag can be closed
	 * @protected
	 */
	protected _canBeClosed()
	{
		return this._getComboBoxElement().multiple && !this._getComboBoxElement().readonly;
	}

	/**
	 * @private
	 * @return returns checked formElements
	 */
	private __getSelectedTags() {
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


	__handleEditBtn(e)
	{
		const selected = this.__getSelectedTags()[parseInt(e.target.parentElement.dataset.index)];
		this.__getSelectedTags()[parseInt(e.target.parentElement.dataset.index)].checked = false;
		this._getComboBoxElement()._inputNode.value = selected.value;
	}

	__handleCloseBtn(e)
	{
		this.__getSelectedTags()[parseInt(e.target.parentElement.dataset.index)].checked = false;
	}

	/**
	 *
	 * @param option
	 */
	_selectedTagTemplate(option, index)
	{
		return html`
            <div class="taglist-selection__tag" data-index=${index}>
                ${this._getComboBoxElement().editModeEnabled ? html`<span class="tag-btn tag-editBtn" @click="${this.__handleEditBtn}"></span>` : ''}
                <span class="tag-label">${option.label}</span>
                ${this._canBeClosed() ? html`<span class="tag-btn tag-closeBtn" @click="${this.__handleCloseBtn}"></span>` : ''}
            </div>
		`;
	}

	/**
	 *
	 */
	_selectedTagsTemplate() {
		return html`
            <div class="taglist-selection__tags">
                ${this.__getSelectedTags().map((option, index) =>
                {
                    return this._selectedTagTemplate(option, index);
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
			if (!this._inputNode.value && this._canBeClosed()) {
				if (this.__getSelectedTags().length) {
					this.__getSelectedTags()[this.__getSelectedTags().length - 1].checked = false;
				}
			}
		}
		//todo: setting a new option value changes option indexes therefore the last activeIndex should be adopted according
		// to our new options' indexes. We need to figure out how to set that index before the last selected option gets unchecked.
		this._getComboBoxElement().activeIndex = this.__getSelectedTags().length;
	}
}
customElements.define('taglist-selection', TaglistSelection);
