import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";
import {cleanSelectOptions} from "../FindSelectOptions";

export class Et2SelectDayOfWeek extends Et2StaticSelectMixin(Et2Select)
{
	connectedCallback()
	{
		super.connectedCallback();

		// Wait for connected instead of constructor because attributes make a difference in
		// which options are offered
		this.fetchComplete = StaticOptions.dow(this, {other: this.other || []}).then(options =>
		{
			this.set_static_options(cleanSelectOptions(options));
		});
	}

	set value(new_value)
	{
		let expanded_value = typeof new_value == "object" ? new_value : [];
		if(new_value && (typeof new_value == "string" || typeof new_value == "number"))
		{
			let int_value = parseInt(new_value);
			this.updateComplete.then(() =>
			{
				this.fetchComplete.then(() =>
				{
					let options = this.select_options;
					for(let index in options)
					{
						let right = parseInt(options[index].value);

						if((int_value & right) == right)
						{
							expanded_value.push("" + right);
						}
					}
					super.value = expanded_value;
				})
			});
			return;
		}
		super.value = expanded_value;
	}

	get value()
	{
		return super.value;
	}
}

customElements.define("et2-select-dow", Et2SelectDayOfWeek);