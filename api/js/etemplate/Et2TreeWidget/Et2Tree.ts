import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlTree} from "@shoelace-style/shoelace";
import {Et2Link} from "../Et2Link/Et2Link";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";

export class Et2Tree extends Et2widgetWithSelectMixin(SlTree){
 constructor()
 {
	 super();
 }

 _optionTemplate(){}
}

customElements.define("et2-tree", Et2Tree);
const tree = new Et2Tree();

