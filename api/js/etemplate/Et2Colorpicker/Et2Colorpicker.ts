/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */



import {css, html} from "@lion/core";
import {LionInput} from "@lion/input";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

export class Et2Colorpicker extends Et2InputWidget(Et2Widget(LionInput))
{
	private cleared: boolean = true;

	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: flex;
			}
			div:hover > span.clear-icon {
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

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	render()
	{
		return html`
            <div class="et2_colorpicker" id="${this.id}">
                <input class="et2_colorpicker" type="color" @change="${this._handleChange}"/>
				<span class="clear-icon" @click="${this._handleClickClear}"></span>
            </div>
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
		let value = this._inputNode.value;
		if (this.cleared || value === '#FFFFFF' || value === '#ffffff') {
			return '';
		}
		return value;
	}

	set_value(color)
	{
		if(!color)
		{
			color = '';
		}
		this.cleared = !color;
		this._inputNode.value = color;
	}
}
customElements.define('et2-colorpicker', Et2Colorpicker);