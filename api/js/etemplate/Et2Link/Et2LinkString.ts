/**
 * EGroupware eTemplate2 - JS Link list object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2022 Nathan Gray
 */


import {css, html, LitElement, nothing, PropertyValues, render, TemplateResult} from "lit";
import {until} from "lit/directives/until.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {LinkInfo} from "./Et2Link";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";
import {repeat} from "lit/directives/repeat.js";

/**
 * Display a list of entries in a comma separated list
 *
 * Given an application & entry ID, will query the list of links and display
 *
 * @see Et2Link
 */

// @ts-ignore TypeScript says there's something wrong with types
@customElement('et2-link-string')
export class Et2LinkString extends Et2Widget(LitElement) implements et2_IDetachedDOM
{

	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					list-style-type: none;
					display: inline;
					padding: 0px;
				}

				et2-link, et2-link::part(base), et2-description {
					display: inline;
				}

				et2-link:hover {
					text-decoration: underline;
				}


				/* CSS for child elements */

				et2-link::part(title):after {
					content: ", "
				}

				et2-link:last-child::part(title):after {
					content: initial;
				}
			`
		];
	}


	/**
	 * Specify the application for all the entries, so you only need to specify the entry ID
	 */
	@property({ type: String, reflect: true })
	application;

	/**
	 * Application entry ID
	 */
	@property({type: String, reflect: true})
	entryId;

	/**
	 * Application filter
	 * Set to an appname or comma separated list of applications to show only linked entries from those
	 * applications
	 */
	@property({type: String})
	onlyApp;

	/**
	 * Type filter
	 * Sub-type key to list only entries of that type
	 */
	@property({type: String})
	linkType;

	/**
	 * Show links that are marked as deleted, being held for purge
 	 */
	@property({type: Boolean})
	showDeleted = false;

	/**
	 * Pass value as an object, will be parsed to set application & entryId
	 */
	@property({type: Object})
	value;

	/**
	 * Number of application-links to load (file-links are always fully loaded currently)
	 *
	 * If number is exceeded, a "Load more links ..." button is displayed, which will load the double amount of links each time clicked
	 */
	@property({type: Number})
	limit = 20;

	protected _totalResults : number = 0;
	protected _link_list : LinkInfo[] = [];
	protected _loadingPromise : Promise<LinkInfo[]> = Promise.resolve([]);
	protected _loading = false;

	constructor()
	{
		super();
	}

	async getUpdateComplete()
	{
		const result = await super.getUpdateComplete();
		if(this._loadingPromise)
		{
			// Wait for the values to arrive before we say we're done
			await this._loadingPromise;
		}
		return result;
	}

	/**
	 * Set the value of the list
	 *
	 * Value can be:
	 * - String: CSV list of entries in either app:ID or just ID if application is set.
	 * - Object: {to_app: <appname>, to_id: <Entry ID>} List of linked entries will be fetched from the server
	 * - Array: {app: <appname>, id: <ID>}[]
	 * @param _value
	 */
	public set_value(_value : string | { to_app : string, to_id : string } | LinkInfo[])
	{
		this._link_list = [];
		if(typeof _value["total"] !== "undefined")
		{
			this._totalResults = _value["total"];
			delete _value["total"];
		}

		if(typeof _value == "object" && !Array.isArray(_value) && !_value.to_app && this.application)
		{
			_value.to_app = this.application;
		}

		// We have app & ID - fetch list
		if(typeof _value == 'object' && !Array.isArray(_value) && _value.to_app && _value.to_id && (
			typeof _value.to_id === "string" || typeof _value.to_id == "number"))
		{
			this.application = _value.to_app;
			this.entryId = _value.to_id;

			// Let update complete finish first, if it's not done yet
			this.updateComplete.then(() =>
			{
				this.get_links();
			})
			return;
		}

		// CSV list of IDs for one app
		if(typeof _value === "string")
		{
			let ids = _value.split(",");
			ids.forEach((id) => (<LinkInfo[]>this._link_list).push(<LinkInfo>{app: this.application, id: id}));
		}
		// List of LinkInfo
		else if(Array.isArray(_value) || typeof _value[0] == "object")
		{
			this._link_list = <LinkInfo[]>Object.values(_value);
		}
		// List of LinkInfo stuffed into to_id - entry is not yet saved
		else if(_value.to_id && typeof _value.to_id !== "string")
		{
			this.entryId = _value.to_id;
			Object.keys(_value.to_id).forEach((key) =>
			{
				this._link_list.push(<LinkInfo>_value.to_id[key]);
			});
		}
		this.requestUpdate();
	}

	public updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if((changedProperties.has("application") || changedProperties.has("entryId") || changedProperties.has("onlyApp") || changedProperties.has("linkType")) &&
			this.application && this.entryId
		)
		{
			// Something changed, and we have the information needed to get the matching links
			this.get_links();
		}
	}

	public render() : TemplateResult
	{
		// This shows loading template until loadingPromise resolves, then shows _listTemplate
		return html`
            ${until(this._loadingPromise?.then(res =>
                    {
                        return this._listTemplate();
                    }),
                    this._loadingTemplate()
            )}
            ${until(this.moreResultsTemplate(), nothing)}
		`;
	}

	protected _listTemplate()
	{
		return html`
            ${repeat(this._link_list, l => l.link_id, this._linkTemplate)}
		`;
	}

	/**
	 * Render one link
	 *
	 * @param link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _linkTemplate(link : LinkInfo) : TemplateResult
	{
		const id = typeof link.id === "string" ? link.id : link.link_id;
		return html`
            <et2-link part="link" class="et2_link"
                      app="${link.app}" entryId="${id}" .value=${link} ._parent=${this}
            ></et2-link>`;
	}

	/**
	 * Render that we're waiting for data
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _loadingTemplate() : TemplateResult
	{
		return html`
            <div class="search__loading">
                <sl-spinner></sl-spinner>
            </div>
		`;
	}

	protected async moreResultsTemplate()
	{
		if(this._totalResults <= 0 || !this._loadingPromise)
		{
			return nothing;
		}
		return this._loadingPromise.then(() =>
		{
			const moreCount = this._totalResults - this._link_list.length;
			const more = html`
                <et2-description statustext="${this.egw().lang("%1 more...", moreCount)}">...
                </et2-description>`;
			return html`${moreCount > 0 ? more : nothing}`;
		});
	}

	/**
	 * Render a list of links inside the list
	 * These get slotted, rather than put inside the shadow dom
	 *
	 * @param links
	 * @protected
	 */
	protected _addLinks(links : LinkInfo[])
	{
		return;
		// Remove anything there right now
		while(this.lastChild)
		{
			this.removeChild(this.lastChild);
		}

		links.forEach((link) =>
		{
			let temp = document.createElement("div");
			render(this._linkTemplate(link), temp);
			temp.childNodes.forEach((node) => this.appendChild(node));
		})

		/*
		This should work, and it does, but only once.
		It fails if you try and update then run it again - none of the children get added
		Something about how lit renders
		render(html`${repeat(links,
				(link) => link.app + ":" + link.id,
				(link) => this._linkTemplate(link))}`,
			<HTMLElement><unknown>this
		);

		 */
		this.dispatchEvent(new Event("change", {bubbles: true}));
	}

	/**
	 * Starts the request for link list to the server
	 *
	 * Called internally to fetch the list.  May be called externally to trigger a refresh if a link is added.
	 *
	 */
	public get_links(not_saved_links? : LinkInfo[], offset = 0)
	{
		if(this._loading)
		{
			// Already waiting
			return;
		}
		this._loading = true;

		if(typeof not_saved_links === "undefined")
		{
			not_saved_links = [];
		}
		let _value = {
			to_app: this.application,
			to_id: this.entryId,
			only_app: this.onlyApp,
			show_deleted: this.showDeleted,
			limit: [offset, /* num_rows: */this.limit]
		};

		this._loadingPromise = <Promise<LinkInfo[]>>(this.egw().jsonq('EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_list', [_value]))
			.then(_value =>
			{
				if(typeof _value.total)
				{
					this._totalResults = _value.total;
					delete _value.total;
				}
				if(_value)
				{
					for(let link of <LinkInfo[]>Object.values(_value))
					{
						// Avoid duplicates, files are always sent
						if(!not_saved_links.some(l => l.app == link.app && l.id == link.id) &&
							!this._link_list.some(l => l.app == link.app && l.id == link.id))
						{
							this._link_list.push(link);
						}
					}
				}
				this._loading = false;
				this.requestUpdate();
			})
	}

	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("application", "entryId", "statustext");
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