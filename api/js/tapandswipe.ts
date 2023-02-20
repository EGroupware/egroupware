/**
 * EGroupware TapAndSwipe helper library
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package Api
 * @subpackage ui
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn@egroupware.org>
 */
export interface TapAndSwipeOptions {
	// allow scrolling would stop swipe/tap events from being fired when there's scrolling available. It can be restricted
	// to Vertical/Horizental/Both scrolling. If no value set it means not allowed.
	allowScrolling? : string|null,
	// tolorated pixel to fire the swipe events
	threshold? : number,
	// time delay for defirentiate between tap event and long tap event, threshold is in milliseconds
	tapHoldThreshold? : number,
	// DOM node(s) that event handler has to be bound to, it can be a selector or acctual HTMLElement
	element? : string|HTMLElement,
	// callback function being called on swipe gestures
	swipe? : Function,
	// callback function being called on tap
	tap? : Function,
	// callback function being called on long tap(tap and hold)
	tapAndHold? : Function,
}

export type TapAndSwipeOptionsType = TapAndSwipeOptions;

export class tapAndSwipe {
	static readonly _default : TapAndSwipeOptionsType = {
		threshold : 10,
		tapHoldThreshold : 3000,
		allowScrolling : 'both',
		swipe : function(){},
		tap : function(){},
		tapAndHold: function(){}
	}
	/**
	 * Keeps the touch X start point
	 * @private
	 */
	private _startX : number = null;
	/**
	 * Keeps the touch Y start point
	 * @private
	 */
	private _startY : number = null;
	/**
	 * Keeps the touch X end point
	 * @private
	 */
	private _endX : number = null;
	/**
	 * Keeps the touch Y end point
	 * @private
	 */
	private _endY : number = null;
	/**
	 * keeps the distance travelled between start point and end point
	 * @private
	 */
	private _distance : number = null;
	/**
	 * flag to keep the status of type of tap
	 * @private
	 */
	private _isTapAndHold : boolean = false;
	/**
	 * keeps the timeout id for taphold
	 */
	private _tapHoldTimeout : number = null;

	/**
	 * keeps the contact point on touch start
	 * @private
	 */
	private _fingercount : number = null;

	private _scrolledElementObj : {el : HTMLElement, scrollTop : number, scrollLeft : number} = null;

	private _hasBeenScrolled : boolean = false;
	/**
	 * Options
	 * @protected
	 */
	protected options : TapAndSwipeOptionsType = null;

	/**
	 * Keeps the html node
	 */
	public element : HTMLElement  = null;

	/**
	 * Constructor
	 * @param _element
	 * @param _options
	 */
	public constructor(_element : string | HTMLElement, _options? : TapAndSwipeOptionsType)
	{
		this.options = {...tapAndSwipe._default, ..._options};
		const element = _element||_options.element;
		// Dont construct if the element is not there
		if (!element || !(typeof element != 'string' && element instanceof EventTarget)) return;

		this.element = (element instanceof EventTarget) ? element : document.querySelector(element);
		this.element.addEventListener('touchstart', this._onTouchStart.bind(this), false);
		this.element.addEventListener('touchend', this._ontouchEnd.bind(this), false);
		this.element.addEventListener('touchmove', this._onTouchMove.bind(this), false);
	}

	_onTouchMove(event)
	{

	}
	/**
	 * on touch start event handler
	 * @param event
	 * @private
	 */
	private _onTouchStart(event : TouchEvent)
	{
		this._startX = event.changedTouches[0].screenX;
		this._startY = event.changedTouches[0].screenY;
		this._isTapAndHold = false;
		this._fingercount = event.touches.length;

		if(event.composedPath())
		{
			const scrolledItem = event.composedPath().filter(_item => {
				return _item instanceof HTMLElement && this.element.contains(_item) && (_item.scrollTop != 0 || _item.scrollLeft !=0);
			});
			if (scrolledItem.length>0)
			{
				this._scrolledElementObj = {el: scrolledItem[0], scrollTop: scrolledItem[0].scrollTop, scrollLeft: scrolledItem[0].scrollLeft};
			}
			else
			{
				this._scrolledElementObj = null;
			}
		}

		this._tapHoldTimeout = window.setTimeout(_=>{
			this._isTapAndHold = true;
			//check scrolling
			if (this.options.allowScrolling && this._hasBeenScrolled)
			{
				return;
			}

			this.options.tapAndHold.call(this, event, this._fingercount);
		}, this.options.tapHoldThreshold);
	}

	/**
	 * On touch end event handler
	 * @param event
	 * @private
	 */
	private _ontouchEnd(event : TouchEvent)
	{
		this._endX = event.changedTouches[0].screenX;
		this._endY = event.changedTouches[0].screenY;

		if (this._scrolledElementObj) {
			switch (this.options.allowScrolling)
			{
				case "vertical":
					this._hasBeenScrolled = this._scrolledElementObj.el.scrollTop != this._scrolledElementObj.scrollTop;
					break;
				case "horizental":
					this._hasBeenScrolled = this._scrolledElementObj.el.scrollLeft != this._scrolledElementObj.scrollLeft;
					break;
				case "both":
					this._hasBeenScrolled = (this._scrolledElementObj.el.scrollTop != this._scrolledElementObj.scrollTop) ||
						(this._scrolledElementObj.el.scrollLeft != this._scrolledElementObj.scrollLeft);
					break;
				default:
					this._hasBeenScrolled = false;
			}
		}
		else
		{
			this._hasBeenScrolled = false;
		}

		this._handler(event);
	}

	/**
	 * Handles the type of gesture and calls the right callback for it
	 * @param event
	 * @private
	 */
	private _handler(event : TouchEvent)
	{
		//cleanup tapHoldTimeout
		window.clearTimeout(this._tapHoldTimeout);

		//check scrolling
		if (this.options.allowScrolling && this._hasBeenScrolled)
		{
			// let the scrolling happens
			return;
		}

		// Tap & TapAndHold handler
		if (this._endX == this._startX && this._endY == this._startY
			|| (Math.sqrt((this._endX-this._startX)*(this._endX-this._startX) + (this._endY-this._startY)+(this._endY-this._startY))
				< this.options.threshold))
		{
			if (!this._isTapAndHold)
			{
				this.options.tap.call(this,event, this._fingercount);
			}

			return;
		}

		// left swipe handler
		if (this._endX + this.options.threshold < this._startX) {
			this._distance = this._startX - this._endX;
			this.options.swipe.call(this, event, 'left', this._distance, this._fingercount);
			return;
		}

		// right swipe handler
		if (this._endX - this.options.threshold > this._startX) {
			this._distance = this._endX - this._startX;
			this.options.swipe.call(this, event, 'right', this._distance, this._fingercount);
			return;
		}

		// up swipe handler
		if (this._endY + this.options.threshold < this._startY) {
			this._distance = this._startY - this._endY;
			this.options.swipe.call(this, event, 'up', this._distance, this._fingercount);
			return;
		}

		// down swipe handler
		if (this._endY - this.options.threshold > this._startY) {
			this._distance = this._endY - this._startY;
			this.options.swipe.call(this, event, 'down', this._distance, this._fingercount);
			return;
		}
	}

	/**
	 * destroy the event listeners
	 */
	public destroy()
	{
		this.element.removeEventListener('touchstart', this._onTouchStart);
		this.element.removeEventListener('touchend', this._ontouchEnd);
	}
}