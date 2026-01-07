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
import {Et2Widget} from "../Et2Widget/Et2Widget";

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

	/* If nextmatch ID is provided, the filters will be read from the nextmatch header */
	@property({type: String})
	nextmatch : string | et2_nextmatch = null;

	/* When copying from a nextmatch, we can leave, delete or replace column headers with text in place of the original widgets */
	@property({type: String})
	originalWidgets : "none" | "delete" | "hide" | "replace" = "none";

	protected hasSlotController = new HasSlotController(this, "label", "help-text", "prefix", "suffix");
	protected _nextmatch : et2_nextmatch = null;
	protected _groups : {
		[nextmatch_id : string] : { [name : string] : { filters : Filter[], order : number, dataId? : string } }
	} = {};

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
				}
			}, newValue, et2_IInput);
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
			// Don't bind now, nextmatch probably isn't loaded yet
			// @ts-ignore template_promise is private, but et2_nextmatch doesn't have updateComplete()
			(this._nextmatch.template_promise ?? Promise.resolve()).then(() => this.readNextmatchFilters());

			this._nextmatch.getDOMNode().addEventListener("et2-filter", this.handleNextmatchFilter);
			this._nextmatch.getDOMNode().classList.add("et2-filterbox--loaded");
		}
	}

	public async readNextmatchFilters()
	{
		// Wait for nextmatch widgets to finish or we'll miss settings
		let waitForWebComponents = [];
		this._nextmatch.getChildren().forEach((w) =>
		{
			// @ts-ignore
			if(typeof w.updateComplete !== "undefined")
			{
				// @ts-ignore
				waitForWebComponents.push(w.updateComplete)
			}
		});
		await Promise.all(waitForWebComponents);

		// @ts-ignore header is private
		this._nextmatch.header.header_div[0]
			.querySelectorAll(".et2-input-widget")
			.forEach((widget : HTMLElement) =>
			{
				this._adoptNextmatchWidget(widget);
			});

		// Now for column headers
		const filters = Array.from(this._nextmatch.getDOMNode().querySelectorAll("et2-nextmatch-header-filter, et2-nextmatch-header-account, et2-nextmatch-header-entry, et2-nextmatch-header-custom"));
		filters.forEach((widget : HTMLElement) =>
		{
			this._adoptNextmatchWidget(widget);
		});

		this._nextmatch.getDOMNode().classList.add("et2-filterbox--" + this.originalWidgets);

		// If the nextmatch has sub-headers and we didn't grab everything from them, mark the NM so we don't hide them
		const subHeaders = ["header_left", "header_right", "header_row", "header2"];
		subHeaders.forEach(subHeader =>
		{
			if(this._nextmatch.options[subHeader])
			{
				const subTemplate = this._nextmatch.getWidgetById(this._nextmatch.options[subHeader]);
				if(subTemplate && subTemplate.childElementCount > 0)
				{
					this._nextmatch.getDOMNode().classList.add("et2-filterbox--has-header");
				}
			}
		});
		if(this._nextmatch.options.settings.lettersearch)
		{
			this._nextmatch.getDOMNode().classList.add("et2-filterbox--has-lettersearch");
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
		this.value = event.detail.activeFilters;
	}

	private handleSlotChange(event)
	{
		// What changed?
		debugger;
	}
	render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		const hasClearButton = this.clearable && !this.disabled && this.value.length > 0;

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