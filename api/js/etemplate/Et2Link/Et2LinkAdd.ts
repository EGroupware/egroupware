import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {css, html, LitElement, nothing} from "lit";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {LinkInfo} from "./Et2Link";
import {Et2Button} from "../Et2Button/Et2Button";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";

/**
 * Find and select a single entry using the link system.
 *
 *
 */
@customElement("et2-link-add")
export class Et2LinkAdd extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				.form-control {
					display: flex;
					align-items: center;
					flex-wrap: wrap;
				}

				.form-control-input {
					display: flex;
					flex: 1 1 auto;
					position: relative;
					max-width: 100%;
			}
			`
		];
	}

	/**
	 * Either an array of LinkInfo (defined in Et2Link.ts) or array with keys to_app and to_id
	 */
	@property({type: Object})
	value : LinkInfo[] & { to_app : string, to_id : string }
	/**
	 * Limit to the listed applications (comma seperated)
	 */
	@property()
	applicationList : string

	/**
	 * @type {LinkInfo}
	 * @private
	 */
	private _value : LinkInfo;

	constructor()
	{
		super();

		this.handleButtonClick = this.handleButtonClick.bind(this);
	}

	/**
	 * Limit to just this application - hides app selection
	 */
	@property()
	set application(app)
	{
		app = app || "";

		// If initial value got set before only_app, it still needs app in pre-render value
		if(this.value && app)
		{
			this.value.app = app;
		}
		this.requestUpdate("application")
	}

	get application()
	{
		return this.value?.app;
	}

	get _appNode() : Et2LinkAppSelect
	{
		return this.shadowRoot.querySelector("et2-link-apps");
	}

	get _buttonNode() : Et2Button
	{
		return this.shadowRoot.querySelector("et2-button");
	}

	/**
	 * Add button was clicked
	 * @param {MouseEvent} e
	 */
	handleButtonClick(e : MouseEvent)
	{
		this.egw().open(this.value.to_app + ":" + this.value.to_id, this._appNode.value, 'add');
	}

	render()
	{
		const hasLabel = this.label ? true : false;
		const hasHelpText = this.helpText ? true : false;
		const isEditable = !(this.disabled || this.readonly);

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'link-add': true,
                        'link-add__readonly': !isEditable,
                        'vlink-add__disabled': this.disabled,
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': hasLabel,
                        'form-control--has-help-text': hasHelpText
                    })}
            >
                <label
                        id="label"
                        part="form-control-label"
                        class="form-control__label"
                        aria-hidden=${hasLabel ? 'false' : 'true'}
                >
                    <slot name="label">${this.label}</slot>
                </label>
                <div part="form-control-input" class="form-control-input">
                    <slot part="prefix" name="prefix"></slot>
                    <et2-link-apps
                            onlyApp=${this.application || nothing}
                            applicationList=${this.applicationList || nothing}
                            ?disabled=${this.disabled}
                            ?readonly=${this.readonly}
                            .value=${this.value?.app}
                    ></et2-link-apps>
                    <et2-button
                            id=${this.id + "_add"}
                            image="add"
                            aria-label=${this.egw().lang("Add entry")}
                            ?disabled=${this.disabled}
                            ?readonly=${this.readonly}
                            noSubmit
                            @click=${this.handleButtonClick}
                    ></et2-button>
                    <slot part="suffix" name="suffix"></slot>
                </div>
            </div>
		`;
	}
}