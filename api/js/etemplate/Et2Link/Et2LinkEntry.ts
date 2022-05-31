import {css, html, LitElement, SlotMixin} from "@lion/core";
import {Et2LinkAppSelect} from "./Et2LinkAppSelect";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {FormControlMixin, ValidateMixin} from "@lion/form-core";
import {Et2LinkSearch} from "./Et2LinkSearch";

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
				const app = document.createElement("et2-link-apps")
				return app;
			},
			select: () =>
			{
				const select = <Et2LinkSearch><unknown>document.createElement("et2-link-search");
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

	set only_app(app)
	{
		this._appNode.only_app = app;
		this._searchNode.app = app;
	}

	get only_app()
	{
		return this._appNode.only_app;
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

	/**
	 * @return {TemplateResult}
	 * @protected
	 */
	// eslint-disable-next-line class-methods-use-this
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