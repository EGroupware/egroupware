/**
 * EGroupware eTemplate2 - Countdown timer widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_baseWidget;
*/

import {et2_no_init} from "./et2_core_common";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from "./et2_core_valueWidget";
import {egw} from "../jsapi/egw_global";

/**
 * Class which implements the "countdown" XET-Tag
 *
 * Value for countdown is an integer duration in seconds or a server-side to a duration converted expiry datetime.
 *
 * The duration has the benefit, that it does not depend on the correct set time and timezone of the browser / computer of the user.
 */
export class et2_countdown extends et2_valueWidget {
	static readonly _attributes: any = {
		format: {
			name: "display format",
			type: "string",
			default: "s", // s or l
			description: "Defines display format; s (Initial letter) or l (Complete word) display, default is s."
		},
		onFinish: {
			name: "on finish countdown",
			type: "js",
			default: et2_no_init,
			description: "Callback function to call when the countdown is finished."
		},
		hideEmpties: {
			name: "hide empties",
			type: "string",
			default: true,
			description: "Only displays none empty values."
		},
		precision: {
			name: "how many counters to show",
			type: "integer",
			default: 0,	// =all
			description: "Limit number of counters, eg. 2 does not show minutes and seconds, if days are displayed"
		},
		alarm: {
			name: "alarm",
			type: "any",
			default: "",
			description: "Defines an alarm set before the countdown is finished, it should be in seconds"
		},
		onAlarm: {
			name: "alarm callback",
			type: "js",
			default: "",
			description: "Defines a callback to gets called at alarm - timer. This only will work if there's an alarm set."
		}
	};

	private time : Date;

	private timer = null;
	private container : JQuery = null;
	private days : JQuery = null;
	private hours : JQuery = null;
	private minutes : JQuery = null;
	private seconds : JQuery = null;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object) {
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_countdown._attributes, _child || {}));

		// Build countdown dom container
		this.container = jQuery(document.createElement("div"))
			.addClass("et2_countdown");
		this.days = jQuery(document.createElement("span"))
			.addClass("et2_countdown_days").appendTo(this.container);
		this.hours = jQuery(document.createElement("span"))
			.addClass("et2_countdown_hours").appendTo(this.container);
		this.minutes = jQuery(document.createElement("span"))
			.addClass("et2_countdown_minutes").appendTo(this.container);
		this.seconds = jQuery(document.createElement("span"))
			.addClass("et2_countdown_seconds").appendTo(this.container);
		this.setDOMNode(this.container[0]);
	}

	public set_value(_time)
	{
		if (isNaN(_time)) return;

		super.set_value(_time);
		this.time = new Date();
		this.time.setSeconds(this.time.getSeconds() + parseInt(_time));

		let self = this;
		this.timer = setInterval(function(){
			if (self._updateTimer() <= 0)
			{
				clearInterval(self.timer);
				if (typeof self.onFinish == "function") self.onFinish();
			}
		}, 1000);
	}

	private _updateTimer()
	{
		let now = new Date();
		let distance = this.time.getTime() - now.getTime();

		if (distance < 0) return 0;

		let alarms = [];
		if (Array.isArray(this.options.alarm))
		{
			alarms = this.options.alarm;
		}
		else
		{
			alarms[this.options.alarm] = this.options.alarm;
		}

		// alarm values should be set as array index to reduce its time complexity from O(n) to O(1)
		// otherwise the execution time might be more than a second which would cause timer being delayed
		if (alarms[Math.floor(distance/1000)] && typeof this.onAlarm == 'function')
		{
			console.log('alarm is called')
			this.onAlarm();
		}

		let values = {
			days: Math.floor(distance / (1000 * 60 * 60 * 24)),
			hours: Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
			minutes: Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)),
			secounds: Math.floor((distance % (1000 * 60)) / 1000)
		};

		this.days.text(values.days+this._getIndicator("days"));
		this.hours.text(values.hours+this._getIndicator("hours"))
		this.minutes.text(values.minutes+this._getIndicator("minutes"));
		this.seconds.text(values.secounds+this._getIndicator("seconds"));

		if (this.options.hideEmpties)
		{
			if (values.days == 0)
			{
				this.days.hide();
				if(values.hours == 0)
				{
					this.hours.hide();
					if(values.minutes == 0)
					{
						this.minutes.hide();
						if(values.secounds == 0) this.seconds.hide();
					}
				}
			}
		}
		if (this.options.precision)
		{
			const units = ['days','hours','minutes','seconds'];
			for (let u=0; u < 4; ++u)
			{
				if (values[units[u]])
				{
					for(let n=u+this.options.precision; n < 4; n++)
					{
						this[units[n]].hide();
					}
					break;
				}
				else
				{
					this[units[u]].hide();
				}
			}
		}
		return distance;
	}

	private _getIndicator(_v)
	{
		return this.options.format == 's' ? egw.lang(_v).substr(0,1) : egw.lang(_v);
	}
}
et2_register_widget(et2_countdown, ["countdown"]);
