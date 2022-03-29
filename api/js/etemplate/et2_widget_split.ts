/**
* EGroupware eTemplate2 - Split panel
*
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
* @package etemplate
* @subpackage api
* @link http://www.egroupware.org
* @author Nathan Gray
* @copyright Nathan Gray 2013
* @version $Id$
*/

/*egw:uses
/vendor/bower-asset/jquery/dist/jquery.js;
jquery.splitter;
    et2_core_baseWidget;
*/

import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
* A container widget that accepts 2 children, and puts a resize bar between them.
*
* This is the etemplate2 implementation of the traditional split pane / split panel.
* The split can be horizontal or vertical, and can be limited in size.  You can also
* turn on double-click docking to minimize one of the children.
*
* @see http://methvin.com/splitter/ Uses Splitter
* @augments et2_DOMWidget
*/
class et2_split extends et2_DOMWidget implements et2_IResizeable, et2_IPrint
{
	static readonly _attributes : any = {
		"orientation": {
			"name": "Orientation",
			"description": "Horizontal or vertical (v or h)",
			"default": "v",
			"type": "string"
		},
		"outline": {
			"name":	"Outline",
			"description": "Use a 'ghosted' copy of the splitbar and does not resize the panes until the mouse button is released.  Reduces flickering or unwanted re-layout during resize",
			"default": false,
			"type": "boolean"
		},
		"dock_side": {
			"name": "Dock",
			"description": "Allow the user to 'Dock' the splitbar to one side of the splitter, essentially hiding one pane and using the entire splitter area for the other pane.  One of leftDock, rightDock, topDock, bottomDock.",
			"default": et2_no_init,
			"type": "string"
		},
		"width": {
			"default": "100%"
		},
		// Not needed
		"overflow": {"ignore": true},
		"no_lang": {"ignore": true},
		"rows": {"ignore": true},
		"cols": {"ignore": true}
	};

	public static readonly DOCK_TOLERANCE : number = 15;

	div : JQuery = null;
	dynheight : et2_dynheight = null;
	left : JQuery;
	right : JQuery;
	loading : JQueryDeferred<void>;
	stop_resize : boolean;
	orientation : "v" | "h";
	dock_side : "leftDock" | "rightDock" | "topDock" | "bottomDock";
	prefSize : number;
	outline : boolean;

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_split._attributes, _child || {}));

		this.div = jQuery(document.createElement("div"))
			.addClass('et2_split');

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(
			(<et2_DOMWidget><unknown>this.getParent()).getDOMNode() || this.getInstanceManager().DOMContainer,
			this.div, 100
		);

		// Add something so we can see it - will be replaced if there's children
		this.left = jQuery("<div>Top / Left</div>").appendTo(this.div);
		this.right = jQuery("<div>Bottom / Right</div>").appendTo(this.div);

		// Deferred object so we can wait for children
		this.loading = jQuery.Deferred();

		// Flag to temporarily ignore resizing
		this.stop_resize = false;
	}

	destroy()
	{
		// Stop listening
		this.left.next().off("mouseup");

		// Destroy splitter, restore children
		this.div.trigger("destroy");

		// Destroy dynamic full-height
		this.dynheight.destroy();

		super.destroy();

		// Remove placeholder children
		if(this._children.length == 0)
		{
			this.div.empty();
		}
		this.div.remove();
	}

	/**
	 * Tap in here to check if we have real children, because all children should be created
	 * by this point.  If there are, replace the placeholders.
	 */
	loadFromXML(_node)
	{
		super.loadFromXML(_node);
		if(this._children.length > 0)
		{
			if(this._children[0])
			{
				this.left.detach();
				this.left = jQuery(this._children[0].getDOMNode(this._children[0]))
					.appendTo(this.div);
			}
			if(this._children[1])
			{
				this.right.detach();
				this.right = jQuery(this._children[1].getDOMNode(this._children[1]))
					.appendTo(this.div);
			}
		}

		// Nextmatches (and possibly other "full height" widgets) need to be adjusted
		// Trigger the dynamic height thing to re-initialize
		for(var i = 0; i < this._children.length; i++)
		{
			if(this._children[i].dynheight)
			{
				this._children[i].dynheight.outerNode = (i == 0 ? this.left : this.right);
				this._children[i].dynheight.initialized = false;
			}
		}
	}

	doLoadingFinished()
	{
		super.doLoadingFinished();

		// Use a timeout to give the children a chance to finish
		var self = this;
		window.setTimeout(function() {
			self._init_splitter();
		},1);

		// Not done yet, but widget will let you know
		return this.loading.promise();
	}

	/**
	 * Initialize the splitter UI
	 * Internal.
	 */
	private _init_splitter()
	{
		if(!this.isAttached()) return;

		// Avoid trying to do anything while hidden - it ruins all the calculations
		// Try again in resize()
		if(!this.div.is(':visible')) return;

		let options = {
			type:	this.orientation,
			dock:	this.dock_side,
			splitterClass: "et2_split",
			outline: true,
			eventNamespace: '.et2_split.'+this.id,

			// Default sizes, in case the preference doesn't work
			// Splitter would normally just go to 50%, but our deferred loading
			// ruins sizing for it
			sizeTop: this.dynheight.outerNode.height() / 2,
			sizeLeft: this.dynheight.outerNode.width() / 2
		};

		let widget = this;

		//Convert pixel size to percent
		let pix2per = function (_size)
		{
			let per;
			if (widget.orientation == "v")
			{
				per = _size * 100 / widget.dynheight.outerNode.width();
			}
			else
			{
				per = _size * 100 / widget.dynheight.outerNode.height();
			}
			return per.toFixed(2) + "%";
		};

		// Check for position preference, load it in
		if(this.id)
		{
			let pref = this.egw().preference('splitter-size-' + this.id, this.egw().getAppName());
			if(pref)
			{
				// Change from percent back to numeric
				if(typeof pref.sizeLeft !== "undefined")
				{
					pref.sizeLeft = ((parseFloat(pref.sizeLeft) / 100) * widget.dynheight.outerNode.width());
				}
				if(typeof pref.sizeTop !== "undefined")
				{
					pref.sizeTop = ((parseFloat(pref.sizeTop) / 100) * widget.dynheight.outerNode.height());
				}
				options = jQuery.extend(options, pref);
				this.prefSize = pref[this.orientation == "v" ? 'sizeLeft' : 'sizeTop'];
			}
			// If there is no preference yet, set it to half size
			// Otherwise the right pane gets the fullsize
			if (typeof this.prefSize == 'undefined' || !this.prefSize)
			{
				this.prefSize = this.orientation == "v" ? options.sizeLeft: options.sizeTop;
			}
		}

		// Avoid double init
		if(this.div.hasClass(options.splitterClass))
		{
			this.div.trigger("destroy");
		}

		// Initialize splitter
		this.div.splitter(options);

		this.div.trigger('resize',[options.sizeTop || options.sizeLeft || 0]);

		// Start docked?
		if(options.dock)
		{
			if(options.dock == "bottomDock" && Math.abs(options.sizeTop - this.div.height()) < et2_split.DOCK_TOLERANCE ||
				options.dock == "topDock" && options.sizeTop == 0 ||
				!this.div.is(':visible')  // Starting docked if hidden simplifies life when resizing
			)
			{
				this.dock();
			}
		}

		// Add icon to splitter bar
		let icon = "ui-icon-grip-"
			+ (this.dock_side ? "solid" : "dotted") + "-"
			+ (this.orientation == "h" ? "horizontal" : "vertical");

		jQuery(document.createElement("div"))
			.addClass("ui-icon")
			.addClass(icon)
			.appendTo(this.left.next());

		// Save preference when size changed
		if(this.id && this.egw().getAppName())
		{
			let self = this;
			this.left.on("resize"+options.eventNamespace, function(e) {

				// Force immediate layout, so proper layout & sizes are available
				// for resize().  Chrome defers layout, so current DOM node sizes
				// are not available to widgets if they ask.
				var display = this.style.display;
				this.style.display = 'none';
				this.offsetHeight;
				this.style.display = display;

				if(e.namespace == options.eventNamespace.substr(1) && !self.isDocked())
				{
					// Store current position in preferences
					var size = self.orientation == "v" ? {sizeLeft: self.left.width()} : {sizeTop: self.left.height()};
					self.prefSize = size[self.orientation == "v" ?'sizeLeft' : 'sizeTop'];
					var prefInPercent = self.orientation == "v" ?{sizeLeft:pix2per(size.sizeLeft)}:{sizeTop:pix2per(size.sizeTop)};
					if(parseInt(self.orientation == 'v' ? prefInPercent.sizeLeft : prefInPercent.sizeTop) < 100)
					{
						self.egw().set_preference(self.egw().getAppName(), 'splitter-size-' + self.id, prefInPercent);
					}
				}

				// Ok, update children
				self.iterateOver(function(widget) {
					// Extra resize would cause stalling chrome
					// as resize might confilict with bottom download bar
					// in chrome which does a window resize, so better to not
					// trigger second resize and leave that to an application
					// if it is neccessary.

					// Above forcing is not enough for Firefox, defer
					window.setTimeout(jQuery.proxy(function() {this.resize();}, widget),200);
				},self,et2_IResizeable);
			});
		}

		this.loading.resolve();
	}

	/**
	 * Implement the et2_IResizable interface to resize
	 */
	resize()
	{
		// Avoid doing anything while hidden - check here, and init if needed
		if(this.div.children().length <= 2)
		{
			this._init_splitter();
		}
		if(this.dynheight && !this.stop_resize )
		{
			let old = {w: this.div.width(), h: this.div.height()};
			this.dynheight.update(function(w,h) {
				if(this.orientation == "v")
				{
					this.left.height(h);
					this.right.height(h);
					if(this.left.width() + this.right.width() + this.div.find('.splitter-bar').outerWidth() < this.div.width() ||
						this.left.width() + this.right.width()- this.div.find('.splitter-bar').outerWidth() > this.div.width())
						this.div.trigger('resize.et2_split.'+this.id, this.prefSize);
				}
				if(this.orientation == "h")
				{
					this.left.width(w);
					this.right.width(w);
					if(this.isDocked()) {
						if(this.dock_side == "topDock")
						{
							this.right.height(h);
							this.left.height(0);
						}
						else
						{
							this.left.height(h);
							this.right.height(0);
						}
					}
				}
				if(w != old.w || h != old.h)
				{
					this.div.trigger('resize.et2_split.'+this.id, this.prefSize);
				}
			}, this);
		}
	}

	getDOMNode()
	{
		return this.div[0];
	}

	/**
	 * Set splitter orientation
	 *
	 * @param orient String "v" or "h"
	 */
	set_orientation(orient)
	{
		this.orientation = orient;

		this._init_splitter();
	}

	/**
	 * Set the side for docking
	 *
	 * @param dock String One of leftDock, rightDock, topDock, bottomDock
	 */
	set_dock_side(dock)
	{
		this.dock_side = dock;

		this._init_splitter();
	}

	/**
	 * Turn on or off resizing while dragging
	 *
	 * @param outline boolean
	 */
	set_outline(outline)
	{
		this.outline = outline;
		this._init_splitter();
	}

	/**
	 * If the splitter has a dock direction set, dock it.
	 * Docking requires the dock attribute to be set.
	 */
	dock()
	{
		if(this.isDocked()) return;
		this.div.trigger("dock");
	}

	/**
	 * If the splitter is docked, restore it to previous size
	 * Docking requires the dock attribute to be set.
	 */
	undock()
	{
		this.div.trigger("undock");
	}

	/**
	 * Determine if the splitter is docked
	 * @return boolean
	 */
	isDocked()
	{
		var bar = jQuery('.splitter-bar',this.div);
		return bar.hasClass('splitter-bar-horizontal-docked') || bar.hasClass('splitter-bar-vertical-docked');
	}

	/**
	 * Toggle the splitter's docked state.
	 * Docking requires the dock attribute to be set.
	 */
	toggleDock()
	{
		this.div.trigger("toggleDock");
	}

	// Printing
	/**
	 * Prepare for printing by stopping all the fuss
	 *
	 */
	beforePrint()
	{
		// Resizing causes preference changes & relayouts.  Don't do it.
		this.stop_resize = true;

		// Add the class, if needed
		this.div.addClass('print');

		// Don't return anything, just work normally
	}
	afterPrint()
	{
		this.div.removeClass('print');
		this.stop_resize = false;
	}
}
et2_register_widget(et2_split, ["split"]);

