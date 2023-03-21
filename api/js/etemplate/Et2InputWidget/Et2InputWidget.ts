import {et2_IInput, et2_IInputNode, et2_ISubmitListener} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, dedupeMixin, LitElement, PropertyValues} from "@lion/core";
import {Required} from "../Validators/Required";
import {ManualMessage} from "../Validators/ManualMessage";
import {LionValidationFeedback, Validator} from "@lion/form-core";
import {et2_csvSplit} from "../et2_core_common";

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
	protected value : string | number | Object;

	public required : boolean;

	public set_value(any) : void;

	public get_value() : any;

	public getValue() : any;

	public set_readonly(boolean) : void;

	public set_validation_error(message : string | false) : void;

	public isDirty() : boolean;

	public resetDirty() : void;

	public isValid(messages : string[]) : boolean;
}

type Constructor<T = {}> = new (...args : any[]) => T;
const Et2InputWidgetMixin = <T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2InputWidgetClass extends Et2Widget(superclass) implements et2_IInput, et2_IInputNode, et2_ISubmitListener
	{
		private __readonly : boolean;
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

		protected isSlComponent = false;

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

				  .form-field__feedback {
					position: relative;
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
				// readOnly is what the property is in Lion, readonly is the attribute
				readOnly: {
					type: Boolean,
					attribute: 'readonly',
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
				}
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

			this.__readonly = false;
			this._oldValue = this.getValue();

			this.isSlComponent = typeof (<any>this).handleChange === 'function';

			this.et2HandleFocus = this.et2HandleFocus.bind(this);
			this.et2HandleBlur = this.et2HandleBlur.bind(this);
		}

		connectedCallback()
		{
			super.connectedCallback();
			this._oldChange = this._oldChange.bind(this);
			this.node = this.getInputNode();
			this.updateComplete.then(() =>
			{
				this.addEventListener(this.isSlComponent ? 'sl-change' : 'change', this._oldChange);
			});
			this.addEventListener("focus", this.et2HandleFocus);
			this.addEventListener("blur", this.et2HandleBlur);
		}

		disconnectedCallback()
		{
			super.disconnectedCallback();
			this.removeEventListener(this.isSlComponent ? 'sl-change' : 'change', this._oldChange);

			this.removeEventListener("focus", this.et2HandleFocus);
			this.removeEventListener("blur", this.et2HandleBlur);
		}

		/**
		 * A property has changed, and we want to make adjustments to other things
		 * based on that
		 *
		 * @param {import('@lion/core').PropertyValues } changedProperties
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
			this._messagesHeldWhileFocused = (this.validators || []).filter((validator) => (validator instanceof ManualMessage));

			this.updateComplete.then(() =>
			{
				// Remove all messages.  Manual will be explicitly replaced, other validators will be re-run on blur.
				this.querySelectorAll("lion-validation-feedback").forEach(e => e.remove());
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

		public get readonly() { return this.__readonly};

		set readOnly(new_value) {this.readonly = new_value;}

		/**
		 *  Lion mapping
		 * @deprecated
		 */
		get readOnly()
		{ return this.readonly};

		getValue()
		{
			return this.readonly || this.disabled ? null : this.value;
		}

		/**
		 * Legacy support for labels with %s that get wrapped around the widget
		 *
		 * Not the best way go with webComponents - shouldn't modify their DOM like this
		 *
		 * @param new_label
		 */
		set label(new_label : string)
		{
			if(!new_label || !new_label.includes("%s"))
			{
				return super.label = new_label;
			}
			this.__label = new_label;
			const [pre, post] = et2_csvSplit(new_label, 2, "%s");
			this.label = pre;
			if(post?.trim().length > 0)
			{
				const label = document.createElement("et2-description");
				label.innerText = post;
				// Put in suffix, if parent has a suffix slot
				if(this.parentNode?.shadowRoot?.querySelector("slot[name='suffix']"))
				{
					label.slot = "suffix";
				}

				this.parentNode.append(label);
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
			debugger;

			// Check for required
			if(this.required && !this.readonly && !this.disabled &&
				(this.getValue() == null || this.getValue().valueOf() == ''))
			{
				messages.push(this.egw().lang('Field must not be empty !!!'));
				ok = false;
			}
			return ok;
		}

		getInputNode()
		{
			// From LionInput
			return this._inputNode;
		}

		transformAttributes(attrs)
		{
			super.transformAttributes(attrs);

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
		 */
		async validate()
		{
			if(this.readonly)
			{
				// Don't validate if the widget is read-only, there's nothing the user can do about it
				return Promise.resolve();
			}
			let validators = [...(this.validators || []), ...(this.defaultValidators || [])];
			let fieldName = this.id;
			let feedbackData = [];
			let resultPromises = [];
			this.querySelector("lion-validation-feedback")?.remove();

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
					formControl: this,
					fieldName,
				}).then((message) =>
				{
					feedbackData.push({message, type: validator.type, validator});
				});
			}.bind(this);

			// Check if a validator fails
			const doCheck = async(value, validator) =>
			{
				const result = validator.execute(value, validator.param, {node: this});
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
				let values = this.getValue();
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
					doCheck(values, validator);
				}
					// Only validate if field is required, or not required and has a value
				// Don't bother to validate empty fields
				else if(this.required || !this.required && this.getValue() != '')
				{
					// Validate each individual item
					values.forEach((value) => doCheck(value, validator));
				}
			});
			this.validateComplete = Promise.all(resultPromises);

			// Wait until all validation is finished, then update UI
			this.validateComplete.then(() =>
			{
				// Show feedback from all failing validators
				if(feedbackData.length > 0)
				{
					let feedback = <LionValidationFeedback>document.createElement("lion-validation-feedback");
					feedback.feedbackData = feedbackData;
					feedback.slot = "help-text";
					this.append(feedback);
					if(this.shadowRoot.querySelector("slot[name='feedback']"))
					{
						feedback.slot = "feedback";
					}
					else if(<HTMLElement>this.shadowRoot.querySelector("#help-text"))
					{
						// Not always visible?
						(<HTMLElement>this.shadowRoot.querySelector("#help-text")).style.display = "initial";
					}
					else
					{
						// No place to show the validation error.  That's a widget problem, but we'll show it as message
						this.egw().message(feedback.textContent, "error");
					}
				}
			});

			return this.validateComplete;
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
			let feedback = (<LionValidationFeedback>this.querySelector("lion-validation-feedback"))?.feedbackData || [];
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

			// If using Lion validators, run them now
			if(this.validate)
			{
				// Force update now
				this.validate(true);
				await this.validateComplete;

				return (this.hasFeedbackFor || []).indexOf("error") == -1;
			}
			return true;
		}
	}

	return Et2InputWidgetClass;
}
export const Et2InputWidget = dedupeMixin(Et2InputWidgetMixin);