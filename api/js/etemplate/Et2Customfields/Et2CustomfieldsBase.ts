import {CSSResult, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {html as staticHtml, unsafeStatic} from "lit/static-html.js";
import {property} from "lit/decorators/property.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {
	Et2CustomfieldsController,
	Et2CustomfieldSelectionItem,
	mergeCustomfieldSettingsFromSources
} from "./Et2CustomfieldsController";

/**
 * Customfield values are stored on row/content records using the historical #name key.
 */
export const CUSTOMFIELD_PREFIX = "#";

/**
 * Render a light-DOM widget's stylesheet as a <style> in its own subtree.
 *
 * Lit adopts `static styles` into a shadow root, so a widget whose createRenderRoot() returns
 * `this` never gets them.  Adopting the sheet onto the root the widget is in does not work
 * either: a nextmatch puts these inside et2-datagrid's shadow root, and the datagrid reassigns
 * that whole root's adoptedStyleSheets list whenever its row stylesheets change, dropping
 * anything a child had put there.  A <style> the widget renders itself survives that in every
 * root, and still comes from the same .styles.ts the CSS would otherwise live in twice.
 *
 * The CSS is spliced in statically, so it is part of the cached template rather than a binding
 * inside <style>, which lit does not support.
 *
 * @param {CSSResult} styles The widget's stylesheet.
 */
export function lightDomStylesTemplate(styles : CSSResult)
{
	return staticHtml`
		<style>${unsafeStatic(styles.cssText)}</style>`;
}

/**
 * Base webcomponent for customfield-based widgets.
 *
 * It centralizes visibility/filter-state mapping while leaving concrete field rendering
 * to the specific widget/header implementations.
 *
 * @property customfields - Customfield metadata keyed by unprefixed field name.
 * @property fields - Visibility map keyed by unprefixed field name.
 * @property value - Customfield values keyed by prefixed field name, e.g. #cf_name.
 * @property exclude - Comma-separated field names that should be hidden.
 * @property typeFilter - Type filter, array of filters, or previous filter reuse.
 * @property tab - Optional tab restriction for rendered customfields.
 */
export class Et2CustomfieldsBase extends Et2Widget(LitElement)
{
	static get styles()
	{
		return super.styles ?? [];
	}

	@property({type: Object, attribute: false})
	customfields : Record<string, any> = {};

	/**
	 * Which customfields to show, keyed by unprefixed name.
	 *
	 * Typed as an object so transformAttributes() hands the map over untouched - an untyped
	 * property goes through `"" + value` instead, which turns it into "[object Object]".  A
	 * template may still give a comma-separated list of names as a string.
	 */
	@property({type: Object, attribute: false})
	fields : Record<string, boolean> | string = {};

	@property({type: Object, attribute: false})
	value : Record<string, any> = {};

	@property({type: String})
	exclude : string = "";

	@property({attribute: "type-filter"})
	typeFilter : string | string[] | "previous" | null = null;

	@property({type: String})
	tab : string | null = null;

	protected _controller : Et2CustomfieldsController | null = null;

	transformAttributes(attrs : Record<string, any>)
	{
		const modifications = this.getArrayMgr("modifications");
		const localData = this.id ? (modifications?.getEntry(this.id) || {}) : {};
		const globalData = modifications?.getRoot?.()?.getEntry("~custom_fields~", true) || {};
		mergeCustomfieldSettingsFromSources(attrs, localData, globalData);
		if(typeof attrs.type_filter !== "undefined" && typeof attrs.typeFilter === "undefined")
		{
			attrs.typeFilter = attrs.type_filter;
		}
		super.transformAttributes(attrs);

		// super applies our own modifications straight onto us, so a customfields tab that carries
		// an empty set there overwrites the definitions just merged in, leaving a tab with nothing
		// in it although the app has customfields.  The shared definitions are the fallback for
		// exactly this, so put them back.
		const defined = (fields : any) => !!fields && typeof fields === "object" && Object.keys(fields).length > 0;
		if(!defined(this.customfields) && defined(globalData.customfields))
		{
			this.customfields = globalData.customfields;
		}

		const rowValues = this._rowCustomfieldValues();
		if(rowValues !== null)
		{
			this.value = rowValues;
		}
	}

	/**
	 * Recompute visibility before render so subclasses can use getVisibleFieldNames()
	 * without waiting for a second Lit update.
	 */
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);
		if(
			changedProperties.has("customfields") ||
			changedProperties.has("fields") ||
			changedProperties.has("exclude") ||
			changedProperties.has("typeFilter") ||
			changedProperties.has("tab")
		)
		{
			this._recomputeVisibility();
		}
	}

	/**
	 * Resolve row-scoped #customfield values for legacy widgets using array managers.
	 */
	protected _rowCustomfieldValues() : Record<string, any> | null
	{
		const contentMgr = this.getArrayMgr("content");
		if(!contentMgr)
		{
			return null;
		}
		const value : Record<string, any> = {};
		const rowValue = this.id ? contentMgr.getEntry(this.id) : null;
		if(rowValue && typeof rowValue === "object")
		{
			const customfields = this.customfields || {};
			for(const key of Object.keys(customfields))
			{
				value[CUSTOMFIELD_PREFIX + key] = this._customfieldValueFromRecord(rowValue, key) ?? "";
			}
		}
		else
		{
			const customfields = this.customfields || {};
			for(const key of Object.keys(customfields))
			{
				const prefixedKey = CUSTOMFIELD_PREFIX + key;
				value[prefixedKey] = this._customfieldValueFromContent(contentMgr, key);
			}
		}
		return value;
	}

	private _customfieldValueFromContent(contentMgr : any, fieldName : string)
	{
		const prefixedKey = CUSTOMFIELD_PREFIX + fieldName;
		const direct = contentMgr.getEntry?.(prefixedKey);
		if(typeof direct !== "undefined" && direct !== null)
		{
			return direct;
		}

		const perspective = contentMgr.getPerspectiveData?.();
		const rowIndex = perspective?.row;
		const rowData = typeof rowIndex !== "undefined" && rowIndex !== null ? contentMgr.data?.[rowIndex] : null;
		const rowValue = this._customfieldValueFromRecord(rowData, fieldName);
		if(typeof rowValue !== "undefined")
		{
			return rowValue;
		}

		const rootValue = this._customfieldValueFromRecord(contentMgr.data, fieldName);
		if(typeof rootValue !== "undefined")
		{
			return rootValue;
		}

		return "";
	}

	private _customfieldValueFromRecord(record : any, fieldName : string)
	{
		if(!record || typeof record !== "object")
		{
			return undefined;
		}
		const prefixedKey = CUSTOMFIELD_PREFIX + fieldName;
		if(Object.prototype.hasOwnProperty.call(record, prefixedKey))
		{
			return record[prefixedKey];
		}
		return undefined;
	}

	/**
	 * @deprecated Legacy et2_customfields_list method name, kept for callers that still hold a
	 * legacy widget reference.  Use setCustomfieldVisibility() instead.
	 *
	 * @param {Object} fields Visibility keyed by unprefixed field name.
	 */
	set_visible(fields : Record<string, boolean>)
	{
		this.setCustomfieldVisibility(fields);
	}

	setCustomfieldVisibility(fields : Record<string, boolean>)
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		this._controller?.setVisibility(fields || {});
		this.fields = {...fields};
	}

	getCustomfieldVisibility() : Record<string, boolean>
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		return this._controller?.getVisibleMap() || {};
	}

	getCustomfieldSelectionItems() : Et2CustomfieldSelectionItem[]
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		return this._controller?.getSelectionItems() || [];
	}

	getVisibleFieldNames() : string[]
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		return this._controller?.getVisibleFieldNames() || [];
	}

	/**
	 * Work out which tab we are in, for `tab="panel"`.
	 *
	 * The server adds one catch-all customfields tab, named cf-default, cf-default-private or
	 * cf-default-non-private.  A customfield no named tab claimed belongs there - narrowed to
	 * just the private or just the non-private ones when the name says so - so that tab takes no
	 * name of its own.  In any other tab we take the customfields assigned to its label.
	 */
	protected _resolvedTab() : { tab : string | null, defaultTabMatch : "" | "-private" | "-non-private" | null }
	{
		if(this.tab !== "panel")
		{
			return {tab: this.tab, defaultTabMatch: null};
		}
		const panel = this.closest("et2-tab-panel");
		if(!panel)
		{
			return {tab: null, defaultTabMatch: null};
		}
		const name = (<any>panel).name ?? panel.id;
		const defaultTab = String(name).match(/^cf-default(-(non-)?private)?$/);
		if(defaultTab)
		{
			return {tab: null, defaultTabMatch: <"" | "-private" | "-non-private">(defaultTab[1] ?? "")};
		}
		return {tab: this._tabLabel(panel, name), defaultTabMatch: null};
	}

	/**
	 * Label of the tab we sit in, which is what a customfield's own `tab` is matched against.
	 *
	 * The rendered tab element carries the translated label and is what a desktop dialog shows.  A
	 * mobile dialog's tabbox renders no tab elements at all - it puts the labels in a menu - so fall
	 * back to the tab data the tabbox was built from.  Getting nothing here means no filtering, ie.
	 * every customfield in every tab, so it is worth having both.
	 *
	 * @param {Element} panel The tab panel we are in.
	 * @param {string} name Its name, which the tabs are keyed by.
	 */
	private _tabLabel(panel : Element, name : string) : string | null
	{
		let tabbox : any = panel.parentElement;
		while(tabbox && !tabbox.tabData)
		{
			tabbox = tabbox.parentElement ?? (<ShadowRoot>tabbox.getRootNode())?.host;
		}
		const rendered = <HTMLElement>tabbox?.querySelector?.('et2-tab[panel="' + name + '"]');
		return rendered?.innerText ?? tabbox?.tabData?.find(tab => tab.id === name)?.label ?? null;
	}

	protected _recomputeVisibility()
	{
		const {tab, defaultTabMatch} = this._resolvedTab();
		this._controller = new Et2CustomfieldsController({
			customfields: this.customfields || {},
			fields: this.fields || {},
			exclude: this.exclude,
			typeFilter: this.typeFilter,
			tab,
			defaultTabMatch
		});
	}

	render() : TemplateResult | typeof nothing
	{
		return nothing;
	}
}
