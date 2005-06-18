  /***************************************************************************\
  * eGroupWare - Contacts Center                                              *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Raphael Derosso Pereira <raphaelpereira@users.sourceforge.net>         *
  *  - Jonas Goes <jqhcb@users.sourceforge.net>                               *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Think.e - http://www.think-e.com.br                         *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

/***********************************************\
*               INITIALIZATION                  *
\***********************************************/

/***********************************************\
*               AUXILIAR FUNCTIONS              *
\***********************************************/

function Element (element)
{
	return document.all[element];
}

/***********************************************\
*      HTML ELEMENTS AUXILIAR FUNCTIONS         *
\***********************************************/

/***********************************************\
*                OTHER FUNCTIONS                *
\***********************************************/

//postpone function to be executed after body had loaded
cJsLib.prototype.postponeFunction = function(obj_function)
{
	if (document && document.body && this.loaded)
	{
		obj_function();
		return;
	}
	
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
cJsLib.prototype.execPostponed = function()
{
	if (this._original_body_onload != null)
	{
		this._original_body_onload();	
	}
	
	var code = '';
	var _this = this;
	
	for (var i in this._functions)
	{
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
/*
	for (var i=0; i < this._functions.length; i++)
	{
		this._functions[i](); //TODO treat args
	}
*/
}

//put function on body onload
cJsLib.prototype.init = function()
{
	if (this.initialized)
	{
		return;
	}

	this.initialized = true;
	this.loaded = false;

	var _this = this;
	var init = function()
	{
		var execPostponed = function()
		{
			_this.execPostponed();
			_this.loaded = true;
		};
		_this._original_body_onload = document.body.onload;
		document.body.onload = execPostponed;
		//document.body.onload = function() { alert('Rodou!')};
	};

	Timeout(function() { if (document.body) return true; else return false;}, init);
}

var JsLib = new cJsLib();


/***********************************************\
 *         JS Object Extension                 *
\***********************************************/
// Insert Debug Holder
function _createDebugDOM()
{
	var dbg_holder = document.createElement('xmp');
	
	dbg_holder.id = 'jsapiDebug';
	dbg_holder.style.position = 'absolute';
	dbg_holder.style.left = '1500px';
	dbg_holder.style.top = '0px';
	dbg_holder.style.fontFamily = 'monospace';
	
	document.body.appendChild(dbg_holder);
}
