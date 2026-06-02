import {css, html, LitElement, PropertyValues} from "lit";
import {
	Et2CustomfieldsController,
	Et2CustomfieldSelectionItem,
	mergeCustomfieldSettingsFromSources
} from "../../Et2Customfields/Et2CustomfieldsController";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import "./SortableHeader";

/**
 * Header widget that renders visible custom fields as Nextmatch sort headers.
 *
 * The Datagrid column selection uses this component's public visibility methods
 * to keep custom field headers aligned with saved user column preferences.
 */
@customElement('et2-nextmatch-header-customfields')
export class Et2CustomfieldsHeader extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			super.styles,
			css`
				:host {
					display: block;
					position: relative;
				}
				.label.et2_label_empty {
					min-width: var(--sl-spacing-small);
				}

				.list {
					width: 100%;
					border-collapse: collapse;
				}

				.list td {
					padding: 0;
					vertical-align: top;
				}

				.field-header {
					display: block;
					width: 100%;
				}

				.list-clamp {
					max-height: 5em;
					overflow: hidden;
				}

				.overflow-caption {
					display: block;
					max-width: 100%;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}

				.hover-list {
					display: none;
					position: absolute;
					left: 0;
					top: 100%;
					min-width: 100%;
					max-height: 16em;
					overflow: auto;
					padding: var(--sl-spacing-medium);
					z-index: var(--sl-z-index-dropdown);
					background: var(--sl-panel-background-color);
					border: var(--sl-panel-border-width) solid var(--sl-panel-border-color);
					box-shadow: var(--sl-shadow-large);
				}

				:host(:hover) .hover-list,
				:host(:focus-within) .hover-list {
					display: block;
				}
			`
		];
	}

	/**
	 * Caption shown when no custom fields are visible, or when the visible field
	 * list is collapsed because it exceeds `maxVisibleFields`.
	 */
	@property({type: String})
	label : string = "Custom fields";

	/**
	 * Custom field definitions keyed by field name.
	 */
	@property({type: Object, attribute: false})
	customfields : Record<string, any> = {};

	/**
	 * Visibility map keyed by custom field name. Fields set to true are rendered
	 * as sortable headers. Template attributes may also use a comma-separated
	 * list of field names.
	 */
	@property({type: Object, attribute: false})
	fields : Record<string, boolean> = {};

	/**
	 * Comma-separated custom field names to omit from the header.
	 */
	@property({type: String})
	exclude : string = "";

	/**
	 * Optional custom field type filter. Use `"previous"` to reuse the prior
	 * customfields type filter, matching the legacy customfields behavior.
	 */
	@property({attribute: "type-filter"})
	typeFilter : string | string[] | "previous" | null = null;

	/**
	 * Maximum number of visible custom field header rows before collapsing to the
	 * caption and hover list.
	 */
	@property({type: Number, attribute: "max-visible-fields"})
	maxVisibleFields : number = 3;

	/**
	 * Retry interval used while waiting for custom field definitions from late
	 * template modifications.
	 */
	@property({type: Number, attribute: false})
	hydrationRetryMs : number = 80;

	private _controller : Et2CustomfieldsController | null = null;
	private _headerContainer : HTMLElement | null = null;
	private _previousHeaderStyles : {overflow : string; textOverflow : string; whiteSpace : string} | null = null;
	private _pendingHydrationTimer : number | null = null;
	private _hydrationAttempts : number = 0;
	private _hasVisibilityOverride : boolean = false;
	private static readonly HYDRATION_RETRY_MAX = 8;

	/**
	 * Fill missing custom field settings from template modifications before Lit
	 * applies attributes to the component.
	 */
	transformAttributes(attrs : Record<string, any>)
	{
		const modifications = this.getArrayMgr("modifications");
		const localData = this.id ? (modifications?.getEntry(this.id) || {}) : {};
		const globalData = modifications?.getRoot?.()?.getEntry("~custom_fields~", true) || {};
		mergeCustomfieldSettingsFromSources(attrs, localData, globalData);
		attrs.fields = this._normalizeFieldsAttribute(attrs.fields);
		if(typeof attrs.type_filter !== "undefined" && typeof attrs.typeFilter === "undefined")
		{
			attrs.typeFilter = attrs.type_filter;
		}
		super.transformAttributes(attrs);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._enableHeaderOverflow();
		if(!this._syncCustomfieldsFromModifications())
		{
			this._recomputeVisibility();
		}
		this._scheduleHydrationRetry();
	}

	disconnectedCallback()
	{
		this._clearHydrationRetry();
		this._restoreHeaderOverflow();
		super.disconnectedCallback();
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(
			changedProperties.has("customfields") ||
			changedProperties.has("fields") ||
			changedProperties.has("exclude") ||
			changedProperties.has("typeFilter")
		)
		{
			this._recomputeVisibility();
		}
		// Some templates populate global customfield modifications after initial attribute transform.
		// Keep one-way hydration from modifications so initial render has field list without user interaction.
		if(!this.customfields || !Object.keys(this.customfields).length || !this.fields || !Object.keys(this.fields).length)
		{
			this._syncCustomfieldsFromModifications();
		}
		this._scheduleHydrationRetry();
		this._enableHeaderOverflow();
	}

	private _normalizeFieldsAttribute(fields : Record<string, boolean> | string | null | undefined) : Record<string, boolean> | string | null | undefined
	{
		if(typeof fields !== "string")
		{
			return fields;
		}
		const trimmed = fields.trim();
		if(!trimmed)
		{
			return fields;
		}
		if(trimmed.startsWith("{"))
		{
			try
			{
				return JSON.parse(trimmed);
			}
			catch(e)
			{
				return fields;
			}
		}
		const result : Record<string, boolean> = {};
		trimmed.split(",")
			.map((name) => name.trim())
			.filter(Boolean)
			.forEach((name) => result[name] = true);
		return result;
	}

	/**
	 * Get custom fields in the shape expected by Nextmatch column selection.
	 */
	getCustomfieldSelectionItems() : Et2CustomfieldSelectionItem[]
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		return this._controller?.getSelectionItems() || [];
	}

	/**
	 * Get the current custom field visibility map.
	 */
	getCustomfieldVisibility() : Record<string, boolean>
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		return this._controller?.getVisibleMap() || {};
	}

	/**
	 * Apply custom field visibility from column selection or saved Datagrid
	 * preferences.
	 */
	setCustomfieldVisibility(fields : Record<string, boolean>)
	{
		if(!this._controller)
		{
			this._recomputeVisibility();
		}
		this._controller?.setVisibility(fields || {});
		this._hasVisibilityOverride = true;
		this.fields = {...fields};
		this.requestUpdate();
	}

	private _recomputeVisibility()
	{
		this._controller = new Et2CustomfieldsController({
			customfields: this.customfields || {},
			fields: this.fields || {},
			exclude: this.exclude,
			typeFilter: this.typeFilter,
			mode: "nextmatch-customfields"
		});
	}

	private _syncCustomfieldsFromModifications() : boolean
	{
		// Preserve user-selected visibility after column selection or persisted
		// Datagrid preferences have applied an explicit visibility map.
		const modifications = this.getArrayMgr("modifications");
		if(!modifications)
		{
			return false;
		}
		const localData = this.id ? (modifications.getEntry(this.id) || {}) : {};
		const globalData = modifications.getRoot?.()?.getEntry("~custom_fields~", true) || {};
		const previousFields = this.fields || {};
		const preserveVisibility = this._hasVisibilityOverride && Object.keys(previousFields).length > 0;
		const attrs : Record<string, any> = {
			customfields: this.customfields,
			fields: this.fields,
			exclude: this.exclude,
			typeFilter: this.typeFilter
		};
		const changed = mergeCustomfieldSettingsFromSources(attrs, localData, globalData);
		attrs.fields = this._normalizeFieldsAttribute(attrs.fields);
		if(changed)
		{
			this.customfields = attrs.customfields || {};
			this.fields = preserveVisibility ? {...previousFields} : (attrs.fields || {});
			this.exclude = attrs.exclude || this.exclude;
			this.typeFilter = typeof attrs.typeFilter === "undefined" ? this.typeFilter : attrs.typeFilter;
			this._recomputeVisibility();
			this._hydrationAttempts = 0;
		}
		return changed;
	}

	private _needsHydrationRetry() : boolean
	{
		return !this.customfields || !Object.keys(this.customfields).length;
	}

	private _scheduleHydrationRetry()
	{
		// Some templates populate custom field modifications after the first
		// attribute transform, so retry briefly before rendering the fallback.
		if(!this.isConnected || this._pendingHydrationTimer !== null || !this._needsHydrationRetry())
		{
			return;
		}
		if(this._hydrationAttempts >= Et2CustomfieldsHeader.HYDRATION_RETRY_MAX)
		{
			return;
		}
		const retryMs = Number.isFinite(this.hydrationRetryMs) && this.hydrationRetryMs > 0 ? this.hydrationRetryMs : 80;
		this._pendingHydrationTimer = window.setTimeout(() =>
		{
			this._pendingHydrationTimer = null;
			this._hydrationAttempts++;
			const changed = this._syncCustomfieldsFromModifications();
			if(changed)
			{
				this.requestUpdate();
			}
			this._scheduleHydrationRetry();
		}, retryMs);
	}

	private _clearHydrationRetry()
	{
		if(this._pendingHydrationTimer !== null)
		{
			window.clearTimeout(this._pendingHydrationTimer);
			this._pendingHydrationTimer = null;
		}
	}

	private _customfieldSortId(fieldName : string) : string
	{
		return `#${fieldName}`;
	}

	private _overflowCaption() : string
	{
		return String(this.label || "").trim() || "Custom fields";
	}

	private _fieldsTableTemplate(fields : Et2CustomfieldSelectionItem[])
	{
		return html`
			<table class="list">
				<tbody>
				${fields.map((field) => html`
					<tr>
						<td>
							<et2-nextmatch-sortheader
								class="field-header"
								id=${this._customfieldSortId(field.name)}
								label=${field.label}
							></et2-nextmatch-sortheader>
						</td>
					</tr>
				`)}
				</tbody>
			</table>
		`;
	}

	private _enableHeaderOverflow()
	{
		// The compact hover list needs to extend beyond the datagrid header cell.
		const parent = this.parentElement;
		if(!parent || !parent.classList.contains("dg-col-inner"))
		{
			return;
		}
		if(this._headerContainer === parent)
		{
			return;
		}
		this._headerContainer = parent;
		this._previousHeaderStyles = {
			overflow: parent.style.overflow || "",
			textOverflow: parent.style.textOverflow || "",
			whiteSpace: parent.style.whiteSpace || ""
		};
		parent.style.overflow = "visible";
		parent.style.textOverflow = "clip";
		parent.style.whiteSpace = "normal";
	}

	private _restoreHeaderOverflow()
	{
		if(!this._headerContainer || !this._previousHeaderStyles)
		{
			return;
		}
		this._headerContainer.style.overflow = this._previousHeaderStyles.overflow;
		this._headerContainer.style.textOverflow = this._previousHeaderStyles.textOverflow;
		this._headerContainer.style.whiteSpace = this._previousHeaderStyles.whiteSpace;
		this._headerContainer = null;
		this._previousHeaderStyles = null;
	}

	/**
	 * Reflect the active Nextmatch sort state into the rendered custom field
	 * sort headers.
	 */
	setSortState(sortId : string | null, mode : "none" | "asc" | "desc")
	{
		const currentSortId = String(sortId || "");
		const headers = Array.from(this.renderRoot.querySelectorAll("et2-nextmatch-sortheader")) as any[];
		headers.forEach((header) =>
		{
			const headerMode = currentSortId && header.id === currentSortId ? mode : "none";
			if(typeof header.setSortmode === "function")
			{
				header.setSortmode(headerMode);
			}
			else if(typeof header.set_sortmode === "function")
			{
				header.set_sortmode(headerMode);
			}
		});
	}

	render()
	{
		const fields = this.getCustomfieldSelectionItems().filter((field) => field.visible === true);
		if(fields.length)
		{
			const list = this._fieldsTableTemplate(fields);
			const maxVisibleFields = Number.isFinite(this.maxVisibleFields) && this.maxVisibleFields >= 0 ?
				Math.floor(this.maxVisibleFields) : 3;
			const isOverflowed = fields.length > maxVisibleFields;
			if(isOverflowed)
			{
				return html`
					<span class="overflow-caption">${this._overflowCaption()}</span>
					<div class="hover-list">${list}</div>
				`;
			}
			return html`
				<div class="list-clamp">${list}</div>
			`;
		}
		return html`
			<span class="label ${this._overflowCaption() ? "" : "et2_label_empty"}">${this._overflowCaption()}</span>
		`;
	}
}
