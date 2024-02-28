import {LitElement, nothing, TemplateResult} from "lit";
import {html, literal, StaticValue} from "lit/static-html.js";
import {Et2Tree, TreeItemData, TreeSearchResult} from "./Et2Tree";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {state} from "lit/decorators/state.js";
import {HasSlotController} from "../Et2Widget/slot";
import {map} from "lit/directives/map.js";
import {SlPopup, SlRemoveEvent} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import styles from "./Et2TreeDropdown.styles";
import {Et2Tag} from "../Et2Select/Tag/Et2Tag";
import {SearchMixin, SearchResult, SearchResultsInterface} from "../Et2Widget/SearchMixin";
import {Et2InputWidgetInterface} from "../Et2InputWidget/Et2InputWidget";


interface TreeSearchResults extends SearchResultsInterface<TreeSearchResult>
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
 */

export class Et2TreeDropdown extends SearchMixin<Constructor<any> & Et2InputWidgetInterface & typeof LitElement, TreeSearchResult, TreeSearchResults>(Et2WidgetWithSelectMixin(LitElement))
{

	static get styles()
	{
		return [
			shoelace,
			super.styles,
			styles
		];
	}

	/** Placeholder text to show as a hint when the select is empty. */
	@property() placeholder = "";

	@property({type: Boolean, reflect: true}) multiple = false;

	/** The component's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = "";

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

	@state() currentTag : Et2Tag;

	// We show search results in the same dropdown
	@state() treeOrSearch : "tree" | "search" = "tree";

	private get _popup() : SlPopup { return this.shadowRoot.querySelector("sl-popup")}
	private get _tree() : Et2Tree { return this.shadowRoot.querySelector("et2-tree")}

	private get _tags() : Et2Tag[] { return Array.from(this.shadowRoot.querySelectorAll("et2-tag"));}

	protected readonly hasSlotController = new HasSlotController(<LitElement><unknown>this, "help-text", "label");
	private __value : string[];

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

	disconnectedCallback()
	{
		super.disconnectedCallback();
		document.removeEventListener("click", this.handleDocumentClick);
	}

	updated()
	{
		// @ts-ignore Popup sometimes loses the anchor which breaks the sizing
		this._popup.handleAnchorChange();
	}
	/** Selected tree leaves */
	@property()
	set value(new_value : string | string[])
	{
		if(typeof new_value === "string")
		{
			new_value = new_value.split(",")
		}
		const oldValue = this.__value;
		// Filter to make sure there are no trailing commas or duplicates
		this.__value = Array.from(new Set(<string[]>new_value.filter(v => v)));
		this.requestUpdate("value", oldValue);
	}

	get value() : string | string[]
	{
		return this.multiple ? this.__value : (
			this.__value?.length ? this.__value[0] : ""
		);
	}

	/** Sets focus on the control. */
	focus(options? : FocusOptions)
	{
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");

		if(this._searchNode)
		{
			this._searchNode.focus(options);
		}
	}

	/** Removes focus from the control. */
	blur()
	{
		this.open = false;
		this.treeOrSearch = "tree";
		this.hasFocus = false;
		this._popup.active = false;
		// Should not be needed, but not firing the update
		this.requestUpdate("open");
		this.requestUpdate("hasFocus");
		this._searchNode.blur();

		clearTimeout(this._searchTimeout);
	}


	/** Shows the tree. */
	async show()
	{
		if(this.open || this.disabled)
		{
			this.open = false;
			this.requestUpdate("open", true);
			return undefined;
		}

		this.open = true;
		this.requestUpdate("open", false)
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
		this.show();

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

	protected searchResultSelected()
	{
		super.searchResultSelected();

		if(this.multiple && typeof this.value !== "undefined")
		{
			// Add in the new result(s)
			(<string[]>this.value).splice(this.value.length, 0, ...this.selectedResults.map(el => el.value));
		}
		else if(typeof this.value !== "undefined")
		{
			// Just replace our value with whatever they chose
			this.value = this.selectedResults[0]?.value ?? "";
		}

		// Done with search, show the tree
		this.treeOrSearch = "tree";
		// Close the dropdown
		this.hide();
		this.requestUpdate("value");
	}

	/**
	 * Keyboard events that the search input did not grab
	 * (tags, otion navigation)
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
		else
		{
			this.blur();
		}

	}

	private handleSearchFocus()
	{
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");
		this.hide();

		// Reset tags to not take focus
		this.setCurrentTag(null);

		this._searchNode.setSelectionRange(this._searchNode.value.length, this._searchNode.value.length);
	}

	handleSearchKeyDown(event)
	{
		super.handleSearchKeyDown(event);

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
		Array.from(this._tree._tree.querySelectorAll('sl-tree-item')).forEach(e =>
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
			this.value = event?.detail?.selection?.map(i => i.id) ?? []
		}
		else
		{
			const id = event?.detail?.selection?.map(i => i.id).pop();
			if(id && !this.value.includes(id))
			{
				// Copy so LitElement knows it changed
				this.value = [...this.value, id];
			}
		}

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new Event("change", {bubbles: true}));
		});
		if(!this.multiple)
		{
			this.hide();
		}
	}

	handleTriggerClick()
	{
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
			this._tree.focus();
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
		return html`
            <input id="search" type="text" part="input"
                   class="tree-dropdown__search search__input"
                   autocomplete="off"
                   ?disabled=${this.disabled}
                   ?readonly=${this.readonly}
                   placeholder="${this.hasFocus || this.value.length > 0 || this.disabled || this.readonly ? "" : this.placeholder}"
                   tabindex="0"
                   @keydown=${this.handleSearchKeyDown}
                   @blur=${() => {this.hasFocus = false;}}
                   @focus=${this.handleSearchFocus}
                   @paste=${this.handlePaste}
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

	tagsTemplate()
	{
		const value = this.getValueAsArray();
		return html`${map(value, (value, index) =>
		{
			// Deal with value that is not in options
			const option = this.optionSearch(value, this.select_options, 'value', 'children');
			return option ? this.tagTemplate(option) : nothing;
		})}`;
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
                    .value=${option.id}
                    @sl-remove=${(e : SlRemoveEvent) => this.handleTagRemove(e, option.id)}
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
		const isPlaceholderVisible = this.placeholder && this.value.length === 0 && !this.disabled && !this.readonly;

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
                                'tree-dropdown--placeholder-visible': isPlaceholderVisible,
                                'tree-dropdown--searching': this.treeOrSearch == "search",
                                'tree-dropdown--has-value': hasValue
                            })}
                            flip
                            shift
                            sync="width"
                            auto-size="vertical"
                            auto-size-padding="10"
                            ?active=${this.open}
                            placement=${this.placement || "bottom"}
                            stay-open-on-select
                            ?disabled=${this.disabled}
                    >
                        <div
                                part="combobox"
                                class="tree-dropdown__combobox"
                                slot="anchor"
                                @keydown=${this.handleComboboxKeyDown}
                        >
                            <slot part="prefix" name="prefix" class="tree-dropdown__prefix"></slot>
                            <div part="tags" class="tree-dropdown__tags">
                                ${this.tagsTemplate()}
                                ${this.inputTemplate()}
                            </div>
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
                                exportparts=""
                                ?readonly=${this.readonly}
                                ?disabled=${this.disabled}
                                value=${this.multiple ? nothing : this.value}
                                ._selectOptions=${this.select_options}
                                .actions=${this.actions}
                                .styleTemplate=${() => this.styleTemplate()}

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