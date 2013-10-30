/**
 * EGroupware eTemplate2 - JS file which contains the complete et2 module
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	// Include all widget classes here
	et2_widget_template;
	et2_widget_grid;
	et2_widget_box;
	et2_widget_hbox;
	et2_widget_groupbox;
	et2_widget_split;
	et2_widget_button;
	et2_widget_color;
	et2_widget_description;
	et2_widget_textbox;
	et2_widget_number;
	et2_widget_url;
	et2_widget_selectbox;
	et2_widget_checkbox;
	et2_widget_radiobox;
	et2_widget_date;
	et2_widget_dialog;
	et2_widget_diff;
	et2_widget_dropdown_button;
	et2_widget_styles;
	et2_widget_favorites;
	et2_widget_html;
	et2_widget_htmlarea;
	et2_widget_tabs;
	et2_widget_taglist;
	et2_widget_toolbar;
	et2_widget_tree;
	et2_widget_historylog;
	et2_widget_hrule;
	et2_widget_image;
	et2_widget_iframe;
	et2_widget_file;
	et2_widget_link;
	et2_widget_progress;
	et2_widget_portlet;
	et2_widget_selectAccount;
	et2_widget_ajaxSelect;
	et2_widget_vfs;
	et2_widget_itempicker;

	et2_extension_nextmatch;
	et2_extension_customfields;

	// Requirements for the etemplate2 object
	et2_core_common;
	et2_core_xml;
	et2_core_arrayMgr;
	et2_core_interfaces;
	et2_core_legacyJSFunctions;

	// Include the client side api core
	jsapi.egw_core;
	jsapi.egw_json;
*/

/**
 * The etemplate2 class manages a certain etemplate2 instance.
 *
 * @param _container is the DOM-Node into which the DOM-Nodes of this instance
 * 	should be inserted
 * @param _submitURL is the URL to which the form data should be submitted.
 */
function etemplate2(_container, _menuaction)
{
	if (typeof _menuaction == "undefined")
	{
		_menuaction = "etemplate_new::ajax_process_content";
	}

	// Copy the given parameters
	this.DOMContainer = _container;
	this.menuaction = _menuaction;

	// Unique ID to prevent DOM collisions across multiple templates
	this.uniqueId = _container.getAttribute("id") ? _container.getAttribute("id").replace('.','-') : '';

	// Preset the object variable
	this.widgetContainer = null;

	// List of templates (XML) that are known, but not used.  Indexed by id.
	this.templates = {};

	// Connect to the window resize event
	$j(window).resize(this, function(e) {e.data.resize();});
}

/**
 * Calls the resize event of all widgets
 */
etemplate2.prototype.resize = function()
{
	if (this.widgetContainer)
	{
		// Call the "resize" event of all functions which implement the
		// "IResizeable" interface
		this.widgetContainer.iterateOver(function(_widget) {
			_widget.resize();
		}, this, et2_IResizeable);
	}
};

/**
 * Clears the current instance.
 */
etemplate2.prototype.clear = function()
{
	if (this.widgetContainer != null)
	{
		// Un-register handler
		this.widgetContainer.egw().unregisterJSONPlugin(etemplate2_handle_assign, this, 'assign');

		this.widgetContainer.free();
		this.widgetContainer = null;
	}
	$j(this.DOMContainer).empty();

	// Remove self from the index
	for(name in this.templates)
	{
		if(typeof etemplate2._byTemplate[name] == "undefined") continue;
		for(var i = 0; i < etemplate2._byTemplate[name].length; i++)
		{
			if(etemplate2._byTemplate[name][i] == this)
			{
				etemplate2._byTemplate[name].splice(i,1);
			}
		}
	}
};

/**
 * Creates an associative array containing the data array managers for each part
 * of the associative data array. A part is something like "content", "readonlys"
 * or "sel_options".
 */
etemplate2.prototype._createArrayManagers = function(_data)
{
	if (typeof _data == "undefined")
	{
		_data = {};
	}

	// Create all neccessary _data entries
	var neededEntries = ["content", "sel_options", "readonlys", "modifications",
		"validation_errors"];
	for (var i = 0; i < neededEntries.length; i++)
	{
		if (typeof _data[neededEntries[i]] == "undefined" || !_data[neededEntries[i]])
		{
			egw.debug("log", "Created not passed entry '" + neededEntries[i] +
				"' in data array.");
			_data[neededEntries[i]] = {};
		}
	}

	var result = {};

	// Create an array manager object for each part of the _data array.
	for (var key in _data)
	{
		switch (key) {
			case "etemplate_exec_id":	// already processed
			case "app_header":
				break;
			case "readonlys":
				result[key] = new et2_readonlysArrayMgr(_data[key]);
				break;
			default:
				result[key] = new et2_arrayMgr(_data[key]);
		}
	}

	return result;
};

/**
 * Loads the template from the given URL and sets the data object
 */
etemplate2.prototype.load = function(_name, _url, _data, _callback)
{
	egw().debug("info", "Loaded data", _data);
	var currentapp = _data.currentapp || window.egw_appName;

	// Register a handler for AJAX responses
	egw(currentapp, window).registerJSONPlugin(etemplate2_handle_assign, this, 'assign');

	if(console.groupCollapsed)
	{
		egw.window.console.groupCollapsed("Loading %s", _name);
	}

	// require necessary translations from server, if not already loaded
	if (!$j.isArray(_data.langRequire)) _data.langRequire = [];
	egw(currentapp, window).langRequire(window, _data.langRequire, function()
	{
		// Appname should be first part of the template name
		var split = _name.split('.');
		var appname = split[0];

		// Initialize application js
		var app_callback = null;
		// Only initialize once
		if(typeof app[appname] == "function")
		{
			(function() { new app[appname]();}).call();
		}
		else if (typeof app[appname] !== "object")
		{
			egw.debug("warn", "Did not load '%s' JS object",appname);
		}
		if(typeof app[appname] == "object")
		{
			app_callback = function(et2) {app[appname].et2_ready(et2);};
		}

		// Create the document fragment into which the HTML will be injected
		var frag = document.createDocumentFragment();


		// Clear any existing instance
		this.clear();

		// Create the basic widget container and attach it to the DOM
		this.widgetContainer = new et2_container(null);
		this.widgetContainer.setApiInstance(egw(currentapp, egw.elemWindow(this.DOMContainer)));
		this.widgetContainer.setInstanceManager(this);
		this.widgetContainer.setParentDOMNode(this.DOMContainer);

		// store the id to submit it back to server
		if(_data) {
			this.etemplate_exec_id = _data.etemplate_exec_id;
			// set app_header
			window.egw_app_header(_data.app_header);
		}

		var _load = function() {
			egw.debug("log", "Loading template...");

			// Read the XML structure of the requested template
			this.widgetContainer.loadFromXML(this.templates[_name || missing_name]);

			// List of Promises from widgets that are not quite fully loaded
			var deferred = [];

			// Inform the widget tree that it has been successfully loaded.
			this.widgetContainer.loadingFinished(deferred);

			// Insert the document fragment to the DOM Container
			this.DOMContainer.appendChild(frag);

			// Add into indexed list
			if(typeof etemplate2._byTemplate[_name] == "undefined")
			{
				etemplate2._byTemplate[_name] = [];
			}
			etemplate2._byTemplate[_name].push(this);

			if(console.groupEnd)
			{
				egw.window.console.groupEnd();
			}
			if(deferred.length > 0)
			{
				var still_deferred = 0;
				$j(deferred).each(function() {if(this.state() == "pending") still_deferred++;});
				if(still_deferred > 0)
				{
					egw.debug("log", "Template loaded, waiting for %d/%d deferred to finish...",still_deferred, deferred.length);
				}
			}

			// Wait for everything to be loaded, then finish it up
			jQuery.when.apply(null, deferred).done(jQuery.proxy(function() {
				egw.debug("log", "Finished loading %s, triggering load event", _name);

				// Trigger the "resize" event
				this.resize();

				// Tell others about it
				if(typeof _callback == "function")
				{
					_callback.call(window,this);
				}
				if(_callback != app_callback)
				{
					app_callback.call(window,this);
				}

				$j(this.DOMContainer).trigger('load', this);
			},this));
			};


		// Load & process
		if(!this.templates[_name])
		{
			// Asynchronously load the XET file
			et2_loadXMLFromURL(_url, function(_xmldoc) {

				// Scan for templates and store them
				for(var i = 0; i < _xmldoc.childNodes.length; i++) {
					var template = _xmldoc.childNodes[i];
					if(template.nodeName.toLowerCase() != "template") continue;
					this.templates[template.getAttribute("id")] = template;
					if(!_name) missing_name = template.getAttribute("id");
				}
				_load.apply(this,[]);
			}, this);

			// Split the given data into array manager objects and pass those to the
			// widget container - do this here because file is loaded async
			this.widgetContainer.setArrayMgrs(this._createArrayManagers(_data));
		}
		else
		{
			// Set array managers first, or errors will happen
			this.widgetContainer.setArrayMgrs(this._createArrayManagers(_data));

			// Already have it
			_load.apply(this,[]);
		}
	}, this);
};

/**
 * Check if template contains any dirty (unsaved) content
 *
 * @returns {Boolean}
 */
etemplate2.prototype.isDirty = function()
{
	var dirty = false;
	this.widgetContainer.iterateOver(function(_widget) {
		if (_widget.isDirty && _widget.isDirty())
		{
			dirty = true;
		}
	});

	return dirty;
};

/**
 * Submit form via ajax
 *
 * @param et2_button|string button button widget or string with id
 * @param boolean async true: do an asynchronious submit, default is synchronious
 * @return boolean true if submit was send, false if eg. validation stoped submit
 */
etemplate2.prototype.submit = function(button, async)
{
	// Get the form values
	var values = this.getValues(this.widgetContainer);

	// Trigger the submit event
	var canSubmit = true;
	this.widgetContainer.iterateOver(function(_widget) {
		if (_widget.submit(values) === false)
		{
			canSubmit = false;
		}
	}, this, et2_ISubmitListener);

	if (canSubmit)
	{
		if (typeof button == 'string')
		{
			button = this.widgetContainer.getWidgetById(button);
		}
		// Button parameter used for submit buttons in datagrid
		// TODO: This should probably go in nextmatch's getValues(), along with selected rows somehow.
		// I'm just not sure how.
		if(button && !values.button)
		{
			values.button = button.id;
			var path = button.getPath();
			var target = values;
			for(var i = 0; i < path.length; i++)
			{
				if(!values[path[i]]) values[path[i]] = {};
				target = values[path[i]];
			}
			if(target != values)
			{
				var indexes = button.id.split('[');
				if (indexes.length > 1)
				{
					indexes = [indexes.shift(), indexes.join('[')];
					indexes[1] = indexes[1].substring(0,indexes[1].length-1);
					var children = indexes[1].split('][');
					if(children.length)
					{
						indexes = jQuery.merge([indexes[0]], children);
					}
				}
				var idx = '';
				for(var i = 0; i < indexes.length; i++)
				{
					idx = indexes[i];
					if(!target[idx] || target[idx]['$row_cont']) target[idx] = i < indexes.length -1 ? {} : true;
					target = target[idx];
				}
			}
		}

		// Create the request object
		if (this.menuaction)
		{
			var api = this.widgetContainer.egw();
			var request = api.json(this.menuaction, [this.etemplate_exec_id,values], null, this, async);
			request.sendRequest();
		}
		else
		{
			this.widgetContainer.egw().debug("warn", "Missing menuaction for submit.  Values: ", values);
		}
	}
	return canSubmit;
};

/**
 * Does a full form post submit.
 * Only use this one if you need it, use the ajax submit() instead
 */
etemplate2.prototype.postSubmit = function()
{
	// Get the form values
	var values = this.getValues(this.widgetContainer);

	// Trigger the submit event
	var canSubmit = true;
	this.widgetContainer.iterateOver(function(_widget) {
		if (_widget.submit(values) === false)
		{
			canSubmit = false;
		}
	}, this, et2_ISubmitListener);

	if (canSubmit)
	{
		var form = jQuery("<form id='form' action='"+egw().webserverUrl +
			"/etemplate/process_exec.php?menuaction=" + this.widgetContainer.egw().getAppName()+ "&ajax=true' method='POST'>");

		var etemplate_id = jQuery(document.createElement("input"))
			.attr("name",'etemplate_exec_id')
			.attr("type",'hidden')
			.val(this.etemplate_exec_id)
			.appendTo(form);

		var input = document.createElement("input");
		input.type = "hidden";
		input.name = 'value';
		input.value = egw().jsonEncode(values);
		form.append(input);
		form.appendTo(jQuery('body')).submit();
	}
};

/**
 * Fetches all input element values and returns them in an associative
 * array. Widgets which introduce namespacing can use the internal _target
 * parameter to add another layer.
 */
etemplate2.prototype.getValues = function(_root)
{
	var result = {};

	// Iterate over the widget tree
	_root.iterateOver(function(_widget) {

		// The widget must have an id to be included in the values array
		if (_widget.id == "")
		{
			return;
		}

		// Get the path to the node we have to store the value at
		var path = _widget.getPath();

		// check if id contains a hierachical name, eg. "button[save]"
		var id = _widget.id;
		var indexes = id.split('[');
		if (indexes.length > 1)
		{
			indexes = [indexes.shift(), indexes.join('[')];
			indexes[1] = indexes[1].substring(0,indexes[1].length-1);
			var children = indexes[1].split('][');
			if(children.length)
			{
				indexes = jQuery.merge([indexes[0]], children);
			}
			path = path.concat(indexes);
			// Take the last one as the ID
			id = path.pop();
		}

		// Set the _target variable to that node
		var _target = result;
		for (var i = 0; i < path.length; i++)
		{
			// Create a new object for not-existing path nodes
			if (typeof _target[path[i]] === 'undefined')
			{
				_target[path[i]] = {};
			}

			// Check whether the path node is really an object
			if (typeof _target[path[i]] === 'object')
			{
				_target = _target[path[i]];
			}
			else
			{
				egw.debug("error", "ID collision while writing at path " +
					"node '" + path[i] + "'");
			}
		}

		// Handle arrays, eg radio[]
		if(id === "")
		{
			id = typeof _target == "undefined" ? 0 : Object.keys(_target).length;
		}

		var value = _widget.getValue();

		// Check whether the entry is really undefined
		if (typeof _target[id] != "undefined" && (typeof _target[id] != 'object' || typeof value != 'object'))
		{
			egw.debug("error", _widget, "Overwriting value of '" + _widget.id +
				"', id exists twice!");
		}

		// Store the value of the widget and reset its dirty flag
		if (value !== null)
		{
			// Merge, if possible (link widget)
			if(typeof _target[id] == 'object' && typeof value == 'object')
			{
				_target[id] = jQuery.extend({},_target[id],value);
			}
			else
			{
				_target[id] = value;
			}
		}
		else if (jQuery.isEmptyObject(_target))
		{
			// Avoid sending back empty sub-arrays
			_target = result;
			for (var i = 0; i < path.length-1; i++)
			{
				_target = _target[path[i]];
			}
			delete _target[path[path.length-1]];
		}
		_widget.resetDirty();

	}, this, et2_IInput);

	egw().debug("info", "Value", result);
	return result;
};


/**
 * "Intelligently" refresh the template based on the given ID
 *
 * Rather than blindly re-load the entire template, we try to be a little smarter about it.
 * If there's a message provided, we try to find where it goes and set it directly.  Then
 * we look for a nextmatch widget, and tell it to refresh its data based on that ID.
 *
 * @param msg String Message to try to display.  eg: "Entry added" (not used anymore, handeled by egw_refresh and egw_message)
 * @param id String|null Application specific entry ID to try to refresh
 * @param type String|null Type of change.  One of 'edit', 'delete', 'add' or null
 *
 * @see jsapi.egw_refresh()
 * @see egw_fw.egw_refresh()
 */
etemplate2.prototype.refresh = function(msg, app, id, type)
{
	// Refresh nextmatches
	this.widgetContainer.iterateOver(function(_widget) {
		// Trigger refresh
		_widget.refresh(id,type);
	}, this, et2_nextmatch);
};

// Some static things to make getting into widget context a little easier //

/**
 * List of etemplates by loaded template
 */
etemplate2._byTemplate = {};

/**
 * Get a list of etemplate2 objects that loaded the given template name
 *
 * @param template String Name of the template that was loaded
 *
 * @return Array list of etemplate2 that have that template
 */

etemplate2.getByTemplate = function(template)
{
	if(typeof etemplate2._byTemplate[template] != "undefined")
	{
		return etemplate2._byTemplate[template];
	}
	else
	{
		// Return empty array so you can always iterate over results
		return [];
	}
};

/**
 * Get a list of etemplate2 objects that are associated with the given application
 *
 * "Associated" is determined by the first part of the template
 *
 * @param template String Name of the template that was loaded
 *
 * @return Array list of etemplate2 that have that app as the first part of their loaded template
 */
etemplate2.getByApplication = function(app)
{
	var list = [];
	for(var name in etemplate2._byTemplate)
	{
		if(name.indexOf(app + ".") == 0)
		{
			list = list.concat(etemplate2._byTemplate[name]);
		}
	}
	return list;
};

/**
 * Plugin for egw.json type "et2_load"
 *
 * @param _type
 * @param _response
 * @returns {Boolean}
 */
function etemplate2_handle_load(_type, _response)
{
	// Check the parameters
	var data = _response.data;

	// handle egw_framework::refresh_opener()
	if (jQuery.isArray(data['refresh-opener']))
	{
		if (window.opener && typeof window.opener.egw_refresh == 'function')
		{
			window.opener.egw_refresh.apply(window.opener, data['refresh-opener']);
		}
	}

	// handle egw_framework::message()
	if (jQuery.isArray(data['message']))
	{
		window.egw_message.apply(window, data['message']);
	}

	// handle egw_framework::window_close(), this will terminate execution
	if (data['window-close'])
	{
		if (typeof data['window-close'] == 'string' && data['window-close'] !== 'true')
		{
			alert(data['window-close']);
		}
		window.close();
	}

	// handle egw_framework::window_focus()
	if (data['window-focus'])
	{
		window.focus();
	}

	// handle framework.setSidebox calls
	if (window.framework && jQuery.isArray(data['setSidebox']))
	{
		window.framework.setSidebox.apply(window, JSON.parse(data['setSidebox']));
	}

	// regular et2 re-load
	if (typeof data.url == "string" && typeof data.data === 'object')
	{
		if(typeof this.load == 'function')
		{
			// Called from etemplate
			this.load(data.name, data.url, data.data);
			return true;
		}
		else
		{
			// Not etemplate
			var node = document.getElementById(data.DOMNodeID);
			if(node)
			{
				var et2 = new etemplate2(node, "etemplate_new::ajax_process_content");
				et2.load(data.name, data.url, data.data);
				return true;
			}
			else
			{
				egw.debug("error", "Could not find target node %s", data.DOMNodeId);
			}
		}
	}

	throw("Error while parsing et2_load response");
}

/**
 * Plugin for egw.json type "et2_validation_error"
 *
 * @param _type
 * @param _response
 */
function etemplate2_handle_validation_error(_type, _response)
{
	// Display validation errors
	//$j(':input',this.DOMContainer).data("validator").invalidate(_response.data);
	egw().debug("warn","Validation errors", _response.data);
}
/**
 * Handle assign for attributes on etemplate2 widgets
 *
 * @param {String} type "assign"
 * @param res Response
 * @param res.data.id {String} Widget ID
 * @param res.data.key {String} Attribute name
 * @param res.data.value New value for widget
 * @param res.data.etemplate_exec_id
 * @param {type} req
 * @returns {Boolean} Handled by this plugin
 * @throws Invalid parameters if the required res.data parameters are missing
 */
function etemplate2_handle_assign(type, res, req)
{
	//Check whether all needed parameters have been passed and call the alertHandler function
	if ((typeof res.data.id != 'undefined') &&
		(typeof res.data.key != 'undefined') &&
		(typeof res.data.value != 'undefined')
	)
	{
		if(typeof res.data.etemplate_exec_id == 'undefined' ||
			res.data.etemplate_exec_id != this.etemplate_exec_id)
		{
			// Not for this etemplate, but not an error
			return false;
		}
		if (res.data.key == 'etemplate_exec_id')
		{
			this.etemplate_exec_id = res.data.value;
			return true;
		}
		var widget = this.widgetContainer.getWidgetById(res.data.id);
		if (widget)
		{
			try
			{
				widget['set_' + res.data.key].call(widget,res.data.value);
				return true;
			}
			catch (e)
			{
				egw.debug("error", "When assigning %s on %s via AJAX, \n"+e.message,res.data.key,res.data.id,widget);
			}
		}
		return false;
	}
	throw 'Invalid parameters';
}
// Calls etemplate2_handle_response in the context of the object which
// requested the response from the server
egw(window).registerJSONPlugin(etemplate2_handle_load, null, 'et2_load');
egw(window).registerJSONPlugin(etemplate2_handle_validation_error, null, 'et2_validation_error');


/**
 * Compatability function for etemplate
 *
 * When we're fully on et2, replace each useage with a call to etemplate2 widget.getInstanceManager().submit()
 * @param obj DOM Node, usually a button
 * @param widget et2_widget
 */
function xajax_eT_wrapper(obj,widget)
{
	egw().debug("warn", "xajax_eT_wrapper() is deprecated, replace with widget.getInstanceManager().submit()");
	if(typeof obj == "object")
	{
		$j("div.popupManual div.noPrint").hide();
		$j("div.ajax-loader").show();
		if(typeof widget == "undefined" && obj.id)
		{
			// Try to find the widget by ID so we don't have to change every call
			var et2 = etemplate2.getByApplication(egw_getAppName());
			for(var i = 0; i < et2.length; i++)
			{
				widget = et2[i].widgetContainer.getWidgetById(obj.id);
				if(widget.getInstanceManager) break;
			}
		}
		widget.getInstanceManager().submit(this);
	}
	else
	{
		$j("div.popupManual div.noPrint").show();
		$j("div.ajax-loader").hide();
	}
}
