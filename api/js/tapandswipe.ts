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
	// tolorated pixel to fire the swipe events
	threshold? : number,
	// time delay for defirentiate between tap event and long tap event, threshold is in milliseconds
	tapHoldThreshold? : number,
	// callback function being called on swipe gestures
	swipe? : Function,
	// callback function being called on tap
	tap? : Function,
	// callback function being called on long tap(tap and hold)
	tapAndHold? : Function
}

export type TapAndSwipeOptionsType = TapAndSwipeOptions;

export class tapAndSwipe {
	static readonly _default : TapAndSwipeOptionsType = {
		threshold : 10,
		tapHoldThreshold : 3000,
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

	/**
	 * Options
	 * @protected
	 */
	protected options : TapAndSwipeOptionsType = null;
	/**
	 * Keeps the html node
	 */
	private _element : HTMLElement  = null;

	/**
	 * Constructor
	 * @param _element
	 * @param _options
	 */
	public constructor(_element : string | HTMLElement, _options? : TapAndSwipeOptionsType)
	{
		this.options = {...tapAndSwipe._default, ..._options};
		this._element = (_element instanceof EventTarget) ? _element : document.querySelector(_element);
		this._element.addEventListener('touchstart', this._onTouchStart.bind(this), false);
		this._element.addEventListener('touchend', this._ontouchEnd.bind(this), false);
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

		this._tapHoldTimeout = window.setTimeout(_=>{
			this._isTapAndHold = true;
			this.options.tapAndHold(event, this._fingercount);
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

		// Tap & TapAndHold handler
		if (this._endX == this._startX && this._endY == this._startY
			|| (Math.sqrt((this._endX-this._startX)*(this._endX-this._startX) + (this._endY-this._startY)+(this._endY-this._startY))
				< this.options.threshold))
		{
			if (!this._isTapAndHold)
			{
				this.options.tap(event, this._fingercount);
			}

			return;
		}

		// left swipe handler
		if (this._endX + this.options.threshold < this._startX) {
			this._distance = this._startX - this._endX;
			this.options.swipe(event, 'left', this._distance, this._fingercount);
			return;
		}

		// right swipe handler
		if (this._endX - this.options.threshold > this._startX) {
			this._distance = this._endX - this._startX;
			this.options.swipe(event, 'right', this._distance, this._fingercount);
			return;
		}

		// up swipe handler
		if (this._endY + this.options.threshold < this._startY) {
			this._distance = this._startY - this._endY;
			this.options.swipe(event, 'up', this._distance, this._fingercount);
			return;
		}

		// down swipe handler
		if (this._endY - this.options.threshold > this._startY) {
			this._distance = this._endY - this._startY;
			this.options.swipe(event, 'down', this._distance, this._fingercount);
			return;
		}
	}

	/**
	 * destroy the event listeners
	 */
	public destroy()
	{
		this._element.removeEventListener('touchstart', this._onTouchStart);
		this._element.removeEventListener('touchend', this._ontouchEnd);
	}
}