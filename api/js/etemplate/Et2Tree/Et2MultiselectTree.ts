import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlTree, SlTreeItem} from "@shoelace-style/shoelace";
import {Et2Link} from "../Et2Link/Et2Link";
import {et2_no_init} from "../et2_core_common";
import {egw, framework} from "../../jsapi/egw_global";
import {SelectOption, find_select_options, cleanSelectOptions} from "../Et2Select/FindSelectOptions";
import {egwIsMobile} from "../../egw_action/egw_action_common";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {LitElement, css, TemplateResult, html, PropertyDeclaration, nothing} from "lit";
import {repeat} from "lit/directives/repeat.js";
import shoelace from "../Styles/shoelace";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {egw_getActionManager, egw_getAppObjectManager, egwActionObject} from "../../egw_action/egw_action";
import {et2_action_object_impl} from "../et2_core_DOMWidget";
import {EgwActionObject} from "../../egw_action/EgwActionObject";
import {object} from "prop-types";
import {EgwAction} from "../../egw_action/EgwAction";
import {query} from "@lion/core";
import {Et2Tree, TreeItemData} from "./Et2Tree";


/**
 * @event {{id: String, item:SlTreeItem}} sl-expand emmited when tree item expands
 * //TODO add for other events
 */
export class Et2MultiselectTree extends Et2Tree {


    @property()
    onselect // description: "Javascript executed when user selects a node"

    //This is never used as far as I can tell mailapp.folgerMgmt_onCheck should select all sub folders --> Sl-Tree offers this functionality on default
    @property()
    oncheck // description: "Javascript executed when user checks a node"

    //only used in calendar_sidebox.xet
    @property({type: Function})
    onchange;// 	description: "JS code which gets executed when selection changes"

    @state()
    selectedNodes: SlTreeItem[]
    @state()
    selectedItems: TreeItemData[]

    constructor() {
        super();
        this.multiple = true;
    }

    public set_onchange(_handler: any)
    {
        this.onchange = _handler
    }

    /**
     * getValue, retrieves the Ids of the selected Items
     * @return string or object or null
     */
    getValue()
    {
        let res:string[] = []
        for (const selectedItem of this.selectedItems)
        {
            res.push(selectedItem.id)
        }
        return res
    }

    _optionTemplate(selectOption: TreeItemData): TemplateResult<1> {
        this._currentOption = selectOption
        let img: String = selectOption.im0 ?? selectOption.im1 ?? selectOption.im2;
        if (img) {
            //sl-icon images need to be svgs if there is a png try to find the corresponding svg
            img = img.endsWith(".png") ? img.replace(".png", ".svg") : img;
            img = "api/templates/default/images/dhtmlxtree/" + img

        }
        return html`
            <sl-tree-item
               
                    id=${selectOption.id}
                    ?lazy=${this._currentOption.item?.length === 0 && this._currentOption.child}

                    @sl-lazy-load=${(event) => {
                        this.handleLazyLoading(selectOption).then((result) => {
                            this.getItem(selectOption.id).item = [...result.item]
                            this.requestUpdate("_selectOptions")
                        })

                    }}
            >
                <sl-icon src="${img ?? nothing}"></sl-icon>

                ${this._currentOption.text}
                ${repeat(this._currentOption.item, this._optionTemplate.bind(this))}
            </sl-tree-item>`
    }

    public render(): unknown {
        return html`
            <sl-tree
                    selection="${this.multiple?"multiple":nothing}"
                    @sl-selection-change=${
                            (event: any) => {
                                //TODO inefficient
                                this.selectedItems = []
                                for (const slTreeItem of <SlTreeItem[]>event.detail.selection) {
                                    this.selectedItems.push(this.getItem(slTreeItem.id));
                                }
                                this.selectedNodes = event.detail.selection;
                                //TODO look at what signature is expected here
                                this.onchange(event,this)


                            }
                    }
                    @sl-expand=${
                            (event) => {
                                event.detail.id = event.target.id
                                event.detail.item = event.target
                            }
                    }
                    @sl-after-expand=${
                            (event) => {
                                event.detail.id = event.target.id
                                event.detail.item = event.target

                            }
                    }
            >
                ${repeat(this._selectOptions, this._optionTemplate.bind(this))}
            </sl-tree>
        `;
    }

}
// @ts-ignore TypeScript says there's something wrong with types
customElements.define("et2-tree-multiple", Et2MultiselectTree);
// @ts-ignore TypeScript says there's something wrong with types
customElements.define("et2-tree-cat-multiple", class extends Et2MultiselectTree{});


