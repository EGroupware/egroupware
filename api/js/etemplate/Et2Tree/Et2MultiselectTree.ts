import {SlTreeItem} from "@shoelace-style/shoelace";
import {html, nothing, TemplateResult} from "lit";
import {repeat} from "lit/directives/repeat.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Tree, TreeItemData} from "./Et2Tree";


/**
 * @event {{id: String, item:SlTreeItem}} sl-expand emmited when tree item expands
 * //TODO add for other events
 */
export class Et2MultiselectTree extends Et2Tree {
}
customElements.define("et2-tree-multiple", Et2MultiselectTree);
customElements.define("et2-tree-cat-multiple", class extends Et2MultiselectTree{});


