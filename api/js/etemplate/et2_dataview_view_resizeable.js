/**
 * EGroupware eTemplate2 - Functions which allow resizing of table headers
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/**
 * This set of functions is currently only supporting resizing in ew-direction
 */

(function()
{
	"use strict";

	// Define some constants
	var RESIZE_BORDER = 12;
	var RESIZE_MIN_WIDTH = 25;
	var RESIZE_ADD = 2; // Used to ensure mouse is under the resize element after resizing has finished

	// In resize region returns whether the mouse is currently in the
	// "resizeRegion"
	function inResizeRegion(_x, _elem)
	{
		var ol = _x - _elem.offset().left;
		return (ol > (_elem.outerWidth(true) - RESIZE_BORDER));
	}

	var helper = null;
	var overlay = null;
	var didResize = false;
	var resizeWidth = 0;

	function startResize(_outerElem, _elem, _callback, _column)
	{
		if (overlay == null || helper == null)
		{
			// Prevent text selection
			// FireFox handles highlight prevention (text selection) different than other browsers
			if (typeof _elem[0].style.MozUserSelect !="undefined")
			{
				_elem[0].style.MozUserSelect = "none";
			}
			else
			{
				_elem[0].onselectstart = function() {
					return false;
				};
			}

			// Indicate resizing is in progress
			$j(_outerElem).addClass('egwResizing');

			// Reset the "didResize" flag
			didResize = false;

			// Create the resize helper
			var left = _elem.offset().left;
			helper = $j(document.createElement("div"))
				.addClass("egwResizeHelper")
				.appendTo("body")
				.css("top", _elem.offset().top + "px")
				.css("left", left + "px")
				.css("height", _outerElem.outerHeight(true) + "px");

			// Create the overlay which will be catching the mouse movements
			overlay = $j(document.createElement("div"))
				.addClass("egwResizeOverlay")

				.bind("mousemove", function(e) {
					didResize = true;
					resizeWidth = Math.max(e.pageX - left + RESIZE_ADD,
						_column && _column.minWidth ? _column.minWidth : RESIZE_MIN_WIDTH
					);
					helper.css("width", resizeWidth + "px");
				})

				.bind("mouseup", function() {
					stopResize(_outerElem);

					// Reset text selection
					_elem[0].onselectstart = null;

					// Call the callback if the user actually performed a resize
					if (didResize)
					{
						_callback(resizeWidth);
					}
				})
				.appendTo("body");
		}
	}

	function stopResize(_outerElem)
	{

		$j(_outerElem).removeClass('egwResizing');
		if (helper != null)
		{
			helper.remove();
			helper = null;
		}

		if (overlay != null)
		{
			overlay.remove();
			overlay = null;
		}
	}

	this.et2_dataview_makeResizeable = function(_elem, _callback, _context)
	{
		// Get the table surrounding the given element - this element is used to
		// align the helper properly
		var outerTable = _elem.closest("table");

		// Bind the "mousemove" event in the "resize" namespace
		_elem.bind("mousemove.resize", function(e) {
			var stopResize = false;
			// Stop switch to resize cursor if the mouse position
			// is more intended for scrollbar not the resize edge
			// 8pixel is an arbitary number for scrolbar area
			if (e.target.clientHeight < e.target.scrollHeight && e.target.offsetWidth - e.offsetX <= 8)
			{
				stopResize = true;
			}
			_elem.css("cursor", inResizeRegion(e.pageX, _elem) && !stopResize? "ew-resize" : "auto");
		});

		// Bind the "mousedown" event in the "resize" namespace
		_elem.bind("mousedown.resize", function(e) {
			var stopResize = false;
			// Stop resize if the mouse position is more intended
			// for scrollbar not the resize edge
			// 8pixel is an arbitary number for scrolbar area
			if (e.target.clientHeight < e.target.scrollHeight && e.target.offsetWidth - e.offsetX <= 8)
			{
				stopResize = true;
			}
			// Do not triger startResize if clicked element is select-tag, as it may causes conflict in some browsers
			if (inResizeRegion(e.pageX, _elem) && e.target.tagName != 'SELECT' && !stopResize)
			{
				// Start the resizing
				startResize(outerTable, _elem, function(_w) {
					_callback.call(_context, _w);
				}, _context);
			}

		});
	};

	this.et2_dataview_resetResizeable = function(_elem)
	{
		// Remove all events in the ".resize" namespace from the element
		_elem.unbind(".resize");
	};
}).call(window);

