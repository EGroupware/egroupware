/**
 * EGroupware eTemplate2 - Html WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

import {Et2Widget} from "../Et2Widget/Et2Widget";
import {html, LitElement, nothing, PropertyValues} from "lit";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {et2_IDetachedDOM} from "../et2_core_interfaces";

/**
 * @summary Displays raw HTML content (from the content array or a set value), with a simple
 * optional label prefix - ported from legacy et2_html/et2_widget_html.ts.
 *
 * Renders into the light DOM (createRenderRoot() returns `this`, same technique as Et2Image/
 * Et2VfsMime/Et2Customfields) rather than a shadow root - arbitrary provided HTML/CSS classes
 * need to inherit normal page styling, which shadow-DOM encapsulation would silently break.
 *
 * Legacy et2_html ran embedded `<script>` blocks (via jQuery's .append(), which executes them) -
 * lit's unsafeHTML() inserts them the same inert way plain innerHTML does, so each script element
 * is recreated after render to force execution, preserving that behaviour.
 */
export class Et2Html extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	static get properties()
	{
		return {
			...super.properties,
			value: {type: String, noAccessor: true}
		};
	}

	private _value : string = "";

	get value() { return this._value; }

	set value(_value : string)
	{
		const oldValue = this._value;
		this._value = _value || "";
		this.requestUpdate('value', oldValue);
	}

	set_value(_value : string)
	{
		this.value = _value;
	}

	protected createRenderRoot()
	{
		return this;
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has('value'))
		{
			this._executeScripts();
		}
	}

	private _executeScripts()
	{
		this.querySelectorAll('script').forEach((oldScript : HTMLScriptElement) =>
		{
			const newScript = document.createElement('script');
			for(const attr of Array.from(oldScript.attributes))
			{
				newScript.setAttribute(attr.name, attr.value);
			}
			newScript.textContent = oldScript.textContent;
			oldScript.replaceWith(newScript);
		});
	}

	render()
	{
		return html`${this.label ? html`<span class="et2_label">${this.label}</span>` : nothing}${unsafeHTML(this._value)}`;
	}

	/*
	 * et2_IDetachedDOM - nextmatch/datagrid row virtualization support, matching legacy et2_html
	 */
	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("value", "label", "class");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object)
	{
		for(const key in _values)
		{
			if(typeof _values[key] !== "undefined")
			{
				this[key] = _values[key];
			}
		}
	}
}
customElements.define("et2-html", Et2Html);
