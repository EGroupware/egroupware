import {cleanSelectOptions, SelectOption} from "../Et2Select/FindSelectOptions";
import {css, html, TemplateResult} from "lit";
import {Et2Select} from "../Et2Select/Et2Select";


export class Et2LinkAppSelect extends Et2Select
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
			:host([appIcons]) {
				max-width: 75px;
			}
			.select__menu {
				overflow-x: hidden;
			}
			::part(control) {
				border: none;
				box-shadow: initial;
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
			onlyApp: {type: String},
			/**
			 * Limit to these applications (comma seperated).
			 */
			applicationList: {type: String},
			/**
			 * Show application icons instead of application names
			 */
			appIcons: {type: Boolean, reflect: true}
		}
	};

	/*
					icon.style.width = "var(--icon-width)";
					icon.style.height = "var(--icon-width)";

	 */

	protected __applicationList : string[];
	protected __onlyApp : string;

	/**
	 * Constructor
	 *
	 */
	constructor()
	{
		super();
		this.onlyApp = "";
		this.appIcons = true;
		this.applicationList = [];
		this.hoist = true;

		// Select options are based off abilities registered with link system
		this._reset_select_options();
	}

	set onlyApp(app : string)
	{
		this.__onlyApp = app || "";
		this.updateComplete.then(() =>
		{
			this.style.display = this.onlyApp ? 'none' : '';
		});
	}

	get onlyApp() : string
	{
		// __onlyApp may be undefined during creation
		return this.__onlyApp || "";
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(!this.value)
		{
			// use preference
			let appname = "";
			if(typeof this.value != 'undefined' && this.parentNode && this.parentNode.toApp)
			{
				appname = this.parentNode.toApp;
			}
			this.value = this.egw().preference('link_app', appname || this.egw().app_name());
		}
		// Register to
		this.addEventListener("sl-change", this._handleChange);

		if(this.__onlyApp)
		{
			this.style.display = 'none';
		}
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("sl-change", this._handleChange);
	}

	/**
	 * Called before update() to compute values needed during the update
	 *
	 * @param changedProperties
	 */
	willUpdate(changedProperties)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("onlyApp") || changedProperties.has("applicationList"))
		{
			this._reset_select_options();
		}
	}

	set applicationList(app_list : string[])
	{
		let oldValue = this.__applicationList;
		if(typeof app_list == "string")
		{
			app_list = (<string>app_list).split(",");
		}
		this.__applicationList = app_list;
		this.requestUpdate("applicationList", oldValue);
	}

	get applicationList() : string[]
	{
		return this.__applicationList;
	}

	get value() : string
	{
		return this.onlyApp ? this.onlyApp : <string>super.value;
	}

	set value(new_value)
	{
		super.value = new_value;
	}

	handleValueChange(e)
	{
		super.handleValueChange(e);

		// update preference
		let appname = "";
		if(typeof this.value != 'undefined' && this.parentNode && this.parentNode.toApp)
		{
			appname = this.parentNode.toApp;
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
		if(this.onlyApp)
		{
			select_options.push({
				value: this.onlyApp,
				label: this.egw().lang(this.onlyApp),
				icon: this.egw().link_get_registry(this.onlyApp, 'icon') ?? this.onlyApp + "/navbar"
			});
		}
		else if(this.applicationList.length > 0)
		{
			select_options = this.applicationList.map((app) =>
			{
				return {
					value: app,
					label: this.egw().lang(app),
					icon: this.egw().link_get_registry(app, 'icon') ?? app + "/navbar"
				};
			});
		}
		else
		{
			//@ts-ignore link_app_list gives {app:name} instead of an array, but parent will fix it
			select_options = this.egw().link_app_list('query');
			if(typeof select_options['addressbook-email'] !== 'undefined')
			{
				delete select_options['addressbook-email'];
			}
			select_options = cleanSelectOptions(select_options);
			select_options.map((option) =>
			{
				option.icon = this.egw().link_get_registry(option.value, 'icon') ?? option.value + "/navbar"
			});
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
            <sl-option value="${option.value}" title="${option.title}">
                ${this.appIcons ? "" : option.label}
                ${this._iconTemplate(option)}
            </sl-option>`;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-link-apps", Et2LinkAppSelect);