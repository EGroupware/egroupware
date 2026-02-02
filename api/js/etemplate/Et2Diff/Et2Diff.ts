import {customElement} from "lit/decorators/custom-element.js";
import {css, html, LitElement, nothing, PropertyValueMap, render} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import * as Diff2Html from "diff2html";
import {Diff2HtmlConfig} from "diff2html";
import {ColorSchemeType} from "diff2html/lib/types";
import {property} from "lit/decorators/property.js";
import shoelace from "../Styles/shoelace";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";

/**
 * Show a nicely formatted diff
 */
@customElement("et2-diff")
export class Et2Diff extends Et2InputWidget(LitElement)
{
	@property({type: Boolean, reflect: true})
	open = false;

	/**
	 * Disable the dialog and show the whole diff
	 *
	 * @type {boolean}
	 */
	@property({type: Boolean, reflect: true})
	noDialog = false;

	// CSS in etemplate2.css due to library
	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			css`
				:host {
					position: relative;
				}

				.expand-icon {
					display: none;
					position: absolute;
					bottom: var(--sl-spacing-medium);
					right: var(--sl-spacing-medium);
					background-color: var(--sl-panel-background-color);
					z-index: 1;
				}

				:host(:hover) {
					.expand-icon {
						display: initial;
					}
				}

				:host(:not([open])) {
					cursor: pointer;
				}

				:host(:not([noDialog])) .form-control-input {
					max-height: 9em;
					overflow: hidden;
				}
			`
		];
	}

	/**
	 * Always return false as a et2-diff is never dirty
	 */
	isDirty()
	{
		return false;
	}

	private readonly diff_options : Diff2HtmlConfig = {
		matching: "words",
		drawFileList: false,
		colorScheme: ColorSchemeType.AUTO
	};

	updated(changedProperties : PropertyValueMap<any>)
	{
		if(changedProperties.has("value") || this.value && this.childElementCount == 0)
		{
			// Put diff into lightDOM so styles can leak, since we can't import the library CSS into the component
			render(html`${unsafeHTML(Diff2Html.html(this.value ?? "", this.diff_options))}`, this, {host: this});
		}
	}

	set value(value : string)
	{
		if(typeof value == 'string')
		{

			// Diff2Html likes to have files, we don't have them
			if(value.indexOf('---') !== 0)
			{
				value = "--- diff\n+++ diff\n" + value;
			}

			super.value = value;
			this.requestUpdate("value");
		}
	}

	_handleClick(e)
	{
		const oldValue = this.getAttribute("open")
		this.toggleAttribute("open");
		this.requestUpdate("open", oldValue);
	}

	getDetachedAttributes(attrs)
	{
		attrs.push("id", "value", "class");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data? : any) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}

	render()
	{
		const labelTemplate = this._labelTemplate();
		const helpTextTemplate = this._helpTextTemplate();
		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': labelTemplate !== nothing,
                        'form-control--has-help-text': helpTextTemplate !== nothing
                    })}
            >
                ${labelTemplate}
                <div part="form-control-input" class="form-control-input">
                    <!-- Actual diff goes into lightDOM since we can't import the CSS directly -->
                    ${this.open && !this.noDialog ?
                      html`
                          <et2-dialog
                                  part=dialog" label="Diff" open
                                  buttons=${Et2Dialog.BUTTONS_OK}
                                  @click=${(e) =>
                                  {
                                      // Stop bubble or it will re-show dialog
                                      e.stopPropagation()
                                  }}
                                  @close=${() =>
                                  {
                                      this.removeAttribute("open");
                                      this.requestUpdate("open", true);
                                  }}
                          >
                              <slot></slot>
                          </et2-dialog>` : html`
							  ${!this.noDialog ? html`
                                <et2-button-icon
                                        part="expand-icon"
                                        class="expand-icon"
                                        image="arrows-fullscreen" label="View" noSubmit></et2-button-icon>` : nothing}
                          <slot></slot>`
                    }
                </div>
                ${helpTextTemplate}
            </div>
		`;
	}
}