/**
 * EGroupware eTemplate2 - Category Box WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {css, html, LitElement, TemplateResult} from "lit";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../../et2_core_interfaces";
import "./Et2CategoryTag";

export interface Et2CategoryBoxOption
{
	value : string;
	label : string;
}

/**
 * A horizontal row of Et2CategoryTag chips, one per {value, label} entry in `value`.
 *
 * Purely a renderer of whatever it's given - it has no opinion on how many entries justify
 * showing anything; an empty value collapses to nothing, and callers decide what (and how many)
 * entries to pass.
 */
export class Et2CategoryBox extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	private _value : Et2CategoryBoxOption[] = [];

	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                gap: var(--sl-spacing-2x-small);
            }
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			value: {type: Array}
		};
	}

	get value() : Et2CategoryBoxOption[]
	{
		return this._value;
	}

	set value(new_value : Et2CategoryBoxOption[])
	{
		const oldValue = this._value;
		this._value = Array.isArray(new_value) ? new_value : [];
		this.requestUpdate("value", oldValue);
	}

	set_value(new_value : Et2CategoryBoxOption[])
	{
		this.value = new_value;
	}

	render() : TemplateResult
	{
		// Nothing to show collapses the host to zero size, so an empty box doesn't leave a stray
		// gap in a flex-parent (eg. rows.less's tr.mail et2-vbox::part(base) gap).
		this.style.display = this._value.length ? "" : "none";

		return html`
            ${this._value.map(item => html`
				<et2-category-tag value=${item.value}>${item.label}</et2-category-tag>
			`)}
		`;
	}

	getDetachedAttributes(attrs)
	{
		attrs.push("id", "value", "class");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}

customElements.define("et2-category-box", Et2CategoryBox);
