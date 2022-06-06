import {SelectOption} from "../Et2Select/FindSelectOptions";
import {css, html, SlotMixin, TemplateResult} from "@lion/core";
import {Et2Select} from "../Et2Select/Et2Select";


export class Et2LinkAppSelect extends SlotMixin(Et2Select)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				--icon-width: 20px;
				display: inline-block;
				min-width: 64px;
			}
			:host([app_icons]) {
				max-width: 75px;
			}
			.select__menu {
				overflow-x: hidden;
			}
			::part(control) {
				border: none;
				box-shadow: initial;
			}
			et2-image {
				width: var(--icon-width);
			}
			::slotted(img), img {
				vertical-align: middle;
			}
			`
		]
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Limit to just this one application, and hide the selection
			 */
			"only_app": {type: String},
			/**
			 * Limit to these applications (comma seperated).
			 */
			"application_list": {type: String},
			/**
			 * Show application icons instead of application names
			 */
			"app_icons": {type: Boolean, reflect: true}
		}
	};

	get slots()
	{
		return {
			...super.slots,
			"": () =>
			{

				const icon = document.createElement("et2-image");
				icon.setAttribute("slot", "prefix");
				icon.setAttribute("src", "api/navbar");
				icon.style.width = "var(--icon-width)";
				return icon;
			}
		}
	}

	protected __application_list : string[];
	protected __only_app : string;

	/**
	 * Constructor
	 *
	 */
	constructor()
	{
		super();
		this.only_app = "";
		this.app_icons = true;
		this.application_list = [];
		this.hoist = true;

		// Select options are based off abilities registered with link system
		this._reset_select_options();

		this._handleChange = this._handleChange.bind(this);
	}

	set only_app(app : string)
	{
		this.__only_app = app || "";
		this.updateComplete.then(() =>
		{
			this.style.display = this.only_app ? 'none' : '';
		});
	}

	get only_app() : string
	{
		// __only_app may be undefined during creation
		return this.__only_app || "";
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Set icon
		this.querySelector("[slot='prefix']").setAttribute("src", this.value + "/navbar");

		// Register to
		this.addEventListener("change", this._handleChange);

		if (this.__only_app) this.style.display = 'none';
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("change", this._handleChange);
	}

	/**
	 * Called before update() to compute values needed during the update
	 *
	 * @param changedProperties
	 */
	willUpdate(changedProperties)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("only_app") || changedProperties.has("application_list"))
		{
			this._reset_select_options();
		}
	}

	set application_list(app_list : string[])
	{
		let oldValue = this.__application_list;
		if(typeof app_list == "string")
		{
			app_list = (<string>app_list).split(",");
		}
		this.__application_list = app_list;
		this.requestUpdate("application_list", oldValue);
	}

	get application_list() : string[]
	{
		return this.__application_list;
	}

	get value()
	{
		return this.only_app ? this.only_app : super.value;
	}

	set value(new_value)
	{
		super.value = new_value;
	}

	private _handleChange(e)
	{
		// Set icon
		this.querySelector("[slot='prefix']").setAttribute("src", this.value + "/navbar");

		// update preference
		let appname = "";
		if(typeof this.value != 'undefined' && this.parentNode && this.parentNode.to_app)
		{
			appname = this.parentNode.to_app;
		}
		this.egw().set_preference(appname || this.egw().app_name(), 'link_app', this.value);
	}

	/**
	 * Limited select options here
	 * This method will check properties and set select options appropriately
	 */
	private _reset_select_options()
	{
		let select_options = [];

		// Limit to one app
		if(this.only_app)
		{
			select_options.push({value: this.only_app, label: this.egw().lang(this.only_app)});
		}
		else if(this.application_list.length > 0)
		{
			select_options = this.application_list;
		}
		else
		{
			//@ts-ignore link_app_list gives {app:name} instead of an array, but parent will fix it
			select_options = this.egw().link_app_list('query');
			if(typeof select_options['addressbook-email'] !== 'undefined')
			{
				delete select_options['addressbook-email'];
			}
		}
		if (!this.value)
		{
			this.value = <string>this.egw().preference('link_app', this.egw().app_name());
		}
		this.select_options = select_options;
	}


	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html`
            <sl-menu-item value="${option.value}" title="${option.title}">
                ${this.app_icons ? "" : option.label}
                ${this._iconTemplate(option.value)}
            </sl-menu-item>`;
	}

	_iconTemplate(appname)
	{
		let url = appname ? this.egw().image('navbar', appname) : "";
		return html`
            <et2-image style="width: var(--icon-width)" slot="prefix" src="${url}"></et2-image>`;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-link-apps", Et2LinkAppSelect);