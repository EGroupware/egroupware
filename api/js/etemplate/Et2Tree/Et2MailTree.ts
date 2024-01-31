// import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
// import {SlTree, SlTreeItem} from "@shoelace-style/shoelace";
// import {Et2Link} from "../Et2Link/Et2Link";
// import {et2_no_init} from "../et2_core_common";
// import {egw, framework} from "../../jsapi/egw_global";
// import {SelectOption, find_select_options, cleanSelectOptions} from "../Et2Select/FindSelectOptions";
// import {egwIsMobile} from "../../egw_action/egw_action_common";
// import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
// import {LitElement, css, TemplateResult, html, PropertyDeclaration, nothing} from "lit";
// import {repeat} from "lit/directives/repeat.js";
// import shoelace from "../Styles/shoelace";
// import {property} from "lit/decorators/property.js";
// import {state} from "lit/decorators/state.js";
// import {egw_getActionManager, egw_getAppObjectManager, egwActionObject} from "../../egw_action/egw_action";
// import {et2_action_object_impl} from "../et2_core_DOMWidget";
// import {EgwActionObject} from "../../egw_action/EgwActionObject";
// import {object} from "prop-types";
// import {EgwAction} from "../../egw_action/EgwAction";
// import {query} from "@lion/core";
// import {Et2Tree, TreeItemData} from "./Et2Tree";
//
//
// /**
//  * @event {{id: String, item:SlTreeItem}} sl-expand emmited when tree item expands
//  * //TODO add for other events
//  */
// export class Et2MailTree extends Et2Tree{
//
//
//     constructor() {
//         super();
//     }
//     _optionTemplate(selectOption: TreeItemData): TemplateResult<1> {
//         this._currentOption = selectOption
//         //needs locig to chose which im to use
//         /*
//         if collapsed?
//          */
//         let img: String = selectOption.im0 ?? selectOption.im1 ?? selectOption.im2;
//         if (img) {
//             //sl-icon images need to be svgs if there is a png try to find the corresponding svg
//             img = img.endsWith(".png") ? img.replace(".png", ".svg") : img;
//             img = "api/templates/default/images/dhtmlxtree/" + img
//
//         }
//         return html`
//             <sl-tree-item
//                     id=${selectOption.id}
//                     ?expanded=${selectOption.id.includes("INBOX") || selectOption.id == window.egw.preference("ActiveProfileID", "mail")}
//                     ?lazy=${this._currentOption.item.length === 0 && this._currentOption.child}
//                     @sl-lazy-load=${(event) => {
//             this.handleLazyLoading(selectOption).then((result) => {
//                 this.getItem(selectOption.id).item = [...result.item]
//                 this.requestUpdate("_selectOptions")
//             })
//
//         }}
//             >
//                 <sl-icon src="${img ?? nothing}"></sl-icon>
//
//                 ${this._currentOption.text}
//                 ${repeat(this._currentOption.item, this._optionTemplate.bind(this))}
//             </sl-tree-item>`
//     }
//
//     public render(): unknown {
//         return html`
//             <sl-tree
//                     @sl-selection-change=${
//             (event: any) => {
//                 this._previousOption = this._currentOption
//                 this._currentOption = this.getItem(event.detail.selection[0].id);
//                 event.detail.previous = this._previousOption.id;
//                 this._currentSlTreeItem = event.detail.selection[0];
//
//
//             }
//         }
//                     @sl-expand=${
//             (event) => {
//                 event.detail.id = event.target.id
//                 event.detail.item = event.target
//             }
//         }
//                     @sl-after-expand=${
//             (event) => {
//                 event.detail.id = event.target.id
//                 event.detail.item = event.target
//
//             }
//         }
//             >
//                 ${repeat(this._selectOptions, this._optionTemplate.bind(this))}
//             </sl-tree>
//         `;
//     }
//
// }
//
// //customElements.define("et2-mail-tree", Et2MailTree);
//
//
