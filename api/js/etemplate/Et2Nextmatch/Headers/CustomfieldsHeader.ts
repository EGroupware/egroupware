import {html, LitElement, PropertyValues} from "lit";
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
 * @summary Renders visible custom fields as sortable Nextmatch headers.
 *
 * This component renders its nested sort headers into light DOM so the owning
 * Datagrid can discover and synchronize sort state. Custom field visibility is
 * managed by Nextmatch column selection and saved user preferences.
 *
 * @csspart base - The component's internal wrapper.
 * @csspart field-list - Scrollable container for visible custom field headers.
 * @csspart label - Fallback label shown when no custom fields are visible.
 */
@customElement("et2-nextmatch-header-customfields")
export class Et2CustomfieldsHeader extends Et2Widget(LitElement)
{
	/**
	 * Caption shown when no custom fields are visible.
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
	 * Retry interval used while waiting for custom field definitions from late
	 * template modifications.
	 */
	@property({type: Number, attribute: false})
	hydrationRetryMs : number = 80;

	/**
	 * Render nested sortheaders into light DOM so the owning Nextmatch/datagrid
	 * can reflect single-column sort state without piercing another shadow root.
	 */
	protected createRenderRoot()
	{
		return this;
	}

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
		if(!this._syncCustomfieldsFromModifications())
		{
			this._recomputeVisibility();
		}
		this._scheduleHydrationRetry();
	}

	disconnectedCallback()
	{
		this._clearHydrationRetry();
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
	}

	private _controller : Et2CustomfieldsController | null = null;
	private _pendingHydrationTimer : number | null = null;
	private _hydrationAttempts : number = 0;
	private _hasVisibilityOverride : boolean = false;
	private static readonly HYDRATION_RETRY_MAX = 8;

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

	private _recomputeVisibility()
	{
		this._controller = new Et2CustomfieldsController({
			customfields: this.customfields || {},
			fields: this.fields || {},
			exclude: this.exclude,
			typeFilter: this.typeFilter
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
            <table class="customfields-header__fields">
				<tbody>
				${fields.map((field) => html`
					<tr>
						<td>
							<et2-nextmatch-sortheader
                                    class="customfields-header__field-header"
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

	/**
	 * This component renders into light DOM so the owning Datagrid can discover
	 * nested sort headers. Lit static styles are shadow-root scoped, so these
	 * local styles need to render with the light-DOM template.
	 */
	private _lightDomStylesTemplate()
	{
		return html`
			<style>
				et2-nextmatch-header-customfields {
					display: block;
				}
				et2-nextmatch-header-customfields .label.et2_label_empty {
					min-width: var(--sl-spacing-small);
				}

                et2-nextmatch-header-customfields .customfields-header {
                    position: relative;
                }

                et2-nextmatch-header-customfields .customfields-header__fields {
					width: 100%;
					border-collapse: collapse;
				}

                et2-nextmatch-header-customfields .customfields-header__fields td {
					padding: 0;
					vertical-align: top;
				}

                et2-nextmatch-header-customfields .customfields-header__field-header {
					display: block;
					width: 100%;
				}

                et2-nextmatch-header-customfields .customfields-header__field-list {
					max-height: 5em;
					overflow: hidden;
                    overflow-y: auto;
				}
			</style>
		`;
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

	render()
	{
		const fields = this.getCustomfieldSelectionItems().filter((field) => field.visible === true);
		if(fields.length)
		{
			return html`
				${this._lightDomStylesTemplate()}
                <div class="customfields-header" part="base">
                    <div class="customfields-header__field-list" part="field-list">
                        ${this._fieldsTableTemplate(fields)}
                    </div>
                </div>
			`;
		}
		return html`
			${this._lightDomStylesTemplate()}
            <div class="customfields-header" part="base">
				<span
                        class="customfields-header__label label ${this._overflowCaption() ? "" : "et2_label_empty"}"
                        part="label"
                >${this._overflowCaption()}</span>
            </div>
		`;
	}
}
