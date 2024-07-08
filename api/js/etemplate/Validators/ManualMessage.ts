import {Validator} from "./Validator";

/**
 * Manual validator for server-side validation messages passed
 * from Etemplate.  It just gives whatever message is passed in when created.
 *
 */
export class ManualMessage extends Validator
{
	static get validatorName()
	{
		return "ManualMessage";
	}

	static async getMessage({fieldName, modelValue, formControl, params})
	{
		return params;
	}
}