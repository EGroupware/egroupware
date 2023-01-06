/**
 * EGroupware eTemplate2 - Splitter widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {cssImage, Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlSplitPanel} from "@shoelace-style/shoelace";
import {et2_IDOMNode, et2_IResizeable} from "../../et2_core_interfaces";
import {et2_DOMWidget} from "../../et2_core_DOMWidget";
import {css, html, SlotMixin} from "@lion/core";
import {colorsDefStyles} from "../../Styles/colorsDefStyles";

export class Et2Split extends Et2Widget(SlotMixin(SlSplitPanel))
{

	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			css`
			:host {
				height: 100%;
			}
			slot:not([name='handle'])::slotted(*) {
				height: 100%;
				width: 100%;
			}
			::slotted(.split-handle) {
				position: absolute;
				width: 20px;
				height: 20px;
				background-image: ${cssImage("splitter_vert")};
				background-position: center;
				background-repeat: no-repeat;
			}
			:host([vertical]) ::slotted(.split-handle) {
				background-image: ${cssImage("splitter_horz")};
			}
			.divider {
				background-color: var(--gray_10)
			}
			.divider:hover {
				filter: brightness(85%);
			}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * The current position of the divider from the primary panel's edge as a percentage 0-100.
			 * Defaults to 50% of the container's initial size
			 */
			position: Number,
			/**
			 * If no primary panel is designated, both panels will resize proportionally and docking is disabled
			 * "start" | "end" | undefined
			 */
			primary: String,

			/**
			 * Legacy orientation
			 * "v" | "h"
			 * @deprecated use vertical=true|false instead
			 */
			orientation: String
		}
	}

	get slots()
	{
		return {
			handle: () =>
			{
				return this._handleTemplate();
			}
		}
	}


	public static PREF_PREFIX = "splitter-size-";
	protected static PANEL_NAMES = ["start", "end"];
	private _resize_timeout : ReturnType<typeof setTimeout> = null;
	private _undock_position : number = undefined;

	// To hold troublesome elements we need to hide while resizing
	private _hidden : HTMLElement[] = [];

	constructor()
	{
		super();

		// Bind handlers to instance
		this._handleResize = this._handleResize.bind(this);
		this._handleMouseDown = this._handleMouseDown.bind(this);
		this._handleMouseUp = this._handleMouseUp.bind(this);
		this._handleDoubleClick = this._handleDoubleClick.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Add listeners
		this.addEventListener("sl-reposition", this._handleResize);

		// Wait for everything to complete,
		this.getUpdateComplete().then(() =>
		{
			// Divider node not available earlier
			this._dividerNode.addEventListener("mousedown", this._handleMouseDown);
			this._dividerNode.addEventListener("mouseup", this._handleMouseUp);
			this._dividerNode.addEventListener("dblclick", this._handleDoubleClick);

			// now tell legacy children to resize
			this.iterateOver((widget) =>
			{
				// Nextmatches (and possibly other "full height" widgets) need to be adjusted
				// Trigger the dynamic height thing to re-initialize
				// TODO: When dynheight goes away, this can too
				if(typeof widget.dynheight !== "undefined")
				{
					let outerNodetopOffset = widget.dynheight.outerNode.offset().top;
					widget.dynheight.outerNode = {
						// Random 3px deducted to make things fit better.  Otherwise nm edges are hidden
						width: () => parseInt(getComputedStyle(this.shadowRoot.querySelector(".start")).width) - 3,
						height: () => parseInt(getComputedStyle(this.shadowRoot.querySelector(".start")).height) - 3,
						offset: () => {return {top:outerNodetopOffset}}
					};
					widget.dynheight._collectBottomNodes = function()
					{
						this.bottomNodes = [];//widget.dynheight.bottomNodes.filter((node) => (node[0].parentNode != this));
					};
				}
				if(widget.resize)
				{
					widget.resize();
				}
			}, this, et2_DOMWidget);
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("sl-reposition", this._handleResize);
		this._dividerNode.removeEventListener("mousedown", this._handleMouseDown);
		this._dividerNode.removeEventListener("mouseup", this._handleMouseUp);
		this._dividerNode.removeEventListener("dblclick", this._handleDoubleClick);
	}

	/**
	 * Determine if the splitter is docked
	 * @return boolean
	 */
	isDocked()
	{
		// Docked if we have a primary set, and we're all the way to one side
		return (this.primary == "start" && this.position == 100) || (this.primary == "end" && this.position == 0);
	}

	/**
	 * Toggle docked or not
	 *
	 * @param {boolean} dock
	 */
	toggleDock(dock? : boolean)
	{
		// Need a primary panel designated so we know which one disappears
		if(typeof this.primary == "undefined")
		{
			return;
		}
		if(typeof dock == "undefined")
		{
			dock = !this.isDocked();
		}

		let undocked = (typeof this._undock_position == "undefined" || [0, 100].indexOf(this._undock_position) != -1) ? 50 : this._undock_position;
		this.position = dock ? (this.primary == 'start' ? 100 : 0) : undocked;
	}

	dock() { return this.toggleDock(true);}

	undock() { return this.toggleDock(false)}

	/**
	 * Set the orientation of the splitter
	 *
	 * "h" for the splitter bar to be horizontal (children are stacked vertically)
	 * "v" for the splitter bar to be vertical (children are side by side horizontally)
	 *
	 * @param {string} orientation
	 */
	set orientation(orientation)
	{
		this.vertical = orientation == "h";
		this.requestUpdate("vertical");
	}

	get orientation()
	{
		return this.vertical ? "h" : "v";
	}

	/**
	 * Load user's size preference
	 *
	 * @protected
	 */
	protected _loadPreference()
	{
		if(!this.id)
		{
			return;
		}

		let pref = this.egw().preference(Et2Split.PREF_PREFIX + this.id, this.egw().getAppName());
		if(pref)
		{
			// Doesn't matter if it's left or top or what, we just want the number
			this.position = parseInt(Object.values(pref)[0]);
			if(typeof this.position != "number" || isNaN(this.position))
			{
				this.position = 50;
			}
		}
		this._undock_position = this.position;
	}

	/**
	 * Save the current position to user preference
	 *
	 * @protected
	 */
	protected _savePreference()
	{
		if(!this.id || !this.egw() || !this.position)
		{
			return;
		}

		// Store current position in preferences
		let size = this.vertical ? {sizeTop: Math.round(this.position)} : {sizeLeft: Math.round(this.position)};
		this.egw().set_preference(this.egw().getAppName(), Et2Split.PREF_PREFIX + this.id, size);

		// make sure mouse up is handled when the mouse position has crossed the min/max points. The mouseup event does not
		// get called naturally in those situations.
		if (this.position <= parseInt(this.style.getPropertyValue('--min'))
			|| this.position >= parseInt(this.style.getPropertyValue('--max')))
		{
			this._handleMouseUp();
		}

	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);

		// if ID changes, check preference
		if(changedProperties.has("id") && this.id)
		{
			this._loadPreference();
		}
	}

	/**
	 * Override parent to avoid resizing when not visible, as that breaks size calculations
	 *
	 * @returns {any}
	 */
	handlePositionChange()
	{
		if(this.offsetParent !== null)
		{
			return super.handlePositionChange();
		}
	}

	/**
	 * Override parent to avoid resizing when not visible, as that breaks size calculations
	 */
	handleResize(entries)
	{
		if(this.offsetParent !== null)
		{
			return super.handleResize(entries);
		}
	}

	/**
	 * Handle a resize
	 * This includes notifying any manually resizing widgets, and updating preference if needed
	 *
	 *
	 * @param e
	 */
	_handleResize(e, timeout = 100)
	{
		// Update where we would undock to
		if(this.position != 0 && this.position != 100)
		{
			this._undock_position = this.position;
		}

		if(this._resize_timeout)
		{
			clearTimeout(this._resize_timeout);
		}
		this._resize_timeout = setTimeout(function()
		{
			this._resize_timeout = undefined;

			this._savePreference();

			// Tell widgets that manually resize about it
			this.iterateOver(function(_widget)
			{
				if(typeof _widget.resize === 'function')
				{
					_widget.resize();
				}
			}, self, et2_IResizeable);
		}.bind(this), timeout);
	}

	/**
	 * Handle doubleclick (on splitter bar) to dock
	 */
	_handleDoubleClick(e)
	{
		this.toggleDock();
	}

	/**
	 * Hide child iframes, they screw up sizing
	 * @param e
	 */
	_handleMouseDown(e)
	{
		const hidden = ['iframe'];
		for(let tag of hidden)
		{
			let hide = this.querySelectorAll(tag);
			this._hidden.push(...hide);
			for(let h of hide)
			{
				h.style.visibility = "hidden";
				this.egw().loading_prompt(this.id, true, this.egw().lang('Recalculating frame size...'), h.parentElement)
			}
		}

		// If they move quickly, the mouse can leave the divider and we won't get the mouseup
		// On firefox, this causes incorrect sizes
		const listener = (e) =>
		{
			this._handleMouseUp(e);
			this.getRootNode().removeEventListener("mouseup", listener);
		}
		this.getRootNode().addEventListener("mouseup", listener);
	}

	/**
	 * Show any hidden children
	 */
	_handleMouseUp(e)
	{
		for(let h of this._hidden)
		{
			h.style.visibility = "initial";
			this.egw().loading_prompt(this.id, false);
		}
		// Do resize a little later for fast draggers using firefox
		this._handleResize(e, 500);
	}

	/**
	 * HTML template for split handle
	 */
	_handleTemplate()
	{
		return html`
            <div class="split-handle"></div>`;
	}

	get _dividerNode() : HTMLElement
	{
		return this.shadowRoot.querySelector("[part='divider']");
	}

	/**
	 * Loads the widget tree from an XML node
	 * Overridden here to auto-assign slots if not set
	 *
	 * @param _node xml node
	 */
	loadFromXML(_node)
	{
		super.loadFromXML(_node);

		for(let i = 0; i < this.getChildren().length && i < Et2Split.PANEL_NAMES.length; i++)
		{
			let child = (<et2_IDOMNode>this.getChildren()[i]).getDOMNode();
			if(child && !child.getAttribute("slot"))
			{
				child.setAttribute("slot", Et2Split.PANEL_NAMES[i]);
			}
		}
	}
}

customElements.define("et2-split", Et2Split as any);