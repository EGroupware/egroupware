import {LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {html, literal, StaticValue} from "lit/static-html.js";
import {Et2Tree, TreeItemData} from "./Et2Tree";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {state} from "lit/decorators/state.js";
import {HasSlotController} from "../Et2Widget/slot";
import {map} from "lit/directives/map.js";
import {SlPopup, SlRemoveEvent, SlTreeItem} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import styles from "./Et2TreeDropdown.styles";
import {Et2Tag} from "../Et2Select/Tag/Et2Tag";
import {SearchMixin, SearchResult, SearchResultElement, SearchResultsInterface} from "../Et2Widget/SearchMixin";
import {Et2InputWidgetInterface} from "../Et2InputWidget/Et2InputWidget";
import {Required} from "../Validators/Required";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {EgwMenuShoelace} from "../../egw_action/EgwMenuShoelace";


interface TreeSearchResults extends SearchResultsInterface<TreeItemData>
{
}

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * @summary A tree that is hidden in a dropdown
 *
 * @dependency sl-dropdown
 * @dependency et2-tree
 * @dependency et2-tag
 *
 * @slot label - The input's label. Alternatively, you can use the `label` attribute.
 * @slot prefix - Used to prepend a presentational icon or similar element to the combobox.
 * @slot suffix - Used to append a presentational icon or similar element to the input.
 * @slot expand-icon - The icon to show when the control is expanded and collapsed. Rotates on open and close.
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event change - Emitted when the control's value changes.
 * @event sl-show - Emitted when the suggestion menu opens.
 * @event sl-after-show - Emitted after the suggestion menu opens and all animations are complete.
 * @event sl-hide - Emitted when the suggestion menu closes.
 * @event sl-after-hide - Emitted after the suggestion menu closes and all animations are complete.
 *
 * @csspart form-control - The form control that wraps the label, input, and help text.
 * @since 23.1.x
 */

export class Et2TreeDropdown extends SearchMixin<Constructor<any> & Et2InputWidgetInterface & typeof LitElement, TreeItemData, TreeSearchResults>(Et2WidgetWithSelectMixin(LitElement))
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
	 * List of properties that get translated
	 * @returns object
	 */
	static get translate()
	{
		return {
			...super.translate,
			placeholder: true,
		}
	}

	/** Placeholder text to show as a hint when the select is empty. */
	@property() placeholder = "";

	@property({type: Boolean, reflect: true}) multiple = false;

	/** Adds a clear button when the dropdown is not empty. */
	@property({type: Boolean}) clearable = false;

	/** The component's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = "";

	/** "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, getSelectedNode() contains node-id" */
	@property({type: String})
	autoloading: string = "";

	/**
	 * Indicates whether the dropdown is open. You can toggle this attribute to show and hide the tree, or you can
	 * use the `show()` and `hide()` methods and this attribute will reflect the open state.
	 */
	@property({type: Boolean, reflect: true}) open = false;

	/**
	 * Actions (passed to the tree)
	 * @type {{}}
	 */
	@property({type: Object}) actions = {};

	/**
	 * If true, only leafs (NOT folders) are selectable
	 */
	@property()
	set leafOnly(_leafOnly: boolean)
	{
		this.updateComplete.then(() => {
			const tree = this._tree
				if (tree)
				{
					tree.leafOnly = _leafOnly;
					tree.requestUpdate("leafOnly")

				}
			}
		)
	}
	get leafOnly()
	{
		return this._tree?.leafOnly;
	}

	/**
	 * set the corresponding attribute if you want the tree to scroll to the selected item, when it is opened
	 * Please already supply the parents of the current selection in an open state from the server side if possible
	 */
	@property({type: Boolean}) openAtSelection = false

	@state() currentTag: Et2Tag;

	// We show search results in the same dropdown
	@state() treeOrSearch : "tree" | "search" = "tree";

	private get _popup(): SlPopup
	{
		return this.shadowRoot?.querySelector("sl-popup")
	}

	private get _tree(): Et2Tree
	{
		return this.shadowRoot?.querySelector("et2-tree")
	}

	private get _tags() : Et2Tag[] { return Array.from(this.shadowRoot.querySelectorAll("et2-tag"));}

	protected readonly hasSlotController = new HasSlotController(<LitElement><unknown>this, "help-text", "label");
	private __value : string[];

	protected displayLabel = '';

	constructor()
	{
		super();
		this.__value = [];

		this.handleDocumentClick = this.handleDocumentClick.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		document.addEventListener("click", this.handleDocumentClick);
	}

	firstUpdated()
	{
		let options = this.multiple || !this.emptyLabel ? this.select_options : [{
			value: "",
			label: this.emptyLabel
		}, ...this.select_options];

		this._tree._selectOptions = <TreeItemData[]>options;
		this._tree.requestUpdate("_selectOptions");
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		document.removeEventListener("click", this.handleDocumentClick);
	}

	willUpdate(changedProperties)
	{
		super.willUpdate(changedProperties);

		// Child tree not updating when our emptyLabel changes
		if(this._tree && (changedProperties.has("select_options") || changedProperties.has("emptyLabel")))
		{
			let options = this.multiple || !this.emptyLabel ? this.select_options : [{
				value: "",
				label: this.emptyLabel
			}, ...this.select_options];

			this._tree._selectOptions = <TreeItemData[]>options;
			this._tree.requestUpdate("_selectOptions");
		}
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		// required changed, add / remove validator
		if(changedProperties.has('required'))
		{
			// Remove all existing Required validators (avoids duplicates)
			this.validators = (this.validators || []).filter((validator) => !(validator instanceof Required))
			if(this.required)
			{
				this.validators.push(new Required());
			}
		}

		if(changedProperties.has("value"))
		{
			// Base off this.value, not this.getValue(), to ignore readonly
			this.classList.toggle("hasValue", !(this.value == null || this.value == ""));
		}

		// pass aria-attributes to our input node
		if(changedProperties.has('ariaLabel') || changedProperties.has('ariaDescription'))
		{
			this._setAriaAttributes();
		}

		// @ts-ignore Popup sometimes loses the anchor which breaks the sizing
		this._popup.handleAnchorChange();
	}

	/** Selected tree leaves */
	@property()
	set value(new_value : string | string[])
	{
		if(!new_value)new_value="";
		// @ts-ignore handling invalid number type gracefully
		if(typeof new_value === 'number')
		{
			new_value = ""+new_value;
		}
		if(typeof new_value === "string")
		{
			new_value = new_value.split(",")
		}
		const oldValue = this.__value;
		// Filter to make sure there are no trailing commas or duplicates
		this.__value = Array.from(new Set(<string[]>new_value.filter(v => v)));

		this.displayLabel = "";
		if(!this.multiple)
		{
			const option = this.optionSearch(this.__value[0], this.select_options, 'value', 'children');
			if(option)
			{
				this.displayLabel = option.label;
			}
		}

		this.requestUpdate("value", oldValue);
	}

	get value() : string | string[]
	{
		return this.multiple ? this.__value : (
			this.__value?.length ? this.__value[0] : ""
		);
	}


	get select_options() : SelectOption[]
	{
		return super.select_options;
	}

	set select_options(new_options : SelectOption[])
	{
		super.select_options = new_options;

		// Overridden so we can update displayLabel in the case where value got set before selectOptions
		if(this.value && !this.multiple)
		{
			const option = this.optionSearch(typeof this.value == "string" ? this.value : this.value[0], this.select_options, 'value', 'children');
			if(option)
			{
				this.displayLabel = option.label;
			}
		}

		if(this._tree)
		{
			this._tree._selectOptions = <TreeItemData[]>new_options;
			this._tree.requestUpdate("_selectOptions");
		}
	}

	/** Sets focus on the control. */
	focus(options? : FocusOptions)
	{
		this.handleFocus();
	}

	/** Removes focus from the control. */
	blur()
	{
		this.handleBlur();
	}


	/** Shows the tree. */
	async show()
	{
		if(this.readonly || this.disabled)
		{
			this.open = false;
			this.requestUpdate("open", true);
			return this.updateComplete;
		}
		this.open = true;
		this.requestUpdate("open", false)
		if (this.openAtSelection)
		{
			//TODO check what to wait on, waiting on updateComplete does not work
			setTimeout(() =>
				this._tree.scrollToSelected(),100)
		}
		return this.updateComplete
	}

	/** Hides the tree. */
	async hide()
	{
		if(!this.open || this.disabled)
		{
			return undefined;
		}

		this.open = false;
		this._popup.active = false;
		this._searchNode.value = "";
		this.requestUpdate("open");
		return this.updateComplete
	}

	private setCurrentTag(tag : Et2Tag)
	{
		this._tags.forEach(t =>
		{
			t.tabIndex = -1;
			if(t.current)
			{
				t.current = false;
				t.requestUpdate();
			}
		});
		this.currentTag = tag;
		if(tag)
		{
			this.currentTag.tabIndex = 0;
			this.currentTag.current = true;
			this.currentTag.requestUpdate();
			this.currentTag.focus();
		}
	}


	startSearch()
	{
		super.startSearch();

		// Show the dropdown, that's where the results will go
		this.open = true;

		// Hide the tree
		this.treeOrSearch = "search";
	}


	/**
	 * If you have a local list of options, you can search through them on the client and include them in the results.
	 *
	 * This is done independently from the server-side search, and the results are merged.
	 *
	 * @param {string} search
	 * @param {object} options
	 * @returns {Promise<any[]>}
	 * @protected
	 */
	protected localSearch<DataType extends SearchResult>(search : string, searchOptions : object, localOptions : DataType[] = []) : Promise<DataType[]>
	{
		return super.localSearch(search, searchOptions, this.select_options);
	}

	/**
	 * Toggles a search result's selected state
	 * Overridden to handle multiple attribute so only 1 result is selected
	 */
	protected toggleResultSelection(result : HTMLElement & SearchResultElement, force? : boolean)
	{
		if(!this.multiple)
		{
			this._resultNodes.forEach(t => t.selected = false);
		}

		super.toggleResultSelection(result, force);
	}

	protected searchResultSelected()
	{
		super.searchResultSelected();

		const oldValue = [...this.value];
		if(this.multiple && typeof this.value !== "undefined")
		{
			// Add in the new result(s), no duplicates
			this.value = [...new Set([...this.value, ...this.selectedResults.map(el => el.value)])];
		}
		else if(typeof this.value !== "undefined")
		{
			// Just replace our value with whatever they chose
			this.value = this.selectedResults[0]?.value ?? "";
		}

		// Done with search, show the tree
		this.treeOrSearch = "tree";

		// Close the dropdown, move on
		if(!this.multiple || this.egw().preference("select_multiple_close") == "close")
		{
			this.blur();
		}
		else
		{
			this._tree.value = <string[]>this.value;
		}

		// Update values
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		});
		this._tree.requestUpdate("value", oldValue);
		this.requestUpdate("value", oldValue);
	}

	private handleClearClick(event : MouseEvent)
	{
		event.stopPropagation();

		if(this.value.length > 0)
		{
			this.value = [];
			this.displayInput.focus({preventScroll: true});

			// Emit after update
			this.updateComplete.then(() =>
			{
				this.emit('sl-clear');
				this.emit('sl-input');
				this.emit('sl-change');
			});
		}
	}

	private handleClearMouseDown(event : MouseEvent)
	{
		// Don't lose focus or propagate events when clicking the clear button
		event.stopPropagation();
		event.preventDefault();
	}

	/**
	 * Keyboard events that the search input did not grab
	 * (tags, option navigation)
	 *
	 * @param {KeyboardEvent} event
	 */
	handleComboboxKeyDown(event : KeyboardEvent)
	{
		// Navigate between tags
		if(this.currentTag && (["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)))
		{
			let nextTagIndex = this._tags.indexOf(this.currentTag);
			const tagCount = this._tags.length
			switch(event.key)
			{
				case 'ArrowLeft':
					nextTagIndex--;
					break;
				case 'ArrowRight':
					nextTagIndex++;
					break;
				case 'Home':
					nextTagIndex = 0;
					break;
				case 'End':
					nextTagIndex = this._tags.length - 1;
					break;
			}
			nextTagIndex = Math.max(0, nextTagIndex);
			if(nextTagIndex < tagCount && this._tags[nextTagIndex])
			{
				this.setCurrentTag(this._tags[nextTagIndex]);
			}
			else
			{
				// Arrow back to search, or got lost
				this._searchNode.focus();
			}
			event.stopPropagation();
			return false;
		}
		// Remove tag
		if(event.target instanceof Et2Tag && ["Delete", "Backspace"].includes(event.key))
		{
			const tags = this._tags;
			let index = tags.indexOf(event.target);
			event.target.dispatchEvent(new CustomEvent('sl-remove', {bubbles: true}));
			index += event.key == "Delete" ? 1 : -1;
			if(index >= 0 && index < tags.length)
			{
				this.setCurrentTag(this._tags[index]);
			}
			else
			{
				this._searchNode.focus();
			}
			event.stopPropagation();
			return;
		}
		// Close popup if focus is on tree
		if(["Escape"].includes(event.key))
		{
			this.hide();
			event.stopPropagation();
			return;
		}
	}

	protected handleDocumentClick(event)
	{
		if(event.target == this || event.composedPath().includes(this))
		{
			return
		}
		if(this.open)
		{
			event.preventDefault();
			this.hide()
		}
		if(this.hasFocus)
		{
			this.blur();
		}
	}

	private handleFocus()
	{
		if(this.disabled || this.readonly)
		{
			return;
		}
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		this.updateComplete.then(() =>
		{
			if(this._searchNode)
			{
				this._searchNode.focus();
			}
			else
			{
				this._tree.focus();
			}

			this.dispatchEvent(new Event("sl-focus"));
		})
	}

	private handleBlur()
	{
		this.open = false;
		this.treeOrSearch = "tree";
		this.hasFocus = false;
		this.resultsOpen = false;
		this._popup.active = false;
		// Should not be needed, but not firing the update
		this.requestUpdate("resultsOpen");
		this.requestUpdate("open");
		this.requestUpdate("hasFocus");
		this._searchNode?.blur();

		clearTimeout(this._searchTimeout);

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("sl-blur"));
		})
	}

	protected handleClick(event)
	{
		// Open if clicking somewhere in the widget
		if(event.target.classList.contains("tree-dropdown__combobox"))
		{
			event.stopPropagation();
			this.show();
			this.handleFocus();
		}
	}

	private handleSearchFocus(event)
	{
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		// Reset tags to not take focus
		this.setCurrentTag(null);

		// Don't show if only tabbed into
		if(!event.relatedTarget)
		{
			this.show();
		}
	}

	private handleInternalBlur(event)
	{
		// Focus lost to some other internal component - ignore it
		let o = event.relatedTarget;
		while(o)
		{
			if(o == this.shadowRoot || o instanceof EgwMenuShoelace)
			{
				return;
			}
			o = o.parentNode;
		}
		this.handleBlur();
	}

	handleSearchKeyDown(event)
	{
		super.handleSearchKeyDown(event);

		// Hide popup if focus is on search
		if(["Escape"].includes(event.key))
		{
			this.hide();
			event.stopPropagation();
			return;
		}
		// Show options if popup is closed on arrow down or space
		if((event.key == "ArrowDown" || event.key == " " && this._searchNode.value == "") && !this.open && !this.resultsOpen)
		{
			this.show();
			event.stopPropagation();
			event.preventDefault();
		}
		// Move to tree if popup is open & tree is showing
		else if(event.key == "ArrowDown" && this.treeOrSearch == "tree")
		{
			this._tree.focus();
			event.stopPropagation();
		}

		// Left at beginning goes to tags
		if(this._searchNode.selectionStart == 0 && event.key == "ArrowLeft")
		{
			this.hide();
			this._tags.forEach(t => t.tabIndex = 0);
			if(this._tags.length > 0)
			{
				this.setCurrentTag(this._tags[this._tags.length - 1]);
			}
			event.stopPropagation();
			return;
		}

	}

	protected handleLabelClick()
	{
		this._searchNode.focus();
	}

	handleTagRemove(event : SlRemoveEvent, value : string)
	{
		// Find the tag value and remove it from current value
		let valueArray = this.getValueAsArray();
		const oldValue = valueArray.slice(0);
		const index = valueArray.indexOf(value);
		valueArray.splice(index, 1);
		this.value = valueArray;
		this.requestUpdate("value", oldValue);
		// TODO: Clean up this scope violation
		// sl-tree-item is not getting its selected attribute updated
		Array.from(this._tree._tree.querySelectorAll('sl-tree-item')).forEach((e : SlTreeItem) =>
		{
			if(this.value.includes(e.id))
			{
				e.setAttribute("selected", "");
			}
			else
			{
				e.removeAttribute("selected");
			}
		});
		this._tree.requestUpdate();
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		});
	}

	handleTreeChange(event)
	{
		const oldValue = this.value.slice(0);

		// For single value, we can just grab selected from the tree.  For multiple, we need to manage it.
		if(!this.multiple)
		{
			this.value = event?.detail?.selection?.map(i => i.id || i.value) ?? []
		}
		else
		{
			const id = event?.detail?.selection?.map(i => i.id || i.value).pop();
			if(id && !this.value.includes(id))
			{
				// Copy so LitElement knows it changed
				this.value = [...this.value, id];
			}
		}
		this.requestUpdate("value", oldValue);

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		});
		if(!this.multiple || this.egw().preference("select_multiple_close") == "close")
		{
			this.blur();
		}
	}

	handleTriggerClick(event)
	{
		if(this.disabled || this.readonly)
		{
			return;
		}

		event.stopPropagation();

		this.hasFocus = true;
		if(this.open)
		{
			this._popup.active = false;
			this._searchNode.value = "";
		}
		else
		{
			this._popup.active = true;
		}
		this.open = this._popup.active;
		this.treeOrSearch = "tree";
		this.requestUpdate("open");
		this.updateComplete.then(() =>
		{
			this._tree.style.minWidth = getComputedStyle(this).width;
			this.focus();
		})
	}

	/**
	 * Get the icon for the select option
	 *
	 * @param option
	 * @protected
	 */
	protected iconTemplate(option)
	{
		if(!option.icon && !option.im0)
		{
			return html``;
		}

		return html`
            <et2-image slot="prefix" part="icon" src="${option.icon ?? option.im0}"></et2-image>`
	}

	inputTemplate()
	{
		let placeholder = "";
		let image : symbol | TemplateResult = nothing;
		if(this.disabled || this.readonly || (!this.open && this.multiple && this.value.length > 0))
		{
			placeholder = "";
		}
		else if(this.hasFocus)
		{
			placeholder = this.egw().lang("Search");
		}
		else
		{
			placeholder = this.emptyLabel || this.placeholder;
		}
		if(!this.multiple && this.value && !this.open)
		{
			const option = this.optionSearch(this.value, this.select_options, 'value', 'children');
			image = option ? this.iconTemplate(option?.option ?? option) : null;
		}
		if(this.disabled || this.readonly)
		{
			return html`${image}${this.displayLabel || this.emptyLabel || placeholder}`;
		}
		return html`
            ${image}
            <input id="search" type="text" part="input"
                   class="tree-dropdown__search search__input"
                   autocomplete="off"
                   spellcheck="false"
                   autocapitalize="off"
                   aria-controls="listbox"
                   aria-expanded=${this.open ? 'true' : 'false'}
                   aria-haspopup="listbox"
                   aria-labelledby="label"
                   aria-disabled=${this.disabled ? 'true' : 'false'}
                   aria-describedby="help-text"
                   role="combobox"

                   ?disabled=${this.disabled}
                   ?readonly=${this.readonly}
                   placeholder="${placeholder}"
                   tabindex="0"
                   .value=${this.hasFocus ? "" : this.displayLabel}
                   @keydown=${this.handleSearchKeyDown}
                   @blur=${this.handleInternalBlur}
                   @click=${() =>
                   {
                       if(!this.open)
                       {
                           this.show();
                       }
                   }}
                   @focus=${this.handleSearchFocus}
                   @paste=${this.handlePaste}
            />
            <input
                    class="tree-dropdown__value-input"
                    type="text"
                    ?disabled=${this.disabled}
                    ?required=${this.required}
                    .value=${Array.isArray(this.value) ? this.value.join(', ') : this.value}
                    tabindex="-1"
                    aria-hidden="true"
                    @focus=${this.handleFocus}
                    @blur=${this.handleBlur}
            />
		`;
	}

	styleTemplate() : TemplateResult
	{
		return html``;
	}

	/**
	 * Tag used for rendering tags when multiple=true
	 * Used for creating, finding & filtering options.
	 * @see createTagNode()
	 * @returns {string}
	 */
	public get tagTag() : StaticValue
	{
		return literal`et2-tag`;
	}

	/**
	 * Shows the currently selected values as tags when multiple=true
	 *
	 * @returns {TemplateResult}
	 */
	tagsTemplate()
	{
		const value = this.getValueAsArray();
		return html`
            <div part="tags" class="tree-dropdown__tags">
                ${map(value, (value, index) =>
                {
                    // Deal with value that is not in options
                    const option = this.optionSearch(value, this.select_options, 'value', 'children');
                    return option ? this.tagTemplate(option) : nothing;
                })}
            </div>`;
	}

	tagTemplate(option : TreeItemData)
	{
		const readonly = (this.readonly || option && typeof (option.disabled) != "undefined" && option.disabled);
		const isEditable = false && !readonly;
		const image = option ? this.iconTemplate(option?.option ?? option) : null;
		const isValid = true;
		const tagName = this.tagTag;
		return html`
            <${tagName}
                    part="tag"
                    exportparts="
                      base:tag__base,
                      content:tag__content,
                      remove-button:tag__remove-button,
                      remove-button__base:tag__remove-button__base,
                      icon:icon
                    "
                    class=${"tree_tag " + option.class ?? ""}
                    tabindex="-1"
                    variant=${isValid ? nothing : "danger"}
                    size=${this.size || "medium"}
                    title=${option.title}
                    ?removable=${!readonly}
                    ?readonly=${readonly}
                    ?editable=${isEditable}
                    .value=${option.value || option.id}
                    @sl-remove=${(e : SlRemoveEvent) => this.handleTagRemove(e, option.value || option.id)}
                    @change=${this.handleTagEdit}
                    @dblclick=${this._handleDoubleClick}
                    @click=${typeof this.onTagClick == "function" ? (e) => this.onTagClick(e, e.target) : nothing}
            >
                ${image ?? nothing}
                ${(option.label ?? option.text).trim()}
            </${tagName}>
		`;
	}

	public render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
		const hasValue = this.value && this.value.length > 0;
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		const hasClearIcon = this.clearable && !this.disabled && this.value.length > 0;
		const isPlaceholderVisible = (this.placeholder || this.emptyLabel) && this.value.length === 0 && !this.disabled && !this.readonly;
		let options = this.multiple || !this.emptyLabel ? this.select_options : [{
			value: "",
			label: this.emptyLabel
		}, ...this.select_options];
		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': hasLabel,
                        'form-control--has-help-text': hasHelpText
                    })}
            >
                <label
                        id="label"
                        part="form-control-label"
                        class="form-control__label"
                        aria-hidden=${hasLabel ? 'false' : 'true'}
                >
                    <slot name="label">${this.label}</slot>
                </label>
                <div part="form-control-input" class="form-control-input">
                    <sl-popup
                            class=${classMap({
                                "tree-dropdown": true,
                                input: true,
                                'tree-dropdown--open': this.open,
                                'tree-dropdown--disabled': this.disabled,
                                'tree-dropdown--readonly': this.readonly,
                                'tree-dropdown--focused': this.hasFocus,
                                'tree-dropdown--multiple': this.multiple,
                                'tree-dropdown--placeholder-visible': isPlaceholderVisible,
                                'tree-dropdown--searching': this.treeOrSearch == "search",
                                'tree-dropdown--has-value': hasValue
                            })}
                            flip
                            shift
                            auto-size="both"
                            auto-size-padding="10"
                            ?active=${this.open}
                            placement=${this.placement || "bottom"}
							strategy="fixed"
                            ?disabled=${this.disabled}
                            ?readonly=${this.readonly}
                            @sl-after-hide=${() => {this.resultsOpen = false;}}
                    >
                        <div
                                part="combobox control"
                                class="tree-dropdown__combobox"
                                slot="anchor"
                                @keydown=${this.handleComboboxKeyDown}
                                @click=${this.handleClick}
                        >
                            <slot part="prefix" name="prefix" class="tree-dropdown__prefix"></slot>
                            ${this.multiple ? this.tagsTemplate() : nothing}
                            ${this.inputTemplate()}
                            ${hasClearIcon
                              ? html`
                                        <button
                                                part="clear-button"
                                                class="select__clear"
                                                type="button"
                                                aria-label=${this.localize.term('clearEntry')}
                                                @mousedown=${this.handleClearMouseDown}
                                                @click=${this.handleClearClick}
                                                tabindex="-1"
                                        >
                                            <slot name="clear-icon">
                                                <sl-icon name="x-circle-fill" library="system"></sl-icon>
                                            </slot>
                                        </button>
                                    `
                              : ''}
                            <slot part="suffix" name="suffix" class="tree-dropdown__suffix"></slot>
                            <slot name="expand-icon" part="expand-icon" class="tree-dropdown__expand-icon"
                                  @click=${this.handleTriggerClick}>
                                <sl-icon library="system" name="chevron-down" aria-hidden="true"></sl-icon>
                            </slot>
                        </div>
                        ${this.searchResultsTemplate()}
                        <et2-tree
                                .id=${this.id + "_tree"}
                                ._parent=${this}
                                class="tree-dropdown__tree"
                                exportparts="label"
                                ?readonly=${this.readonly}
                                ?disabled=${this.disabled}
                                value=${this.multiple ? nothing : this.value}
                                .actions=${this.actions}
                                .styleTemplate=${() => this.styleTemplate()}
								.autoloading="${this.autoloading}"
								?leafOnly = ${this.leafOnly}

                                @blur=${this.handleInternalBlur}
                                @et2-click=${(e) =>
                                {
                                    // Always hide the popup when a tree item is clicked
                                    //               this.hide();
                                }}
                                @keydown=${this.handleComboboxKeyDown}
                                @sl-selection-change=${this.handleTreeChange}
                        >
                        </et2-tree>
                    </sl-popup>
                </div>
                <div
                        part="form-control-help-text"
                        id="help-text"
                        class="form-control__help-text"
                        aria-hidden=${hasHelpText ? 'false' : 'true'}
                >
                    <slot name="help-text">${this.helpText}</slot>
                </div>
		`
	}
}

// @ts-ignore Type problems because of Et2WidgetWithSelectMixin
customElements.define("et2-tree-dropdown", Et2TreeDropdown);