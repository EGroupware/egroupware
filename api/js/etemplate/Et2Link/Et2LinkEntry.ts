import {css, html, LitElement, SlotMixin} from "@lion/core";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {FormControlMixin, ValidateMixin} from "@lion/form-core";
import {Et2LinkSearch} from "./Et2LinkSearch";

export interface LinkEntry {
	app: string;
	id: string|number;
	title?: string;
}

/**
 * EGroupware eTemplate2 - Search & select link entry WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
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
				return app;
			},
			select: () =>
			{
				const select = <Et2LinkSearch><unknown>document.createElement("et2-link-search");
				select.app = this.__only_app || this.__app;
				if (this.__value && typeof this.__value === 'object')
				{
					select.app = this.__value.app;
					select.select_options = [{value: this.__value.id, label: this.__value.title}];
					select.value = this.__value.id;
				}
				else
				{
					select.value = this.__value;
				}
				return select;
			}
		}
	}

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();

		this._handleAppChange = this._handleAppChange.bind(this);
	}

	protected __only_app : string;
	protected __app : string;
	protected __value : LinkEntry | string | number;

	set only_app(app)
	{
		this.__only_app = app;
		if (this._appNode) this._appNode.only_app = app;
		if (this._searchNode) this._searchNode.app = app;
	}

	get only_app()
	{
		return this.__only_app;
	}

	set app(app)
	{
		this.__app = app;
		if(this._appNode)
		{
			this._appNode.value = app;
		}
		if(this._searchNode)
		{
			this._searchNode.app = app;
		}
	}

	get app()
	{
		return this._appNode?.value || this.__app;
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
	}

	protected _unbindListeners()
	{
		this._appNode.removeEventListener("change", this._handleAppChange);
	}

	protected _handleAppChange(event)
	{
		this._searchNode.app = this._appNode.value;
	}

	get value() : LinkEntry|string|number
	{
		if (this.only_app)
		{
			return this._searchNode?.value || this.__value;
		}
		return this._searchNode ?  {
			id: this._searchNode?.value || this.__value,
			app: this._appNode?.value || this.__onlyApp || this.__app,
			//search: this._searchNode...	// content of search field
		} : this.__value;
	}

	set value(val: LinkEntry|string|number)
	{
		if (!val)
		{
			if (this._searchNode) this._searchNode.value = '';
		}
		else if (typeof val === 'string')
		{
			if (val.indexOf(',') > 0)  val = val.replace(",", ":");
			const vals = val.split(':');
			this.app = vals[0];
			if (this._searchNode)
			{
				this._searchNode.value = vals[1];
			}
		}
		else	// object with attributes: app, id, title
		{
			this.app = val.app;
			if (this._searchNode)
			{
				this._searchNode.value = val.id;
				this._searchNode.select_options = [{value: val.id, label: val.title}];
			}
		}
		this.__value = val;
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

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-link-entry", Et2LinkEntry);