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
 * @param _menuaction is the URL to which the form data should be submitted.
 */
function etemplate2(_container, _menuaction)
{
	if (typeof _menuaction == "undefined")
	{
		_menuaction = "home.etemplate_new.ajax_process_content.etemplate";
	}

	// Copy the given parameters
	this.DOMContainer = _container;
	this.menuaction = _menuaction;

	// Unique ID to prevent DOM collisions across multiple templates
	this.uniqueId = _container.getAttribute("id") ? _container.getAttribute("id").replace('.','-') : '';

	/**
	 * Preset the object variable
	 * @type {et2_container}
	 */
	this.widgetContainer = null;

}

// List of templates (XML) that are known, not always used.  Indexed by id.
etemplate2.prototype.templates = {};


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
	// Remove any handlers on window (resize)
	if(this.uniqueId)
	{
		$j(window).off("."+this.uniqueId);
	}

	// call our destroy_session handler, if it is not already unbind, and unbind it after
	if (this.destroy_session)
	{
		this.destroy_session();
		this.unbind_unload();
	}
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
 *
 * @param {object} _data object with values for attributes content, sel_options, readonlys, modifications
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
				result[key].perspectiveData.owner = this.widgetContainer;
				break;
			default:
				result[key] = new et2_arrayMgr(_data[key]);
				result[key].perspectiveData.owner = this.widgetContainer;
		}
	}

	return result;
};

/**
 * Bind our unload handler to notify server that eT session/request no longer needed
 *
 * We only bind, if we have an etemplate_exec_id: not the case for pure client-side
 * calls, eg. via et2_dialog.
 */
etemplate2.prototype.bind_unload = function()
{
	if (this.etemplate_exec_id)
	{
		this.destroy_session = jQuery.proxy(function(ev)
		{
			var request = egw.json(this.app+".etemplate_new.ajax_destroy_session.etemplate",
				[this.etemplate_exec_id], null, null, false);
			request.sendRequest();
		}, this);

		if (!window.onbeforeunload)
		{
			window.onbeforeunload = this.destroy_session;
		}
	}
};

/**
 * Unbind our unload handler
 */
etemplate2.prototype.unbind_unload = function()
{
	if (window.onbeforeunload === this.destroy_session)
	{
		window.onbeforeunload = null;
	}
	delete this.destroy_session;
};

/**
 * Loads the template from the given URL and sets the data object
 *
 * @param {string} _name name of template
 * @param {string} _url url to load template
 * @param {object} _data object with attributes content, langRequire, etemplate_exec_id, ...
 * @param {function} _callback called after tempalte is loaded
 */
etemplate2.prototype.load = function(_name, _url, _data, _callback)
{
	this.name = _name;	// store top-level template name to have it available in widgets

	egw().debug("info", "Loaded data", _data);
	var currentapp = this.app = _data.currentapp || window.egw_appName;

	// extract $content['msg'] and call egw.message() with it
	var msg = _data.content.msg;
	if (typeof msg != 'undefined')
	{
		egw(window).message(msg);
		delete _data.content.msg;
	}

	// Register a handler for AJAX responses
	egw(currentapp, window).registerJSONPlugin(etemplate2_handle_assign, this, 'assign');

	if(console.groupCollapsed)
	{
		egw.window.console.groupCollapsed("Loading %s into ", _name, this.DOMContainer);
	}
	if(console.timeline)
	{
		console.timeline(_name);
	}
	if(console.profile)
	{
		console.profile(_name);
	}
	var start_time = (new Date).getTime();

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
		// new app class with constructor function in app.classes[appname]
		if (typeof app[appname] !== 'object' && typeof app.classes[appname] == 'function')
		{
			app[appname] = new app.classes[appname]();
		}
		// old app class with constructor function in app[appname] (deprecated)
		else if(typeof app[appname] == "function")
		{
			(function() { new app[appname]();}).call();
		}
		else if (typeof app[appname] !== "object")
		{
			egw.debug("warn", "Did not load '%s' JS object",appname);
		}
		if(typeof app[appname] == "object")
		{
			app_callback = function(_et2, _name) {
				app[appname].et2_ready(_et2, _name);
			};
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
			if (typeof _data.app_header == 'string')
			{
				window.egw_app_header(_data.app_header);
			}
			// bind our unload handler
			this.bind_unload();
		}

		var _load = function() {
			egw.debug("log", "Loading template...");
			if(console.timeStamp)
			{
				console.timeStamp("Begin rendering template");
			}

			// Add into indexed list - do this before, so anything looking can find it,
			// even if it's not loaded
			if(typeof etemplate2._byTemplate[_name] == "undefined")
			{
				etemplate2._byTemplate[_name] = [];
			}
			etemplate2._byTemplate[_name].push(this);

			// Read the XML structure of the requested template
			this.widgetContainer.loadFromXML(this.templates[this.name]);

			// List of Promises from widgets that are not quite fully loaded
			var deferred = [];

			// Inform the widget tree that it has been successfully loaded.
			this.widgetContainer.loadingFinished(deferred);

			// Connect to the window resize event
			$j(window).on("resize."+this.uniqueId, this, function(e) {e.data.resize();});

			// Insert the document fragment to the DOM Container
			this.DOMContainer.appendChild(frag);

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
					_callback.call(window,this,_name);
				}
				if(app_callback && _callback != app_callback)
				{
					app_callback.call(window,this,_name);
				}

				$j(this.DOMContainer).trigger('load', this);

				// Profiling
				if(console.timelineEnd)
				{
					console.timelineEnd(_name);
				}
				if(console.profileEnd)
				{
					console.profileEnd(_name);
				}
				var end_time = (new Date).getTime();
				var gen_time_div = $j('#divGenTime_'+appname);
				if (!gen_time_div.length) gen_time_div = $j('.pageGenTime');
				gen_time_div.find('.et2RenderTime').remove();
				gen_time_div.append('<span class="et2RenderTime">'+egw.lang('eT2 rendering took %1s', (end_time-start_time)/1000)+'</span>');
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
					if(!_name) this.name = template.getAttribute("id");
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
 * @param {(et2_button|string)} button button widget or string with id
 * @param {boolean} async true: do an asynchronious submit, default is synchronious
 * @param {boolean} no_validation - Do not do individual widget validation, just submit their current values
 * @return {boolean} true if submit was send, false if eg. validation stoped submit
 */
etemplate2.prototype.submit = function(button, async, no_validation)
{
	if(typeof no_validation == 'undefined')
	{
		no_validation = false;
	}

	// Get the form values
	var values = this.getValues(this.widgetContainer);

	// Trigger the submit event
	var canSubmit = true;
	if(!no_validation)
	{
		this.widgetContainer.iterateOver(function(_widget) {
			if (_widget.submit(values) === false)
			{
				canSubmit = false;
			}
		}, this, et2_ISubmitListener);
	}

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
			values.button = {};
			var path = button.getPath();
			var target = values;
			for(var i = 0; i < path.length; i++)
			{
				if(!values[path[i]]) values[path[i]] = {};
				target = values[path[i]];
			}
			if(target != values || button.id.indexOf('[') != -1 && path.length == 0)
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
			else if (typeof values.button == 'undefined')
			{
				values.button = button.id;
			}
		}

		// Create the request object
		if (this.menuaction)
		{
			// unbind our session-destroy handler, as we are submitting
			this.unbind_unload();

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
		// unbind our session-destroy handler, as we are submitting
		this.unbind_unload();

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
 *
 * @param {et2_widget} _root widget to start iterating
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
			// Don't warn about children of nextmatch header - they're part of nm value
			if(!_widget.getParent().instanceOf(et2_nextmatch_header_bar))
			{
				egw.debug("warn", _widget, "Overwriting value of '" + _widget.id +
					"', id exists twice!");
			}
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
 * @param {string} msg message to try to display.  eg: "Entry added" (not used anymore, handeled by egw_refresh and egw_message)
 * @param {string} app app-name
 * @param {(string|null)} id application specific entry ID to try to refresh
 * @param {(string|null)} type type of change.  One of 'update','edit', 'delete', 'add' or null
 *
 * @see jsapi.egw_refresh()
 * @see egw_fw.egw_refresh()
 */
etemplate2.prototype.refresh = function(msg, app, id, type)
{
	msg, app;	// unused but required by function signature
	var refresh_done = false;

	// Refresh nextmatches
	this.widgetContainer.iterateOver(function(_widget) {
		// Trigger refresh
		_widget.refresh(id,type);
		refresh_done = true;
	}, this, et2_nextmatch);

	return refresh_done;
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
 * @param {string} app app-name
 * @return {array} list of etemplate2 that have that app as the first part of their loaded template
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
 * Get a etemplate2 object from the given DOM ID
 *
 * @param {string} id DOM ID of the container node
 * @returns {etemplate2|null}
 */
etemplate2.getById = function(id)
{
	for( var name in etemplate2._byTemplate)
	{
		console.log(name, etemplate2._byTemplate[name]);
		for(var i = 0; i < etemplate2._byTemplate[name].length; i++)
		{
			var et = etemplate2._byTemplate[name][i];

			if(et.DOMContainer.getAttribute("id") == id)
			{
				return et;
			}
		}
	}
	return null;
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

	// need to set app_header before message, as message temp. replaces app_header
	if (typeof data.data == 'object' && typeof data.data.app_header == 'string')
	{
		window.egw_app_header(data.data.app_header, data.data.currentapp||null);
		delete data.data.app_header;
	}

	// handle egw_framework::message()
	if (jQuery.isArray(data.message))
	{
		window.egw_message.apply(window, data.message);
	}

	// handle egw_framework::window_close(), this will terminate execution
	if (data['window-close'])
	{
		if (typeof data['window-close'] == 'string' && data['window-close'] !== 'true')
		{
			alert(data['window-close']);
		}
		window.close();
		return true;
	}

	// handle egw_framework::window_focus()
	if (data['window-focus'])
	{
		window.focus();
	}

	// handle framework.setSidebox calls
	if (window.framework && jQuery.isArray(data.setSidebox))
	{
		window.framework.setSidebox.apply(window.framework, data.setSidebox);
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
				var et2 = new etemplate2(node);
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
	for(var id in _response.data)
	{
		var widget = this.widgetContainer.getWidgetById(id);
		if(widget)
		{
			widget.showMessage(_response.data[id],'validation_error');
		}
	}
	egw().debug("warn","Validation errors", _response.data);
}
/**
 * Handle assign for attributes on etemplate2 widgets
 *
 * @param {string} type "assign"
 * @param {object} res Response
 * res.data.id {String} Widget ID
 * res.data.key {String} Attribute name
 * res.data.value New value for widget
 * res.data.etemplate_exec_id
 * @param {object} req
 * @returns {Boolean} Handled by this plugin
 * @throws Invalid parameters if the required res.data parameters are missing
 */
function etemplate2_handle_assign(type, res, req)
{
	type, req;	// unused, but required by plugin signature

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
		if(this.widgetContainer == null)
		{
			// Right etemplate, but it's already been cleared.
			egw.debug('warn', "Tried to call assign on an un-loaded etemplate", res.data);
			return false;
		}
		var widget = this.widgetContainer.getWidgetById(res.data.id);
		if (widget)
		{
			if(typeof widget['set_' + res.data.key] != 'function')
			{
				egw.debug('warn', "Cannot set %s attribute %s via JSON assign, no set_%s()",res.data.id,res.data.key,res.data.key);
				return false;
			}
			try
			{
				widget['set_' + res.data.key].call(widget,res.data.value);
				return true;
			}
			catch (e)
			{
				egw.debug("error", "When assigning %s on %s via AJAX, \n"+(e.message||e+""),res.data.key,res.data.id,widget);
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
