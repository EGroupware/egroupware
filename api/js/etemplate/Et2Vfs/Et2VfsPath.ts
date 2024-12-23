/**
 * EGroupware eTemplate2 - Vfs path WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import shoelace from "../Styles/shoelace";
import styles from "./Et2VfsPath.styles";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import {FileInfo} from "./Et2VfsSelectDialog";
import {SlBreadcrumbItem} from "@shoelace-style/shoelace";
import {HasSlotController} from "../Et2Widget/slot";
import {until} from "lit/directives/until.js";

/**
 * @summary Display an editable path from the VFS
 * @since
 *
 * @slot label - The input’s label. Alternatively, you can use the label attribute.
 * @slot prefix - Before the path
 * @slot suffix - Like prefix, but after
 * @slot edit-icon - The icon that switches to editing the path as text.
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event change - Emitted when the control's value changes.
 * @event {CustomEvent} click - Emitted when the user clicks on part of the path.  `event.detail` contains the path.
 *
 * @csspart form-control-input - The textbox's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart suffix - The container that wraps the suffix slot.
 *
 */
export class Et2VfsPath extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			styles
		];
	}

	/** The component's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = '';

	/* User is directly editing the path as a string */
	@state() editing = false;

	protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'label');
	private _value = ""


	get _edit() : HTMLInputElement { return this.shadowRoot.querySelector("input");}

	constructor()
	{
		super();

		this.handleEditMouseDown = this.handleEditMouseDown.bind(this);
		this.handleKeyDown = this.handleKeyDown.bind(this);
		this.handlePathClick = this.handlePathClick.bind(this);
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		this.checkPathOverflow();
	}

	@property()
	set value(_value : string)
	{
		try
		{
			_value = this.egw().decodePath(<string>_value);
		}
		catch(e)
		{
			this.set_validation_error('Error! ' + _value);
			return;
		}
		const oldValue = this._value;
		this._value = <string>_value;
		this.requestUpdate("value", oldValue);
	}

	get value() { return this._value;}

	setValue(_value : string | FileInfo)
	{
		if(typeof _value != "string" && _value.path)
		{
			_value = _value.path;
		}
		this.value = <string>_value;
	}

	getValue()
	{
		return (this.readonly || this.disabled) ? null : (this.egw().encodePath(this._value || ''));
	}

	public focus()
	{
		this.edit();
	}

	public blur()
	{
		const oldEditing = this.editing;
		this.editing = false;
		this.requestUpdate("editing", oldEditing);
		if(!this._edit)
		{
			return;
		}

		let oldValue = this.value;
		this.value = this._edit.value;

		if(oldValue != this.value)
		{
			this.updateComplete.then(() =>
			{
				this.dispatchEvent(new Event("change"));
			})
		}
	}

	public edit()
	{
		const oldValue = this.editing;
		this.editing = true;

		this.requestUpdate("editing", oldValue);
		this.updateComplete.then(() =>
		{
			this._edit?.focus();
		})
	}

	protected checkPathOverflow()
	{
		const wrapper = this.shadowRoot.querySelector(".vfs-path__scroll");
		const path = wrapper?.querySelector("sl-breadcrumb");
		const scroll = path?.shadowRoot?.querySelector("nav");
		if(!wrapper || !scroll)
		{
			return;
		}
		wrapper.parentElement.classList.remove("vfs-path__overflow");
		path.updateComplete.then(() =>
		{
			if(wrapper.clientWidth < scroll.scrollWidth)
			{
				// Too small
				wrapper.parentElement.classList.add("vfs-path__overflow");
				wrapper.scrollLeft = scroll.scrollWidth - wrapper.clientWidth;
			}
		});
	}

	protected handleLabelClick()
	{
		this.edit();
	}

	protected handleEditMouseDown(event : MouseEvent)
	{
		event.preventDefault();
		event.stopPropagation();
		this.edit();
	}

	protected handleKeyDown(event : KeyboardEvent)
	{
		switch(event.key)
		{
			case "Enter":
				const oldValue = this.value;
				this.value = this._edit.value;
				this.requestUpdate("value", oldValue);
				if(oldValue != this.value)
				{
					this.updateComplete.then(() =>
					{
						this.dispatchEvent(new Event("change"));
					});
				}
			// Fall through
			case "Escape":
				event.stopPropagation();
				event.preventDefault();
				this._edit.value = this.value;
				this.blur();
				break;
		}
	}

	protected handlePathClick(event : MouseEvent)
	{
		let target = event.target;
		if(target.slot == "separator")
		{
			target = target.parentNode;
		}
		else if(target instanceof Image && target.parentElement.slot == "prefix")
		{
			// Icon
			target = target.parentElement.parentElement;
		}
		if(target instanceof SlBreadcrumbItem && event.composedPath().includes(this))
		{
			event.preventDefault();
			event.stopPropagation();

			const dirs = Array.from(target.parentElement.querySelectorAll('sl-breadcrumb-item')) ?? [];
			let stopIndex = dirs.indexOf(target) + 1;
			let newPath = dirs.slice(0, stopIndex)
				// Strip out any extra space
				.map(d => (d.dataset.value ?? "").trim().replace(/\/*$/, '').trim() + "/")
				.filter(p => p);
			if(newPath[0] !== '/')
			{
				// Make sure we start at /, breadcrumb parsing above might lose it
				newPath.unshift('/');
			}
			if(!(this.disabled || this.readonly))
			{
				const oldValue = this.value;
				this.value = newPath.join("")

				// No trailing slash in the value
				if(this.value !== "/" && this.value.endsWith("/"))
				{
					this.value = this.value.replace(/\/*$/, '');
				}
				if(oldValue != this.value)
				{
					this.updateComplete.then(() =>
					{
						this.dispatchEvent(new Event("change"));
					})
				}
			}
			// Can still click on it when disabled I guess
			if(!this.disabled)
			{
				this.dispatchEvent(new CustomEvent("click", {
					bubbles: true,
					cancelable: true,
					detail: newPath.join("")
				}));
			}
		}
	}

	protected handleScroll(event : WheelEvent)
	{
		this.shadowRoot.querySelector(".vfs-path__scroll").scrollLeft += event.deltaY;
	}

	protected _getIcon(pathParts)
	{
		let image = this.egw().image("navbar", "filemanager");
		if(pathParts.length > 2 && (pathParts[1] == "apps" || pathParts[1] == "templates"))
		{
			const app = this.egw().app(pathParts[2], 'name') || this.egw().appByTitle(pathParts[2], 'name');
			if (app && !(image = this.egw().image('navbar', app)))
			{
				image = this.egw().image('navbar', 'api');
			}
		}

		return image;
	}

	protected pathPartTemplate(pathParts, path, index)
	{
		let pathName : string | TemplateResult<1> = path.trim();
		if(pathParts.length > 1 && (pathParts[1] == "apps" || pathParts[1] == "templates"))
		{
			switch(index)
			{
				case 1:
					pathName = this.egw().lang(pathParts[1] == "apps" ? "Applications" : "Templates");
					break;
				case 2:
					pathName = this.egw().lang(pathName);
					break;
				case 3:
					if(!isNaN(<number><unknown>pathName) && pathParts[1] !== "templates")
					{
						pathName = html`${until(this.egw().link_title(pathParts[2], pathParts[3], true) || pathName, pathName)}`
					}
			}
		}
		// we want / aka pathParts [''] to be displayed as /, therefore we need to add another '' to it
		else if (pathParts.length === 1)
		{
			pathParts.unshift('');
		}
		return html`
            <sl-breadcrumb-item class="vfs-path__directory" data-value="${path.trim()}">
                ${pathName}
                <span slot="separator">/</span>
            </sl-breadcrumb-item>`;
	}

	render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		// No trailing slash in the path
		const pathParts = this.value
			// Remove trailing /
			.replace(/\/*$/, '')
			.split('/');
		const isEditable = !(this.disabled || this.readonly);
		const editing = this.editing && isEditable;

		let icon = this._getIcon(pathParts);

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'vfs-path': true,
                        'vfs-path__readonly': !isEditable,
                        'vfs-path__disabled': this.disabled,
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
                        @click=${this.handleLabelClick}
                >
                    <slot name="label">${this.label}</slot>
                </label>
                <div part="form-control-input" class="form-control-input"
                     @click=${() => this.focus()}
                     @mouseout=${(e) =>
                     {
                         if(e.target.classList.contains("form-control-input"))
                         {
                             this.checkPathOverflow();
                         }
                     }}
                >
                    <slot part="prefix" name="prefix">
                        ${icon ? html`
                            <et2-image src="${icon}" slot="prefix" height="1.5em"
                                       @click=${(e) =>
                                       {
                                           this.setValue("/");
                                           this.updateComplete.then(() => {this.dispatchEvent(new Event("change", {bubbles: true}))});
                                       }}
                            ></et2-image>` : nothing}
                    </slot>
                    ${editing ? html`
                        <input
                                class="vfs-path__value-input"
                                type="text"
                                ?disabled=${this.disabled}
                                ?required=${this.required}
                                .value=${this.value}
                                tabindex="-1"
                                @blur=${() => this.blur()}
                                @keydown=${this.handleKeyDown}
                        />
                        <div class="vfs-path__edit"/>` : html`
                        <sl-icon-button name="caret-left"
                                        @click=${(e) =>
                                        {
                                            e.stopPropagation();
                                            this.handleScroll({deltaY: -20})
                                        }}></sl-icon-button>
                        <div class="vfs-path__scroll"
                             @wheel=${this.handleScroll}
                        >
                        <sl-breadcrumb
                                label=${this.label || this.egw().lang("path")}
                                class="vfs-path__breadcrumb"
                                @click=${this.handlePathClick}
                        >
                            <span slot="separator">/</span>
                            ${repeat(pathParts, (part, i) => this.pathPartTemplate(pathParts, part, i))}
                        </sl-breadcrumb>
                        </div>
                        <sl-icon-button name="caret-right"
                                        @click=${(e) =>
                                        {
                                            e.stopPropagation();
                                            this.handleScroll({deltaY: 20})
                                        }}></sl-icon-button>
                        ${!isEditable ? nothing : html`
                            <button
                                    part="edit-button"
                                    class="vfs-path__edit"
                                    type="button"
                                    aria-label=${this.egw().lang('edit')}
                                    @click=${this.handleEditMouseDown}
                                    tabindex="-1"
                            >
                                <slot name="edit-icon">
                                    <sl-icon name="pencil"></sl-icon>
                                </slot>
                            </button>`
                        }
                    `}
                    <slot part="suffix" name="suffix"></slot>
                </div>
                <div
                        part="form-control-help-text"
                        id="help-text"
                        class="form-control__help-text"
                        aria-hidden=${hasHelpText ? 'false' : 'true'}
                >
                    <slot name="help-text">${this.helpText}</slot>
                </div>
            </div>
		`;
	}
}

customElements.define("et2-vfs-path", Et2VfsPath);