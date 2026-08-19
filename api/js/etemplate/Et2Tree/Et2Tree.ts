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
import {
	EGW_AI_DRAG,
	EGW_AI_DRAG_ENTER,
	EGW_AI_DRAG_OUT,
	EGW_AO_FLAG_IS_CONTAINER
} from "../../egw_action/egw_action_constants";
import styles, {mobileCss} from "./Et2Tree.styles";
import {egwIsMobile} from "../../egw_action/egw_action_common";

export type TreeItemData = SelectOption & {
	focused?: boolean;
	data?: Object,//{sieve:true,...} or {acl:true} or other
	//this is coming from SelectOption
	value: string,
	im0: String,
	im1: String,
	im2: String,
	// Child items
	children: TreeItemData[],
	checked?: Boolean,

	// For items with children, "disabled" will make the item not expandable.
	// unselectable=true is like disabled=true, but will still allow the item to expand
	// and show its children
	unselectable? : boolean,

	nocheckbox: number | Boolean,
	open: 0 | 1,
	/**
	 * @deprecated Use "value"
	 */
	id : string,
	/**
	 * @deprecated No longer used, the nested data structure is used instead
	 */
	parent : string,
	/**
	 * @deprecated Use "label"
	 */
	text : string,
	/**
	 * @deprecated Use "children"
	 */
	item : object[],
	// Has children, but they may not be provided in children (lazy loaded)
	// @deprecated, SelectOption provides hasChildren
	child : Boolean | 1,

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
 *
 * @event et2-click Emitted when a tree item is clicked.  Clicks on the expand / collapse button and other slotted contents are excluded
 */
export class Et2Tree extends Et2WidgetWithSelectMixin(LitElement) implements FindActionTarget
{
	/**
	 * the trees lazy-loading promise, so we can externally do additional stuff after it resolves
	 */
	private lazyLoading: Promise<void>;

	/**
	 * Node ids (value ?? id) with a handleItemLazyLoad() fetch currently in flight.
	 *
	 * A node stays "lazy" (see _optionTemplate()) until its own fetch resolves and updates its
	 * item/child state - but every OTHER node's fetch completing also triggers a tree-wide
	 * requestUpdate("_selectOptions"), which re-renders every item and, via _optionTemplate()'s
	 * own autoload self-trigger, re-dispatches "sl-lazy-load" for this node again even though it's
	 * already loading. Without this guard, N concurrently-loading nodes (eg. many
	 * persisted-open folders self-triggering at once) produce a multiplying storm of redundant,
	 * duplicate fetches for the same nodes as their siblings resolve one by one.
	 */
	private _lazyLoadPending = new Set<string>();

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
	@property()
	autoloading: string | ((item : TreeItemData) => Promise<any>) = ""  //description: "JSON URL or menuaction to be called for nodes marked with child=1, but not having children, getSelectedNode() contains node-id - or a Javascript callback function(item) returning a Promise of the same {item: [...]} / {children: [...]} shape, for a caller that wants to supply children itself instead of an ajax round-trip"
	@property({type: Function})
	onopenstart //description: "Javascript function executed when user opens a node: function(_id, _widget, _hasChildren) returning true to allow opening!"
	@property({type: Function})
	onopenend   //description: "Javascript function executed when opening a node is finished: function(_id, _widget, _hasChildren)"
	@property({type: String})
	openStatePreference: string = ""  //description: "'app.prefName' - if set, the tree automatically restores which nodes were expanded from this preference on load, and (debounced) saves the current expand-state back to it on every node open/close"
	@property({type: String})
	imagePath : string = egw?.webserverUrl + "/api/templates/default/images/dhtmlxtree/" //TODO we will need a different path here! maybe just rename the path?
	//     description: "Directory for tree structure images, set on server-side to 'dhtmlx' subdir of templates image-directory"
	@property()
	value:any[]|string = []

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
	// openStatePreference bookkeeping - see applyOpenState()/saveOpenState()
	private _openIds : Set<string>;
	private _hasSavedOpenState : boolean = false;
	private _openStateSaveTimer : number;
	/***
	 * If you alter the pictures used as expand/collapse icons
	 * you need to increase this number to cache bust Browser-caching
	 ***/
	static svgVersion="1.1";

	private get _tree() { return this.shadowRoot.querySelector('sl-tree') ?? null};


	constructor()
	{
		super();
		this._selectOptions = [];

		this._optionTemplate = this._optionTemplate.bind(this);
		this.handleItemLazyLoad = this.handleItemLazyLoad.bind(this);

		this.selectedNodes = [];
	}

	connectedCallback()
	{
		super.connectedCallback();
		// Actions can't be initialized without being connected to InstanceManager
		// initialize actions only after all current Updates are completed to stop it from happening multiple times
		this.updateComplete.then((complete) =>
		{
		if(complete && this.actions && Object.values(this.actions).length)
		{
			this._initActions();
			this._link_actions(this.actions)
		}
		})
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this._currentSlTreeItem = null;
		this.selectedNodes.splice(0, this.selectedNodes.length);
	}

	destroy()
	{
		if(this._actionManager)
		{
			// Delete all actions
			this._actionManager.remove();
			this._actionManager = undefined;
		}
	}

	private _initCurrent()
	{
		this._currentSlTreeItem = this.selected;
		this._currentOption = this._currentSlTreeItem?this.getNode(this._currentSlTreeItem?.id):null
	}
	firstUpdated()
	{
		if (this.autoloading && typeof this.autoloading === "string")
		{
			// @ts-ignore from static get properties
			let url = this.autoloading;

			if (url.charAt(0) != '/' && url.substr(0, 4) != 'http')
			{
				url = '/json.php?menuaction=' + url;
			}
			this.autoloading = url;
		}

		if (this.openStatePreference)
		{
			this.applyOpenState();
		}

		// Check if top level should be autoloaded
		if(this.autoloading && !this._selectOptions?.length)
		{
			this.lazyLoading = this.handleLazyLoading({item: this._selectOptions}).then((results) =>
			{
				this._selectOptions = results?.children ?? results?.item ?? [];
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
		if(this.actions && Object.values(this.actions).length)
		{
			this._initActions();
			this._link_actions(this.actions)
		}
	}

	/**
	 * Split "app.prefName" (openStatePreference) into its [app, name] parts.
	 *
	 * App names never contain a dot, so splitting on the first one is unambiguous.
	 */
	private openStatePreferenceParts() : [string, string] | null
	{
		const dot = this.openStatePreference.indexOf('.');
		if (dot < 1 || dot === this.openStatePreference.length - 1)
		{
			return null;
		}
		return [this.openStatePreference.substring(0, dot), this.openStatePreference.substring(dot + 1)];
	}

	/**
	 * Lazily parse and cache openStatePreference's persisted expanded-node-ids, once per widget
	 * instance - reused by every applyOpenState() call (initial render, and again after every
	 * lazy-load merge, see handleItemLazyLoad()). Also records (_hasSavedOpenState) whether a
	 * preference value was ever actually saved at all, as opposed to merely being unset - an
	 * explicitly-saved empty list is still the non-empty raw string "[]", distinct from a raw
	 * value of "" /undefined/null for "never saved anything yet". applyOpenState() needs this
	 * distinction: see its own docblock for why.
	 */
	private loadOpenIds() : Set<string>
	{
		if (this._openIds) return this._openIds;
		const parts = this.openStatePreferenceParts();
		let ids : string[] = [];
		if (parts)
		{
			const raw = egw().preference(parts[1], parts[0]);
			this._hasSavedOpenState = !!raw;
			try
			{
				ids = JSON.parse(raw || "[]") || [];
			}
			catch (e)
			{
				ids = [];
			}
		}
		return this._openIds = new Set(ids);
	}

	/**
	 * Apply openStatePreference's persisted expanded-node-ids to whatever's currently in
	 * _selectOptions. Restoring a *deep* expand path (e.g. "INBOX > Project > 2026") needs more
	 * than a single pass at first render: with lazy per-level loading, "Project"/"2026" don't
	 * exist client-side at all until "INBOX" has actually been expanded once and its children
	 * have arrived - so this must be re-run every time a lazy-load merge brings new nodes into
	 * _selectOptions (see handleItemLazyLoad()), not just once in firstUpdated(). Each pass only
	 * ever marks nodes already present; _optionTemplate()'s own expandState-driven eager
	 * lazy-load dispatch (see its docblock) is what actually drives the next level's fetch once
	 * a matching node gets marked open here, continuing the cascade level by level as each
	 * fetch resolves.
	 *
	 * The top level (_selectOptions itself) is authoritative both ways - closed AND open - but
	 * only once a preference value has actually ever been saved (_hasSavedOpenState, see
	 * loadOpenIds()). A caller's own initial data can carry its own "open by default" flag (eg.
	 * mail's active-account row, or its sole account if there's only one) that would otherwise
	 * never get overridden by an explicit user close: closing a top-level item removes it from
	 * the persisted set, but with only an additive apply, the caller's own default `open` would
	 * just reassert itself again on the next page load, making it impossible to ever permanently
	 * close that item. That authoritative reset must NOT kick in before anything's ever been
	 * saved though - a brand-new user (nothing saved yet) should still see the caller's own
	 * sensible defaults (eg. their one and only account starting open), not everything
	 * force-closed just because the preference happens to not exist yet. Deeper levels stay
	 * additive-only regardless (never reset to closed) - they have no such caller-supplied
	 * default to override, and a fresh fetch may have deliberately set its own `open` (eg. mail's
	 * INBOX-always-open-if-it-has-children behaviour, folderTree.ts's buildNode()) that must not
	 * be clobbered.
	 *
	 * At least one top-level item always ends up open (falls back to the first one if the
	 * authoritative pass above would otherwise leave zero) - a saved state can end up with none
	 * open (eg. one saved before handleItemCollapse()'s own "can't close the last one" guard
	 * existed), and a completely-collapsed top level would leave the user looking at an empty
	 * tree with nothing to click.
	 */
	private applyOpenState() : void
	{
		if (!this.openStatePreferenceParts()) return;
		const ids = this.loadOpenIds();
		const authoritative = this._hasSavedOpenState;

		const applyRecursive = (options : TreeItemData[], isTopLevel : boolean) =>
		{
			(options ?? []).forEach((option : any) =>
			{
				if (ids.has(option.id ?? option.value))
				{
					option.open = 1;
				}
				else if (isTopLevel && authoritative)
				{
					option.open = 0;
				}
				applyRecursive(option.item ?? option.children, false);
			});
		};
		applyRecursive(this._selectOptions, true);
		if (authoritative && this._selectOptions?.length && !this._selectOptions.some((o : any) => o.open))
		{
			(this._selectOptions[0] as any).open = 1;
		}
		this.requestUpdate("_selectOptions");
	}

	/**
	 * Collect every currently-open node's id (recursively) and (debounced) write it back to
	 * openStatePreference - called from handleItemExpand()/handleItemCollapse().
	 */
	private saveOpenState() : void
	{
		const parts = this.openStatePreferenceParts();
		if (!parts) return;
		const [app, name] = parts;

		const ids : string[] = [];
		const collect = (options : TreeItemData[]) =>
		{
			(options ?? []).forEach((option : any) =>
			{
				if (option.open)
				{
					ids.push(option.id ?? option.value);
				}
				collect(option.item ?? option.children);
			});
		};
		collect(this._selectOptions);

		window.clearTimeout(this._openStateSaveTimer);
		this._openStateSaveTimer = window.setTimeout(() =>
		{
			egw().set_preference(app, name, JSON.stringify(ids));
		}, 300);
	}

	protected updated(_changedProperties: PropertyValues)
	{
		super.updated(_changedProperties);

		// openStatePreference is often assigned imperatively (eg. mail's app.ts, right after
		// getWidgetById()) - possibly after firstUpdated() already ran and found it empty, so
		// also (re-)apply here whenever it actually changes, not just once on first render
		if (_changedProperties.has("openStatePreference") && this.openStatePreference)
		{
			this.applyOpenState();
		}
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
			this.onclick(_ev.target.value, this, this._previousOption?.value ?? this._previousOption?.id)
		}
	}

	static get styles()
	{

		return [
			shoelace,
			super.styles,
			styles,
			egwIsMobile()?mobileCss:css``
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
		if(this._actionManager)
		{
			// @ts-ignore egw() exists on this
			this._actionManager.updateActions(this.actions, this.egw().appName);

			// Put a reference to the widget into the action stuff, so we can
			// easily get back to widget context from the action handler
			this._actionManager.data = {widget: this};
		}
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

	/**
	 * Set badge with given value on a tree-node
	 *
	 * @param _id of tree-node
	 * @param _value
	 */
	public set_badge(_id: string, _value: string)
	{
		const node = this.getNode(_id);
		if (node)
		{
			node.badge = _value;
			this.requestUpdate();
		}
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
		return this._search(_id, this._selectOptions) ?? this.optionSearch(_id, this._selectOptions, 'value', 'children')
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
		let item = this.getNode(_id);
		// if the item does not exist in the tree yet no need to refresh
		if(item == null)
		{
			return Promise.resolve();
		}
		if (typeof data !== "undefined" && data !== null)
		{
			Object.assign(item, data);
			this.requestUpdate("_selectOptions");
			return Promise.resolve();
		}
		return this.handleLazyLoading(item).then((result) => {
			Object.assign(item, result);
			this.requestUpdate("_selectOptions")
		})
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
			this._selectOptions.map(option => this.getDomNode(option.value ?? option.id)) ://NodeID == 0 means that we want all tree Items
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
		this.getNode(_id).value = _newItemId

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
	 * scroll to item with given id
	 * make sure all parents of the item are expanded else scroll will fail
	 * @param _id
	 */
	public scrollToItem(_id: string)
	{
		const item: SlTreeItem = this.getDomNode(_id);
		if (item == null) return
		item.scrollIntoView(false);
	}

	/**
	 * scrolls to the (first) selected slTreeItem into view
	 * this function delays, if not all parents of the item are expanded
	 *
	 * @return boolean true: selected item scrolled into view, false: selected item not found / loaded
	 */
	public scrollToSelected()
	{
		try
		{
			const item: SlTreeItem = this.shadowRoot.querySelector('sl-tree-item[selected]');
			if (!item) {
				return false;
			}

			//this might not work because item pant is not expanded
			//in that case expand all parents and wait before trying to scroll again
			let parent: SlTreeItem = item.parentElement?.tagName === "SL-TREE-ITEM" ? <SlTreeItem>item.parentElement : null;
			//scroll and exit if parent does not need expansion
			if (!parent || parent.expanded)
			{
				item.scrollIntoView(false)
				return true;
			}
			//fallback
			//expand all parent items
			while (parent)
			{
				if (!parent.expanded) parent.expanded = true;
				parent = parent.parentElement?.tagName === "SL-TREE-ITEM" ? <SlTreeItem>parent.parentElement : null;
			}
			this.updateComplete.then(() => item.scrollIntoView(false));
		} catch (e)
		{
			console.log("Could not scroll to item");
		}
		return true;
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

		return this.updateComplete.then(() => this.lazyLoading ? this.lazyLoading : Promise.resolve());
	}

	/**
	 * hasChildren
	 *
	 * @param _id ID of the node
	 * @return the number of childelements
	 */
	hasChildren(_id)
	{
		return this.getNode(_id).child;
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
				this.value.push(item.value);
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

		// Remove drop hover from any parent nodes
		if(event.type == "dragenter")
		{
			event.stopPropagation();
			let current = option.parentElement;
			while(current)
			{
				current.classList.remove("draggedOver", "drop-hover");
				current = current.parentElement;
			}
		}
		// Ignore/stop events from child nodes, unless it's dragenter and the parent sl-tree-item isn't hovered yet
		if(["dragenter", "dragleave"].includes(event.type) && event.target != option && event.composedPath().includes(option))
		{
			event.stopPropagation();
			if(event.type != "dragenter" || option.classList.contains("drop-hover"))
			{
				return;
			}
		}
		//let id = option.value ?? (typeof option.id == 'number' ? String(option.id) : option.id);
		//console.log(event.type, id, event.target);

		const typeMap = {
			dragstart: EGW_AI_DRAG,
			dragenter: EGW_AI_DRAG_ENTER,
			dragleave: EGW_AI_DRAG_OUT,
		}
		this.widget_object.iface.triggerEvent(typeMap[event.type] ?? event.type, event);
	}

	protected async handleItemClick(event)
	{
		// Don't react to expand or children
		if(event.target.hasAttribute("slot") || !event.target?.closest("sl-tree-item"))
		{
			return;
		}
		await this.updateComplete;
		event.target?.closest("sl-tree-item").dispatchEvent(new CustomEvent("et2-click", {
			detail: {item: event.target?.closest("sl-tree-item")},
			bubbles: true,
			composed: true
		}));
	}

	protected handleItemCollapse(event)
	{
		const selectOption = this.optionSearch(event.target.value ?? event.target.id, this._selectOptions, 'value', 'children') ??
			this.optionSearch(event.target.value ?? event.target.id, this._selectOptions, 'id', 'item');
		if(selectOption)
		{
			// never let the last remaining open top-level item collapse (only matters once
			// openStatePreference is in use, eg. mail's account list - a single account, or the
			// last one still open among several, must always stay open so the user never ends up
			// looking at a completely empty tree with nothing left to click). Not clearing .open
			// here still forces a re-render below, which reasserts ?expanded=true on the DOM node
			// and snaps it straight back open, undoing Shoelace's own already-applied collapse.
			const isLastOpenTopLevel = this.openStatePreferenceParts() &&
				this._selectOptions.includes(selectOption as any) &&
				!this._selectOptions.some((o : any) => o !== selectOption && o.open);
			if (!isLastOpenTopLevel)
			{
				selectOption.open = 0;
			}

			this.requestUpdate("_selectOptions")
		}
		if (this.openStatePreference)
		{
			this.saveOpenState();
		}
	}

	protected handleItemExpand(event)
	{
		const selectOption = this.optionSearch(event.target.value ?? event.target.id, this._selectOptions, 'value', 'children') ??
			this.optionSearch(event.target.value ?? event.target.id, this._selectOptions, 'id', 'item');
		if(selectOption)
		{
			selectOption.open = 1;
		}
		if (this.openStatePreference)
		{
			this.saveOpenState();
		}
	}

	protected handleItemLazyLoad(event)
	{
		// No need for this to bubble up, we'll handle it (otherwise the parent leaf will load too)
		event.stopPropagation();
		const selectOption = this.optionSearch(event.target.value ?? event.target.id, this._selectOptions, 'value', 'children') ??
			this.optionSearch(event.target.value ?? event.target.id, this._selectOptions, 'id', 'item');

		const key = selectOption?.value ?? selectOption?.id ?? (event.target.value ?? event.target.id);
		if(this._lazyLoadPending.has(key))
		{
			// a fetch for this exact node is already in flight - see _lazyLoadPending's docblock
			return;
		}
		this._lazyLoadPending.add(key);

		this.lazyLoading = this.handleLazyLoading(selectOption).then((result) =>
		{
			// TODO: We already have the right option in context.  Look into this.getNode(), find out why it's there.  It doesn't do a deep search.
			const parentNode = selectOption ?? this.getNode(selectOption.id) ?? this.optionSearch(selectOption.value, this._selectOptions, 'value', 'children');
			if(!parentNode || !parentNode.item || parentNode.item.length == 0)
			{
				parentNode.child = false;
				parentNode.open = false;
				this.requestUpdate("lazy", "true");
			}
			// the DOM node may not exist right now (e.g. an ancestor's own re-render is still
			// pending while this and another lazy-load resolve close together) - nothing to reset
			// in that case, and crashing here would also skip the openState cascade/requestUpdate below
			const domNode = this.getDomNode(parentNode.value ?? parentNode.id);
			if(domNode)
			{
				domNode.loading = false;
			}
			if (this.openStatePreference)
			{
				// cascade the restore: newly-arrived children may themselves be in the
				// persisted expanded-ids set, continuing a deep expand path level by level
				// as each fetch resolves (see applyOpenState()'s docblock)
				this.applyOpenState();
			}
			this.requestUpdate("_selectOptions")
		}).finally(() => this._lazyLoadPending.delete(key));
	}

	/**
	 * Handle a change in selected items
	 *
	 * @returns {Promise<void>}
	 * @protected
	 */
	protected handleSelectionChange(event)
	{
		// Filter out unselectable nodes
		let nodes = event.detail.selection.filter(node => !node.hasAttribute("unselectable"));
		if(nodes.length != event.detail.selection.length)
		{
			event.detail.selection.forEach(n =>
			{
				if(!n.hasAttribute("unselectable"))
				{
					return;
				}
				n.removeAttribute("selected");
				if(n.querySelectorAll(":scope > sl-tree-item").length > 0)
				{
					n.toggleAttribute("expanded");
				}
			});
			event.stopPropagation();
			this.requestUpdate("value");
			return;
		}

		this._previousOption = this._currentOption ?? (this.value.length ? this.getNode(this.value[0]) : null);
		this._currentOption = this.getNode(nodes[0].id) ?? this.optionSearch(nodes[0].value ?? nodes[0].id, this._selectOptions, 'value', 'children');
		const ids = event.detail.selection.map(i => i.id);
		// implemented unlinked multiple
		if(this.multiple)
		{
			const idx = this.value.indexOf(ids[0]);
			if(idx < 0)
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
		event.detail.previous = this._previousOption?.value ?? this._previousOption?.id;
		this._currentSlTreeItem = nodes[0];
		/* implemented unlinked-multiple
		if(this.multiple)
		{
			this.selectedNodes = event.detail.selection
		}*/

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("et2-selection-change", {
				bubbles: true,
				detail: {
					selection: this.selectedNodes,
					ids: this.value
				}
			}))
		})
	}

	/**
	 * Resolves once the initial autoloading fetch (if any) has populated _selectOptions.
	 * Public so callers like Et2TreeDropdown can wait for data before eg. scrolling to a selection.
	 */
	public async finishedLazyLoading()
	{
		await this.lazyLoading;
		return this.lazyLoading
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
		}
		else if(selectOption.hasChildren || selectOption.item?.length > 0)// item is a folder and closed use im2
		{
			img = selectOption.im2;
		} else// item is a leaf use im0
		{
			img = selectOption.im0;
		}
		//fallback to try and set icon if everything else failed
		if (!img) img = selectOption.icon ?? selectOption.im0 ?? selectOption.im1 ?? selectOption.im2;

		// lazy iff "child" is set and "children" is empty or children does not exist in the first place
		let lazy: Boolean | 1 | 0;
		if (typeof selectOption.item !== "undefined")
		{
			lazy = (selectOption.item?.length === 0 && selectOption.child) || (selectOption.child && !selectOption.item);
		} else
		{
			lazy = (typeof selectOption.children === "undefined" || selectOption.children?.length == 0)
				&& selectOption.hasChildren;
		}
		const value = selectOption.value ?? selectOption.id;
		if(expandState && this.autoloading && lazy)
		{
			this.updateComplete.then(() =>
			{
				this.getDomNode(value)?.dispatchEvent(new CustomEvent("sl-lazy-load"));
			})
		}
		const selected = typeof this.value == "string" && this.value == value || Array.isArray(this.value) && this.value.includes(value);
		const draggable = this.widget_object?.actionLinks?.filter(al => al.actionObj?.type == "drag").length > 0

		// title uses ?? below, not || : an explicit "" must render as title="" to block the
		// browser's native title-attribute inheritance from a DOM ancestor (a child node with no
		// tooltip of its own would otherwise silently show its parent's - eg. mail's INBOX node,
		// whose own children live inside its DOM subtree). Only the genuinely-unset case
		// (undefined/null) should fall through to .title/nothing.
		return html`
            <sl-tree-item
                    part="item"
                    exportparts="checkbox, label, item:item-item"
                    id=${value}
                    value="${value}"
                    title=${selectOption.tooltip ?? selectOption.title ?? nothing}
                    class=${selectOption.class || nothing}
                    ?selected=${selected && !selectOption.unselectable}
                    ?unselectable=${selectOption.unselectable}
                    ?expanded=${expandState}
                    ?disabled=${selectOption.disabled}
                    ?lazy=${lazy}
                    ?focused=${selectOption.focused || nothing}
                    draggable=${draggable}
                    @click=${this.handleItemClick}
                    @sl-lazy-load=${this.handleItemLazyLoad}
                    @sl-expand=${this.handleItemExpand}
                    @sl-collapse=${this.handleItemCollapse}
            >
                <et2-image src="${img ?? nothing}" inline></et2-image>
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
                    @sl-selection-change=${this.handleSelectionChange}
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
                    @dragstart=${(event) => {this.handleDragEvent(event);}}
                    @dragenter=${(event) => {this.handleDragEvent(event);}}
                    @dragleave=${(event) => {this.handleDragEvent(event);}}
					@drop=${(event) => {this.handleDragEvent(event);}}
            >
				<sl-icon src="${this.egw().image("bi-chevron-right")}?v=${Et2Tree.svgVersion}" slot="expand-icon"></sl-icon>
				<sl-icon src="${this.egw().image("bi-chevron-down")}?v=${Et2Tree.svgVersion}" slot="collapse-icon"></sl-icon>
                <slot></slot>
                ${repeat(this._selectOptions, (o) => o.value, this._optionTemplate)}
            </sl-tree>
<!--            this is needed on mobile devices to make sure scrolling can reach all the way to the bottom -->
            ${egwIsMobile()?html`<div part="bottom-spacer"></div>`:nothing}
		`;
	}

	handleLazyLoading(_item: TreeItemData)
	{
		let result: Promise<TreeItemData>;
		if (typeof this.autoloading === "function")
		{
			result = Promise.resolve(this.autoloading(_item));
		}
		else
		{
			let requestLink = egw().link(egw().ajaxUrl(egw().decodePath(this.autoloading)),
				{
					id: _item.value ?? _item.id
				})

			result = egw().request(requestLink, [])
		}

		return result
			.then((results) => {
				Object.assign(_item, results);

				// Add actions
				if(this.actions && Object.entries(this.actions).length > 0)
				{
					const itemAO = this.widget_object.getObjectById(_item.value ?? _item.id);
					let parentAO = null;
					if(itemAO && itemAO.parent)
					{
						// Remove previous, if it exists
						parentAO = itemAO.parent;
						itemAO.remove();
					}
				}

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
		let objectManager = egw_getAppObjectManager(true, this.getInstanceManager()?.app);
		this.widget_object = objectManager.getObjectById(this.id);
		const ao_impl = new et2_action_object_impl(this, this);
		ao_impl.aoi = new EgwDragDropShoelaceTree(this);
		if(this.widget_object == null || this.widget_object.manager !== this._actionManager)
		{
			// Add a new container to the object manager which will hold the widget
			// objects
			this.widget_object = objectManager.insertObject(false, new EgwActionObject(
				//@ts-ignore
				this.id, objectManager, ao_impl.getAOI(),
				this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager,
				EGW_AO_FLAG_IS_CONTAINER
			));
		} else
		{
			// @ts-ignore
			this.widget_object.setAOI(ao_impl.getAOI());
		}

		// Delete all old objects
		this.widget_object.clear();
		this.widget_object.unregisterActions();

		// Go over the widget & add links - this is where we decide which actions are
		// 'allowed' for this widget at this time
		var action_links = this._get_action_links(actions);
		this.widget_object.updateActionLinks(action_links);
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
			if(value.value == _id || value.id === _id)
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
		return false;
	}

	private _deleteItem(_id, list)
	{
		for (let i = 0; i < list.length; i++)
		{
			const value = list[i];
			if(value.value === _id || value.id === _id)
			{
				list.splice(i, 1)
			} else if (_id.startsWith(value.id))
			{
				this._deleteItem(_id, value.item)
			}
		}
	}

	/**
	 * returns the closest SlTreeItem to the click position, and the corresponding EgwActionObject
	 * @param _event the click event
	 * @returns { target:SlTreeItem, action:EgwActionObject }
	 */
	findActionTarget(_event): { target: SlTreeItem, action: EgwActionObject }
	{
		let e = _event.composedPath ? _event : _event.originalEvent;
		let target = e.composedPath().find(element => {
			return element.tagName == "SL-TREE-ITEM"
		});
		if(!target)
		{
			return {target: null, action: null};
		}
		let action : EgwActionObject = this.widget_object.getObjectById(target.id);

		// Create on the fly if not there?  Action handlers might need the EgwActionObject
		if(!action)
		{
			// NOTE: FLAT object structure under the tree ActionObject to avoid nested selection
			action = this.widget_object.addObject(target.id, this.widget_object.iface)
			action._context = target;
			action.findActionTargetHandler = this.widget_object;
			action.setSelected = (set) =>
			{
				target.action_selected = set;
				this.widget_object.updateSelectedChildren(action, set);
			}
			action.getSelected = () => target.action_selected;
			// Required to get dropped accepted, but also re-binds
			action.updateActionLinks(this._get_action_links(this.actions));
		}
		action.findActionTargetHandler = this.widget_object;
		// This is just the action system, which we override
		this.widget_object.setAllSelected(false);
		// This will affect action system & DOM, but not our internal value
		this.widget_object.children.forEach(c =>
		{
			c.setSelected(false)
		})

		this.widget_object.iface.stateChangeContext = action;
		action.setSelected(true);

		return {target: target, action: action};
	}
}

customElements.define("et2-tree", Et2Tree);