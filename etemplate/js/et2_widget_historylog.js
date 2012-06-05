/**
 * eGroupWare eTemplate2 - JS History log
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
 */

var et2_historylog = et2_valueWidget.extend([et2_IDataProvider],{
	columns: [
		{'id': 'user_ts', caption: 'Date', 'width': '120px', widget_type: 'date-time'},
		{'id': 'owner', caption: 'User', 'width': '150px', widget_type: 'select-account'},
		{'id': 'status', caption: 'Changed', 'width': '120px', widget_type: 'select'},
		{'id': 'new_value', caption: 'New Value'},
		{'id': 'old_value', caption: 'Old Value'}
	],

	TIMESTAMP: 0, OWNER: 1, FIELD: 2, NEW_VALUE: 3, OLD_VALUE: 4,
	
	init: function() {
		this._super.apply(this, arguments);
		this.div = $j(document.createElement("div"))
			.addClass("et2_historylog");

		this.innerDiv = $j(document.createElement("div"))
			.appendTo(this.div);

		this._filters = {
			record_id: this.options.value.id,
			appname: this.options.value.app,
			get_rows: 'historylog::get_rows'
		};
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

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(this.egw().window,
				this.innerDiv, 250
		);

		// Create the outer grid container
		this.dataview = new et2_dataview(this.innerDiv, this.egw());
		this.dataview.setColumns(jQuery.extend(true, [],this.columns));

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
		$j(window).resize(function() {
			self.dynheight.update(function(_w, _h) {
				self.dataview.resize(_w, _h);
			});
		});
	},

	/**
	 * Destroys all 
	 */
	destroy: function() {
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

	createWidgets: function() {

		// Constant widgets - first 3 columns
		for(var i = 0; i < this.columns.length; i++)
		{
			if(this.columns[i].widget_type)
			{
				var attrs = {'readonly': true, 'id': this.columns[i].id};
				this.columns[i].widget = et2_createWidget(this.columns[i].widget_type, attrs, this);
				this.columns[i].widget.transformAttributes(attrs);
				this.columns[i].nodes = $j(this.columns[i].widget.getDetachedNodes());
			}
		}

		// Add in handling for links
		if(typeof this.options.value['status-widgets']['~link~'] == 'undefined')
		{
			this.columns[2].widget.optionValues['~link~'] = this.egw().lang('link');
			this.options.value['status-widgets']['~link~'] = 'link';
		}

		// Per-field widgets - new value & old value
		this.fields = {};

		// Custom fields - try to use an existing one
		var cf_widget = null
		var labels = {};
		this.getRoot().iterateOver(function(widget) { cf_widget = widget;}, this, et2_customfields_list);
		if(cf_widget == null)
		{
			cf_widget = et2_createWidget('customfields', {}, this);
		}
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
			if(typeof field == 'object') attrs['select-options'] = field;

			var widget = et2_createWidget(typeof field == 'string' ? field : 'select', attrs, this);
			widget.transformAttributes(attrs);

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
			else if (self._needsDiffWidget(_data['status'], _data[self.columns[i].id]))
			{
				// Large text value - span both columns, and show a nice diff
				var jthis = jQuery(this);
				if(i == 3)
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
					jthis.css("width", (self.dataview.columnMgr.columnWidths[i] + self.dataview.columnMgr.columnWidths[i+1]-8)+'px');

					if(widget) widget.setDetachedAttributes(nodes, {
						value:_data[self.columns[i].id],
						label: jthis.parents("td").prev().text()
					});
				}
				else if (i == 4)
				{
					// Skip column 4
					jthis.parents("td").remove();
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
		return columnName == 'note' || columnName == 'description' || value && value.length > 100
	},
});
et2_register_widget(et2_historylog, ['historylog']);
