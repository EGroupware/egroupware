import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin} from "../StaticOptions";

export class Et2SelectBitwise extends Et2StaticSelectMixin(Et2Select)
{
	set value(new_value)
	{
		/* beforeSendToClient does this, we don't want it twice
		let oldValue = this._value;
		let expanded_value = [];
		let options = this.select_options;
		for(let index in options)
		{
			let right = parseInt(options[index].value);
			if(!!(new_value & right))
			{
				expanded_value.push(right);
			}
		}
		*/
		super.value = new_value;
	}
}

customElements.define("et2-select-bitwise", Et2SelectBitwise);