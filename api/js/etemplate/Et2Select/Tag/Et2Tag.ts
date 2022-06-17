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
import {classMap, css, html, TemplateResult} from "@lion/core";
import shoelace from "../../Styles/shoelace";

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
		.tag--pill {
			overflow: hidden;
		}
		::slotted(et2-image)
		{
			height: 20px;
			width: 20px;
		}
		.tag__content {
			padding: 0px 0.2rem;
		}
		/* Avoid button getting truncated by right side of button */
		.tag__remove {
			margin-right: 0;
			margin-left: 0;
		}
		`];
	}

	static get properties()
	{
		return {
			...super.properties,
			value: {type: String, reflect: true}
		}
	}

	constructor(...args : [])
	{
		super(...args);
		this.value = "";
		this.pill = true;
		this.removable = true;
	}

	protected _styleTemplate() : TemplateResult
	{
		return null;
	}

	render()
	{
		return html`
            ${this._styleTemplate()}
            <span
                    part="base"
                    class=${classMap({
                        tag: true,
                        // Types
                        'tag--primary': this.variant === 'primary',
                        'tag--success': this.variant === 'success',
                        'tag--neutral': this.variant === 'neutral',
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
		  <span part="prefix" class="tag__prefix">
			  <slot name="prefix"></slot>
		  </span>
        <span part="content" class="tag__content">
          <slot></slot>
        </span>
        ${this.removable
          ? html`
                    <sl-icon-button
                            part="remove-button"
                            exportparts="base:remove-button__base"
                            name="x"
                            library="system"
                            label=${this.egw().lang('remove')}
                            class="tag__remove"
                            @click=${this.handleRemoveClick}
                    ></sl-icon-button>
                `
          : ''}
      </span>
		`;
	}
}

customElements.define("et2-tag", Et2Tag);