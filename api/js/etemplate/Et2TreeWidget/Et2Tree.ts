import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlTree} from "@shoelace-style/shoelace";
import {Et2Link} from "../Et2Link/Et2Link";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {et2_no_init} from "../et2_core_common";
import {egw, framework} from "../../jsapi/egw_global";
import {SelectOption, find_select_options, cleanSelectOptions} from "../Et2Select/FindSelectOptions";
import {html, TemplateResult} from "@lion/core";
import {egwIsMobile} from "../../egw_action/egw_action_common";

export type TreeItem = {
    child: Boolean | 1,
    data?: Object,//{sieve:true,...} or {acl:true} or other
    id: String,
    im0: String,
    im1: String,
    im2: String,
    item: TreeItem[],
    checked?: Boolean,
    nocheckbox: number | Boolean,
    open: 0 | 1,
    parent: String,
    text: String,
    tooltip: String
}


export class Et2Tree extends Et2widgetWithSelectMixin(SlTree) {
    private input: any = null;
    private div: JQuery;
    private autoloading_url: any;
    private selectOptions: TreeItem[];

    constructor() {
        super();
    }

    static get properties() {
        return {
            ...super.properties,
            multiple: {
                name: "",
                type: Boolean,
                default: false,
                description: "Allow selecting multiple options"
            },
            selectOptions: {
                type: "any",
                name: "Select options",
                default: {},
                description: "Used to set the tree options."
            },
            onClick: {
                name: "onClick",
                type: "js",
                description: "JS code which gets executed when clicks on text of a node"
            },
            onSelect: {
                name: "onSelect",
                type: "js",
                default: et2_no_init,
                description: "Javascript executed when user selects a node"
            },
            onCheck: {
                name: "onCheck",
                type: "js",
                default: et2_no_init,
                description: "Javascript executed when user checks a node"
            },
            // TODO do this : --> onChange event is mapped depending on multiple to onCheck or onSelect
            onOpenStart: {
                name: "onOpenStart",
                type: "js",
                default: et2_no_init,
                description: "Javascript function executed when user opens a node: function(_id, _widget, _hasChildren) returning true to allow opening!"
            },
            onOpenEnd: {
                name: "onOpenEnd",
                type: "js",
                default: et2_no_init,
                description: "Javascript function executed when opening a node is finished: function(_id, _widget, _hasChildren)"
            },
            imagePath: {
                name: "Image directory",
                type: String,
                default: egw().webserverUrl + "/api/templates/default/images/dhtmlxtree/",//TODO we will need a different path here! maybe just rename the path?
                description: "Directory for tree structure images, set on server-side to 'dhtmlx' subdir of templates image-directory"
            },
            value: {
                type: "any",
                default: {}
            },
            actions: {
                name: "Actions array",
                type: "any",
                default: et2_no_init,
                description: "List of egw actions that can be done on the tree.  This includes context menu, drag and drop.  TODO: Link to action documentation"
            },
            autoLoading: {
                name: "Auto loading",
                type: String,
                default: "",
                description: "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, GET parameter selected contains node-id"
            },
            stdImages: {
                name: "Standard images",
                type: String,
                default: "",
                description: "comma-separated names of icons for a leaf, closed and opened folder (default: leaf.png,folderClosed.png,folderOpen.png), images with extension get loaded from imagePath, just 'image' or 'appname/image' are allowed too"
            },
            multiMarking: {
                name: "multi marking",
                type: "any",
                default: false,
                description: "Allow marking multiple nodes, default is false which means disabled multiselection, true or 'strict' activates it and 'strict' makes it strict to only same level marking"
            },
            highlighting: {
                name: "highlighting",
                type: Boolean,
                default: false,
                description: "Add highlighting class on hovered over item, highlighting is disabled by default"
            },
        }
    };

    public set onOpenStart(_handler: Function) {
        this.installHandler("onOpenStart", _handler)
    }

    public set onChange(_handler: Function) {
        this.installHandler("onChange", _handler)
    }

    public set onClick(_handler: Function) {
        this.installHandler("onClick", _handler)
    }

    public set onSelect(_handler: Function) {
        this.installHandler("onSelect", _handler)
    }

    public set onOpenEnd(_handler: Function) {
        this.installHandler("onOpenEnd", _handler)
    }

    _optionTemplate() {
        // @ts-ignore
        this.selectOptions= find_select_options(this)[1];
        //slot = expanded/collapsed instead of expand/collapse like it is in documentation
        let result: TemplateResult<1> = html``
        for (const selectOption of this.selectOptions) {
            result = html`${result}
            <sl-tree-item>
                ${this.recursivelyAddChildren(selectOption)}
            </sl-tree-item>`
        }
        const h = html`${result}`
        return h
    }

    /**
     * @deprecated assign to onOpenStart
     * @param _handler
     */
    public set_onopenstart(_handler: Function) {
        this.installHandler("onOpenStart", _handler)
    }

    /**
     * @deprecated assign to onChange
     * @param _handler
     */
    public set_onchange(_handler: Function) {
        this.installHandler('onchange', _handler);
    }

    /**
     * @deprecated assign to onClick
     * @param _handler
     */
    public set_onclick(_handler: Function) {
        this.installHandler('onclick', _handler);
    }

    /**
     * @deprecated assign to onSelect
     * @param _handler
     */
    public set_onselect(_handler: Function) {
        this.installHandler('onselect', _handler);
    }

    /**
     * @deprecated assign to onOpenEnd
     * @param _handler
     */
    public set_onopenend(_handler: Function) {
        this.installHandler('onOpenEnd', _handler);
    }

    private recursivelyAddChildren(item: any): TemplateResult<1> {
        let img:String =item.im0??item.im1??item.im2;
        let attributes = ""
        let res: TemplateResult<1> = html`${item.text}`;
        if(img){
            img = "api/templates/default/images/dhtmlxtree/"+img
            //sl-icon images need to be svgs if there is a png try to find the corresponding svg
            if(img.endsWith(".png"))img = img.replace(".png",".svg");
            res = html`<sl-icon src=${img}></sl-icon>${res}`
        }
        if (item.item?.length > 0) // there are children available
        {
            for (const subItem of item.item) {
                res = html`
                    ${res}
                    <sl-tree-item lazy> ${this.recursivelyAddChildren(subItem)}</sl-tree-item>`
            }
        // }else if(item.child === 1){
        //     res = html``
            //     }
        }
        return res;
    }

    private installHandler(_name: String, _handler: Function) {
        if (this.input == null) this.createTree(this);
        // automatic convert onChange event to oncheck or onSelect depending on multiple is used or not
        // if (_name == "onchange") {
        //     _name = this.options.multiple ? "oncheck" : "onselect"
        // }
        // let handler = _handler;
        // let widget = this;
        // this.input.attachEvent(_name, function(_id){
        //     let args = jQuery.makeArray(arguments);
        //     // splice in widget as 2. parameter, 1. is new node-id, now 3. is old node id
        //     args.splice(1, 0, widget);
        //     // try to close mobile sidemenu after clicking on node
        //     if (egwIsMobile() && typeof args[2] == 'string') framework.toggleMenu('on');
        //     return handler.apply(this, args);
        // });
    }

    private createTree(widget: this) {
        widget.input = document.querySelector("et2-tree");
        // Allow controlling icon size by CSS
        widget.input.def_img_x = "";
        widget.input.def_img_y = "";

        // to allow "," in value, eg. folder-names, IF value is specified as array
        widget.input.dlmtr = ':}-*(';
    }
}

customElements.define("et2-tree", Et2Tree);
const tree = new Et2Tree();

