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

"use strict";

/*egw:uses
        jquery.jquery;
	jquery.splitter;
        et2_core_baseWidget;
*/

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
var et2_split = et2_DOMWidget.extend([et2_IResizeable], 
{
	attributes: {
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
	},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_split
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.div = $j(document.createElement("div"))
			.addClass('et2_split');

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(
			this.getParent().getDOMNode() || this.getInstanceManager().DOMContainer,
			this.div, 100
		);

		// Add something so we can see it - will be replaced if there's children
		this.left = $j("<div>Top / Left</div>").appendTo(this.div);
		this.right = $j("<div>Bottom / Right</div>").appendTo(this.div);

		// Deferred object so we can wait for children
		this.loading = jQuery.Deferred();
	},

	destroy: function() {
		// Stop listening
		this.left.next().off("mouseup");
		
		// Destroy splitter, restore children
		this.div.trigger("destroy");

		// Destroy dynamic full-height
		this.dynheight.free();

		// Remove placeholder children
		if(this._children.length == 0)
		{
			this.div.empty();
		}
		this.div.remove();
	},

	/**
	 * Tap in here to check if we have real children, because all children should be created
	 * by this point.  If there are, replace the placeholders.
	 */
	loadFromXML: function() {
		this._super.apply(this, arguments);
		if(this._children.length > 0)
		{
			if(this._children[0])
			{
				this.left.detach();
				this.left = $j(this._children[0].getDOMNode(this._children[0]))
					.appendTo(this.div);
			}
			if(this._children[1])
			{
				this.right.detach();
				this.right = $j(this._children[1].getDOMNode(this._children[1]))
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
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Use a timeout to give the children a chance to finish
		var self = this;
		window.setTimeout(function() {
			self._init_splitter();
		},1);
		

		// Not done yet, but widget will let you know
		return this.loading.promise();
	},

	/**
	 * Initialize the splitter UI
	 * Internal.
	 */
	_init_splitter: function() {
		if(!this.isAttached()) return;
		var options = {
			type:	this.orientation,
			dock:	this.dock_side,
			splitterClass: "et2_split",
			outline: true,
			eventNamespace: '.et2_split.'+this.id
		};
		
		// Check for position preference, load it in
		if(this.id)
		{
			var pref = this.egw().preference('splitter-size-' + this.id, this.egw().getAppName());
			if(pref)
			{
				options = $j.extend(options, pref);
			}
		}

		// Avoid double init
		if(this.div.hasClass(options.splitterClass))
		{
			this.div.trigger("destroy");
		}

		// Initialize splitter
		this.div.splitter(options);

		// Start docked?
		if(options.dock)
		{
			if(options.dock == "bottomDock" && options.sizeTop >= this.div.height() ||
				options.dock == "topDock" && options.sizeTop == 0)
			{
				this.dock();
			}
		}
		
		// Add icon to splitter bar
		var icon = "ui-icon-grip-"
			+ (this.dock_side ? "solid" : "dotted") + "-" 
			+ (this.orientation == "h" ? "horizontal" : "vertical");

		$j(document.createElement("div"))
			.addClass("ui-icon")
			.addClass(icon)
			.appendTo(this.left.next());

		// Save preference when size changed
		if(this.id && this.egw().getAppName())
		{
			self = this;
			this.left.on("resize"+options.eventNamespace, function(e) {
				if(e.namespace == options.eventNamespace.substr(1) && !self.isDocked())
				{
					// Store current position in preferences
					var size = self.orientation == "v" ? {sizeLeft: self.left.width()} : {sizeTop: self.left.height()};
					self.egw().set_preference(self.egw().getAppName(), 'splitter-size-' + self.id, size);
				}
				self.iterateOver(function(widget) {
					if(widget != self) widget.resize();
				},self,et2_IResizeable);
			});
		}

		this.loading.resolve();
	},

	/**
	 * Implement the et2_IResizable interface to resize
	 */
	resize: function() {
		if(this.dynheight)
		{
			var old = {w: this.div.width(), h: this.div.height()};
			this.dynheight.update(function(w,h) {
				if(this.orientation == "v")
				{
					this.left.height(h);
					this.right.height(h);
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
					this.div.trigger('resize.et2_split.'+this.id);
				}
			}, this);
		}
	},

	getDOMNode: function() {
		return this.div[0];
	},

	/**
	 * Set splitter orientation
	 *
	 * @param orient String "v" or "h"
	 */
	set_orientation: function(orient) {
		this.orientation = orient;
		
		this._init_splitter();
	},

	/**
	 * Set the side for docking
	 *
	 * @param dock String One of leftDock, rightDock, topDock, bottomDock
	 */
	set_dock_side: function(dock) {
		this.dock_side = dock;

		this._init_splitter();
	},

	/**
	 * Turn on or off resizing while dragging
	 *
	 * @param outline boolean
	 */
	set_outline: function(outline) {
		this.outline = outline;
		this._init_splitter();
	},

	/**
	 * If the splitter has a dock direction set, dock it.
	 * Docking requires the dock attribute to be set.
	 */
	dock: function() {
		if(this.isDocked()) return;
		this.div.trigger("dock");
	},

	/**
	 * If the splitter is docked, restore it to previous size
	 * Docking requires the dock attribute to be set.
	 */
	undock: function() {
		this.div.trigger("undock");
	},

	/**
	 * Determine if the splitter is docked
	 * @return boolean
	 */
	isDocked: function() {
		var bar = $j('.splitter-bar',this.div);
		return bar.hasClass('splitter-bar-horizontal-docked') || bar.hasClass('splitter-bar-vertical-docked');
	},

	/**
	 * Toggle the splitter's docked state.
	 * Docking requires the dock attribute to be set.
	 */
	toggleDock: function() {
		this.div.trigger("toggleDock");
	}
});

et2_register_widget(et2_split, ["split"]);
