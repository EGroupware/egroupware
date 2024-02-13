import {html, LitElement, nothing} from "lit";
import {Et2Tree, TreeItemData} from "./Et2Tree";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {HasSlotController} from "../Et2Widget/slot";
import {keyed} from "lit/directives/keyed.js";
import {map} from "lit/directives/map.js";
import {SlDropdown, SlRemoveEvent} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import styles from "./Et2TreeDropdown.styles";
import {literal, StaticValue} from "lit/static-html.js";

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


	private get _popup() : SlDropdown { return this.shadowRoot.querySelector("sl-popup")}

	private get _tree() : Et2Tree { return this.shadowRoot.querySelector("et2-tree")}

	protected readonly hasSlotController = new HasSlotController(this, "help-text", "label");
	private __value : string[];

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
		this.__value = <string[]>new_value;
		this.requestUpdate("value", oldValue);
	}

	get value() : string | string[]
	{
		return this.multiple ? this.__value : (
			this.__value?.length ? this.__value[0] : ""
		);
	}

	handleTagRemove(event : SlRemoveEvent, value : string)
	{
		// Find the tag value and remove it from current value
		const index = this.value.indexOf(value);
		this.value.splice(index, 1);
		this.requestUpdate("value");
		this.dispatchEvent(new Event("change", {bubbles: true}));
	}

	handleTreeChange(event)
	{
		const oldValue = this.value;
		this.value = this._tree.value;
		this.requestUpdate("value", oldValue);
	}

	handleTriggerClick()
	{
		if(this.open)
		{
			this._popup.active = false;
		}
		else
		{
			this._popup.active = true;
		}
		this.open = this._popup.active;
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
                   @blur=${this.handleSearchBlur}
                   @focus=${this.handleSearchFocus}
                   @paste=${this.handlePaste}
            />
		`;
	}

	tagsTemplate()
	{
		const value = this.getValueAsArray();
		return html`${keyed(this._valueUID, map(value, (value, index) => this.tagTemplate(this.optionSearch(value, this.select_options))))}`;
	}

	tagTemplate(option : TreeItemData)
	{
		const readonly = (this.readonly || option && typeof (option.disabled) != "undefined" && option.disabled);
		const isEditable = false && !readonly;
		const image = this.iconTemplate(option.option ?? option);
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
                    ?pill=${this.pill}
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
                            strategy="fixed"
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
                            ${this.tagsTemplate()}
                            ${this.inputTemplate()}
                            ${this.searching ? html`
                                <sl-spinner class="tree-dropdown"></sl-spinner>` : nothing
                            }
                            <slot part="suffix" name="suffix" class="tree-dropdown__suffix"></slot>
                            <et2-button caret class="tree-dropdown__trigger"
                                        @click=${this.handleTriggerClick}
                                        .noSubmit=${true}
                            >
                            </et2-button>
                        </div>
                        <et2-tree
                                class="tree-dropdown__tree"
                                multiple=${this.multiple}
                                ?readonly=${this.readonly}
                                ?disabled=${this.disabled}
                                .value=${this.value}
                                ._selectOptions=${this.select_options}

                                @change=${this.handleTreeChange}
                        >
                        </et2-tree>
                    </sl-popup>
                </div>
		`
	}
}

customElements.define("et2-tree-dropdown", Et2TreeDropdown);