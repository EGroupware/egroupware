import {LitElement, nothing, PropertyValues} from "lit";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {
	Et2CustomfieldSelectionItem,
	Et2CustomfieldsController,
	mergeCustomfieldSettingsFromSources
} from "./Et2CustomfieldsController";

export type Et2CustomfieldsMode = "customfields" | "customfields-list" | "customfields-filters" | "nextmatch-customfields";

/**
 * Base webcomponent for customfield-based widgets.
 *
 * It centralizes visibility/filter-state mapping while leaving concrete field rendering
 * to the specific widget/header implementations.
 */
export class Et2CustomfieldsBase extends Et2Widget(LitElement)
{
	@property({attribute: false})
	customfields : Record<string, any> = {};

	@property({attribute: false})
	fields : Record<string, boolean> = {};

	@property({type: String})
	exclude : string = "";

	@property({attribute: "type-filter"})
	typeFilter : string | string[] | "previous" | null = null;

	@property({type: String})
	tab : string | null = null;

	@property({attribute: false})
	mode : Et2CustomfieldsMode = "customfields";

	@state()
	protected _visibleFields : Record<string, boolean> = {};

	protected _controller : Et2CustomfieldsController | null = null;

	constructor(...args : any[])
	{
		super(...args);
	}

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
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(
			changedProperties.has("customfields") ||
			changedProperties.has("fields") ||
			changedProperties.has("exclude") ||
			changedProperties.has("typeFilter") ||
			changedProperties.has("tab") ||
			changedProperties.has("mode")
		)
		{
			this._recomputeVisibility();
		}
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
			tab: this.tab,
			mode: this.mode
		});
		this._visibleFields = this._controller.getVisibleMap();
	}

	render()
	{
		return nothing;
	}
}
