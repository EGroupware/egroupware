import {SlTreeItem} from "@shoelace-style/shoelace";
import {egw} from "../../jsapi/egw_global";
import {find_select_options, SelectOption} from "../Et2Select/FindSelectOptions";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {css, html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {repeat} from "lit/directives/repeat.js";
import shoelace from "../Styles/shoelace";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {egw_getActionManager, egw_getAppObjectManager} from "../../egw_action/egw_action";
import {et2_action_object_impl} from "../et2_core_DOMWidget";
import {EgwActionObject} from "../../egw_action/EgwActionObject";
import {EgwAction} from "../../egw_action/EgwAction";
import {EgwDragDropShoelaceTree} from "../../egw_action/EgwDragDropShoelaceTree";
import {FindActionTarget} from "../FindActionTarget";
import {EGW_AI_DRAG_ENTER, EGW_AI_DRAG_OUT} from "../../egw_action/egw_action_constants";

export type TreeItemData = SelectOption & {
	focused?: boolean;
	// Has children, but they may not be provided in item
	child: Boolean | 1,
	data?: Object,//{sieve:true,...} or {acl:true} or other
	//this is coming from SelectOption
	value: string,
	im0: String,
	im1: String,
	im2: String,
	// Child items
	children: TreeItemData[],
	checked?: Boolean,
	nocheckbox: number | Boolean,
	open: 0 | 1,
	parent: String,
	text: String,
	tooltip: String,
	userdata: any[]
	//here we can store the number of unread messages, if there are any
	badge?: string;
}

/**
 * checks if the event has an Element in its composedPath that satisfies the Tag, className or both
 * @param _ev
 * @param tag
 * @param className
 * @returns true iff tag and classname are satisfied on the same Element somewhere in the composedPath and false otherwise
 */
export const composedPathContains = (_ev: any, tag?: string, className?: string) => {

	// Tag and classname is given
	// check if one element has given tag with given class
	if(tag && className)
	{
		return _ev.composedPath().some((el) => {
			return el?.classList?.contains(className) && el?.tagName?.toLowerCase() === tag.toLowerCase()
		})

	}
	// only classname is given
	// check if one element has given class
	if(className && !tag)
		return _ev.composedPath().some((el) => {
			return el?.classList?.contains(className)
		})
	// only tag is given
	// check if one element has given tag
	if(tag && !className)
		return _ev.composedPath().some((el) => {
			return el?.tagName?.toLowerCase() === tag.toLowerCase()
		})
	return false
}

/**
 * @event {{id: String, item:SlTreeItem}} sl-expand emmited when tree item expands
 * //TODO add for other events
 * @since 23.1.x
 */
export class Et2Tree extends Et2WidgetWithSelectMixin(LitElement) implements FindActionTarget
{
	//does not work because it would need to be run on the shadow root
	//@query("sl-tree-item[selected]") selected: SlTreeItem;

	/**
	 * get the first selected node using attributes on the shadow root elements
	 */
	private get selected(){
		return this.shadowRoot.querySelector("sl-tree-item[selected]")
	}
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
	/**
	 * If true, only leafs (NOT folders) are selectable
	 */
	@property({type:Boolean})
	leafOnly = false


	//onselect and oncheck only appear in multiselectTree
	// @property()
	// onselect // description: "Javascript executed when user selects a node"
	// @property()
	// oncheck // description: "Javascript executed when user checks a node"

	@property({type: Boolean})
	highlighting: Boolean = false   // description: "Add highlighting class on hovered over item, highlighting is disabled by default"
	@property({type: String})
	autoloading: string = ""  //description: "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, getSelectedNode() contains node-id"
	@property({type: Function})
	onopenstart //description: "Javascript function executed when user opens a node: function(_id, _widget, _hasChildren) returning true to allow opening!"
	@property({type: Function})
	onopenend   //description: "Javascript function executed when opening a node is finished: function(_id, _widget, _hasChildren)"
	@property({type: String})
	imagePath: string = egw().webserverUrl + "/api/templates/default/images/dhtmlxtree/" //TODO we will need a different path here! maybe just rename the path?
	//     description: "Directory for tree structure images, set on server-side to 'dhtmlx' subdir of templates image-directory"
	@property()
	value = []

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

	@state()
	selectedNodes: SlTreeItem[]

	private _actionManager: EgwAction;
	widget_object: EgwActionObject;

	private get _tree() { return this.shadowRoot.querySelector('sl-tree') ?? null};


	constructor()
	{
		super();
		this._selectOptions = [];

		this._optionTemplate = this._optionTemplate.bind(this);

		this.selectedNodes = [];
	}

	private _initCurrent()
	{
		this._currentSlTreeItem = this.selected;
		this._currentOption = this._currentSlTreeItem?this.getNode(this._currentSlTreeItem?.id):null
	}
	firstUpdated()
	{
		if (this.autoloading)
		{
			// @ts-ignore from static get properties
			let url = this.autoloading;

			if (url.charAt(0) != '/' && url.substr(0, 4) != 'http')
			{
				url = '/json.php?menuaction=' + url;
			}
			this.autoloading = url;
		}

		// Check if top level should be autoloaded
		if(this.autoloading && !this._selectOptions?.length)
		{
			this.handleLazyLoading({item: this._selectOptions}).then((results) =>
			{
				this._selectOptions = results?.item ?? [];
				this._initCurrent()
				this.requestUpdate("_selectOptions");
				this.updateComplete.then((value) => {
					if (value)
					{
						this._link_actions(this.actions)
					}

				})
			})
		}
		if (this._selectOptions?.length) this._initCurrent()

		// Actions can't be initialized without being connected to InstanceManager
		this._initActions();
		this._link_actions(this.actions)
	}

	protected updated(_changedProperties: PropertyValues)
	{
		super.updated(_changedProperties);
	}

	//Sl-Trees handle their own onClick events
	_handleClick(_ev)
	{
		// check if not expand icon (> or v) was clicked, we have an onclick handler and a string value
		if (!(_ev.composedPath()[0].tagName === 'svg' &&
				(_ev.composedPath()[0].classList.contains('bi-chevron-right') ||
					_ev.composedPath()[0].classList.contains('bi-chevron-down')
				)
			) &&
			typeof this.onclick === "function" && typeof _ev.target.value === "string")
		{
			this.onclick(_ev.target.value, this, _ev.target.value)
		}
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
					display: block;
                }


				::part(expand-button) {
					rotate: none;
					padding: 0 var(--sl-spacing-small);
				}

				/* Stop icon from shrinking if there's not enough space */
                /* increase font size by 2px this was previously done in pixelegg css but document css can not reach shadow root*/

                sl-tree-item et2-image {
                    flex: 0 0 1em;
                    font-size: calc(100% + 2px);
					line-height: calc(100% - 2px);
					padding-right: .4em;
					width: 1em;
					height: 1em;
					display: inline-block;
                }

				::part(label) {
					overflow: hidden;
					flex: 1 1 auto;
				}

				::part(label):hover {
					text-decoration: underline;
				}

				.tree-item__label {
					overflow: hidden;
					white-space: nowrap;
					text-overflow: ellipsis;
				}

				sl-tree-item.drop-hover {
					background-color: var(--highlight-background-color);
				}

				/*Mail specific style TODO move it out of the component*/
                sl-tree-item.unread > .tree-item__label {
                        font-weight: bold;
                    }
				
                sl-tree-item.mailAccount > .tree-item__label {
                    font-weight: bold;
                }
				sl-tree > sl-tree-item:nth-of-type(n+2){
					margin-top: 2px;
				}
				/* End Mail specific style*/

                sl-tree-item.drop-hover sl-tree-item {
                    background-color: var(--sl-color-neutral-0);
                }

                /*TODO color of selected marker in front should be #006699 same as border top color*/

                sl-badge::part(base) {

                    background-color: var(--badge-color); /* This is the same color as app color mail */
                    font-size: 1em;
                    font-weight: 900;
                    position: absolute;
                    top: 0;
                    right: 0.5em;
                    line-height: 60%;
                }


                @media only screen and (max-device-width: 768px) {
                    :host {
                        --sl-font-size-medium: 1.2rem;
                    }

                    sl-tree-item {
                        padding: 0.1em;
                    }


                }
            `


		]
	}

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
			if(this.isConnected)
			{
				window.egw().debug("warn", "Widget should have an ID if you want actions", this);
			}
			// No id because we're not done yet, try again later
			return;
		}

		if(this.isConnected)
		{
			this._initActions();
		}
	}

	public loadFromXML()
	{
		let new_options = [];

		if(this.id)
		{
			new_options = <TreeItemData[]><unknown>find_select_options(this)[1];
		}
		if(new_options?.length)
		{
			this._selectOptions = new_options;
		}
	}

	/**
	 * Initialize the action manager and add some actions to it
	 * @private
	 */
	private _initActions()
	{
		// Only look 1 level deep
		// @ts-ignore exists from Et2Widget
		var gam = egw_getActionManager(this.egw().appName, true, 1);
		if(typeof this._actionManager != "object")
		{
			// @ts-ignore exists from Et2Widget
			if(this.getInstanceManager() && gam.getActionById(this.getInstanceManager().uniqueId, 1) !== null)
			{
				// @ts-ignore exists from Et2Widget
				gam = gam.getActionById(this.getInstanceManager().uniqueId, 1);
			}
			if(gam.getActionById(this.id, 1) != null)
			{
				this._actionManager = gam.getActionById(this.id, 1);
			}
			else
			{
				this._actionManager = gam.addAction("actionManager", this.id);
			}
		}
		// @ts-ignore egw() exists on this
		this._actionManager.updateActions(this.actions, this.egw().appName);
		// @ts-ignore
		if(this.options.default_execute)
		{
			this._actionManager.setDefaultExecute(this.options.default_execute);
		}

		// Put a reference to the widget into the action stuff, so we can
		// easily get back to widget context from the action handler
		this._actionManager.data = {widget: this};
	}

	/** Sets focus on the control. */
	focus(options? : FocusOptions)
	{
		this._tree?.focus();
	}

	/** Removes focus from the control. */
	blur()
	{
		this._tree.blur();
	}
	/**
	 * @deprecated assign to onopenstart
	 * @param _handler
	 */
	public set_onopenstart(_handler: any)
	{
		this.onopenstart = _handler
	}

	/**
	 * @deprecated assign to onopenend
	 * @param _handler
	 */
	public set_onopenend(_handler: any)
	{
		this.onopenend = _handler
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

	public set_badge(_id: string, _value: string)
	{
		this.getNode(_id).badge = _value;
		this.requestUpdate();
	}

	/**
	 * @return currently selected Item or First Item, if no selection was made yet
	 */
	public getSelectedItem(): TreeItemData
	{
		return this._currentOption || (this._selectOptions ? this._selectOptions[0] : null);
	}

	/**
	 * getSelectedNode, retrieves the full node of the selected Item
	 * @return {SlTreeItem} full SlTreeItem
	 */
	getSelectedNode(): SlTreeItem
	{
		return this._currentSlTreeItem
	}

	getDomNode(_id: string): SlTreeItem | null
	{
		return this.shadowRoot.querySelector('sl-tree-item[id="' + _id.replace(/"/g, '\\"') + '"');
	}


	/**
	 * return the Item with given _id, was called getDomNode(_id) in dhtmlxTree
	 * @param _id
	 */
	public getNode(_id: string): TreeItemData
	{
		if(_id == undefined){debugger;}
		// TODO: Look into this._search(), find out why it doesn't always succeed
		return this._search(_id, this._selectOptions) ?? this.optionSearch(_id, this._selectOptions, 'id', 'item')
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
	 * @return Promise
	 */
	refreshItem(_id, data)
	{
		/* TODO currently always ask the sever
		if (typeof data != "undefined" && data != null)
		{

			//data seems never to be used
			this.refreshItem(_id, null)
		} else*/
		{
			let item = this.getNode(_id);
			// if the item does not exist in the tree yet no need to refresh
			if(item == null) return
			return this.handleLazyLoading(item).then((result) => {
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
		const temp = this.getDomNode(_id).defaultSlot;
		if (!temp) return 0;
			temp.setAttribute("style", _style);
	}

	/**
	 * manipulate the classes of a tree item
	 * this sets the class property of the item (just like php might set it).
	 * This triggers the class attribute of the sl-tree-item to be set
	 * mode "=" remove all classes and set only the given one
	 * mode "+" add the given class
	 * mode "-" remove the given class
	 * @param _id
	 * @param _className
	 * @param _mode
	 */
	setClass(_id: string, _className: string, _mode: '=' | '+' | '-')
	{
		const item = this.getNode(_id);
		if (item == null) return;
		if (!item.class) item.class = "";
		switch (_mode)
		{
			case "=":
				item.class = _className
				break;
			case "-":
				item.class = item.class.replace(_className, "")
				break;
			case "+":
				if (!item.class.includes(_className))
				{
					if (item.class == "")
					{
						item.class = _className;
					} else
					{
						item.class += " " + _className;
					}
				}
				break;
		}
		if (item.class.trim() === "") item.class = undefined;
	}

	/**
	 * getTreeNodeOpenItems
	 *
	 * @param {string} _nodeID the nodeID where to start from (initial node) 0 means for all items
	 * @param {string} mode the mode to run in: "forced" fakes the initial node openState to be open
	 * @return {object} structured array of node ids: array(message-ids)
	 */
	getTreeNodeOpenItems(_nodeID: string | 0, mode?: string)
	{
		let subItems =
			(_nodeID == 0) ?
				this._selectOptions.map(option => this.getDomNode(option.id)) ://NodeID == 0 means that we want all tree Items
				this.getDomNode(_nodeID).getChildrenItems();// otherwise get the subItems of the given Node
		let oS: boolean;
		let PoS: 0 | 1 | -1;
		let rv: string[];
		let returnValue = (_nodeID == 0) ? [] : [_nodeID]; // do not keep 0 in the return value...
		let modetorun = "none";
		if (mode)
		{
			modetorun = mode;
		}
		PoS = (_nodeID == 0) ? 1 : (this.getDomNode(_nodeID).expanded ? 1 : 0)
		if (modetorun == "forced") PoS = 1;
		if (PoS == 1)
		{
			for (const item of subItems)
			{
				//oS = this.input.getOpenState(z[i]);
				oS = item.expanded // iff current item is expanded go deeper
				//if (oS == -1) {returnValue.push(z[i]);}
				//if (oS == 0) {returnValue.push(z[i]);}
				if (!oS)
				{
					returnValue.push(item.id)
				}
				//if (oS == 1)
				else
				{
					rv = this.getTreeNodeOpenItems(item.id);
					for (const recId of rv)
					{
						returnValue.push(recId);
					}
				}
			}
		}
		//alert(returnValue.join('#,#'));
		return returnValue;

	}

	/**
	 * @param _id
	 * @param _newItemId
	 * @param _label
	 * @return Promise
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
		return this.updatedComplete();
	}

	public focusItem(_id)
	{
		let item = this.getNode(_id)
		item.focused = true
	}

	/**
	 * Open an item, which might trigger lazy-loading
	 *
	 * @param string _id
	 * @return Promise
	 */
	public openItem(_id : string)
	{
		let item = this.getNode(_id);
		if(item)
		{
			item.open = 1;
		}
		this.requestUpdate();
		return this.updateComplete;
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

	/**
	 * Set or unset checkbox of given node and all it's children based on given value
	 *
	 * @param _id
	 * @param _value "toggle" means the current nodes value, as the toggle already happened by default
	 * @return boolean false if _id was not found
	 */
	setSubChecked(_id : string, _value : boolean|"toggle")
	{
		const node = this.getDomNode(_id);
		if (!node) return false;

		if (_value !== 'toggle')
		{
			node.selected = _value;
		}
		Array.from(node.querySelectorAll('sl-tree-item')).forEach((item : SlTreeItem) => {
			item.selected = node.selected;
		});
		// set selectedNodes and value
		this.selectedNodes = [];
		this.value = [];
		Array.from(this._tree.querySelectorAll('sl-tree-item')).forEach((item : SlTreeItem) => {
			if (item.selected)
			{
				this.selectedNodes.push(item);
				this.value.push(item.id);
			}
		});
		return true;
	}

	getUserData(_nodeId, _name)
	{
		return this.getNode(_nodeId)?.userdata?.find(elem => elem.name === _name)?.content
	}

	/**
	 * Handle drag events from inside the shadowRoot
	 *
	 * events get re-targeted to the tree as they bubble, and action can't tell the difference between leaves
	 * inside the shadowRoot
	 *
	 * @param event
	 * @returns {Promise<void>}
	 * @protected
	 */
	protected async handleDragEvent(event)
	{
		await this.updateComplete;
		let option = event.composedPath().find(element =>
		{
			return element.tagName == "SL-TREE-ITEM"
		});
		if(!option)
		{
			return;
		}
		let id = option.value ?? (typeof option.id == 'number' ? String(option.id) : option.id);
		//console.log(event.type, id);

		const typeMap = {
			dragenter: EGW_AI_DRAG_ENTER,
			dragleave: EGW_AI_DRAG_OUT
		}
		this.widget_object.getObjectById(id).iface.triggerEvent(typeMap[event.type], event);
	}

	/**
	 * Overridable, add style
	 * @returns {TemplateResult<1>}
	 */
	styleTemplate()
	{
		return html``;
	}

	//this.selectOptions = find_select_options(this)[1];
	_optionTemplate(selectOption: TreeItemData): TemplateResult<1>
	{

		// Check to see if node is marked as open with no children.  If autoloadable, load the children
		const expandState = (this.calculateExpandState(selectOption));

		//mail sends multiple image options depending on folder state
		let img: String;
		if (selectOption.open) //if item is a folder and it is opened use im1
		{
			img = selectOption.im1;
		} else if (selectOption.child || selectOption.item?.length > 0)// item is a folder and closed use im2
		{
			img = selectOption.im2;
		} else// item is a leaf use im0
		{
			img = selectOption.im0;
		}
		//fallback to try and set icon if everything else failed
		if (!img) img = selectOption.icon ?? selectOption.im0 ?? selectOption.im1 ?? selectOption.im2;

		// lazy iff "child" is set and "item" is empty or item does not exist in the first place
		const lazy = (selectOption.item?.length === 0 && selectOption.child) || (selectOption.child && !selectOption.item)
		if(expandState && this.autoloading && lazy)
		{
			this.updateComplete.then(() =>
			{
				this.getDomNode(selectOption.id)?.dispatchEvent(new CustomEvent("sl-lazy-load"));
			})
		}
		const value = selectOption.value ?? selectOption.id;
		const selected = typeof this.value == "string" && this.value == value || Array.isArray(this.value) && this.value.includes(value);

		return html`
            <sl-tree-item
                    part="item"
                    exportparts="checkbox, label, item:item-item"
                    id=${value}
                    title=${selectOption.tooltip ||selectOption.title || nothing}
                    class=${selectOption.class || nothing}
                    ?selected=${selected}
                    ?expanded=${expandState}
                    ?disabled=${selectOption.disabled}
                    ?lazy=${lazy}
                    ?focused=${selectOption.focused || nothing}
                    @sl-lazy-load=${(event) => {
                        // No need for this to bubble up, we'll handle it (otherwise the parent leaf will load too)
                        event.stopPropagation();

                        this.handleLazyLoading(selectOption).then((result) => {
                            // TODO: We already have the right option in context.  Look into this.getNode(), find out why it's there.  It doesn't do a deep search.
                            const parentNode = selectOption ?? this.getNode(selectOption.id) ?? this.optionSearch(selectOption.id, this._selectOptions, 'id', 'item');
                            parentNode.item = [...result.item]
							if (parentNode.item.length == 0)
							{
								parentNode.child = false;
								this.getDomNode(parentNode.id).loading = false
							}
                            this.requestUpdate("_selectOptions")
							this._link_actions(this.actions)
                        })

					}
					}
					@sl-expand=${event => {
						if (event.target.id === selectOption.id)
						{
							selectOption.open = 1;

							this.requestUpdate("_selectOptions")
						}
					}}
					@sl-collapse=${event => {
						if (event.target.id === selectOption.id)
						{
							selectOption.open = 0;

							this.requestUpdate("_selectOptions")
						}
                    }}
            >


                <et2-image src="${img ?? nothing}"></et2-image>
                <span part="label_text" class="tree-item__label">
					${selectOption.label ?? selectOption.text}
				</span>
                ${(selectOption.badge) ?
					html`
						<sl-badge pill variant="neutral">${selectOption.badge}</sl-badge>
					` : nothing}

                ${selectOption.children ? repeat(selectOption.children, this._optionTemplate) : (selectOption.item ? repeat(selectOption.item, this._optionTemplate) : nothing)}
            </sl-tree-item>`
	}


	public render(): unknown
	{
		return html`
            ${this.styleTemplate()}
            <sl-tree
                    part="tree"
                    .selection=${this.leafOnly?"leaf":"single"}
                    @sl-selection-change=${
                            (event: any) => {
                                this._previousOption = this._currentOption ?? (this.value.length ? this.getNode(this.value[0]) : null);
                                this._currentOption = this.getNode(event.detail.selection[0].id) ?? this.optionSearch(event.detail.selection[0].id, this._selectOptions, 'id', 'item');
                                const ids = event.detail.selection.map(i => i.id);
								// implemented unlinked multiple
								if (this.multiple)
                                {
                                    const idx = this.value.indexOf(ids[0]);
                                    if (idx < 0)
                                    {
                                        this.value.push(ids[0]);
                                    }
                                    else
                                    {
                                        this.value.splice(idx, 1);
                                    }
									// sync tree-items selected attribute with this.value
									this.selectedNodes = [];
                                    Array.from(this._tree.querySelectorAll('sl-tree-item')).forEach((item : SlTreeItem) =>
                                    {
                                        if(this.value.includes(item.id))
                                        {
                                            item.setAttribute("selected", "");
											this.selectedNodes.push(item);
                                        }
                                        else
                                        {
                                            item.removeAttribute("selected");
                                        }
                                    });
                                    this._tree.requestUpdate();
                                }
								else
                                {
                                    this.value = this.multiple ? ids ?? [] : ids[0] ?? "";
                                }
                                event.detail.previous = this._previousOption?.id;
                                this._currentSlTreeItem = event.detail.selection[0];
								/* implemented unlinked-multiple
								if(this.multiple)
								{
									this.selectedNodes = event.detail.selection
								}*/
                                if(typeof this.onclick == "function")
                                {
									// wait for the update, so app founds DOM in the expected state
									this._tree.updateComplete.then(() => {
                                        this.onclick(event.detail.selection[0].id, this, event.detail.previous)
									});
                                }
                            }
                    }
                    @sl-expand=${
                            (event) => {
                                event.detail.id = event.target.id
                                event.detail.item = event.target
								if (this.onopenstart)
								{
									this.onopenstart(event.detail.id, this, 1)
								}
                            }
                    }
                    @sl-after-expand=${
                            (event) => {
                                event.detail.id = event.target.id
                                event.detail.item = event.target

								if (this.onopenend)
								{
									this.onopenend(event.detail.id, this, -1)
								}
								

                            }
                    }
                    @dragenter=${(event) => {this.handleDragEvent(event);}}
                    @dragleave=${(event) => {this.handleDragEvent(event);}}
            >
				<sl-icon name="chevron-right" slot="expand-icon"></sl-icon>
				<sl-icon name="chevron-down" slot="collapse-icon"></sl-icon>
                ${repeat(this._selectOptions, (o) => o.value, this._optionTemplate)}
            </sl-tree>
		`;
	}

	handleLazyLoading(_item: TreeItemData)
	{
		let requestLink = egw().link(egw().ajaxUrl(egw().decodePath(this.autoloading)),
			{
				id: _item.id
			})

		let result: Promise<TreeItemData> = egw().request(requestLink, [])


		return result
			.then((results) => {
				_item = results;

				// Add actions
				const itemAO = this.widget_object.getObjectById(_item.id);
				let parentAO = null;
				if(itemAO && itemAO.parent)
				{
					// Remove previous, if it exists
					parentAO = itemAO.parent;
					itemAO.remove();
				}

				// Need the DOM nodes to actually link the actions
				this.updateComplete.then(() =>
				{
					this.linkLeafActions(
						parentAO ?? this.widget_object,
						_item,
						this._get_action_links(this.actions)
					);
				});

				return results;
			});
	}

	/**
	 *
	 *
	 */
	_link_actions(actions)
	{
		if(this.actions && !this._actionManager)
		{
			// ActionManager creation was missed
			this.actions = this._actions;
		}
		// Get the top level element for the tree
		let objectManager = egw_getAppObjectManager(true);
		this.widget_object = objectManager.getObjectById(this.id);
		if (this.widget_object == null)
		{
			// Add a new container to the object manager which will hold the widget
			// objects
			this.widget_object = objectManager.insertObject(false, new EgwActionObject(
				//@ts-ignore
				this.id, objectManager, (new et2_action_object_impl(this, this)).getAOI(),
				this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager
			));
		} else
		{
			// @ts-ignore
			this.widget_object.setAOI((new et2_action_object_impl(this, this)).getAOI());
		}

		// Delete all old objects
		this.widget_object.clear();
		this.widget_object.unregisterActions();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);
		this.widget_object.updateActionLinks(action_links);
		//Drop target enabeling
		if (typeof this._selectOptions != 'undefined')
		{
			let self: Et2Tree = this
			// Iterate over the options (leaves) and add action to each one
			for (const selectOption of this._selectOptions)
			{
				this.linkLeafActions(this.widget_object, selectOption, action_links)
			}
		}
	}

	/**
	 * Add actions on a leaf
	 *
	 * @param {EgwActionObject} parentActionObject
	 * @param {TreeItemData} option
	 * @param {string[]} action_links
	 * @protected
	 */
	protected linkLeafActions(parentActionObject : EgwActionObject, option : TreeItemData, action_links : string[])
	{
		// Add a new action object to the object manager
		let id = option.value ?? (typeof option.id == 'number' ? String(option.id) : option.id);

		// @ts-ignore
		let obj : EgwActionObject = parentActionObject.addObject(id, new EgwDragDropShoelaceTree(this, id));
		obj.findActionTargetHandler = this;
		obj.updateActionLinks(action_links);

		const children = <TreeItemData[]><unknown>(option.children ?? option.item) ?? [];
		for(let i = 0; i < children.length; i++)
		{
			this.linkLeafActions(obj, children[i], action_links);
		}
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


	/**
	 *
	 * @param _id to search for
	 * @param data{TreeItemData[]} structure to search in
	 * @return {TreeItemData} node with the given _id or null
	 * @private
	 */
	private _search(_id: string|number, data: TreeItemData[]): TreeItemData
	{
		let res: TreeItemData = null
		if (_id == undefined)
		{
			return null
		}
		if (typeof _id === "number")
		{
			_id = _id + "";
		}
		for (const value of data)
		{
			if (value.id === _id)
			{
				res = value
				return res
			}
			else if(_id?.startsWith(value.id) && typeof value.item !== "undefined")
			{
				res = this._search(_id, value.item)
			}
		}
		return res
	}

	/**
	 * checks whether item should be drawn open or closed
	 * also sets selectOption.open if necessary
	 * @param selectOption
	 * @returns true iff item is in expanded state
	 */
	private calculateExpandState = (selectOption: TreeItemData) => {

		if (selectOption.open)
		{
			return true
		}
		// TODO: Move this mail-specific stuff into mail
		if(selectOption.id && (selectOption.id.endsWith("INBOX") || selectOption.id == window.egw.preference("ActiveProfileID", "mail")))
		{
			selectOption.open = 1
			return true
		}
		return false;
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

	/**
	 * returns the closest SlItem to the click position, and the corresponding EgwActionObject
	 * @param _event the click event
	 * @returns { target:SlTreeItem, action:EgwActionObject }
	 */
	findActionTarget(_event): { target: SlTreeItem, action: EgwActionObject }
	{
		let e = _event.composedPath ? _event : _event.originalEvent;
		let target = e.composedPath().find(element => {
			return element.tagName == "SL-TREE-ITEM"
		});
		let action : EgwActionObject = this.widget_object.getObjectById(target.id);
		return {target: target, action: action};
	}
}

customElements.define("et2-tree", Et2Tree);