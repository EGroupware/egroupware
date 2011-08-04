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
				_callback.call(_context, xmldoc);
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
				var xmldoc = xmlhttp.responseXML.documentElement;
				_callback.call(_context, xmldoc);
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

