/**
 * EGroupware eTemplate2 - JS XML Code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/**
 * Loads the given URL asynchronously from the server
 *
 * We make the Ajax call through main-windows jQuery object, to ensure cached copy
 * in main-windows etemplate2 prototype works in IE too!
 *
 * @param {string} _url
 * @param {function} _callback function(_xml)
 * @param {object} _context for _callback
 */
function et2_loadXMLFromURL(_url, _callback, _context)
{
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	// use window object from main window with same algorithm as for the template cache
	var win;
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
		win = top;
	}
	win.jQuery.ajax({
		url: _url,
		context: _context,
		type: 'GET',
		dataType: 'xml',
		success: function(_data, _status, _xmlhttp){
			_callback.call(_context, _data.documentElement);
		},
		error: function(_xmlhttp, _err) {
			egw().debug('error', 'Loading eTemplate from '+_url+' failed! '+_xmlhttp.status+' '+_xmlhttp.statusText);
		}
	});
}

function et2_directChildrenByTagName(_node, _tagName)
{
	// Normalize the tag name
	_tagName = _tagName.toLowerCase();

	var result = [];
	var children = _node.childNodes || _node.children || [];
	for (var i = 0; i < children.length; i++)
	{
		var child = children[i];
		child.parentNode = _node;
		if (child.nodeName && _tagName === child.nodeName.toLowerCase() || child.tag && _tagName === child.tag)
		{
			result.push(child);
		}
	}

	return result;
}

function et2_filteredNodeIterator(_node, _callback, _context)
{
	if(!_node.children) return;
	for (var i = 0; i < _node.children.length; i++)
	{
		var node = _node.children[i];
		node.parentNode = _node;
		var nodeName = node.tag;
		if (nodeName.charAt(0) != "#")
		{
			_callback.call(_context, node, nodeName);
		}
	}
}

function et2_readAttrWithDefault(_node, _name, _default)
{
	if( _node.getAttribute)
	{
		var val = _node.getAttribute(_name);
	}
	else if (_node.attributes)
	{
		var val = _node.attributes[_name];
	}
	return (val === null || typeof val === 'undefined') ? _default : val;
}


