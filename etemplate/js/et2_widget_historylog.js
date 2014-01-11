/**
 * EGroupware eTemplate2 - JS History log
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

"use strict";

/*egw:uses
        jquery.jquery;
        jquery.jquery-ui;
        et2_core_valueWidget;

	// Include the grid classes
        et2_dataview;
*/

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
var et2_historylog = et2_valueWidget.extend([et2_IDataProvider],
{
	attributes: {
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
		}
	},

	legacyOptions: ["status_id"],
	columns: [
		{'id': 'user_ts', caption: 'Date', 'width': '120px', widget_type: 'date-time'},
		{'id': 'owner', caption: 'User', 'width': '150px', widget_type: 'select-account'},
		{'id': 'status', caption: 'Changed', 'width': '120px', widget_type: 'select'},
		{'id': 'new_value', caption: 'New Value', 'width': 'auto'},
		{'id': 'old_value', caption: 'Old Value', 'width': 'auto'}
	],

	TIMESTAMP: 0, OWNER: 1, FIELD: 2, NEW_VALUE: 3, OLD_VALUE: 4,
	
	/**
	 * Constructor
	 * 
	 * @memberOf et2_historylog
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.div = $j(document.createElement("div"))
			.addClass("et2_historylog");

		this.innerDiv = $j(document.createElement("div"))
			.appendTo(this.div);
	},

	set_status_id: function(_new_id) {
		this.options.status_id = _new_id;
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Find the tab widget, if there is one
		var tabs = this;
		do {
			tabs = tabs._parent;
		} while (tabs != this.getRoot() && tabs._type != 'tabbox');
		if(tabs != this.getRoot())
		{
			// Find the tab index
			for(var i = 0; i < tabs.tabData.length; i++)
			{
				// Find the tab
				if(tabs.tabData[i].contentDiv.has(this.div).length)
				{
					// Bind the action to when the tab is selected
					var handler = function(e) {
						e.data.div.unbind("click.history");
						e.data.history.finishInit();
						e.data.history.dynheight.update(function(_w, _h) {
							e.data.history.dataview.resize(_w, _h);
						});
					};
					tabs.tabData[i].flagDiv.bind("click.history",{"history": this, div: tabs.tabData[i].flagDiv}, handler);
					break;
				}
			}
		}
		else
		{
			this.finishInit();
		}
	},

	/**
	 * Finish initialization which was skipped until tab was selected
	 */
	finishInit: function() {
		// No point with no ID
		if(!this.options.value || !this.options.value.id)
		{
			return;
		}
		this._filters = {
			record_id: this.options.value.id,
			appname: this.options.value.app,
			get_rows: 'historylog::get_rows'
		};

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(this.div.parent(),
				this.innerDiv, 250
		);

		// Create the outer grid container
		this.dataview = new et2_dataview(this.innerDiv, this.egw());
		var dataview_columns = [];
		for (var i = 0; i < this.columns.length; i++)
		{
			dataview_columns[i] = {"id": this.columns[i].id, "caption": this.columns[i].caption, "width":this.columns[i].width};
		}
		this.dataview.setColumns(dataview_columns);

		// Create widgets for columns that stay the same, and set up varying widgets
		this.createWidgets();

		// Create the gridview controller
		var linkCallback = function() {};
		this.controller = new et2_dataview_controller(null, this.dataview.grid,
			this, this.rowCallback, linkCallback, this,
			null
		);

		// Trigger the initial update
		this.controller.update();

		// Write something inside the column headers
		for (var i = 0; i < this.columns.length; i++)
		{
			$j(this.dataview.getHeaderContainerNode(i)).text(this.columns[i].caption);
		}

		// Register a resize callback
		var self = this;
		$j(window).on('resize.' +this.options.value.app + this.options.value.id, function() {
			self.dynheight.update(function(_w, _h) {
				self.dataview.resize(_w, _h);
			});
		});
	},

	/**
	 * Destroys all 
	 */
	destroy: function() {
		// Unbind 
		$j(window).off('.' +this.options.value.app + this.options.value.id);
		
		// Free the widgets
		for(var i = 0; i < this.columns.length; i++)
		{
			if(this.columns[i].widget) this.columns[i].widget.destroy();
		}
		for(var key in this.fields)
		{
			this.fields[key].widget.destroy();
		}
		if(this.diff) this.diff.widget.destroy();
		
		// Free the grid components
		if(this.dataview) this.dataview.free();
		if(this.rowProvider) this.rowProvider.free();
		if(this.controller) this.controller.free();
		if(this.dynheight) this.dynheight.free();

		this._super.apply(this, arguments);
	},

	/**
	 * Create all needed widgets for new / old values
	 */
	createWidgets: function() {

		// Constant widgets - first 3 columns
		for(var i = 0; i < this.columns.length; i++)
		{
			if(this.columns[i].widget_type)
			{
				// Status ID is allowed to be remapped to something else.  Only affects the widget ID though
				var attrs = {'readonly': true, 'id': (i == this.FIELD ? this.options.status_id : this.columns[i].id)};
				this.columns[i].widget = et2_createWidget(this.columns[i].widget_type, attrs, this);
				this.columns[i].widget.transformAttributes(attrs);
				this.columns[i].nodes = $j(this.columns[i].widget.getDetachedNodes());
			}
		}

		// Add in handling for links
		if(typeof this.options.value['status-widgets']['~link~'] == 'undefined')
		{
			this.columns[this.FIELD].widget.optionValues['~link~'] = this.egw().lang('link');
			this.options.value['status-widgets']['~link~'] = 'link';
		}

		// Add in handling for files
		if(typeof this.options.value['status-widgets']['~file~'] == 'undefined')
		{
			this.columns[this.FIELD].widget.optionValues['~file~'] = this.egw().lang('File');
			this.options.value['status-widgets']['~file~'] = 'vfs';
		}

		// Add in handling for user-agent & action
		if(typeof this.options.value['status-widgets']['user_agent_action'] == 'undefined')
		{
			this.columns[this.FIELD].widget.optionValues['user_agent_action'] = this.egw().lang('User-agent & action');
		}

		// Per-field widgets - new value & old value
		this.fields = {};

		var labels = this.columns[this.FIELD].widget.optionValues;

		// Custom fields - Need to create one that's all read-only for proper display
		var cf_widget = et2_createWidget('customfields', {'readonly':true}, this);
		cf_widget.loadFields();
		// Override this or it may damage the real values
		cf_widget.getValue = function() {return null;};
		for(var key in cf_widget.widgets)
		{
			// Add label
			labels[cf_widget.prefix + key] = cf_widget.options.customfields[key].label;

			// If it doesn't support detached nodes, just treat it as text
			if(cf_widget.widgets[key].getDetachedNodes)
			{
				var nodes = cf_widget.widgets[key].getDetachedNodes();
				for(var i = 0; i < nodes.length; i++)
				{
					if(nodes[i] == null) nodes.splice(i,1);
				}
				
				// Save to use for each row
				this.fields[cf_widget.prefix + key] = {
					attrs: cf_widget.widgets[key].options,
					widget: cf_widget.widgets[key],
					nodes: jQuery(nodes)
				};
			}
		}
		// Add all cf labels
		this.columns[this.FIELD].widget.set_select_options(labels);

		// From app
		for(var key in this.options.value['status-widgets'])
		{
			var field = this.options.value['status-widgets'][key];
			var attrs = {'readonly': true, 'id': key};
			var options = null;
			if(typeof field == 'object')
			{
				attrs['select_options'] = field;
			}
			// Check for options after the type, ex: link-entry:infolog
			else if (field.indexOf(':') > 0)
			{
				var options = field.split(':');
				field = options.shift();
			}
			
			var widget = et2_createWidget(typeof field == 'string' ? field : 'select', attrs, this);

			// Parse / set legacy options
			if(options)
			{
				var mgr = this.getArrayMgr("content");
				for(var i = 0; i < options.length && i < widget.legacyOptions.length; i++)
				{
					// Not set
					if(options[i] == "") continue;

					var attr = widget.attributes[widget.legacyOptions[i]];
					var attrValue = options[i];

					// If the attribute is marked as boolean, parse the
					// expression as bool expression.
					if (attr.type == "boolean")
					{
						attrValue = mgr.parseBoolExpression(attrValue);
					}
					else
					{
						attrValue = mgr.expandName(attrValue);
					}
					attrs[widget.legacyOptions[i]] = attrValue;
					if(typeof widget['set_'+widget.legacyOptions[i]] == 'function')
					{
						widget['set_'+widget.legacyOptions[i]].call(widget, attrValue);
					}
					else
					{
						widget.options[widget.legacyOptions[i]] = attrValue;
					}
				}
			}

			if(widget.instanceOf(et2_selectbox)) widget.options.multiple = true;
			widget.transformAttributes(attrs);

			// Save to use for each row
			this.fields[key] = {
				attrs: attrs,
				widget: widget,
				nodes: jQuery(widget.getDetachedNodes())
			};
		}
		// Widget for text diffs
		var diff = et2_createWidget('diff', {}, this);
		this.diff = {
			widget: diff,
			nodes: jQuery(diff.getDetachedNodes())
		};
	},

	getDOMNode: function(_sender) {
                if (_sender == this)
                {
                        return this.div[0];
                }

                for (var i = 0; i < this.columns.length; i++)
                {
                        if (_sender == this.columns[i].widget)
                        {
                                return this.dataview.getHeaderContainerNode(i);
                        }
                }
		return null;
	},


	dataFetch: function (_queriedRange, _callback, _context) {
		// Skip getting data if there's no ID
		if(!this.value.id) return;
		
		// Pass the fetch call to the API
		this.egw().dataFetch(
			this.getInstanceManager().etemplate_exec_id,
			_queriedRange,
			this._filters,
			this.id,
			_callback,
			_context
		);
	},


	// Needed by interface
	dataRegisterUID: function (_uid, _callback, _context) {
		this.egw().dataRegisterUID(_uid, _callback, _context, this.getInstanceManager().etemplate_exec_id,
                                this.id);
	},

	dataUnregisterUID: function (_uid, _callback, _context) {
		// Needed by interface
	},

	/**
	 * The row callback gets called by the gridview controller whenever
	 * the actual DOM-Nodes for a node with the given data have to be
	 * created.
	 */
	rowCallback: function(_data, _row, _idx, _entry) {
		var tr = _row.getDOMNode();
		jQuery(tr).attr("valign","top");

		var row = this.dataview.rowProvider.getPrototype("default");
		var self = this;
		$j("div", row).each(function (i) {
			var nodes = [];
			var widget = self.columns[i].widget;
			if(typeof widget == 'undefined' && typeof self.fields[_data.status] != 'undefined')
			{
				nodes = self.fields[_data.status].nodes.clone();
				widget = self.fields[_data.status].widget;
			}
			else if (widget)
			{
				nodes = self.columns[i].nodes.clone();
			}
			else if (self._needsDiffWidget(_data['status'], _data[self.columns[self.OLD_VALUE].id]) ||
				self._needsDiffWidget(_data['status'], _data[self.columns[self.NEW_VALUE].id]))
			{
				// Large text value - span both columns, and show a nice diff
				var jthis = jQuery(this);
				if(i == self.NEW_VALUE)
				{
					// Diff widget
					widget = self.diff.widget;
					nodes = self.diff.nodes.clone();

					_data[self.columns[i].id] = {
						'old': _data[self.columns[i+1].id],
						'new': _data[self.columns[i].id]
					};

					// Skip column 4
					jthis.parents("td").attr("colspan", 2)
						.css("border-right", "none");
					jthis.css("width", (self.dataview.columnMgr.columnWidths[i] + self.dataview.columnMgr.columnWidths[i+1]-10)+'px');

					if(widget) widget.setDetachedAttributes(nodes, {
						value:_data[self.columns[i].id],
						label: jthis.parents("td").prev().text()
					});

					// Skip column 4
					jthis.parents("td").next().remove();
				}
			}
			else
			{
				// No widget fallback - display actual value
				nodes = '<span>'+_data[self.columns[i].id] + '</span>';
			}
			if(widget) widget.setDetachedAttributes(nodes, {value:_data[self.columns[i].id]});
			$j(this).append(nodes);
		});
		$j(tr).append(row.children());

		return tr;
	},

	/**
	 * How to tell if the row needs a diff widget or not
	 */
	_needsDiffWidget: function(columnName, value) {
		if(typeof value !== "string")
		{
			this.egw().debug("warning", "Crazy diff value", value);
			return false;
		}
		return columnName == 'note' || columnName == 'description' || (value && (value.length > 50 || value.match(/\n/g)));
	},
});
et2_register_widget(et2_historylog, ['historylog']);
