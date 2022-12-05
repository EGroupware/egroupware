/**
 * EGroupware eTemplate2 - Box widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {classMap, css, html, LitElement} from "@lion/core";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../../et2_core_interfaces";

export class Et2Box extends Et2Widget(LitElement) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
				display: block;
            }
            :host > div {
            	display: flex;
            	flex-wrap: nowrap;
            	justify-content: flex-start;
            	align-items: stretch;
            	gap: 5px;
            	height: 100%;
			}
			:host([align="right"]) > div {
				justify-content: flex-end;
			}
			:host([align="left"]) > div {
				justify-content: flex-start;
			}
			:host([align="center"]) > div {
				justify-content: center;
			}
			/* CSS for child elements */
            ::slotted(*) {
            	flex: 1 1 auto;
            }
            ::slotted(img),::slotted(et2-image) {
            	/* Stop images from growing.  In general we want them to stay */
            	flex-grow: 0;
            }
            ::slotted([align="left"]) {
            	margin-right: auto;
            	order: -1;
            }
            ::slotted([align="right"]) {
            	margin-left: auto;
            	order: 1;
            }
            
            /* work around for chromium print bug, see render() */
            :host > .no-print-gap {
            	gap: 0px;
            }
            `,
		];
	}

	render()
	{
		/**
		 * Work around Chromium bug
		 * https://bugs.chromium.org/p/chromium/issues/detail?id=1161709
		 *
		 * Printing with gap on empty element gives huge print output
		 */
		let noGap = false;
		if(this.querySelectorAll(":scope > :not([disabled])").length == 0)
		{
			noGap = true;
		}

		
		return html`
            <div part="base" ${this.id ? html`id="${this.id}"` : ''} class=${classMap({
                "no-print-gap": noGap
            })}>
                <slot></slot>
            </div> `;

	}

	set_label(new_label)
	{
		// Boxes don't have labels
	}

	_createNamespace() : boolean
	{
		return true;
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push('data');
	}

	getDetachedNodes()
	{
		return [this.getDOMNode()];
	}

	setDetachedAttributes(_nodes, _values)
	{
		if(_values.data)
		{
			this.data = _values.data;
		}
	}
}

customElements.define("et2-box", Et2Box);

export class Et2HBox extends Et2Box
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host > div {
            	flex-direction: row;
			}`
		];
	}
}

customElements.define("et2-hbox", Et2HBox);

export class Et2VBox extends Et2Box
{
	static get styles()
	{
		return [
			...super.styles,
			css`
            :host > div {
            	flex-direction: column;
			}
			/* CSS for child elements */
            ::slotted(*) {
            	/* Stop children from growing vertically.  In general we want them to stay their "normal" height */
            	flex-grow: 0;
            }
			`
		];
	}
}

customElements.define("et2-vbox", Et2VBox);