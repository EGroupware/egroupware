/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */



import {css, html, SlotMixin, render, RenderOptions} from "@lion/core";
import {LionInput} from "@lion/input";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

export class Et2Colorpicker extends Et2InputWidget(Et2Widget(SlotMixin(LionInput)))
{
	private readonly cleared_value = '#FEFEFE';	// as input type=color interprets everything not a "#rrggbb" string as black, we use this for no value selected for now

	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: flex;
			}
			.input-group__suffix{
				width: 12px;
				height: 12px;
			}
			.input-group__container {
				align-items: center
			}
			:host(:hover) ::slotted([slot="suffix"]) {
				width: 12px;
				height: 12px;
				display: inline-flex;
				background-image: url(pixelegg/images/close.svg);
				background-size: 10px;
				background-position: center;
				background-repeat: no-repeat;
				cursor: pointer;
			}
			`,
		];
	}

	get slots()
	{
		return {
			...super.slots,
			input: () => this.__getInputNode(),
			suffix: () => this.__getClearButtonNode()
		}
	}

	constructor()
	{
		super();

		// Override the default type of "text"
		this.type = 'color';

		// Bind the handlers, since slots bind without this as context
		this._handleChange = this._handleChange.bind(this);
		this._handleClickClear = this._handleClickClear.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	__getInputNode()
	{
		const renderParent = document.createElement('div');
		render(
			this._inputTemplate(),
			renderParent,
			<RenderOptions>({
				scopeName: this.localName,
				eventContext: this,
			}),
		);
		return renderParent.firstElementChild;
	}

	_inputTemplate()
	{
		return html`
            <input type="color" .value="${this.value || this.cleared_value}" onchange="${this._handleChange}"/>
		`;
	}

	/**
	 * Get the clear button node
	 * @returns {Element|null}
	 */
	__getClearButtonNode()
	{
		const renderParent = document.createElement('div');
		render(
			this._clearButtonTemplate(),
			renderParent,
			<RenderOptions>({
				scopeName: this.localName,
				eventContext: this,
			}),
		);
		return renderParent.firstElementChild;
	}

	_clearButtonTemplate()
	{
		return html`
            <span class="clear-icon" @click="${this._handleClickClear}"></span>
		`;
	}

	_handleChange(e)
	{
		this.set_value(e.target.value);
	}

	_handleClickClear()
	{
		this.set_value('');
	}

	getValue()
	{
		if (this.value.toUpperCase() === this.cleared_value)
		{
			return '';
		}
		return this.value;
	}

	set_value(color)
	{
		if (!color)
		{
			color = this.cleared_value;
		}
		this.value = color;
	}
}
customElements.define('et2-colorpicker', Et2Colorpicker);