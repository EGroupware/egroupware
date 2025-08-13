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
import {LitElement, nothing, TemplateResult} from "lit";
import {html} from "lit/static-html.js";
import {et2_INextmatchHeader, et2_nextmatch, et2_nextmatch_customfields} from "../et2_extension_nextmatch";
import {Et2Favorites} from "../Et2Favorites/Et2Favorites";
import {classMap} from "lit/directives/class-map.js";
import {HasSlotController} from "../Et2Widget/slot";
import {unsafeStatic} from "@open-wc/testing";
import shoelace from "../Styles/shoelace";

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

	/* Custom filter rendering function.  The first argument is the Filter, the second is the index.  The function should
	 * return a Lit TemplateResult
	 */
	@property() getFilter : (filter : Filter, index : number) => TemplateResult | HTMLElement = (filter, index) =>
	{
		if(filter.widget)
		{
			filter.widget.classList.add("et2-label-fixed");
			filter.widget.label = filter.label;
			return <HTMLElement><unknown>filter.widget;
		}
		const tag = unsafeStatic(filter.type || "et2-select");
		return html`todo filter ${index}:
        <${tag} label=${filter.label} value=${filter.value}></${tag}>`;
	};

	protected hasSlotController = new HasSlotController(this, "label", "help-text", "prefix", "suffix");
	protected _nextmatch : et2_nextmatch = null;
	protected _groups : {
		[nextmatch_id : string] : { [name : string] : { filters : Filter[], order : number, dataId? : string } }
	} = {};

	constructor()
	{
		super();
		this.applyFilters = this.applyFilters.bind(this);
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
			if(this.nextmatch !== this._nextmatch)
			{
				this._findNextmatch();
			}
		}
		if(changedProperties.has("filters"))
		{
			this._sortFilters();
		}
	}

	public applyFilters()
	{
		const changeEvent = new CustomEvent("change", {detail: this.value, bubbles: true, composed: true});
		this.dispatchEvent(changeEvent);
		if(this._nextmatch && !changeEvent.defaultPrevented)
		{
			this._nextmatch.applyFilters(this.value);
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

	public get value()
	{
		const value = {};
		// Custom content is reflected inside, not an actual child so we need to trace the slot
		// and get the values from the et2 instance
		if(this.hasSlotController.test('[default]'))
		{
			Array.from(this.querySelectorAll('slot')).forEach((child) =>
			{
				child.assignedElements().forEach((element) =>
				{
					if(element.getInstanceManager && element.getInstanceManager())
					{
						Object.assign(value, element.getInstanceManager().getValues(element));
					}
				})
			})
		}
		let entry = value;
		const nm_group = this._nextmatch ? this._groups[this._nextmatch.id] : {}
		Object.values(nm_group ?? {}).forEach((group) =>
		{
			if(group.dataId && typeof value[group.dataId] == "undefined")
			{
				value[group.dataId] = {};
			}
			entry = group.dataId ? value[group.dataId] : value;
			group.filters?.forEach((filter) =>
			{
				if(filter.widget.getValue() != null)
				{
					entry[filter.widget.id || filter.name] = filter.widget.getValue();
				}
			});
		})
		return value;
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
		if(this._nextmatch && !this.hasSlotController.test("[default]"))
		{
			// Don't bind now, nextmatch probably isn't loaded yet
			// @ts-ignore template_promise is private, but et2_nextmatch doesn't have updateComplete()
			(this._nextmatch.template_promise ?? Promise.resolve()).then(() => this.readNextmatchFilters(this._nextmatch));
		}
	}

	public async readNextmatchFilters(nextmatch : et2_nextmatch = this._nextmatch)
	{
		// Only read filters once
		if(typeof this._groups[nextmatch.id] != "undefined")
		{
			return;
		}

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

		this._groups[nextmatch.id] = {};
		const group = this._groups[nextmatch.id];

		// Start with the nm header
		this._groups[nextmatch.id][''] = {filters: [], order: 0};
		// @ts-ignore header is private
		nextmatch.header.header_div[0]
			.querySelectorAll(".et2-input-widget")
			.forEach((widget : HTMLElement) =>
			{
				const filter = this._adoptNextmatchWidget(widget);
				if(!filter)
				{
					return;
				}
				if(filter.widget.dataset.groupOrder)
				{
					this._groups[nextmatch.id][''].order = parseInt(filter.dataset.groupOrder);
				}
				this._groups[nextmatch.id][''].filters.push(filter);
			});

		// Now for column headers
		const dataId = 'col_filter';
		const filters = Array.from(nextmatch.getDOMNode().querySelectorAll("et2-nextmatch-header-filter, et2-nextmatch-header-account, et2-nextmatch-header-entry"));
		filters.forEach((widget : HTMLElement) =>
		{
			// Customfields get their own group
			const groupName = (widget.dataset.groupName ? widget.egw().lang(widget.dataset.groupName) : null) ?? (
				widget.getParent().instanceOf(et2_nextmatch_customfields) ?
				widget.egw().lang('Custom fields') : widget.egw().lang('Column Filters')
			);
			if(typeof group[groupName] == "undefined")
			{
				group[groupName] = {
					filters: [],
					order: parseInt(widget.dataset.groupOrder) || 0,
					dataId: dataId
				};
			}
			const filter = this._adoptNextmatchWidget(widget);
			if(filter)
			{
				group[groupName].filters.push(filter);
			}
		});

		this._sortFilters();
		this._nextmatch.getDOMNode().classList.add("et2-filterbox--loaded", "et2-filterbox--" + this.originalWidgets);

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
		this.requestUpdate();
	}

	private _adoptNextmatchWidget(widget) : Filter
	{
		const dealWithOriginal = (widget, filter) =>
		{
			switch(this.originalWidgets)
			{
				case "none":
					break;
				case "replace":
					if(widget.implements(et2_INextmatchHeader))
					{
						const replacement = document.createElement("div");
						replacement.innerHTML = filter.label;
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

		const et2Widget = widget as unknown as typeof Et2InputWidget;
		const clone = et2Widget.clone();
		et2Widget.addEventListener("change", (event) => { clone.value = et2Widget.value});
		const filter = {
			// @ts-ignore
			label: et2Widget.label || et2Widget.ariaLabel || et2Widget.placeholder || et2Widget.emptyLabel,
			// @ts-ignore
			widget: clone,
			order: widget.dataset.order
		};
		this.filters.push(filter);
		dealWithOriginal(widget, filter);
		return filter;
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

	}

	protected _groupTemplate(group : string, filters : Filter[]) : TemplateResult | symbol
	{
		if(filters.length == 0)
		{
			return nothing;
		}
		if(!group)
		{
			return html`${filters.map((filter, index) => this.getFilter(filter, index))}`;
		}
		return html`
            <et2-details summary=${group} open>
                ${filters.map((filter, index) => this.getFilter(filter, index))}
            </et2-details>
		`;
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
                    ${this.hasSlotController.test('[default]') ? html`
                        <slot></slot>` : html`
                        ${Object.keys(this._groups[this._nextmatch.id] ?? {}).map(groupName => this._groupTemplate(groupName, this._groups[this._nextmatch.id][groupName].filters))}
                    `}
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