/**
 * EGroupware eTemplate2 - JS XML Code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

import "../../../vendor/bower-asset/jquery/dist/jquery.min.js";
import "../jquery/jquery.noconflict.js";
import {egw} from "../jsapi/egw_global.js";

/**
 * Loads the given URL asynchronously from the server
 *
 * We make the Ajax call through main-windows jQuery object, to ensure cached copy
 * in main-windows etemplate2 prototype works in IE too!
 *
 * @param {string} _url
 * @param {function} _callback function(_xml)
 * @param {object} _context for _callback
 * @param {function} _fail_callback function(_xml)
 * @return Promise
 */
export function et2_loadXMLFromURL(_url : string, _callback? : Function, _context? : object, _fail_callback? : Function)
{
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	// use window object from main window with same algorithm as for the template cache
	let win;
	try {
		if (opener && opener.etemplate2)
		{
			win = opener;
		}
	}
	catch (e) {
		// catch security exception if opener is from a different domain
	}
	if (typeof win == "undefined")
	{
		win = egw.top;
	}

	// if preprocessor is missing --> add it
	if (_url.indexOf('/etemplate.php/') === -1)
	{
		const parts = _url.match(/^(.*)(\/[^/]+\/templates\/.*)$/);
		if (parts)
		{
			_url = parts[1]+'/api/etemplate.php'+parts[2];
		}
	}

	// we add the full url (protocol and domain) as sometimes just the path
	// gives a CSP error interpreting it as file:///path
	// (if there are a enough 404 errors in html content ...)
	return win.fetch((_url[0] === '/' ? location.protocol+'//'+location.host : '')+_url, {
		method: 'GET'
	})
		.then((response) => {
			if (!response.ok) {
				throw response;
			}
			return response.text();
		})
		.then((xml) => {
			const parser = new window.DOMParser();
			return parser.parseFromString( xml, "text/xml" );
		})
		.then((xmldoc) => {
			if (typeof _callback === 'function') {
				_callback.call(_context, xmldoc.children[0]);
			}
			return xmldoc.children[0];
		})
		.catch((_err) => {
			egw().message('Loading eTemplate from '+_url+' failed!'+"\n\n"+
				(typeof _err.stack !== 'undefined' ? _err.stack : _err.status+' '+_err.statusText), 'error');
			if(typeof _fail_callback === 'function') {
				_fail_callback.call(_context, _err);
			}
		});
}

export function et2_directChildrenByTagName(_node : HTMLElement, _tagName : String) : HTMLElement[]
{
	// Normalize the tag name
	_tagName = _tagName.toLowerCase();

	let result = [];
	for (let i = 0; i < _node.childNodes.length; i++)
	{
		if (_tagName == _node.childNodes[i].nodeName.toLowerCase())
		{
			result.push(_node.childNodes[i]);
		}
	}

	return result;
}

export function et2_filteredNodeIterator(_node : HTMLElement, _callback : Function, _context : object)
{
	for (let i = 0; i < _node.childNodes.length; i++)
	{
		let node = _node.childNodes[i];
		let nodeName = node.nodeName.toLowerCase();
		if (nodeName.charAt(0) != "#")
		{
			_callback.call(_context, node, nodeName);
		}
	}
}

export function et2_readAttrWithDefault(_node : HTMLElement, _name : string, _default : string) : string
{
	let val = _node.getAttribute(_name);

	return (val === null) ? _default : val;
}