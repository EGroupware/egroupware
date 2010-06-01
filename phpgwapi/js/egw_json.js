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

/* The constructor of the egw_json_request class.
 * @param string _url the url of the AJAX handler on the server
 * @param string _menuaction the menuaction function which should be called and which handles the actual request
 * @param array _parameters which should be passed to the menuaction function.
*/
function egw_json_request(_url, _menuaction, _parameters)
{
	//Copy the supplied parameters
	this.menuaction = _menuaction;
	this.parameters = _parameters;
	this.url = _url;
	this.sender = null;
	this.callback = null;
	this.alertHandler = this.alertFunc;
	if (document.alertHandler)
	{
		this.alertHandler = document.alertHandler;
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

	//Assemble the actual request string
	var request  = '{';
	request += '"request":{';
	if (this.parameters)
	{
		request += '"parameters":[';
		for (var i = 0; i < this.parameters.length; i++)
		{
			if (i > 0)
			{
				request += ',';
			}
			request += '"' + this.parameters[i] + '"';
		}
		request += ']';
	}
	request += '}}';

	var request_obj = new Object();
	request_obj.json_data = request;

	//Send the request via the jquery AJAX interface to the server
	$.ajax({url: this.url + '?menuaction=' + this.menuaction,
		async: is_async,
		context: this,
		data: request_obj,
		dataType: 'json',
		type: 'POST', 
		success: this.handleResponse});
}

egw_json_request.prototype.alertFunc = function(_message, _details)
{
	alert(_message);
}

/* Internal function which handles the response from the server */
egw_json_request.prototype.handleResponse = function(data, textStatus, XMLHttpRequest)
{
	if (data.response)
	{
		var hasResponse = false;
		for (var i = 0; i < data.response.length; i++)
		{
			switch (data.response[i].type)
			{
				case 'alert':
					//Check whether all needed parameters have been passed and call the alertHandler function
					if ((typeof data.response[i].data.message != 'undefined') && 
						(typeof data.response[i].data.details != 'undefined'))
					{					
						this.alertHandler(
							data.response[i].data.message,
							data.response[i].data.details)
						hasResponse = true;
					}
					break;
				case 'assign':
					//Check whether all needed parameters have been passed and call the alertHandler function
					if ((typeof data.response[i].data.id != 'undefined') && 
						(typeof data.response[i].data.key != 'undefined') &&
						(typeof data.response[i].data.value != 'undefined'))
					{					
						var obj = document.getElementById(data.response[i].data.id);
						if (obj)
						{
							obj[data.response[i].data.key] = data.response[i].data.value;
						}
						hasResponse = true;
					}
					break;
				case 'data':
					//Callback the caller in order to allow him to handle the data
					if (this.callback)
					{
						this.callback.call(this.sender, data.response[i].data);
					}
					hasResponse = true;
					break;
				case 'script':
					if (typeof data.response[i].data == 'string')
					{
						eval(data.response[i].data);
						hasResponse = true;
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
