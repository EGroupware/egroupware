/**
 * eGroupWare eTemplate2 - JS file which contains the complete et2 module
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
	et2_widget_button;
	et2_widget_description;
	et2_widget_textbox;
	et2_widget_number;
	et2_widget_url;
	et2_widget_selectbox;
	et2_widget_checkbox;
	et2_widget_radiobox;
	et2_widget_date;
	et2_widget_styles;
	et2_widget_html;
	et2_widget_tabs;
	et2_widget_hrule;
	et2_widget_image;
	et2_widget_file;
	et2_widget_link;

	et2_extension_nextmatch;

	// Requirements for the etemplate2 object
	et2_core_common;
	et2_core_xml;
	et2_core_arrayMgr;
	et2_core_interfaces;
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

	// Preset the object variable
	this.widgetContainer = null;

	// Connect to the window resize event
	$j(window).resize(this, function(e) {e.data.resize()});
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
}

/**
 * Clears the current instance.
 */
etemplate2.prototype.clear = function()
{
	if (this.widgetContainer != null)
	{
//		$j(':input',this.DOMContainer).validator().data("validator").destroy();
		this.widgetContainer.free();
		this.widgetContainer = null;
	}
}

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
		if (typeof _data[neededEntries[i]] == "undefined")
		{
			et2_debug("log", "Created not passed entry '" + neededEntries[i] +
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
}

/**
 * Loads the template from the given URL and sets the data object
 */
etemplate2.prototype.load = function(_url, _data)
{
	// Asynchronously load the XET file (code below is executed ahead of the
	// code in the callback function)
	et2_loadXMLFromURL(_url, function(_xmldoc) {
		// Read the XML structure
		this.widgetContainer.loadFromXML(_xmldoc);

		// Inform the widget tree that it has been successfully loaded.
		this.widgetContainer.loadingFinished();

		// Trigger the "resize" event
		this.resize();
	}, this);

	// Clear any existing instance
	this.clear();

	// Create the basic widget container and attach it to the DOM
	this.widgetContainer = new et2_container(null);
	this.widgetContainer.setInstanceManager(this);
	this.widgetContainer.setParentDOMNode(this.DOMContainer);

	// store the id to submit it back to server
	if(_data) {
		this.etemplate_exec_id = _data.etemplate_exec_id;
	}
	
	// set app_header
	if (window.opener) {	// popup
		document.title = _data.app_header;
	} else {
		// todo for idots or jdots framework
	}

	// Split the given data into array manager objects and pass those to the
	// widget container
	this.widgetContainer.setArrayMgrs(this._createArrayManagers(_data));
}

etemplate2.prototype.submit = function()
{
	// Validator
	/*var valid = true;
	var inputs = $j(':input',this.DOMContainer).each(function() {
		if(typeof $j(this).data("validator") == "undefined") return true;
		valid = valid && $j(this).data("validator").checkValidity();
		return true;
	});
	if(!valid) return false;*/

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
		// Create the request object
		if (typeof egw_json_request != "undefined" && this.menuaction)
		{
			var request = new egw_json_request(this.menuaction, [this.etemplate_exec_id,values], this);
			request.sendRequest(true);
		}
		else
		{
			et2_debug("info", "Form got submitted with values: ", values);
		}
	}
}

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
		var path = _widget.getArrayMgr("content").getPath();
		
		// check if id contains a hierachical name, eg. "button[save]"
		var id = _widget.id;
		if (_widget.id.indexOf('[') != -1)
		{
			var parts = _widget.id.replace(/]/g,'').split('[');
			id = parts.pop();
			path = path.concat(parts);
		}

		// Set the _target variable to that node
		var _target = result;
		for (var i = 0; i < path.length; i++)
		{
			// Create a new object for not-existing path nodes
			if (typeof _target[path[i]] == "undefined")
			{
				_target[path[i]] = {};
			}

			// Check whether the path node is really an object
			if (_target[path[i]] instanceof Object)
			{
				_target = _target[path[i]];
			}
			else
			{
				et2_debug("error", "ID collision while writing at path " + 
					"node '" + path[i] + "'");
			}
		}

		// Check whether the entry is really undefined
		if (typeof _target[id] != "undefined")
		{
			et2_debug("error", _widget, "Overwriting value of '" + _widget.id + 
				"', id exists twice!");
		}

		// Store the value of the widget and reset its dirty flag
		var value = _widget.getValue();
		if (value !== null)
		{
			_target[id] = value;
		}
		_widget.resetDirty();

	}, this, et2_IInput);

	return result;
}

/**
 * Function which handles the EGW JSON et2_load response
 */
function etemplate2_handle_response(_type, _response)
{
	if (_type == "et2_load")
	{
		// Check the parameters
		var data = _response.data;
		if (typeof data.url == "string" && data.data instanceof Object)
		{
			this.load(data.url, data.data);
			return true;
		}

		throw("Error while parsing et2_load response");
	} else if (_type == "et2_validation_error") {
		// Display validation errors
//		$j(':input',this.DOMContainer).data("validator").invalidate(_response.data);
	}

	return false;
}

// Register the egw_json result object
if (typeof egw_json_register_plugin != "undefined")
{
	// Calls etemplate2_handle_response in the context of the object which
	// requested the response from the server
	egw_json_register_plugin(etemplate2_handle_response, null);
}
else
{
	et2_debug("info", "EGW JSON Plugin could not be registered, running ET2 standalone.");
}

