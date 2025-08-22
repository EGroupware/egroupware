import {css, html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {et2_IInput, et2_IInputNode, et2_ISubmitListener} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {HasSlotController} from "../Et2Widget/slot";
import {et2_csvSplit} from "../et2_core_common";
import {property} from "lit/decorators/property.js";
import {Validator} from "../Validators/Validator";
import {ManualMessage} from "../Validators/ManualMessage";
import {Required} from "../Validators/Required";
import {EgwValidationFeedback} from "../Validators/EgwValidationFeedback";
import {dedupeMixin} from "@open-wc/dedupe-mixin";
import {Et2TabPanel} from "../Layout/Et2Tabs/Et2TabPanel";
import type {Et2Tabs} from "../Layout/Et2Tabs/Et2Tabs";


/**
 * This mixin will allow any LitElement to become an Et2InputWidget
 *
 * Usage:
 * export class Et2Button extends Et2InputWidget(LitWidget)) {...}
 */

/**
 * Need to define the interface first, to get around TypeScript issues with protected/public
 * This must match the public API for Et2InputWidgetClass
 * @see https://lit.dev/docs/composition/mixins/#typing-the-subclass
 */
export declare class Et2InputWidgetInterface
{
	readonly : boolean;
	disabled : boolean;
	protected value : string | number | Object;

	public required : boolean;

	public set_value(any) : void;

	public get_value() : any;

	public getValue(submit_value? : boolean) : any;

	public set_readonly(boolean) : void;

	public set_validation_error(message : string | false) : void;

	public isDirty() : boolean;

	public resetDirty() : void;

	public isValid(messages : string[]) : boolean;

	public focus() : void;
}

/**
 * Massively simplified validate, as compared to what ValidatorMixin gives us, since ValidatorMixin extends
 * FormControlMixin which breaks SlSelect's render()
 *
 * We take all validators for the widget, and if there's a value (or field is required) we check the value
 * with each validator.  For array values we check each element with each validator.  If the value does not
 * pass the validator, we collect the message and display feedback to the user.
 *
 * We handle validation errors from the server with ManualMessages, which always "fail".
 * If the value is empty, we only validate if the field is required.
 *
 * @param skipManual Do not run any manual validators, used during submit check.  We don't want manual validators to block submit.
 */
export async function validate(widget,skipManual = false)
{
	if(widget.readonly || widget.disabled)
	{
		// Don't validate if the widget is read-only, there's nothing the user can do about it
		return Promise.resolve();
	}
	let validators = [...(widget.validators || []), ...(widget.defaultValidators || [])];
	let fieldName = widget.id;
	let feedbackData = [];
	let resultPromises = [];
	(<EgwValidationFeedback>widget.querySelector("egw-validation-feedback"))?.remove();

	// Collect message of a (failing) validator
	const doValidate = async function(validator, value)
	{
		if(validator.config.fieldName)
		{
			fieldName = await validator.config.fieldName;
		}
		// @ts-ignore [allow-protected]
		return validator._getMessage({
			modelValue: value,
			formControl: widget,
			fieldName,
		}).then((message) =>
		{
			feedbackData.push({message, type: validator.type, validator});
		});
	}.bind(widget);

	// Check if a validator fails
	const doCheck = async(value, validator) =>
	{
		const result = validator.execute(value, validator.param, {node: widget});
		if(result === true)
		{
			resultPromises.push(doValidate(validator, value));
		}
		else if(result !== false && typeof result.then === 'function')
		{
			result.then(doValidate(validator, value));
			resultPromises.push(result);
		}
	};

	validators.map(async validator =>
	{
		let values = widget.getValue();
		if(!Array.isArray(values))
		{
			values = [values];
		}
		if(!values.length)
		{
			values = [''];
		}	// so required validation works

		// Run manual validation messages just once, doesn't usually matter what the value is
		if(validator instanceof ManualMessage)
		{
			if(!skipManual)
			{
				doCheck(values, validator);
			}
		}
			// Only validate if field is required, or not required and has a value
		// Don't bother to validate empty fields
		else if(widget.required || !widget.required && widget.getValue() != '' && widget.getValue() !== null)
		{
			// Validate each individual item
			values.forEach((value) => doCheck(value, validator));
		}
	});
	widget.validateComplete = Promise.all(resultPromises);

	// Wait until all validation is finished, then update UI
	widget.validateComplete.then(() =>
	{
		// Show feedback from all failing validators
		if(feedbackData.length > 0)
		{
			let feedback = document.createElement("egw-validation-feedback");
			feedback.feedbackData = feedbackData;
			feedback.slot = "help-text";
			widget.append(feedback);
			if(widget.shadowRoot.querySelector("slot[name='feedback']"))
			{
				feedback.slot = "feedback";
			}
			else if(<HTMLElement>widget.shadowRoot.querySelector("#help-text"))
			{
				// Not always visible?
				(<HTMLElement>widget.shadowRoot.querySelector("#help-text")).style.display = "initial";
			}
			else
			{
				// No place to show the validation error.  That's a widget problem, but we'll show it as message
				widget.egw().message(feedback.textContent, "error");
			}
		}
	});

	return widget.validateComplete;
}


type Constructor<T = {}> = new (...args : any[]) => T;
const Et2InputWidgetMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2InputWidgetClass extends Et2Widget(superclass) implements et2_IInput, et2_IInputNode, et2_ISubmitListener
	{
		private __readonly : boolean;
		private __label : string = "";
		protected _oldValue : string | number | Object;
		protected node : HTMLElement;

		// Validators assigned to one specific instance of a widget
		protected validators : Validator[];
		// Validators for every instance of a type of widget
		protected defaultValidators : Validator[];
		// Promise used during validation
		protected validateComplete : Promise<undefined>;
		// Hold on to any server messages while the user edits
		private _messagesHeldWhileFocused : Validator[];

		// Allows us to check to see if label or help-text is set.  Override to check additional slots.
		protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'label');

		/** WebComponent **/
		static get styles()
		{
			return [
				...super.styles,
				css`
				  /* Allow actually disabled inputs */

				  :host([disabled]) {
					display: initial;
				  }

				  /* Needed so required can show through */

				  ::slotted(input), input {
					background-color: transparent;
				  }

				  /* Used to allow auto-sizing on slotted inputs */

				  .input-group__container > .input-group__input ::slotted(.form-control) {
					width: 100%;
				  }

				  .form-control__help-text {
					position: relative;
					  width: 100%;
				  }
				`
			];
		}

		static get properties()
		{
			return {
				...super.properties,
				/**
				 * The label of the widget
				 * Overridden from parent to use our accessors
				 */
				label: {
					type: String, noAccessor: true
				},

				// readonly is what is in the templates
				// I put this in here so loadWebComponent finds it when it tries to set it from the template
				readonly: {
					type: Boolean,
					reflect: true
				},

				required: {
					type: Boolean,
					reflect: true
				},
				onchange: {
					type: Function
				},
				/**
				 * Have browser focus this input on load.
				 * Overrides etemplate2.focusOnFirstInput(), use only once per page
				 * https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input#attributes
				 */
				autofocus: {
					type: Boolean,
					reflect: true
				},

				autocomplete: {
					type: String
				},
				ariaLabel : String,
				ariaDescription : String,
				helpText : String,
			};
		}

		/**
		 * List of properties that get translated
		 * Done separately to not interfere with properties - if we re-define label property,
		 * labels go missing.
		 * @returns object
		 */
		static get translate()
		{
			return {
				...super.translate,
				placeholder: true,
				ariaLabel : true,
				ariaDescription : true,
				helpText : true,
			}
		}

		/**
		 * Compatibility for deprecated name "needed"
		 *
		 * @deprecated use required instead
		 * @param val
		 */
		set needed(val : boolean)
		{
			this.required = val;
		}
		/**
		 * Compatibility for deprecated name "needed"
		 *
		 * @deprecated use required instead
		 */
		get needed()
		{
			return this.required;
		}

		constructor(...args : any[])
		{
			super(...args);

			this.validators = [];
			this.defaultValidators = [];
			this._messagesHeldWhileFocused = [];

			this.readonly = false;
			this.required = false;
			this._oldValue = this.getValue();

			this.et2HandleFocus = this.et2HandleFocus.bind(this);
			this.et2HandleBlur = this.et2HandleBlur.bind(this);
			this.handleSlChange = this.handleSlChange.bind(this);
			this.autocomplete = 'on';
		}

		connectedCallback()
		{
			super.connectedCallback();

			this.classList.add("et2-input-widget");
			this._oldChange = this._oldChange.bind(this);
			this.node = this.getInputNode();
			this.addEventListener("sl-change", this.handleSlChange);
			this.addEventListener("change", this._oldChange);
			this.addEventListener("focus", this.et2HandleFocus);
			this.addEventListener("blur", this.et2HandleBlur);

			// set aria-label and -description fallbacks (done here and not in updated to ensure reliable fallback order)
			if (!this.ariaLabel) this.ariaLabel = this.label || this.placeholder || this.statustext;
			if (!this.ariaDescription) this.ariaDescription = this.helpText || (this.statustext !== this.ariaLabel ? this.statustext : '');
			this._setAriaAttributes();
		}

		/**
		 * Set aria-attributes on our input node
		 *
		 * @protected
		 */
		protected _setAriaAttributes()
		{
			// pass them on to input-node,  if we have one / this.getInputNode() returns one
			const input = this.getInputNode();
			if (input)
			{
				input.ariaLabel = this.ariaLabel;
				input.ariaDescription = this.ariaDescription;
			}
		}

		disconnectedCallback()
		{
			super.disconnectedCallback();
			this.removeEventListener("sl-change", this.handleSlChange);
			this.removeEventListener("change", this._oldChange);
			this.removeEventListener("focus", this.et2HandleFocus);
			this.removeEventListener("blur", this.et2HandleBlur);

			// Hacky hack to clean up Shoelace form controller
			// https://github.com/shoelace-style/shoelace/issues/2376
			if(this.formControlController && this.formControlController.form)
			{
				this.formControlController.form.removeEventListener('formdata', this.formControlController.handleFormData);
				this.formControlController.form.removeEventListener('submit', this.formControlController.handleFormSubmit);
				this.formControlController.form.removeEventListener('reset', this.formControlController.handleFormReset);
			}
		}

		destroy()
		{
			super.destroy();
			this.onchange = null;
			this.change = null;
		}

		/**
		 * A property has changed, and we want to make adjustments to other things
		 * based on that
		 *
		 * @param changedProperties
		 */
		updated(changedProperties : PropertyValues)
		{
			super.updated(changedProperties);

			// required changed, add / remove validator
			if(changedProperties.has('required'))
			{
				// Remove all existing Required validators (avoids duplicates)
				this.validators = (this.validators || []).filter((validator) => !(validator instanceof Required))
				if(this.required)
				{
					this.validators.push(new Required());
				}
			}

			if(changedProperties.has("value"))
			{
				// Base off this.value, not this.getValue(), to ignore readonly
				this.classList.toggle("hasValue", !(this.value == null || this.value == ""));
			}

			// pass aria-attributes to our input node
			if (changedProperties.has('ariaLabel') || changedProperties.has('ariaDescription'))
			{
				this._setAriaAttributes();
			}
		}

		/**
		 * Change handler calling custom handler set via onchange attribute
		 *
		 * @param _ev
		 * @returns
		 */
		_oldChange(_ev : Event) : boolean
		{
			if(typeof this.onchange == 'function' && (
				// If we have an instanceManager, make sure it's ready.  Otherwise, we ignore the event
				!this.getInstanceManager() || this.getInstanceManager().isReady
			))
			{
				// Make sure function gets a reference to the widget, splice it in as 2. argument if not
				let args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1)
				{
					args.splice(1, 0, this);
				}

				return this.onchange(...args);
			}

			return true;
		}

		/**
		 * When input receives focus, clear any validation errors.
		 *
		 * If the value is the same on blur, we'll put them back
		 * The ones from the server (ManualMessage) can interfere with submitting.
		 *
		 * Named et2HandleFocus to avoid overwriting handleFocus() in Shoelace components
		 *
		 * @param {FocusEvent} _ev
		 */
		et2HandleFocus(_ev : FocusEvent)
		{
			if(this._messagesHeldWhileFocused.length > 0)
			{
				return;
			}

			// Collect any ManualMessages
			this._messagesHeldWhileFocused = (this.validators || []).filter(
				(validator) => (validator instanceof ManualMessage)
			);
			// Remove ManualMessages from validators list
			for(let i = 0; i < this.validators.length; i++)
			{
				if(this._messagesHeldWhileFocused.indexOf(this.validators[i]) != -1)
				{
					this.validators.splice(i, 1);
				}
			}

			this.updateComplete.then(() =>
			{
				// Remove all messages.  Manual will be explicitly replaced, other validators will be re-run on blur.
				this.querySelectorAll("egw-validation-feedback").forEach(e => e.remove());
			});
		}

		/**
		 * If the value is unchanged, put any held validation messages back
		 *
		 * Named et2HandleBlur to avoid overwriting handleBlur() in Shoelace components
		 *
		 * @param {FocusEvent} _ev
		 */
		et2HandleBlur(_ev : FocusEvent)
		{
			if(this._messagesHeldWhileFocused.length > 0 && this.getValue() == this._oldValue)
			{
				this.validators = this.validators.concat(this._messagesHeldWhileFocused);
				this._messagesHeldWhileFocused = [];
			}
			this.updateComplete.then(() =>
			{
				this.validate();
			});
		}

		/**
		 * Handle sl-change event from Shoelace components and dispatch a change event so anything listening for
		 * change events can react to it instead of having to listen for both sl-change and change.
		 *
		 * @param event
		 */
		handleSlChange(event)
		{
			// Only for ourselves, don't dispatch for children bubbling up
			if(event.target === this)
			{
				event.stopPropagation();
				this.updateComplete.then(() =>
				{
					this.dispatchEvent(new Event("change", {bubbles: true}));
				});
			}
		}

		set_value(new_value)
		{
			this.value = new_value;

			// Save this so we can compare against any user changes
			this._oldValue = this.getValue();

			if(typeof this._callParser == "function")
			{
				this.modelValue = this._callParser(new_value);
			}
		}

		get_value()
		{
			return this.getValue();
		}

		set_readonly(new_value)
		{
			this.readonly = new_value;
		}

		// Deal with Lion readOnly vs etemplate readonly
		public set readonly(new_value)
		{
			this.__readonly = super.__readOnly = new_value;
			this.requestUpdate("readonly");
		}

		public get readonly()
		{
			return this.__readonly;
		}

		/**
		 * Was from early days (Lion)
		 * @deprecated
		 * @param {boolean} new_value
		 */
		set readOnly(new_value)
		{
			this.readonly = new_value;
		}

		/**
		 *  Lion mapping
		 * @deprecated
		 */
		get readOnly()
		{
			return this.readonly;
		}

		/**
		 * @param boolean submit_value true: call by etemplate2.(getValues|submit|postSubmit)()
		 */
		getValue(submit_value? : boolean)
		{
			return this.readonly || this.disabled ? null : (
				// Give a clone of objects or receiver might use the reference
				this.value && typeof this.value == "object" ? (typeof this.value.length == "undefined" ? {...this.value} : [...this.value]) : this.value
			);
		}

		/**
		 * The label of the widget
		 * Legacy support for labels with %s that get wrapped around the widget
		 *
		 * Not the best way go with webComponents - shouldn't modify their DOM like this
		 *
		 * @param new_label
		 */
		@property()
		set label(new_label : string)
		{
			if(!new_label || !new_label.includes("%s"))
			{
				super.set_label(new_label);
				return;
			}
			this.__label = new_label;
			const [pre, post] = et2_csvSplit(new_label, 2, "%s");
			this.label = pre;
			if(post?.trim().length > 0)
			{
				this.__label = pre;
				this.updateComplete.then(() =>
				{
					const label = document.createElement("et2-description");
					label.innerText = post;
					// Add into shadowDOM (may go missing, in which case we need a different strategy)
					this.shadowRoot?.querySelector(".form-control-input").after(label);
				});
			}
		}

		get label()
		{
			return this.__label;
		}

		isDirty()
		{
			// Readonly can't be dirty, it can't change
			if(this.readonly)
			{
				return false;
			}

			let value = this.getValue();
			if(typeof value !== typeof this._oldValue)
			{
				return true;
			}
			if(this._oldValue === value)
			{
				return false;
			}
			switch(typeof this._oldValue)
			{
				case "object":
					if(Array.isArray(this._oldValue) &&
						this._oldValue.length !== value.length
					)
					{
						return true;
					}
					for(let key in this._oldValue)
					{
						if(this._oldValue[key] !== value[key])
						{
							return true;
						}
					}
					return false;
				default:
					return this._oldValue != value;
			}
		}

		resetDirty()
		{
			this._oldValue = this.getValue();
		}

		/**
		 * Used by etemplate2 to determine if we can submit or not
		 *
		 * @param messages
		 * @returns {boolean}
		 */
		isValid(messages)
		{
			var ok = true;

			// Check for required
			if(this.required && !this.readonly && !this.disabled &&
				(this.getValue() == null || this.getValue().valueOf() == ''))
			{
				messages.push(this.egw().lang('Field must not be empty !!!'));
				ok = false;
			}
			return ok;
		}

		/**
		 * Get input to e.g. set aria-attributes
		 */
		getInputNode()
		{
			return this.shadowRoot?.querySelector('input');
		}

		async focus()
		{
			const tab = <Et2TabPanel>this.closest('et2-tab-panel');
			if(tab && tab.name)
			{
				(<Et2Tabs>tab.parentElement).show(tab.name);
				await (<Et2Tabs>tab.parentElement).updateComplete;
			}
			this.scrollIntoViewIfNeeded && this.scrollIntoViewIfNeeded();
			super.focus && super.focus();
			this.getInputNode()?.focus();
		}

		transformAttributes(attrs)
		{
			super.transformAttributes(attrs);

			// Set attributes for the form / autofill.  It's the individual widget's
			// responsibility to do something appropriate with these properties.
			if(this.autocomplete == "on" && window.customElements.get(this.localName).getPropertyOptions("name") != "undefined" &&
				this.getArrayMgr("content") !== null
			)
			{
				this.name = this.getArrayMgr("content").explodeKey(this.id).pop();
			}

			// Check whether an validation error entry exists
			if(this.id && this.getArrayMgr("validation_errors"))
			{
				let val = this.getArrayMgr("validation_errors").getEntry(this.id);
				if(val)
				{
					this.set_validation_error(val);
				}
			}
		}

		/**
		 * Massively simplified validate, as compared to what ValidatorMixin gives us, since ValidatorMixin extends
		 * FormControlMixin which breaks SlSelect's render()
		 *
		 * We take all validators for the widget, and if there's a value (or field is required) we check the value
		 * with each validator.  For array values we check each element with each validator.  If the value does not
		 * pass the validator, we collect the message and display feedback to the user.
		 *
		 * We handle validation errors from the server with ManualMessages, which always "fail".
		 * If the value is empty, we only validate if the field is required.
		 *
		 * @param skipManual Do not run any manual validators, used during submit check.  We don't want manual validators to block submit.
		 */
		async validate(skipManual = false)
		{
			return validate(this, skipManual).then(() => this.requestUpdate());
		}

		set_validation_error(err : string | false)
		{
			/* Shoelace uses constraint validation API
			https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#the-constraint-validation-api

			if(err === false && this.setCustomValidity)
			{
				// Remove custom validity
				this.setCustomValidity('');
				return;
			}
			this.setCustomValidity(err);

			// must call reportValidity() or nothing will happen
			this.reportValidity();

			 */

			if(err === false)
			{
				// Remove all Manual validators
				this.validators = (this.validators || []).filter((validator) => !(validator instanceof ManualMessage))
				return;
			}
			// Need to change interaction state so messages show up
			// submitted is a little heavy-handed, especially on first load, but it works
			this.submitted = true;

			// Add validator
			this.validators.push(new ManualMessage(err));
			// Force a validate - not needed normally, but if you call set_validation_error() manually,
			// it won't show up without validate()
			this.validate();
		}

		/**
		 * Get a list of feedback types
		 *
		 * @returns {string[]}
		 */
		public get hasFeedbackFor() : string[]
		{
			let feedback = (this.querySelector("egw-validation-feedback"))?.feedbackData || [];
			return feedback.map((f) => f.type);
		}

		/**
		 * Called whenever the template gets submitted. We return false if the widget
		 * is not valid, which cancels the submission.
		 *
		 * @param _values contains the values which will be sent to the server.
		 * 	Listeners may change these values before they get submitted.
		 */
		async submit(_values) : Promise<boolean>
		{
			this.submitted = true;

			// If using validators, run them now
			if(this.validate)
			{
				// Force update now
				this.validate(true);
				await this.validateComplete;

				return (this.hasFeedbackFor || []).indexOf("error") == -1;
			}
			return true;
		}

		/**
		 * Common sub-template to add a label.
		 * This goes inside the form control wrapper div, before and at the same depth as the input controls.
		 *
		 *
		 * @returns {TemplateResult} Either a TemplateResult or nothing (the object).  Check for nothing to set
		 *    'form-control--has-label' class on the wrapper div.
		 * @protected
		 */
		protected _labelTemplate() : TemplateResult | typeof nothing
		{
			const hasLabelSlot = this.hasSlotController?.test('label');
			const hasLabel = this.label ? true : !!hasLabelSlot;
			return hasLabel ? html`
                <label
                        id="label"
                        part="form-control-label"
                        class="form-control__label"
                        aria-hidden=${hasLabel ? 'false' : 'true'}
                        @click=${typeof this.handleLabelClick == "function" ? this.handleLabelClick : nothing}
                >
                    <slot name="label">${this.label}</slot>
                </label>
			` : nothing;
		}

		protected _helpTextTemplate() : TemplateResult | typeof nothing
		{
			const hasHelpTextSlot = this.hasSlotController?.test('help-text');
			const hasHelpText = this.helpText ? true : !!hasHelpTextSlot || this.hasFeedbackFor.length > 0;
			return hasHelpText ? html`
                <div
                        part="form-control-help-text"
                        id="help-text"
                        class="form-control__help-text"
                        aria-hidden=${hasHelpText ? 'false' : 'true'}
                >
                    ${this.helpText}
                    <slot name="help-text"></slot>
                </div>` : nothing;
		}
	}

	return Et2InputWidgetClass as Constructor & T;
}
export const Et2InputWidget = dedupeMixin(Et2InputWidgetMixin);