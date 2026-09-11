/**
 * EGroupware eTemplate2 - Filterbox WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import styles from "./Et2Filterbox.styles";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {LitElement, nothing} from "lit";
import {html} from "lit/static-html.js";
import {et2_INextmatchHeader, et2_nextmatch} from "../et2_extension_nextmatch";
import {Et2Favorites} from "../Et2Favorites/Et2Favorites";
import {classMap} from "lit/directives/class-map.js";
import {HasSlotController} from "../Et2Widget/slot";
import shoelace from "../Styles/shoelace";
import {Et2Template} from "../Et2Template/Et2Template";
import {et2_arrayMgr} from "../et2_core_arrayMgr";
import {et2_IInput} from "../et2_core_interfaces";
import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";

/**
 * @summary A list of filters ( from a nextmatch )
 *
 *
 * @slot label - The input's label. Alternatively, you can use the `label` attribute.
 * @slot prefix - Used to prepend content above the list of filters
 * @slot suffix - Like prefix, but after
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event change - Emitted when the control's value changes.
 *
 * @csspart form-control - The form control that wraps the label, input, and help text.
 * @csspart form-control-label - The label's wrapper.
 * @csspart form-control-input - The textbox's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart suffix - The container that wraps the suffix slot.
 * @csspart listbox - The listbox container where filters are slotted.
 * @csspart filter - Each filter item
 */
@customElement("et2-filterbox")
export class Et2Filterbox extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			shoelace,
			...(Array.isArray(super.styles) ? super.styles : [super.styles]),
			styles
		];
	}

	/* Adds a clear button when the filters are not empty. */
	@property({type: Boolean}) clearable = false;

	/* Apply changes immediately or wait for apply button */
	@property({type: Boolean}) autoapply = false;

	/* Specify filters explicitly instead of reading them from a nextmatch */
	@property({type: Array})
	filters : Filter[] = [];

	/**
	 * If nextmatch ID is provided, the filters will be read from the nextmatch header.
	 *
	 * This uses a custom accessor instead of Lit's generated setter so _findNextmatch() runs
	 * deterministically as soon as this is set, rather than only via willUpdate()'s batched
	 * changedProperties diffing. The modern Et2Nextmatch hands us its own instance directly here
	 * (see Et2Nextmatch._ensureFilterbox()) before this filterbox is even connected to the
	 * document - relying solely on the next render pass left a window where willUpdate()'s
	 * change-detection didn't line up with that timing and this filterbox's 'et2-filter' listener
	 * silently never got attached at all, permanently freezing its widgets (eg. a date range
	 * filter drawer stuck showing stale values while nm's own filters kept updating correctly
	 * underneath). Only gate the eager call on a real Et2Nextmatch instance (not a string id) -
	 * that path doesn't need a connected DOM to resolve (see _findNextmatch()), unlike the legacy
	 * string-id lookup, which does and is left on willUpdate()'s existing, already-working timing.
	 */
	@property({type: String})
	set nextmatch(value : string | et2_nextmatch)
	{
		const oldValue = this._nextmatchValue;
		if(value === oldValue)
		{
			return;
		}
		this._nextmatchValue = value;
		this.requestUpdate("nextmatch", oldValue);
		if(value && typeof value !== "string")
		{
			this._findNextmatch();
		}
	}

	get nextmatch() : string | et2_nextmatch
	{
		return this._nextmatchValue;
	}

	private _nextmatchValue : string | et2_nextmatch = null;

	/* When copying from a nextmatch, we can leave, delete or replace column headers with text in place of the original widgets */
	@property({type: String})
	originalWidgets : "none" | "delete" | "hide" | "replace" = "none";

	protected hasSlotController = new HasSlotController(this, "label", "help-text", "prefix", "suffix");
	protected _nextmatch : et2_nextmatch = null;
	protected _groups : {
		[nextmatch_id : string] : { [name : string] : { filters : Filter[], order : number, dataId? : string } }
	} = {};
	private _filterTemplateUpdateToken : number = 0;
	private _activeFilterTemplate : HTMLElement | null = null;
	// Set while handleNextmatchFilter() is pushing nextmatch's state into our widgets, so their
	// resulting native "change" events don't echo straight back into applyFilters() and re-push
	// a stale snapshot (widgets whose "empty" value isn't falsy, eg. a link-entry {app, id: ''})
	// into nextmatch - see handleFilterChange(). Widgets built on Et2InputWidget dispatch that
	// "change" only after their own updateComplete resolves (see handleSlChange()), ie. on a
	// later microtask than the synchronous set_value() call - so the guard has to stay up until
	// _templateValues's _pendingWidgetUpdates settles, not just until set_value() returns.
	private _syncingFromNextmatch : boolean = false;
	private _pendingWidgetUpdates : Promise<any> = Promise.resolve();

	constructor()
	{
		super();
		this.applyFilters = this.applyFilters.bind(this);
		this.handleNextmatchFilter = this.handleNextmatchFilter.bind(this);
		this.handleSlotChange = this.handleSlotChange.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback()
		//intercept all keydown events from reaching the nextmatch
		document.addEventListener("keydown", this.handleKeypress, {capture: true});
		this.addEventListener("slotchange", this.handleSlotChange);
		this._nextmatch?.getDOMNode()?.addEventListener("et2-filter",this.handleNextmatchFilter);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback()
		document.removeEventListener("keydown", this.handleKeypress, {capture: true});
		this.removeEventListener("slotchange", this.handleSlotChange);
		this._nextmatch?.getDOMNode()?.removeEventListener("et2-filter", this.handleNextmatchFilter);
	}

	willUpdate(changedProperties : Map<string, unknown>)
	{
		if(changedProperties.has("nextmatch"))
		{
			// Lost the nextmatch?  Clear its filters
			if(!this.nextmatch && this._nextmatch)
			{
				this.filters = [];
			}
			if(this.nextmatch && this.nextmatch !== this._nextmatch)
			{
				this._findNextmatch();
			}
		}
	}

	/**
	 * We're not finished updating until any values we're pushing into our widgets have landed.
	 * Those set_value() calls settle a microtask or more after they're made, so without this
	 * anything that awaits updateComplete before reading our value still gets the old one.
	 */
	async getUpdateComplete() : Promise<boolean>
	{
		const result = await super.getUpdateComplete();
		await this._pendingWidgetUpdates;
		return result;
	}

	public applyFilters()
	{
		const value = this.value;
		const changeEvent = new CustomEvent("change", {
			detail: value,
			bubbles: true,
			composed: true,
			cancelable: true
		});
		this.dispatchEvent(changeEvent);
		if(this._nextmatch && !changeEvent.defaultPrevented)
		{
			this._nextmatch.applyFilters(value);

			// Call without update so nm updates the indicator in column header
			if(value["sort"])
			{
				this._nextmatch.sortBy(value["sort"].id, value["sort"].asc, false);
			}
		}
	}

	public clearFilters()
	{
		this.filters.forEach((filter) =>
		{
			filter.value = "";
			if(filter.widget)
			{
				filter.widget.value = "";
			}
		});
		this.applyFilters();
	}

	public set value(newValue : object)
	{
		// Custom content is reflected inside, not an actual child so we need to trace the slot
		// and set the values via widget
		if(this.hasSlotController.test('[default]'))
		{
			this._templateValues = newValue;
		}
	}
	public get value()
	{
		const value = {};
		// Custom content is reflected inside, not an actual child so we need to trace the slot
		// and get the values from the et2 instance
		if(this.hasSlotController.test('[default]'))
		{
			// @ts-ignore
			Array.from(this.querySelectorAll(':scope > *')).forEach((element : Et2Widget) =>
			{
				if(typeof element.getInstanceManager == "function" && element.getInstanceManager())
				{
					let templateValue = element.getInstanceManager().getValues(element);
					// @ts-ignore
					this.getPath().toReversed().forEach(p => templateValue = templateValue[p]);
					Object.assign(value, templateValue);
				}
			});
		}

		return value;
	}

	private set _templateValues(newValue : object)
	{
		// Use an array mgr to hande non-simple IDs
		const mgr = new et2_arrayMgr(newValue);
		this._syncWidgetValues(() =>
		{
			const pending : Promise<any>[] = [];
			// @ts-ignore
			Array.from(this.querySelectorAll(':scope > *')).forEach((element : Et2Template | typeof Et2Widget) =>
			{
				// @ts-ignore
				typeof element.iterateOver == "function" && element.iterateOver(function(child)
				{
					let value : string | object = '';
					if(typeof child.set_value != "undefined" && child.id)
					{
						value = mgr.getEntry(child.id);
						if(value == null)
						{
							value = '';
						}
						child.set_value(value);
						if(child.updateComplete)
						{
							pending.push(child.updateComplete);
						}
					}
				}, newValue, et2_IInput);
			});
			return pending;
		});
	}

	/**
	 * Push a programmatic value into one or more widgets without it echoing back out through
	 * handleFilterChange() as if the user had edited something.
	 *
	 * Widgets built on Et2InputWidget only dispatch their "change" event after their own
	 * updateComplete resolves (see handleSlChange()) - a later microtask than the synchronous
	 * set_value() call - so the guard has to stay up until every touched widget's updateComplete
	 * settles, not just until `apply` returns. Any code that calls set_value() on adopted/synced
	 * widgets on nextmatch's behalf (not in reaction to the user editing them) should go through
	 * this, not set_value() directly - see handleFilterChange().
	 *
	 * @param apply Perform the set_value() calls and return each touched widget's updateComplete
	 */
	private _syncWidgetValues(apply : () => Promise<any>[])
	{
		this._syncingFromNextmatch = true;
		this._pendingWidgetUpdates = Promise.all(apply());
		this._pendingWidgetUpdates.finally(() =>
		{
			this._syncingFromNextmatch = false;
		});
	}

	/**
	 * Find our nextmatch widget
	 *
	 * @protected
	 */
	protected _findNextmatch()
	{
		if(!this.nextmatch)
		{
			this._nextmatch = null;
			return;
		}
		let root = <HTMLElement>this.getRootNode();
		if(root instanceof ShadowRoot)
		{
			root = <HTMLElement>root.host;
		}
		// Find a matching nextmatch widget
		// @ts-ignore getRoot & getInstanceManager do exist
		this._nextmatch = typeof this.nextmatch == "string" ?
			// @ts-ignore getRoot might exist
						  this.getRoot().getWidgetById(this.nextmatch) ??
							  // @ts-ignore getInstanceManager() might exist
							  this.getInstanceManager()?.widgetContainer?.getWidgetById(this.nextmatch) ??
							  // Find the DOMNode, but then need to find the nextmatch widget
							  root.querySelector("[id$=" + this.nextmatch + "]").closest("et2-template").getWidgetById(this.nextmatch) :
						  this.nextmatch;

		// Found a nextmatch and there's no custom filter - autogenerate filters
		if(this._nextmatch)
		{
			const nextmatchNode = typeof this._nextmatch.getDOMNode === "function" ? this._nextmatch.getDOMNode() : this._nextmatch;
			// Don't bind now, nextmatch probably isn't loaded yet
			// @ts-ignore template_promise is private, but et2_nextmatch doesn't have updateComplete()
			(this._nextmatch.template_promise ?? Promise.resolve()).then(() => this.readNextmatchFilters());

			nextmatchNode?.addEventListener?.("et2-filter", this.handleNextmatchFilter);
			nextmatchNode?.classList?.add("et2-filterbox--loaded");
		}
	}

	public async readNextmatchFilters()
	{
		const nextmatchNode = typeof this._nextmatch?.getDOMNode === "function" ? this._nextmatch.getDOMNode() : this._nextmatch;

		// Wait for nextmatch widgets to finish or we'll miss settings
		const waitForWebComponents = [];
		if(typeof this._nextmatch?.getChildren === "function")
		{
			this._nextmatch.getChildren().forEach((w) =>
			{
				// @ts-ignore
				if(typeof w.updateComplete !== "undefined")
				{
					// @ts-ignore
					waitForWebComponents.push(w.updateComplete)
				}
			});
		}
		// @ts-ignore updateComplete exists on web components
		else if(typeof this._nextmatch?.updateComplete !== "undefined")
		{
			// @ts-ignore
			waitForWebComponents.push(this._nextmatch.updateComplete);
		}
		await Promise.all(waitForWebComponents);

		if(this._nextmatch?.header?.header_div?.[0])
		{
			// @ts-ignore header is private
			this._nextmatch.header.header_div[0]
				.querySelectorAll(".et2-input-widget")
				.forEach((widget : HTMLElement) =>
				{
					this._adoptNextmatchWidget(widget);
				});
		}

		// Now for column headers
		const filters = Array.from(nextmatchNode?.querySelectorAll?.("et2-nextmatch-header-filter, et2-nextmatch-header-account, et2-nextmatch-header-entry, et2-nextmatch-header-custom") ?? []);
		filters.forEach((widget : HTMLElement) =>
		{
			this._adoptNextmatchWidget(widget);
		});

		nextmatchNode?.classList?.add("et2-filterbox--" + this.originalWidgets);

		if(this._nextmatch?.options)
		{
			// If the nextmatch has sub-headers and we didn't grab everything from them, mark the NM so we don't hide them
			const subHeaders = ["header_left", "header_right", "header_row", "header2"];
			subHeaders.forEach(subHeader =>
			{
				if(this._nextmatch.options[subHeader])
				{
					const subTemplate = this._nextmatch.getWidgetById(this._nextmatch.options[subHeader]);
					if(subTemplate && subTemplate.childElementCount > 0)
					{
						nextmatchNode?.classList?.add("et2-filterbox--has-header");
					}
				}
			});
			if(this._nextmatch.options.settings?.lettersearch)
			{
				nextmatchNode?.classList?.add("et2-filterbox--has-lettersearch");
			}
		}
		this.requestUpdate();
	}

	private _adoptNextmatchWidget(widget) : Filter
	{
		const noReplaceClasses = ['et2-nextmatch-header-entry'];
		const dealWithOriginal = (widget) =>
		{
			switch(this.originalWidgets)
			{
				case "none":
					break;
				case "replace":
					if(!noReplaceClasses.includes(widget.localName) && widget.implements(et2_INextmatchHeader))
					{
						const replacement = document.createElement("span");
						replacement.innerHTML = widget.label || widget.ariaLabel || widget.placeholder || widget.emptyLabel;
						widget.replaceWith(replacement);
						break;
					}
				// Fall through
				case "delete":
					widget.remove();
					break;
				case "hide":
					widget.classList.add("hideme");
					widget.ariaHidden = true;
					break;

			}
		};
		// Skip hidden widgets
		if(!widget.checkVisibility())
		{
			return;
		}
		// Skip buttons
		if(widget instanceof HTMLButtonElement || widget.classList.contains("et2-button-widget"))
		{
			return;
		}
		// Skip favourites
		if(widget instanceof Et2Favorites)
		{
			return;
		}

		dealWithOriginal(widget);
	}

	private _sortFilters()
	{
		Object.entries(this._groups).forEach(([nm_id, group]) =>
		{
			// Make sure filters are in group if they weren't already
			if(this.filters.length > 0 && Object.keys(group).length == 0)
			{
				group[''] = {filters: this.filters, order: 0};
			}
			else
			{
				// Convert the _groups object to an array of entries, sort them, and reconstruct the object
				const sortedGroups = Object.entries(group)
					.map(([groupName, group]) => [groupName, {
						filters: group.filters,
						order: group.order
					}])
					.sort(([keyA, groupA], [keyB, groupB]) =>
					{
						// Sort by the 'order' property of the group objects
						return groupA.order - groupB.order;
					});

				// Rebuild the object in sorted order
				group = Object.fromEntries(sortedGroups);
			}

			// Sort the filters within each group by their order
			Object.keys(group).forEach((groupName) =>
			{
				group[groupName]?.filters?.sort((a, b) => a.order - b.order);
			});
		});
	}


	protected handleFilterChange(event : Event)
	{
		if(this._syncingFromNextmatch)
		{
			return;
		}
		if(this.autoapply)
		{
			event.stopPropagation();
			this.applyFilters();
		}
	}

	/**
	 * Enable the filterbox to intercept keypresses from the nextmatch before they reach it
	 * @param event
	 * @private
	 */
	private handleKeypress(event)
	{
		// Only intercept keypresses when filters drawer is open
		if(!event?.target?.filtersDrawer?.open)
		{
			return
		}
		if(event.key == "Escape")
		{
			event.target.filtersDrawer.hide();
		}
		event.stopPropagation();
	}

	/**
	 * The nextmatch filtered, update our values to match
	 *
	 * @param event
	 * @private
	 */
	private handleNextmatchFilter(event)
	{
		if(!event.detail?.activeFilters)
		{
			return;
		}
		// set value triggers _templateValues, which guards itself via _syncWidgetValues()
		this.value = event.detail.activeFilters;
	}

	private handleSlotChange(event)
	{
		// Slot content can be dynamic; trigger re-evaluation for value mapping.
		this.requestUpdate();
	}

	/**
	 * Public API used by nextmatch to apply a filter template source.
	 *
	 * @param template string template id/url or ready template element
	 */
	public setFilterTemplate(template : string | Et2Template | HTMLElement | null)
	{
		void this._setFilterTemplate(template);
	}

	/**
	 * Apply a filter template from string/template element with deterministic race handling.
	 *
	 * We deliberately avoid timer-based waiting and use a monotonic token so only the
	 * latest requested template can be attached.
	 */
	private async _setFilterTemplate(template : string | Et2Template | HTMLElement | null)
	{
		const updateToken = ++this._filterTemplateUpdateToken;
		this._activeFilterTemplate?.remove();
		this._activeFilterTemplate = null;

		if(!template)
		{
			return;
		}

		let templateElement : HTMLElement | null = null;
		if(typeof template === "string")
		{
			const isUrl = /^(http|\/).*\.xet($|\?)/.test(template);
			templateElement = <Et2Template><unknown>loadWebComponent("et2-template", {
				id: "filter-template",
				template: isUrl ? "" : template,
				url: isUrl ? template : ""
			}, this);
		}
		else if(template instanceof HTMLElement)
		{
			templateElement = template;
		}
		if(!templateElement)
		{
			return;
		}

		const load = (templateElement as any).load;
		if(typeof load === "function")
		{
			try
			{
				await load.call(templateElement);
			}
			catch(e)
			{
				if(updateToken === this._filterTemplateUpdateToken)
				{
					console.error(e);
				}
				return;
			}
		}
		if(updateToken !== this._filterTemplateUpdateToken)
		{
			return;
		}
		this._activeFilterTemplate = templateElement;
		// Guard the whole append+settle window, not just the explicit sort seeding below -
		// a freshly-appended template's widgets can dispatch their own "change" purely from
		// first-render self-correction (eg. a select whose server-rendered value doesn't
		// match its options), which would otherwise echo into handleFilterChange() just like
		// an explicit set_value() would.
		this._syncWidgetValues(() =>
		{
			this.append(templateElement);
			const pending = this._syncSortWidgets(templateElement);
			if(typeof (<any>templateElement).iterateOver === "function")
			{
				(<any>templateElement).iterateOver((child) =>
				{
					if(child.updateComplete)
					{
						pending.push(child.updateComplete);
					}
				}, null, et2_IInput);
			}
			return pending;
		});
	}

	/**
	 * Seed the hidden sort[id]/sort[asc] widgets that filter-template.php generates
	 * (it has no way to know the nextmatch's real current sort server-side) with
	 * this._nextmatch's actual sort, so a first autoapply doesn't send a sort missing
	 * its id and wipe out the ORDER BY.
	 *
	 * Returns the touched widgets' updateComplete promises rather than guarding itself -
	 * the caller folds them into its own, wider sync window (see _setFilterTemplate()).
	 */
	private _syncSortWidgets(root : HTMLElement) : Promise<any>[]
	{
		const sort = this._nextmatch?.activeFilters?.["sort"];
		if(!sort || typeof (<any>root).iterateOver !== "function")
		{
			return [];
		}
		const mgr = new et2_arrayMgr({sort});
		const pending : Promise<any>[] = [];
		(<any>root).iterateOver((child) =>
		{
			if(typeof child.set_value != "undefined" && (child.id === "sort[id]" || child.id === "sort[asc]"))
			{
				const value = mgr.getEntry(child.id);
				child.set_value(value == null ? '' : value);
				if(child.updateComplete)
				{
					pending.push(child.updateComplete);
				}
			}
		}, null, et2_IInput);
		return pending;
	}

	render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		const hasClearButton = this.clearable && !this.disabled && Object.keys(this.value || {}).length > 0;

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        filterbox: true,
                    })}
            >
                ${this._labelTemplate()}
                <slot name="prefix" part="prefix" class="filterbox__prefix"></slot>
                <div part="filters" class="filterbox__filters"
                     @change=${this.handleFilterChange}
                >
                    <slot></slot>
                </div>
                <slot name="suffix" part="suffix" class="filterbox__suffix"></slot>
                ${this._helpTextTemplate()}
                <div slot="footer" part="buttons" class="filterbox__buttons">
                    ${this.autoapply ? nothing : html`
                        <et2-button variant="primary" label="Apply" nosubmit
                                    ?disabled=${this.disabled}
                                    @click=${this.applyFilters}
                        ></et2-button>
                    `}
                    <slot name="footer"></slot>
                    ${hasClearButton ? html`
                        <et2-button label="Clear" nosubmit @click=${this.clearFilters}></et2-button>
                    ` : nothing}
                </div>
            </div>
		`;
	}
}

export type Filter = {
	name? : string,
	label? : string,
	type? : string,
	value? : string,
	group? : string,
	order? : number,

	widget? : typeof Et2InputWidget
};
