import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {FormControlMixin, ValidateMixin} from "@lion/form-core";
import {css, html, LitElement, PropertyValues, SlotMixin} from "@lion/core";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {LinkInfo} from "./Et2Link";
import {Et2Button} from "../Et2Button/Et2Button";

/**
 * Find and select a single entry using the link system.
 *
 *
 */
export class Et2LinkAdd extends Et2InputWidget(FormControlMixin(ValidateMixin(SlotMixin(LitElement))))
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: block;
				border: solid var(--sl-input-border-width) var(--sl-input-border-color);
    			border-radius: var(--sl-input-border-radius-medium);
			}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Either an array of LinkInfo (defined in Et2Link.ts) or array with keys to_app and to_id
			 */
			value: {type: Object},
			/**
			 * Limit to just this application - hides app selection
			 */
			application: {type: String},
			/**
			 * Limit to the listed applications (comma seperated)
			 */
			applicationList: {type: String}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			app: () =>
			{
				const app = <Et2LinkAppSelect>document.createElement("et2-link-apps");
				app.appIcons = false;
				if(this.application)
				{
					app.onlyApp = this.application;
				}
				else if(typeof this._value !== "undefined" && this._value.app)
				{
					app.value = this._value.app;
				}
				if(this.applicationList)
				{
					app.applicationList = this.applicationList;
				}
				return app;
			},
			button: () =>
			{
				const button = <Et2Button>document.createElement("et2-button");
				button.id = this.id + "_add";
				button.label = this.egw().lang("Add")

				return button;
			}
		}
	}

	/**
	 * We only care about this value until render.  After the sub-nodes are created,
	 * we take their "live" values for our value.
	 *
	 * N.B.: Single underscore!  Otherwise we conflict with parent __value
	 *
	 * @type {LinkInfo}
	 * @private
	 */
	private _value : LinkInfo;

	constructor()
	{
		super();

		this.handleButtonClick = this.handleButtonClick.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Clear initial value, no longer needed
		this._value = undefined;

		this._bindListeners();
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has("readonly"))
		{
			this._appNode.readonly = this.readonly;
		}
		// Pass some properties on to app selection
		if(changedProperties.has("only_app"))
		{
			this._appNode.only_app = this.only_app;
		}
		if(changedProperties.has("applicationList"))
		{
			this._appNode.applicationList = this.applicationList;
		}
		if(changedProperties.has("app_icons"))
		{
			this._appNode.app_icons = this.app_icons;
		}
	}

	set application(app)
	{
		app = app || "";

		// If initial value got set before only_app, it still needs app in pre-render value
		if(this._value && app)
		{
			this._value.app = app;
		}
		if(this._appNode)
		{
			this._appNode.value = app;
		}
	}

	get application()
	{
		if(this._value)
		{
			return this._value.app;
		}
		if(this._appNode)
		{
			return this._appNode.value;
		}
	}

	get _appNode() : Et2LinkAppSelect
	{
		return this.querySelector("[slot='app']");
	}

	get _buttonNode() : Et2Button
	{
		return this.querySelector("[slot='button']");
	}

	/**
	 * @return {TemplateResult}
	 * @protected
	 */
	_inputGroupInputTemplate()
	{
		return html`
            <div class="input-group__input">
                <slot name="app"></slot>
                <slot name="button"></slot>
            </div>
		`;
	}

	protected _bindListeners()
	{
		//this._appNode.addEventListener("change", this._handleAppChange);
		this._buttonNode.addEventListener("click", this.handleButtonClick)
	}

	protected _unbindListeners()
	{
		this._buttonNode.removeEventListener("click", this.handleButtonClick)
	}

	/**
	 * Add button was clicked
	 * @param {MouseEvent} e
	 */
	handleButtonClick(e : MouseEvent)
	{
		this.egw().open(this.value.to_app + ":" + this.value.to_id, this._appNode.value, 'add');
	}
}

customElements.define("et2-link-add", Et2LinkAdd);