import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlTree} from "@shoelace-style/shoelace";
import {Et2Link} from "../Et2Link/Et2Link";
import {et2_no_init} from "../et2_core_common";
import {egw, framework} from "../../jsapi/egw_global";
import {SelectOption, find_select_options, cleanSelectOptions} from "../Et2Select/FindSelectOptions";
import {egwIsMobile} from "../../egw_action/egw_action_common";
import {Et2WidgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {LitElement, css, TemplateResult, html} from "lit";
import {repeat} from "lit/directives/repeat.js";
import shoelace from "../Styles/shoelace";

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


export class Et2Tree extends Et2WidgetWithSelectMixin(LitElement)
{
	static get styles()
	{

		return [
			shoelace,
			// @ts-ignore
			...super.styles,
			css`

			`

		]
	}

	private currentItem: TreeItem
	private input: any = null;
	private autoloading_url: any;
	private selectOptions: TreeItem[] = [];
	private needsLazyLoading: Boolean = true;
	/**
	 * Limit server searches to 100 results, matches Link::DEFAULT_NUM_ROWS
	 * @type {number}
	 */
	static RESULT_LIMIT: number = 100;

	constructor()
	{
		super();
	}

	public loadFromXML()
	{
		//if(this.id)
		this.selectOptions = <TreeItem[]><unknown>find_select_options(this)[1]
	}

	static get properties()
	{
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

	public set onOpenStart(_handler: Function)
	{
		this.installHandler("onOpenStart", _handler)
	}

	public set onChange(_handler: Function)
	{
		this.installHandler("onChange", _handler)
	}

	public set onClick(_handler: Function)
	{
		this.installHandler("onClick", _handler)
	}

	public set onSelect(_handler: Function)
	{
		this.installHandler("onSelect", _handler)
	}

	public set onOpenEnd(_handler: Function)
	{
		this.installHandler("onOpenEnd", _handler)
	}


	/**
	 * @deprecated assign to onOpenStart
	 * @param _handler
	 */
	public set_onopenstart(_handler: Function)
	{
		this.installHandler("onOpenStart", _handler)
	}

	/**
	 * @deprecated assign to onChange
	 * @param _handler
	 */
	public set_onchange(_handler: Function)
	{
		this.installHandler('onchange', _handler);
	}

	/**
	 * @deprecated assign to onClick
	 * @param _handler
	 */
	public set_onclick(_handler: Function)
	{
		this.installHandler('onclick', _handler);
	}

	/**
	 * @deprecated assign to onSelect
	 * @param _handler
	 */
	public set_onselect(_handler: Function)
	{
		this.installHandler('onselect', _handler);
	}

	/**
	 * @deprecated assign to onOpenEnd
	 * @param _handler
	 */
	public set_onopenend(_handler: Function)
	{
		this.installHandler('onOpenEnd', _handler);
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
		if (this.autoLoading)
		{
			// @ts-ignore from static get properties
			let url = this.autoLoading;

			if (url.charAt(0) != '/' && url.substr(0, 4) != 'http')
			{
				url = '/json.php?menuaction=' + url;
			}
			this.autoloading_url = url;
		}
	}

	private handleLazyLoading(_item: TreeItem)
	{
		let sendOptions = {
			num_rows: Et2Tree.RESULT_LIMIT,
		}
		return egw().request(egw().link(egw().ajaxUrl(egw().decodePath(this.autoloading_url)),
			{
				id: _item.id
			}), [sendOptions])
			.then((results) => {

				// If results have a total included, pull it out.
				// It will cause errors if left in the results
				// this._total_result_count = results.length;
				// if(typeof results.total !== "undefined")
				// {
				// 	this._total_result_count = results.total;
				// 	delete results.total;
				// }
				// let entries = cleanSelectOptions(results);
				// this.processRemoteResults(entries);
				// return entries;
				return results
			});
	}

	//this.selectOptions = find_select_options(this)[1];
	_optionTemplate(selectOption: TreeItem)
	{
		// @ts-ignore

		//slot = expanded/collapsed instead of expand/collapse like it is in documentation
		//selectOption.child === 1
		return html`
            <sl-tree-item
                    .currentItem=${selectOption}
                    ?lazy=${this.needsLazyLoading}
                    @sl-lazy-load=${() => this.handleLazyLoading(selectOption)}
            >
                ${selectOption.text}
                ${repeat(selectOption.item, this._optionTemplate.bind(this))}
            </sl-tree-item>`
	}

	public render(): unknown
	{
		return html`
            <sl-tree

            >
                ${repeat(this.selectOptions, this._optionTemplate.bind(this))}
            </sl-tree>
		`;
	}

	protected remoteQuery(search: string, options: object): Promise<SelectOption[]>
	{
		// Include a limit, even if options don't, to avoid massive lists breaking the UI
		let sendOptions = {
			num_rows: Et2Tree.RESULT_LIMIT,
			...options
		}
		return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(this.searchUrl)),
			{query: search, ...sendOptions}), [search, sendOptions]).then((results) => {
			// If results have a total included, pull it out.
			// It will cause errors if left in the results
			this._total_result_count = results.length;
			if (typeof results.total !== "undefined")
			{
				this._total_result_count = results.total;
				delete results.total;
			}
			let entries = cleanSelectOptions(results);
			this.processRemoteResults(entries);
			return entries;
		});
	}
}

customElements.define("et2-tree", Et2Tree);


