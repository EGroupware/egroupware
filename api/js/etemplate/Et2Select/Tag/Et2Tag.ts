/**
 * EGroupware eTemplate2 - Tag WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlTag} from "@shoelace-style/shoelace";
import {css, html, TemplateResult} from "lit";
import {classMap} from "lit/directives/class-map.js";
import shoelace from "../../Styles/shoelace";
import {state} from "lit/decorators/state.js";
import {property} from "lit/decorators/property.js";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";

/**
 * Tag is usually used in a Select with multiple=true, but there's no reason it can't go anywhere
 */
export class Et2Tag extends Et2Widget(SlTag)
{
	static get styles()
	{
		return [
			super.styles,
			shoelace, css`
			:host {
			  flex: 1 1 auto;
			}

			.tag--pill {
			  overflow: hidden;
			}

			::slotted(et2-image) {
			  height: 20px;
			  width: 20px;
			}

			.tag__prefix {
			  line-height: normal;
			}
			.tag__content {
			  padding: 0px 0.2rem;
			  flex: 1 2 auto;
			  overflow: hidden;
			  text-overflow: ellipsis;
			}

			.tag__edit {
			  flex: 10 1 auto;
			  min-width: 20ex;
			  width: 60ex;
			}

			/* Avoid button getting truncated by right side of button */

			.tag__remove {
			  margin-right: 0;
			  margin-left: 0;
			}

			et2-button-icon {
			  visibility: hidden;
			}

			:host(:hover) et2-button-icon {
			  visibility: visible;
			}
			`];
	}

	@property({type: Boolean}) editable = false;
	@property({type: String, reflect: true}) value = "";

	@state() current = false; // the user has keyed into the tag (focused), but hasn't done anything yet (shows a highlight)
	@state() isEditing = false;

	constructor(...args : [])
	{
		super(...args);
		this.pill = false;
		this.removable = true;

		this.handleKeyDown = this.handleKeyDown.bind(this);
		this.handleChange = this.handleChange.bind(this);
	}

	protected _styleTemplate() : TemplateResult
	{
		return null;
	}

	render()
	{
		let content;
		if(this.isEditing)
		{
			content = html`${this._editTemplate()}`
		}
		else
		{
			content = html`${this._contentTemplate()}
            ${this.editable ? html`
                <et2-button-icon
                        label=${this.egw().lang("edit")}
                        image="pencil"
                        noSubmit="true"
                        @click=${this.startEdit}
                ></et2-button-icon>` : ''
            }
            ${this.removable
              ? html`
                        <sl-icon-button
                                part="remove-button"
                                exportparts="base:remove-button__base"
                                name="x-lg"
                                library="system"
                                label=${this.egw().lang('remove')}
                                class="tag__remove"
                                @click=${this.handleRemoveClick}
                                tabindex="-1"
                        ></sl-icon-button>
                    `
              : ''}
			`;
		}
		return html`
            ${this._styleTemplate()}
            <span
                    part="base"
                    class=${classMap({
                        tag: true,
                        'tag--editable': this.editable,
                        'tag--editing': this.isEditing,
                        // Types
                        'tag--primary': this.variant === 'primary' || this.current,
                        'tag--success': this.variant === 'success',
                        'tag--neutral': this.variant === 'neutral' && !this.current,
                        'tag--warning': this.variant === 'warning',
                        'tag--danger': this.variant === 'danger',
                        'tag--text': this.variant === 'text',
                        // Sizes
                        'tag--small': this.size === 'small',
                        'tag--medium': this.size === 'medium',
                        'tag--large': this.size === 'large',
                        // Modifiers
                        'tag--pill': this.pill,
                        'tag--removable': this.removable
                    })}
            >
				${this._prefixTemplate()}
			${content}
      </span>
		`;
	}

	_contentTemplate() : TemplateResult
	{
		return html`
            <span part="content" class="tag__content">
          <slot></slot>
        </span>`;
	}

	_editTemplate() : TemplateResult
	{
		return html`
            <span part="content" class="tag__content tag__edit">
				<et2-textbox value="${this.value}"
                             @sl-change=${this.handleChange}
                             @blur=${this.stopEdit}
                             @mousedown=${e => e.stopPropagation()}
                             @click=${e => e.stopPropagation()}
                             @keydown=${this.handleKeyDown}
                ></et2-textbox>
			</span>
		`;
	}

	_prefixTemplate() : TemplateResult
	{
		return html`
            <span part="prefix" class="tag__prefix">
				<slot name="prefix"></slot>
		</span>`;
	}

	startEdit(event? : MouseEvent)
	{
		if(event)
		{
			event.stopPropagation();
		}
		this.getRootNode().host.hide()
		this.isEditing = true;
		this.setAttribute("contenteditable", "true");

		this.requestUpdate();
		this.updateComplete.then(async() =>
		{
			await this._editNode.updateComplete;
			// This stops drag and drop from interfereing with mouse edits
			this._editNode.input.setAttribute("contenteditable", "true");

			this._editNode.focus();
		})
	}

	stopEdit()
	{
		this.isEditing = false;
		this.removeAttribute("contenteditable");
		let event = new Event("change", {
			bubbles: true
		});
		event.originalValue = this.value;
		this.dataset.original_value = this.value;
		if(!this.editable)
		{
			return;
		}
		this.value = this.textContent = this._editNode.value.trim();
		this.requestUpdate();
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(event);
		})
	}

	get _editNode() : Et2Textbox
	{
		return this.shadowRoot.querySelector('et2-textbox');
	}

	handleKeyDown(event : KeyboardEvent)
	{
		// Consume event so it doesn't bubble up to select
		event.stopPropagation();

		if(["Tab", "Enter"].indexOf(event.key) !== -1)
		{
			this._editNode.blur();
		}
		else if(["Escape"].includes(event.key))
		{
			this._editNode.value = this.value;
			this.stopEdit();
		}
	}

	handleChange(event : CustomEvent)
	{

	}
}

customElements.define("et2-tag", Et2Tag);