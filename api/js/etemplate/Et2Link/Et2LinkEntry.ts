/**
 * EGroupware eTemplate2 - Search & select link entry WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {css, html, LitElement, nothing} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {Et2LinkSearch} from "./Et2LinkSearch";
import {Et2Link, LinkInfo} from "./Et2Link";
import {HasSlotController} from "../Et2Widget/slot";

/**
 * Find and select a single entry using the link system.
 *
 *
 */
export class Et2LinkEntry extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...(Array.isArray(super.styles) ? super.styles : [super.styles]),
			css`
				:host {
				display: block;
				}

				:host(.hideApp) ::slotted([slot="app"]) {
				display: none;
				}

				.form-control-input {
					display: flex;
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
			placeholder: {type: String},

			/**
			 * Additional search parameters that are passed to the server
			 * when we query searchUrl
			 */
			searchOptions: {type: Object}
		}
	}

	/**
	 * We only care about this value until render.  After the sub-nodes are created,
	 * we take their "live" values for our value.
	 *
	 * @type {LinkInfo}
	 * @private
	 */
	private __value : LinkInfo = {app: "", id: ""};

	protected __onlyApp : string;
	protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'label');

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();

		this._handleShow = this._handleShow.bind(this);
		this._handleHide = this._handleHide.bind(this);

		if(!this.readonly)
		{
			this.updateComplete.then(() =>
			{
				this._bindListeners();
			});
		}
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._unbindListeners();
	}

	set onlyApp(app)
	{
		this.__onlyApp = app || "";

		// If initial value got set before onlyApp, it still needs app in pre-render value
		if(this.__value && app)
		{
			this.__value.app = this.__onlyApp;
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
		if(typeof this.__value !== "object" || this.__value == null)
		{
			this.__value = <LinkInfo>{app: app}
		}
		else
		{
			this.__value.app = app;
		}
		this.requestUpdate("value");
	}

	get app()
	{
		return this.__value?.app || "";
	}

	set searchOptions(options)
	{
		this.updateComplete.then(() =>
		{
			this._searchNode.searchOptions = options;
		});
	}

	get searchOptions()
	{
		return this._searchNode?.searchOptions;
	}

	get _appNode() : Et2LinkAppSelect
	{
		return this.shadowRoot?.querySelector("et2-link-apps");
	}

	get _searchNode() : Et2LinkSearch
	{
		return <Et2LinkSearch>this.shadowRoot?.querySelector("et2-link-search");
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
		this.addEventListener("sl-show", this._handleShow);
		this.addEventListener("sl-hide", this._handleHide);
	}

	protected _unbindListeners()
	{
		this.removeEventListener("sl-show", this._handleShow);
		this.removeEventListener("sl-hide", this._handleHide);
	}


	/**
	 * Hide app selection when there's an entry
	 * @param event
	 * @protected
	 */
	protected handleEntrySelect(event)
	{
		event.stopPropagation();
		this.value = <string>this._searchNode.value ?? "";
		this.classList.toggle("hideApp", Boolean(typeof this.value == "object" ? this.value?.id : this.value));

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true, composed: true}));
		});
		this.requestUpdate('value');

		this.validate();
	}


	/**
	 * Show app selection when there's no entry
	 * @param event
	 * @protected
	 */
	protected handleEntryClear(event)
	{
		this.value = ""
		this.classList.remove("hideApp")
		this._searchNode.value = "";
		this._searchNode.focus();

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change"));
		});
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
			return <string>this._searchNode?.value ?? "";
		}
		return this.__value;
	}

	set value(val : LinkInfo | string | number)
	{
		let value : LinkInfo = {app: this.onlyApp || (this.app || this._appNode?.value), id: ""};

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

		const oldValue = this.__value;
		this.__value = value;

		this.classList.toggle("hideApp", Boolean(this.__value.id));
		this.requestUpdate("value", oldValue);
	}

	protected handleLabelClick()
	{
		this._searchNode.focus();
	}


	/**
	 * Update the search node's app & clear selected value when
	 * selected app changes.
	 * @param event
	 * @protected
	 */
	protected handleAppChange(e)
	{
		this.app = this._appNode.value;
		this._searchNode.app = this._appNode.value;
		this._searchNode.value = "";
		this._searchNode.clearSearch();
		this._searchNode.focus();

		this.requestUpdate('value');
	}


	render()
	{
		const labelTemplate = this._labelTemplate();
		const helpTemplate = this._helpTextTemplate();

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': labelTemplate !== nothing,
                        'form-control--has-help-text': helpTemplate !== nothing
                    })}
            >
                ${labelTemplate}
                <div part="form-control-input" class="form-control-input">
                    <et2-link-apps
                            onlyApp=${this.onlyApp ? this.onlyApp : nothing}
                            ?appIcons=${this.appIcons}
                            applicationList=${this.applicationList ? this.applicationList : nothing}
                            ?disabled=${this.disabled}
                            ?readonly=${this.readonly}
                            .value=${this.__value?.app ? this.__value.app : nothing}
                            @change=${this.handleAppChange}
                    ></et2-link-apps>
                    <et2-link-search
                            exportparts="combobox:control"
                            ?placeholder=${this.placeholder}
                            ?required=${this.required}
                            ?disabled=${this.disabled}
                            ?readonly=${this.readonly}
                            .app=${this.__value?.app || nothing}
                            .value=${this.__value?.id || nothing}
                            @change=${this.handleEntrySelect}
                            @sl-clear=${this.handleEntryClear}
                    >
                        ${(this.__value?.title) ? html`
                            <option value=${this.__value.id}>${this.__value.title}</option>
                        ` : nothing}
                    </et2-link-search>
                </div>
                ${helpTemplate}
            </div>
		`;
	}
}

customElements.define("et2-link-entry", Et2LinkEntry);

export class Et2LinkEntryReadonly extends Et2Link
{

}

customElements.define("et2-link-entry_ro", Et2LinkEntryReadonly);