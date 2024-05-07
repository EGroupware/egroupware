import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {html, LitElement} from "lit";


/**
 * @desc Takes care of accessible rendering of error messages
 * Should be used in conjunction with FormControl having ValidateMixin applied
 *
 * Based on Lion
 */
@customElement("egw-validation-feedback")
export class EgwValidationFeedback extends LitElement
{
	@property({type: Array, attribute: false})
	feedbackData = [];

	/**
	 * @overridable
	 * @param {Object} opts
	 * @param {string | Node | TemplateResult } opts.message message or feedback node or TemplateResult
	 * @param {string} [opts.type]
	 * @param {Validator} [opts.validator]
	 * @protected
	 */
	// eslint-disable-next-line class-methods-use-this
	_messageTemplate({message})
	{
		return message;
	}

	/**
	 * @param  changedProperties
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);
		if(this.feedbackData && this.feedbackData[0])
		{
			this.setAttribute('type', this.feedbackData[0].type);
			this.currentType = this.feedbackData[0].type;
			window.clearTimeout(this.removeMessage);
			// TODO: this logic should be in ValidateMixin, so that [show-feedback-for] is in sync,
			// plus duration should be configurable
			if(this.currentType === 'success')
			{
				this.removeMessage = window.setTimeout(() =>
				{
					this.removeAttribute('type');
					/** @type {messageMap[]} */
					this.feedbackData = [];
				}, 3000);
			}
		}
		else if(this.currentType !== 'success')
		{
			this.removeAttribute('type');
		}
	}

	render()
	{
		return html`
            ${this.feedbackData &&
            this.feedbackData.map(
                    ({message, type, validator}) => html`
                        ${this._messageTemplate({message, type, validator})}
                    `,
            )}
		`;
	}
}