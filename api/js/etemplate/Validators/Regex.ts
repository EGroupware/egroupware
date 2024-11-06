import {Pattern} from "./StringValidators"

export class Regex extends Pattern
{
	/**
	 * Give a message about this field being required.  Could be customised according to MessageData.
	 * @param {MessageData | undefined} data
	 * @returns {Promise<string>}
	 */
	static async getMessage(data)
	{
		return data.formControl.egw().lang("'%1' does not match the required pattern '%2'", data.modelValue, data.params);
	}
}