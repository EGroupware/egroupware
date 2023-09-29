import {Et2SelectNumber} from "./Et2SelectNumber";

export class Et2SelectPercent extends Et2SelectNumber
{
	constructor()
	{
		super();
		this.min = 0;
		this.max = 100;
		this.interval = 10;
		this.suffix = "%%";
	}
}

customElements.define("et2-select-percent", Et2SelectPercent);