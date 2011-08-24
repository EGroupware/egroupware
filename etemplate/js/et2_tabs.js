/**
 * EGroupware eTemplate2 - JS Tabs object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jsapi.egw;
	jquery.jquery;
	et2_widget;
*/

/**
 * Class which implements the tabbox-tag
 */ 
var et2_tabbox = et2_DOMWidget.extend({

	/**
	 * Currently selected tab
	 */
	selected_index: 0,
	init: function() {
		// Create the outer tabbox container
		this.container = $j(document.createElement("div"))
			.addClass("et2_tabbox");

		// Create the upper container for the tab flags
		this.flagContainer = $j(document.createElement("div"))
			.addClass("et2_tabheader")
			.appendTo(this.container);

		// Create the lower tab container
		this.tabContainer = $j(document.createElement("div"))
			.addClass("et2_tabs")
			.appendTo(this.container);

		this._super.apply(this, arguments);

		this.tabData = [];
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.container = null;
		this.flagContainer = null;
		this.tabData = [];
	},

	_readTabs: function(tabData, tabs) {
		var selected = "";
		this.selected_index = 0;
		var hidden = {};
		if (this.id)
		{
			// Set the value for this element
			var contentMgr = this.getArrayMgr("content");
			if (contentMgr != null) {
				var val = contentMgr.getValueForID(this.id);
				if (val !== null)
				{
					selected = val;
				}
			}
			contentMgr = this.getArrayMgr("readonlys");
			if (contentMgr != null) {
				var val = contentMgr.getValueForID(this.id);
				if (val !== null)
				{
					hidden = val;
				}
			}
		}
		var i = 0;
		et2_filteredNodeIterator(tabs, function(node, nodeName) {
			if (nodeName == "tab")
			{
				var index_name = et2_readAttrWithDefault(node, "id");
				var hide = false;
				if(index_name) {
					if(selected == index_name) this.selected_index = i;
					if(hidden[index_name]) {
						hide = true;
					}
				}
				tabData.push({
					"label": egw.lang(et2_readAttrWithDefault(node, "label", "Tab")),
					"widget": null,
					"contentDiv": null,
					"flagDiv": null,
					"hidden": hide
				});
			}
			else
			{
				throw("Error while parsing: Invalid tag '" + nodeName +
					"' in tabs tag");
			}
			i++;
		}, this);
	},

	_readTabPanels: function(tabData, tabpanels) {
		var i = 0;
		et2_filteredNodeIterator(tabpanels, function(node, nodeName) {
			if (i < tabData.length)
			{
				// Create the widget corresponding to the given node
				tabData[i].widget = this.createElementFromNode(node,
					nodeName);
			}
			else
			{
				throw("Error while reading tabpanels tag, too many widgets!");
			}
			i++;
		}, this);
	},

	loadFromXML: function(_node) {
		// Get the tabs and tabpanels tags
		var tabsElems = et2_directChildrenByTagName(_node, "tabs");
		var tabpanelsElems = et2_directChildrenByTagName(_node, "tabpanels");

		if (tabsElems.length == 1 && tabpanelsElems.length == 1)
		{
			var tabs = tabsElems[0];
			var tabpanels = tabpanelsElems[0];

			var tabData = [];

			// Parse the "tabs" tag
			this._readTabs(tabData, tabs);

			// Read and create the widgets defined in the "tabpanels"
			this._readTabPanels(tabData, tabpanels);

			// Create the tab DOM-Nodes
			this.createTabs(tabData)
		}
		else
		{
			throw("Error while parsing tabbox, none or multiple tabs or tabpanels tags!");
		}
	},

	createTabs: function(tabData) {
		this.tabData = tabData;

		this.tabContainer.empty();
		this.flagContainer.empty();

		for (var i = 0; i < this.tabData.length; i++)
		{
			var entry = this.tabData[i];
			entry.flagDiv = $j(document.createElement("span"))
				.addClass("et2_tabflag")
				.text(entry.label)
				.appendTo(this.flagContainer);
			if(entry.hidden)
			{
				entry.flagDiv.hide();
			}
			else
			{
				entry.flagDiv.click({"tabs": this, "idx": i}, function(e) {
					e.data.tabs.setActiveTab(e.data.idx);
				});
			}
			entry.contentDiv = $j(document.createElement("div"))
				.addClass("et2_tabcntr")
				.appendTo(this.tabContainer);
		}

		this.setActiveTab(this.selected_index);
	},

	setActiveTab: function(_idx) {
		this.selected_index = _idx;

		// Remove the "active" flag from all tabs-flags
		$j(".et2_tabflag", this.flagContainer).removeClass("active");

		// Hide all tab containers
		this.tabContainer.children().hide();

		// Set the tab flag with the given index active and show the corresponding
		// container
		this.flagContainer.children(":eq(" + _idx + ")").addClass("active");
		this.tabContainer.children(":eq(" + _idx + ")").show();
	},

	getDOMNode: function(_sender) {
		if (_sender == this)
		{
			return this.container[0];
		}
		else
		{
			for (var i = 0; i < this.tabData.length; i++)
			{
				if (this.tabData[i].widget == _sender)
				{
					return this.tabData[i].contentDiv[0];
				}
			}

			return null;
		}
	},

	set_height: function(_value) {
		this.height = _value;

		this.tabContainer.css("height", _value);
	}

});

et2_register_widget(et2_tabbox, ["tabbox"]);
