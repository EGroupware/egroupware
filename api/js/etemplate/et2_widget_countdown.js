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
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_baseWidget;
*/
require("./et2_core_common");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
/**
 * Class which implements the "countdown" XET-Tag
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
        // create a date widget
        _this.time = et2_core_widget_1.et2_createWidget('date-time', {});
        _this.setDOMNode(_this.container[0]);
        return _this;
    }
    et2_countdown.prototype.set_time = function (_time) {
        if (_time == "")
            return;
        this.time.set_value(_time);
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
        var tempDate = new Date();
        var now = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(), tempDate.getHours(), tempDate.getMinutes() - tempDate.getTimezoneOffset(), tempDate.getSeconds());
        var time = new Date(this.time.getValue());
        var distance = time.getTime() - now.getTime();
        if (distance < 0)
            return 0;
        if (this.options.alarm > 0 && this.options.alarm == distance / 1000 && typeof this.onAlarm == 'function') {
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
        return distance;
    };
    et2_countdown.prototype._getIndicator = function (_v) {
        return this.options.format == 's' ? egw.lang(_v).substr(0, 1) : egw.lang(_v);
    };
    et2_countdown._attributes = {
        time: {
            name: "time",
            type: "any",
            default: "",
            description: ""
        },
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
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_countdown = et2_countdown;
et2_core_widget_1.et2_register_widget(et2_countdown, ["countdown"]);
//# sourceMappingURL=et2_widget_countdown.js.map