import {Pattern} from "@lion/form-core";

export class Regex extends Pattern
{
	/**
	 * Give a message about this field being required.  Could be customised according to MessageData.
	 * @param {MessageData | undefined} data
	 * @returns {Promise<string>}
	 */
	static async getMessage(data)
	{
		// TODO: This is a poor error message, it shows the REGEX
		return data.formControl.egw().lang("'%1' has an invalid format !!!", data.params);
	}
}