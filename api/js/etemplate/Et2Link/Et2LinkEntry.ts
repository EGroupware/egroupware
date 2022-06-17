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
import {FormControlMixin, ValidateMixin} from "@lion/form-core";
import {Et2LinkSearch} from "./Et2LinkSearch";
import {Et2Link, LinkInfo} from "./Et2Link";

/**
 * Find and select a single entry using the link system.
 *
 *
 */
export class Et2LinkEntry extends Et2InputWidget(FormControlMixin(ValidateMixin(SlotMixin(LitElement))))
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
			only_app: {type: String},
			/**
			 * Limit to the listed applications (comma seperated)
			 */
			application_list: {type: String},
			/**
			 * Show just application icons instead of names
			 */
			app_icons: {type: Boolean},
			/**
			 * Callback before query to server.
			 * It will be passed the request & et2_link_entry objects.  Must return true, or false to abort query.
			 */
			query: {type: Function},
			/**
			 * Callback when user selects an option.  Must return true, or false to abort normal action.
			 */
			select: {type: Function}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			app: () =>
			{
				const app = <Et2LinkAppSelect>document.createElement("et2-link-apps")
				if(this.__only_app)
				{
					app.only_app = this.__only_app;
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

	protected __only_app : string;

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
		if(changedProperties.has("readonly"))
		{
			this._appNode.readonly = this.readonly;
			this._searchNode.readonly = this.readonly;
		}
	}

	set only_app(app)
	{
		this.__only_app = app || "";
		if(app)
		{
			this.app = app;
		}
	}

	get only_app()
	{
		return this.__only_app;
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
	}

	/**
	 * Hide app selection when there's an entry
	 * @param event
	 * @protected
	 */
	protected _handleEntrySelect(event)
	{
		this.classList.add("hideApp");
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
	}


	/**
	 * Option select dropdown opened
	 * Show app selection (Et2LinkAppSelect controls own visibility according to only_app)
	 * @param event
	 * @protected
	 */
	protected _handleShow(event)
	{
		this.classList.remove("hideApp");
	}

	/**
	 * Option select dropdown closed
	 * Hide app selection (Et2LinkAppSelect controls own visibility according to only_app)
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
		if(this.only_app)
		{
			return this._searchNode?.value;
		}
		return this._searchNode ? <LinkInfo>{
			id: this._searchNode.value,
			app: this.app,
			//search: this._searchNode...	// content of search field
		} : this._value;
	}

	set value(val : LinkInfo | string | number)
	{
		let value : LinkInfo = {app: "", id: ""};

		if(typeof val === 'string' && val.length > 0)
		{
			if(val.indexOf(',') > 0)
			{
				val = val.replace(",", ":");
			}
			const vals = val.split(':');
			value.app = vals[0];
			value.id = vals[1];
		}
		else if(typeof val === "number" && val)
		{
			value.id = String(val);
		}
		else if(typeof val === "object")	// object with attributes: app, id, title
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
            <div class="input-group__input">
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
