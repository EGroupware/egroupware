import {Required as LionRequired} from "@lion/form-core";

export class Required extends LionRequired
{
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