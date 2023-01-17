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


import {css, html, LitElement, PropertyValues, render, TemplateResult, until} from "@lion/core";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2Link, LinkInfo} from "./Et2Link";
import {et2_IDetachedDOM} from "../et2_core_interfaces";

/**
 * Display a list of entries in a comma separated list
 *
 * Given an application & entry ID, will query the list of links and display
 *
 * @see Et2Link
 */

// @ts-ignore TypeScript says there's something wrong with types
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
			/* CSS for child elements */
            ::slotted(*):after {
            	content: ", "
            }
            ::slotted(*:last-child):after {
            	content:initial;
            }
			`
		];
	}


	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Specify the application for all the entries, so you only need to specify the entry ID
			 */
			application: {
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
			 * Application filter
			 * Set to an appname or comma separated list of applications to show only linked entries from those
			 * applications
			 */
			onlyApp: {
				type: String
			},
			/**
			 * Type filter
			 * Sub-type key to list only entries of that type
			 */
			linkType: {
				type: String
			},

			// Show links that are marked as deleted, being held for purge
			showDeleted: {type: Boolean},

			/**
			 * Pass value as an object, will be parsed to set application & entryId
			 */
			value: {
				type: Object,
				reflect: false
			}
		}
	}

	protected _link_list : LinkInfo[];
	protected _loadingPromise : Promise<LinkInfo[]>;

	constructor()
	{
		super();
		this._link_list = []
		this.__showDeleted = false;
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
			this.get_links();
			return;
		}

		// CSV list of IDs for one app
		if(typeof _value === "string")
		{
			let ids = _value.split(",");
			ids.forEach((id) => (<LinkInfo[]>this._link_list).push(<LinkInfo>{app: this.application, id: id}));
		}
		// List of LinkInfo
		else if(Array.isArray(_value))
		{
			this._link_list = _value;
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
		this._addLinks(this._link_list);
		super.requestUpdate();
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
            ${this._loadingPromise ? until(
                    this._loadingPromise?.then(res =>
                    {
                        this._listTemplate();
                    }),
                    this._loadingTemplate()
            ) : this._listTemplate()}
		`;
	}

	protected _listTemplate()
	{
		return html`
            <slot></slot>`;
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
            <et2-link app="${link.app}" entryId="${id}" .value=${link} ._parent=${this}></et2-link>`;
	}

	/**
	 * Render that we're waiting for data
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _loadingTemplate() : TemplateResult
	{
		return html`loading...`;
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

	}

	/**
	 * Starts the request for link list to the server
	 *
	 * Called internally to fetch the list.  May be called externally to trigger a refresh if a link is added.
	 *
	 */
	public get_links(not_saved_links? : LinkInfo[])
	{
		if(typeof not_saved_links === "undefined")
		{
			not_saved_links = [];
		}
		let _value = {
			to_app: this.application,
			to_id: this.entryId,
			only_app: this.onlyApp,
			show_deleted: this.showDeleted
		};
		if(this._loadingPromise)
		{
			// Already waiting
			return;
		}

		this._loadingPromise = <Promise<LinkInfo[]>>(this.egw().jsonq('EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_list', [_value]))
			.then(_value =>
			{
				this._addLinks(not_saved_links.concat(_value));
				this._loadingPromise = null;
			})
	}

	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("application", "entryId");
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
};

// @ts-ignore TypeScript says there's something wrong with types
customElements.define("et2-link-string", Et2LinkString, {extends: 'ul'});