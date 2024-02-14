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

		this.selectedNodes = [];
		this.selectedItems = [];
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
		if(this.selectedItems?.length)
        for (const selectedItem of this.selectedItems)
        {
            res.push(selectedItem.id)
        }
        return res
    }

    _optionTemplate(selectOption: TreeItemData): TemplateResult<1> {
        let img: String = selectOption.im0 ?? selectOption.im1 ?? selectOption.im2;
        if (img) {
            //sl-icon images need to be svgs if there is a png try to find the corresponding svg
            img = img.endsWith(".png") ? img.replace(".png", ".svg") : img;
            img = "api/templates/default/images/dhtmlxtree/" + img

        }
        return html`
            <sl-tree-item
               
                    id=${selectOption.id}
                    ?selected=${this.value.includes(selectOption.id)}
                    ?lazy=${selectOption.item?.length === 0 && selectOption.child}

                    @sl-lazy-load=${(event) => {
                        this.handleLazyLoading(selectOption).then((result) => {
                            this.getNode(selectOption.id).item = [...result.item]
                            this.requestUpdate("_selectOptions")
                        })

                    }}
            >
                <sl-icon src="${img ?? nothing}"></sl-icon>

                ${selectOption.text}
                ${(selectOption.item) ? html`${repeat(selectOption.item, this._optionTemplate.bind(this))}` : nothing}
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
                                    this.selectedItems.push(this.getNode(slTreeItem.id));
                                }
                                this.selectedNodes = event.detail.selection;
                                //TODO look at what signature is expected here
                                if(typeof this.onclick == "function")
                                {
                                    this.onclick(event.detail.selection[0].id, this, event.detail.previous)
                                }

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
customElements.define("et2-tree-multiple", Et2MultiselectTree);
customElements.define("et2-tree-cat-multiple", class extends Et2MultiselectTree{});


