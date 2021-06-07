/**
 * EGroupware eTemplate2 - Functions which allow resizing of table headers
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

import {egw} from "../jsapi/egw_global";

/**
 * This set of functions is currently only supporting resizing in ew-direction
 */

export class et2_dataview_view_resizable
{

	// Define some constants
	public static readonly RESIZE_BORDER = 12;
	public static readonly RESIZE_MIN_WIDTH = 25;
	public static readonly RESIZE_ADD = 2; // Used to ensure mouse is under the resize element after resizing has finished


	public static helper : JQuery = null;
	public static overlay : JQuery = null;
	public static didResize = false;
	public static resizeWidth = 0;

	// In resize region returns whether the mouse is currently in the
	// "resizeRegion"
	public static  inResizeRegion(_x, _elem)
	{
		var ol = _x - _elem.offset().left;
		return (ol > (_elem.outerWidth(true) - et2_dataview_view_resizable.RESIZE_BORDER));
	}

	public static startResize(_outerElem, _elem, _callback, _column)
	{
		if (this.overlay == null || this.helper == null)
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
			jQuery(_outerElem).addClass('egwResizing');

			// Reset the "didResize" flag
			this.didResize = false;

			// Create the resize helper
			var left = _elem.offset().left;
			this.helper = jQuery(document.createElement("div"))
				.addClass("egwResizeHelper")
				.appendTo("body")
				.css("top", _elem.offset().top + "px")
				.css("left", left + "px")
				.css("height", _outerElem.outerHeight(true) + "px");

			// Create the overlay which will be catching the mouse movements
			this.overlay = jQuery(document.createElement("div"))
				.addClass("egwResizeOverlay")

				.bind("mousemove", function(e) {
					this.didResize = true;
					this.resizeWidth = Math.max(e.pageX - left + et2_dataview_view_resizable.RESIZE_ADD,
						_column && _column.minWidth ? _column.minWidth : et2_dataview_view_resizable.RESIZE_MIN_WIDTH
					);
					this.helper.css("width", this.resizeWidth + "px");
				}.bind(this))

				.bind("mouseup", function() {
					this.stopResize(_outerElem);

					// Reset text selection
					_elem[0].onselectstart = null;

					// Call the callback if the user actually performed a resize
					if (this.didResize)
					{
						_callback(this.resizeWidth);
					}
				}.bind(this))
				.appendTo("body");
		}
	}

	public static stopResize(_outerElem)
	{

		jQuery(_outerElem).removeClass('egwResizing');
		if (this.helper != null)
		{
			this.helper.remove();
			this.helper = null;
		}

		if (this.overlay != null)
		{
			this.overlay.remove();
			this.overlay = null;
		}
	}

	public static makeResizeable = function(_elem, _callback, _context)
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
			_elem.css("cursor", et2_dataview_view_resizable.inResizeRegion(e.pageX, _elem) && !stopResize? "ew-resize" : "auto");
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
			if (et2_dataview_view_resizable.inResizeRegion(e.pageX, _elem) && e.target.tagName != 'SELECT' && !stopResize)
			{
				// Start the resizing
				et2_dataview_view_resizable.startResize(outerTable, _elem, function(_w) {
					_callback.call(_context, _w);
				}, _context);
			}

		});

		// Bind double click for auto-size
		_elem.dblclick(function(e) {
			// Just show message for relative width columns
			if(_context && _context.relativeWidth)
			{
				return egw.message(egw.lang('You tried to automatically size a flex column, which always takes the rest of the space','info'));
			}
			// Find column class - it's usually the first one
			var col_class = '';
			for(var i = 0; i < this.classList.length; i++)
			{
				if(this.classList[i].indexOf('gridCont') === 0)
				{
					col_class = this.classList[i];
					break;
				}
			}

			// Find widest part, including header
			var column = jQuery(this);
			column.children().css('width','auto');
			var max_width = column.children().children().innerWidth();
			var padding = column.outerWidth(true) - max_width;
			
			var resize = jQuery(this).closest('.egwGridView_outer')
				.find('tbody td.'+col_class+'> div:first-child')
				.add(column.children())
			// Set column width to auto to allow space for everything to flow
				.css('width','auto');
			resize.children()
				.css({'white-space':'nowrap'})
				.each(function()
					{
						var col = jQuery(this);
						// Find visible (text) children and force them to not wrap
						var children = col.find('span:visible, time:visible, label:visible')
							.css({'white-space':'nowrap'});
						this.offsetWidth;
						children.each(function()
						{
							var child = jQuery(this);
							this.offsetWidth;
							if(child.outerWidth() > max_width)
							{
								max_width = child.outerWidth();
							}
							window.getComputedStyle(this).width;
						});
						this.offsetWidth;
						if(col.innerWidth() > max_width)
						{
							max_width = col.innerWidth();
						}

						// Reset children
						children.css('white-space','');
						children.css('display','');
					}
				)
				.css({'white-space':''});
		
			// Reset column
			column.children().css('width','');
			resize.css('width','');
			_callback.call(_context, max_width+padding);
		});
	};

	public static et2_dataview_resetResizeable = function(_elem)
	{
		// Remove all events in the ".resize" namespace from the element
		_elem.unbind(".resize");
	}
}

