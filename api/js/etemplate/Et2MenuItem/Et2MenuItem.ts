import {customElement} from "lit/decorators/custom-element.js";
import {SlMenuItem} from "@shoelace-style/shoelace";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {et2_IInput} from "../et2_core_interfaces";

@customElement('et2-menu-item')
export class Et2MenuItem extends Et2Widget(SlMenuItem) implements et2_IInput
{
	getValue()
	{
		return this.value;
	}

	isDirty()
	{
		return false;
	}

	resetDirty() {}

	isValid() {return true;}
}