/**
 * EGroupware eTemplate2 - JS History log
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
	et2_core_valueWidget;

	// Include the grid classes
	et2_dataview;
*/

import {et2_IDataProvider} from "./et2_dataview_interfaces";
import {et2_createWidget, et2_register_widget, et2_registry, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_dataview} from "./et2_dataview";
import {et2_dataview_column} from "./et2_dataview_model_columns";
import {et2_dataview_controller} from "./et2_dataview_controller";
import {et2_diff} from "./et2_widget_diff";
import {et2_IDetachedDOM, et2_IResizeable} from "./et2_core_interfaces";
import {et2_customfields_list} from "./et2_extension_customfields";
import {et2_selectbox} from "./et2_widget_selectbox";
import {loadWebComponent} from "./Et2Widget/Et2Widget";
import {cleanSelectOptions, SelectOption} from "./Et2Select/FindSelectOptions";

/**
 * eTemplate history log widget displays a list of changes to the current record.
 * The widget is encapsulated, and only needs the record's ID, and a map of
 * fields:widgets for display.
 *
 * It defers its initialization until the tab that it's on is selected, to avoid
 * wasting time if the user never looks at it.
 *
 * @augments et2_valueWidget
 */
export class et2_historylog extends et2_valueWidget implements et2_IDataProvider,et2_IResizeable
{
	static readonly _attributes = {
		"value": {
			"name": "Value",
			"type": "any",
			"description": "Object {app: ..., id: ..., status-widgets: {}} where status-widgets is a map of fields to widgets used to display those fields"
		},
		"status_id":{
			"name": "status_id",
			"type": "string",
			"default": "status",
			"description": "The history widget is traditionally named 'status'.  If you name another widget in the same template 'status', you can use this attribute to re-name the history widget.  "
		},
		"columns": {
			"name": "columns",
			"type": "string",
			"default": "user_ts,owner,status,new_value,old_value",
			"description": "Columns to display.  Default is user_ts,owner,status,new_value,old_value"
		},
		"get_rows": {
			"name": "get_rows",
			"type": "string",
			"default": "EGroupware\\Api\\Storage\\History::get_rows",
			"description": "Method to get rows"
		}
	};

	public static readonly legacyOptions = ["status_id"];
	protected static columns = [
		{'id': 'user_ts', caption: 'Date', 'width': '120px', widget_type: 'et2-date-time', widget: null, nodes: null},
		{
			'id': 'owner',
			caption: 'User',
			'width': '150px',
			widget_type: 'et2-select-account_ro',
			widget: null,
			nodes: null
		},
		{'id': 'status', caption: 'Changed', 'width': '120px', widget_type: 'et2-select', widget: null, nodes: null},
		{'id': 'new_value', caption: 'New Value', 'width': '50%', widget: null, nodes: null},
		{'id': 'old_value', caption: 'Old Value', 'width': '50%', widget: null, nodes: null}
	];

	static readonly TIMESTAMP = 0;
	static readonly OWNER = 1;
	static readonly FIELD = 2;
	static readonly NEW_VALUE = 3;
	static readonly OLD_VALUE = 4;
	
	private div: JQuery;
	private innerDiv: JQuery;
	private _filters: { appname: string; record_id: string; get_rows: string; };
	private dataview: et2_dataview;
	private controller: et2_dataview_controller;
	private fields: any;
	private diff: et2_diff;

	/**
	 * Constructor
	 *
	 * @memberOf et2_historylog
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object) {
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_historylog._attributes, _child || {}));
		this.div = jQuery(document.createElement("div"))
			.addClass("et2_historylog");

		this.innerDiv = jQuery(document.createElement("div"))
			.appendTo(this.div);

		this._resize = this._resize.bind(this);
	}

	set_status_id( _new_id)
	{
		this.options.status_id = _new_id;
	}

	doLoadingFinished() : boolean | Promise<unknown>
	{
		super.doLoadingFinished();

		// Find the tab
		let tab = this.get_tab_info();
		if(tab)
		{
			// Bind the action to when the tab is selected
			const handler = (e) =>
			{
				if(typeof this.dataview == "undefined")
				{
					this.finishInit();
				}
				// TODO: Find a better way to get this to wait
				window.setTimeout(this._resize, 10);
			};
			tab.flagDiv.addEventListener("click", handler);
		}
		else
		{
			this.finishInit();
		}
		return true;
	}

	_createNamespace()
	{
		return true;
	}
	/**
	 * Finish initialization which was skipped until tab was selected
	 */
	finishInit( )
	{
		// No point with no ID
		if(!this.options.value || !this.options.value.id)
		{
			return;
		}
		this._filters = {
			record_id: this.options.value.id,
			appname: this.options.value.app,
			get_rows: this.options.get_rows
		};

		// Warn if status_id is the same as history id, that causes overlap and missing labels
		if(this.options.status_id === this.id)
		{
			this.egw().debug("warn", "status_id attribute should not be the same as historylog ID");
		}

		const parent = this.get_tab_info();

		// Create the outer grid container
		this.dataview = new et2_dataview(this.innerDiv, this.egw());
		const dataview_columns = [];
		let _columns = typeof this.options.columns === "string" ?
			this.options.columns.split(',') : this.options.columns;
		for (var i = 0; i < et2_historylog.columns.length; i++)
		{
			dataview_columns[i] = {
					"id": et2_historylog.columns[i].id,
					"caption": et2_historylog.columns[i].caption,
					"width":et2_historylog.columns[i].width,
					"visibility":_columns.indexOf(et2_historylog.columns[i].id) < 0 ?
						et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE : et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE
			};
		}
		this.dataview.setColumns(dataview_columns);

		// Create widgets for columns that stay the same, and set up varying widgets
		this.createWidgets();

		// Create the gridview controller
		const linkCallback = function ()
		{
		};
		this.controller = new et2_dataview_controller(null, this.dataview.grid);
		this.controller.setContext(this);
		this.controller.setDataProvider(this);
		this.controller.setLinkCallback(linkCallback);
		this.controller.setRowCallback(this.rowCallback);
		this.controller.setActionObjectManager(null);

		const total = typeof this.options.value.total !== "undefined" ?
			this.options.value.total : 0;

		// This triggers an invalidate, which updates the grid
		this.dataview.grid.setTotalCount(total);

		// Insert any data sent from server, so invalidate finds data already
		if(this.options.value.rows && this.options.value.num_rows)
		{
			this.controller.loadInitialData(
				this.options.value.dataStorePrefix,
				this.options.value.row_id,
				this.options.value.rows
			);
			// Remove, to prevent duplication
			delete this.options.value.rows;
			// This triggers an invalidate, which updates the grid
			this.dataview.grid.setTotalCount(total);
		}
		else
		{
			// Trigger the initial update
			this.controller.update();
		}

		// Write something inside the column headers
		for (var i = 0; i < et2_historylog.columns.length; i++)
		{
			jQuery(this.dataview.getHeaderContainerNode(i)).text(et2_historylog.columns[i].caption);
		}

		// Register a resize callback
		jQuery(window).on('resize.' +this.options.value.app + this.options.value.id, function()
		{
			this.dataview.resize();
		}.bind(this));
	}

	/**
	 * Destroys all
	 */
	destroy( )
	{
		// Unbind, if bound
		if(this.options.value && !this.options.value.id)
		{
			jQuery(window).off('.' +this.options.value.app + this.options.value.id);
		}

		// Free the widgets
		for(let i = 0; i < et2_historylog.columns.length; i++)
		{
			if(et2_historylog.columns[i].widget) et2_historylog.columns[i].widget.destroy();
		}
		for(let key in this.fields)
		{
			this.fields[key].widget.destroy();
		}

		// Free the grid components
		if(this.dataview) this.dataview.destroy();
		if(this.controller) this.controller.destroy();

		super.destroy();
	}

	/**
	 * Create all needed widgets for new / old values
	 */
	createWidgets( )
	{

		// Constant widgets - first 3 columns
		for(let i = 0; i < et2_historylog.columns.length; i++)
		{
			const column = et2_historylog.columns[i];
			if(column.widget_type)
			{
				// Status ID is allowed to be remapped to something else.  Only affects the widget ID though
				let attrs = {'readonly': true, 'id': (i == et2_historylog.FIELD ? this.options.status_id : column.id)};
				column.widget = loadWebComponent(column.widget_type, attrs, this);
				column.nodes = jQuery(column.widget.getDetachedNodes());
			}
		}

		// Add in handling for links
		if(typeof this.options.value['status-widgets']['~link~'] == 'undefined')
		{
			et2_historylog.columns[et2_historylog.FIELD].widget.select_options.push({
				value: '~link~',
				label: this.egw().lang('link')
			});
			this.options.value['status-widgets']['~link~'] = 'link';
		}

		// Add in handling for files
		if(typeof this.options.value['status-widgets']['~file~'] == 'undefined')
		{
			et2_historylog.columns[et2_historylog.FIELD].widget.select_options.push({
				value: '~file~',
				label: this.egw().lang('File')
			});
			this.options.value['status-widgets']['~file~'] = 'vfs';
		}

		// Add in handling for user-agent & action
		if(typeof this.options.value['status-widgets']['user_agent_action'] == 'undefined')
		{
			et2_historylog.columns[et2_historylog.FIELD].widget.select_options.push({
				value: 'user_agent_action',
				label: this.egw().lang('User-agent & action')
			});
		}

		// Per-field widgets - new value & old value
		this.fields = {};

		let labels = et2_historylog.columns[et2_historylog.FIELD].widget.select_options;

		// Custom fields - Need to create one that's all read-only for proper display
		let cf_widget = <et2_customfields_list>et2_createWidget('customfields', {'readonly':true}, this);
		cf_widget.loadFields();
		// Override this or it may damage the real values
		cf_widget.getValue = function() {return null;};
		for(let key in cf_widget.widgets)
		{
			// Add label
			let option = (<SelectOption[]>labels).find(option => option.value == et2_customfields_list.PREFIX + key);
			if(option && !option.label)
			{
				option.label = cf_widget.options.customfields[key].label;
			}
			else
			{
				labels.push({
					value: et2_customfields_list.PREFIX + key,
					label: cf_widget.options.customfields[key].label
				});
			}

			// If it doesn't support detached nodes, just treat it as text
			if(cf_widget.widgets[key].getDetachedNodes)
			{
				var nodes = cf_widget.widgets[key].getDetachedNodes();
				for(var i = 0; i < nodes.length; i++)
				{
					if(nodes[i] == null)
					{
						nodes.splice(i, 1);
					}
				}

				// Save to use for each row
				this.fields[et2_customfields_list.PREFIX + key] = {
					attrs: cf_widget.widgets[key].options,
					widget: cf_widget.widgets[key],
					nodes: jQuery(nodes)
				};
			}
		}

		// Add all cf labels
		et2_historylog.columns[et2_historylog.FIELD].widget.set_select_options(labels);

		// From app
		for(var key in this.options.value['status-widgets'])
		{
			let attrs = jQuery.extend({'readonly': true, 'id': key}, this.getArrayMgr('modifications').getEntry(key));
			const field = attrs.type || this.options.value['status-widgets'][key];
			const options = null;
			const widget = this._create_widget(key, field, attrs, options);
			if(widget === null)
			{
				continue;
			}
			if(widget.instanceOf(et2_selectbox)) widget.options.multiple = true;
			widget.transformAttributes(attrs);

			// Save to use for each row
			let nodes = widget._children.length ? [] : jQuery(widget.getDetachedNodes());
			for(let i = 0; i < widget._children.length; i++)
			{
				// @ts-ignore
				nodes.push(jQuery(widget._children[i].getDetachedNodes()));
			}
			this.fields[key] = {
				attrs: attrs,
				widget: widget,
				nodes: nodes
			};
		}
		// Widget for text diffs
		const diff = et2_createWidget('diff', {}, this);
		this.diff = {
		// @ts-ignore
			widget: diff,
			nodes: jQuery(diff.getDetachedNodes())
		};
	}

	_create_widget(key, field, attrs, options)
	{
		let widget = null;

		// If field has multiple parts (is object) and isn't an obvious select box
		if(typeof field === 'object')
		{
			// Check for multi-part statuses needing multiple widgets
			let need_box = false;//!this.getArrayMgr('sel_options').getEntry(key);
			for(let j in field)
			{
				// Require widget to be a widget, to avoid invalid widgets
				// (and template, which is a widget and an infolog todo status)
				if(et2_registry[field[j]] && ['template'].indexOf(field[j]) < 0 || customElements.get(field[j]) ||
					typeof field[j] == "object" && typeof field[j].value == "undefined")
				{
					need_box = true;
					break;
				}
			}

			if(need_box)
			{
				// Multi-part value needs multiple widgets
				widget = loadWebComponent('et2-vbox', attrs, this);
				for(let i in field)
				{
					let type = field[i];
					const child_attrs = jQuery.extend({readonly: true}, attrs);
					if(typeof type === 'object')
					{
						child_attrs['select_options'] = cleanSelectOptions(field[i]);
						type = 'et2-select';
					}
					else
					{
						delete child_attrs['select_options'];
					}
					child_attrs.id = i;
					const child = this._create_widget(i, type, child_attrs, options);
					widget.addChild(child);
					child.transformAttributes(child_attrs);
				}
			}
			else
			{
				attrs['select_options'] = cleanSelectOptions(field);
			}
		}
		// Check for options after the type, ex: link-entry:infolog
		else if (field.indexOf(':') > 0)
		{
			var options = field.split(':');
			field = options.shift();
		}

		if(widget === null)
		{
			// Try to find a webcomponent first
			const tries = [field, "et2-" + field + "_ro", "et2-" + field];
			for(let i = 0; i < tries.length && !widget; i++)
			{
				if(typeof window.customElements.get(tries[i]) !== "undefined")
				{
					widget = loadWebComponent(tries[i], attrs, this);
				}
			}
			if(!widget)
			{
				widget = et2_createWidget(typeof field === 'string' ? field : 'select', attrs, this);
				if(typeof field === "string")
				{
					console.log("History specified legacy widget '" + field + "' for " + key + ".  Please change in PHP source.");
				}
			}
		}

		if(!widget.instanceOf(et2_IDetachedDOM))
		{
			this.egw().debug("warn", this, "Invalid widget " + field + " for " + key + ".  Status widgets must implement et2_IDetachedDOM.");
			return null;
		}

		// Parse / set legacy options
		if(options)
		{
			const mgr = this.getArrayMgr("content");
			let legacy = widget.constructor.legacyOptions || [];
			for(let i = 0; i < options.length && i < legacy.length; i++)
			{
				// Not set
				if(options[i] === "") continue;

				const attr = widget.attributes[legacy[i]] || {};
				let attrValue = options[i];

				// If the attribute is marked as boolean, parse the
				// expression as bool expression.
				if (attr.type === "boolean")
				{
					attrValue = mgr.parseBoolExpression(attrValue);
				}
				else
				{
					attrValue = mgr.expandName(attrValue);
				}
				attrs[legacy[i]] = attrValue;
				if(typeof widget['set_'+legacy[i]] === 'function')
				{
					widget['set_'+legacy[i]].call(widget, attrValue);
				}
				else
				{
					widget.options[legacy[i]] = attrValue;
				}
			}
		}
		return widget;
	}

	getDOMNode( _sender)
	{
		if (_sender == this)
		{
				return this.div[0];
		}

		for (let i = 0; i < et2_historylog.columns.length; i++)
		{
			if (_sender == et2_historylog.columns[i].widget)
			{
				return this.dataview.getHeaderContainerNode(i);
			}
		}
		return null;
	}


	dataFetch( _queriedRange, _callback, _context)
	{
		// Skip getting data if there's no ID
		if(!this.value.id) return;

		// Set num_rows to fetch via nextmatch
		if ( this.options.value['num_rows'] )
			_queriedRange['num_rows'] = this.options.value['num_rows'];

		const historylog = this;
		// Pass the fetch call to the API
		this.egw().dataFetch(
			this.getInstanceManager().etemplate_exec_id,
			_queriedRange,
			this._filters,
			this.id,
			function(_response) {
				_callback.call(this,_response);
			},
			_context,
			[]
		);
	}


	// Needed by interface
	dataRegisterUID( _uid, _callback, _context)
	{
		this.egw().dataRegisterUID(_uid, _callback, _context, this.getInstanceManager().etemplate_exec_id,
                                this.id);
	}

	dataUnregisterUID( _uid, _callback, _context)
	{
		// Needed by interface
	}

	/**
	 * The row callback gets called by the gridview controller whenever
	 * the actual DOM-Nodes for a node with the given data have to be
	 * created.
	 *
	 * @param {type} _data
	 * @param {type} _row
	 * @param {type} _idx
	 * @param {type} _entry
	 */
	rowCallback( _data, _row, _idx, _entry)
	{
		let tr = _row.getDOMNode();
		jQuery(tr).attr("valign","top");

		let row = this.dataview.rowProvider.getPrototype("default");
		let self = this;
		jQuery("div", row).each(function (i) {
			let nodes : any[] | JQuery = [];
			let widget = et2_historylog.columns[i].widget;
			let value = _data[et2_historylog.columns[i].id];
			if(et2_historylog.OWNER === i && _data['share_email'])
			{
				// Show share email instead of owner
				widget = undefined;
				value = _data['share_email'];
			}
			// Get widget from list, unless it needs a diff widget
			if((typeof widget == 'undefined' || widget == null) && typeof self.fields[_data.status] != 'undefined' && (
					i < et2_historylog.NEW_VALUE ||
					i >= et2_historylog.NEW_VALUE && (
						self.fields[_data.status].nodes || !self._needsDiffWidget(_data['status'], _data[et2_historylog.columns[et2_historylog.OLD_VALUE].id])
					)
			))
			{
				widget = self.fields[_data.status].widget;
				if(widget && typeof window.customElements.get(widget.localName) != "undefined")
				{
					nodes = widget.clone() // Note: Slower than cloneNode(), but simpler to deal with all the different widgets;
					widget = nodes;
				}
				else if(!widget._children.length)
				{
					nodes = self.fields[_data.status].nodes.clone();
				}
			}
			// WebComponent IS the node
			else if(widget && typeof window.customElements.get(widget.localName) != "undefined")
			{
				nodes = widget.clone() // Note: Slower than cloneNode(), but simpler to deal with all the different widgets;
				widget = nodes;
			}
			else if(widget)
			{
				nodes = et2_historylog.columns[i].nodes.clone();
			}
			else if((
					// Already parsed & cached
					typeof _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id] == "object" &&
					typeof _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id] != "undefined" &&
					_data[et2_historylog.columns[et2_historylog.NEW_VALUE].id] !== null) ||	// typeof null === 'object'
				// Large old value
				self._needsDiffWidget(_data['status'], _data[et2_historylog.columns[et2_historylog.OLD_VALUE].id]) ||
				// Large new value
				self._needsDiffWidget(_data['status'], _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id]))
			{
				// Large text value - span both columns, and show a nice diff
				let jthis = jQuery(this);
				if(i === et2_historylog.NEW_VALUE)
				{
					// Diff widget
					widget = self.diff.widget;
					nodes = self.diff.nodes.clone();

					if(widget) widget.setDetachedAttributes(nodes, {
						value: value,
						label: jthis.parents("td").prev().text()
					});

					self._spanValueColumns(jthis);
				}
			}
			else
			{
				// No widget fallback - display actual value
				nodes = jQuery('<span>').text(value === null ? '' : value);
			}
			if(widget)
			{
				if(widget._children.length)
				{
					// Multi-part values
					for(var j = 0; j < widget._children.length; j++)
					{
						const id = widget._children[j].id;
						const widget_value = value ? value[id] || "" : "";
						widget._children[j].setDetachedAttributes(nodes[j], {value: widget_value});
					}
				}
				else
				{
					widget.setDetachedAttributes(nodes, {value:value});
				}
			}
			jQuery(this).append(nodes);
		});
		jQuery(tr).append(row.children());

		return tr;
	}

	/**
	 * How to tell if the row needs a diff widget or not
	 *
	 * @param {string} columnName
	 * @param {string} value
	 * @returns {Boolean}
	 */
	_needsDiffWidget( columnName, value)
	{
		if(typeof value !== "string" && value)
		{
			this.egw().debug("warn", "Crazy diff value", value);
			return false;
		}
		return value === '***diff***';
	}

	/**
	 * Make a single row's new value cell span across both new value and old value
	 * columns.  Used for diff widget.
	 *
	 * @param {jQuery} row jQuery wrapped row node
	 */
	_spanValueColumns(row)
	{
		// Stretch column 4
		row.parents("td").attr("colspan", 2)
			.css("border-right", "none");
		row.css("width", (
			this.dataview.getColumnMgr().getColumnWidth(et2_historylog.NEW_VALUE) +
			this.dataview.getColumnMgr().getColumnWidth(et2_historylog.OLD_VALUE) - 10) + 'px');

		// Skip column 5
		row.parents("td").next().remove();
	}

	_resize()
	{
		let tab = this.get_tab_info();
		if(this.dataview)
		{
			const style = getComputedStyle(tab.contentDiv);
			// -# to avoid scrollbars
			this.dataview.resize(
				Math.min(
					window.innerWidth - 15,
					parseInt(style.width)
				) - 5,
				parseInt(style.height) - parseInt(style.paddingTop) - parseInt(style.paddingBottom) - 5
			);
		}
	}

	resize(_height)
	{
		
		this._resize();
		// Resize diff widgets to match new space
		if(this.dataview)
		{
			const columns = this.dataview.getColumnMgr();
			jQuery('.et2_diff', this.div).closest('.innerContainer')
				.width(columns.getColumnWidth(et2_historylog.NEW_VALUE) + columns.getColumnWidth(et2_historylog.OLD_VALUE));
		}
	}
}
et2_register_widget(et2_historylog, ['historylog']);