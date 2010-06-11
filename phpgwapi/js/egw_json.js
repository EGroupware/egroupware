/**
 * eGroupWare API: JSON - Contains the client side javascript implementation of class.egw_json.inc.php
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/* The egw_json_request is the javaScript side implementation of class.egw_json.inc.php.*/

function egw_json_encode_simple(input)
{
	switch (input.constructor)
	{
		case String:
			return '"' + input + '"';		

		case Number:
			return input.toString();

		case Boolean:
			return input ? 'true' : 'false';

		default:
			return null;
	}
}

function egw_json_encode(input)
{
	if (!input) return 'null';

	var simple_res = egw_json_encode_simple(input);
	if (simple_res == null)
	{
		switch (input.constructor)
		{
			case Array:
				var buf = [];
				for (var k in input)
				{
					buf.push(egw_json_encode(input[k]));
				}
				return '[' + buf.join(',') + ']';

			case Object:
				var buf = [];
				for (var k in input)
				{
					buf.push(egw_json_encode_simple(k) + ':' + egw_json_encode(input[k]));
				}
				return '{' + buf.join(',') + '}';

			default:
				return 'null';
		}
	}
	else
	{
		return simple_res;
	}
}


/* The constructor of the egw_json_request class.
 * @param string _menuaction the menuaction function which should be called and which handles the actual request
 * @param array _parameters which should be passed to the menuaction function.
*/
function egw_json_request(_menuaction, _parameters)
{
	//Copy the supplied parameters
	this.menuaction = _menuaction;

	if (typeof _parameters != 'undefined')
	{
		this.parameters = _parameters;
	}
	else
	{
		this.parameters = new Array;
	}

	var url = window.egw_webserverUrl;

	// Search up to parent if the current window is in a frame
	if(typeof url == "undefined")
	{
		url = top.egw_webserverUrl;
	}

	this.url = url + '/json.php';

	this.sender = null;
	this.callback = null;
	this.alertHandler = this.alertFunc;
	if (window.egw_alertHandler)
	{
		this.alertHandler = window.egw_alertHandler;
	}
}

/* Sends the AJAX JSON request.
 * @param boolean _async specifies whether the request should be handeled asynchronously (true, the sendRequest function immediately returns to the caller) or asynchronously (false, the sendRequest function waits until the request is received)
 * @param _callback is an additional callback function which should be called upon a "data" response is received
 * @param _sender is the reference object the callback function should get
*/
egw_json_request.prototype.sendRequest = function(_async, _callback, _sender)
{
	//Store the sender and callback parameter inside this class	
	this.sender = _sender;
	if (typeof _callback != "undefined")
		this.callback = _callback;

	//Copy the async parameter which defaults to "true"	
	var is_async = true;
	if (typeof _async != "undefined")
		is_async = _async;

	//Assemble the actual request object containing the json data string
	var request_obj = {
		"json_data": egw_json_encode(
		{
			"request": {
				"parameters": this.parameters
			}
		})
	}

	//Send the request via the jquery AJAX interface to the server
	$.ajax({url: this.url + '?menuaction=' + this.menuaction,
		async: is_async,
		context: this,
		data: request_obj,
		dataType: 'json',
		type: 'POST', 
		success: this.handleResponse});
}

egw_json_request.prototype.getFormValues = function(_form)
{
	var elem = null;
	if (typeof _form == 'object')
	{
		elem = _form;
	}
	else
	{
		elem = document.getElementsByName(_form)[0];
	}

	var serialized = new Object;
	if (typeof elem != "undefined" && elem && elem.childNodes)
	{
		_egw_json_getFormValues(serialized, elem.childNodes)
	}

	return serialized;
}

egw_json_request.prototype.alertFunc = function(_message, _details)
{
	alert(_message);
}

function _egw_json_debug_log(_msg)
{
	if (typeof console != "undefined" && typeof console.log != "undefined")
	{
		console.log(_msg);
	}
}

/* Internal function which handles the response from the server */
egw_json_request.prototype.handleResponse = function(data, textStatus, XMLHttpRequest)
{
	if (data && data.response)
	{
		var hasResponse = false;
		for (var i = 0; i < data.response.length; i++)
		{
			var res = data.response[i];				

			switch (data.response[i].type)
			{
				case 'alert':
					//Check whether all needed parameters have been passed and call the alertHandler function
					if ((typeof res.data.message != 'undefined') && 
						(typeof res.data.details != 'undefined'))
					{					
						this.alertHandler(
							res.data.message,
							res.data.details)
						hasResponse = true;
					}
					break;
				case 'assign':
					//Check whether all needed parameters have been passed and call the alertHandler function
					if ((typeof res.data.id != 'undefined') && 
						(typeof res.data.key != 'undefined') &&
						(typeof res.data.value != 'undefined'))
					{					
						var obj = document.getElementById(res.data.id);
						if (obj)
						{
							obj[res.data.key] = res.data.value;
							hasResponse = true;
						}
					}
					break;
				case 'data':
					//Callback the caller in order to allow him to handle the data
					if (this.callback)
					{
						this.callback.call(this.sender, res.data);
						hasResponse = true;
					}
					break;
				case 'script':
					if (typeof res.data == 'string')
					{
						try
						{
							var func = function() {eval(res.data);};
							func.call(window);
						}
						catch (e)
						{
							e.code = res.data;
							_egw_json_debug_log(e);
						}
						hasResponse = true;
					}
					break;
				case 'jquery':
					if (typeof res.data.select == 'string' &&
					    typeof res.data.func == 'string')
					{
						try
						{
							var jQueryObject = $(res.data.select);
							jQueryObject[res.data.func].apply(jQueryObject,	res.data.parms);
						}
						catch (e)
						{
							_egw_json_debug_log(e);
						}
						hasResponse = true;
					}
					break;
				case 'redirect':
					if (typeof res.data.url == 'string' &&
						typeof res.data.global == 'boolean')
					{
						//Special handling for framework reload
						if (res.data.url.indexOf("?cd=10") > 0)
							res.data.global = true;

						if (res.data.global)
						{
							egw_topWindow().location.href = res.data.url;
						}
						else
						{
							window.location.href = res.data.url;
						}
					}
					break;
			}
		}

		/* If no explicit response has been specified, call the callback (if one was set) */
		if (!hasResponse && this.callback)
		{			
			this.callback.call(this.sender, data.response[i].data);			
		}
	}
}


/**
 * Deprecated legacy xajax wrapper functions for the new egw_json interface
 */
_xajax_doXMLHTTP = function(_async, _menuaction, _arguments)
{
	/* Assemble the parameter array */
	var paramarray = new Array();
	for (var i = 1; i < _arguments.length; i++)
	{
		paramarray[paramarray.length] = _arguments[i];
	}

	/* Create a new request, passing the menuaction and the parameter array */
	var request = new egw_json_request(_menuaction, paramarray);

	/* Send the request */
	request.sendRequest(_async);

	return request;
}

xajax_doXMLHTTP = function(_menuaction)
{
	return _xajax_doXMLHTTP(true, _menuaction, arguments);
}

xajax_doXMLHTTPsync = function(_menuaction)
{
	return _xajax_doXMLHTTP(false, _menuaction, arguments);
};

window.xajax = {
	"getFormValues": function(_form)
	{
		return egw_json_request.prototype.getFormValues(_form);
	}
};

/*
	The following code is adapted from the xajax project which is licensed under
	the following license
	@copyright Copyright (c) 2005-2007 by Jared White & J. Max Wilson
	@copyright Copyright (c) 2008-2009 by Joseph Woolley, Steffen Konerow, Jared White  & J. Max Wilson
	@license http://www.xajaxproject.org/bsd_license.txt BSD License
*/

/**
 * used internally by the legacy "egw_json_response.getFormValues" to recursively
 * run over all form elements
 * @param serialized is the object which will contain the form data
 * @param children is the children node of the form we're runing over
 */
function _egw_json_getFormValues(serialized, children)
{
	for (var i = 0; i < children.length; ++i) {
		var child = children[i];

		if (typeof child.childNodes != "undefined")
			_egw_json_getFormValues(serialized, child.childNodes);

		_egw_json_getFormValue(serialized, child);
	}
}

function _egw_json_getObjectLength(_obj)
{
	var res = 0;
	for (key in _obj)
	{
		if (_obj.hasOwnProperty(key))
			res++;
	}
	return res;
}

/**
 * used internally to serialize 
 */
function _egw_json_getFormValue(serialized, child)
{
	//Return if the child doesn't have a name, is disabled, or is a radio-/checkbox and not checked
	if ((typeof child.name == "undefined") || (child.disabled && child.disabled == true) ||				
		(child.type && (child.type == 'radio' || child.type == 'checkbox') && (!child.checked)))
	{
		return;
	}
	
	var name = child.name;
	var values = null;	

 	if ('select-multiple' == child.type)
	{
		values = new Array;
 		for (var j = 0; j < child.length; ++j)
		{
 			var option = child.options[j];
 			if (option.selected == true)
 				values.push(option.value);
 		}
 	}
	else
	{
 		values = child.value;
 	}

	//Special treatment if the name of the child contains a [] - then all theese
	//values are added to an array.
	var keyBegin = name.indexOf('[');
	if (0 <= keyBegin) {
		var n = name;
		var k = n.substr(0, n.indexOf('['));
		var a = n.substr(n.indexOf('['));
		if (typeof serialized[k] == 'undefined')
			serialized[k] = new Object;

		var p = serialized; // pointer reset
		while (a.length != 0) {
			var sa = a.substr(0, a.indexOf(']')+1);
			
			var lk = k; //save last key
			var lp = p; //save last pointer
			
			a = a.substr(a.indexOf(']')+1);
			p = p[k];
			k = sa.substr(1, sa.length-2);
			if (k == '') {
				if ('select-multiple' == child.type) {
					k = lk; //restore last key
					p = lp;
				} else {
					k = _egw_json_getObjectLength(p);
				}
			}
			if (typeof p[k] == 'undefined')
			{
				p[k] = new Object; 
			}
		}
		p[k] = values;
	} else {
		//Add the value to the result object with the given name
		if (typeof values != "undefined")
		{
			serialized[name] = values;
		}
	}
}
