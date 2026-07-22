/**
 * EGroupware eTemplate2 - Styles WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, html, LitElement} from "lit";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";

export function resolveEt2StylesSrc(src : string, egw? : any, templateUrl? : string) : string
{
	src = src || "";
	if(!src)
	{
		return "";
	}
	if(src.startsWith("data:") || src.startsWith("blob:") ||
		(src.startsWith("http") && src.indexOf("://") <= 5))
	{
		return src;
	}
	if(src[0] == "/")
	{
		return egw?.link?.(src) || src;
	}
	if(templateUrl)
	{
		return resolveEt2StylesSrcRelativeToTemplate(src, templateUrl);
	}
	return egw?.link?.("/" + src.replace(/^\//, "")) || src;
}

function resolveEt2StylesSrcRelativeToTemplate(src : string, templateUrl : string) : string
{
	try
	{
		const origin = window.location?.origin || "";
		const base = templateUrl.match(/^[a-z][a-z0-9+.-]*:\/\//i)
		             ? templateUrl
		             : origin + (templateUrl[0] == "/" ? templateUrl : "/" + templateUrl);
		const resolved = new URL(src, base);
		return origin && resolved.origin == origin
		       ? resolved.pathname + resolved.search + resolved.hash
		       : resolved.toString();
	}
	catch(_e)
	{
		const base = (templateUrl || "").split(/[?#]/, 1)[0];
		const directory = base.substring(0, base.lastIndexOf("/") + 1);
		return directory ? directory + src : src;
	}
}

/**
 * Et2Styles injects CSS into the document head.
 *
 * It is the modern WebComponent replacement for the legacy `et2_styles` widget
 * (`api/js/etemplate/et2_widget_styles.ts`).  Inline CSS can be passed either as
 * light-DOM text content (the classic `<styles>...</styles>` template usage) or
 * via the `value` property.  Additionally, an external stylesheet can be loaded
 * by setting the `src` property to a URL.
 *
 * The injected CSS is appended to the head of the EGroupware window so it can
 * influence the whole document, not just the element's shadow root.  When the
 * component is disconnected (removed from the DOM) the injected `<style>` and
 * `<link>` nodes are removed again so they don't accumulate.
 *
 * @slot - Inline CSS rules (legacy text content of the <styles> tag)
 *
 * @csspart styles - Not applicable; Et2Styles renders no shadow content.
 */
@customElement("et2-styles")
export class Et2Styles extends Et2Widget(LitElement)
{
	/** Inline CSS rules.  Set programmatically or via light-DOM text content. */
	@property({type: String})
	value = "";

	/**
	 * URL of an external stylesheet to load.
	 *
	 * Relative URLs are resolved against the EGroupware webserver root.
	 */
	@property({type: String})
	src = "";

	/** Style node holding the inline rules, appended to the document head */
	private _styleNode : HTMLStyleElement = null;

	/** Link node holding the external rules, appended to the document head */
	private _linkNode : HTMLLinkElement = null;

	/** The document head the nodes were injected into, captured at connect time */
	private _head : HTMLHeadElement = null;

	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			css`
				:host {
					display: none;
				}
			`
		];
	}

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
		// Some templates place CSS rules as text content inside the <styles>
		// tag. Read it before the initial render so it does not schedule a
		// follow-up Lit update from firstUpdated().
		if(!this.value && this.textContent)
		{
			this.value = this.textContent.trim();
		}

		// Start from a clean slate in case we are being re-connected so we never
		// leave orphaned nodes.
		this._removeInjectedNodes();

		// Capture the head we inject into so we can remove from the exact same
		// node on disconnect, even if egw() is no longer available then.
		this._head = this._getHead();

		// Create the style node and append it to the document head, matching the
		// legacy widget behaviour.
		this._styleNode = document.createElement("style");
		this._styleNode.setAttribute("type", "text/css");
		this._head.appendChild(this._styleNode);

		// Apply any inline rules (from value or light-DOM text content)
		this._applyStyles();

		// Apply an external stylesheet, if requested
		this._applySrc();
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this._removeInjectedNodes();
	}

	updated(changedProperties)
	{
		super.updated(changedProperties);

		// Re-apply inline CSS whenever the value (or the element id) changes
		if(changedProperties.has("value"))
		{
			this._applyStyles();
		}

		// (Re)load the external stylesheet whenever src changes
		if(changedProperties.has("src"))
		{
			this._applySrc();
		}

		// Mirror the (resolved) widget id onto the injected style node, just like
		// the legacy set_id() did.
		if(changedProperties.has("id"))
		{
			if(this._styleNode)
			{
				if(this.dom_id)
				{
					this._styleNode.setAttribute("id", this.dom_id);
				}
				else
				{
					this._styleNode.removeAttribute("id");
				}
			}
		}
	}

	/**
	 * Remove any style/link nodes previously injected into the head.
	 *
	 * @internal
	 */
	protected _removeInjectedNodes()
	{
		if(this._styleNode && this._styleNode.parentNode)
		{
			this._styleNode.parentNode.removeChild(this._styleNode);
		}
		if(this._linkNode && this._linkNode.parentNode)
		{
			this._linkNode.parentNode.removeChild(this._linkNode);
		}
		this._styleNode = null;
		this._linkNode = null;
	}

	/**
	 * Push the current inline CSS into the style node
	 *
	 * @internal
	 */
	protected _applyStyles()
	{
		if(!this._styleNode)
		{
			return;
		}
		// Modern browsers: assign the text.  (Legacy IE styleSheet.cssText path
		// is intentionally dropped - we no longer support IE.)
		this._styleNode.textContent = this.value || "";
	}

	/**
	 * Load (and/or update) the external stylesheet referenced by `src`
	 *
	 * @internal
	 */
	protected _applySrc()
	{
		const url = this._resolveSrc(this.src);
		if(!url)
		{
			// No (valid) source - drop any link node we created earlier
			if(this._linkNode && this._linkNode.parentNode)
			{
				this._linkNode.parentNode.removeChild(this._linkNode);
				this._linkNode = null;
			}
			return;
		}

		if(!this._linkNode)
		{
			this._linkNode = document.createElement("link");
			this._linkNode.setAttribute("rel", "stylesheet");
			this._linkNode.setAttribute("type", "text/css");
			this._head.appendChild(this._linkNode);
		}
		this._linkNode.setAttribute("href", url);
	}

	/**
	 * Resolve a (possibly relative) src into a usable URL
	 *
	 * Mirrors the URL handling used elsewhere in the framework (eg. Et2Image):
	 * absolute paths, http(s), data: and blob: URLs are used as-is, everything
	 * else is treated as a path relative to the EGroupware webserver root.
	 *
	 * @param _src
	 * @internal
	 */
	protected _resolveSrc(_src : string) : string
	{
		return resolveEt2StylesSrc(_src, this.egw(), this._templateUrl());
	}

	/**
	 * Get the URL of the containing template, if available, so bare src values
	 * like "row.css" resolve beside the template file.
	 *
	 * @internal
	 */
	protected _templateUrl() : string
	{
		const domTemplate = this.closest?.("et2-template") as any;
		if(typeof domTemplate?.getUrl == "function")
		{
			return domTemplate.getUrl();
		}
		let parent : any = typeof (this as any).getParent == "function" ? (this as any).getParent() : null;
		while(parent)
		{
			if(typeof parent.getUrl == "function")
			{
				return parent.getUrl();
			}
			parent = typeof parent.getParent == "function" ? parent.getParent() : null;
		}
		return "";
	}

	/**
	 * Get the document head to inject into.
	 *
	 * The legacy widget used the EGroupware (top) window's head.  Fall back to
	 * the current document if egw is not available (eg. unit tests).
	 *
	 * @internal
	 */
	protected _getHead() : HTMLHeadElement
	{
		try
		{
			const win : any = this.egw()?.window;
			if(win && win.document && win.document.head)
			{
				return win.document.head;
			}
		}
		catch(e)
		{
			// Ignore and fall back
		}
		return document.head;
	}

	render()
	{
		// Et2Styles injects into the document head; nothing to render itself.
		return html``;
	}
}
