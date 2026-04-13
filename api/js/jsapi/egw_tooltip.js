/* jshint esversion: 11 */
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
 egw_core;
 */
import './egw_core.js';

/**
 *
 * @param {string} _app application name object is instanciated for
 * @param {object} _wnd window object is instanciated for
 */
egw.extend('tooltip', egw.MODULE_WND_LOCAL, function (_app, _wnd)
{
	"use strict";

	const tooltipped = new Set();
	const tooltipData = new WeakMap();
	let tooltip_div = null;
	let current_elem = null;
	let hide_timeout = null;

	function removeDiv()
	{
		if (!tooltip_div)
		{
			return;
		}
		tooltip_div.remove();
		tooltip_div = null;
	}

	_wnd.addEventListener("pagehide", () =>
	{
		[...tooltipped].forEach(node =>
		{
			egw.tooltipUnbind(node);
		});
		tooltipped.clear();

		if (tooltip_div)
		{
			removeDiv();
		}
		return null;
	});

	const time_delta = 100;
	let show_delta = 0;
	const show_delay = 200;

	let x = 0;
	let y = 0;

	const optionsDefault = {
		hideonhover: true,
		position: 'right',
		open: function () {},
		close: function () {}
	};

	const mouseenter = function (e)
	{
		const elem = e.currentTarget
		const data = tooltipData.get(elem);
		if (!data)
		{
			return false;
		}
		if (elem !== current_elem)
		{
			//Prepare the tooltip
			prepare(data.html, data.isHtml, data.options);

			// Set the current element the mouse is over and
			// initialize the position variables
			current_elem = elem;
			show_delta = 0;
			x = e.clientX;
			y = e.clientY;
			// Create the timeout for showing the timeout
			_wnd.setTimeout(function () {showTooltipTimeout(elem, e, data.options)}, time_delta);
		}

		return false;
	};

	const mouseleave = function (e)
	{
		const elem = e.currentTarget
		const data = tooltipData.get(elem);
		show_delta = 0;

		if (tooltip_div && e.relatedTarget && tooltip_div.contains(e.relatedTarget))
		{
			return;
		}

		if (hide_timeout)
		{
			_wnd.clearTimeout(hide_timeout);
		}
		hide_timeout = _wnd.setTimeout(() =>
		{
			current_elem = null;
			if (data.options.close.call(this, e, tooltip_div))
			{
				return;
			}
			if (tooltip_div)
			{
				setStyle(tooltip_div, 'display', 'none')
			}
		}, 150);
	};

	const mousemove = function (e)
	{
		//Calculate the distance the mouse took since the last call of mousemove
		const dx = x - e.clientX;
		const dy = y - e.clientY;
		const movedist = Math.sqrt(dx * dx + dy * dy);

		//Block appereance of the tooltip on fast movements (with small movedistances)
		if (movedist > 2)
		{
			show_delta = 0;
		}

		x = e.clientX;
		y = e.clientY;
	}

	/**
	 * We might not get an actual DOMNode, but we want to work on the actual DOMNodes not e.g. a jquery Object
	 */
	function getActualNode(_elem)
	{
		return _elem instanceof Node ? _elem : (typeof _elem.get === "function" ? _elem.get(0) : _elem);
	}

	/**
	 * We might not get an actual DOMNode, but we want to work on the actual DOMNodes not e.g. a jquery Object
	 */
	function getActualNode(_elem)
	{
		return _elem instanceof Node ? _elem : (typeof _elem.get === "function" ? _elem.get(0) : _elem);
	}

	/**
	 * Removes the tooltip_div from the DOM if it does exist.
	 */
	function hide()
	{
		if (tooltip_div)
		{
			removeDiv();
		}
		//there should only be the one we removed, but just in case, remove all tooltips
		_wnd.document.querySelectorAll("body > .egw_tooltip").forEach(t => t.remove());
	}

	function setStyle(elem, property, value)
	{
		elem.style[property] = typeof value === 'number' ? value + 'px' : value;
	}

	/**
	 * Shows the tooltip at the current cursor position.
	 */
	function show(node, event, options)
	{
		if (tooltip_div && typeof x !== 'undefined' && typeof y !== 'undefined')
		{
			options?.open?.call(node, event, tooltip_div);
			//set display to block, so we can get the width and height
			setStyle(tooltip_div, 'display', 'block');
			//Get the width and the height of the tooltip
			let tooltip_width = Math.ceil(tooltip_div.getBoundingClientRect().width);
			if (tooltip_width > 300)
			{
				tooltip_width = 300;
			}
			let tooltip_height = Math.ceil(tooltip_div.getBoundingClientRect().height);

			//Calculate the cursor_rectangle - this is a space the tooltip might
			//not overlap with
			const cursor_rect = {
				left: (x - 8),
				top: (y - 8),
				right: (x + (options.position === "center" ? -1 * tooltip_width / 2 : 8)),
				bottom: (y + 8)
			};

			//Calculate how much space is left on each side of the rectangle
			const window_width = _wnd.document.documentElement.clientWidth;
			const window_height = _wnd.document.documentElement.clientHeight;
			const space_left = {
				left: (cursor_rect.left),
				top: (cursor_rect.top),
				right: (window_width - cursor_rect.right),
				bottom: (window_height - cursor_rect.bottom)
			};


			if (space_left.right < tooltip_width)
			{
				setStyle(tooltip_div, 'left', Math.max(0, cursor_rect.left - tooltip_width))
			} else if (space_left.left >= tooltip_width)
			{
				setStyle(tooltip_div, 'left', cursor_rect.right)
			} else
			{
				setStyle(tooltip_div, 'left', cursor_rect.right)
				setStyle(tooltip_div, 'maxWidth', space_left.right)
			}

			// tooltip does fit neither above nor below: put him vertical centered left or right of cursor
			if (space_left.bottom < tooltip_height && space_left.top < tooltip_height)
			{
				if (tooltip_height > window_height - 20)
				{
					tooltip_height = window_height - 20;
					setStyle(tooltip_div, 'maxHeight', tooltip_height)
				}
				setStyle(tooltip_div, 'top', (window_height - tooltip_height) / 2)
			} else if (space_left.bottom < tooltip_height)
			{
				setStyle(tooltip_div, 'top', cursor_rect.top - tooltip_height)
			} else
			{
				setStyle(tooltip_div, 'top', cursor_rect.bottom)
			}

		}
	}

	/**
	 * Creates the tooltip_div with the given text.
	 *
	 * @param {string} _html
	 * @param {boolean} _isHtml if set to true content gets appended as html
	 * @param {{hideonhover: boolean, position: string, open: function, close: function}} _options options object
	 * open and close are functions which are called when the tooltip is shown or hidden, respectively.
	 * hideonhover is a boolean that determines if the tooltip should be hidden when the mouse enters it.
	 */
	function prepare(_html, _isHtml, _options)
	{
		// Free and null the old tooltip_div
		hide();

		//Generate the tooltip div, set it's text and append it to the body tag
		tooltip_div = _wnd.document.createElement('div');
		setStyle(tooltip_div, 'display', 'none')
		if (_isHtml)
		{
			if (_html instanceof Node)
			{
				tooltip_div.append(_html);
			} else
			{
				tooltip_div.insertAdjacentHTML('beforeend', _html);
			}
		} else
		{
			tooltip_div.textContent = _html;
		}
		tooltip_div.classList.add("egw_tooltip");
		_wnd.document.body.append(tooltip_div);

		//The tooltip should automatically hide when the mouse comes over it
		tooltip_div.addEventListener("mouseenter", () =>
		{
			if(hide_timeout)
			{
				_wnd.clearTimeout(hide_timeout);
				hide_timeout = null;
			}
			if (_options.hideonhover)
			{
				hide();
			}
		});
	}

	/**
	 * showTooltipTimeout is used to prepare showing the tooltip.
	 */
	function showTooltipTimeout(node, event, options)
	{
		if (current_elem === node)
		{
			show_delta += time_delta;
			if (show_delta < show_delay)
			{
				//Repeat the call of timeout
				_wnd.setTimeout(function () {showTooltipTimeout(this, event, options)}.bind(node), time_delta);
			} else
			{
				show_delta = 0;
				show(node, event, options);
			}
		}
	}

	function unbindEvents(elem)
	{
		elem.removeEventListener('mouseenter', mouseenter);
		elem.removeEventListener('mouseleave', mouseleave);
		elem.removeEventListener('mousemove', mousemove);
		tooltipData.delete(elem);

	}

	return {
		/**
		 * Binds a tooltip to the given DOM-Node with the given html.
		 * It is important to remove all tooltips from all elements which are
		 * no longer needed, in order to prevent memory leaks.
		 *
		 * @param _elem is the element to which the tooltip should get bound.
		 * @param _html is the html code which should be shown as tooltip.
		 * @param _isHtml if set to true content gets appended as html not text
		 * @param _options
		 */
		tooltipBind: function (_elem, _html, _isHtml, _options)
		{

			const options = {...optionsDefault, ...(_options || {})};
			const elem = getActualNode(_elem);
			tooltipped.add(elem);

			unbindEvents(elem);

			if (_html && !egwIsMobile())
			{
				tooltipData.set(elem, {html: _html, isHtml: _isHtml, options: options});

				elem.addEventListener('mouseenter', mouseenter);
				elem.addEventListener('mouseleave', mouseleave);
				elem.addEventListener('mousemove', mousemove);
			}
		},

		/**
		 * Unbinds the tooltip from the given DOM-Node.
		 *
		 * @param _elem is the element from which the tooltip should get
		 * removed.
		 */
		tooltipUnbind: function (_elem)
		{
			const elem = getActualNode(_elem);
			if (current_elem === elem)
			{
				hide();
				current_elem = null;
			}

			// Unbind all "tooltip" events from the given element
			unbindEvents(elem);
			tooltipped.delete(elem);
		},

		tooltipDestroy: function ()
		{
			hide()
			current_elem = null;
		},

		/**
		 * Hide tooltip, cancel the timer
		 */
		tooltipCancel: function ()
		{
			hide();
			current_elem = null;
		}
	};

});