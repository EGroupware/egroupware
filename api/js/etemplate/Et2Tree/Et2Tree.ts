import {SlTreeItem} from "@shoelace-style/shoelace";
import {et2_no_init} from "../et2_core_common";
import {egw} from "../../jsapi/egw_global";
import {find_select_options} from "../Et2Select/FindSelectOptions";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {css, html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
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
import {EgwDragDropShoelaceTree} from "../../egw_action/EgwDragDropShoelaceTree";

export type TreeItemData = {
	focused?: boolean;
	child: Boolean | 1,
	data?: Object,//{sieve:true,...} or {acl:true} or other
	id: string,
	im0: String,
	im1: String,
	im2: String,
	item: TreeItemData[],
	checked?: Boolean,
	nocheckbox: number | Boolean,
	open: 0 | 1,
	parent: String,
	text: String,
	tooltip: String,
	userdata: any[]
}

/**
 * @event {{id: String, item:SlTreeItem}} sl-expand emmited when tree item expands
 * //TODO add for other events
 */
export class Et2Tree extends Et2WidgetWithSelectMixin(LitElement)
{
	/**
	 * Limit server searches to 100 results, matches Link::DEFAULT_NUM_ROWS
	 * @type {number}
	 */
	static RESULT_LIMIT: number = 100;
	//does not work because it would need to be run on the shadow root
	@query("sl-tree-item[selected]") selected: SlTreeItem;
	@property({type: Boolean})
	multiple: Boolean = false;
	@property({type: String})
	leafIcon: String;
	@property({type: String})
	collapsedIcon: String;
	@property({type: String})
	openIcon: String;
	@property({type: Function})
	onclick;// 	description: "JS code which gets executed when clicks on text of a node"


	//onselect and oncheck only appear in multiselectTree
	// @property()
	// onselect // description: "Javascript executed when user selects a node"
	// @property()
	// oncheck // description: "Javascript executed when user checks a node"

	@property({type: Boolean})
	highlighting: Boolean = false   // description: "Add highlighting class on hovered over item, highlighting is disabled by default"
	@property({type: String})
	autoloading: String = ""  //description: "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, getSelectedNode() contains node-id"
	@property()
	onopenstart //description: "Javascript function executed when user opens a node: function(_id, _widget, _hasChildren) returning true to allow opening!"
	@property()
	onopenend   //description: "Javascript function executed when opening a node is finished: function(_id, _widget, _hasChildren)"
	@property({type: String})
	imagePath: String = egw().webserverUrl + "/api/templates/default/images/dhtmlxtree/" //TODO we will need a different path here! maybe just rename the path?
	//     description: "Directory for tree structure images, set on server-side to 'dhtmlx' subdir of templates image-directory"
	@property()
	value = {}

	protected autoloading_url: any;
	// private selectOptions: TreeItemData[] = [];
	@state()
	protected _selectOptions: TreeItemData[]
	@state()
	protected _currentOption: TreeItemData
	@state()
	protected _previousOption: TreeItemData
	@state()
	protected _currentSlTreeItem: SlTreeItem;

	private input: any = null;
	private _actionManager: EgwAction;


	constructor()
	{
		super();
	}

	//Sl-Trees handle their own onClick events
	_handleClick(_ev)
	{
	}

	static get styles()
	{

		return [
			shoelace,
			// @ts-ignore
			...super.styles,
			css`
                :host {
                    --sl-spacing-large: 1rem;
                }

                ::part(expand-button) {
                    padding: 0;
                }
			`,
			css`
                ::part(label):hover {
                    text-decoration: underline;
                }
			`
		]
	}

	static get properties()
	{
		return {
			...super.properties,
			// multiple: {
			//     name: "",
			//     type: Boolean,
			//     default: false,
			//     description: "Allow selecting multiple options"
			// },
			// selectOptions: {
			//     type: "any",
			//     name: "Select options",
			//     default: {},
			//     description: "Used to set the tree options."
			// },
			// onclick: {
			// 	name: "onclick",
			// 	type: "js",
			// 	description: "JS code which gets executed when clicks on text of a node"
			// },
			// onSelect: {
			//     name: "onSelect",
			//     type: "js",
			//     default: et2_no_init,
			//     description: "Javascript executed when user selects a node"
			//},
			// onCheck: {
			//     name: "onCheck",
			//     type: "js",
			//     default: et2_no_init,
			//     description: "Javascript executed when user checks a node"
			// },
			// onOpenStart: {
			//     name: "onOpenStart",
			//     type: "js",
			//     default: et2_no_init,
			//     description: "Javascript function executed when user opens a node: function(_id, _widget, _hasChildren) returning true to allow opening!"
			// },
			// onOpenEnd: {
			//     name: "onOpenEnd",
			//     type: "js",
			//     default: et2_no_init,
			//     description: "Javascript function executed when opening a node is finished: function(_id, _widget, _hasChildren)"
			// },
			// imagePath: {
			//     name: "Image directory",
			//     type: String,
			//     default: egw().webserverUrl + "/api/templates/default/images/dhtmlxtree/",//TODO we will need a different path here! maybe just rename the path?
			//     description: "Directory for tree structure images, set on server-side to 'dhtmlx' subdir of templates image-directory"
			// },
			// value: {
			//     type: "any",
			//     default: {}
			// },
			// actions: {
			// 	name: "Actions array",
			// 	type: "any",
			// 	default: et2_no_init,
			// 	description: "List of egw actions that can be done on the tree.  This includes context menu, drag and drop.  TODO: Link to action documentation"
			// },
			// autoloading: {
			//     name: "Auto loading",
			//     type: String,
			//     default: "",
			//     description: "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, GET parameter selected contains node-id"
			// },
			//only used once ever as "bullet" in admin/.../index.xet
			stdImages: {
				name: "Standard images",
				type: String,
				default: "",
				description: "comma-separated names of icons for a leaf, closed and opened folder (default: leaf.png,folderClosed.png,folderOpen.png), images with extension get loaded from imagePath, just 'image' or 'appname/image' are allowed too"
			},
			//what is this used for only used as "strict in mail subscribe.xet and folder_management.xet
			multiMarking: {
				name: "multi marking",
				type: "any",
				default: false,
				description: "Allow marking multiple nodes, default is false which means disabled multiselection, true or 'strict' activates it and 'strict' makes it strict to only same level marking"
			},
			// highlighting: {
			//     name: "highlighting",
			//     type: Boolean,
			//     default: false,
			//     description: "Add highlighting class on hovered over item, highlighting is disabled by default"
			// },
		}
	};

	private _actions: object

	get actions()
	{
		return this._actions
	}

	/**
	 * Set Actions on the widget
	 *
	 * Each action is defined as an object:
	 *
	 * move: {
	 *      type: "drop",
	 *      acceptedTypes: "mail",
	 *      icon:   "move",
	 *      caption:	"Move to"
	 *      onExecute:      javascript:mail_move"
	 * }
	 *
	 * This will turn the widget into a drop target for "mail" drag types.  When "mail" drag types are dropped,
	 * the global function mail_move(egwAction action, egwActionObject sender) will be called.  The ID of the
	 * dragged "mail" will be in sender.id, some information about the sender will be in sender.context.  The
	 * etemplate2 widget involved can typically be found in action.parent.data.widget, so your handler
	 * can operate in the widget context easily.  The location varies depending on your action though.  It
	 * might be action.parent.parent.data.widget
	 *
	 * To customise how the actions are handled for a particular widget, override _link_actions().  It handles
	 * the more widget-specific parts.
	 *
	 * @param {object} actions {ID: {attributes..}+} map of egw action information
	 * @see api/src/Etemplate/Widget/Nextmatch.php egw_actions() method
	 */
	@property({type: Object})
	set actions(actions: object)
	{
		this._actions = actions
		if (this.id == "" || typeof this.id == "undefined")
		{
			window.egw().debug("warn", "Widget should have an ID if you want actions", this);
			return;
		}

		// Initialize the action manager and add some actions to it
		// Only look 1 level deep
		// @ts-ignore exists from Et2Widget
		var gam = egw_getActionManager(this.egw().appName, true, 1);
		if (typeof this._actionManager != "object")
		{
			// @ts-ignore exists from Et2Widget
			if (gam.getActionById(this.getInstanceManager().uniqueId, 1) !== null)
			{
				// @ts-ignore exists from Et2Widget
				gam = gam.getActionById(this.getInstanceManager().uniqueId, 1);
			}
			if (gam.getActionById(this.id, 1) != null)
			{
				this._actionManager = gam.getActionById(this.id, 1);
			} else
			{
				this._actionManager = gam.addAction("actionManager", this.id);
			}
		}
		// @ts-ignore egw() exists on this
		this._actionManager.updateActions(actions, this.egw().appName);
		// @ts-ignore
		if (this.options.default_execute) this._actionManager.setDefaultExecute(this.options.default_execute);

		// Put a reference to the widget into the action stuff, so we can
		// easily get back to widget context from the action handler
		this._actionManager.data = {widget: this};

	}

	public loadFromXML()
	{
		//if(this.id)
		this._selectOptions = <TreeItemData[]><unknown>find_select_options(this)[1];
		this._currentOption = this._selectOptions[0];

	}

	/**
	 * @deprecated assign to onopenstart
	 * @param _handler
	 */
	public set_onopenstart(_handler: any)
	{
		this.onopenstart = _handler
		this.installHandler("onopenstart", _handler)
	}

	/**
	 * @deprecated assign to onopenend
	 * @param _handler
	 */
	public set_onopenend(_handler: any)
	{
		this.onopenend = _handler
		this.installHandler('onopenend', _handler);
	}


	/**
	 * @deprecated assign to onclick
	 * @param _handler
	 */
	public set_onclick(_handler: Function)
	{
		this.installHandler('onclick', _handler);
	}

	/**
	 * @deprecated assign to onselect
	 * @param _handler
	 */
	public set_onselect(_handler: any)
	{
		this.onselect = _handler;
	}


	public getSelectedItem(): TreeItemData
	{
		return this._currentOption
	}

	/**
	 * getValue, retrieves the Id of the selected Item
	 * @return string or object or null
	 */
	getValue(): string | string[]
	{
		return this._currentOption ? this._currentOption.id : null
	}

	/**
	 * getSelectedNode, retrieves the full node of the selected Item
	 * @return {SlTreeItem} full SlTreeItem
	 */
	getSelectedNode(): SlTreeItem
	{
		return this._currentSlTreeItem
	}

	getDomNode(_id): SlTreeItem
	{
		return this.shadowRoot.querySelector("sl-tree-item[id='" + _id + "'");
	}


	/**
	 * return the Item with given _id, was called getDomNode(_id) in dhtmlxTree
	 * @param _id
	 */
	public getNode(_id: string): TreeItemData
	{

		return this._search(_id, this._selectOptions)
	}

	/**
	 * set the text of item with given id to new label
	 * @param _id
	 * @param _label
	 * @param _tooltip
	 */
	setLabel(_id, _label, _tooltip?)
	{
		let tooltip = _tooltip || (this.getNode(_id) && this.getNode(_id).tooltip ? this.getNode(_id).tooltip : "");
		let i = this.getNode(_id)
		i.tooltip = tooltip
		i.text = _label
	}

	/**
	 * getLabel, gets the Label of of an item by id
	 * @param _id ID of the node
	 * @return _label
	 */
	getLabel(_id)
	{
		return this.getNode(_id)?.text;
	}

	/**
	 * getSelectedLabel, retrieves the Label of the selected Item
	 * @return string or null
	 */
	getSelectedLabel()
	{
		return this.getSelectedItem()?.text
	}

	/**
	 * deleteItem, deletes an item by id
	 * @param _id ID of the node
	 * @param _selectParent select the parent node true/false TODO unused atm
	 * @return void
	 */
	deleteItem(_id, _selectParent)
	{
		this._deleteItem(_id, this._selectOptions)
		// Update action
		// since the action ID has to = this.id, getObjectById() won't work
		let treeObj = (<EgwActionObject><unknown>egw_getAppObjectManager(false)).getObjectById(this.id);
		for (let i = 0; i < treeObj.children.length; i++)
		{
			if (treeObj.children[i].id == _id)
			{
				treeObj.children.splice(i, 1);
			}
		}
		this.requestUpdate();
	}

	/**
	 * Updates a leaf of the tree by requesting new information from the server using the
	 * autoloading attribute.
	 *
	 * @param {string} _id ID of the node
	 * @param {Object} [data] If provided, the item is refreshed directly  with
	 *    the provided data instead of asking the server
	 * @return void
	 */
	refreshItem(_id, data)
	{
		if (typeof data != "undefined" && data != null)
		{
			//TODO currently always ask the sever
			//data seems never to be used
			this.refreshItem(_id, null)
		} else
		{
			let item = this.getNode(_id)
			this.handleLazyLoading(item).then((result) => {
				item.item = [...result.item]
				this.requestUpdate("_selectOptions")
			})
		}
	}

	/**
	 * Does nothing
	 * @param _id
	 * @param _style
	 */
	setStyle(_id, _style)
	{
		var temp = this.getDomNode(_id);
		if (!temp) return 0;
		if (!temp.style.cssText)
			temp.setAttribute("style", _style);
		else
			temp.style.cssText = temp.style.cssText + ";" + _style;
	}

	/**
	 * getTreeNodeOpenItems TODO
	 *
	 * @param {string} _nodeID the nodeID where to start from (initial node)
	 * @param {string} mode the mode to run in: "forced" fakes the initial node openState to be open
	 * @return {object} structured array of node ids: array(message-ids)
	 */
	getTreeNodeOpenItems(_nodeID: string, mode?: string)
	{

	}

	/**
	 * @param _id
	 * @param _newItemId
	 * @param _label
	 */
	public renameItem(_id, _newItemId, _label)
	{
		this.getNode(_id).id = _newItemId

		// Update action
		// since the action ID has to = this.id, getObjectById() won't work
		let treeObj: EgwActionObject = egw_getAppObjectManager(false).getObjectById(this.id);
		for (const actionObject of treeObj.children)
		{
			if (actionObject.id == _id)
			{
				actionObject.id = _newItemId;
				if (actionObject.iface)
				{
					actionObject.iface.id = _newItemId
				}
				break
			}

		}

		if (typeof _label != 'undefined') this.setLabel(_newItemId, _label);
		this.requestUpdate()
	}

	public focusItem(_id)
	{
		let item = this.getNode(_id)
		item.focused = true
	}

	/**
	 * hasChildren
	 *
	 * @param _id ID of the node
	 * @return the number of childelements
	 */
	hasChildren(_id)
	{
		return this.getNode(_id).item?.length;
	}

	/**
	 * reSelectItem, reselects an item by id
	 * @param _id ID of the node
	 */
	reSelectItem(_id)
	{
		this._previousOption = this._currentOption
		this._currentOption = this.getNode(_id);
		const node: SlTreeItem = this.getDomNode(_id)
		if (node)
		{
			this._currentSlTreeItem = node;
			node.selected = true
		}
	}

	getUserData(_nodeId, _name)
	{
		return this.getNode(_nodeId)?.userdata?.find(elem => {
			elem.name === _name
		})?.content
	}

	//this.selectOptions = find_select_options(this)[1];
	_optionTemplate(selectOption: TreeItemData): TemplateResult<1>
	{
		this._currentOption = selectOption
		/*
		if collapsed .. opended? leaf?
		 */
		let img: String = selectOption.im0 ?? selectOption.im1 ?? selectOption.im2;
		if (img)
		{
			//sl-icon images need to be svgs if there is a png try to find the corresponding svg
			img = img.endsWith(".png") ? img.replace(".png", ".svg") : img;
			img = "api/templates/default/images/dhtmlxtree/" + img

		}


		return html`
            <sl-tree-item
                    id=${selectOption.id}
                    ?expanded=${(this.calculateExpandState(selectOption))}
                    ?lazy=${this._currentOption.item?.length === 0 && this._currentOption.child}
                    ?focused=${selectOption.focused || nothing}
                    @sl-lazy-load=${(event) => {
                        this.handleLazyLoading(selectOption).then((result) => {
                            this.getNode(selectOption.id).item = [...result.item]
                            this.requestUpdate("_selectOptions")
                        })

                    }}
            >
                <sl-icon src="${img ?? nothing}"></sl-icon>

                ${this._currentOption.text}
                ${this._currentOption.item ? repeat(this._currentOption.item, this._optionTemplate.bind(this)) : ""}
            </sl-tree-item>`
	}


	public render(): unknown
	{
		return html`
            <sl-tree
                    @sl-selection-change=${
                            (event: any) => {
                                this._previousOption = this._currentOption
                                this._currentOption = this.getNode(event.detail.selection[0].id);
                                event.detail.previous = this._previousOption.id;
                                this._currentSlTreeItem = event.detail.selection[0];

                                this.onclick(event.detail.selection[0].id, this, event.detail.previous)
                            }
                    }
                    @sl-expand=${
                            (event) => {
                                event.detail.id = event.target.id
                                event.detail.item = event.target
                                this.onopenstart(event.detail.id, this, 1)
                            }
                    }
                    @sl-after-expand=${
                            (event) => {
                                event.detail.id = event.target.id
                                event.detail.item = event.target

                                this.onopenend(event.detail.id, this, -1)

                            }
                    }

            >
                ${repeat(this._selectOptions, this._optionTemplate.bind(this))}
            </sl-tree>
		`;
	}

	handleLazyLoading(_item: TreeItemData)
	{
		let requestLink = egw().link(egw().ajaxUrl(egw().decodePath(this.autoloading_url)),
			{
				id: _item.id
			})

		let result: Promise<TreeItemData> = egw().request(requestLink, [])


		return result
			.then((results) => {
				_item = results;
				return results;
			});
	}

	/**
	 *
	 *
	 */
	_link_actions(actions)
	{
		// Get the top level element for the tree
		let objectManager = egw_getAppObjectManager(true);
		let widget_object = objectManager.getObjectById(this.id);
		if (widget_object == null)
		{
			// Add a new container to the object manager which will hold the widget
			// objects
			widget_object = objectManager.insertObject(false, new EgwActionObject(
				//@ts-ignore
				this.id, objectManager, (new et2_action_object_impl(this, this)).getAOI(),
				this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager
			));
		} else
		{
			// @ts-ignore
			widget_object.setAOI((new et2_action_object_impl(this, this)).getAOI());
		}

		// Delete all old objects
		widget_object.clear();
		widget_object.unregisterActions();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);
		//Drop target enabeling
		if (typeof this._selectOptions != 'undefined')
		{
			let self: Et2Tree = this
			// Iterate over the options (leaves) and add action to each one
			let apply_actions = function (treeObj: EgwActionObject, option: TreeItemData) {
				// Add a new action object to the object manager
				// @ts-ignore
				let obj: EgwActionObject = treeObj.addObject((typeof option.id == 'number' ? String(option.id) : option.id), new EgwDragDropShoelaceTree(self, option.id));
				obj.updateActionLinks(action_links);

				if (option.item && option.item.length > 0)
				{
					for (let i = 0; i < option.item.length; i++)
					{
						apply_actions.call(this, treeObj, option.item[i]);
					}
				}
			};
			for (const selectOption of this._selectOptions)
			{

				apply_actions.call(this, widget_object, selectOption)
			}
		}


		widget_object.updateActionLinks(action_links);
	}

	/**
	 * Get all action-links / id's of 1.-level actions from a given action object
	 *
	 * This can be overwritten to not allow all actions, by not returning them here.
	 *
	 * @param actions
	 * @returns {Array}
	 */
	_get_action_links(actions)
	{
		var action_links = [];
		for (var i in actions)
		{
			var action = actions[i];
			action_links.push(typeof action.id != 'undefined' ? action.id : i);
		}
		return action_links;
	}

	protected updated(_changedProperties: PropertyValues)
	{
		this._link_actions(this.actions)
		super.updated(_changedProperties);
	}

	private _search(_id: string, data: TreeItemData[]): TreeItemData
	{
		let res: TreeItemData = null
		for (const value of data)
		{
			if (value.id === _id)
			{
				res = value
				return res
			} else if (_id.startsWith(value.id))
			{
				res = this._search(_id, value.item)
			}
		}
		return res
	}

	private calculateExpandState = (selectOption: TreeItemData) => {
		if (selectOption.id.endsWith("INBOX") || selectOption.id == window.egw.preference("ActiveProfileID", "mail"))
		{
			return true
		}
		if (selectOption.open)
		{
			return true
		}
		if (
			 this._selectOptions[0] == selectOption &&
			(this._selectOptions.find((selectOption) => {
					return selectOption.open
				}) == undefined
			)
			)
		{
			return true //open the first item, if no item is opened
		}

		return false
			;
	}

	private _deleteItem(_id, list)
	{
		for (let i = 0; i < list.length; i++)
		{
			const value = list[i];
			if (value.id === _id)
			{
				list.splice(i, 1)
			} else if (_id.startsWith(value.id))
			{
				this._deleteItem(_id, value.item)
			}
		}
	}

	private installHandler(_name: String, _handler: Function)
	{
		if (this.input == null) this.createTree();
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

	private createTree()
	{
		// widget.input = document.querySelector("et2-tree");
		// // Allow controlling icon size by CSS
		// widget.input.def_img_x = "";
		// widget.input.def_img_y = "";
		//
		// // to allow "," in value, eg. folder-names, IF value is specified as array
		// widget.input.dlmtr = ':}-*(';
		// @ts-ignore from static get properties
		if (this.autoloading)
		{
			// @ts-ignore from static get properties
			let url = this.autoloading;

			if (url.charAt(0) != '/' && url.substr(0, 4) != 'http')
			{
				url = '/json.php?menuaction=' + url;
			}
			this.autoloading_url = url;
		}
	}

}

customElements.define("et2-tree", Et2Tree);
customElements.define("et2-tree-cat", class extends Et2Tree
{
});


