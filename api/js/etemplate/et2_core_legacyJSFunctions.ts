/**
 * EGroupware eTemplate2 - Execution layer for legacy event code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-21
 */

/*egw:uses
	et2_interfaces;
	et2_core_common;
*/


import {egw} from "../jsapi/egw_global";
import {et2_IDOMNode} from "./et2_core_interfaces";
import {et2_form_name} from "./et2_core_common";

export function et2_compileLegacyJS(_code, _widget, _context)
{
	// Replace the javascript pseudo-functions
	_code = js_pseudo_funcs(_code,_widget);

	// Check whether _code is simply "1" -- if yes replace it accordingly
	if (_code === '1')
	{
		_code = 'widget.getInstanceManager().submit(); return false;';
	}

	// Check whether some pseudo-variables still reside inside of the code,
	// if yes, replace them.
	if (_code.indexOf("$") >= 0 || _code.indexOf("@") >= 0)
	{
		// Get the content array manager for the widget
		var mgr = _widget.getArrayMgr("content");
		if(mgr)
		{
			_code = mgr.expandName(_code);
		}
		// If replacement cleared the code, skip the rest
		if(!_code)
		{
			return false;
		}
	}

	// Context is the context in which the function will run. Set context to
	// null as a default, so that it's possible to find bugs where "this" is
	// accessed in the code, but not properly set.
	var context = _context ? _context : null;

	// Check whether the given widget implements the "et2_IDOMNode"
	// interface
	if (!context && _widget.implements(et2_IDOMNode))
	{
		context = _widget.getDOMNode();
	}

	// Check to see if it's referring to an existing function with no arguments specified.
	// If so, bind context & use it directly
	if(_code.indexOf("(") === -1)
	{
		var parts = _code.split(".");
		var existing_func = parts.pop();
		var parent = _widget.egw().window;
		for (var i = 0; i < parts.length; ++i) {
			if (typeof parent[parts[i]] !== "undefined") {
				parent = parent[parts[i]];
			}
			// Nope
			else {
				break;
			}
		}
		if (typeof parent[existing_func] === "function") {
			// Bind the object so no matter what happens, context is correct
			return parent[existing_func].bind(parent);
		}
	}

	// Generate the function itself, if it fails, log the error message and
	// return a function which always returns false
	try {
		// Code is app.appname.function, add the arguments so it can be executed
		if (typeof _code == 'string' && _code.indexOf('app') == 0 && _code.split('.').length >= 3 && _code.indexOf('(') == -1)
		{
			const parts = _code.split('.');
			const app = _widget.getInstanceManager().app_obj;
			// check if we need to load the object
			if (parts.length === 3 && typeof app[parts[1]] === 'undefined')
			{
				return function (ev, widget)
				{
					return egw.applyFunc(_code, [ev, widget]);
				}
			}
			// Code is app.appname.function, add the arguments so it can be executed
			_code += '(ev,widget)';
			// Return what the function returns
			if(_code.indexOf("return") == -1)
			{
				_code = "return " + _code;
			}
		}
		// use app object from etemplate2, which might be private and not just window.app
		_code = _code.replace(/(window\.)?app\./, 'widget.getInstanceManager().app_obj.');

		var func = new Function('ev', 'widget', _code);
	} catch(e) {
		_widget.egw().debug('error', 'Error while compiling JS code ', _code);
		return (function() {return false;});
	}

	// Execute the code and return its results, pass the egw instance and
	// the widget
	return function(ev, widget?)
	{
		// Dump the executed code for debugging
		egw.debug('log', 'Executing legacy JS code: ', _code);

		if(arguments && arguments.length > 2)
		{
			egw.debug('warn', 'Legacy JS code only supports 2 arguments (event and widget)', _code, arguments);
		}
		// Return the result of the called function
		return func.call(context, ev, widget || _widget);
	};
}

/**
 * Resolve javascript pseudo functions in onclick or onchange:
 * - egw::link('$l','$p') calls egw.link($l,$p)
 * - form::name('name') returns expanded name/id taking into account the name at that point of the template hierarchy
 * - egw::lang('Message ...') translate the message, calls egw.lang()
 * - confirm('message') translates 'message' and adds a '?' if not present
 * - window.open() replaces it with egw_openWindowCentered2()
 * - xajax_doXMLHTTP('etemplate. replace ajax calls in widgets with special handler not requiring etemplate run rights
 *
 * @param {string} _val onclick, onchange, ... action
 * @param {et2_widget} widget
 * @ToDo replace xajax_doXMLHTTP with egw.json()
 * @ToDo replace (common) cases of confirm with new dialog, idea: calling function supplys function to call after confirm
 * @ToDo template::styles(name) inserts the styles of a named template
 * @return string
 */
function js_pseudo_funcs(_val,widget)
{
	if (_val.indexOf('egw::link(') != -1)
	{
		_val = _val.replace(/egw::link\(/g,'egw.link(');
	}

	if (_val.indexOf('form::name(') != -1)
	{
		// et2_form_name doesn't care about ][, just [
		var _cname = widget.getPath() ? widget.getPath().join("[") : false;
		document.et2_form_name = et2_form_name;
		_val = _val.replace(/form::name\(/g, "'"+widget.getRoot()._inst.uniqueId+"_'+"+(_cname ? "document.et2_form_name('"+_cname+"'," : '('));
	}

	if (_val.indexOf('egw::lang(') != -1)
	{
		_val = _val.replace(/egw::lang\(/g,'egw.lang(');
	}

	// ToDo: inserts the styles of a named template
	/*if (preg_match('/template::styles\(["\']{1}(.*)["\']{1}\)/U',$on,$matches))
	{
		$tpl = $matches[1] == $this->name ? $this : new etemplate($matches[1]);
		$on = str_replace($matches[0],"'<style>".str_replace(array("\n","\r"),'',$tpl->style)."</style>'",$on);
	}*/

	// translate messages in confirm()
	if (_val.indexOf('confirm(') != -1)
	{
		_val = _val.replace(/confirm\((['"])(.*?)(\?)?['"]\)/,"confirm(egw.lang($1$2$1)+'$3')"); // add ? if not there, saves extra phrase
	}

	// replace window.open() with EGw's egw_openWindowCentered2()
	if (_val.indexOf('window.open(') != -1)
	{
		_val = _val.replace(/window.open\('(.*)','(.*)','dependent=yes,width=([^,]*),height=([^,]*),scrollbars=yes,status=(.*)'\)/,
			"egw_openWindowCentered2('$1', '$2', $3, $4, '$5')");
	}

	// replace xajax calls to code in widgets, with the "etemplate" handler,
	// this allows to call widgets with the current app, otherwise everyone would need etemplate run rights
	if (_val.indexOf("xajax_doXMLHTTP('etemplate.") != -1)
	{
		_val = _val.replace(/^xajax_doXMLHTTP\('etemplate\.([a-z]+_widget\.[a-zA-Z0-9_]+)\'/,
			"xajax_doXMLHTTP('"+egw.getAppName()+".$1.etemplate'");
	}

	if (_val.indexOf('this.form.submit()') != -1)
	{
		_val = _val.replace('this.form.submit()','widget.getInstanceManager().submit()');
	}
	return _val;
}
