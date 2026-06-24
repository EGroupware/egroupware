import {LitElement, nothing, PropertyValues, TemplateResult} from "lit";
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
	@property({type: Object, attribute: false})
	customfields : Record<string, any> = {};

	@property({attribute: false})
	fields : Record<string, boolean> = {};

	@property({type: Object, attribute: false})
	value : Record<string, any> = {};

	@property({type: String})
	exclude : string = "";

	@property({attribute: "type-filter"})
	typeFilter : string | string[] | "previous" | null = null;

	@property({type: String})
	tab : string | null = null;

	protected _visibleFields : Record<string, boolean> = {};

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
		this._visibleFields = this._controller?.getVisibleMap() || {};
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

	protected _recomputeVisibility()
	{
		this._controller = new Et2CustomfieldsController({
			customfields: this.customfields || {},
			fields: this.fields || {},
			exclude: this.exclude,
			typeFilter: this.typeFilter,
			tab: this.tab
		});
		this._visibleFields = this._controller.getVisibleMap();
	}

	render() : TemplateResult | typeof nothing
	{
		return nothing;
	}
}
