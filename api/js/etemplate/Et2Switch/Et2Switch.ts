/**
 * EGroupware eTemplate2 - Switch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {html, nothing} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {property} from "lit/decorators/property.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlSwitch} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

import styles from "./Et2Switch.styles";
/**
 * Switch to turn on or off.  Like a checkbox, but different UI.
 *
 * Add "et2SlideSwitch" class to use an alternate UI with images.  Use CSS to set the images:
 *
 * `label` names the switch - it goes to the left of it, like the label of any other input widget,
 * and is what a screen reader reads out for the control.  toggleOn / toggleOff are a different
 * thing: short state names written on the switch itself, for when "on" and "off" are worth
 * spelling out.  Both can be used at once.
 *
 * @slot label - The switch's label.  Normally set with the `label` attribute instead.
 * @slot help-text - Text describing how to use the switch.  Or use the `helpText` attribute.
 *
 * @csspart form-control - The wrapper around label, switch and help text.
 * @csspart form-control-label - The label's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart base - The switch itself: the control plus the toggleOn / toggleOff text.
 * @csspart control - The control that houses the switch's thumb.
 * @csspart thumb - The switch's thumb.
 * @csspart label - The wrapper around the toggleOn / toggleOff text.
 */
export class Et2Switch extends Et2InputWidget(SlSwitch)
{
	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			styles,
		];
	}

	/* label to show when the toggle switch is on */
	@property({type: String})
	toggleOn = '';

	/* label to show when the toggle switch is off */
	@property({type: String})
	toggleOff = '';

	constructor()
	{
		super();
		this.isSlComponent = true;
	}

	/**
	 * Shoelace's switch has no label of its own - its only content slot is the default one, which
	 * we need for toggleOn / toggleOff - and we can not reach inside its render() to add one.  So
	 * we wrap it: our label part comes first, and Shoelace's own form-control is flattened into
	 * our flex row (`display: contents`, see Et2Switch.styles) so label, switch and help text sit
	 * in one row under the same part names as any other input widget.  Going through the normal
	 * part names is what makes `.et2-label-fixed`, the required-asterisk and the label font-size
	 * work here too, instead of having to be re-invented for the switch.
	 */
	render()
	{
		const label = this._labelTemplate();
		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        "form-control": true,
                        "form-control--has-label": label !== nothing,
                        "form-control--small": this.size === "small",
                        "form-control--medium": this.size === "medium",
                        "form-control--large": this.size === "large",
                    })}
            >
                ${label}
                ${super.render()}
            </div>
		`;
	}

	updated(changedProperties)
	{
		super.updated(changedProperties);

		if(changedProperties.has("toggleOn") || changedProperties.has("toggleOff") ||
			changedProperties.has("checked"))
		{
			this._updateToggleNode();
		}

		// Shoelace's <input> is inside markup we do not render, so aria-labelledby can not go in a
		// template.  Without it the switch has no accessible name: our label is outside the
		// <label> Shoelace wraps around the input, so the implicit association that names a
		// checkbox by its adjacent text never reaches it.
		const input = this.getInputNode();
		if(input)
		{
			if(this.shadowRoot?.querySelector("#label"))
			{
				input.setAttribute("aria-labelledby", "label");
			}
			else
			{
				input.removeAttribute("aria-labelledby");
			}
		}
	}

	set value(new_value : string | boolean)
	{
		this.requestUpdate("checked");
		this.checked = !!new_value;
		return;
	}

	get value ()
	{
		return this.checked;
	}

	/**
	 * The toggleOn / toggleOff text, which lives in the light DOM
	 *
	 * Shoelace's default slot is the only place inside the switch we can put content, and the CSS
	 * that lays the text over the control (Et2Switch.styles) needs it slotted there.
	 */
	private get _toggleNode() : HTMLElement
	{
		return this.querySelector(":scope > span.label");
	}

	/**
	 * Create the toggleOn / toggleOff text on first use, then keep it up to date in place.
	 *
	 * Updating in place rather than re-rendering matters: `label` puts its own slotted span into
	 * this same light DOM (Et2Widget.set_label()), and re-rendering the light DOM took that span
	 * with it - which is how a switch used to end up with no label and no accessible name at all.
	 */
	private _updateToggleNode()
	{
		const switchLabel = this.shadowRoot?.querySelector(".switch__label");
		if(!this.toggleOn && !this.toggleOff)
		{
			this._toggleNode?.remove();
			switchLabel?.classList.remove("toggle__label");
			return;
		}

		let node = this._toggleNode;
		if(!node)
		{
			node = document.createElement("span");
			node.classList.add("label");
			node.append(...["on", "off"].map(state =>
			{
				const text = document.createElement("span");
				text.classList.add(state);
				return text;
			}));
			this.appendChild(node);
		}
		node.querySelector(".on").textContent = this.toggleOn;
		node.querySelector(".off").textContent = this.toggleOff;
		node.classList.toggle("on", this.checked);
		switchLabel?.classList.add("toggle__label");
	}
}

customElements.define("et2-switch", Et2Switch);
