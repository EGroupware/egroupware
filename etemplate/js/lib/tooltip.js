/**
 * eGroupware2 UI - Tooltip JS
 *
 * This javascript file contains the JavaScript code for the etemplate2 tooltip
 *
 * @link http://www.egroupware.org
 * @author Andreas Stoeckel (as@stylite.de)
 * @version $Id: tooltip.js 31497 2010-07-22 09:50:11Z igel457 $
 */

/* Tooltip handling */
function egw_tooltip()
{
	this.tooltip_div = null;
	this.current_elem = null;
	this.time_delta = 100;
	this.show_delta = 0;
	this.show_delay = 800;
	this.x = 0;
	this.y = 0;
}

egw_tooltip.prototype.bindToElement = function(_elem, _text)
{
	if (_text != '')
	{
		var self = this;
		_elem.bind('mouseenter.tooltip', function(e) {
			if (_elem != self.current_elem)
			{
				//Prepare the tooltip
				self._prepare(_text);

				self.current_elem = _elem;
				self.show_delta = 0;
				self.x = e.clientX;
				self.y = e.clientY;

				window.setTimeout(function() {self.showHintTimeout();}, self.time_delta);
			}

			return false;
		});

		_elem.bind('mouseleave.tooltip', function() {
			self.current_elem = null;
			self.show_delta = 0;
			//self.hide();
			if (self.tooltip_div)
			{
				self.tooltip_div.fadeOut(100);
			}
		});

		_elem.bind('mousemove.tooltip', function(e) {
			//Calculate the distance the mouse took since the last call of mousemove
			var dx = self.x - e.clientX;
			var dy = self.y - e.clientY;
			var movedist = Math.sqrt(dx * dx + dy * dy);

			//Block appereance of the tooltip on fast movements (with small movedistances)
			if (movedist > 2)
				self.show_delta = 0;

			self.x = e.clientX;
			self.y = e.clientY;
		});
	}
}

egw_tooltip.prototype.unbindFromElement = function(_elem)
{
	if (this.current_elem == _elem)
	{
		this.hide();
		this.current_elem = null;
	}

	_elem.unbind('mouseenter.tooltip');
	_elem.unbind('mouseleave.tooltip');
	_elem.unbind('mousemove.tooltip');
}

egw_tooltip.prototype.showHintTimeout = function()
{
	if (this.current_elem != null)
	{
		this.show_delta += this.time_delta;
		if (this.show_delta < this.show_delay)
		{
			//Repeat the call of timeout
			var self = this;
			window.setTimeout(function() {self.showHintTimeout();}, this.time_delta);
		}
		else
		{	
			this.show_delta = 0;
			this.show();
		}
	}
}

egw_tooltip.prototype._prepare = function(_text)
{
	var self = this;

	//Remove the old tooltip
	if (this.tooltip_div)
		this.hide();

	//Generate the tooltip div, set it's text and append it to the body tag
	this.tooltip_div = $j(document.createElement('div'));
	this.tooltip_div.hide();
	this.tooltip_div.append(_text);
	this.tooltip_div.addClass("egw_tooltip");
	$j('body').append(this.tooltip_div);

	//The tooltip should automatically hide when the mouse comes over it
	this.tooltip_div.mouseenter(function() {
			self.hide();
	});
}

egw_tooltip.prototype.show = function()
{
	if (this.tooltip_div)
	{
		//Calculate the cursor_rectangle - this is a space the tooltip might
		//not overlap with
		var cursor_rect = {
			left: (this.x - 8),
			top: (this.y - 8),
			right: (this.x + 8),
			bottom: (this.y + 8)
		};

		//Calculate how much space is left on each side of the rectangle
		var window_width = $j(document).width();
		var window_height = $j(document).height();
		var space_left = {
			left: (cursor_rect.left),
			top: (cursor_rect.top),
			right: (window_width - cursor_rect.right),
			bottom: (window_height - cursor_rect.bottom)
		};

		//Get the width and the height of the tooltip
		var tooltip_width = this.tooltip_div.width();
		if (tooltip_width > 300) tooltip_width = 300;
		var tooltip_height = this.tooltip_div.height();


		if (space_left.right < tooltip_width) {
			this.tooltip_div.css('left', cursor_rect.left - tooltip_width);
		} else if (space_left.left >= tooltip_width) {
			this.tooltip_div.css('left', cursor_rect.right);
		} else	{
			this.tooltip_div.css('left', cursor_rect.right);
			this.tooltip_div.css('max-width', space_left.right);
		}

		if (space_left.bottom < tooltip_height) {
			this.tooltip_div.css('top', cursor_rect.top - tooltip_height);
		} else if (space_left.top >= tooltip_height) {
			this.tooltip_div.css('top', cursor_rect.bottom);
		} else	{
			this.tooltip_div.css('top', cursor_rect.bottom);
			this.tooltip_div.css('max-height', space_left.bottom);
		}

		this.tooltip_div.fadeIn(100);
	}
}

egw_tooltip.prototype.hide = function()
{
	if (this.tooltip_div)
	{
		this.tooltip_div.remove();
		this.tooltip_div = null;
	}
}

//A reference to the current tooltip object
window.egw_global_tooltip = new egw_tooltip();

