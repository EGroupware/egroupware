import {SlBadge} from "@shoelace-style/shoelace";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {property} from "lit/decorators/property.js";
import {customElement} from "lit/decorators/custom-element.js";

@customElement("et2-badge")
export class Et2Description extends Et2Widget(SlBadge) implements et2_IDetachedDOM
{

	@property({type: String}) label = "";

	set value(new_value)
	{
		this.innerText = new_value;
	}

	get value()
	{
		return this.innerText;
	}

	getDetachedAttributes(attrs)
	{
		attrs.push("id", "label", "value", "class", "href", "statustext");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data? : any) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}