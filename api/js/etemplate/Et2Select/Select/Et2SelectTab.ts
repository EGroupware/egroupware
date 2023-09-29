import {Et2SelectApp} from "./Et2SelectApp";
import {SelectOption} from "../FindSelectOptions";

export class Et2SelectTab extends Et2SelectApp
{
	constructor()
	{
		super();

		this.allowFreeEntries = true;
	}

	set value(new_value)
	{
		if(!new_value)
		{
			super.value = new_value;
			return;
		}
		const values = Array.isArray(new_value) ? new_value : [new_value];
		const options = this.select_options;
		values.forEach(value =>
		{
			if(!options.filter(option => option.value == value).length)
			{
				const matches = value.match(/^([a-z0-9]+)\-/i);
				let option : SelectOption = {value: value, label: value};
				if(matches)
				{
					option = options.filter(option => option.value == matches[1])[0] || {
						value: value,
						label: this.egw().lang(matches[1])
					};
					option.value = value;
					option.label += ' ' + this.egw().lang('Tab');
				}
				try
				{
					const app = opener?.framework.getApplicationByName(value);
					if(app && app.displayName)
					{
						option.label = app.displayName;
					}
				}
				catch(e)
				{
					// ignore security exception, if opener is not accessible
				}
				this.select_options.concat(option);
			}
		})
		super.value = new_value;
	}

	get value()
	{
		return super.value;
	}
}

customElements.define("et2-select-tab", Et2SelectTab);