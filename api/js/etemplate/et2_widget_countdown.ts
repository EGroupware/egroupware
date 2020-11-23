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
			if (self._updateTimer() <= 0) clearInterval(this.timer);
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

		this.days.text(Math.floor(distance / (1000 * 60 * 60 * 24))+this._getIndicator("days"));
		this.hours.text(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))+this._getIndicator("hours"));
		this.minutes.text(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))+this._getIndicator("minutes"));
		this.seconds.text(Math.floor((distance % (1000 * 60)) / 1000)+this._getIndicator("seconds"));

		return distance;
	}

	private _getIndicator(_v)
	{
		return this.options.format == 's' ? egw.lang(_v).substr(0,1) : egw.lang(_v);
	}
}
et2_register_widget(et2_countdown, ["countdown"]);
