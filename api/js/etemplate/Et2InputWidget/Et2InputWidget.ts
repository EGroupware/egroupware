import {et2_IInput, et2_IInputNode} from "../et2_core_interfaces";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {dedupeMixin} from "@lion/core";

/**
 * This mixin will allow any LitElement to become an Et2InputWidget
 *
 * Usage:
 * export class Et2Button extends Et2InputWidget(Et2Widget(LitWidget)) {...}
 */


const Et2InputWidgetClass = superclass =>
	class extends Et2Widget(superclass) implements et2_IInput, et2_IInputNode
	{

		label : string = '';
		protected value : string | number | Object;
		protected _oldValue : string | number | Object;

		/** WebComponent **/
		static get properties()
		{
			return {
				...super.properties,
				// readOnly is what the property is in Lion, readonly is the attribute
				readOnly: {
					type: Boolean,
					attribute: 'readonly',
					reflect: true,
				},
			};
		}

		constructor(...args : any[])
		{
			super(...args);

		}

		set_value(new_value)
		{
			this.value = new_value;
		}

		get_value()
		{
			return this.getValue();
		}

		getValue()
		{
			return typeof this.serializedValue !== "undefined" ? this.serializedValue : this.modalValue;
		}


		isDirty()
		{
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

		isValid(messages)
		{
			var ok = true;

			// Check for required
			if(this.options && this.options.needed && !this.options.readonly && !this.disabled &&
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
	};

export const Et2InputWidget = dedupeMixin(Et2InputWidgetClass);