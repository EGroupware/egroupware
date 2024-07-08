import {Validator} from "./Validator";

export class Required extends Validator
{
	/**
	 * Returns a Boolean.  True if the test fails
	 * @param {?} [modelValue]
	 * @param {?} [param]
	 * @param {{}} [config]
	 * @returns {Boolean|Promise<Boolean>}
	 */
	execute(modelValue : any, param : any, config : {}) : boolean | Promise<boolean>
	{
		return modelValue == "" || modelValue == undefined || modelValue == null;
	}

	/**
	 * Give a message about this field being required.  Could be customised according to MessageData.
	 * @param {MessageData | undefined} data
	 * @returns {Promise<string>}
	 */
	static async getMessage(data)
	{
		return data.formControl.egw().lang("Field must not be empty !!!");
	}
}