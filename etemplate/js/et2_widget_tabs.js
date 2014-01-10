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
	et2_core_widget;
*/

/**
 * Class which implements the tabbox-tag
 * 
 * @augments et2_DOMWidget
 */ 
var et2_tabbox = et2_valueWidget.extend([et2_IInput],
{
	attributes: {
		'tabs': {
			'name': 'Tabs',
			'default': et2_no_init,
			'description': "Array of [extra] tabs.  Each tab needs {label:..., template:...}.  Additional optional keys are hidden and id, for access into content array"
		},
		'add_tabs': {
			'name': 'Add tabs',
			'default': false,
			'description': 'Set to true if tabs should be added to tabs from read from template, default false if not'
		}
	},

	/**
	 * Currently selected tab
	 */
	selected_index: 0,

	/**
	 * Construtor
	 * 
	 * @memberOf et2_tabbox
	 */
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
				var val = contentMgr.getEntry(this.id);
				if (val !== null)
				{
					selected = val;
				}
			}
			contentMgr = this.getArrayMgr("readonlys");
			if (contentMgr != null) {
				var val = contentMgr.getEntry(this.id);
				if (val !== null && typeof val !== 'undefined')
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
					"id": index_name,
					"label": this.egw().lang(et2_readAttrWithDefault(node, "label", "Tab")),
					"widget": null,
					"widget_options": {},
					"contentDiv": null,
					"flagDiv": null,
					"hidden": hide,
					"XMLNode": null,
					"promise": null
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
				// Store node for later evaluation
				tabData[i].XMLNode = node;
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
		var tabData = [];

		// Check for a parent height, we'll apply it to tab panels
		var height = et2_readAttrWithDefault(_node.parentNode, "height",null);
		if(height)
		{
			this.tabContainer.css("height", height);
		}

		// if no tabs set or they should be added to tabs from xml
		if (!this.options.tabs || this.options.add_tabs)
		{
			if (tabsElems.length == 1 && tabpanelsElems.length == 1)
			{
				var tabs = tabsElems[0];
				var tabpanels = tabpanelsElems[0];
	
				// Parse the "tabs" tag
				this._readTabs(tabData, tabs);
	
				// Read and create the widgets defined in the "tabpanels"
				this._readTabPanels(tabData, tabpanels);
			}
			else
			{
				this.egw().debug("error","Error while parsing tabbox, none or multiple tabs or tabpanels tags!",this);
			}
		}
		if (this.options.tabs)
		{
			var readonly = this.getArrayMgr("readonlys").getEntry(this.id) || {};
			for(var i = 0; i < this.options.tabs.length; i++)
			{
				var tab = this.options.tabs[i];
				var tab_id = tab.id || tab.template;
				var tab_options = {id: tab_id, template:tab.template};
				if(tab.id)
				{
					tab_options.content = tab.id;
				}
				tabData.push({
					"id": tab.id,
					"label": this.egw().lang(tab.label),
					"widget": null,
					"widget_options": tab_options,
					"contentDiv": null,
					"flagDiv": null,
					"hidden": typeof tab.hidden != "undefined" ? tab.hidden : readonly[tab_id] || false,
					"XMLNode": null,
					"promise": null
				});
			}
		}

		// Create the tab DOM-Nodes
		this.createTabs(tabData);
	},

	/**
	 * Load is finished, set up tabs to load on their own
	 */
	doLoadingFinished: function()
	{
		var tab_deferred = jQuery.Deferred();
		var promises = [];
		var tabs = this;

		// Specially process the selected index so it shows up right away
		this._loadTab(this.selected_index,promises);
		// We can do this and not wind up with 2 because child is a template,
		// which has special handling
		this._children[0].loadingFinished();
		
		// Apply parent now, which actually puts into the DOM
		this._super.apply(this, arguments);
		
		// Defer parsing & loading of other tabs until later
		window.setTimeout(function() {
			for (var i = 0; i < tabs.tabData.length; i++)
			{
				tabs._loadTab(i,promises);
			}
			jQuery.when(promises).then(function() {
				tab_deferred.resolve();
			});
		},0);

		return tab_deferred.promise();
	},

	/**
	 * Load & render a tab's content
	 */
	_loadTab: function(index,promises) {
		var tabData = this.tabData[index];
		if(!tabData || tabData.loaded) return;
		if(tabData.XMLNode != null)
		{
			tabData.widget = this.createElementFromNode(tabData.XMLNode,tabData.XMLNode.nodeName.toLowerCase());
			
			// Release the XML node
			tabData.XMLNode = null;
		}
		else if (tabData.widget_options)
		{
			tabData.widget = et2_createWidget('template',tabData.widget_options,this);
		}

		// Set loaded flag to not do this again, even if not fully done
		tabData.loaded = true;

		// loadingFinished() will be called either when the promise from doLoadingFinished is resolved,
		// or during the normal execution
	},

	/**
	 * Check for custom tabs
	 */
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// Add in settings that are objects
		var data = this.getArrayMgr("modifications").getEntry(this.id);
		for(var key in data)
		{
			if(typeof data[key] === 'object' && ! _attrs[key]) _attrs[key] = data[key];
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
				.appendTo(this.flagContainer);
			entry.flagDiv.text(entry.label || "Tab");
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

		// Check for a passed in value
		if(this.options.value)
		{
			this.selected_index = 0;
			for(var i = 0; i < this.tabData.length; i++)
			{
				if(this.tabData[i].id == this.options.value)
				{
					this.selected_index = i;
					break;
				}
			}
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
	},

	/**
	 * getValue has to return the value of the input widget
	 */
	getValue: function() {
		return this.tabData[this.selected_index].id;
	},

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty: function() {
		return this.selected_index != this.value;
	},

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty: function() 
	{
		this.value = this.selected_index;
	},
	isValid: function(messages) {
		return true;
	}
});
et2_register_widget(et2_tabbox, ["tabbox"]);
