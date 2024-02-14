import {html, LitElement, nothing} from "lit";
import {Et2Tree, TreeItemData} from "./Et2Tree";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {state} from "lit/decorators/state.js";
import {HasSlotController} from "../Et2Widget/slot";
import {keyed} from "lit/directives/keyed.js";
import {map} from "lit/directives/map.js";
import {SlPopup, SlRemoveEvent} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import styles from "./Et2TreeDropdown.styles";
import {Et2Tag} from "../Et2Select/Tag/Et2Tag";

/**
 * @summary A tree that is hidden in a dropdown
 *
 * @dependency sl-dropdown
 * @dependency et2-tree
 * @dependency et2-tag
 *
 * @slot label - The input's label. Alternatively, you can use the `label` attribute.
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

export class Et2TreeDropdown extends Et2WidgetWithSelectMixin(LitElement)
{

	static get styles()
	{
		return [
			shoelace,
			...super.styles,
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

	@state() searching = false;
	@state() hasFocus = false;
	@state() currentTag : Et2Tag;

	private get _popup() : SlPopup { return this.shadowRoot.querySelector("sl-popup")}
	private get _tree() : Et2Tree { return this.shadowRoot.querySelector("et2-tree")}

	private get _search() : HTMLInputElement { return this.shadowRoot.querySelector("#search")}

	private get _tags() : Et2Tag[] { return Array.from(this.shadowRoot.querySelectorAll("et2-tag"));}

	protected readonly hasSlotController = new HasSlotController(this, "help-text", "label");
	private __value : string[];

	protected _searchTimeout : number;
	protected _searchPromise : Promise<TreeItemData[]> = Promise.resolve([]);

	constructor()
	{
		super();
		this.multiple = false;
		this.__value = [];
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
		// Filter to make sure there are no trailing commas
		this.__value = <string[]>new_value.filter(v => v);
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

		if(this._search)
		{
			this._search.focus(options);
		}
	}

	/** Removes focus from the control. */
	blur()
	{
		this.open = false;
		this.hasFocus = false;
		this._popup.active = false;
		// Should not be needed, but not firing the update
		this.requestUpdate("open");
		this.requestUpdate("hasFocus");
		this._search.blur();

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
				this._search.focus();
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
				this._search.focus();
			}
		}
	}

	private handleSearchFocus()
	{
		this.hasFocus = true;
		// Should not be needed, but not firing the update
		this.requestUpdate("hasFocus");
		this.show();

		// Reset tags to not take focus
		this.setCurrentTag(null);

		this._search.setSelectionRange(this._search.value.length, this._search.value.length);
	}

	handleSearchKeyDown(event)
	{
		clearTimeout(this._searchTimeout);

		// Left at beginning goes to tags
		if(this._search.selectionStart == 0 && event.key == "ArrowLeft")
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
		// Tab on empty leaves
		if(this._search.value == "" && event.key == "Tab")
		{
			// Propagate, browser will do its thing
			return;
		}
		// Up / Down navigates options
		if(['ArrowDown', 'ArrowUp'].includes(event.key) && this._tree)
		{
			if(!this.open)
			{
				this.show();
			}
			event.stopPropagation();
			this._tree.focus();
			return;
		}

		// Start search immediately
		else if(event.key == "Enter")
		{
			event.preventDefault();
			this.startSearch();
			return;
		}
		else if(event.key == "Escape")
		{
			this.hide();
			event.stopPropagation();
			return;
		}

		// Start the search automatically if they have enough letters
		// -1 because we're in keyDown handler, and value is from _before_ this key was pressed
		if(this._search.value.length - 1 > 0)
		{
			this._searchTimeout = window.setTimeout(() => {this.startSearch()}, 500);
		}
	}

	protected handleLabelClick()
	{
		this._search.focus();
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
		const oldValue = this.value;
		this.value = event?.detail?.selection?.map(i => i.id) ?? [];
		this.requestUpdate("value", oldValue);

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
		}
		else
		{
			this._popup.active = true;
		}
		this.open = this._popup.active;
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
		if(!option.icon)
		{
			return html``;
		}

		return html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>`
	}

	inputTemplate()
	{
		return html`
            <input id="search" type="text" part="input"
                   class="tree-dropdown__search"
                   exportparts="base:search__base"
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

	tagsTemplate()
	{
		const value = this.getValueAsArray();
		return html`${keyed(this._valueUID, map(value, (value, index) =>
		{
			// Deal with value that is not in options
			const option = this.optionSearch(value, this.select_options);
			return option ? this.tagTemplate(option) : nothing;
		}))}`;
	}

	tagTemplate(option : TreeItemData)
	{
		const readonly = (this.readonly || option && typeof (option.disabled) != "undefined" && option.disabled);
		const isEditable = false && !readonly;
		const image = option ? this.iconTemplate(option?.option ?? option) : null;
		const isValid = true;

		return html`
            <et2-tag
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
                ${option.text.trim()}
            </et2-tag>
		`;
	}

	public render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
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
                            ${this.searching ? html`
                                <sl-spinner class="tree-dropdown"></sl-spinner>` : nothing
                            }
                            <slot part="suffix" name="suffix" class="tree-dropdown__suffix"></slot>
                            <slot name="expand-icon" part="expand-icon" class="tree-dropdown__expand-icon"
                                  @click=${this.handleTriggerClick}>
                                <sl-icon library="system" name="chevron-down" aria-hidden="true"></sl-icon>
                            </slot>
                        </div>
                        <et2-tree
                                class="tree-dropdown__tree"
                                multiple=${this.multiple}
                                ?readonly=${this.readonly}
                                ?disabled=${this.disabled}
                                value=${this.value}
                                ._selectOptions=${this.select_options}

                                @sl-selection-change=${this.handleTreeChange}
                        >
                        </et2-tree>
                    </sl-popup>
                </div>
		`
	}
}

customElements.define("et2-tree-dropdown", Et2TreeDropdown);

customElements.define("et2-tree-cat", class extends Et2TreeDropdown
{
});