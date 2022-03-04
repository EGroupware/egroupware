import {ResultValidator} from "@lion/form-core";

/**
 * Manual validator for server-side validation messages passed
 * from Etemplate.  It just gives whatever message is passed in when created.
 *
 */
export class ManualMessage extends ResultValidator
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