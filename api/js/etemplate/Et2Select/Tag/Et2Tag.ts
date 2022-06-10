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
import {classMap, css, html} from "@lion/core";
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
		::slotted(et2-image)
		{
			height: 20px;
			width: 20px;
		}
		`];
	}

	constructor(...args : [])
	{
		super(...args);
	}

	render()
	{
		return html`
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