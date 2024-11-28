/**
 * EGroupware eTemplate2 - Email WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {html, LitElement, nothing, PropertyValues, render} from "lit";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import shoelace from "../Styles/shoelace";
import styles from "./Et2Template.styles";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";
import {et2_loadXMLFromURL} from "../et2_core_xml";
import {Et2InputWidgetInterface} from "../Et2InputWidget/Et2InputWidget";
import type {IegwAppLocal} from "../../jsapi/egw_global";
import {until} from "lit/directives/until.js";
import {classMap} from "lit/directives/class-map.js";

// @ts-ignore
/**
 * @summary Load & populate a template (.xet file)
 *
 *
 * @event load - Emitted when all elements are loaded
 *
 * @csspart template - Wrapper around template content
 *
 * @cssproperty [--height=5] - The maximum height of the widget, to limit size when you have a lot of addresses.  Set by rows property, when set.
 */
@customElement("et2-template")
export class Et2Template extends Et2Widget(LitElement)
{

	static get styles()
	{
		return [
			shoelace,
			super.styles,
			styles
		];
	}

	/**
	 * Name / ID of template with optional cache-buster ('?'+filemtime of template on server)
	 * Template can be <app>.<template_file>.<template> form or short <template>.
	 * To use the short form, the file must already be loaded.
	 */
	@property()
	template : string;

	/**
	 * Url of template
	 * A full URL to load template,  including cache-buster ('?'+filemtime of template on server)
	 * @type {string}
	 */
	@property()
	url : string;

	/**
	 * Content index
	 *
	 * Used for passing in specific content to the template other than what it would get by ID.
	 * @type {string}
	 */
	@property()
	content : string;

	/**
	 * Cache of known templates
	 * @type {{[name : string] : Element}}
	 */
	public static templateCache : { [name : string] : Element } = {};
	protected loading : Promise<void>;

	private __egw : IegwAppLocal = null;

	// Internal flag to indicate loading is in progress, since we can't monitor a promise
	private __isLoading = false;


	constructor(egw? : IegwAppLocal)
	{
		super();

		if(egw)
		{
			this.__egw = egw;
		}

		this.loading = Promise.resolve();
	}

	connectedCallback() : void
	{
		super.connectedCallback();
		this.addEventListener("load", this.handleLoad);

		// If we can, start loading immediately
		if(this.template || this.id || this.url)
		{
			this.load();
		}
	}

	disconnectedCallback() : void
	{
		super.disconnectedCallback();
		this.removeEventListener("load", this.handleLoad);
	}

	async getUpdateComplete() : Promise<boolean>
	{
		const result = await super.getUpdateComplete();
		await this.loading;

		return result;
	}

	willUpdate(changedProperties : PropertyValues)
	{
		// If content index was changed, re-check / create namespace
		if(changedProperties.has("content"))
		{
			this.checkCreateNamespace();
		}

		// Load if template (template, id or URL) or content index changed
		// (as long as we're not currently already loading, to prevent loops if load changes an attribute)
		if(!this.__isLoading && ["template", "id", "url", "content"].filter(v => changedProperties.has(v)).length > 0)
		{
			this.load();
		}
	}

	/**
	 * Searches for a DOM widget by id in the tree, descending into the child levels.
	 *
	 * @param _id is the id you're searching for
	 */
	getDOMWidgetById(_id) : typeof Et2Widget | null
	{
		let widget = this.getWidgetById(_id);
		if(widget && (widget instanceof HTMLElement || widget.instanceOf(Et2Widget)))
		{
			return <typeof Et2Widget>widget;
		}
		return null
	}

	/**
	 * Searches for a Value widget by id in the tree, descending into the child levels.
	 *
	 * @param _id is the id you're searching for
	 */
	getInputWidgetById(_id) : Et2InputWidgetInterface | null
	{
		let widget = <any>this.getWidgetById(_id);
		// instead of checking widget to be an instance of valueWidget (which would create a circular dependency)
		// we check for the interface/methods of valueWidget
		if(widget && typeof widget.get_value === 'function' && typeof widget.set_value === 'function')
		{
			return <Et2InputWidgetInterface>widget;
		}
		return null
	}

	/**
	 * Set the value for a child widget, specified by the given ID
	 *
	 * @param id  string The ID you're searching for
	 * @param value Value for the widget
	 *
	 * @return Returns the result of widget's set_value(), though this is usually undefined
	 *
	 * @throws Error If the widget cannot be found or it does not have a set_value() function
	 */
	setValueById(id : string, value) : any
	{
		let widget = this.getWidgetById(id);
		if(!widget)
		{
			throw 'Could not find widget ' + id;
		}

		// Don't care about what class it is, just that it has the function
		// @ts-ignore
		if(typeof widget.set_value !== 'function')
		{
			throw 'Widget ' + id + ' does not have a set_value() function';
		}

		// @ts-ignore
		return widget.set_value(value);
	}

	/**
	 * Get the current value of a child widget, specified by the given ID
	 *
	 * This is the current value of the widget, which may be different from the original value given in content
	 *
	 * @param id  string The ID you're searching for
	 * @throws Error If the widget cannot be found or it does not have a set_value() function
	 */
	getValueById(id : string)
	{
		let widget = this.getWidgetById(id);
		if(!widget)
		{
			throw 'Could not find widget ' + id;
		}

		// Don't care about what class it is, just that it has the function
		// @ts-ignore
		if(typeof widget.get_value !== 'function' && typeof widget.value == "undefined")
		{
			throw 'Widget ' + id + ' does not have a get_value() function';
		}

		// @ts-ignore
		return typeof widget.get_value == "function" ? widget.get_value() : widget.value;
	}

	/**
	 * Set the value for a child widget, specified by the given ID
	 *
	 * @param id  string The ID you're searching for
	 * @param value new value to set
	 * @throws Error If the widget cannot be found, or it does not have a set_value() function
	 */
	setDisabledById(id : string, value : boolean)
	{
		let widget = this.getWidgetById(id);
		if(!widget)
		{
			throw 'Could not find widget ' + id;
		}

		// Don't care about what class it is, just that it has the function
		// @ts-ignore
		if(typeof widget.set_disabled !== 'function')
		{
			throw 'Widget ' + id + ' does not have a set_disabled() function';
		}

		// @ts-ignore
		return widget.set_disabled(value);
	}

	public egw() : IegwAppLocal
	{
		if(this.__egw)
		{
			return this.__egw;
		}
		else
		{
			return super.egw();
		}
	}

	/**
	 * Get the template XML and create widgets from it
	 *
	 * Asks the server if we don't have that template on the client yet, then takes the template
	 * node and goes through it, creating widgets.  This is normally called automatically when the
	 * template is added to the DOM, but if you want to re-load or not put it in the DOM you need to call load() yourself.
	 *
	 * If you need to set more than just content (select options, readonly or modifications), set it in the array manager
	 * before calling load:
	 * ```
	 * template.setArrayMgr("readonlys", template.getArrayMgr("readonlys").openPerspective(template, newReadonlys));
	 * ```
	 *
	 * @returns {Promise<void>}
	 * @protected
	 */
	public async load(newContent? : object)
	{
		// @ts-ignore can't find disabled, it's in Et2Widget
		if(this.disabled)
		{
			this.loading = Promise.resolve();
			return this.loading;
		}

		if(typeof newContent != "undefined")
		{
			// @ts-ignore ArrayMgr still expects et2_widgets
			this.setArrayMgr("content", this.getArrayMgr("content").openPerspective(this, newContent));
		}
		this.__isLoading = true;
		this.loading = new Promise(async(resolve, reject) =>
		{
			// Empty in case load was called again
			this.clear();

			// Get template XML
			let xml : Element;
			try
			{
				xml = await this.findTemplate();
			}
			catch(e)
			{
				reject(e);
				return;
			}
			// Read the XML structure of the requested template
			if(typeof xml != 'undefined')
			{
				// Get any template attributes from XML template node
				const attrs = {};
				xml.getAttributeNames().forEach(attribute =>
				{
					attrs[attribute] = xml.getAttribute(attribute);
				});
				// Don't change ID, keep what we've got
				delete attrs["id"];
				this.transformAttributes(attrs);

				// Load children into template
				this.loadFromXML(xml);
			}
			else
			{
				reject("Could not find template");
				return;
			}

			// Wait for widgets to be complete
			await this.loadFinished();
			console.groupEnd();
			this.__isLoading = false;

			// Resolve promise, this.updateComplete now resolved
			resolve();

			// Yield to give anything else a chance to run
			setTimeout(() =>
			{
				// Notification event
				this.dispatchEvent(new CustomEvent("load", {
					bubbles: true,
					composed: true,
					detail: this
				}));
			}, 0);
		}).catch(reason =>
		{
			this.loadFailed(reason);
		});
		return this.loading;
	}


	/**
	 * Find the template XML node, either from the local cache or the server
	 *
	 * @returns {Promise<any>}
	 * @protected
	 */
	protected async findTemplate() : Promise<Element>
	{
		// Find template name
		const parts = (this.template || this.id).split('?');
		const cache_buster = parts.length > 1 ? parts.pop() : null;
		let template_name = parts.pop();

		// Check to see if the template is already known / loaded into global ETemplate cache
		let xml = Et2Template.templateCache[template_name];

		// Check to see if ID is short form --> prepend parent/top-level name
		if(!xml && template_name.indexOf('.') < 0)
		{
			const root = this.getRoot();
			const top_name = root && root.getInstanceManager() ? root.getInstanceManager().name : null;
			if(top_name && template_name.indexOf('.') < 0)
			{
				template_name = top_name + '.' + template_name
				xml = Et2Template.templateCache[template_name];
			}
		}

		// Ask the server for the template
		if(!xml)
		{
			const url = this.getUrl();
			let templates = <Element>{};
			try
			{
				templates = await this.loadFromFile(url);
				if(!templates)
				{
					throw new Error("No templates found in template file " + url);
				}
			}
			catch(e)
			{
				throw new Error("Could not load template file " + url);
			}

			// Scan the file for templates and store them
			let fallback;
			for(let i = 0; i < templates.childNodes?.length; i++)
			{
				const template = <Element>templates.childNodes[i];
				if(!["template", "et2-template"].includes(template.nodeName.toLowerCase()))
				{
					continue;
				}
				Et2Template.templateCache[template.getAttribute("id")] = <Element>template;
				if(template.getAttribute("id") == template_name)
				{
					xml = template;
				}
				fallback = template;
			}
			// Take last template in the file if we had no better match
			if(!xml)
			{
				xml = fallback;
			}
		}
		return xml;
	}

	/**
	 * Load the xml from the given file
	 *
	 * Broken out here so it can be stubbed for testing
	 *
	 * @param path
	 * @returns {Promise<Element | void>}
	 * @protected
	 */
	protected loadFromFile(path)
	{
		return et2_loadXMLFromURL(path, null, this);
	}
	/**
	 * The template has been loaded, wait for child widgets to be complete.
	 *
	 * For webComponents, we wait for the widget's updateComplete.
	 * For legacy widgets, we let them finish and wait for their doLoadingFinished Promise
	 *
	 * @protected
	 */
	protected loadFinished()
	{
		// List of Promises from widgets that are not quite fully loaded
		let deferred = [];

		// Inform the widget tree that it has been successfully loaded.
		super.loadingFinished(deferred);

		// Don't wait for ourselves, it will never happen
		deferred = deferred.filter((d) => { return d.widget !== this});

		let ready = false;

		// Wait for everything to be loaded, then finish it up.  Use timeout to give anything else a chance
		// to run.
		return Promise.race([
			Promise.all(deferred).then(() => ready = true),
			// If loading takes too long, give some feedback so we can try to track down why
			new Promise((resolve) =>
			{
				setTimeout(() =>
					{
						if(ready)
						{
							return;
						}
						this.loadFailed("Load timeout");
						console.debug("This is the deferred widget list. Look for widgets still pending to find the problem", deferred);
						resolve();
					}, 10000
				);
			})
		]);
	}

	protected clear()
	{
		// Clear
		while(this.firstChild) this.removeChild(this.lastChild);
	}

	loadFailed(reason? : any)
	{
		const message = (this.templateName) + " @ " + this.getUrl() + (reason ? " \n" + reason : "");
		render(this.errorTemplate(message), this);
		this.egw().debug("warn", "Loading failed: " + message);
	}

	protected getUrl()
	{
		if(this.url)
		{
			return this.url;
		}

		let url = "";
		const parts = (this.template || this.id).split('?');
		const cache_buster = parts.length > 1 ? parts.pop() : null;
		let template_name = this.templateName;

		// Full URL passed as template?
		if(template_name.startsWith(this.egw().webserverUrl) && template_name.endsWith("xet"))
		{
			url = template_name;
		}
		else
		{

			const splitted = template_name.split('.');
			const app = splitted.shift();
			url = this.egw().link(
				'/' + app + "/templates/default/" + splitted.join('.') + ".xet",
				{download: cache_buster ? cache_buster : (new Date).valueOf()}
			);
		}

		// if we have no cache-buster, reload daily
		if(url.indexOf('?') === -1)
		{
			url += '?download=' + ((new Date).valueOf() / 86400 | 0).toString();
		}
		return url;
	}

	public get app()
	{
		const parts = (this.template || this.id).split('?');
		const cache_buster = parts.length > 1 ? parts.pop() : null;
		let template_name = parts.pop();

		const splitted = template_name.split('.');
		return splitted.shift() || "";
	}

	public get templateName()
	{
		const parts = (this.template || this.id).split('?');
		const cache_buster = parts.length > 1 ? parts.pop() : null;
		let template_name = parts.pop() || "";

		return template_name;
	}

	/**
	 * Override parent to support content attribute
	 * Templates always have ID set, but seldom do we want them to
	 * create a namespace based on their ID.
	 */
	checkCreateNamespace()
	{
		if(this.content)
		{
			const old_id = this.id;
			this._widget_id = this.content;
			super.checkCreateNamespace.apply(this, arguments);
			this._widget_id = old_id;
		}
	}

	_createNamespace() : boolean
	{
		return this.content && this.content != this.id;
	}

	handleLoad(event)
	{
		if(this.onload && typeof this.onload == "function")
		{
			// Make sure function gets a reference to the widget
			let args = Array.prototype.slice.call(arguments);
			if(args.indexOf(this) == -1)
			{
				args.push(this);
			}

			return this.onload.apply(this, args);
		}
	}

	loadingTemplate()
	{
		let loading = html`
            <sl-spinner></sl-spinner>`;


		return html`
            <div class="template--loading">${loading}</div>`;
	}

	errorTemplate(errorMessage = "")
	{
		return html`
            <sl-alert variant="warning" open>
                <sl-icon slot="icon" name="exclamation-triangle"></sl-icon>
                <strong>Loading failed</strong><br/>
                ${errorMessage}
            </sl-alert>`
	}

	render()
	{
		const classes = {
			template: true,
			'template--disabled': this.disabled,
			'template--readonly': this.readonly
		};
		if(this.app)
		{
			classes["template--app-" + this.app] = true;
		}
		if(this.layout != "none")
		{
			classes["layout-" + this.layout] = true;
			classes["template--layout-" + this.layout] = true;
		}
		return html`
            <div
                    part="base"
                    class=${classMap(classes)}
            >
                ${until(this.loading.then(() => nothing), this.loadingTemplate())}
                <slot></slot>
            </div>`
	}
}