/**
 * EGroupware eTemplate2 - Countdown timer widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_baseWidget;
*/

import './et2_core_common';
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_date} from "./et2_widget_date";
import {et2_baseWidget} from "./et2_core_baseWidget";

/**
 * Class which implements the "countdown" XET-Tag
 */
export class et2_countdown extends et2_baseWidget {
	static readonly _attributes: any = {
		time: {
			name: "time",
			type: "any",
			default: "",
			description: ""
		},
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

	private time : et2_date;

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
		// create a date widget
		this.time = <et2_date> et2_createWidget('date-time', {});
		this.setDOMNode(this.container[0]);
	}

	public set_time(_time)
	{
		if (_time == "") return;
		this.time.set_value(_time);
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
		let tempDate = new Date();
		let now = new Date(
			tempDate.getFullYear(),
			tempDate.getMonth(),
			tempDate.getDate(),
			tempDate.getHours(),
			tempDate.getMinutes()-tempDate.getTimezoneOffset(),
			tempDate.getSeconds()
		);

		let time = new Date (this.time.getValue());
		let distance = time.getTime() - now.getTime();

		if (distance < 0) return 0;
		if (this.options.alarm > 0 && this.options.alarm == distance/1000 && typeof this.onAlarm == 'function')
		{
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
		return distance;
	}

	private _getIndicator(_v)
	{
		return this.options.format == 's' ? egw.lang(_v).substr(0,1) : egw.lang(_v);
	}
}
et2_register_widget(et2_countdown, ["countdown"]);
