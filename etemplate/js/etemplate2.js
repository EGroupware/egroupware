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
	et2_template;
	et2_grid;
	et2_box;
	et2_button;
	et2_description;
	et2_textbox;

	// Requirements for the etemplate2 object
	et2_xml;
	et2_arrayMgr;
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
}

/**
 * Clears the current instance.
 */
etemplate2.prototype.clear = function()
{
	if (this.widgetContainer != null)
	{
		this.widgetContainer.destroy();
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
	var neededEntries = ["content", "readonlys", "validation_errors"];
	for (var i = 0; i < neededEntries.length; i++)
	{
		if (typeof _data[neededEntries[i]] == "undefined")
		{
			et2_debug("info", "Created not passed entry '" + neededEntries[i] + "' in data array.");
			_data[neededEntries[i]] = {};
		}
	}

	var result = {};

	// Create an array manager object for each part of the _data array.
	for (var key in _data)
	{
		result[key] = new et2_arrayMgr(_data[key]);
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
		this.widgetContainer.loadFromXML(_xmldoc);
	}, this);

	// Clear any existing instance
	this.clear();

	// Create the basic widget container and attach it to the DOM
	this.widgetContainer = new et2_container(null);
	this.widgetContainer.setInstanceManager(this);
	this.widgetContainer.setParentDOMNode(this.DOMContainer);

	// Split the given data into array manager objects and pass those to the
	// widget container
	this.widgetContainer.setArrayMgrs(this._createArrayManagers(_data));
}

etemplate2.prototype.submit = function()
{
	// Get the form values
	var values = this.widgetContainer.getValues();

	// Create the request object
	if (typeof egw_json_request != "undefined")
	{
		var request = new egw_json_request(this.menuaction, [values], this);
		request.sendRequest(true);
	}
	else
	{
		console.log(values);
	}
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

