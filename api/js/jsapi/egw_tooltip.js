/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/*egw:uses
	vendor.bower-asset.jquery.dist.jquery;
	egw_core;
*/
import './egw_core.js';

/**
 *
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('tooltip', egw.MODULE_WND_LOCAL, function(_app, _wnd)
{
	"use strict";

	var tooltip_div = null;
	var current_elem = null;

	var time_delta = 100;
	var show_delta = 0;
	var show_delay = 200;

	var x = 0;
	var y = 0;

	var optionsDefault = {
		hideonhover: true,
		position:'right',
		open: function(){},
		close: function(){}
	};

	/**
	 * Removes the tooltip_div from the DOM if it does exist.
	 */
	function hide()
	{
		if (tooltip_div != null)
		{
			tooltip_div.remove();
			tooltip_div = null;
		}
	}

	/**
	 * Shows the tooltip at the current cursor position.
	 */
	function show(node, event, options)
	{
		if (tooltip_div && typeof x !== 'undefined' && typeof y !== 'undefined')
		{
			options.open.call(node, event, tooltip_div);
			//Calculate the cursor_rectangle - this is a space the tooltip might
			//not overlap with
			var cursor_rect = {
				left: (x - 8),
				top: (y - 8),
				right: (x + (options.position == "center" ? -1 * tooltip_div.width()/2 : 8)),
				bottom: (y + 8)
			};

			//Calculate how much space is left on each side of the rectangle
			var window_width = jQuery(_wnd.document).width();
			var window_height = jQuery(_wnd.document).height();
			var space_left = {
				left: (cursor_rect.left),
				top: (cursor_rect.top),
				right: (window_width - cursor_rect.right),
				bottom: (window_height - cursor_rect.bottom)
			};

			//Get the width and the height of the tooltip
			var tooltip_width = tooltip_div.width();
			if (tooltip_width > 300) tooltip_width = 300;
			var tooltip_height = tooltip_div.height();

			if (space_left.right < tooltip_width) {
				tooltip_div.css('left', Math.max(0,cursor_rect.left - tooltip_width));
			} else if (space_left.left >= tooltip_width) {
				tooltip_div.css('left', cursor_rect.right);
			} else	{
				tooltip_div.css('left', cursor_rect.right);
				tooltip_div.css('max-width', space_left.right);
			}

			// tooltip does fit neither above nor below: put him vertical centered left or right of cursor
			if (space_left.bottom < tooltip_height && space_left.top < tooltip_height) {
				if (tooltip_height > window_height-20) {
					tooltip_div.css('max-height', tooltip_height=window_height-20);
				}
				tooltip_div.css('top', (window_height-tooltip_height)/2);
			} else if (space_left.bottom < tooltip_height) {
				tooltip_div.css('top', cursor_rect.top - tooltip_height);
			} else {
				tooltip_div.css('top', cursor_rect.bottom);
			}
			tooltip_div.fadeIn(100);

		}
	}

	/**
	 * Creates the tooltip_div with the given text.
	 *
	 * @param {string} _html
	 * @param {boolean} _isHtml if set to true content gets appended as html
	 */
	function prepare(_html, _isHtml, _options)
	{
		// Free and null the old tooltip_div
		hide();

		//Generate the tooltip div, set it's text and append it to the body tag
		tooltip_div = jQuery(_wnd.document.createElement('div'));
		tooltip_div.hide();
		if (_isHtml)
		{
			tooltip_div.append(_html);
		}
		else
		{
			tooltip_div.text(_html)
		}
		tooltip_div.addClass("egw_tooltip");
		jQuery(_wnd.document.body).append(tooltip_div);

		//The tooltip should automatically hide when the mouse comes over it
		tooltip_div.mouseenter(function() {
				if (_options.hideonhover) hide();
		});
	}

	/**
	 * showTooltipTimeout is used to prepare showing the tooltip.
	 */
	function showTooltipTimeout(node, event, options)
	{
		if (current_elem != null)
		{
			show_delta += time_delta;
			if (show_delta < show_delay)
			{
				//Repeat the call of timeout
				_wnd.setTimeout(function(){showTooltipTimeout(this, event, options)}.bind(node), time_delta);
			}
			else
			{
				show_delta = 0;
				show(node, event, options);
			}
		}
	}

	return {
		/**
		 * Binds a tooltip to the given DOM-Node with the given html.
		 * It is important to remove all tooltips from all elements which are
		 * no longer needed, in order to prevent memory leaks.
		 *
		 * @param _elem is the element to which the tooltip should get bound.
		 * @param _html is the html code which should be shown as tooltip.
		 * @param _options
		 */
		tooltipBind: function(_elem, _html, _isHtml, _options) {

			var options = {...optionsDefault, ...(_options||{})};

			_elem = jQuery(_elem);
			if (_html != '')
			{
				_elem.bind('mouseenter.tooltip', function(e) {
					if (_elem != current_elem)
					{
						//Prepare the tooltip
						prepare(_html, _isHtml, options);

						// Set the current element the mouse is over and
						// initialize the position variables
						current_elem = _elem;
						show_delta = 0;
						x = e.clientX;
						y = e.clientY;
						let self = this;
						// Create the timeout for showing the timeout
						_wnd.setTimeout(function(){showTooltipTimeout(self, e, options)}, time_delta);
					}

					return false;
				});

				_elem.bind('mouseleave.tooltip', function(e) {
					current_elem = null;
					show_delta = 0;
					if (options.close.call(this, e, tooltip_div)) return;
					if (tooltip_div)
					{
						tooltip_div.fadeOut(100);
					}
				});

				_elem.bind('mousemove.tooltip', function(e) {
					//Calculate the distance the mouse took since the last call of mousemove
					var dx = x - e.clientX;
					var dy = y - e.clientY;
					var movedist = Math.sqrt(dx * dx + dy * dy);

					//Block appereance of the tooltip on fast movements (with small movedistances)
					if (movedist > 2)
					{
						show_delta = 0;
					}

					x = e.clientX;
					y = e.clientY;
				});
			}
		},

		/**
		 * Unbinds the tooltip from the given DOM-Node.
		 *
		 * @param _elem is the element from which the tooltip should get
		 * removed. _elem has to be a jQuery node.
		 */
		tooltipUnbind: function(_elem) {
			_elem = jQuery(_elem);
			if (current_elem == _elem) {
				hide();
				current_elem = null;
			}

			// Unbind all "tooltip" events from the given element
			_elem.unbind('.tooltip');
		},

		tooltipDestroy: function () {
			if (tooltip_div)
			{
				tooltip_div.fadeOut(100);
				tooltip_div.remove();
			}
		}
	};

});

