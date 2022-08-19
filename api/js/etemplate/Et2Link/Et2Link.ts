/**
 * EGroupware eTemplate2 - JS Link object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2022 Nathan Gray
 */


import {ExposeMixin, ExposeValue} from "../Expose/ExposeMixin";
import {css, html, LitElement} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../et2_core_interfaces";

/**
 * Display a specific, single entry from an application
 *
 * The entry is specified with the application name, and the app's ID for that entry.
 * You can set it directly in the properties (application, entryId) or use set_value() to
 * pass an object {app: string, id: string, [title: string]} or string in the form <application>::<ID>.
 * If title is not specified, it will be fetched using framework's egw.link_title()
 */

// @ts-ignore TypeScript says there's something wrong with types
export class Et2Link extends ExposeMixin<Et2Widget>(Et2Widget(LitElement)) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: block;
				cursor: pointer;
			}
			:host:hover {
				text-decoration: underline
			}
			/** Style based on parent **/
			:host-context(et2-link-string) {
				display: inline;
			}
			:host-context(et2-link-list):hover {
				text-decoration: none;
			}
			`
		];
	}


	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Specify the application for the entry
			 */
			app: {
				type: String,
				reflect: true,
			},
			/**
			 * Application entry ID
			 */
			entryId: {
				type: String,
				reflect: true
			},
			/**
			 * Pass value as an object, will be parsed to set application & entryId
			 */
			value: {
				type: Object,
				reflect: false
			},
			/**
			 * View link type
			 * Used for displaying the linked entry
			 * [view|edit|add]
			 * default "view"
			 */
			linkHook: {
				type: String
			},
			/**
			 * Target application
			 *
			 * Passed to egw.open() to open entry in specified application
			 */
			targetApp: {
				type: String
			},
			/**
			 * Optional parameter to be passed to egw().open in order to open links in specified target eg. _blank
			 */
			extraLinkTarget: {
				type: String
			},

			/**
			 * Breaks title into multiple lines based on this delimiter by replacing it with '\r\n'"
			 */
			breakTitle: {
				type: String
			}

		}
	}

	static MISSING_TITLE = "??";

	// Title is read-only inside
	private _title : string;
	private _titlePromise : Promise<string>;

	constructor()
	{
		super();
		this._title = "";
		this.__linkHook = "view";
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	createRenderRoot()
	{
		return this;
	}

	render()
	{
		let title = this.title;

		if(this.breakTitle)
		{
			// Set up title to optionally break on the provided character - replace all space with nbsp, add a
			// zero-width space after the break string
			title = title
				.replace(this.breakTitle, this.breakTitle.trimEnd() + "\u200B")
				.replace(/ /g, '\u00a0');
		}
		return html`${title}`;
	}

	public set title(_title)
	{
		this._title = _title;
	}

	public get title()
	{
		return this._title;
	}

	/**
	 * Get a value representation of the link.
	 *
	 * @returns {LinkInfo | string}
	 */
	get value() : LinkInfo | string
	{
		return this.app && this.entryId ? this.app + ":" + this.entryId : "";
	}

	set value(_value : LinkInfo | string)
	{
		if(!_value)
		{
			this.entryId = "";
			this.title = "";
			return;
		}
		if(typeof _value != 'object' && _value)
		{
			if(_value.indexOf(':') >= 0)
			{
				// application_name:ID
				let app = _value.split(':', 1);
				let id = _value.substr(app[0].length + 1);
				_value = {app: app[0], id: id};
			}
			else if(this.app)
			{
				// Application set, just passed ID
				_value = {app: this.app, id: _value};
			}
			else
			{
				console.warn("Bad value for link widget.  Need an object with keys 'app', 'id', and optionally 'title'", _value);
				return;
			}
		}
		if(typeof _value !== "string")
		{
			this.app = _value.app;
			this.entryId = _value.id;
			this._title = Et2Link.MISSING_TITLE;

			if(_value.title)
			{
				this._title = _value.title;
			}
			Object.keys(_value).forEach(key =>
			{
				// Skip these, they're either handled explicitly, or ID which we don't want to mess with
				if(["app", "entryId", "title", "id"].indexOf(key) != -1)
				{
					return;
				}
				this.dataset[key] = _value[key];
			})
		}
	}


	set_value(_value : LinkInfo | string)
	{
		this.value = _value;
	}

	get exposeValue() : ExposeValue
	{
		let info = <ExposeValue><unknown>{
			app: this.app,
			id: this.entryId,
			path: this.dataset['icon']
		};
		info['label'] = this.title;
		info = Object.assign(info, this.dataset);

		if(info['remark'])
		{
			info['label'] += " - " + info['remark'];
		}
		if(!info.path && this.app == "file")
		{
			// Fallback to check the "normal" place if path wasn't available
			info.path = "/webdav.php/apps/" + this.dataset.app2 + "/" + this.dataset.id2 + "/" + this.entryId;
		}

		if(typeof info["type"] !== "undefined")
		{
			// Links use "type" for mimetype.
			info.mime = info["type"];
		}

		return info;
	}

	/**
	 * If app or entryId has changed, we'll update the title
	 *
	 * @param changedProperties
	 */
	willUpdate(changedProperties)
	{
		super.willUpdate(changedProperties);

		super.requestUpdate();
		if(changedProperties.has("app") || changedProperties.has("entryId"))
		{
			if(this.app && this.entryId && !this._title)
			{
				this._title = Et2Link.MISSING_TITLE;
			}
			if(this.app && this.entryId && this._title == Et2Link.MISSING_TITLE)
			{
				// Title will be fetched from server and then set
				this._titlePromise = this.egw()?.link_title(this.app, this.entryId, true).then(title =>
				{
					this._title = title;
					// It's probably already been rendered
					this.requestUpdate();
				});
			}
		}
	}

	_handleClick(_ev : MouseEvent) : boolean
	{
		// If we don't have app & entryId, nothing we can do
		if(!this.app || !this.entryId || typeof this.entryId !== "string")
		{
			return false;
		}
		// If super didn't handle it (returns false), just use egw.open()
		if(super._handleClick(_ev))
		{
			this.egw().open(Object.assign({
				app: this.app,
				id: this.entryId
			}, this.dataset), "", this.linkHook, this.dataset.extra_args, this.targetApp || this.app, this.targetApp);
		}

		_ev.stopImmediatePropagation();
		return false;
	}

	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("app", "entryId");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data?)
	{
		for(let k in _values)
		{
			this[k] = _values[k];
		}
	}
}

// @ts-ignore TypeScript says there's something wrong with types
customElements.define("et2-link", Et2Link);

/**
 * Interface to describe needed information about a link
 */
export interface LinkInfo
{
	app : string,
	id : string,
	title? : string,

	link_id? : string;
	comment? : string
	icon? : string,
	help? : string,

	// Extra information for things like files
	download_url? : string,
	target? : string,
	mode? : number
}