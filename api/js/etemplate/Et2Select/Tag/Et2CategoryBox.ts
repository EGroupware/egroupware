/**
 * EGroupware eTemplate2 - Category Box WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {css, html, LitElement, nothing, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../../et2_core_interfaces";
import "../../Layout/Et2Box/Et2Box";
import "./Et2CategoryTag";

export interface Et2CategoryBoxOption
{
	value : string;
	label : string;
}

/**
 * A horizontal row of Et2CategoryTag chips, one per {value, label} entry in `value`.
 *
 * Purely a renderer of whatever it's given - an empty value renders nothing at all, so it never
 * leaves a stray gap in a flex-parent. The tags are declared as children of the inner
 * <et2-hbox> in the same template, not added imperatively afterwards - that hbox sees them
 * already present the first time it ever renders, so its own layout is correct from the start.
 */
@customElement("et2-category-box")
export class Et2CategoryBox extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	@property({type: Array}) value : Et2CategoryBoxOption[] = [];

	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: contents;
				}
				et2-category-tag {
					flex: 0 0 auto;
					&::part(base){
						padding: 0;
					}
				}
			`,
		];
	}

	render() : TemplateResult
	{
		if(!this.value?.length)
		{
			return nothing;
		}
		return html`
			<et2-hbox part="flexContainer">
				${this.value.map(item => html`
					<et2-category-tag value=${item.value}>${item.label}</et2-category-tag>
				`)}
			</et2-hbox>
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
