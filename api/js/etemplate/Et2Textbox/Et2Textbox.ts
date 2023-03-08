/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, PropertyValues} from "@lion/core";
import {Regex} from "../Validators/Regex";
import {SlInput} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

export class Et2Textbox extends Et2InputWidget(SlInput)
{

	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
				:host([type="hidden"]) {
					display: none;
				}
				.input__control {
					border: none;
					width: 100%;
				}
				.input:hover:not(.input--disabled) .input__control {
					color: var(--input-text-color, inherit);
				}
				`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Perl regular expression eg. '/^[0-9][a-f]{4}$/i'
			 *
			 * Not to be confused with this.validators, which is a list of validators for this widget
			 */
			validator: String,
			onkeypress: Function,
		}
	}

	static get translate()
	{
		return Object.assign({
			helpText: true
		}, super.translate);
	}

	constructor(...args : any[])
	{
		super(...args);
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	/** @param {import('@lion/core').PropertyValues } changedProperties */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has('validator'))
		{
			// Remove all existing Pattern validators (avoids duplicates)
			this.validators = (this.validators || []).filter((validator) => !(validator instanceof Regex))
			this.validators.push(new Regex(this.validator));
		}
	}

	get validator()
	{
		return this.__validator;
	}

	set validator(value)
	{
		if(typeof value == 'string')
		{
			let parts = value.split('/');
			let flags = parts.pop();
			if(parts.length < 2 || parts[0] !== '')
			{
				this.egw().debug(this.egw().lang("'%1' has an invalid format !!!", value));
				return;
			}
			parts.shift();
			this.__validator = new RegExp(parts.join('/'), flags);

			this.requestUpdate("validator");
		}
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-textbox", Et2Textbox);