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

import './egw_core';

export interface TooltipOptions
{
	hideonhover? : boolean;
	position? : string;
	open? : (this : Node, event : Event, tooltip_div : HTMLElement) => any;
	close? : (this : Node, event : Event, tooltip_div : HTMLElement) => any;
}

export interface TooltipModule
{
	/**
	 * Binds a tooltip to the given DOM-Node with the given html.
	 * It is important to remove all tooltips from all elements which are
	 * no longer needed, in order to prevent memory leaks.
	 *
	 * @param _elem is the element to which the tooltip should get bound.
	 * @param _html is the html or text code which should be shown as tooltip.
	 * @param _isHtml if set to true content gets appended as html not text
	 * @param _options options object. open and close are functions which are
	 * called when the tooltip is shown or hidden, respectively. hideonhover
	 * is a boolean that determines if the tooltip should be hidden when the
	 * mouse enters it.
	 */
	tooltipBind(_elem : HTMLElement | JQuery, _html : string | Node, _isHtml? : boolean, _options? : TooltipOptions) : void;

	/**
	 * Unbinds the tooltip from the given DOM-Node.
	 *
	 * @param _elem is the element from which the tooltip should get
	 * removed.
	 */
	tooltipUnbind(_elem : HTMLElement | JQuery) : void;

	/**
	 * Hide any currently shown tooltip and remove it from the DOM
	 */
	tooltipDestroy() : void;

	/**
	 * Hide tooltip, cancel the timer
	 */
	tooltipCancel() : void;
}

declare global
{
	interface IegwWndLocal extends TooltipModule
	{
	}
}

class Tooltip implements TooltipModule
{
	#tooltipped = new Set<Node>();
	#tooltipData = new WeakMap<Node, {html : string | Node, isHtml : boolean, options : Required<TooltipOptions>}>();
	#tooltip_div : HTMLElement = null;
	#current_elem : Node = null;
	#hide_timeout : number = null;

	#time_delta = 100;
	#show_delta = 0;
	#show_delay = 200;

	#x = 0;
	#y = 0;

	#optionsDefault : Required<TooltipOptions> = {
		hideonhover: true,
		position: 'right',
		open: function () {},
		close: function () {}
	};

	constructor(private _wnd : Window)
	{
		_wnd.addEventListener("pagehide", () =>
		{
			[...this.#tooltipped].forEach(node =>
			{
				egw.tooltipUnbind(<HTMLElement>node);
			});
			this.#tooltipped.clear();

			if (this.#tooltip_div)
			{
				this.removeDiv();
			}
			return null;
		});
	}

	private removeDiv()
	{
		if (!this.#tooltip_div)
		{
			return;
		}
		this.#tooltip_div.remove();
		this.#tooltip_div = null;
	}

	// Bound to real DOM events (addEventListener/removeEventListener need a
	// stable reference to unregister later) - arrow fields give each
	// instance exactly one such stable reference, same as the closures they
	// replace. Doesn't touch `this` for anything besides instance state, so
	// no dynamic-this concern here (unlike mouseleave below).
	#mouseenter = (e : MouseEvent) =>
	{
		const elem = <Node>e.currentTarget
		const data = this.#tooltipData.get(elem);
		if (!data)
		{
			return false;
		}
		if (elem !== this.#current_elem)
		{
			//Prepare the tooltip
			this.prepare(data.html, data.isHtml, data.options);

			// Set the current element the mouse is over and
			// initialize the position variables
			this.#current_elem = elem;
			this.#show_delta = 0;
			this.#x = e.clientX;
			this.#y = e.clientY;
			// Create the timeout for showing the timeout
			this._wnd.setTimeout(() => {this.showTooltipTimeout(elem, e, data.options)}, this.#time_delta);
		}

		return false;
	}

	// Unlike mouseenter/mousemove, the body here relies on `this` being the
	// DOM element the listener fired on (browser-provided, standard
	// addEventListener semantics) - both directly (`current_elem == this`)
	// and via `data.options.close.call(this, ...)`. An arrow field would
	// permanently lex-bind `this` to the Tooltip instance instead, breaking
	// both. Stays a plain `function` (still a single stable reference, since
	// it's assigned once as a field initializer); `self` reaches this
	// Tooltip instance's state from inside it.
	#mouseleave = ((self : Tooltip) => function(this : Node, e : MouseEvent)
	{
		const elem = <Node>e.currentTarget
		const data = self.#tooltipData.get(elem);
		self.#show_delta = 0;

		if (self.#tooltip_div && e.relatedTarget && self.#tooltip_div.contains(<Node>e.relatedTarget))
		{
			return;
		}

		if (self.#hide_timeout)
		{
			self._wnd.clearTimeout(self.#hide_timeout);
		}
		self.#hide_timeout = self._wnd.setTimeout(() =>
		{
			if (self.#current_elem == this)
			{
				self.#current_elem = null;
			}
			if (data.options.close.call(this, e, self.#tooltip_div))
			{
				return;
			}
			if (self.#tooltip_div)
			{
				self.setStyle(self.#tooltip_div, 'display', 'none')
			}
		}, 150);
	})(this);

	#mousemove = (e : MouseEvent) =>
	{
		//Calculate the distance the mouse took since the last call of mousemove
		const dx = this.#x - e.clientX;
		const dy = this.#y - e.clientY;
		const movedist = Math.sqrt(dx * dx + dy * dy);

		//Block appereance of the tooltip on fast movements (with small movedistances)
		if (movedist > 2)
		{
			this.#show_delta = 0;
		}

		this.#x = e.clientX;
		this.#y = e.clientY;
	}

	/**
	 * We might not get an actual DOMNode, but we want to work on the actual DOMNodes not e.g. a jquery Object
	 */
	private getActualNode(_elem : HTMLElement | JQuery) : Node
	{
		return _elem instanceof Node ? _elem : (typeof (<any>_elem).get === "function" ? (<any>_elem).get(0) : _elem);
	}

	/**
	 * Removes the tooltip_div from the DOM if it does exist.
	 */
	private hide()
	{
		if (this.#tooltip_div)
		{
			this.removeDiv();
		}
		//there should only be the one we removed, but just in case, remove all tooltips
		this._wnd.document.querySelectorAll("body > .egw_tooltip").forEach(t => t.remove());
	}

	private setStyle(elem : HTMLElement, property : string, value : string | number)
	{
		elem.style[property] = typeof value === 'number' ? value + 'px' : value;
	}

	/**
	 * Shows the tooltip at the current cursor position.
	 */
	private show(node : Node, event : MouseEvent, options : Required<TooltipOptions>)
	{
		if (this.#tooltip_div && typeof this.#x !== 'undefined' && typeof this.#y !== 'undefined')
		{
			options?.open?.call(<any>node, event, this.#tooltip_div);
			//set display to block, so we can get the width and height
			this.setStyle(this.#tooltip_div, 'display', 'block');
			//Get the width and the height of the tooltip
			let tooltip_width = Math.ceil(this.#tooltip_div.getBoundingClientRect().width);
			if (tooltip_width > 300)
			{
				tooltip_width = 300;
			}
			let tooltip_height = Math.ceil(this.#tooltip_div.getBoundingClientRect().height);

			//Calculate the cursor_rectangle - this is a space the tooltip might
			//not overlap with
			const cursor_rect = {
				left: (this.#x - 8),
				top: (this.#y - 8),
				right: (this.#x + (options.position === "center" ? -1 * tooltip_width / 2 : 8)),
				bottom: (this.#y + 8)
			};

			//Calculate how much space is left on each side of the rectangle
			const window_width = this._wnd.document.documentElement.clientWidth;
			const window_height = this._wnd.document.documentElement.clientHeight;
			const space_left = {
				left: (cursor_rect.left),
				top: (cursor_rect.top),
				right: (window_width - cursor_rect.right),
				bottom: (window_height - cursor_rect.bottom)
			};

			if (space_left.right < tooltip_width)
			{
				this.setStyle(this.#tooltip_div, 'left', Math.max(0, cursor_rect.left - tooltip_width))
			} else if (space_left.left >= tooltip_width)
			{
				this.setStyle(this.#tooltip_div, 'left', cursor_rect.right)
			} else
			{
				this.setStyle(this.#tooltip_div, 'left', cursor_rect.right)
				this.setStyle(this.#tooltip_div, 'maxWidth', space_left.right)
			}

			// tooltip does fit neither above nor below: put him vertical centered left or right of cursor
			if (space_left.bottom < tooltip_height && space_left.top < tooltip_height)
			{
				if (tooltip_height > window_height - 20)
				{
					tooltip_height = window_height - 20;
					this.setStyle(this.#tooltip_div, 'maxHeight', tooltip_height)
				}
				this.setStyle(this.#tooltip_div, 'top', (window_height - tooltip_height) / 2)
			} else if (space_left.bottom < tooltip_height)
			{
				this.setStyle(this.#tooltip_div, 'top', cursor_rect.top - tooltip_height)
			} else
			{
				this.setStyle(this.#tooltip_div, 'top', cursor_rect.bottom)
			}

		}
	}

	/**
	 * Creates the tooltip_div with the given text.
	 *
	 * @param _html
	 * @param _isHtml if set to true content gets appended as html
	 * @param _options options object
	 * open and close are functions which are called when the tooltip is shown or hidden, respectively.
	 * hideonhover is a boolean that determines if the tooltip should be hidden when the mouse enters it.
	 */
	private prepare(_html : string | Node, _isHtml : boolean, _options : Required<TooltipOptions>)
	{
		// Free and null the old tooltip_div
		this.hide();

		//Generate the tooltip div, set it's text and append it to the body tag
		this.#tooltip_div = this._wnd.document.createElement('div');
		this.setStyle(this.#tooltip_div, 'display', 'none')
		if (_isHtml)
		{
			if (_html instanceof Node)
			{
				this.#tooltip_div.append(_html);
			} else
			{
				this.#tooltip_div.insertAdjacentHTML('beforeend', _html);
			}
		} else
		{
			this.#tooltip_div.textContent = <string>_html;
		}
		this.#tooltip_div.classList.add("egw_tooltip");
		this._wnd.document.body.append(this.#tooltip_div);

		//The tooltip should automatically hide when the mouse comes over it
		this.#tooltip_div.addEventListener("mouseenter", () =>
		{
			if(this.#hide_timeout)
			{
				this._wnd.clearTimeout(this.#hide_timeout);
				this.#hide_timeout = null;
			}
			if (_options.hideonhover)
			{
				this.hide();
			}
		});
	}

	/**
	 * showTooltipTimeout is used to prepare showing the tooltip.
	 *
	 * The original re-scheduled itself via a `this`/`.bind(node)` dance
	 * that's fully equivalent to just closing over `node` directly (it's
	 * already a parameter of this same function) - simplified accordingly,
	 * same observable recursive-call behavior.
	 */
	private showTooltipTimeout(node : Node, event : MouseEvent, options : Required<TooltipOptions>)
	{
		if (this.#current_elem === node)
		{
			this.#show_delta += this.#time_delta;
			if (this.#show_delta < this.#show_delay)
			{
				//Repeat the call of timeout
				this._wnd.setTimeout(() => this.showTooltipTimeout(node, event, options), this.#time_delta);
			} else
			{
				this.#show_delta = 0;
				this.show(node, event, options);
			}
		}
	}

	private unbindEvents(elem : any)
	{
		elem.removeEventListener('mouseenter', this.#mouseenter);
		elem.removeEventListener('mouseleave', this.#mouseleave);
		elem.removeEventListener('mousemove', this.#mousemove);
		this.#tooltipData.delete(elem);
	}

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
	tooltipBind = (_elem : HTMLElement | JQuery, _html : string | Node, _isHtml? : boolean, _options? : TooltipOptions) : void =>
	{
		const options = {...this.#optionsDefault, ...(_options || {})};
		const elem = this.getActualNode(_elem);
		this.#tooltipped.add(elem);

		this.unbindEvents(elem);

		if (_html && !egwIsMobile())
		{
			this.#tooltipData.set(elem, {html: _html, isHtml: _isHtml, options: options});

			(<any>elem).addEventListener('mouseenter', this.#mouseenter);
			(<any>elem).addEventListener('mouseleave', this.#mouseleave);
			(<any>elem).addEventListener('mousemove', this.#mousemove);
		}
	}

	/**
	 * Unbinds the tooltip from the given DOM-Node.
	 *
	 * @param _elem is the element from which the tooltip should get
	 * removed.
	 */
	tooltipUnbind = (_elem : HTMLElement | JQuery) : void =>
	{
		const elem = this.getActualNode(_elem);
		if (this.#current_elem === elem)
		{
			this.hide();
			this.#current_elem = null;
		}

		// Unbind all "tooltip" events from the given element
		this.unbindEvents(elem);
		this.#tooltipped.delete(elem);
	}

	tooltipDestroy = () : void =>
	{
		this.hide()
		this.#current_elem = null;
	}

	/**
	 * Hide tooltip, cancel the timer
	 */
	tooltipCancel = () : void =>
	{
		this.hide();
		this.#current_elem = null;
	}
}

egw.extend('tooltip', egw.MODULE_WND_LOCAL, (_app : string, _wnd : Window) => new Tooltip(_wnd));
