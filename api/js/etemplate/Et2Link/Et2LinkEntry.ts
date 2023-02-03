/**
 * EGroupware eTemplate2 - Search & select link entry WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, html, LitElement, PropertyValues, SlotMixin} from "@lion/core";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {FormControlMixin} from "@lion/form-core";
import {Et2LinkSearch} from "./Et2LinkSearch";
import {Et2Link, LinkInfo} from "./Et2Link";

/**
 * Find and select a single entry using the link system.
 *
 *
 */
export class Et2LinkEntry extends Et2InputWidget(FormControlMixin(SlotMixin(LitElement)))
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			  :host {
				display: block;
			  }

			  :host(.hideApp) ::slotted([slot="app"]) {
				display: none;
			  }

			  .input-group__input {
				gap: 0.5rem;
			  }

			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			value: {type: Object},
			/**
			 * Limit to just this application - hides app selection
			 */
			onlyApp: {type: String},
			/**
			 * Limit to the listed applications (comma seperated)
			 */
			applicationList: {type: String},
			/**
			 * Show just application icons instead of names
			 */
			appIcons: {type: Boolean},
			/**
			 * Callback before query to server.
			 * It will be passed the request & et2_link_entry objects.  Must return true, or false to abort query.
			 */
			query: {type: Function},
			/**
			 * Callback when user selects an option.  Must return true, or false to abort normal action.
			 */
			select: {type: Function},

			/**
			 * Displayed in the search / select when no value is selected
			 */
			placeholder: {type: String}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			app: () =>
			{
				const app = <Et2LinkAppSelect>document.createElement("et2-link-apps")
				if(this.__onlyApp)
				{
					app.onlyApp = this.__onlyApp;
				}
				else if(typeof this._value !== "undefined" && this._value.app)
				{
					app.value = this._value.app;
				}
				return app;
			},
			select: () =>
			{
				const select = <Et2LinkSearch><unknown>document.createElement("et2-link-search");
				if(typeof this._value !== "undefined" && this._value.id)
				{
					if(this._value.title)
					{
						select.select_options = [{value: this._value.id, label: this._value.title}]
					}
					select.app = this._value.app;
					select.value = this._value.id;
				}
				return select;
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

	protected __onlyApp : string;

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();

		this._handleAppChange = this._handleAppChange.bind(this);
		this._handleEntrySelect = this._handleEntrySelect.bind(this);
		this._handleEntryClear = this._handleEntryClear.bind(this);
		this._handleShow = this._handleShow.bind(this);
		this._handleHide = this._handleHide.bind(this);

		// Clear initial value
		this._value = undefined;

		if(!this.readonly)
		{
			this._bindListeners();
		}
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._unbindListeners();
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has("required"))
		{
			this._searchNode.required = this.required;
		}
		if(changedProperties.has("readonly"))
		{
			this._appNode.readonly = this._appNode.disabled = this.readonly;
			this._searchNode.readonly = this.readonly;
		}
		// Pass some properties on to app selection
		if(changedProperties.has("onlyApp"))
		{
			this._appNode.onlyApp = this.onlyApp;
		}
		if(changedProperties.has("applicationList"))
		{
			this._appNode.applicationList = this.applicationList;
		}
		if(changedProperties.has("appIcons"))
		{
			this._appNode.appIcons = this.appIcons;
		}
	}

	set onlyApp(app)
	{
		this.__onlyApp = app || "";

		// If initial value got set before onlyApp, it still needs app in pre-render value
		if(this._value && app)
		{
			this._value.app = this.__onlyApp;
		}
		if(app)
		{
			this.app = app;
		}
		if(this._appNode)
		{
			this._appNode.onlyApp = app;
		}
	}

	get onlyApp()
	{
		return this.__onlyApp;
	}

	set app(app)
	{
		this.updateComplete.then(() =>
		{
			this._appNode.value = app;
			this._searchNode.app = app;
		});
	}

	get app()
	{
		return this._appNode?.value || "";
	}

	get _appNode() : Et2LinkAppSelect
	{
		return this.querySelector("[slot='app']");
	}

	get _searchNode() : Et2LinkSearch
	{
		return this.querySelector("[slot='select']");
	}

	get placeholder() : string
	{
		return this._searchNode?.placeholder;
	}

	set placeholder(new_value)
	{
		if(this._searchNode)
		{
			this._searchNode.placeholder = new_value;
		}
	}

	protected _bindListeners()
	{
		this._appNode.addEventListener("change", this._handleAppChange);
		this._searchNode.addEventListener("sl-select", this._handleEntrySelect);
		this._searchNode.addEventListener("sl-clear", this._handleEntryClear);
		this.addEventListener("sl-show", this._handleShow);
		this.addEventListener("sl-hide", this._handleHide);
	}

	protected _unbindListeners()
	{
		this._appNode.removeEventListener("change", this._handleAppChange);
		this.removeEventListener("sl-select", this._handleEntrySelect);
		this.removeEventListener("sl-clear", this._handleEntryClear);
		this.removeEventListener("sl-show", this._handleShow);
		this.removeEventListener("sl-hide", this._handleHide);
	}

	/**
	 * Update the search node's app & clear selected value when
	 * selected app changes.
	 * @param event
	 * @protected
	 */
	protected _handleAppChange(event)
	{
		this._searchNode.app = this._appNode.value;
		this._searchNode.value = "";
		this._searchNode.clearSearch();
		this._searchNode.focus();

		this.requestUpdate('value');
	}

	/**
	 * Hide app selection when there's an entry
	 * @param event
	 * @protected
	 */
	protected _handleEntrySelect(event)
	{
		this.classList.add("hideApp");
		this.dispatchEvent(new Event("change"));
		this.requestUpdate('value');

		this.validate();
	}


	/**
	 * Show app selection when there's no entry
	 * @param event
	 * @protected
	 */
	protected _handleEntryClear(event)
	{
		this.classList.remove("hideApp")
		this._searchNode.focus();

		this.dispatchEvent(new Event("change"));
		this.requestUpdate('value');

		this.validate();
	}


	/**
	 * Option select dropdown opened
	 * Show app selection (Et2LinkAppSelect controls own visibility according to onlyApp)
	 * @param event
	 * @protected
	 */
	protected _handleShow(event)
	{
		this.classList.remove("hideApp");
	}

	/**
	 * Option select dropdown closed
	 * Hide app selection (Et2LinkAppSelect controls own visibility according to onlyApp)
	 * only if there's a value selected
	 *
	 * @param event
	 * @protected
	 */
	protected _handleHide(event)
	{
		if(this._searchNode.value)
		{
			this.classList.add("hideApp");
		}
	}

	get value() : LinkInfo | string | number
	{
		if(this.onlyApp)
		{
			return <string>this._searchNode?.value;
		}
		return this._searchNode ? <LinkInfo>{
			id: this._searchNode.value,
			app: this.app,
			//search: this._searchNode...	// content of search field
		} : this._value;
	}

	set value(val : LinkInfo | string | number)
	{
		let value : LinkInfo = {app: this.onlyApp || this.app, id: ""};

		if(typeof val === 'string' && val.length > 0)
		{
			if(val.indexOf(',') > 0)
			{
				val = val.replace(",", ":");
			}
			if (val.indexOf(':') > 0)
			{
				const vals = val.split(':');
				value.app = vals[0];
				value.id = vals[1];
			}
			else
			{
				value.id = val;
			}
		}
		else if(typeof val === "number" && val)
		{
			value.id = String(val);
		}
		else if(typeof val === "object" && val !== null)	// object with attributes: app, id, title
		{
			value = (<LinkInfo>val);
		}

		// If the searchNode is not there yet, hold value.  We'll use these values when we create the
		// slotted searchNode.
		if(this._searchNode == null)
		{
			this._value = value;
		}
		else
		{
			this.app = value.app;
			this._searchNode.value = value.id;
		}
		if(value.id)
		{
			this.classList.add("hideApp");
		}
	}

	/**
	 * @return {TemplateResult}
	 * @protected
	 */
	_inputGroupInputTemplate()
	{
		return html`
            <div class="input-group__input" part="control">
                <slot name="app"></slot>
                <slot name="select"></slot>
            </div>
		`;
	}
}

customElements.define("et2-link-entry", Et2LinkEntry);

export class Et2LinkEntryReadonly extends Et2Link
{

}

customElements.define("et2-link-entry_ro", Et2LinkEntryReadonly);