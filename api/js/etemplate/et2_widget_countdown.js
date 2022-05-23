"use strict";
/**
 * EGroupware eTemplate2 - Countdown timer widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
exports.et2_countdown = void 0;
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_baseWidget;
*/
require("./et2_core_common");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
/**
 * Class which implements the "countdown" XET-Tag
 *
 * Value for countdown is an integer duration in seconds or a server-side to a duration converted expiry datetime.
 *
 * The duration has the benefit, that it does not depend on the correct set time and timezone of the browser / computer of the user.
 */
var et2_countdown = /** @class */ (function (_super) {
    __extends(et2_countdown, _super);
    /**
     * Constructor
     */
    function et2_countdown(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_countdown._attributes, _child || {})) || this;
        _this.timer = null;
        _this.container = null;
        _this.days = null;
        _this.hours = null;
        _this.minutes = null;
        _this.seconds = null;
        // Build countdown dom container
        _this.container = jQuery(document.createElement("div"))
            .addClass("et2_countdown");
        _this.days = jQuery(document.createElement("span"))
            .addClass("et2_countdown_days").appendTo(_this.container);
        _this.hours = jQuery(document.createElement("span"))
            .addClass("et2_countdown_hours").appendTo(_this.container);
        _this.minutes = jQuery(document.createElement("span"))
            .addClass("et2_countdown_minutes").appendTo(_this.container);
        _this.seconds = jQuery(document.createElement("span"))
            .addClass("et2_countdown_seconds").appendTo(_this.container);
        _this.setDOMNode(_this.container[0]);
        return _this;
    }
    et2_countdown.prototype.set_value = function (_time) {
        if (isNaN(_time))
            return;
        this.time = new Date();
        this.time.setSeconds(this.time.getSeconds() + parseInt(_time));
        var self = this;
        this.timer = setInterval(function () {
            if (self._updateTimer() <= 0) {
                clearInterval(self.timer);
                if (typeof self.onFinish == "function")
                    self.onFinish();
            }
        }, 1000);
    };
    et2_countdown.prototype._updateTimer = function () {
        var now = new Date();
        var distance = this.time.getTime() - now.getTime();
        if (distance < 0)
            return 0;
        var alarms = [];
        if (Array.isArray(this.options.alarm)) {
            alarms = this.options.alarm;
        }
        else {
            alarms[this.options.alarm] = this.options.alarm;
        }
        // alarm values should be set as array index to reduce its time complexity from O(n) to O(1)
        // otherwise the execution time might be more than a second which would cause timer being delayed
        if (alarms[Math.floor(distance / 1000)] && typeof this.onAlarm == 'function') {
            console.log('alarm is called');
            this.onAlarm();
        }
        var values = {
            days: Math.floor(distance / (1000 * 60 * 60 * 24)),
            hours: Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
            minutes: Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)),
            secounds: Math.floor((distance % (1000 * 60)) / 1000)
        };
        this.days.text(values.days + this._getIndicator("days"));
        this.hours.text(values.hours + this._getIndicator("hours"));
        this.minutes.text(values.minutes + this._getIndicator("minutes"));
        this.seconds.text(values.secounds + this._getIndicator("seconds"));
        if (this.options.hideEmpties) {
            if (values.days == 0) {
                this.days.hide();
                if (values.hours == 0) {
                    this.hours.hide();
                    if (values.minutes == 0) {
                        this.minutes.hide();
                        if (values.secounds == 0)
                            this.seconds.hide();
                    }
                }
            }
        }
        if (this.options.precision) {
            var units = ['days', 'hours', 'minutes', 'seconds'];
            for (var u = 0; u < 4; ++u) {
                if (values[units[u]]) {
                    for (var n = u + this.options.precision; n < 4; n++) {
                        this[units[n]].hide();
                    }
                    break;
                }
                else {
                    this[units[u]].hide();
                }
            }
        }
        return distance;
    };
    et2_countdown.prototype._getIndicator = function (_v) {
        return this.options.format == 's' ? egw.lang(_v).substr(0, 1) : egw.lang(_v);
    };
    et2_countdown._attributes = {
        format: {
            name: "display format",
            type: "string",
            default: "s",
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
            default: 0,
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
    return et2_countdown;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_countdown = et2_countdown;
et2_core_widget_1.et2_register_widget(et2_countdown, ["countdown"]);
//# sourceMappingURL=et2_widget_countdown.js.map