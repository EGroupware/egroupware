/**
 * EGroupware eTemplate2 - Readonly HTML area widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, html, LitElement, nothing} from "lit";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {classMap} from "lit/directives/class-map.js";
import {property} from "lit/decorators/property.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import type {et2_IDetachedDOM} from "../et2_core_interfaces";
import type {HtmlAreaMode} from "./Et2HtmlAreaConfig";

/**
 * Lightweight readonly HTML area used by readonly widget substitution.
 *
 * It intentionally avoids TinyMCE and textarea setup; rich-text values are
 * rendered as HTML, while `mode="ascii"` renders the value as literal text.
 */
export class Et2HtmlAreaReadonly extends Et2InputWidget(LitElement) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: block;
					width: 100%;
					min-width: 0;
				}

				.form-control {
					display: block;
					min-height: 0;
				}

				.form-control-input,
				.htmlarea__readonly {
					display: block;
					min-height: 0;
					min-width: 0;
				}

				.htmlarea__readonly {
					overflow-wrap: anywhere;
				}

				.htmlarea__readonly--ascii {
					white-space: pre-wrap;
				}

				.htmlarea__readonly > :first-child {
					margin-block-start: 0;
				}

				.htmlarea__readonly > :last-child {
					margin-block-end: 0;
				}
			`
		];
	}

	@property({type: String})
	value = "";

	@property({type: String})
	mode : HtmlAreaMode | string = "";

	constructor()
	{
		super();
		this.readonly = true;
	}

	protected get _isAsciiMode() : boolean
	{
		return this.mode === "ascii";
	}

	getDetachedAttributes(attrs : string[]) : void
	{
		attrs.push("id", "label", "value", "class", "mode", "statustext");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], values : Record<string, any>, _data? : any) : void
	{
		for(const attr in values)
		{
			this[attr] = values[attr];
		}
	}

	render()
	{
		const labelTemplate = this._labelTemplate();
		const helpTextTemplate = this._helpTextTemplate();
		const value = this.value ?? "";

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        "form-control": true,
                        "form-control--medium": true,
                        "form-control--has-label": labelTemplate !== nothing,
                        "form-control--has-help-text": helpTextTemplate !== nothing
                    })}
            >
				${labelTemplate}
				<div part="form-control-input" class="form-control-input">
					<div
							part="readonly-content"
							class=${classMap({
								"htmlarea__readonly": true,
								"htmlarea__readonly--ascii": this._isAsciiMode
							})}
					>
						${this._isAsciiMode ? html`${value}` : unsafeHTML(value)}
					</div>
				</div>
                ${helpTextTemplate}
            </div>
		`;
	}
}

customElements.define("et2-htmlarea_ro", Et2HtmlAreaReadonly);
