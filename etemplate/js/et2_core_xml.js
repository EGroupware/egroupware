/**
 * eGroupWare eTemplate2 - JS XML Code
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
 * Loads the given URL asynchronously from the server. When the file is loaded,
 * the given callback function is called, where "this" is set to the given
 * context.
 */
function et2_loadXMLFromURL(_url, _callback, _context)
{
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	// Use the XMLDOM object on IE
	if (window.ActiveXObject)
	{
		var xmldoc = new ActiveXObject("Microsoft.XMLDOM");

		// Set the callback function
		xmldoc.onreadystatechange = function() {
			if (xmldoc && xmldoc.readyState == 4)
			{
				// Find the root node - the root node is the node which is not
				// the "xml", not a text node and not a comment node - those nodes
				// are marked with an "#"
				for (var i = 0; i < xmldoc.childNodes.length; i++)
				{
					var nodeName = xmldoc.childNodes[i].nodeName;
					if (nodeName != "xml" && nodeName.charAt(0) != "#")
					{
						// Call the callback function and pass the current node
						_callback.call(_context, xmldoc.childNodes[i]);
						return;
					}
				}

				throw("Could not find XML root node.");
			}
		}

		xmldoc.load(_url);
	}
	else if (window.XMLHttpRequest)
	{
		// Otherwise make an XMLHttpRequest. Tested with Firefox 3.6, Chrome, Opera
		var xmlhttp = new XMLHttpRequest();

		// Set the callback function
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState == 4)
			{
				if(xmlhttp.responseXML)
				{
					var xmldoc = xmlhttp.responseXML.documentElement;
					_callback.call(_context, xmldoc);
				}
			}
		}

		// Force the browser to interpret the result as XML. overrideMimeType is
		// non-standard, so we check for its existance.
		if (xmlhttp.overrideMimeType)
		{
			xmlhttp.overrideMimeType("application/xml");
		}

		// Retrieve the script asynchronously
		xmlhttp.open("GET", _url, true);
		xmlhttp.send(null);
	}
	else
	{
		throw("XML Request object could not be created!");
	}
}

function et2_directChildrenByTagName(_node, _tagName)
{
	// Normalize the tag name
	_tagName = _tagName.toLowerCase();

	var result = [];
	for (var i = 0; i < _node.childNodes.length; i++)
	{
		if (_tagName == _node.childNodes[i].nodeName.toLowerCase())
		{
			result.push(_node.childNodes[i]);
		}
	}

	return result;
}

function et2_filteredNodeIterator(_node, _callback, _context)
{
	for (var i = 0; i < _node.childNodes.length; i++)
	{
		var node = _node.childNodes[i];
		var nodeName = node.nodeName.toLowerCase();
		if (nodeName.charAt(0) != "#")
		{
			_callback.call(_context, node, nodeName);
		}
	}
}

function et2_readAttrWithDefault(_node, _name, _default)
{
		var val = _node.getAttribute(_name);

		return (val === null) ? _default : val;
}


