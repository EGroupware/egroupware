  /***************************************************************************\
  * eGroupWare - JavaScript API                                               *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Raphael Derosso Pereira <raphaelpereira@users.sourceforge.net>         *
  *  - Jonas Goes <jqhcb@users.sourceforge.net>                               *
  *  - Vinicus Cubas Brand <viniciuscb@users.sourceforge.net>                 *
  *  sponsored by Thyamad - http://www.thyamad.com                            *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU Lesser General Public License as published by *
  *  the Free Software Foundation; either version 2 of the License, or (at    *
  *  your option) any later version.                                          *
  \***************************************************************************/

/***********************************************\
*               INITIALIZATION                  *
\***********************************************/
if (document.all)
{
	navigator.userAgent.toLowerCase().indexOf('msie 5') != -1 ? is_ie5 = true : is_ie5 = false;
	is_ie = true;
	is_moz1_6 = false;
	is_mozilla = false;
	is_ns4 = false;
}
else if (document.getElementById)
{
	navigator.userAgent.toLowerCase().match('mozilla.*rv[:]1\.6.*gecko') ? is_moz1_6 = true : is_moz1_6 = false;
	is_ie = false;
	is_ie5 = false;
	is_mozilla = true;
	is_ns4 = false;
}
else if (document.layers)
{
	is_ie = false;
	is_ie5 = false
	is_moz1_6 = false;
	is_mozilla = false;
	is_ns4 = true;
}

// DO NOT CHANGE THIS!!! Enable DEBUG inside Application!
var DEBUG = false;

/***********************************************\
 *                DATA FUNCTIONS               *
\***********************************************/

function serialize(data)
{
	var f = function(data)
	{
		var str_data;

		if (data == null || 
			(typeof(data) == 'string' && data == ''))
		{
			str_data = 'N;';
		}

		else switch(typeof(data))
		{
			case 'object':
				var arrayCount = 0;

				str_data = '';

				for (i in data)
				{
					if (i == 'length')
					{
						continue;
					}
					
					arrayCount++;
					switch (typeof(i))
					{
						case 'number':
							str_data += 'i:' + i + ';' + serialize(data[i]);
							break;

						case 'string':
							str_data += 's:' + i.length + ':"' + i + '";' + serialize(data[i]);
							break;

						default:
							showMessage(Element('cc_msg_err_serialize_data_unknown').value);
							break;
					}
				}

				if (!arrayCount)
				{
					str_data = 'N;';	
				}
				else
				{
					str_data = 'a:' + arrayCount + ':{' + str_data + '}';
				}
				
				break;
		
			case 'string':
				str_data = 's:' + data.length + ':"' + data + '";';
				break;
				
			case 'number':
				str_data = 'i:' + data + ';';
				break;

			case 'boolean':
				str_data = 'b:' + (data ? '1' : '0') + ';';
				break;

			default:
				showMessage(Element('cc_msg_err_serialize_data_unknown').value);
				return null;
		}

		return str_data;
	}

	var sdata = f(data);
	return sdata;
}

function unserialize(str)
{
	var f = function (str)
	{
		switch (str.charAt(0))
		{
			case 'a':
				
				var data = new Array();
				var n = parseInt( str.substring( str.indexOf(':')+1, str.indexOf(':',2) ) );
				var arrayContent = str.substring(str.indexOf('{')+1, str.lastIndexOf('}'));
			
				for (var i = 0; i < n; i++)
				{
					var pos = 0;

					/* Process Index */
					var indexStr = arrayContent.substr(pos, arrayContent.indexOf(';')+1);
					var index = unserialize(indexStr);
					pos = arrayContent.indexOf(';', pos)+1;
					
					/* Process Content */
					var part = null;
					switch (arrayContent.charAt(pos))
					{
						case 'a':
							var pos_ = matchBracket(arrayContent, arrayContent.indexOf('{', pos))+1;
							part = arrayContent.substring(pos, pos_);
							pos = pos_;
							data[index] = unserialize(part);
							break;
					
						case 's':
							var pval = arrayContent.indexOf(':', pos+2);
							var val  = parseInt(arrayContent.substring(pos+2, pval));
							pos = pval + val + 4;
							data[index] = arrayContent.substr(pval+2, val);
							break;

						default:
							part = arrayContent.substring(pos, arrayContent.indexOf(';', pos)+1);
							pos = arrayContent.indexOf(';', pos)+1;
							data[index] = unserialize(part);
							break;
					}
					arrayContent = arrayContent.substr(pos);
				}
				break;
				
			case 's':
				var pos = str.indexOf(':', 2);
				var val = parseInt(str.substring(2,pos));
				var data = str.substr(pos+2, val);
				str = str.substr(pos + 4 + val);
				break;

			case 'i':
			case 'd':
				var pos = str.indexOf(';');
				var data = parseInt(str.substring(2,pos));
				str = str.substr(pos + 1);
				break;
			
			case 'N':
				var data = null;
				str = str.substr(str.indexOf(';') + 1);
				break;

			case 'b':
				var data = str.charAt(2) == '1' ? true : false;
				break;
		}
		
		return data;
	}

	return f(str);
}

function matchBracket(strG, iniPosG)
{
	var f = function (str, iniPos)
	{
		var nOpen, nClose = iniPos;
		
		do 
		{
			nOpen = str.indexOf('{', nClose+1);
			nClose = str.indexOf('}', nClose+1);

			if (nOpen == -1)
			{
				return nClose;
			}
			
			if (nOpen < nClose )
			{
				nClose = matchBracket(str, nOpen);
			}
			
		} while (nOpen < nClose);

		return nClose;
	}

	return f(strG, iniPosG);
}

/***********************************************\
*                 DATE FUNCTIONS                *
\***********************************************/

	/**
	* Converts a date string into an object second to a php date() date format.
	*
	* The result object have three indexes: day, month and year; (now currently
	* only accepts d , m , and Y in any position and with any separator in the
	* (input) date description string).
	*
	* @author Vinicius Cubas Brand <vinicius@users.sourceforge.net>
	*
	* @param string dateString  The date string in a format described in
	*   phpDateFormat, like for instance '2005/02/09'
	* @param string phpDateFormat  The date descriptor in a php date() format,
	*   like for instance 'Y/m/d'
	* 
	* @todo Other types handling
	*/
	function strtodate(dateString,phpDateFormat)
	{
		var _this = this;
		var elements = new Object;
		this.tmpelm = elements;
		elements['d'] = { leng: 2, pos:-1};
		elements['m'] = { leng: 2, pos:-1};
		elements['Y'] = { leng: 4, pos:-1};
	

		//array to populate - sort order
		var indexes = new Array();

		for (var i in elements)
		{
			elements[i]['pos'] = phpDateFormat.indexOf(i);

			indexes.push(i);
		}

		function sortingFunction(a,b) {
			return _this.tmpelm[a]['pos'] - _this.tmpelm[b]['pos'];
		};
		
		indexes.sort(sortingFunction);

		var offset = 0;
		for (var i in indexes)
		{
			var curr_index = indexes[i];
			elements[curr_index]['start_pos'] = elements[curr_index]['pos'] + offset;
			offset += elements[curr_index]['leng'] - 1;
		}

		for (var i in elements)
		{
			switch (i)
			{
				case 'd':
					var day = parseInt(dateString.slice(elements[i]['start_pos'],elements[i]['start_pos']+elements[i]['leng']));
					break;
				case 'm':
					var month = parseInt(dateString.slice(elements[i]['start_pos'],elements[i]['start_pos']+elements[i]['leng']));
					break;
				case 'Y':
					var year = parseInt(dateString.slice(elements[i]['start_pos'],elements[i]['start_pos']+elements[i]['leng']));
					break;
			}
		}
		var ret = new Object();
		ret['year'] = year;
		ret['month'] = month - 1;
		ret['day'] = day;
		return ret;
	}


/***********************************************\
*               AUXILIAR FUNCTIONS              *
\***********************************************/

/* 
	function js2xmlrpc
	@param methodName  the name of the method, for instance appointmentcenter.uixmlresponder.test
	@param args        all args in sequence, passed to the xml-rpc method
	@return            xml-rpc string corresponding to js objects passed in args
*/
function js2xmlrpc(methodName)
{
	var msg = new XMLRPCMessage(methodName);
	for (var i = 1; i< arguments.length; i++)
	{
		if (i==1 && GLOBALS['extra_get_vars'] && typeof(arguments[i]) == 'object' 
			&& typeof(GLOBALS['extra_get_vars']) == 'object')
		{
			arguments[i]['extra_get_vars'] = GLOBALS['extra_get_vars'];
		}
		msg.addParameter(arguments[i]);
	}
	return msg.xml();
}


/* 
	function xmlrpc2js
	@param responseText  The xml-rpc text of return of a xml-rpc function call
	@return				 Javascript object corresponding to the given xmlrpc 
*/

var xmlrpcHandler = null;
function xmlrpc2js(responseText)
{
	if (!xmlrpcHandler || typeof(xmlrpcHandler) != 'object')
	{
		xmlrpcHandler = importModule("xmlrpc");
	}
	return xmlrpcHandler.unmarshall(responseText);
}

function resizeIcon(id, action)
{
	var element = Element(id);
	
	if (action == 0)
	{
		CC_old_icon_w = element.style.width;
		CC_old_icon_h = element.style.height;

		element.style.zIndex = parseInt(element.style.zIndex) + 1;
		element.style.width = '36px';
		element.style.height = '36px';
		element.style.top = (parseInt(element.style.top) - parseInt(element.style.height)/2) + 'px';
		element.style.left = (parseInt(element.style.left) - parseInt(element.style.width)/2) + 'px';
	}
	else if (action == 1)
	{
		element.style.zIndex = parseInt(element.style.zIndex) - 1;
		element.style.top = (parseInt(element.style.top) + parseInt(element.style.height)/2) + 'px';
		element.style.left = (parseInt(element.style.left) + parseInt(element.style.width)/2) + 'px';
		element.style.width = CC_old_icon_w;
		element.style.height = CC_old_icon_h;
	}
}

function Element (element)
{
	/* IE OBJECTS */
	if (document.all)
	{
		return document.all[element];
	}
	/* MOZILLA OBJECTS */
	else if (document.getElementById)
	{
		return document.getElementById(element);
	}
	/* NS4 OBJECTS */
	else if (document.layers)
	{
		return document.layers[element];
	}
}

function removeHTMLCode(id)
{
	Element(id).parentNode.removeChild(Element(id));
}

function addHTMLCode(parent_id,child_id,child_code,surround_block_tag)
{
	var obj = document.createElement(surround_block_tag);
	Element(parent_id).appendChild(obj);
	obj.id = child_id;
	obj.innerHTML = child_code;
	return obj;
}

function adjustString (str, max_chars)
{
	if (str.length > max_chars)
	{
		return str.substr(0,max_chars) + '...';
	}
	else
	{
		return str;
	}
}

function addSlashes(code)
{
	for (var i = 0; i < code.length; i++)
	{
		switch(code.charAt(i))
		{
			case "'":
			case '"':
			case "\\":
				code = code.substr(0, i) + "\\" + code.charAt(i) + code.substr(i+1);
				i++;
				break;
		}
	}

	return code;
}

function htmlSpecialChars(str)
{
	// TODO: Not implemented!!!
	var pos = 0;
	
	for (var i = 0; i < str.length; i++)
	{
		
	}
}

function replaceComAnd(str, replacer)
{
	var oldPos = 0;
	var pos = 0;
	
	while ((pos = str.indexOf('&', pos)) != -1)
	{
		str = str.substring(oldPos, pos) + replacer + str.substring(pos+1);
	}

	return str;
}

function Timeout(control, code, maxCalls, actualCall, singleTimeout)
{
	if (control())
	{
		if (typeof(code) == 'function')
		{
			code();
		}
		else
		{
			eval(code);
		}
		
		return true;
	}

	if (!actualCall)
	{
		actualCall = 1;
	}

	if (!maxCalls)
	{
		maxCalls = 100;
	}

	if (actualCall == maxCalls)
	{
		showMessage(Element('cc_msg_err_timeout').value);
		return false;
	}

	if (!singleTimeout)
	{
		singleTimeout = 100;
	}

	setTimeout(function(){Timeout(control,code,maxCalls,actualCall+1,singleTimeout);}, singleTimeout);
}

function showMessage(msg, type)
{
	// TODO: Replace alert with 'loading' style div with Ok button

	switch(type)
	{
		case 'confirm':
			return confirm(msg);

		default:
			alert(msg);
			return;
	}
}

// works only correctly in Mozilla/FF and Konqueror
function egw_openWindowCentered2(_url, _windowName, _width, _height, _status)
{
	windowWidth = egw_getWindowOuterWidth();
	windowHeight = egw_getWindowOuterHeight();

	positionLeft = (windowWidth/2)-(_width/2)+egw_getWindowLeft();
	positionTop  = (windowHeight/2)-(_height/2)+egw_getWindowTop();

	windowID = window.open(_url, _windowName, "width=" + _width + ",height=" + _height + 
		",screenX=" + positionLeft + ",left=" + positionLeft + ",screenY=" + positionTop + ",top=" + positionTop +
		",location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status="+_status);
	
	return windowID;
}
function egw_openWindowCentered(_url, _windowName, _width, _height)
{
	return egw_openWindowCentered2(_url, _windowName, _width, _height, 'no');
}

// return the left position of the window
function egw_getWindowLeft()
{
	if(is_mozilla)
	{
		return window.screenX;
	}
	else
	{
		return window.screenLeft;
	}
}

// return the left position of the window
function egw_getWindowTop()
{
	if(is_mozilla)
	{
		return window.screenY;
	}
	else
	{
		//alert(window.screenTop);
		return window.screenTop-90;
	}
}

// get the outerWidth of the browser window. For IE we simply return the innerWidth
function egw_getWindowInnerWidth()
{
	if (is_mozilla)
	{
		return window.innerWidth;
	}
	else
	{
		// works only after the body has parsed
		//return document.body.offsetWidth;
		return document.body.clientWidth;
		//return document.documentElement.clientWidth;
	}
}

// get the outerHeight of the browser window. For IE we simply return the innerHeight
function egw_getWindowInnerHeight()
{
	if (is_mozilla)
	{
		return window.innerHeight;
	}
	else
	{
		// works only after the body has parsed
		//return document.body.offsetHeight;
		//return document.body.clientHeight;
		return document.documentElement.clientHeight;
	}
}

// get the outerWidth of the browser window. For IE we simply return the innerWidth
function egw_getWindowOuterWidth()
{
	if (is_mozilla)
	{
		return window.outerWidth;
	}
	else
	{
		return egw_getWindowInnerWidth();
	}
}

// get the outerHeight of the browser window. For IE we simply return the innerHeight
function egw_getWindowOuterHeight()
{
	if (is_mozilla)
	{
		return window.outerHeight;
	}
	else
	{
		return egw_getWindowInnerHeight();
	}
}

/***********************************************\
*      HTML ELEMENTS AUXILIAR FUNCTIONS         *
\***********************************************/

//-------------------SELECT-------------------------

/* Copy the selected values from one select box to another */
function copyFromSelects(origSelectObj,destSelectObj)
{
	var selectBox1 = origSelectObj;
	var selectBox2 = destSelectObj;
	var exists;
	
	if (selectBox1 == null || selectBox2 == null)
	{
		return false;
	}
	
	var max1 = selectBox1.options.length;
	var max2 = selectBox2.options.length;	
	
	for (var i=0 ; i < max1 ; i++)
	{
		if (selectBox1.options[i].selected)
		{
			exists = false;
			for (var j=0 ; j < max2 ; j++)
			{
				if (selectBox1.options[i].value == selectBox2.options[j].value)
				{
					exists = true;
				}
			}

			if (exists == false)
			{
				selectBox2.options[selectBox2.length] = new Option(selectBox1.options[i].text,selectBox1.options[i].value);
				selectBox1.options[i].selected = false;
			}
		}
	}
}

function removeSelectedOptions(selectId)
{
	var selectBox1 = Element(selectId);
	
	if (selectBox1 == null)
	{
		return false;	
	}
	
	for (var i=0; i < selectBox1.options.length; )
	{
		if (selectBox1.options[i].selected)
		{
			selectBox1.removeChild(selectBox1.options[i]);	
		}
		else
		{
			i++;	
		}
	}
	
}

function clearSelectBox(obj, startIndex)
{
	var nOptions = obj.options.length;

	for (var i = nOptions - 1; i >= startIndex; i--)
	{
		obj.removeChild(obj.options[i]);
	}
}

function fillSelectBox(obj,data)
{

	if (typeof(data) != 'object')
	{
		return false;
	}
	
	var i;
		
	//include new options
	for (i in data)
	{
		if (typeof(data[i]) == 'function')
		{
			continue;
		}

		obj.options[obj.length] = new Option(data[i],i);
	}
}

//use for select-multiple. The values of opts will be selected.
function selectOptions (obj,opts)
{
	if (obj == false || obj.options == false)
	{
		throw('selectOptions: invalid object given as param');
		return false;	
	}
	if (opts == undefined || opts == null) //clean everything
	{
		var objtam  = obj.options.length;
		for (var i=0; i < objtam; i++)
		{
			obj.options[i].selected = false;	
		}
	}
	else
	{		
		if (typeof(opts) != 'object')
		{
			//throw('selectOptions: opts must be an element of type Array or Object');
			return false;
		}

		var objtam  = obj.options.length;
		var optstam = opts.length;

		for (var i=0; i < objtam; i++)
		{
			obj.options[i].selected = false;
			for (var j in opts)
			{
				if (obj.options[i].value == opts[j])
				{
					obj.options[i].selected = true;
				}
			}
		}
	}

}

//return selected options of a select in an array
function getSelectedOptions(obj)
{
	if (obj == null)
	{
		throw('getSelectedOptions: invalid object');
		return new Array();
	}
	
	var max = obj.options.length;
	var response = new Array();
	
	for (var i=0; i< max; i++)
	{
		if (obj.options[i].selected)
		{
			response.push(obj.options[i].value);	
		}
	}
	return response;
}

//return selected values of a select in an array
function getSelectedValues(obj)
{
	if (obj == null || typeof(obj) != 'object')
	{
		return new Object();
	}

	var max = obj.options.length;
	var response = new Object();
	
	for (var i=0; i< max; i++)
	{
		if (obj.options[i].selected)
		{
			response[obj.options[i].value] = obj.options[i].text;
		}
	}
	return response;
}

//return all options of a select in an array
function getAllOptions(id)
{
	var	obj = Element(id);
	
	if (obj == null)
	{
		throw('getSelectedOptions: invalid object');
		return new Array();
	}
	
	var max = obj.options.length;
	var response = new Array();
	
	for (var i=0; i< max; i++)
	{
		response.push(obj.options[i].value);	
	}
	return response;
}


//-------------------RADIO-------------------------

function selectRadio (id, index)
{
	var obj = Element(id);
	var max = obj.options.length;
	for (var i = 0; i < max; i++)
	{
		i == index ? obj.options[i].checked = true : obj.options[i].checked = false;
	}
}

function getSelectedRadio(obj)
{
	if (obj.type == 'radio')
	{
		if (obj.checked)
		{
			return obj.value;
		}
	}
	else if (obj.length)
	{
		var max = obj.length;
		for (var i = 0; i < max; i++)
		{
			if (obj[i].checked)
			{
				return obj[i].value;	
			}	
		}
	}
	return null;
}


/***********************************************\
*                   JSLIB OBJECT                *
\***********************************************/

function cJsLib ()
{
	this._functions = new Array();
	this._arguments = new Array();
	this._original_body_onload = null;

	this.loaded = false;
}

//postpone function to be executed after body had loaded
cJsLib.prototype.postponeFunction = function(obj_function)
{
	if (typeof(obj_function) != 'function')
	{
		throw ('JsLib.postponeFunction: parameter must be a function');	
	}
	
	this._functions.push(obj_function);
	
	// 'arguments' are all args passed to this function
	var args = new Array();
	for (var i = 1; i< arguments.length; i++)
	{
		args.push(arguments[i]);
	}

	if (args.length)
	{
		this._arguments.push(args);
	}
	this.init();
}

//exec postponed functions
cJsLib.prototype.execPostponed = function(e)
{
	if (this._original_body_onload != null)
	{
		this._original_body_onload();	
	}
	
	var code = '';
	var _this = this;
	
	for (var i in this._functions)
	{
		if (typeof(this._functions[i]) != 'function')
		{
			continue;
		}
		
		if (typeof(this._arguments[i]) == 'object')
		{
			code += 'this._functions['+i+'](';
			for (var j in _this._arguments[i])
			{
				code += this._arguments[i][j]+',';
			}

			code = code.substr(code, code.length-1);
			code += ');';

			continue;
		}

		code += 'this._functions['+i+']();';
	}

	eval(code);

	for (var i in this._functions)
	{
		if (typeof(this._arguments[i]) == 'object')
		{
			delete this._arguments[i];
			this._arguments[i] = null;
		}

		delete this._functions[i];
		this._functions[i] = null;
	}
}

//put function on body onload
cJsLib.prototype.init = function()
{
/*	if (this.initialized)
	{
		return;
	}
*/
	this.initialized = true;

	var _this = this;
	var init = function()
	{
		_this.execPostponed();
	};

	Timeout(function() { if (document.body) return true; else return false;}, init);
}

var JsLib = new cJsLib();

// Insert Debug Holder
function _createDebugDOM()
{
	var dbg_holder = document.createElement('xmp');
	
	dbg_holder.id = 'jsapiDebug';
	dbg_holder.style.position = 'absolute';
	dbg_holder.style.left = '1500px';
	dbg_holder.style.top = '0px';
	dbg_holder.style.fontFamily = 'monospace';
	
	var func = function()
	{
		document.body.appendChild(dbg_holder);
	}
	
	JsLib.postponeFunction(func);
}

/***********************************************\
*                   CONSTANTS                   *
\***********************************************/

/***********************************************\
*               GLOBALS VARIABLES               *
\***********************************************/

/***********************************************\
*                OTHER FUNCTIONS                *
\***********************************************/

//JsLib.postponeFunction(function ()
//{
//	dynapi.setPath(GLOBALS['serverRoot']+'/phpgwapi/js/dynapi');
//});
